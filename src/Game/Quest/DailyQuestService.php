<?php
declare(strict_types=1);

namespace Conquer\Game\Quest;

use Conquer\Db\Connection;
use Conquer\Game\Inventory\InventoryService;
use Conquer\Game\Locale;

/**
 * Daily quests and activity rewards reset each UTC day, tracked per player.
 *
 * Quest and milestone definitions are loaded from data/daily_quests.json.
 * The JSON is cached in a static property for the lifetime of the request.
 *
 * DB table: player_daily_quests
 *   player_id, quest_code, quest_date, progress, target, completed, claimed
 *
 * Action → quest_code mapping (used by trackProgress()):
 *   attack_monster   → attack_monster_1, attack_monster_3
 *   upgrade_building → upgrade_building_1
 *   train_troops     → train_troops_100  ($amount = count trained)
 *   research_complete → research_complete_1
 *   gather_resources → collect_resources  ($amount = resource units gathered)
 *   open_chest       → open_chest_1
 *   daily_login      → login_daily        (handled internally by ensureDailyQuests)
 */
final class DailyQuestService
{
    // Static-only helper — no instantiation.
    private function __construct() {}

    // -------------------------------------------------------------------------
    // Data loading
    // -------------------------------------------------------------------------

    /**
     * Parsed quest definitions, keyed by quest code.
     *
     * @var array<string, array<string, mixed>>|null
     */
    private static ?array $definitions = null;
    private static array $milestones = [];
    private static int $maxActivityPoints = 0;

    /**
     * Maps action names to the quest codes they affect.
     *
     * @var array<string, list<string>>
     */
    private const ACTION_QUEST_MAP = [
        'attack_monster'   => ['attack_monster_1', 'attack_monster_3'],
        'upgrade_building' => ['upgrade_building_1'],
        'train_troops'     => ['train_troops_100'],
        'research_complete'=> ['research_complete_1'],
        'gather_resources' => ['collect_resources'],
        'open_chest'       => ['open_chest_1'],
    ];

    /**
     * Loads and caches quest definitions from data/daily_quests.json.
     *
     * @return array<string, array<string, mixed>>  Keyed by quest code.
     * @throws \RuntimeException When the data file is missing or malformed.
     */
    private static function loadDefinitions(): array
    {
        if (self::$definitions !== null) {
            return self::$definitions;
        }

        $path = defined('ROOT_DIR') ? ROOT_DIR . '/data/daily_quests.json' : __DIR__ . '/../../../data/daily_quests.json';

        if (!is_file($path)) {
            throw new \RuntimeException('daily_quests.json not found at: ' . $path);
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException('Cannot read daily_quests.json.');
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        /** @var list<array<string, mixed>> $quests */
        $quests = $decoded['quests'] ?? [];

        $map = [];
        foreach ($quests as $q) {
            $code = (string) $q['code'];
            $map[$code] = $q;
        }

        self::$definitions = $map;
        self::$milestones = array_column($decoded['activity']['milestones'] ?? [], null, 'code');
        self::$maxActivityPoints = (int) ($decoded['activity']['max_points'] ?? 0);
        return self::$definitions;
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Ensures today's quest rows exist for the player (UTC date).
     *
     * Inserts all quests from the JSON definition for today if they are
     * not yet present. Also auto-completes the `login_daily` quest since
     * the very act of being online counts as a daily login.
     *
     * Safe to call multiple times per day — INSERT IGNORE is idempotent.
     */
    public static function ensureDailyQuests(int $playerId): string
    {
        $db          = Connection::getInstance();
        $definitions = self::loadDefinitions();
        // Bind every statement to one database day, including a request crossing midnight.
        $date = (string) $db->query('SELECT UTC_DATE()')->fetchColumn();

        // Seed the whole day in one statement: state polling must not add a round trip per card.
        $values = [];
        $parameters = [];
        foreach ($definitions + self::$milestones as $code => $def) {
            $values[] = '(?, ?, ?, 0, ?, 0, 0)';
            array_push($parameters, $playerId, $code, $date, (int) ($def['target'] ?? 1));
        }
        if ($values) {
            $db->execute('INSERT IGNORE INTO player_daily_quests
                (player_id,quest_code,quest_date,progress,target,completed,claimed) VALUES '.implode(',', $values), $parameters);
        }

        // Auto-complete the login quest.
        self::autoCompleteLoginQuest($playerId, $date);
        return $date;
    }

    /**
     * Returns all daily quests for today (UTC), enriched with static data.
     *
     * @return list<array<string, mixed>>  Each entry contains:
     *   quest_code, title, description, progress, target, completed, claimed, rewards
     */
    public static function getQuests(int $playerId): array
    {
        return self::getState($playerId)['quests'];
    }

    /** Daily rows, derived activity and reset time share the same UTC-date snapshot. */
    public static function getState(int $playerId): array
    {
        $date = self::ensureDailyQuests($playerId);
        $db   = Connection::getInstance();
        $rows = $db->query(
            'SELECT quest_code, progress, target, completed, claimed
             FROM   player_daily_quests
             WHERE  player_id = ? AND quest_date = ?
             ORDER  BY quest_code ASC',
            [$playerId, $date],
        )->fetchAll();

        $definitions = self::loadDefinitions();
        $result      = [];

        foreach ($rows as $row) {
            $code = (string) $row['quest_code'];
            $def  = $definitions[$code] ?? null;
            // Milestones have their own presentation; removed definitions are not claimable.
            if ($def === null) continue;

            $result[] = [
                'quest_code'  => $code,
                'title'       => isset($def['title_key']) ? Locale::t($def['title_key']) : Locale::text((string) ($def['title'] ?? $code)),
                'description' => isset($def['description_key']) ? Locale::t($def['description_key']) : Locale::text((string) ($def['description'] ?? '')),
                'progress'    => (int) $row['progress'],
                'target'      => (int) $row['target'],
                'completed'   => (bool) $row['completed'],
                'claimed'     => (bool) $row['claimed'],
                'activity_points' => (int) ($def['activity_points'] ?? 0),
                'rewards'     => $def['rewards'] ?? [],
            ];
        }

        $points = self::activityPoints($rows);
        $byCode = array_column($rows, null, 'quest_code');
        $milestones = [];
        foreach (self::$milestones as $code => $def) {
            $target = (int) $def['target'];
            $milestones[] = ['quest_code'=>$code, 'target'=>$target, 'progress'=>min($points,$target),
                'completed'=>$points >= $target, 'claimed'=>(bool) ($byCode[$code]['claimed'] ?? false),
                'rewards'=>$def['rewards'] ?? []];
        }
        $reset = (new \DateTimeImmutable($date, new \DateTimeZone('UTC')))->modify('+1 day')->format('Y-m-d 00:00:00');
        return ['quests'=>array_merge($result,StarterMissionService::quests($playerId)),
            'quest_activity'=>['points'=>$points, 'max_points'=>self::$maxActivityPoints, 'milestones'=>$milestones],
            'quest_resets_at'=>$reset];
    }

    /** Only claimed ordinary dailies count; permanent missions and chests never feed activity. */
    private static function activityPoints(array $rows): int
    {
        $points = 0;
        $definitions = self::loadDefinitions();
        foreach ($rows as $row) {
            if (!empty($row['claimed']) && isset($definitions[$row['quest_code']])) {
                $points += (int) ($definitions[$row['quest_code']]['activity_points'] ?? 0);
            }
        }
        return $points;
    }

    /**
     * Tracks action progress for all matching daily quests.
     *
     * Updates progress for every quest linked to the given action.
     * Automatically marks quests as completed when progress >= target.
     *
     * @param int $amount  Units contributed (defaults to 1). For train_troops
     *                     this is the troop count; for gather_resources it is
     *                     the resource total.
     */
    public static function trackProgress(int $playerId, string $action, int $amount = 1): void
    {
        if ($amount <= 0) {
            return;
        }

        $questCodes = self::ACTION_QUEST_MAP[$action] ?? [];

        if ($questCodes === []) {
            return;
        }

        $date = self::ensureDailyQuests($playerId);
        $db = Connection::getInstance();

        foreach ($questCodes as $code) {
            // Add progress, capped at target — single atomic UPDATE.
            $db->execute(
                'UPDATE player_daily_quests
                 SET    progress = LEAST(progress + ?, target)
                 WHERE  player_id  = ?
                   AND  quest_code = ?
                    AND  quest_date = ?
                   AND  completed  = 0',
                [$amount, $playerId, $code, $date],
            );

            // Mark completed where progress has reached or exceeded target.
            $db->execute(
                'UPDATE player_daily_quests
                 SET    completed = 1
                 WHERE  player_id  = ?
                   AND  quest_code = ?
                    AND  quest_date = ?
                   AND  completed  = 0
                   AND  progress  >= target',
                [$playerId, $code, $date],
            );
        }
    }

    /**
     * Claims the reward for a completed, unclaimed quest.
     *
     * @return list<array<string, mixed>>  The rewards array from the JSON definition.
     * @throws \RuntimeException On validation failure (not completed, already claimed, etc.)
     */
    public static function claimReward(int $playerId, string $questCode): array
    {
        if (str_starts_with($questCode,'starter_')) return StarterMissionService::claim($playerId,$questCode);
        // Both the kingdom action and the legacy quest endpoint use this path.
        // Keep the claim marker and every item/currency grant in one transaction.
        return Connection::getInstance()->transaction(
            static fn(): array => self::claimRewardInTransaction($playerId, $questCode),
        );
    }

    /** @return list<array<string, mixed>> */
    private static function claimRewardInTransaction(int $playerId, string $questCode): array
    {
        $db = Connection::getInstance();

        // Both endpoints serialize ordinary and activity claims using the same player row.
        if (!$db->query('SELECT id FROM players WHERE id=? FOR UPDATE', [$playerId])->fetchColumn()) {
            throw new \RuntimeException('Player not found.');
        }
        $date = self::ensureDailyQuests($playerId);
        $definitions = self::loadDefinitions();
        $isMilestone = isset(self::$milestones[$questCode]);
        $def = $definitions[$questCode] ?? self::$milestones[$questCode] ?? null;
        if ($def === null) throw new \RuntimeException('Quest-Definition nicht gefunden: ' . $questCode);

        // Load the quest row with a lock to prevent double-claiming.
        $row = $db->query(
            'SELECT completed, claimed, target
             FROM   player_daily_quests
             WHERE  player_id  = ?
                AND  quest_code = ?
                AND  quest_date = ?
             FOR UPDATE',
            [$playerId, $questCode, $date],
        )->fetch();

        if ($row === false) {
            throw new \RuntimeException('Quest nicht gefunden oder gehört nicht zum heutigen Tag.');
        }

        if ($isMilestone) {
            // A locking read sees claims committed before our player lock, even in an outer transaction.
            $dailyRows = $db->query('SELECT quest_code, claimed FROM player_daily_quests WHERE player_id=? AND quest_date=? ORDER BY quest_code FOR UPDATE', [$playerId, $date])->fetchAll();
            if (self::activityPoints($dailyRows) < (int) $def['target']) {
                throw new \RuntimeException(Locale::t('quests.error.activity_incomplete'));
            }
        } elseif (!(bool) $row['completed']) {
            throw new \RuntimeException('Quest noch nicht abgeschlossen.');
        }

        if ((bool) $row['claimed']) {
            throw new \RuntimeException('Belohnung wurde bereits abgeholt.');
        }

        /** @var list<array<string, mixed>> $rewards */
        $rewards = $def['rewards'] ?? [];

        // Reserve the claim; any failed grant also rolls back this marker.
        $affected = $db->execute(
            'UPDATE player_daily_quests
             SET    claimed = 1, completed = 1, progress = IF(? = 1, target, progress)
             WHERE  player_id  = ?
               AND  quest_code = ?
                AND  quest_date = ?
                AND  (completed = 1 OR ? = 1)
               AND  claimed    = 0',
            [$isMilestone ? 1 : 0, $playerId, $questCode, $date, $isMilestone ? 1 : 0],
        );

        if ($affected === 0) {
            // Race condition — another request already claimed this.
            throw new \RuntimeException('Belohnung wurde bereits abgeholt (Konflikt).');
        }

        // Distribute rewards.
        self::distributeRewards($playerId, $rewards,['source_type'=>'daily_quest','source_key'=>$questCode,'reference'=>'quest:'.$questCode.':'.$date]);

        return $rewards;
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Auto-completes the login_daily quest for today if it exists and is
     * not yet completed.
     */
    private static function autoCompleteLoginQuest(int $playerId, string $date): void
    {
        $db = Connection::getInstance();

        // Set progress = target and completed = 1 in one shot.
        $db->execute(
            'UPDATE player_daily_quests
             SET    progress  = target,
                    completed = 1
             WHERE  player_id  = ?
               AND  quest_code = ?
                AND  quest_date = ?
               AND  completed  = 0',
            [$playerId, 'login_daily', $date],
        );
    }

    /**
     * Distributes a rewards array to the player.
     *
     * Supported reward keys:
     *   gems      — added directly to players.gems
     *   item_code — added to player_inventory via InventoryService
     *
     * @param list<array<string, mixed>> $rewards
     */
    private static function distributeRewards(int $playerId, array $rewards,array $rewardContext=[]): void
    {
        $db = Connection::getInstance();

        foreach ($rewards as $reward) {
            if (isset($reward['gems'])) {
                $gems = (int) $reward['gems'];
                if ($gems > 0) {
                    $db->execute(
                        'UPDATE players SET gems = gems + ? WHERE id = ?',
                        [$gems, $playerId],
                    );
                    \Conquer\Admin\RewardLedger::resources($playerId,\Conquer\Game\World\WorldContext::id(),['gems'=>$gems],$rewardContext);
                }
            }

            if (isset($reward['item_code'])) {
                $itemCode = (int) $reward['item_code'];
                $quantity = (int) ($reward['quantity'] ?? 1);

                if ($itemCode > 0 && $quantity > 0) {
                    InventoryService::addItems($playerId, $itemCode, $quantity,null,$rewardContext);
                }
            }
        }
    }
}
