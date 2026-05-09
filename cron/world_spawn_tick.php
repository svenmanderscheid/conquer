<?php
declare(strict_types=1);

/**
 * World Spawn Tick — runs every hour via cron.
 *
 * Cron line (add via Hostinger panel):
 *   0 * * * *  php /path/to/conquer/cron/world_spawn_tick.php
 *
 * What it does per tick:
 *   1. Removes expired entities (monsters past their despawn_hours)
 *   2. For each solo monster type in world_spawn.json:
 *      — Calculates spawn count per sector using fractional-rate rules
 *      — Respects per-sector caps
 *      — Places monsters randomly (avoids cities + shrines)
 *
 * Scope: solo monsters (Orc/Skeleton/Golem) only.
 * Treasure Goblins, Deathkar, Dragons, Magdar are deferred to later sprints.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Spawn tick must be executed from the command line.' . PHP_EOL);
}

define('ROOT_DIR', dirname(__DIR__));
require_once ROOT_DIR . '/src/Bootstrap.php';
\Conquer\Bootstrap::init(ROOT_DIR);

$db  = \Conquer\Db\Connection::getInstance();
$log = \Conquer\Logger::getInstance();

$worldId = 1;
$now     = gmdate('Y-m-d H:i:s');

// ---------------------------------------------------------------------------
// Load config
// ---------------------------------------------------------------------------

$spawnCfg = json_decode(
    (string) file_get_contents(ROOT_DIR . '/data/world_spawn.json'),
    true
);
$monstersJson = json_decode(
    (string) file_get_contents(ROOT_DIR . '/data/monsters.json'),
    true
);

// Sector caps
$sectorCaps = $spawnCfg['sector_caps'];

// Build HP lookup: (name, level_0indexed) → total group HP
$hpByNameLevel = [];
foreach ($monstersJson['monsters'] as $m) {
    $hpByNameLevel[$m['name'] . '_' . $m['level']] = (int) round($m['stats']['hp'] * $m['amount']);
}

// ---------------------------------------------------------------------------
// Helper: resolve HP for a world_spawn monster code
// world_spawn: 20200101 = Orc Lv 1, monsters.json: 20200101 = Orc Lv 0 (1-off)
// We match by name + (level-1) in monsters.json
// ---------------------------------------------------------------------------

function resolveHp(int $code, array $hpByNameLevel): int
{
    $level   = $code % 100;
    $typeIdx = (int) (floor($code / 100) % 100);
    $name    = match ($typeIdx) {
        1 => 'Orc',
        2 => 'Skeleton',
        3 => 'Golem',
        4 => 'Treasure Goblin',
        5 => 'Deathkar',
        default => null,
    };
    if ($name === null) return $level * 5000;
    return $hpByNameLevel["{$name}_" . ($level - 1)]
        ?? $hpByNameLevel["{$name}_{$level}"]
        ?? $level * 5000;
}

// ---------------------------------------------------------------------------
// Helper: fractional spawn rate → integer spawn count
// ---------------------------------------------------------------------------

function calcSpawnCount(float $rate): int
{
    if ($rate <= 0.0) return 0;
    if ($rate >= 1.0) {
        $base = (int) floor($rate);
        return $base + ((mt_rand() / mt_getrandmax()) < ($rate - $base) ? 1 : 0);
    }
    return ((mt_rand() / mt_getrandmax()) < $rate) ? 1 : 0;
}

// ---------------------------------------------------------------------------
// Sector layout: 4 cols × 2 rows, each 64×128 tiles
// ---------------------------------------------------------------------------

$sectors = [];
for ($row = 0; $row < 2; $row++) {
    for ($col = 0; $col < 4; $col++) {
        $sectors[] = [
            'x_min' => $col * 64,
            'x_max' => ($col + 1) * 64 - 1,
            'y_min' => $row * 128,
            'y_max' => ($row + 1) * 128 - 1,
        ];
    }
}

// ---------------------------------------------------------------------------
// Load static positions to avoid (cities + shrines) for this tick
// ---------------------------------------------------------------------------

$cityRows = $db->query(
    'SELECT coord_x, coord_y FROM cities WHERE world_id = ?',
    [$worldId]
)->fetchAll();
$cities = array_map(static fn($r) => [(int) $r['coord_x'], (int) $r['coord_y']], $cityRows);

$shrineRows = $db->query(
    'SELECT coord_x, coord_y FROM shrines WHERE world_id = ?',
    [$worldId]
)->fetchAll();
$shrines = array_map(static fn($r) => [(int) $r['coord_x'], (int) $r['coord_y']], $shrineRows);

$minCityDist   = (int) $spawnCfg['spawn_rules']['placement_constraints']['min_distance_from_player_city_tiles'];
$minShrineDist = (int) $spawnCfg['spawn_rules']['placement_constraints']['min_distance_from_shrine_tiles'];

function isTileBlocked(int $x, int $y, array $cities, array $shrines, int $minCity, int $minShrine): bool
{
    foreach ($cities as [$cx, $cy]) {
        if (abs($x - $cx) < $minCity && abs($y - $cy) < $minCity) return true;
    }
    foreach ($shrines as [$sx, $sy]) {
        if (abs($x - $sx) < $minShrine && abs($y - $sy) < $minShrine) return true;
    }
    return false;
}

// ---------------------------------------------------------------------------
// Step 1: Remove expired field monsters
// ---------------------------------------------------------------------------

// Build despawn map: monster_code → despawn_hours
$despawnHours = [];
foreach ($spawnCfg['monsters'] as $m) {
    $despawnHours[$m['code']] = (float) $m['despawn_hours'];
}

// Group codes by their despawn window and delete in batches
// Simplification: group by despawn_hours value
$byDespawn = [];
foreach ($despawnHours as $code => $hours) {
    $byDespawn[(string) $hours][] = $code;
}

$totalDeleted = 0;
foreach ($byDespawn as $hours => $codes) {
    $placeholders = implode(',', array_fill(0, count($codes), '?'));
    $params       = array_merge([$worldId], $codes);
    $result       = $db->execute(
        "DELETE FROM field_monsters
         WHERE world_id = ?
           AND monster_code IN ({$placeholders})
           AND spawned_at < DATE_SUB(NOW(), INTERVAL {$hours} HOUR)",
        $params
    );
    // PDO execute() wraps execute, rowCount is on the statement — use a raw query
    // The above deletes; count is approximate from logging
}

// Delete expired uncollected charms
$db->execute(
    'DELETE FROM map_charms WHERE expires_at < UTC_TIMESTAMP() AND collected_by IS NULL',
    [],
);

// Also delete any monster code not in despawn map (orphan cleanup)
// Skipping for now — only add if needed.

// ---------------------------------------------------------------------------
// Step 2: Spawn solo monsters per sector
// ---------------------------------------------------------------------------

// Filter to solo monster types only (Orc / Skeleton / Golem)
$soloTypes = ['Orc', 'Skeleton', 'Golem'];

$spawnMonsters = array_filter(
    $spawnCfg['monsters'],
    static fn($m) => in_array($m['monster'], $soloTypes, true)
);

$totalSpawned = 0;

foreach ($sectors as $sIdx => $sector) {
    // Current solo monster count in this sector
    $monsterCount = (int) $db->query(
        'SELECT COUNT(*) FROM field_monsters
         WHERE world_id = ?
           AND coord_x BETWEEN ? AND ?
           AND coord_y BETWEEN ? AND ?',
        [$worldId, $sector['x_min'], $sector['x_max'], $sector['y_min'], $sector['y_max']]
    )->fetchColumn();

    $charmCount = (int) $db->query(
        'SELECT COUNT(*) FROM map_charms
         WHERE world_id = ?
           AND collected_by IS NULL
           AND expires_at > UTC_TIMESTAMP()
           AND coord_x BETWEEN ? AND ?
           AND coord_y BETWEEN ? AND ?',
        [$worldId, $sector['x_min'], $sector['x_max'], $sector['y_min'], $sector['y_max']]
    )->fetchColumn();

    $currentCount = $monsterCount + $charmCount;

    $cap       = (int) $sectorCaps['solo_monsters_combined'];
    $available = max(0, $cap - $currentCount);

    if ($available <= 0) {
        continue;
    }

    $sectorSpawned = 0;

    foreach ($spawnMonsters as $monsterDef) {
        if ($sectorSpawned >= $available) break;

        $rate       = (float) $monsterDef['spawn_per_sector_per_hour'];
        $spawnCount = min(calcSpawnCount($rate), $available - $sectorSpawned);

        if ($spawnCount <= 0) continue;

        $code = (int) $monsterDef['code'];
        $hp   = resolveHp($code, $hpByNameLevel);

        for ($i = 0; $i < $spawnCount; $i++) {
            // Try to find a free tile
            $placed   = false;
            $attempts = 0;

            while (!$placed && $attempts < 30) {
                $attempts++;
                $x = mt_rand($sector['x_min'], $sector['x_max']);
                $y = mt_rand($sector['y_min'], $sector['y_max']);

                if (isTileBlocked($x, $y, $cities, $shrines, $minCityDist, $minShrineDist)) {
                    continue;
                }

                // Check occupancy
                $occupied = (bool) $db->query(
                    'SELECT id FROM field_monsters WHERE world_id = ? AND coord_x = ? AND coord_y = ? LIMIT 1',
                    [$worldId, $x, $y]
                )->fetch();

                if ($occupied) continue;

                $db->execute(
                    'INSERT INTO field_monsters (world_id, monster_code, coord_x, coord_y, hp_current, spawned_at)
                     VALUES (?, ?, ?, ?, ?, ?)',
                    [$worldId, $code, $x, $y, $hp, $now]
                );
                $placed = true;
                $sectorSpawned++;
                $totalSpawned++;
            }
        }
    }
}

$log->info("world_spawn_tick: despawn cleanup done, {$totalSpawned} monsters spawned across 8 sectors.");
echo "Tick complete. {$totalSpawned} monsters spawned.\n";
