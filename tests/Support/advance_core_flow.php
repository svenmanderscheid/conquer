<?php
declare(strict_types=1);
// CLI-only time control for the disposable browser preview. Never accepts a live database.
if (PHP_SAPI !== 'cli') exit(1);
$root = realpath($argv[1] ?? '');
$temporary = realpath(sys_get_temp_dir());
if (!$root || !$temporary || !str_starts_with($root, $temporary . DIRECTORY_SEPARATOR)
    || !preg_match('/^conquer_feature_test_[a-f0-9]{12}$/D', basename($root))) {
    throw new RuntimeException('Disposable preview root required.');
}
define('ROOT_DIR', $root);
require ROOT_DIR . '/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR . '/src'))->register();
$db = \Conquer\Db\Connection::init($root);
if ($db->query('SELECT DATABASE()')->fetchColumn() !== basename($root)) {
    throw new RuntimeException('Fixture database/root mismatch.');
}
$mode = $argv[2] ?? '';
$id = filter_var($argv[3] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($mode === 'prepare') {
    $db->execute('UPDATE cities SET food=1000000,lumber=1000000,stone=1000000,gold=1000000 WHERE id=1 AND player_id=1 AND world_id=1');
    $db->execute('UPDATE city_troops SET count=500 WHERE city_id=1');
    $db->execute('UPDATE players SET action_points=200,last_ap_regen=UTC_TIMESTAMP() WHERE id=1');
    $target = $db->transaction(static function ($db): array {
        \Conquer\Game\Map\WorldPlacement::lockWorld($db, 1);
        $spot = \Conquer\Game\Map\WorldPlacement::findNear($db, 1, 'monster', 85, 65, null, 16, 'forest');
        if (!$spot) throw new RuntimeException('No controlled monster placement.');
        $db->execute("INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current,monster_type,expires_at) VALUES(1,20209901,?,?,1,'solo',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))", [$spot[0], $spot[1]]);
        return ['id' => (int)$db->getPdo()->lastInsertId(), 'x' => $spot[0], 'y' => $spot[1]];
    });
    echo json_encode($target, JSON_THROW_ON_ERROR);
} elseif ($mode === 'building' && $id) {
    if ($db->execute("UPDATE building_queue SET started_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 SECOND),finishes_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=? AND city_id=1 AND is_processed=0", [$id]) !== 1) {
        throw new RuntimeException('Expected active fixture building queue.');
    }
} elseif ($mode === 'march' && $id) {
    if ($db->execute("UPDATE marches SET departure_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 20 SECOND),arrival_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 SECOND),return_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=? AND player_id=1 AND world_id=1 AND state='marching'", [$id]) !== 1) {
        throw new RuntimeException('Expected active fixture march.');
    }
} else {
    throw new RuntimeException('Unknown preview time-control command.');
}
