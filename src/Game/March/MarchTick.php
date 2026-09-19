<?php
declare(strict_types=1);

namespace Conquer\Game\March;

use Conquer\Game\World\WorldContext;

use Conquer\Db\Connection;
use Conquer\Logger;
use Conquer\Game\Hospital\HospitalService;
use Conquer\Game\Quest\DailyQuestService;
use Conquer\Game\Notification\NotificationService;
use Conquer\Game\Inventory\InventoryService;
use Conquer\Game\Alliance\AllianceGiftService;
use Conquer\Game\Map\WorldPlacement;

/** Fraction of losses that go to hospital instead of dying permanently. */
const MORTALITY_RATE = 0.3; // 30% of losses are wounded, 70% die

/**
 * Lazy march tick — called from MarchHandler::list() so marches resolve
 * even without a running cron job (identical logic to cron/march_tick.php).
 */
final class MarchTick
{
    private function __construct() {}

    /**
     * Process all pending marches for one player:
     *   1. arrived marches   (state='marching',  arrival_time  <= UTC)
     *   2. returning marches (state='returning', return_time   <= UTC)
     */
    public static function runForPlayer(int $playerId): void
    {
        $db = Connection::getInstance();
        $lock = 'conquer-player-' . $playerId;
        if ((int) $db->query('SELECT GET_LOCK(?, 5)', [$lock])->fetchColumn() !== 1) { return; }
        try { self::processPlayer($playerId); }
        finally { $db->query('SELECT RELEASE_LOCK(?)', [$lock]); }
    }

    private static function processPlayer(int $playerId): void
    {
        $db  = Connection::getInstance();
        $log = Logger::getInstance();

        // ── Step 0: recovery — reset marches stuck in 'resolving' > 2 min ──────
        try {
            $db->execute(
                "UPDATE marches
                 SET state = 'marching'
                 WHERE player_id = ?
                   AND state = 'resolving'
                   AND arrival_time <= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 MINUTE)",
                [$playerId],
            );
        } catch (\PDOException $e) {
            $log->error('[MarchTick] resolving recovery failed: ' . $e->getMessage());
        }

        // ── Step 1: arrived ─────────────────────────────────────────────────
        try {
            $arrived = $db->query(
                "SELECT * FROM marches
                 WHERE  player_id = ? AND state = 'marching'
                   AND  arrival_time <= UTC_TIMESTAMP()
                 ORDER  BY arrival_time ASC
                 LIMIT  10",
                [$playerId],
            )->fetchAll();
        } catch (\PDOException $e) {
            $log->error('[MarchTick] arrived query failed: ' . $e->getMessage());
            return;
        }

        foreach ($arrived as $march) {
            $marchId   = (int) $march['id'];
            $marchType = (int) $march['march_type'];
            $cityId    = (int) $march['origin_city_id'];
            $targetX   = (int) $march['target_x'];
            $targetY   = (int) $march['target_y'];
            $monsterId = (int) $march['target_id'];

            $troops = json_decode((string) $march['troops_json'], true) ?? [];
            $intTroops = [];
            foreach ($troops as $code => $count) {
                $intTroops[(int) $code] = (int) $count;
            }

            WorldContext::run((int)$march['world_id'],function()use($db,$log,$marchId,$marchType,$playerId,$cityId,$targetX,$targetY,$monsterId,$intTroops,$march):void{
            try {
                if (in_array($marchType, [13,14], true)) {
                    \Conquer\Game\Shrine\CongressService::resolveMarch($marchId);
                } elseif ($marchType === 5) {
                    $monsterLock = 'conquer-monster-' . $monsterId;
                    if ((int) $db->query('SELECT GET_LOCK(?, 5)', [$monsterLock])->fetchColumn() !== 1) { return; }
                    try {
                        self::resolveMonster($db, $log, $marchId, $playerId, $cityId,
                            $targetX, $targetY, $monsterId, $intTroops, $march);
                    } finally { $db->query('SELECT RELEASE_LOCK(?)', [$monsterLock]); }
                } elseif ($marchType === 6) {
                    $collection=\Conquer\Game\Charm\CharmCollectionService::resolveDue((int)$march['world_id'],(int)$march['target_id']);
                    if($collection['winner_march_id']!==null)$log->info(sprintf('[MarchTick] March %d collected charm %d for player %d',$collection['winner_march_id'],(int)$march['target_id'],$collection['winner_player_id']));
                } elseif ($marchType === 7) {
                    \Conquer\Game\WorldRules::combatLock(function() use ($db,$marchId,$playerId,$cityId,$targetX,$targetY,$monsterId,$intTroops): void {
                        $db->transaction(function() use ($db,$marchId,$playerId,$cityId,$targetX,$targetY,$monsterId,$intTroops): void {
                            if($db->execute("UPDATE marches SET state='resolving' WHERE id=? AND state='marching'",[$marchId])!==1)return;
                            $result=CityCombat::resolve([['player_id'=>$playerId,'city_id'=>$cityId,'troops'=>$intTroops]],$monsterId,$targetX,$targetY,$marchId);
                            $army=$result['armies'][0];
                            $db->execute("UPDATE marches SET state='returning',return_time=DATE_ADD(UTC_TIMESTAMP(),INTERVAL GREATEST(5,TIMESTAMPDIFF(SECOND,departure_time,arrival_time)) SECOND),haul_json=? WHERE id=?",[json_encode(['survivors'=>$army['survivors'],'loot'=>$army['loot'],'reason'=>$result['reason']??null]),$marchId]);
                        });
                    });
                } elseif ($marchType === 8) {
                    self::resolveScout($db, $log, $marchId, $playerId, $cityId, $targetX, $targetY, (int)$march['target_id']);
                } elseif (in_array($marchType,[9,GatherService::FIELD_ATTACK],true)) {
                    GatherService::resolveGather($db, $log, $marchId, $playerId, $cityId, $targetX, $targetY, (int)$march['target_id']);
                } elseif ($marchType === 10) {
                    self::resolveReinforce($db, $log, $marchId, $playerId, $cityId, (int)$march['target_id'], $intTroops);
                } else {
                    // Unsupported type — return immediately
                    $db->execute(
                        "UPDATE marches SET state = 'returning', return_time = UTC_TIMESTAMP() WHERE id = ?",
                        [$marchId],
                    );
                }
            } catch (\Throwable $e) {
                $log->error(sprintf('[MarchTick] Unhandled error in march %d (type %d): %s', $marchId, $marchType, $e->getMessage()));
                // Safety net: put back to returning so the march doesn't block forever
                try {
                    $db->execute(
                        "UPDATE marches SET state='returning', return_time=UTC_TIMESTAMP(), haul_json=? WHERE id=? AND state='resolving'",
                        [json_encode(['survivors'=>$intTroops,'loot'=>[]]), $marchId],
                    );
                } catch (\Throwable) {}
            }
            });
        }

        // Resolve work at resource nodes before homecoming, including offline intervals.
        foreach($db->query("SELECT id,world_id FROM marches WHERE player_id=? AND march_type=9 AND state='arrived' AND gathering_finishes_at<=UTC_TIMESTAMP()",[$playerId])->fetchAll() as $gather){
            try{WorldContext::run((int)$gather['world_id'],fn()=>GatherService::finish((int)$gather['id']));}
            catch(\Throwable $e){$log->error('[GatherService] '.$gather['id'].': '.$e->getMessage());}
        }

        // ── Step 2: returning ────────────────────────────────────────────────
        try {
            $returning = $db->query(
                "SELECT * FROM marches
                 WHERE  player_id = ? AND state = 'returning'
                   AND  return_time <= UTC_TIMESTAMP()
                 ORDER  BY return_time ASC
                 LIMIT  10",
                [$playerId],
            )->fetchAll();
        } catch (\PDOException $e) {
            $log->error('[MarchTick] returning query failed: ' . $e->getMessage());
            return;
        }

        foreach ($returning as $march) {
            $db->transaction(static function (Connection $db) use ($march, $playerId, $log): void {
            $claimed = $db->execute("UPDATE marches SET state = 'complete' WHERE id = ? AND state = 'returning'", [(int) $march['id']]);
            if ($claimed !== 1) { return; }
            $marchId   = (int) $march['id'];
            $cityId    = (int) $march['origin_city_id'];
            $haul      = json_decode((string) ($march['haul_json'] ?? '{}'), true) ?? [];
            $survivors = $haul['survivors'] ?? [];
            $loot      = $haul['loot']      ?? [];

            // Return surviving troops to city
            foreach ($survivors as $code => $count) {
                $count = (int) $count;
                if ($count <= 0) continue;
                $db->execute(
                    'INSERT INTO city_troops (city_id, troop_code, count)
                     VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE count = count + ?',
                    [$cityId, (int) $code, $count, $count],
                );
            }

            // Add looted resources to origin city
            if (!empty($loot)) {
                $db->execute(
                    'UPDATE cities
                     SET food  = food  + :f,
                         lumber = lumber + :w,
                         stone = stone + :s,
                         gold  = gold  + :g
                     WHERE id = :id',
                    [
                        ':f'  => (int) ($loot['food']  ?? 0),
                        ':w'  => (int) ($loot['lumber'] ?? $loot['wood'] ?? 0),
                        ':s'  => (int) ($loot['stone'] ?? 0),
                        ':g'  => (int) ($loot['gold']  ?? 0),
                        ':id' => $cityId,
                    ],
                );
            }

            if (!empty($loot['gems'])) {
                $db->execute('UPDATE players SET gems = gems + ? WHERE id = ?', [(int) $loot['gems'], $playerId]);
            }
            foreach(($haul['items']??[]) as $code=>$count)\Conquer\Game\Inventory\InventoryService::addItems($playerId,(int)$code,(int)$count);
            $db->execute(
                "UPDATE marches SET state = 'complete' WHERE id = ?",
                [$marchId],
            );

            $log->info('[MarchTick] March ' . $marchId . ' completed — troops returned to city ' . $cityId);
            });
        }
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private static function resolveMonster(
        Connection $db,
        Logger     $log,
        int        $marchId,
        int        $playerId,
        int        $cityId,
        int        $targetX,
        int        $targetY,
        int        $monsterId,
        array      $troops,
        array      $march,
    ): void {
        $resolution=$db->transaction(function () use ($db,$marchId,$playerId,$cityId,$monsterId,$targetX,$targetY,$troops,$march): array {
            if($db->execute("UPDATE marches SET state='resolving' WHERE id=? AND state='marching'",[$marchId])!==1)return ['resolved'=>false];
            WorldPlacement::lockWorld($db,WorldContext::id());
            $monster=$db->query('SELECT * FROM field_monsters WHERE id=? AND world_id=? FOR UPDATE',[$monsterId,WorldContext::id()])->fetch();
            if(!$monster||(int)$monster['hp_current']<=0
                ||(int)$monster['coord_x']!==$targetX||(int)$monster['coord_y']!==$targetY){
                self::finalizeMarch($db,$marchId,$troops,[],'defender_wins');
                return ['resolved'=>true,'monster_killed'=>false,'outcome'=>'defender_wins','already_gone'=>true];
            }
            if(($monster['monster_type']??'solo')==='rally'||!\Conquer\Game\Map\MonsterData::isActive((int)$monster['monster_code'])){
                self::finalizeMarch($db,$marchId,$troops,[],'defender_wins');
                NotificationService::push($playerId,'rally_required',['monster_id'=>$monsterId,'x'=>$targetX,'y'=>$targetY]);
                return ['resolved'=>true,'monster_killed'=>false,'outcome'=>'defender_wins','rally_required'=>true];
            }
            $snapshot=json_decode((string)($march['encounter_snapshot_json']??''),true);
            $monsterDef=is_array($snapshot)&&is_array($snapshot['definition']??null)
                ?$snapshot['definition']:self::loadMonsterDef((int)$monster['monster_code']);
            if(is_array($snapshot)&&isset($snapshot['effective_monster_level']))$monster['effective_monster_level']=(int)$snapshot['effective_monster_level'];
            $monsterCode=(int)$monster['monster_code'];
            $buffs=\Conquer\Game\Research\BuffEngine::getBuffs($playerId,WorldContext::id());
            $result=BattleEngine::resolveMonster($troops,$monster,$monsterDef,$buffs);
            $result['report']['source_snapshot']=MonsterReport::capture($playerId,$cityId,WorldContext::id());
            $result['loot']=$result['monster_killed']?($monsterDef['resource_reward']??['food'=>100,'lumber'=>100,'stone'=>50,'gold'=>50]):[];
            $result['items']=$result['monster_killed']?\Conquer\Game\Rewards\RewardCatalog::rollItems($monsterDef['drops']??[]):[];
            $gems=$monsterDef['gems_drop']??[];
            if($result['monster_killed']&&\Conquer\Game\Rewards\RewardCatalog::roll((float)($gems['chance']??0)))$result['loot']['gems']=(int)($gems['amount']??0);
            $result['loot']=\Conquer\Game\Player\TalentEffects::monsterLoot($result['loot'],$buffs);
            $result['report']['items']=$result['items'];$result['report']['item_rewards']=[];
            foreach($result['items'] as $code=>$count){$item=InventoryService::getItemDef((int)$code);$result['report']['item_rewards'][]=['code'=>(int)$code,'count'=>$count,'name'=>$item['name_de']??$item['name']??'Gegenstand'];}
            $result['report']['loot']=$result['loot'];
            $xp=isset($monsterDef['xp'])?(int)$monsterDef['xp']:max(1,(int)($monster['effective_monster_level']??$monsterDef['level']??1))*(str_contains(strtolower($monsterDef['name']??''),'deathkar')?20:10);
            $result['report']['lord_xp']=$result['monster_killed']?$xp:0;
            if ($result['monster_killed']) {
                $settlement=\Conquer\Game\Charm\MonsterCharmLifecycle::settle(
                    WorldContext::id(),$monster,$monsterDef,'solo',$marchId,$playerId,null,
                    ['resources'=>$result['loot'],'items'=>$result['items'],'lord_xp'=>$xp],
                );
                if(!$settlement['created']){
                    self::finalizeMarch($db,$marchId,$troops,[],'defender_wins');
                    return ['resolved'=>true,'monster_killed'=>false,'outcome'=>'defender_wins','already_settled'=>true];
                }
                \Conquer\Game\Hospital\HospitalService::addWounded($cityId,$result['attacker_losses']);
                $result['report']['lord_xp']=\Conquer\Game\Player\LordLevel::addXp($playerId,$xp,WorldContext::id(),'monster-march:'.$marchId);
                $result['report']['charm']=['id'=>$settlement['charm_id'],'world_id'=>WorldContext::id(),'x'=>(int)$monster['coord_x'],'y'=>(int)$monster['coord_y'],'guaranteed'=>true,'ownership'=>null,'exclusive_until'=>null];
                $db->execute('UPDATE players SET kill_count=kill_count+1 WHERE id=?',[$playerId]);
                if($db->execute('DELETE FROM field_monsters WHERE id=? AND world_id=?',[$monsterId,WorldContext::id()])!==1)throw new \RuntimeException('Monster kill lost its target row.');
            } else {
                \Conquer\Game\Hospital\HospitalService::addWounded($cityId,$result['attacker_losses']);
                $db->execute('UPDATE field_monsters SET hp_current=? WHERE id=? AND world_id=?',[$result['new_monster_hp'],$monsterId,WorldContext::id()]);
            }

            $db->execute(
                'INSERT INTO battle_reports
                    (world_id, march_id, attacker_id, attacker_city_id,
                     target_type, target_id, target_x, target_y,
                     outcome, data_json, attacker_read, created_at)
                 VALUES
                    (:world, :mid, :pid, :cid,
                     3, :tid, :tx, :ty,
                     :out, :data, 0, UTC_TIMESTAMP())',
                [
                    ':world' => WorldContext::id(),
                    ':mid'  => $marchId,
                    ':pid'  => $playerId,
                    ':cid'  => $cityId,
                    ':tid'  => $monsterId,
                    ':tx'   => $targetX,
                    ':ty'   => $targetY,
                    ':out'  => $result['outcome'],
                    ':data' => json_encode($result['report']),
                ],
            );

            $db->execute(
                "UPDATE marches
                 SET state       = 'returning',
                     return_time = DATE_ADD(arrival_time,
                                   INTERVAL TIMESTAMPDIFF(SECOND, departure_time, arrival_time) SECOND),
                     haul_json   = :haul
                 WHERE id = :id",
                [
                    ':haul' => json_encode(['survivors' => $result['attacker_survivors'], 'loot' => $result['loot'], 'items'=>$result['items']]),
                    ':id'   => $marchId,
                ],
            );
            return ['resolved'=>true,'monster_killed'=>(bool)$result['monster_killed'],'outcome'=>$result['outcome'],'monster_code'=>$monsterCode,'monster_def'=>$monsterDef];
        });

        // Award Lord XP for monster kill + daily quest tracking
        if ($resolution['monster_killed']??false) {
            // Track daily quests for monster kills
            try {
                DailyQuestService::trackProgress($playerId, 'attack_monster');
            } catch (\Throwable) {}

            // Goblins use the same catalog and return-haul payout as every solo monster.

            // Alliance gift trigger — 20% chance for alliance members
            try {
                AllianceGiftService::triggerMonsterKill($playerId, (int)$resolution['monster_code']);
            } catch (\Throwable) {}
        }

        $log->info(sprintf(
            '[MarchTick] March %d resolved — %s, monster %s',
            $marchId,
            $resolution['outcome']??'skipped',
            ($resolution['monster_killed']??false) ? 'killed' : 'damaged',
        ));
    }

    private static function finalizeMarch(
        Connection $db,
        int        $marchId,
        array      $troops,
        array      $losses,
        string     $outcome,
    ): void {
        $survivors = [];
        foreach ($troops as $code => $count) {
            $survivors[$code] = max(0, $count - (int) ($losses[$code] ?? 0));
        }

        $db->execute(
            "UPDATE marches
             SET state       = 'returning',
                 return_time = UTC_TIMESTAMP(),
                 haul_json   = :haul
             WHERE id = :id",
            [
                ':haul' => json_encode(['survivors' => $survivors]),
                ':id'   => $marchId,
            ],
        );
    }

    private static function resolveScout(Connection $db,Logger $log,int $marchId,int $playerId,int $cityId,int $targetX,int $targetY,int $targetCityId): void
    {
        \Conquer\Game\WorldRules::combatLock(fn()=>$db->transaction(function()use($db,$marchId,$playerId,$cityId,$targetX,$targetY,$targetCityId):void{
            $march=$db->query("SELECT * FROM marches WHERE id=? AND state='marching' FOR UPDATE",[$marchId])->fetch();if(!$march)return;
            $world=(int)$march['world_id'];$cancelled=null;$target=null;
            try{$target=\Conquer\Game\WorldRules::assertCityAttackAllowed($playerId,$targetX,$targetY,null,$world);if((int)$target['id']!==$targetCityId)throw new \RuntimeException('Die Zielstadt wurde versetzt.');}
            catch(\RuntimeException $e){$cancelled=$e->getMessage();}
            if($target && !$cancelled){
                $city=\Conquer\Game\Defense\DefenseService::syncWall($targetCityId);$buildings=[];
                if (!empty($city['anti_spy_until']) && strtotime($city['anti_spy_until'].' UTC')>time()) {
                    $data=['type'=>'scout','blocked'=>true,'reason'=>'Die Stadt ist vor Spähberichten geschützt.','observed_at'=>gmdate('Y-m-d H:i:s'),'target_name'=>$target['display_name'],'target_player_id'=>(int)$city['player_id']];
                    $db->execute("INSERT INTO battle_reports(world_id,march_id,attacker_id,attacker_city_id,defender_id,target_type,target_id,target_x,target_y,outcome,data_json,attacker_read,defender_read,created_at) VALUES(?,?,?,?,?,2,?,?,?,'scouted',?,0,0,UTC_TIMESTAMP())",[$world,$marchId,$playerId,$cityId,(int)$city['player_id'],$targetCityId,$targetX,$targetY,json_encode($data,JSON_THROW_ON_ERROR)]);
                    $db->execute("UPDATE marches SET state='returning',return_time=DATE_ADD(UTC_TIMESTAMP(),INTERVAL GREATEST(2,TIMESTAMPDIFF(SECOND,departure_time,arrival_time)) SECOND),haul_json=? WHERE id=?",[json_encode(['survivors'=>[],'loot'=>[],'reason'=>$data['reason']]),$marchId]);
                    return;
                }
                foreach($db->query('SELECT building_code,level FROM city_buildings WHERE city_id=?',[$targetCityId])->fetchAll() as $b)$buildings[$b['building_code']]=['level'=>(int)$b['level']];
                \Conquer\Game\City\ResourceTick::persist($city,$buildings);$city=$db->query('SELECT * FROM cities WHERE id=?',[$targetCityId])->fetch();
                $defender=(int)$city['player_id'];$p=$db->query('SELECT lord_level,kill_count FROM players WHERE id=?',[$defender])->fetch();
                $troops=array_map('intval',$db->query('SELECT troop_code,count FROM city_troops WHERE city_id=? AND count>0 ORDER BY troop_code',[$targetCityId])->fetchAll(\PDO::FETCH_KEY_PAIR));
                $reinforcements=[];foreach($db->query("SELECT troops_json FROM reinforcements WHERE target_city_id=? AND state='active'",[$targetCityId])->fetchAll() as $r)foreach(json_decode($r['troops_json'],true)?:[] as $code=>$count)$reinforcements[$code]=($reinforcements[$code]??0)+(int)$count;
                $treasures=[];foreach(\Conquer\Game\Treasure\TreasureService::getPlayerTreasures($defender) as $t)if($t['equipped_slot']!==null)$treasures[]=['treasure_code'=>$t['treasure_code'],'name'=>$t['name'],'level'=>$t['level'],'equipped_slot'=>$t['equipped_slot']];
                $mastery=class_exists(\Conquer\Game\Player\MasteryService::class)?\Conquer\Game\Player\MasteryService::snapshot($defender,$world):[];
                $data=['type'=>'scout','observed_at'=>gmdate('Y-m-d H:i:s'),'target_name'=>$target['display_name'],'target_player_id'=>$defender,'target_power'=>(int)$city['power'],'castle_level'=>(int)$city['castle_level'],'lord_level'=>\Conquer\Game\Player\LordLevel::snapshot($defender,$world)['level'],
                    'wall'=>\Conquer\Game\Defense\DefenseService::wallStats($city,$buildings),'resources'=>array_intersect_key(array_map('intval',$city),array_flip(['food','lumber','stone','gold'])),
                    'protected_resources'=>\Conquer\Game\Defense\DefenseService::protectedResources($city,\Conquer\Game\Research\BuffEngine::getBuffs($defender,$world)),'troops'=>$troops,'reinforcements'=>$reinforcements,'mastery'=>$mastery,'treasures'=>$treasures];
                $db->execute("INSERT INTO battle_reports(world_id,march_id,attacker_id,attacker_city_id,defender_id,target_type,target_id,target_x,target_y,outcome,data_json,attacker_read,defender_read,created_at) VALUES(?,?,?,?,?,2,?,?,?,'scouted',?,0,0,UTC_TIMESTAMP())",[$world,$marchId,$playerId,$cityId,$defender,$targetCityId,$targetX,$targetY,json_encode($data,JSON_THROW_ON_ERROR)]);
                NotificationService::push($defender,'scouted',['attacker_name'=>self::getPlayerName($db,$playerId),'x'=>$targetX,'y'=>$targetY]);
            }
            $db->execute("UPDATE marches SET state='returning',return_time=DATE_ADD(UTC_TIMESTAMP(),INTERVAL GREATEST(2,TIMESTAMPDIFF(SECOND,departure_time,arrival_time)) SECOND),haul_json=? WHERE id=?",[json_encode(['survivors'=>[],'loot'=>[],'reason'=>$cancelled]),$marchId]);
        }));
    }

    /** Returns the username for a player id (used for notifications). */
    private static function getPlayerName(Connection $db, int $playerId): string
    {
        try {
            $row = $db->query('SELECT username FROM players WHERE id = ? LIMIT 1', [$playerId])->fetch();
            return $row['username'] ?? 'Unknown';
        } catch (\Throwable) {
            return 'Unknown';
        }
    }

    /**
     * Checks whether the defender's wall HP has reached zero after the attack.
     * If so: teleport the city to a random free coordinate and notify the defender.
     */
    public static function checkWallDestroyed(Connection $db, int $targetCityId, int $defPlayerId,float $damageBonus=0): void
    {
        try {
            $resolve = static function (Connection $db) use ($targetCityId, $defPlayerId,$damageBonus): void {
                $worldId = $db->query('SELECT world_id FROM cities WHERE id = ?', [$targetCityId])->fetchColumn();
                if ($worldId === false) {
                    return;
                }
                $size = WorldPlacement::lockWorld($db, (int) $worldId);
                $wallRow = $db->query(
                    'SELECT wall_hp_current, wall_hp_max FROM cities WHERE id = ? FOR UPDATE',
                    [$targetCityId],
                )->fetch();

                if ($wallRow === false) {
                    return;
                }

                $wallHp    = (int) $wallRow['wall_hp_current'];
                $wallHpMax = (int) $wallRow['wall_hp_max'];

                // Wall damage from battle: attacker_wins → defender loses 30% troops
                // Simplified wall damage: 10% of max HP per player victory
                $wallDamage = (int) ceil($wallHpMax * 0.10 * (1+max(0,$damageBonus)));

                if (($wallHp - $wallDamage) > 0) {
                    // Wall survives — just reduce HP
                    $db->execute(
                        'UPDATE cities SET wall_hp_current = GREATEST(0, wall_hp_current - ?), wall_last_update = UTC_TIMESTAMP() WHERE id = ?',
                        [$wallDamage, $targetCityId],
                    );
                    return;
                }

                // Reserve a complete dry footprint before changing the city or its wall.
                [$nx, $ny] = self::findFreeTeleportCoord($db, (int) $worldId, $targetCityId, $size);

                $db->execute(
                    'UPDATE cities
                     SET coord_x        = ?,
                         coord_y        = ?,
                         wall_hp_current = wall_hp_max,
                         wall_last_update = UTC_TIMESTAMP()
                     WHERE id = ?',
                    [$nx, $ny, $targetCityId],
                );

                NotificationService::push($defPlayerId, 'wall_destroyed', [
                    'new_x' => $nx,
                    'new_y' => $ny,
                ]);
            };
            if ($db->getPdo()->inTransaction()) {
                $resolve($db);
            } else {
                $db->transaction($resolve);
            }
        } catch (\Throwable $e) {
            if($e instanceof \PDOException)throw $e;
            Logger::getInstance()->error('[MarchTick] checkWallDestroyed failed: ' . $e->getMessage());
        }
    }

    /**
     * Finds a dry, unoccupied 4×4 destination inside the locked world's bounds.
     *
     * @return array{int, int}  [x, y]
     */
    private static function findFreeTeleportCoord(Connection $db, int $worldId, int $cityId, int $size): array
    {
        $min = 1;
        $max = $size - 3;
        if ($max < $min) {
            throw new \RuntimeException('The world has no space for a city.');
        }

        for ($attempt = 0; $attempt < 64; $attempt++) {
            $nx = random_int($min, $max);
            $ny = random_int($min, $max);

            if (WorldPlacement::canPlace($db, $worldId, 'city', $nx, $ny, $cityId)) {
                return [$nx, $ny];
            }
        }

        $coord = WorldPlacement::findNear($db, $worldId, 'city', intdiv($size, 2), intdiv($size, 2), $cityId, $size);
        if ($coord !== null) {
            return $coord;
        }
        throw new \RuntimeException('No dry, unoccupied city teleport destination is available.');
    }

    /**
     * Resolves a reinforcement march (type 10).
     * Inserts an entry into the reinforcements table and marks the march as 'arrived'
     * (troops stay at destination until recalled).
     */
    private static function resolveReinforce(Connection $db,Logger $log,int $marchId,int $playerId,int $originCityId,int $targetCityId,array $troops): void
    {
        \Conquer\Game\WorldRules::combatLock(fn()=>$db->transaction(function()use($db,$marchId,$playerId,$originCityId,$targetCityId,$troops):void{
            $m=$db->query("SELECT * FROM marches WHERE id=? AND state='marching' FOR UPDATE",[$marchId])->fetch();if(!$m)return;
            try{
                $city=\Conquer\Game\WorldRules::assertReinforcementAllowed($playerId,$targetCityId,(int)$m['world_id'],(int)$m['target_x'],(int)$m['target_y']);
                $db->query('SELECT id FROM cities WHERE id=? FOR UPDATE',[$targetCityId])->fetchColumn();
                if((int)$db->query("SELECT COUNT(*) FROM reinforcements WHERE target_city_id=? AND state='active'",[$targetCityId])->fetchColumn()>=5)throw new \RuntimeException('Die Stadt hat bereits fünf Verstärkungen.');
            }catch(\RuntimeException $e){
                $db->execute("UPDATE marches SET state='returning',return_time=DATE_ADD(UTC_TIMESTAMP(),INTERVAL GREATEST(5,TIMESTAMPDIFF(SECOND,departure_time,arrival_time)) SECOND),haul_json=? WHERE id=?",[json_encode(['survivors'=>$troops,'loot'=>[],'reason'=>$e->getMessage()]),$marchId]);return;
            }
            $db->execute("INSERT INTO reinforcements(march_id,sender_id,sender_city_id,target_player_id,target_city_id,troops_json,state) VALUES(?,?,?,?,?,?,'active')",[$marchId,$playerId,$originCityId,(int)$city['player_id'],$targetCityId,json_encode($troops)]);
            $db->execute("UPDATE marches SET state='arrived' WHERE id=?",[$marchId]);
        }));
    }

    /** Load monster definition from data files (cached per request). */
    private static function loadMonsterDef(int $code): array
    {
        return \Conquer\Game\Map\MonsterData::get($code);
    }
}
