<?php
declare(strict_types=1);

namespace Conquer\Game\Expedition;

use Conquer\Db\Connection;
use Conquer\Game\City\CityState;
use Conquer\Game\City\ResourceTick;
use Conquer\Game\City\TroopData;
use Conquer\Game\March\MarchSkinService;
use Conquer\Game\Research\BuffEngine;
use Conquer\Game\World\WorldContext;

/**
 * Durable coalition encounter. Lock order is player -> coordination -> encounter.
 * Encounter settlement never locks or writes another player's city. Troop returns
 * are a separate transaction under that owner's player lock, shared with marches.
 */
final class ExpeditionService
{
    public static function state(int $playerId): array
    {
        return self::locked('conquer-player-' . $playerId, function () use ($playerId): array {
            self::tick($playerId);
            CityState::loadForPlayer($playerId);
            return self::snapshot($playerId);
        });
    }

    public static function action(int $playerId, array $body): array
    {
        try{WorldContext::current($body['expected_world_id']??null);WorldContext::assertActionAvailable();}
        catch(\DomainException $e){throw new ExpeditionException('WORLD_RULE',$e->getMessage(),$e->getCode()?:422);}
        return self::locked('conquer-player-' . $playerId, function () use ($playerId, $body): array {
            self::tick($playerId);
            $action = $body['action'] ?? '';
            if (!is_string($action) || !in_array($action, ['create', 'invite', 'accept', 'decline', 'supply', 'dispatch', 'claim', 'cancel'], true)) {
                throw new ExpeditionException('INVALID_ACTION', 'Diese Expeditionsaktion ist unbekannt.');
            }
            // Settle and persist this owner's production before consuming or crediting
            // resources, while holding the same lock as ordinary city actions.
            $cityState = CityState::loadForPlayer($playerId);
            if ($cityState === null) { throw new ExpeditionException('NO_CITY', 'Du benötigst zuerst eine Stadt.', 404); }
            $db = Connection::getInstance();
            if (in_array($action, ['supply', 'claim'], true)) {
                ResourceTick::persist($cityState['city'], $cityState['buildings']);
            }
            $operation = function () use ($db, $playerId, $body, $action, $cityState): array {
                if ($action === 'create') {
                    return $db->transaction(fn () => self::create($playerId, $body));
                }
                $id = self::positiveId($body['expedition_id'] ?? null, 'expedition_id');
                return self::locked('conquer-expedition-' . $id, function () use ($db, $playerId, $body, $action, $id, $cityState): array {
                    return $db->transaction(function () use ($db, $playerId, $body, $action, $id, $cityState): array {
                        $raid = $db->query('SELECT * FROM expeditions WHERE id = ? AND world_id=? FOR UPDATE', [$id,WorldContext::id()])->fetch();
                        if (!$raid) { throw new ExpeditionException('NOT_FOUND', 'Dieser Feldzug wurde nicht gefunden.', 404); }
                        $member = self::membership($playerId);
                        if ($action !== 'claim' && (!ExpeditionRules::active($raid['phase']) || strtotime($raid['expires_at'] . ' UTC') <= time())) {
                            throw new ExpeditionException('EXPEDITION_CLOSED', 'Dieser Feldzug ist bereits abgeschlossen.');
                        }
                        $message = match ($action) {
                            'invite' => self::invite($raid, $member, $playerId, $body),
                            'accept' => self::accept($raid, $member, $playerId),
                            'decline' => self::decline($raid, $member, $playerId),
                            'supply' => self::supply($raid, $member, $playerId, (int) $cityState['city']['id'], $body),
                            'dispatch' => self::dispatch($raid, $member, $playerId, (int) $cityState['city']['id'], $body),
                            'claim' => self::claim($raid, $playerId),
                            'cancel' => self::cancel($raid, $member, $playerId),
                        };
                        return ['expedition_id' => $id, 'message' => $message];
                    });
                });
            };
            // Coalition coordination serializes the "one active raid per alliance"
            // check across independently locked encounters and concurrent leaders.
            $result = in_array($action, ['create', 'invite', 'accept'], true)
                ? self::locked('conquer-expedition-coalitions', $operation) : $operation();
            $result['state'] = self::snapshot($playerId);
            return $result;
        });
    }

    /** Browser and cron execute the identical arrival/expiry/return settlement. */
    public static function tick(?int $playerId = null): void
    {
        $db = Connection::getInstance();
        $ids = $db->query("SELECT id FROM expeditions e
            WHERE (phase IN ('planning','preparation','boss') AND expires_at <= UTC_TIMESTAMP())
               OR EXISTS (SELECT 1 FROM expedition_missions m WHERE m.expedition_id=e.id AND m.status='marching' AND m.arrival_at<=UTC_TIMESTAMP())
            ORDER BY id LIMIT 100")->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            self::locked('conquer-expedition-' . $id, function () use ($db, $id): void {
                $db->transaction(function () use ($db, $id): void {
                    $raid = $db->query('SELECT * FROM expeditions WHERE id=? FOR UPDATE', [(int) $id])->fetch();
                    if (!$raid) { return; }
                    $missions = $db->query("SELECT * FROM expedition_missions WHERE expedition_id=? AND status='marching' AND arrival_at<=UTC_TIMESTAMP() ORDER BY arrival_at,id FOR UPDATE", [(int) $id])->fetchAll();
                    foreach ($missions as $mission) {
                        self::resolve($raid, $mission);
                        $raid = $db->query('SELECT * FROM expeditions WHERE id=?', [(int) $id])->fetch();
                    }
                    if (ExpeditionRules::active($raid['phase']) && strtotime($raid['expires_at'] . ' UTC') <= time()) {
                        $db->execute("UPDATE expeditions SET phase='expired',completed_at=expires_at WHERE id=?", [(int) $id]);
                        $db->execute("UPDATE expedition_missions SET status='returning',return_at=LEAST(return_at,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 20 SECOND)) WHERE expedition_id=? AND status='marching'", [(int) $id]);
                        self::log((int) $id, null, 'expired', 'Das Teilnahmefenster ist abgelaufen. Der Feldzug kann erneut geplant werden; alle Truppen kehren zurück.');
                    }
                });
            });
        }
        $players = $playerId === null
            ? $db->query("SELECT DISTINCT player_id FROM expedition_missions WHERE status='returning' AND return_at<=UTC_TIMESTAMP()")->fetchAll(\PDO::FETCH_COLUMN)
            : [$playerId];
        foreach ($players as $ownerId) { self::returnForPlayer((int) $ownerId); }
    }

    private static function create(int $playerId, array $body): array
    {
        $member = self::membership($playerId);
        self::leader($member);
        if (self::activeForAlliance($member['id'])) {
            throw new ExpeditionException('ALLIANCE_BUSY', 'Deine Allianz hat bereits einen aktiven Feldzug.');
        }
        $name = $body['name'] ?? 'Der Aschenfürst';
        if (!is_string($name) || mb_strlen(trim($name)) < 3 || mb_strlen(trim($name)) > 80 || preg_match('/[\x00-\x1f]/u', $name)) {
            throw new ExpeditionException('INVALID_NAME', 'Der Feldzugsname benötigt 3 bis 80 Zeichen.');
        }
        $db = Connection::getInstance();
        $city=$db->query('SELECT castle_level,world_id FROM cities WHERE player_id=? AND world_id=' . WorldContext::id() . '',[$playerId])->fetch();
        $chapter=max(1,(int)$db->query('SELECT chapter FROM world_chapters WHERE world_id=?',[$city['world_id']])->fetchColumn());
        $boss=$body['boss_code']??'ashen_lord';$difficulty=$body['difficulty']??'normal';
        if(!is_string($boss)||!is_string($difficulty))throw new ExpeditionException('INVALID_ENCOUNTER','Ungültige Begegnung.');
        $rules=EncounterCatalog::create($boss,$difficulty,(int)$city['castle_level'],$chapter);
        $db->execute("INSERT INTO expeditions(world_id,name,boss_code,difficulty,encounter_rules,boss_hp,host_alliance_id,created_by,expires_at) VALUES(?,?,?,?,?,?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 24 HOUR))", [(int)$city['world_id'],trim($name),$boss,$difficulty,json_encode($rules,JSON_THROW_ON_ERROR),$rules['hp'],$member['id'],$playerId]);
        $id = $db->lastInsertId();
        self::log($id, $playerId, 'created', '[' . $member['tag'] . '] plant einen Feldzug gegen den Aschenfürsten. Eine zweite Allianz muss die Einladung bestätigen.');
        return ['expedition_id' => $id, 'message' => 'Feldzug erstellt. Lade jetzt eine Partnerallianz ein.'];
    }

    private static function invite(array $raid, ?array $member, int $playerId, array $body): string
    {
        self::leader($member, (int) $raid['host_alliance_id']);
        if ($raid['phase'] !== 'planning' || $raid['invitation_status'] === 'pending') {
            throw new ExpeditionException('INVITATION_PENDING', 'Warte auf die offene Einladung oder beende diesen Feldzug.');
        }
        $allianceId = self::positiveId($body['alliance_id'] ?? null, 'alliance_id');
        $db = Connection::getInstance();
        $alliance = $db->query('SELECT id,name,tag FROM alliances WHERE id=? AND world_id=?', [$allianceId, (int) $raid['world_id']])->fetch();
        if (!$alliance || $allianceId === $member['id']) { throw new ExpeditionException('INVALID_ALLIANCE', 'Wähle eine andere Allianz aus derselben Welt.'); }
        if (self::activeForAlliance($allianceId)) { throw new ExpeditionException('ALLIANCE_BUSY', 'Diese Allianz nimmt bereits an einem aktiven Feldzug teil.'); }
        $db->execute("UPDATE expeditions SET guest_alliance_id=?,invitation_status='pending' WHERE id=?", [$allianceId, (int) $raid['id']]);
        self::log((int) $raid['id'], $playerId, 'invited', '[' . $alliance['tag'] . '] wurde zur Koalition eingeladen.');
        return 'Einladung gespeichert. Die Partnerallianz bestätigt den Feldzug in ihrem Expeditionsfenster.';
    }

    private static function accept(array $raid, ?array $member, int $playerId): string
    {
        self::leader($member, (int) $raid['guest_alliance_id']);
        if ($raid['phase'] !== 'planning' || $raid['invitation_status'] !== 'pending') { throw new ExpeditionException('NO_INVITATION', 'Es liegt keine offene Einladung vor.'); }
        if (self::activeForAlliance($member['id'], (int) $raid['id'])) { throw new ExpeditionException('ALLIANCE_BUSY', 'Deine Allianz nimmt bereits an einem anderen Feldzug teil.'); }
        Connection::getInstance()->execute("UPDATE expeditions SET invitation_status='accepted',phase='preparation' WHERE id=?", [(int) $raid['id']]);
        self::log((int) $raid['id'], $playerId, 'accepted', '[' . $member['tag'] . '] hat die Koalition bestätigt. Schutzanlagen, Pass und Versorgung müssen gemeinsam gesichert werden.');
        return 'Koalition bestätigt. Die Vorbereitung beginnt.';
    }

    private static function decline(array $raid, ?array $member, int $playerId): string
    {
        self::leader($member, (int) $raid['guest_alliance_id']);
        if ($raid['phase'] !== 'planning' || $raid['invitation_status'] !== 'pending') { throw new ExpeditionException('NO_INVITATION', 'Es liegt keine offene Einladung vor.'); }
        Connection::getInstance()->execute("UPDATE expeditions SET invitation_status='declined' WHERE id=?", [(int) $raid['id']]);
        self::log((int) $raid['id'], $playerId, 'declined', '[' . $member['tag'] . '] hat die Einladung abgelehnt. Eine andere Partnerallianz kann eingeladen werden.');
        return 'Einladung abgelehnt.';
    }

    private static function supply(array $raid, ?array $member, int $playerId, int $cityId, array $body): string
    {
        self::participant($raid, $member, $playerId, $cityId);
        if ($raid['phase'] !== 'preparation') { throw new ExpeditionException('WRONG_PHASE', 'Versorgung wird während der Vorbereitung benötigt.'); }
        $amount = $body['amount'] ?? null;
        if (!is_int($amount) || $amount < 10 || $amount > EncounterCatalog::rules($raid)['supplies'] || $amount % 10 !== 0) {
            throw new ExpeditionException('INVALID_AMOUNT', 'Liefere Nahrung in Zehnerschritten bis zum angezeigten Versorgungsziel.');
        }
        $amount = min($amount, EncounterCatalog::rules($raid)['supplies'] - (int) $raid['supplies']);
        if ($amount <= 0) { throw new ExpeditionException('OBJECTIVE_COMPLETE', 'Das Versorgungslager ist bereits gefüllt.'); }
        $db = Connection::getInstance();
        if ($db->execute('UPDATE cities SET food=food-? WHERE id=? AND player_id=? AND food>=?', [$amount, $cityId, $playerId, $amount]) !== 1) {
            throw new ExpeditionException('INSUFFICIENT_RESOURCES', 'Deine Stadt hat nicht genügend Nahrung.');
        }
        $db->execute('UPDATE expeditions SET supplies=supplies+? WHERE id=?', [$amount, (int) $raid['id']]);
        $db->execute('UPDATE expedition_participants SET supplies=supplies+?,contribution=contribution+? WHERE expedition_id=? AND player_id=?', [$amount, intdiv($amount, 10), (int) $raid['id'], $playerId]);
        self::log((int) $raid['id'], $playerId, 'supply', self::username($playerId) . ' liefert ' . $amount . ' Nahrung.');
        self::advance((int) $raid['id']);
        return $amount . ' Nahrung geliefert. Dein Beitrag wurde gespeichert.';
    }

    private static function dispatch(array $raid, ?array $member, int $playerId, int $cityId, array $body): string
    {
        self::participant($raid, $member, $playerId, $cityId);
        $objective = $body['objective'] ?? '';
        $expectedAlliance = match ($objective) {
            'defenses' => (int) $raid['host_alliance_id'],
            'pass' => (int) $raid['guest_alliance_id'],
            'boss' => $member['id'],
            default => throw new ExpeditionException('INVALID_OBJECTIVE', 'Wähle Schutzanlagen, Pass oder den Boss.'),
        };
        if ($member['id'] !== $expectedAlliance) { throw new ExpeditionException('WRONG_ROLE', 'Diese Aufgabe übernimmt die andere Allianz.', 403); }
        $expectedPhase = $objective === 'boss' ? 'boss' : 'preparation';
        if ($raid['phase'] !== $expectedPhase) { throw new ExpeditionException('WRONG_PHASE', 'Dieses Ziel ist in der aktuellen Phase nicht verfügbar.'); }
        $remaining = match ($objective) {
            'defenses' => EncounterCatalog::rules($raid)['defenses'] - (int) $raid['defenses'],
            'pass' => EncounterCatalog::rules($raid)['pass'] - (int) $raid['pass_progress'],
            'boss' => (int) $raid['boss_hp'],
        };
        if ($remaining <= 0) { throw new ExpeditionException('OBJECTIVE_COMPLETE', 'Dieses Ziel ist bereits abgeschlossen.'); }
        $buffs = BuffEngine::getBuffs($playerId);
        $skinSnapshot = MarchSkinService::dispatchSnapshot($playerId);
        $timing = ExpeditionRules::missionTiming($buffs,MarchSkinService::speedMultiplier($skinSnapshot));
        $army = ExpeditionRules::troops($body['troops'] ?? null, ExpeditionRules::missionCapacity($buffs));
        $army['damage'] = EncounterCatalog::damage($raid,$army['troops'], $objective, $buffs);
        $db = Connection::getInstance();
        $active = (int) $db->query("SELECT COUNT(*) FROM expedition_missions WHERE player_id=? AND city_id=? AND status IN ('marching','returning')", [$playerId,$cityId])->fetchColumn();
        if ($active >= 2) { throw new ExpeditionException('MARCH_LIMIT', 'Zwei Expeditionsarmeen sind unterwegs. Warte auf ihre Rückkehr.'); }
        if (strtotime($raid['expires_at'] . ' UTC') <= time() + $timing['march_seconds']) {
            throw new ExpeditionException('TOO_LATE', 'Die Armee würde erst nach Ende des Feldzugs eintreffen.');
        }
        foreach ($army['troops'] as $code => $count) {
            if ($db->execute('UPDATE city_troops SET count=count-? WHERE city_id=? AND troop_code=? AND count>=?', [$count, $cityId, $code, $count]) !== 1) {
                throw new ExpeditionException('INSUFFICIENT_TROOPS', 'Nicht genügend verfügbare Truppen. Bereits entsandte Armeen sind bis zur Rückkehr gebunden.');
            }
        }
        $db->execute("INSERT INTO expedition_missions(expedition_id,player_id,city_id,alliance_id,objective,march_skin,march_speed_bonus_pct,troops_json,potential_damage,arrival_at,return_at)
            VALUES(?,?,?,?,?,?,?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND),DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND))",
            [(int) $raid['id'], $playerId, $cityId, $member['id'], $objective, $skinSnapshot['march_skin'], $skinSnapshot['bonus_pct'], json_encode($army['troops'], JSON_THROW_ON_ERROR), $army['damage'], $timing['march_seconds'], $timing['march_seconds']+$timing['return_seconds']]);
        $label = ['defenses' => 'Schutzanlagen', 'pass' => 'Passstellung', 'boss' => 'Aschenfürst'][$objective];
        self::log((int) $raid['id'], $playerId, 'dispatch', self::username($playerId) . ' entsendet ' . array_sum($army['troops']) . ' Truppen: ' . $label . '.');
        return 'Armee entsandt. Ankunft in ' . $timing['march_seconds'] . ' Sekunden, Rückkehr nach weiteren ' . $timing['return_seconds'] . ' Sekunden.';
    }

    private static function resolve(array $raid, array $mission): void
    {
        $db = Connection::getInstance();
        $id = (int) $raid['id'];
        $damage = 0;
        // An offline browser must not discard orders that arrived before expiry.
        if (ExpeditionRules::active($raid['phase']) && $mission['arrival_at'] <= $raid['expires_at']) {
            $objective = $mission['objective'];
            $valid = ($objective === 'boss' && $raid['phase'] === 'boss')
                || ($objective !== 'boss' && $raid['phase'] === 'preparation');
            if ($valid) {
                $remaining = match ($objective) {
                    'defenses' => EncounterCatalog::rules($raid)['defenses'] - (int) $raid['defenses'],
                    'pass' => EncounterCatalog::rules($raid)['pass'] - (int) $raid['pass_progress'],
                    'boss' => (int) $raid['boss_hp'],
                };
                $damage = max(0, min($remaining, (int) $mission['potential_damage']));
                if ($damage > 0) {
                    $raidColumn = ['defenses' => 'defenses', 'pass' => 'pass_progress', 'boss' => 'boss_hp'][$objective];
                    $sign = $objective === 'boss' ? '-' : '+';
                    $participantColumn = ['defenses' => 'defenses', 'pass' => 'pass_progress', 'boss' => 'boss_damage'][$objective];
                    // Column identifiers are from fixed, server-owned maps.
                    $db->execute("UPDATE expeditions SET $raidColumn=$raidColumn$sign? WHERE id=?", [$damage, $id]);
                    $db->execute("UPDATE expedition_participants SET $participantColumn=$participantColumn+?,contribution=contribution+? WHERE expedition_id=? AND player_id=?", [$damage, $damage, $id, (int) $mission['player_id']]);
                }
            }
        }
        $db->execute("UPDATE expedition_missions SET status='returning',damage=? WHERE id=? AND status='marching'", [$damage, (int) $mission['id']]);
        $message = self::username((int) $mission['player_id']) . ': ' . ($damage > 0 ? $damage . ' wirksamer Kampfbeitrag.' : 'Ziel bereits erledigt oder Feldzug beendet. Die Armee kehrt vollständig zurück.');
        self::log($id, (int) $mission['player_id'], 'battle', $message);
        self::advance($id);
    }

    private static function advance(int $id): void
    {
        $db = Connection::getInstance();
        $raid = $db->query('SELECT * FROM expeditions WHERE id=?', [$id])->fetch();
        if ($raid['phase'] === 'preparation' && (int) $raid['supplies'] >= EncounterCatalog::rules($raid)['supplies']
            && (int) $raid['defenses'] >= EncounterCatalog::rules($raid)['defenses'] && (int) $raid['pass_progress'] >= EncounterCatalog::rules($raid)['pass']) {
            $db->execute("UPDATE expeditions SET phase='boss' WHERE id=?", [$id]);
            self::log($id, null, 'phase', 'Beide Allianzen haben ihre Aufgaben erfüllt. Versorgung gesichert! Der Aschenfürst ist jetzt verwundbar.');
        } elseif ($raid['phase'] === 'boss' && (int) $raid['boss_hp'] === 0) {
            $db->execute("UPDATE expeditions SET phase='victory',completed_at=UTC_TIMESTAMP() WHERE id=?", [$id]);
            $participants=$db->query('SELECT player_id,boss_damage FROM expedition_participants WHERE expedition_id=? AND boss_damage>0 ORDER BY player_id',[$id])->fetchAll();
            $total=max(1,array_sum(array_column($participants,'boss_damage')));
            $pool=500*max(1,(int)EncounterCatalog::rules($raid)['chapter']);
            foreach($participants as $p) {
                $xp=(int)floor($pool*(int)$p['boss_damage']/$total);
                \Conquer\Game\Player\LordLevel::addXp((int)$p['player_id'],$xp,(int)$raid['world_id'],'expedition:'.$id);
            }
            $db->execute('INSERT INTO world_chapters(world_id,chapter) VALUES(?,?) ON DUPLICATE KEY UPDATE chapter=GREATEST(chapter,VALUES(chapter))',[(int)$raid['world_id'],EncounterCatalog::rules($raid)['chapter']+1]);
            self::log($id, null, 'victory', 'Der Aschenfürst ist besiegt! Teilnehmer ab 10 Beitrag können ihre persönliche Beute abholen. Alle eingesetzten Truppen kehren zurück.');
        }
    }

    private static function returnForPlayer(int $playerId): void
    {
        self::locked('conquer-player-' . $playerId, function () use ($playerId): void {
            $db = Connection::getInstance();
            $db->transaction(function () use ($db, $playerId): void {
                $missions = $db->query("SELECT * FROM expedition_missions WHERE player_id=? AND status='returning' AND return_at<=UTC_TIMESTAMP() ORDER BY id FOR UPDATE", [$playerId])->fetchAll();
                foreach ($missions as $mission) {
                    // Status and troop addition commit together: poll retries and
                    // overlapping workers cannot mint a second returning army.
                    $db->execute("UPDATE expedition_missions SET status='returned' WHERE id=?", [(int) $mission['id']]);
                    $troops = json_decode($mission['troops_json'], true, 32, JSON_THROW_ON_ERROR);
                    foreach ($troops as $code => $count) {
                        $db->execute('INSERT INTO city_troops(city_id,troop_code,count) VALUES(?,?,?) ON DUPLICATE KEY UPDATE count=count+VALUES(count)', [(int) $mission['city_id'], (int) $code, (int) $count]);
                    }
                }
            });
        });
    }

    private static function claim(array $raid, int $playerId): string
    {
        $db = Connection::getInstance();
        $participant = $db->query('SELECT * FROM expedition_participants WHERE expedition_id=? AND player_id=? FOR UPDATE', [(int) $raid['id'], $playerId])->fetch();
        if (!$participant || $raid['phase'] !== 'victory' || (int) $participant['contribution'] < ExpeditionRules::MIN_CONTRIBUTION) {
            throw new ExpeditionException('NO_REWARD', 'Beute benötigt einen Sieg und mindestens 10 wirksamen Beitrag.');
        }
        if ((bool) $participant['reward_claimed']) { throw new ExpeditionException('ALREADY_CLAIMED', 'Du hast diese Beute bereits erhalten.'); }
        $blocked = self::rewardBlockedUntil($playerId);
        if ($blocked !== null) { throw new ExpeditionException('REWARD_COOLDOWN', 'Eine persönliche Bossbeute pro Stunde. Dein Anspruch bleibt erhalten; verfügbar ab ' . gmdate('H:i', strtotime($blocked)) . ' UTC.'); }
        
        $reward = \Conquer\Game\Player\TalentEffects::monsterLoot(EncounterCatalog::rules($raid)['reward'],BuffEngine::getBuffs($playerId,(int)$raid['world_id']));
        $rules=EncounterCatalog::rules($raid);
        $reward['items']=\Conquer\Game\Rewards\RewardCatalog::rollItems($rules['reward_drops']??[]);
        $reward['gems']=(int)($rules['reward_gems']??0);
        foreach($reward['items'] as $code=>$count)\Conquer\Game\Inventory\InventoryService::addItems($playerId,(int)$code,$count);
        if($reward['gems']>0)$db->execute('UPDATE players SET gems=gems+? WHERE id=?',[$reward['gems'],$playerId]);
        $db->execute('INSERT INTO expedition_rewards(expedition_id,player_id,reward_json) VALUES(?,?,?)', [(int) $raid['id'], $playerId, json_encode($reward, JSON_THROW_ON_ERROR)]);
        if ($db->execute('UPDATE cities SET food=food+?,lumber=lumber+?,stone=stone+?,gold=gold+? WHERE id=? AND player_id=? AND world_id=?', [$reward['food'], $reward['lumber'], $reward['stone'], $reward['gold'], (int) $participant['city_id'], $playerId,(int)$raid['world_id']]) !== 1) {
            throw new ExpeditionException('NO_CITY', 'Die Teilnehmerstadt konnte nicht gefunden werden.', 404);
        }
        $db->execute('UPDATE expedition_participants SET reward_claimed=1 WHERE expedition_id=? AND player_id=?', [(int) $raid['id'], $playerId]);
        self::log((int) $raid['id'], $playerId, 'reward', self::username($playerId) . ' erhält persönliche Feldzugsbeute.');
        return 'Beute erhalten: '.$reward['food'].' Nahrung, '.$reward['lumber'].' Holz, '.$reward['stone'].' Stein und '.$reward['gold'].' Gold.';
    }

    private static function cancel(array $raid, ?array $member, int $playerId): string
    {
        self::leader($member);
        if ($member['id'] !== (int) $raid['host_alliance_id'] && !($raid['invitation_status'] === 'accepted' && $member['id'] === (int) $raid['guest_alliance_id'])) {
            throw new ExpeditionException('FORBIDDEN', 'Nur eine beteiligte Allianzleitung kann den Feldzug abbrechen.', 403);
        }
        $db = Connection::getInstance();
        $db->execute("UPDATE expeditions SET phase='cancelled',completed_at=UTC_TIMESTAMP() WHERE id=?", [(int) $raid['id']]);
        $db->execute("UPDATE expedition_missions SET status='returning',return_at=LEAST(return_at,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 20 SECOND)) WHERE expedition_id=? AND status='marching'", [(int) $raid['id']]);
        self::log((int) $raid['id'], $playerId, 'cancelled', 'Die Allianzleitung beendet den Feldzug. Truppen kehren innerhalb von 20 Sekunden zurück; verbrauchte Versorgung bleibt verbraucht.');
        return 'Feldzug abgebrochen. Alle Truppen sind auf dem Rückweg.';
    }

    private static function participant(array $raid, ?array $member, int $playerId, int $cityId): void
    {
        if (!$member || $raid['invitation_status'] !== 'accepted'
            || !in_array($member['id'], [(int) $raid['host_alliance_id'], (int) $raid['guest_alliance_id']], true)) {
            throw new ExpeditionException('NOT_IN_COALITION', 'Deine Allianz gehört nicht zur bestätigten Koalition.', 403);
        }
        $db = Connection::getInstance();
        $participant = $db->query('SELECT alliance_id FROM expedition_participants WHERE expedition_id=? AND player_id=?', [(int) $raid['id'], $playerId])->fetch();
        if ($participant && (int) $participant['alliance_id'] !== $member['id']) {
            throw new ExpeditionException('ALLIANCE_CHANGED', 'Nach einem Allianzwechsel kannst du in diesem Feldzug nicht erneut beitragen. Verdiente Beute bleibt erhalten.', 403);
        }
        $db->execute('INSERT IGNORE INTO expedition_participants(expedition_id,player_id,alliance_id,city_id) VALUES(?,?,?,?)', [(int) $raid['id'], $playerId, $member['id'], $cityId]);
    }

    private static function snapshot(int $playerId): array
    {
        $db = Connection::getInstance();
        $member = self::membership($playerId);
        $allianceId = $member['id'] ?? 0;
        $rows = $db->query("SELECT e.*,h.name AS host_name,h.tag AS host_tag,g.name AS guest_name,g.tag AS guest_tag
            FROM expeditions e JOIN alliances h ON h.id=e.host_alliance_id LEFT JOIN alliances g ON g.id=e.guest_alliance_id
            WHERE e.world_id=? AND (e.host_alliance_id=? OR e.guest_alliance_id=? OR EXISTS(SELECT 1 FROM expedition_participants p WHERE p.expedition_id=e.id AND p.player_id=?))
            ORDER BY CASE WHEN e.phase IN ('planning','preparation','boss') THEN 0 ELSE 1 END,e.id DESC LIMIT 20", [WorldContext::id(),$allianceId, $allianceId, $playerId])->fetchAll();
        $blockedUntil = self::rewardBlockedUntil($playerId);
        $expeditions = [];
        foreach ($rows as $raid) {
            $id = (int) $raid['id'];
            $participants = $db->query("SELECT ep.*,COALESCE(NULLIF(k.display_name,''),p.username) AS username FROM expedition_participants ep JOIN players p ON p.id=ep.player_id LEFT JOIN kingdom_profiles k ON k.player_id=p.id WHERE ep.expedition_id=? ORDER BY ep.contribution DESC,ep.player_id", [$id])->fetchAll();
            $mine = null;
            foreach ($participants as &$participant) {
                foreach (['player_id','alliance_id','contribution','supplies','defenses','pass_progress','boss_damage'] as $key) { $participant[$key] = (int) $participant[$key]; }
                $participant['pass'] = $participant['pass_progress'];
                $participant['reward_claimed'] = (bool) $participant['reward_claimed'];
                unset($participant['city_id'], $participant['expedition_id'], $participant['pass_progress']);
                if ($participant['player_id'] === $playerId) { $mine = $participant; }
            }
            unset($participant);
            $missions = $db->query("SELECT m.*,COALESCE(NULLIF(k.display_name,''),p.username) AS username FROM expedition_missions m JOIN players p ON p.id=m.player_id LEFT JOIN kingdom_profiles k ON k.player_id=p.id WHERE m.expedition_id=? ORDER BY CASE WHEN m.status='returned' THEN 1 ELSE 0 END,m.id DESC LIMIT 60", [$id])->fetchAll();
            foreach ($missions as &$mission) {
                foreach (['id','player_id','alliance_id','damage','potential_damage'] as $key) { $mission[$key] = (int) $mission[$key]; }
                $mission['troops'] = json_decode($mission['troops_json'], true, 32, JSON_THROW_ON_ERROR);
                foreach (['created_at','arrival_at','return_at'] as $key) { $mission[$key] = self::iso($mission[$key]); }
                unset($mission['troops_json'], $mission['city_id'], $mission['expedition_id']);
            }
            unset($mission);
            $logs = $db->query('SELECT id,type,message,created_at FROM expedition_logs WHERE expedition_id=? ORDER BY id DESC LIMIT 50', [$id])->fetchAll();
            foreach ($logs as &$log) { $log['id'] = (int) $log['id']; $log['created_at'] = self::iso($log['created_at']); }
            unset($log);
            $active = ExpeditionRules::active($raid['phase']) && strtotime($raid['expires_at'] . ' UTC') > time();
            $leader = $member && $member['role'] === 'leader';
            $host = $allianceId === (int) $raid['host_alliance_id'];
            $guest = $allianceId > 0 && $allianceId === (int) $raid['guest_alliance_id'];
            $coalition = ($host || $guest) && $raid['invitation_status'] === 'accepted' && (!$mine || $mine['alliance_id'] === $allianceId);
            $expeditions[] = [
                'id' => $id, 'name' => $raid['name'], 'phase' => $raid['phase'],
                'created_at' => self::iso($raid['created_at']), 'expires_at' => self::iso($raid['expires_at']), 'completed_at' => self::iso($raid['completed_at']),
                'host_alliance' => ['id' => (int) $raid['host_alliance_id'], 'name' => $raid['host_name'], 'tag' => $raid['host_tag']],
                'guest_alliance' => $raid['guest_alliance_id'] ? ['id' => (int) $raid['guest_alliance_id'], 'name' => $raid['guest_name'], 'tag' => $raid['guest_tag']] : null,
                'invitation_status' => $raid['invitation_status'],
                'supplies' => ['current' => (int) $raid['supplies'], 'target' => EncounterCatalog::rules($raid)['supplies']],
                'defenses' => ['current' => (int) $raid['defenses'], 'target' => EncounterCatalog::rules($raid)['defenses']],
                'pass' => ['current' => (int) $raid['pass_progress'], 'target' => EncounterCatalog::rules($raid)['pass']],
                'encounter' => EncounterCatalog::rules($raid), 'boss' => ['name' => EncounterCatalog::rules($raid)['name'], 'hp' => (int) $raid['boss_hp'], 'max_hp' => EncounterCatalog::rules($raid)['hp']],
                'participants' => $participants, 'missions' => $missions, 'log' => $logs,
                'my_contribution' => $mine['contribution'] ?? 0, 'reward_claimed' => $mine['reward_claimed'] ?? false,
                'can_claim' => $raid['phase'] === 'victory' && ($mine['contribution'] ?? 0) >= ExpeditionRules::MIN_CONTRIBUTION && !($mine['reward_claimed'] ?? false) && $blockedUntil === null,
                'reward_blocked_until' => $blockedUntil,
                'permissions' => [
                    'invite' => (bool) ($active && $leader && $host && $raid['phase'] === 'planning' && $raid['invitation_status'] !== 'pending'),
                    'accept' => (bool) ($active && $leader && $guest && $raid['phase'] === 'planning' && $raid['invitation_status'] === 'pending' && !self::activeForAlliance($allianceId, $id)),
                    'decline' => (bool) ($active && $leader && $guest && $raid['phase'] === 'planning' && $raid['invitation_status'] === 'pending'),
                    'cancel' => (bool) ($active && $leader && ($host || ($guest && $coalition))),
                    'supply' => (bool) ($active && $coalition && $raid['phase'] === 'preparation' && (int) $raid['supplies'] < EncounterCatalog::rules($raid)['supplies']),
                    'defenses' => (bool) ($active && $coalition && $host && $raid['phase'] === 'preparation' && (int) $raid['defenses'] < EncounterCatalog::rules($raid)['defenses']),
                    'pass' => (bool) ($active && $coalition && $guest && $raid['phase'] === 'preparation' && (int) $raid['pass_progress'] < EncounterCatalog::rules($raid)['pass']),
                    'boss' => (bool) ($active && $coalition && $raid['phase'] === 'boss'),
                ],
            ];
        }
        $troops = $db->query('SELECT ct.troop_code,ct.count FROM city_troops ct JOIN cities c ON c.id=ct.city_id WHERE c.player_id=? AND c.world_id=' . WorldContext::id() . ' AND ct.count>0 ORDER BY ct.troop_code', [$playerId])->fetchAll();
        $army = [];
        foreach ($troops as $troop) {
            $definition = TroopData::get((int) $troop['troop_code']);
            if (!$definition) { continue; }
            $army[] = ['code' => (int) $troop['troop_code'], 'name' => $definition['name'], 'tier' => (int) $definition['tier'], 'count' => (int) $troop['count'], 'attack' => (int) $definition['attack']];
        }
        $available = $db->query("SELECT a.id,a.name,a.tag,a.member_count FROM alliances a WHERE a.world_id=" . WorldContext::id() . " AND a.id<>? AND NOT EXISTS(SELECT 1 FROM expeditions e WHERE e.phase IN ('planning','preparation','boss') AND (e.host_alliance_id=a.id OR (e.guest_alliance_id=a.id AND e.invitation_status='accepted'))) ORDER BY a.member_count DESC,a.id LIMIT 100", [$allianceId])->fetchAll();
        foreach ($available as &$alliance) { $alliance['id'] = (int) $alliance['id']; $alliance['member_count'] = (int) $alliance['member_count']; }
        unset($alliance);
        return [
            'server_time' => gmdate('Y-m-d\TH:i:s\Z'), 'alliance' => $member,
            'can_create' => (bool) ($member && $member['role'] === 'leader' && !self::activeForAlliance($allianceId)),
            'catalog'=>EncounterCatalog::BOSSES,'difficulties'=>EncounterCatalog::DIFFICULTIES,'chapter'=>max(1,(int)$db->query('SELECT chapter FROM world_chapters WHERE world_id=' . WorldContext::id() . '')->fetchColumn()),'available_alliances' => $available, 'troops' => $army, 'rules' => ExpeditionRules::publicRules(BuffEngine::getBuffs($playerId),MarchSkinService::speedMultiplier(MarchSkinService::currentSnapshot($playerId))), 'expeditions' => $expeditions,
        ];
    }

    private static function membership(int $playerId): ?array
    {
        $row = Connection::getInstance()->query('SELECT a.id,a.name,a.tag,m.role FROM alliance_members m JOIN alliances a ON a.id=m.alliance_id WHERE m.player_id=? AND a.world_id=' . WorldContext::id() . '', [$playerId])->fetch();
        if (!$row) { return null; }
        $row['id'] = (int) $row['id'];
        return $row;
    }

    private static function leader(?array $member, ?int $allianceId = null): void
    {
        if (!$member || $member['role'] !== 'leader' || ($allianceId !== null && $member['id'] !== $allianceId)) {
            throw new ExpeditionException('LEADER_REQUIRED', 'Diese Aktion benötigt die Leitung der betreffenden Allianz.', 403);
        }
    }

    private static function activeForAlliance(int $allianceId, int $except = 0): bool
    {
        return (bool) Connection::getInstance()->query("SELECT id FROM expeditions WHERE id<>? AND phase IN ('planning','preparation','boss') AND (host_alliance_id=? OR (guest_alliance_id=? AND invitation_status='accepted')) LIMIT 1", [$except, $allianceId, $allianceId])->fetchColumn();
    }

    private static function rewardBlockedUntil(int $playerId): ?string
    {
        $date = Connection::getInstance()->query('SELECT DATE_ADD(MAX(r.created_at),INTERVAL 1 HOUR) FROM expedition_rewards r JOIN expeditions e ON e.id=r.expedition_id WHERE r.player_id=? AND e.world_id=?', [$playerId,WorldContext::id()])->fetchColumn();
        return $date && strtotime($date . ' UTC') > time() ? self::iso($date) : null;
    }

    private static function positiveId(mixed $value, string $field): int
    {
        if (!is_int($value) || $value <= 0) { throw new ExpeditionException('INVALID_INPUT', $field . ' muss eine positive ganze Zahl sein.'); }
        return $value;
    }

    private static function username(int $playerId): string
    {
        return (string) (Connection::getInstance()->query("SELECT COALESCE(NULLIF(k.display_name,''),p.username) FROM players p LEFT JOIN kingdom_profiles k ON k.player_id=p.id WHERE p.id=?", [$playerId])->fetchColumn() ?: 'Teilnehmer');
    }

    private static function log(int $id, ?int $playerId, string $type, string $message): void
    {
        Connection::getInstance()->execute('INSERT INTO expedition_logs(expedition_id,player_id,type,message) VALUES(?,?,?,?)', [$id, $playerId, $type, $message]);
    }

    private static function iso(?string $value): ?string
    {
        return $value === null ? null : str_replace(' ', 'T', $value) . 'Z';
    }

    private static function locked(string $name, callable $fn): mixed
    {
        $db = Connection::getInstance();
        if ((int) $db->query('SELECT GET_LOCK(?,5)', [$name])->fetchColumn() !== 1) {
            throw new ExpeditionException('BUSY', 'Der Feldzug wird gerade aktualisiert. Bitte versuche es erneut.', 409);
        }
        try { return $fn(); }
        finally { $db->query('SELECT RELEASE_LOCK(?)', [$name]); }
    }
}
