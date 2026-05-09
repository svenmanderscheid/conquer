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
            $survivors = json_decode((string) ($march['haul_json'] ?? '{}'), true)['survivors'] ?? [];

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

        $monsterDef = self::loadMonsterDef((int) $monster['monster_code']);
        $result     = BattleEngine::resolveMonster($troops, $monster, $monsterDef);

        $db->transaction(function () use (
            $db, $marchId, $playerId, $cityId, $monsterId,
            $targetX, $targetY, $result,
        ): void {
            if ($result['monster_killed']) {
                $db->execute('DELETE FROM field_monsters WHERE id = ?', [$monsterId]);
                // Spawn charm at monster's tile
                \Conquer\Game\Charm\CharmSpawner::spawn(1, $targetX, $targetY, (int)$monster['monster_code']);
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
