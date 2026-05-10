<?php
declare(strict_types=1);

namespace Conquer\Game\March;

use Conquer\Db\Connection;
use Conquer\Logger;

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
        $db  = Connection::getInstance();
        $log = Logger::getInstance();

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

            if ($marchType === 5) {
                self::resolveMonster($db, $log, $marchId, $playerId, $cityId,
                    $targetX, $targetY, $monsterId, $intTroops);
            } elseif ($marchType === 6) {
                self::resolveCharmCollect($db, $log, $marchId, $playerId, $cityId, $targetX, $targetY, (int)$march['target_id']);
            } elseif ($marchType === 7) {
                self::resolvePlayerAttack($db, $log, $marchId, $playerId, $cityId, $targetX, $targetY, (int)$march['target_id'], $intTroops);
            } elseif ($marchType === 8) {
                self::resolveScout($db, $log, $marchId, $playerId, $cityId, $targetX, $targetY, (int)$march['target_id']);
            } else {
                // Unsupported type — return immediately
                $db->execute(
                    "UPDATE marches SET state = 'returning', return_time = UTC_TIMESTAMP() WHERE id = ?",
                    [$marchId],
                );
            }
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
                         wood  = wood  + :w,
                         stone = stone + :s,
                         gold  = gold  + :g
                     WHERE id = :id',
                    [
                        ':f'  => (int) ($loot['food']  ?? 0),
                        ':w'  => (int) ($loot['wood']  ?? 0),
                        ':s'  => (int) ($loot['stone'] ?? 0),
                        ':g'  => (int) ($loot['gold']  ?? 0),
                        ':id' => $cityId,
                    ],
                );
            }

            $db->execute(
                "UPDATE marches SET state = 'complete' WHERE id = ?",
                [$marchId],
            );

            $log->info('[MarchTick] March ' . $marchId . ' completed — troops returned to city ' . $cityId);
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
    ): void {
        // Optimistic lock — prevent double-processing
        $db->execute(
            "UPDATE marches SET state = 'resolving' WHERE id = ? AND state = 'marching'",
            [$marchId],
        );

        $monster = $db->query(
            'SELECT * FROM field_monsters WHERE id = ? AND world_id = 1',
            [$monsterId],
        )->fetch();

        if ($monster === false || (int) $monster['hp_current'] <= 0) {
            self::finalizeMarch($db, $marchId, $troops, [], 'defender_wins');
            $log->info('[MarchTick] March ' . $marchId . ' — monster already dead');
            return;
        }

        $monsterDef  = self::loadMonsterDef((int) $monster['monster_code']);
        $monsterCode = (int) $monster['monster_code'];
        $result      = BattleEngine::resolveMonster($troops, $monster, $monsterDef);

        $db->transaction(function () use (
            $db, $marchId, $playerId, $cityId, $monsterId,
            $targetX, $targetY, $monsterCode, $result,
        ): void {
            if ($result['monster_killed']) {
                $db->execute('DELETE FROM field_monsters WHERE id = ?', [$monsterId]);
                // Spawn charm at monster's tile
                \Conquer\Game\Charm\CharmSpawner::spawn(1, $targetX, $targetY, $monsterCode);
            } else {
                $db->execute(
                    'UPDATE field_monsters SET hp_current = ? WHERE id = ?',
                    [$result['new_monster_hp'], $monsterId],
                );
            }

            $db->execute(
                'INSERT INTO battle_reports
                    (world_id, march_id, attacker_id, attacker_city_id,
                     target_type, target_id, target_x, target_y,
                     outcome, data_json, attacker_read, created_at)
                 VALUES
                    (1, :mid, :pid, :cid,
                     3, :tid, :tx, :ty,
                     :out, :data, 0, UTC_TIMESTAMP())',
                [
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
                     return_time = DATE_ADD(UTC_TIMESTAMP(),
                                   INTERVAL TIMESTAMPDIFF(SECOND, departure_time, arrival_time) SECOND),
                     haul_json   = :haul
                 WHERE id = :id",
                [
                    ':haul' => json_encode(['survivors' => $result['attacker_survivors']]),
                    ':id'   => $marchId,
                ],
            );
        });

        // Award Lord XP for monster kill
        if ($result['monster_killed']) {
            $level = (int) ($monsterDef['level'] ?? 1);
            $xp    = $level * 10; // Level 1 = 10 XP, Level 2 = 20 XP, etc.

            // Deathkar bosses award double XP
            $name = strtolower($monsterDef['name'] ?? '');
            if (str_contains($name, 'deathkar')) {
                $xp = $level * 20;
            }

            \Conquer\Game\Player\LordLevel::addXp($playerId, $xp);
            $db->execute('UPDATE players SET kill_count = kill_count + 1 WHERE id = ?', [$playerId]);
        }

        $log->info(sprintf(
            '[MarchTick] March %d resolved — %s, monster %s',
            $marchId,
            $result['outcome'],
            $result['monster_killed'] ? 'killed' : 'damaged',
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

    private static function resolveCharmCollect(
        Connection $db,
        Logger     $log,
        int        $marchId,
        int        $playerId,
        int        $cityId,
        int        $targetX,
        int        $targetY,
        int        $charmId,
    ): void {
        // Optimistic lock
        $db->execute(
            "UPDATE marches SET state = 'resolving' WHERE id = ? AND state = 'marching'",
            [$marchId],
        );

        // Check charm still exists and is collectible
        $charm = $db->query(
            'SELECT * FROM map_charms
             WHERE id = ? AND world_id = 1 AND collected_by IS NULL AND expires_at > UTC_TIMESTAMP()',
            [$charmId],
        )->fetch();

        if ($charm === false) {
            // Charm expired or already taken — march just returns
            $db->execute(
                "UPDATE marches
                 SET state = 'returning', return_time = UTC_TIMESTAMP()
                 WHERE id = ?",
                [$marchId],
            );
            $log->info('[MarchTick] March ' . $marchId . ' — charm ' . $charmId . ' already gone');
            return;
        }

        $grade    = $charm['grade'];
        $category = $charm['stat_category'];
        $code     = (int) $charm['charm_code'];
        $bonus    = \Conquer\Game\Charm\CharmSpawner::bonusPct($grade);
        $durSecs  = \Conquer\Game\Charm\CharmSpawner::durationSecs($grade);

        $db->transaction(function () use (
            $db, $marchId, $playerId, $charmId, $grade, $category, $code, $bonus, $durSecs,
        ): void {
            // Mark charm as collected
            $db->execute(
                'UPDATE map_charms SET collected_by = ?, collected_at = UTC_TIMESTAMP() WHERE id = ?',
                [$playerId, $charmId],
            );

            // Activate buff (overwrite same category if exists)
            $db->execute(
                'INSERT INTO player_charms_active
                    (player_id, stat_category, grade, charm_code, bonus_pct, activated_at, expires_at)
                 VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND))
                 ON DUPLICATE KEY UPDATE
                    grade        = VALUES(grade),
                    charm_code   = VALUES(charm_code),
                    bonus_pct    = VALUES(bonus_pct),
                    activated_at = UTC_TIMESTAMP(),
                    expires_at   = DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND)',
                [$playerId, $category, $grade, $code, $bonus, $durSecs, $durSecs],
            );

            // Return march
            $db->execute(
                "UPDATE marches
                 SET state       = 'returning',
                     return_time = DATE_ADD(UTC_TIMESTAMP(),
                                   INTERVAL TIMESTAMPDIFF(SECOND, departure_time, arrival_time) SECOND),
                     haul_json   = '{}'
                 WHERE id = ?",
                [$marchId],
            );
        });

        $log->info(sprintf(
            '[MarchTick] March %d — charm %d collected (%s %s +%.0f%%)',
            $marchId, $charmId, $grade, $category, $bonus,
        ));
    }

    private static function resolvePlayerAttack(
        Connection $db,
        Logger     $log,
        int        $marchId,
        int        $playerId,
        int        $cityId,
        int        $targetX,
        int        $targetY,
        int        $targetCityId,
        array      $attackerTroops,
    ): void {
        // Optimistic lock
        $db->execute("UPDATE marches SET state='resolving' WHERE id=? AND state='marching'", [$marchId]);

        // Load defender troops
        $defRows   = $db->query('SELECT troop_code, count FROM city_troops WHERE city_id = ?', [$targetCityId])->fetchAll();
        $defTroops = [];
        foreach ($defRows as $r) $defTroops[(int)$r['troop_code']] = (int)$r['count'];

        // Load attacker + defender city stats
        $atkCity = $db->query('SELECT power, player_id FROM cities WHERE id = ?', [$cityId])->fetch();
        $defCity = $db->query('SELECT power, player_id FROM cities WHERE id = ?', [$targetCityId])->fetch();

        $defPlayerId = (int)($defCity['player_id'] ?? 0);

        // Simple battle: compare total power — slight defender advantage (must exceed 40%)
        $atkPower   = (int)($atkCity['power'] ?? 1);
        $defPower   = (int)($defCity['power'] ?? 1);
        $totalPower = $atkPower + $defPower;

        $attackerWins = $atkPower > ($totalPower * 0.4);
        $outcome      = $attackerWins ? 'attacker_wins' : 'defender_wins';

        // Calculate losses (loser loses 30%, winner loses 10%)
        $attackerLosses = [];
        $defenderLosses = [];

        foreach ($attackerTroops as $code => $count) {
            $lossRate               = $attackerWins ? 0.10 : 0.30;
            $attackerLosses[$code]  = (int)ceil($count * $lossRate);
        }
        foreach ($defTroops as $code => $count) {
            if ($count <= 0) continue;
            $lossRate               = $attackerWins ? 0.30 : 0.10;
            $defenderLosses[$code]  = (int)ceil($count * $lossRate);
        }

        // Loot: 20% of defender resources if attacker wins
        $loot = [];
        if ($attackerWins) {
            $res = $db->query('SELECT food, wood, stone, gold FROM cities WHERE id = ?', [$targetCityId])->fetch();
            if ($res !== false) {
                $loot = [
                    'food'  => (int)floor((float)$res['food']  * 0.20),
                    'wood'  => (int)floor((float)$res['wood']  * 0.20),
                    'stone' => (int)floor((float)$res['stone'] * 0.20),
                    'gold'  => (int)floor((float)$res['gold']  * 0.20),
                ];
            }
        }

        $survivors = [];
        foreach ($attackerTroops as $code => $count) {
            $survivors[$code] = max(0, $count - ($attackerLosses[$code] ?? 0));
        }

        $db->transaction(function () use (
            $db, $marchId, $playerId, $cityId, $defPlayerId, $targetCityId,
            $targetX, $targetY, $attackerTroops, $defTroops, $defenderLosses,
            $attackerLosses, $survivors, $loot, $outcome, $attackerWins,
        ): void {
            // Apply defender losses
            foreach ($defenderLosses as $code => $loss) {
                if ($loss <= 0) continue;
                $db->execute(
                    'UPDATE city_troops SET count = GREATEST(0, count - ?) WHERE city_id = ? AND troop_code = ?',
                    [$loss, $targetCityId, $code],
                );
            }

            // Transfer loot from defender city
            if ($attackerWins && !empty($loot)) {
                $db->execute(
                    'UPDATE cities SET food=GREATEST(0,food-:f), wood=GREATEST(0,wood-:w), stone=GREATEST(0,stone-:s), gold=GREATEST(0,gold-:g) WHERE id=:id',
                    [':f'=>$loot['food'],':w'=>$loot['wood'],':s'=>$loot['stone'],':g'=>$loot['gold'],':id'=>$targetCityId],
                );
            }

            // Increment attacker kill count on victory
            if ($attackerWins) {
                $db->execute('UPDATE players SET kill_count = kill_count + 1 WHERE id = ?', [$playerId]);
            }

            // Battle report (attacker side)
            $reportData = [
                'attacker_troops'    => $attackerTroops,
                'defender_troops'    => $defTroops,
                'attacker_losses'    => $attackerLosses,
                'defender_losses'    => $defenderLosses,
                'attacker_survivors' => $survivors,
                'loot'               => $loot,
                'outcome'            => $outcome,
            ];
            $db->execute(
                'INSERT INTO battle_reports
                    (world_id, march_id, attacker_id, attacker_city_id,
                     target_type, target_id, target_x, target_y,
                     outcome, data_json, attacker_read, created_at)
                 VALUES (1,:mid,:pid,:cid, 2,:tid,:tx,:ty, :out,:data, 0, UTC_TIMESTAMP())',
                [':mid'=>$marchId,':pid'=>$playerId,':cid'=>$cityId,
                 ':tid'=>$targetCityId,':tx'=>$targetX,':ty'=>$targetY,
                 ':out'=>$outcome,':data'=>json_encode($reportData)],
            );

            // Battle report (defender side) — unread notification
            if ($defPlayerId > 0) {
                $db->execute(
                    'INSERT INTO battle_reports
                        (world_id, march_id, attacker_id, attacker_city_id,
                         target_type, target_id, target_x, target_y,
                         outcome, data_json, attacker_read, created_at)
                     VALUES (1,:mid,:pid,:cid, 2,:tid,:tx,:ty, :out,:data, 0, UTC_TIMESTAMP())',
                    [':mid'=>$marchId,':pid'=>$defPlayerId,':cid'=>$targetCityId,
                     ':tid'=>$cityId,':tx'=>$targetX,':ty'=>$targetY,
                     ':out'=>($outcome === 'attacker_wins' ? 'defender_loses' : 'attacker_loses'),
                     ':data'=>json_encode($reportData)],
                );
            }

            // Set march returning with survivors + loot in haul
            $db->execute(
                "UPDATE marches SET state='returning',
                 return_time=DATE_ADD(UTC_TIMESTAMP(), INTERVAL TIMESTAMPDIFF(SECOND,departure_time,arrival_time) SECOND),
                 haul_json=:haul WHERE id=:id",
                [':haul'=>json_encode(['survivors'=>$survivors,'loot'=>$loot]), ':id'=>$marchId],
            );
        });

        $log->info(sprintf('[MarchTick] Player attack march %d resolved — %s', $marchId, $outcome));
    }

    private static function resolveScout(
        Connection $db,
        Logger     $log,
        int        $marchId,
        int        $playerId,
        int        $cityId,
        int        $targetX,
        int        $targetY,
        int        $targetCityId,
    ): void {
        $db->execute("UPDATE marches SET state='resolving' WHERE id=? AND state='marching'", [$marchId]);

        // Gather target city info
        $targetCity = $db->query(
            'SELECT c.food, c.wood, c.stone, c.gold, c.power, c.castle_level,
                    p.username, p.lord_level, p.kill_count
             FROM cities c JOIN players p ON p.id = c.player_id
             WHERE c.id = ?',
            [$targetCityId],
        )->fetch();

        $troops = [];
        if ($targetCity !== false) {
            $troopRows = $db->query(
                'SELECT troop_code, count FROM city_troops WHERE city_id = ? AND count > 0',
                [$targetCityId],
            )->fetchAll();
            foreach ($troopRows as $r) {
                $troops[(int)$r['troop_code']] = (int)$r['count'];
            }
        }

        // Placeholder wall stats — real wall system not yet implemented
        $wallDurability = 27000;
        $wallAtk        = 42.0;
        $wallDef        = 42.0;

        $scoutData = [
            'type'         => 'scout',
            'target_name'  => $targetCity['username']    ?? 'Unknown',
            'target_power' => (int)($targetCity['power'] ?? 0),
            'castle_level' => (int)($targetCity['castle_level'] ?? 1),
            'lord_level'   => (int)($targetCity['lord_level']   ?? 0),
            'wall'         => [
                'durability'     => $wallDurability,
                'durability_max' => $wallDurability,
                'attack_buff'    => $wallAtk,
                'defense_buff'   => $wallDef,
            ],
            'resources' => [
                'food'  => (int)($targetCity['food']  ?? 0),
                'wood'  => (int)($targetCity['wood']  ?? 0),
                'stone' => (int)($targetCity['stone'] ?? 0),
                'gold'  => (int)($targetCity['gold']  ?? 0),
            ],
            'troops'    => $troops,
            'mastery'   => null,   // placeholder — not yet implemented
            'treasures' => null,   // placeholder — not yet implemented
        ];

        $db->transaction(function () use (
            $db, $marchId, $playerId, $cityId, $targetCityId, $targetX, $targetY, $scoutData,
        ): void {
            $db->execute(
                'INSERT INTO battle_reports
                    (world_id, march_id, attacker_id, attacker_city_id,
                     target_type, target_id, target_x, target_y,
                     outcome, data_json, attacker_read, created_at)
                 VALUES (1,:mid,:pid,:cid, 2,:tid,:tx,:ty,"scouted",:data, 0, UTC_TIMESTAMP())',
                [':mid'=>$marchId,':pid'=>$playerId,':cid'=>$cityId,
                 ':tid'=>$targetCityId,':tx'=>$targetX,':ty'=>$targetY,
                 ':data'=>json_encode($scoutData)],
            );
            $db->execute(
                "UPDATE marches SET state='returning',
                 return_time=DATE_ADD(UTC_TIMESTAMP(), INTERVAL TIMESTAMPDIFF(SECOND,departure_time,arrival_time) SECOND),
                 haul_json='{}' WHERE id=?",
                [$marchId],
            );
        });

        $log->info(sprintf('[MarchTick] Scout march %d resolved — scouted city %d', $marchId, $targetCityId));
    }

    /** Load monster definition from data files (cached per request). */
    private static function loadMonsterDef(int $code): array
    {
        static $cache = null;
        if ($cache === null) {
            $spawnCfg    = json_decode((string) file_get_contents(ROOT_DIR . '/data/world_spawn.json'), true);
            $monstersCfg = json_decode((string) file_get_contents(ROOT_DIR . '/data/monsters.json'), true);

            $byCode = [];
            foreach ($spawnCfg['monsters'] as $m) {
                $byCode[(int) $m['code']] = ['name' => $m['monster'], 'level' => (int) $m['level']];
            }

            $stats = [];
            foreach ($monstersCfg['monsters'] as $m) {
                $stats[$m['name'] . '_' . $m['level']] = $m;
            }

            $cache = ['byCode' => $byCode, 'stats' => $stats];
        }

        $spawn = $cache['byCode'][$code] ?? null;
        $name  = $spawn['name']  ?? 'Unknown';
        $level = $spawn['level'] ?? 1;

        $statsKey  = $name . '_' . $level;
        $statEntry = $cache['stats'][$statsKey] ?? null;

        return [
            'name'   => $name,
            'level'  => $level,
            'stats'  => $statEntry['stats']  ?? ['hp' => 100, 'attack' => 50, 'defense' => 30],
            'amount' => $statEntry['amount'] ?? 10,
        ];
    }
}
