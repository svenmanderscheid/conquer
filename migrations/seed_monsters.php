<?php
declare(strict_types=1);

/**
 * One-time monster seeder for World 1.
 *
 * Usage:
 *   php migrations/seed_monsters.php
 *
 * Places an initial set of solo monsters (Orc / Skeleton / Golem) across
 * the 256×256 world map — 15 per sector × 8 sectors = ~120 total.
 *
 * Safe to re-run: skips tiles already occupied.
 * The hourly cron (cron/world_spawn_tick.php) handles ongoing population.
 *
 * NOTE: monster_code follows world_spawn.json convention:
 *   202001XX = Orc,  202002XX = Skeleton,  202003XX = Golem  (XX = level 01-10)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Seeder must be executed from the command line.' . PHP_EOL);
}

define('ROOT_DIR', dirname(__DIR__));
require_once ROOT_DIR . '/src/Bootstrap.php';
\Conquer\Bootstrap::init(ROOT_DIR);

$db = \Conquer\Db\Connection::getInstance();

// ---------------------------------------------------------------------------
// Build HP lookup by (monster_name, level) from monsters.json
// monsters.json uses a 1-off code vs world_spawn.json, so we match by name+level
// ---------------------------------------------------------------------------

$monstersJson = json_decode(
    (string) file_get_contents(ROOT_DIR . '/data/monsters.json'),
    true
);

// key: "Name_level" → total group HP (stats.hp × amount)
$hpByNameLevel = [];
foreach ($monstersJson['monsters'] as $m) {
    $key = $m['name'] . '_' . $m['level'];
    $hpByNameLevel[$key] = (int) round($m['stats']['hp'] * $m['amount']);
}

// Resolve HP for a world_spawn code.
// world_spawn: 20200101 = Orc Lv 1, 20200201 = Skeleton Lv 1, etc.
// code % 100 = level, floor(code/100) % 100 = type index
function resolveHp(int $code, array $hpByNameLevel): int
{
    $level = $code % 100;
    $typeIdx = (int) (floor($code / 100) % 100);
    $name = match ($typeIdx) {
        1 => 'Orc',
        2 => 'Skeleton',
        3 => 'Golem',
        4 => 'Treasure Goblin',
        5 => 'Deathkar',
        default => null,
    };
    if ($name === null) return $level * 5000;
    return $hpByNameLevel["{$name}_{$level}"] ?? $level * 5000;
}

// ---------------------------------------------------------------------------
// Seeded LCG for reproducible placement
// ---------------------------------------------------------------------------

$lcgState = 314159265;

function lcgNext(int &$state): float
{
    $state = (int) (($state * 1103515245 + 12345) & 0x7FFFFFFF);
    return $state / 0x7FFFFFFF;
}

function lcgRange(int &$state, int $min, int $max): int
{
    return $min + (int) (lcgNext($state) * ($max - $min + 1));
}

// ---------------------------------------------------------------------------
// Placement constraints
// ---------------------------------------------------------------------------

// Shrine positions (from migration 0015) — keep 20-tile clearance
$shrines = [
    [28, 58], [99, 61], [163, 67], [220, 59],
    [35, 195], [94, 188], [157, 194], [221, 191],
];

function tooCloseToShrine(int $x, int $y, array $shrines, int $minDist): bool
{
    foreach ($shrines as [$sx, $sy]) {
        if (abs($x - $sx) < $minDist && abs($y - $sy) < $minDist) {
            return true;
        }
    }
    return false;
}

function tooCloseToCity(int $x, int $y, array $cities, int $minDist): bool
{
    foreach ($cities as [$cx, $cy]) {
        if (abs($x - $cx) < $minDist && abs($y - $cy) < $minDist) {
            return true;
        }
    }
    return false;
}

// Load existing city positions
$cityRows = $db->query('SELECT coord_x, coord_y FROM cities WHERE world_id = 1')->fetchAll();
$cities   = array_map(static fn($r) => [(int) $r['coord_x'], (int) $r['coord_y']], $cityRows);

// ---------------------------------------------------------------------------
// Monster pool (world_spawn codes, weighted by rarity)
// ---------------------------------------------------------------------------

// [code, weight]
$pool = [
    [20200101, 14],  // Orc Lv 1       — very common
    [20200102, 10],  // Orc Lv 2
    [20200103, 6],   // Orc Lv 3
    [20200104, 3],   // Orc Lv 4
    [20200201, 10],  // Skeleton Lv 1
    [20200202, 7],   // Skeleton Lv 2
    [20200203, 4],   // Skeleton Lv 3
    [20200301, 8],   // Golem Lv 1
    [20200302, 4],   // Golem Lv 2
    [20200303, 2],   // Golem Lv 3     — uncommon
];

$weightedPool = [];
foreach ($pool as [$code, $weight]) {
    $hp = resolveHp($code, $hpByNameLevel);
    for ($i = 0; $i < $weight; $i++) {
        $weightedPool[] = [$code, $hp];
    }
}
$poolSize = count($weightedPool);

// ---------------------------------------------------------------------------
// Sector layout: 4 cols × 2 rows, each 64×128 tiles
// ---------------------------------------------------------------------------

$sectors = [];
for ($row = 0; $row < 2; $row++) {
    for ($col = 0; $col < 4; $col++) {
        $sectors[] = [
            'x_min' => $col * 64 + 4,
            'x_max' => ($col + 1) * 64 - 4,
            'y_min' => $row * 128 + 4,
            'y_max' => ($row + 1) * 128 - 4,
        ];
    }
}

// ---------------------------------------------------------------------------
// Seed monsters
// ---------------------------------------------------------------------------

$worldId    = 1;
$perSector  = 15;
$minCityDist = 5;
$minShrineDist = 20;
$now        = gmdate('Y-m-d H:i:s');
$totalInserted = 0;
$totalSkipped  = 0;

foreach ($sectors as $idx => $s) {
    $placed   = 0;
    $attempts = 0;

    while ($placed < $perSector && $attempts < $perSector * 20) {
        $attempts++;
        $x = lcgRange($lcgState, $s['x_min'], $s['x_max']);
        $y = lcgRange($lcgState, $s['y_min'], $s['y_max']);

        if (tooCloseToShrine($x, $y, $shrines, $minShrineDist)) continue;
        if (tooCloseToCity($x, $y, $cities, $minCityDist)) continue;

        // Skip occupied tiles
        $existing = $db->query(
            'SELECT id FROM field_monsters WHERE world_id = ? AND coord_x = ? AND coord_y = ? LIMIT 1',
            [$worldId, $x, $y]
        )->fetch();
        if ($existing !== false) {
            $totalSkipped++;
            continue;
        }

        $poolIdx = lcgRange($lcgState, 0, $poolSize - 1);
        [$code, $hp] = $weightedPool[$poolIdx];

        $db->execute(
            'INSERT INTO field_monsters (world_id, monster_code, coord_x, coord_y, hp_current, spawned_at)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$worldId, $code, $x, $y, $hp, $now]
        );

        $placed++;
        $totalInserted++;
    }

    $sectorLabel = "Sector {$idx} ({$s['x_min']}-{$s['x_max']}, {$s['y_min']}-{$s['y_max']})";
    echo "[OK]  {$sectorLabel}: {$placed} monsters placed\n";
}

echo "\nDone. {$totalInserted} monsters inserted, {$totalSkipped} tiles skipped.\n";
