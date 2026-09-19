<?php
declare(strict_types=1);

use Conquer\Db\Connection;
use Conquer\Db\MigrationSql;
use Conquer\Game\City\BuildingData;
use Conquer\Game\City\CityState;

if (PHP_SAPI !== 'cli') exit(1);
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__));
    require ROOT_DIR.'/src/Bootstrap.php';
    \Conquer\Bootstrap::init(ROOT_DIR);
}

// Keep this callable against the disposable connection used by lifecycle tests.
(static function (array $args): void {
    $db = Connection::getInstance();
    $apply = in_array('--apply', $args, true);
    $file = '0094_building_cost_snapshot.sql';
    $migrationLock = 'conquer-migrate-balance';
    if ((int)$db->query('SELECT GET_LOCK(?,10)', [$migrationLock])->fetchColumn() !== 1) {
        throw new RuntimeException('The balance migration is already running.');
    }
    try {
        if ($apply) {
            MigrationSql::apply($db->getPdo(), (string)file_get_contents(ROOT_DIR.'/migrations/'.$file));
            $db->execute('INSERT IGNORE INTO migrations(filename) VALUES(?)', [$file]);
        }
        $changed = 0;
        $cities = $db->query('SELECT id,player_id FROM cities ORDER BY id')->fetchAll();
        foreach ($cities as $city) {
            $playerLock = 'conquer-player-'.$city['player_id'];
            if ((int)$db->query('SELECT GET_LOCK(?,5)', [$playerLock])->fetchColumn() !== 1) {
                throw new RuntimeException('A city is currently settling; rerun this resumable migration.');
            }
            try {
                $changed += $db->transaction(static function (Connection $db) use ($city, $apply): int {
                    $stored = $db->query('SELECT power FROM cities WHERE id=? FOR UPDATE', [$city['id']])->fetch();
                    if (!$stored) return 0;
                    $levels = $db->query('SELECT building_code,level FROM city_buildings WHERE city_id=? FOR UPDATE', [$city['id']])->fetchAll(PDO::FETCH_KEY_PAIR);
                    $buildings = [];
                    foreach (CityState::BUILDING_CODES as $code) {
                        $buildings[$code] = ['level'=>(int)($levels[$code] ?? 1)];
                    }
                    $power = BuildingData::calculateCityPower($buildings);
                    if ((int)$stored['power'] === $power) return 0;
                    if ($apply) $db->execute('UPDATE cities SET power=? WHERE id=?', [$power, $city['id']]);
                    return 1;
                });
            } finally {
                $db->query('SELECT RELEASE_LOCK(?)', [$playerLock]);
            }
        }
        echo ($apply ? 'READY' : 'CHECK').' '.$file.': '.$changed.' of '.count($cities).' cities '.($apply ? 'updated' : 'need a power refresh').".\n";
        if (!$apply) echo "Use --apply to install the schema and synchronize cached building power.\n";
    } finally {
        $db->query('SELECT RELEASE_LOCK(?)', [$migrationLock]);
    }
})($argv);
