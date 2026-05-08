<?php
declare(strict_types=1);

/**
 * cron/march_tick.php — March resolution tick
 *
 * Run every minute:  * * * * * php /path/to/conquer/cron/march_tick.php
 *
 * Processes:
 *   1. Arrived marches     (state='marching',  arrival_time  <= UTC_TIMESTAMP())
 *   2. Returning marches   (state='returning', return_time   <= UTC_TIMESTAMP())
 */

define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/src/Bootstrap.php';
\Conquer\Bootstrap::init(ROOT_DIR);

$db  = \Conquer\Db\Connection::getInstance();
$log = \Conquer\Logger::getInstance();

// ─────────────────────────────────────────────────────────────────────────────
// Step 1: Process arrived marches
// ─────────────────────────────────────────────────────────────────────────────

try {
    $arrived = $db->query(
        "SELECT * FROM marches
         WHERE state = 'marching' AND arrival_time <= UTC_TIMESTAMP()
         ORDER BY arrival_time ASC
         LIMIT 50",
    )->fetchAll();
} catch (\PDOException $e) {
    $log->error('[march_tick] Query failed (arrived): ' . $e->getMessage());
    exit(1);
}

foreach ($arrived as $march) {
    $marchId   = (int) $march['id'];
    $marchType = (int) $march['march_type'];
    $cityId    = (int) $march['origin_city_id'];
    $playerId  = (int) $march['player_id'];
    $targetX   = (int) $march['target_x'];
    $targetY   = (int) $march['target_y'];
    $monsterId = (int) $march['target_id'];

    // Decode troops
    $troops = json_decode((string) $march['troops_json'], true) ?? [];
    $intTroops = [];
    foreach ($troops as $code => $count) {
        $intTroops[(int) $code] = (int) $count;
    }

    if ($marchType === 5) {
        // ── Monster attack ────────────────────────────────────────────────────
        resolveMonsterMarch($db, $log, $marchId, $playerId, $cityId, $targetX, $targetY, $monsterId, $intTroops);
    } else {
        // Unsupported march type — just mark as returning immediately
        $db->execute(
            "UPDATE marches SET state = 'returning', return_time = UTC_TIMESTAMP() WHERE id = ?",
            [$marchId],
        );
        $log->info('[march_tick] Unsupported march_type ' . $marchType . ' for march ' . $marchId . ' — skipped');
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Step 2: Process returning marches
// ─────────────────────────────────────────────────────────────────────────────

try {
    $returning = $db->query(
        "SELECT * FROM marches
         WHERE state = 'returning' AND return_time <= UTC_TIMESTAMP()
         ORDER BY return_time ASC
         LIMIT 50",
    )->fetchAll();
} catch (\PDOException $e) {
    $log->error('[march_tick] Query failed (returning): ' . $e->getMessage());
    exit(1);
}

foreach ($returning as $march) {
    $marchId   = (int) $march['id'];
    $cityId    = (int) $march['origin_city_id'];
    $survivors = json_decode((string) ($march['haul_json'] ?? '{}'), true)['survivors'] ?? [];

    // Credit surviving troops back to city.
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

    $log->info('[march_tick] March ' . $marchId . ' completed — troops returned to city ' . $cityId);
}

echo 'March tick done. Arrived: ' . count($arrived) . ', Returned: ' . count($returning) . PHP_EOL;

// ─────────────────────────────────────────────────────────────────────────────
// Helper: resolve a monster march
// ─────────────────────────────────────────────────────────────────────────────

function resolveMonsterMarch(
    \Conquer\Db\Connection $db,
    \Conquer\Logger        $log,
    int                    $marchId,
    int                    $playerId,
    int                    $cityId,
    int                    $targetX,
    int                    $targetY,
    int                    $monsterId,
    array                  $troops,
): void {
    // Mark as resolving first to prevent double-processing.
    $db->execute(
        "UPDATE marches SET state = 'resolving' WHERE id = ? AND state = 'marching'",
        [$marchId],
    );

    // Load monster current state.
    $monster = $db->query(
        'SELECT * FROM field_monsters WHERE id = ? AND world_id = 1',
        [$monsterId],
    )->fetch();

    if ($monster === false || (int) $monster['hp_current'] <= 0) {
        // Monster already dead — return troops immediately.
        finalizeMarch($db, $marchId, $troops, [], 'defender_wins', $targetX, $targetY, null, null);
        $log->info('[march_tick] March ' . $marchId . ' arrived but monster ' . $monsterId . ' already dead');
        return;
    }

    // Load monster definition from data files.
    $monsterDef = resolveMonsterDef((int) $monster['monster_code']);

    // Resolve battle.
    $result = \Conquer\Game\March\BattleEngine::resolveMonster($troops, $monster, $monsterDef);

    $db->transaction(function () use (
        $db, $marchId, $playerId, $cityId, $monsterId,
        $targetX, $targetY, $monster, $monsterDef, $result,
    ): void {
        // Update or delete monster.
        if ($result['monster_killed']) {
            $db->execute('DELETE FROM field_monsters WHERE id = ?', [$monsterId]);
        } else {
            $db->execute(
                'UPDATE field_monsters SET hp_current = ? WHERE id = ?',
                [$result['new_monster_hp'], $monsterId],
            );
        }

        // Create battle report.
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

        // Calculate march return time (same duration as outward march).
        $db->execute(
            "UPDATE marches
             SET state      = 'returning',
                 return_time = DATE_ADD(UTC_TIMESTAMP(), INTERVAL TIMESTAMPDIFF(SECOND, departure_time, arrival_time) SECOND),
                 haul_json   = :haul
             WHERE id = :id",
            [
                ':haul' => json_encode(['survivors' => $result['attacker_survivors']]),
                ':id'   => $marchId,
            ],
        );
    });

    $log->info(sprintf(
        '[march_tick] March %d resolved — outcome: %s, monster %s',
        $marchId,
        $result['outcome'],
        $result['monster_killed'] ? 'killed' : 'damaged (HP ' . $result['new_monster_hp'] . ')',
    ));
}

/**
 * Finalize a march when no battle occurs (e.g., monster already dead).
 */
function finalizeMarch(
    \Conquer\Db\Connection $db,
    int   $marchId,
    array $troops,
    array $losses,
    string $outcome,
    int   $targetX,
    int   $targetY,
    ?int  $monsterId,
    ?array $monsterDef,
): void {
    $survivors = [];
    foreach ($troops as $code => $count) {
        $lost = (int) ($losses[$code] ?? 0);
        $survivors[$code] = max(0, $count - $lost);
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

/**
 * Load monster definition from world_spawn.json + monsters.json by code.
 *
 * @return array<string, mixed>
 */
function resolveMonsterDef(int $code): array
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

    $spawn    = $cache['byCode'][$code] ?? null;
    $name     = $spawn['name']  ?? 'Unknown';
    $level    = $spawn['level'] ?? 1;

    $statsKey  = $name . '_' . ($level - 1);
    $statEntry = $cache['stats'][$statsKey] ?? $cache['stats'][$name . '_' . $level] ?? null;

    return [
        'name'   => $name,
        'level'  => $level,
        'stats'  => $statEntry['stats']  ?? ['hp' => 100, 'attack' => 50, 'defense' => 30],
        'amount' => $statEntry['amount'] ?? 10,
    ];
}
