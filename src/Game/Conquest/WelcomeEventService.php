<?php
declare(strict_types=1);
namespace Conquer\Game\Conquest;

use Conquer\Db\Connection;
use Conquer\Game\Inventory\InventoryService;
use Conquer\Game\Locale;
use Conquer\Game\World\WorldContext;

/** Once-per-world rewards backed by distinct UTC visits and completed game state. */
final class WelcomeEventService
{
    private static ?array $definitions = null;

    /** A kingdom state visit counts once per UTC date, even across devices or retries. */
    public static function state(int $playerId): array
    {
        $city = WorldContext::city($playerId);
        try {
            return Connection::getInstance()->transaction(static function (Connection $db) use ($playerId, $city): array {
                $visit = self::visit($db, $playerId);
                return self::snapshot($db, $playerId, (int)$city['id'], $visit);
            });
        } catch (\PDOException $e) {
            // Deploying code before the additive migration must not break the game.
            if ((int)($e->errorInfo[1] ?? 0) !== 1146) throw $e;
            return ['available'=>false, 'code'=>'welcome', 'claimable_count'=>0];
        }
    }

    public static function claim(int $playerId, mixed $milestoneCode): array
    {
        $definitions = self::definitions();
        if (!is_string($milestoneCode) || !isset($definitions[$milestoneCode])) {
            throw new \DomainException(Locale::t('welcome_event.error.unknown'));
        }
        WorldContext::assertActionAvailable();
        try {
            return Connection::getInstance()->transaction(static function (Connection $db) use ($playerId, $milestoneCode, $definitions): array {
                // The city and campaign locks serialize direct callers as well as API receipts.
                $city = WorldContext::city($playerId, null, true);
                $visit = self::visit($db, $playerId);
                $worldId = WorldContext::id();
                if ($db->query('SELECT 1 FROM player_welcome_event_claims WHERE player_id=? AND world_id=? AND milestone_code=? FOR UPDATE', [$playerId, $worldId, $milestoneCode])->fetchColumn()) {
                    throw new \DomainException(Locale::t('welcome_event.error.claimed'));
                }
                $snapshot = self::snapshot($db, $playerId, (int)$city['id'], $visit);
                $milestones = array_column(array_merge($snapshot['login_rewards'], $snapshot['growth_rewards']), null, 'code');
                if (empty($milestones[$milestoneCode]['completed'])) {
                    throw new \DomainException(Locale::t('welcome_event.error.incomplete'));
                }
                $rewards = $definitions[$milestoneCode]['rewards'];
                // Validate every item before reserving the claim; grants must never silently vanish.
                foreach ($rewards as $reward) {
                    if (!InventoryService::isDropEligible((int)$reward['item_code']) || (int)$reward['quantity'] < 1) {
                        throw new \DomainException(Locale::t('welcome_event.error.unavailable'));
                    }
                }
                $db->execute('INSERT INTO player_welcome_event_claims(player_id,world_id,milestone_code,reward_json) VALUES(?,?,?,?)', [$playerId, $worldId, $milestoneCode, json_encode($rewards, JSON_THROW_ON_ERROR)]);
                foreach ($rewards as $reward) {
                    InventoryService::addItems($playerId, (int)$reward['item_code'], (int)$reward['quantity'], $worldId,['source_type'=>'welcome_event','source_key'=>$milestoneCode,'reference'=>'milestone:'.$milestoneCode]);
                }
                return ['message'=>Locale::t('welcome_event.claimed'), 'rewards'=>$rewards, 'milestone_code'=>$milestoneCode];
            });
        } catch (\PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) !== 1146) throw $e;
            throw new \DomainException(Locale::t('welcome_event.error.unavailable'), 503);
        }
    }

    /** Caller holds a transaction. The database clock defines the whole UTC visit. */
    private static function visit(Connection $db, int $playerId): array
    {
        $clock = $db->query('SELECT UTC_TIMESTAMP() AS now, UTC_DATE() AS today, DATE_ADD(UTC_DATE(), INTERVAL 1 DAY) AS next_visit_at')->fetch();
        $worldId = WorldContext::id();
        $db->execute('INSERT IGNORE INTO player_welcome_events(player_id,world_id,started_at,last_visit_date,visit_days) VALUES(?,?,?,?,1)', [$playerId, $worldId, $clock['now'], $clock['today']]);
        $visit = $db->query('SELECT started_at,last_visit_date,visit_days FROM player_welcome_events WHERE player_id=? AND world_id=? FOR UPDATE', [$playerId, $worldId])->fetch();
        if ($visit['last_visit_date'] < $clock['today']) {
            // Missing dates never count; a week away awards exactly one new visit.
            $visit['visit_days'] = min(7, (int)$visit['visit_days'] + 1);
            $visit['last_visit_date'] = $clock['today'];
            $db->execute('UPDATE player_welcome_events SET visit_days=?,last_visit_date=? WHERE player_id=? AND world_id=?', [$visit['visit_days'], $visit['last_visit_date'], $playerId, $worldId]);
        }
        return $visit + $clock;
    }

    private static function snapshot(Connection $db, int $playerId, int $cityId, array $visit): array
    {
        $worldId = WorldContext::id();
        $claimed = $db->query('SELECT milestone_code FROM player_welcome_event_claims WHERE player_id=? AND world_id=?', [$playerId, $worldId])->fetchAll(\PDO::FETCH_COLUMN);
        $progress = [
            'visit_days'=>(int)$visit['visit_days'],
            'castle'=>(int)$db->query("SELECT level FROM city_buildings WHERE city_id=? AND building_code='castle'", [$cityId])->fetchColumn(),
            'trained'=>(int)$db->query('SELECT COALESCE(SUM(count),0) FROM troop_queue WHERE city_id=? AND is_processed=1 AND finishes_at<=?', [$cityId, $visit['now']])->fetchColumn(),
            'research'=>(int)$db->query('SELECT COALESCE(SUM(level),0) FROM player_research WHERE player_id=? AND world_id=?', [$playerId, $worldId])->fetchColumn(),
        ];
        $groups = ['login_rewards'=>[], 'growth_rewards'=>[]];
        $claimable = 0;
        foreach (self::definitions() as $code=>$definition) {
            $target = (int)$definition['target'];
            $value = min($target, $progress[$definition['metric']]);
            $isClaimed = in_array($code, $claimed, true);
            $complete = $value >= $target;
            if ($complete && !$isClaimed) $claimable++;
            $login = $definition['metric'] === 'visit_days';
            $parameters = ['count'=>$target];
            $row = ['code'=>$code,
                'title'=>Locale::t($login ? 'welcome_event.day' : 'welcome_event.'.$definition['metric'].'.title', $parameters),
                'description'=>Locale::t('welcome_event.'.$definition['metric'].'.description', $parameters),
                'progress'=>$value, 'target'=>$target, 'completed'=>$complete, 'claimed'=>$isClaimed,
                'rewards'=>$definition['rewards']];
            if (isset($definition['navigation'])) $row['navigation'] = $definition['navigation'];
            $groups[$login ? 'login_rewards' : 'growth_rewards'][] = $row;
        }
        return ['available'=>true, 'code'=>'welcome', 'player_id'=>$playerId, 'world_id'=>$worldId,
            'title'=>Locale::t('welcome_event.title'), 'description'=>Locale::t('welcome_event.description'),
            'rules'=>Locale::t('welcome_event.rules'), 'started_at'=>$visit['started_at'],
            'visit_days'=>(int)$visit['visit_days'], 'visited_today'=>$visit['last_visit_date'] === $visit['today'],
            'next_visit_at'=>$visit['next_visit_at'].' 00:00:00', 'claimable_count'=>$claimable] + $groups;
    }

    private static function definitions(): array
    {
        if (self::$definitions !== null) return self::$definitions;
        $data = json_decode((string)file_get_contents(ROOT_DIR.'/data/welcome_event.json'), true, 32, JSON_THROW_ON_ERROR);
        return self::$definitions = array_column($data['milestones'], null, 'code');
    }
}
