<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);

define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/src/Bootstrap.php';
\Conquer\Bootstrap::init(ROOT_DIR);

use Conquer\Db\Connection;
use Conquer\Game\Map\{MonsterData, WorldPlacement};
use Conquer\Game\World\{RegionalSpawns, WorldContext, WorldSettings};

$worldId = max(1, (int)($argv[1] ?? 1));
$level = max(1, min(10, (int)($argv[2] ?? 1)));
$code = 20200500 + $level;
$db = Connection::getInstance();

$result = $db->transaction(static function (Connection $db) use ($worldId, $level, $code): array {
    $size = WorldPlacement::lockWorld($db, $worldId);
    $existing = $db->query(
        'SELECT id,coord_x,coord_y,monster_code FROM field_monsters WHERE world_id=? AND monster_code BETWEEN 20200501 AND 20200510 AND hp_current>0 AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP()) ORDER BY id LIMIT 1 FOR UPDATE',
        [$worldId],
    )->fetch();
    if ($existing) return ['created' => false, 'monster' => $existing];

    $definition = WorldContext::run($worldId, static fn(): array => MonsterData::get($code));
    $span = max(1, $size - 2);
    $position = null;
    for ($index = 0; $index < $size * $size; $index++) {
        $x = 1 + (($index * 73 + 89) % $span);
        $y = 1 + (($index * 151 + 47) % $span);
        if (WorldPlacement::canPlace($db, $worldId, 'boss', $x, $y)) {
            $position = [$x, $y];
            break;
        }
    }
    if ($position === null) throw new RuntimeException('Kein freies 2×2-Gebiet für Dämmerhorn gefunden.');

    [$x, $y] = $position;
    $hp = max(1, (int)round((float)$definition['stats']['hp'] * (int)$definition['amount']));
    $hours = (int)WorldSettings::get($worldId)['settings']['monster_lifetime_hours'];
    $expires = gmdate('Y-m-d H:i:s', time() + max(1, $hours) * 3600);
    $db->execute(
        'INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current,monster_type,expires_at) VALUES(?,?,?,?,?,?,?)',
        [$worldId, $code, $x, $y, $hp, 'rally', $expires],
    );
    $id = $db->lastInsertId();
    RegionalSpawns::stamp('field_monsters', $id, $worldId, $x, $y);

    return ['created' => true, 'monster' => ['id' => $id, 'world_id' => $worldId, 'monster_code' => $code, 'name' => 'Dämmerhorn', 'level' => $level, 'coord_x' => $x, 'coord_y' => $y, 'hp' => $hp, 'expires_at' => $expires]];
});

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
