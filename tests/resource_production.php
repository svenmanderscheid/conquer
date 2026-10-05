<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR . '/src'))->register();
require __DIR__ . '/Support/FeatureDatabase.php';
date_default_timezone_set('UTC');

use Conquer\Db\Connection;
use Conquer\Game\City\{BuildingData, BuildingPlotService, CityState, ResourceTick};
use Conquer\Game\World\WorldContext;

$checks = 0;
function productionCheck(bool $ok, string $label): void
{
    global $checks;
    if (!$ok) throw new RuntimeException($label);
    ++$checks;
}
function productionNear(float $actual, float $expected, string $label): void
{
    productionCheck(abs($actual - $expected) < 0.000001, $label . ': ' . $actual . ' vs ' . $expected);
}
function productionStock(array $city): array
{
    return array_map('intval', array_intersect_key($city, array_flip(['food', 'lumber', 'stone', 'gold'])));
}

// Expectations deliberately use the pre-change rates, independent of the helper.
$producers = ['farm' => ['food', 300], 'lumber_camp' => ['lumber', 300], 'quarry' => ['stone', 240], 'gold_mine' => ['gold', 150]];
foreach ($producers as $code => [$resource, $oldBase]) {
    foreach ([1, 5, 10, 20, 30] as $level) {
        $expected = $oldBase * 1.3 * pow(1.15, $level - 1);
        productionNear(BuildingData::getHourlyRate($code, $level), $expected, "$code L$level receives exactly 30% more production");
        productionNear(BuildingData::getHourlyRate($code, $level, ['resource_production' => 20, $resource . '_prod_pct' => .07]), $expected * 1.2 * 1.07, "$code L$level applies VIP and resource bonuses once");
    }
    productionNear(BuildingData::getHourlyRate($code, 0), 0, "$code is inactive at level zero");
}
productionNear(BuildingData::getHourlyRate('castle', 30), 0, 'non-production buildings remain inactive');

$fixture = new \ConquerTests\FeatureDatabase();
$db = Connection::getInstance();
try {
    $buildings = array_fill_keys(CityState::BUILDING_CODES, ['level' => 1]);
    $city = ['id' => 0, 'food' => 0, 'lumber' => 0, 'stone' => 0, 'gold' => 0, 'last_resource_update' => gmdate('Y-m-d H:i:s', time() - 3600)];
    $once = ResourceTick::apply($city, $buildings);
    foreach ($producers as [$resource, $oldBase]) {
        productionCheck($once[$resource] === (int)($oldBase * 1.3), "$resource credits one hour at the raised base rate");
    }
    productionCheck(productionStock(ResourceTick::apply($once, $buildings)) === productionStock($once), 'reusing a computed snapshot does not credit its interval twice');
    $future = array_replace($city, ['last_resource_update' => gmdate('Y-m-d H:i:s', time() + 60)]);
    productionCheck(ResourceTick::apply($future, $buildings) === $future, 'future snapshots do not accrue resources');

    $caps = BuildingData::getStorageCaps($buildings);
    $nearFull = $city;
    $overFull = $city;
    foreach ($caps as $resource => $cap) {
        $nearFull[$resource] = $cap - 1;
        $overFull[$resource] = $cap + 1000;
    }
    productionCheck(productionStock(ResourceTick::apply($nearFull, $buildings)) === $caps, 'all four resources stop at their storage caps');
    productionCheck(productionStock(ResourceTick::apply($overFull, $buildings)) === productionStock($overFull), 'above-cap rewards are preserved without additional production');

    // Two cities belonging to the same player exercise the real read and write paths.
    $db->execute("INSERT INTO players(id,username,email,password_hash,vip_points,vip_level) VALUES(1,'ProductionFixture','production@tests.invalid','unused',10000,5)");
    $db->execute("INSERT INTO worlds(id,name,slug,status,map_size,map_seed,speed_factor) VALUES(2,'Fast fixture','fast-fixture','running',256,43,2)");
    foreach ([1, 2] as $world) {
        $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,food,lumber,stone,gold,last_resource_update) VALUES(?,1,?,'Production fixture',40,40,0,0,0,0,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR))", [$world, $world]);
        foreach (CityState::BUILDING_CODES as $code) $db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(?,?,1)', [$world, $code]);
        foreach (['food_production', 'wood_production', 'stone_production', 'gold_production'] as $code) {
            $db->execute('INSERT INTO player_research(player_id,world_id,research_code,level) VALUES(1,?,?,?)', [$world, $code, $world]);
        }
    }
    $db->execute("INSERT INTO active_buffs(player_id,buff_type,multiplier,expires_at) VALUES(1,'production_boost',1.25,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))");
    $db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id) VALUES(1,2,'Production allies','PROD',1)");
    $db->execute("INSERT INTO alliance_members(alliance_id,player_id,world_id,role) VALUES(1,1,2,'leader')");
    foreach (['food', 'lumber', 'stone', 'gold'] as $resource) $db->execute('INSERT INTO alliance_research(alliance_id,research_code,level) VALUES(1,?,1)', ['ally_' . $resource . '_prod']);
    WorldContext::bind(1);
    foreach ([1, 2] as $world) {
        $before = $db->query('SELECT * FROM cities WHERE id=?', [$world])->fetch();
        $state = CityState::loadForPlayer(1, $world);
        foreach ($producers as $code => [$resource, $oldBase]) {
            $research = $world === 1 ? .02 : .05;
            $alliance = $world === 1 ? 0 : ($resource === 'gold' ? .0075 : .01);
            $expected = $oldBase * 1.3 * 1.2 * (1 + $research + $alliance) * $world * 1.25;
            productionNear($state['production_rates'][$code], $expected, "$resource displayed world $world rate combines VIP, research, alliance and timed buffs once");
            $elapsed = strtotime($state['city']['last_resource_update']) - strtotime($before['last_resource_update']);
            productionCheck(abs((int)$state['city'][$resource] - floor($expected * $elapsed / 3600)) <= 1, "$resource offline preview uses displayed world $world rate");
        }
        ResourceTick::persist($before, $state['buildings']);
        $persisted = $db->query('SELECT * FROM cities WHERE id=?', [$world])->fetch();
        $elapsed = strtotime($persisted['last_resource_update']) - strtotime($before['last_resource_update']);
        foreach ($producers as $code => [$resource]) {
            productionCheck(abs((int)$persisted[$resource] - floor($state['production_rates'][$code] * $elapsed / 3600)) <= 1, "$resource settlement uses city world $world even with world 1 selected");
        }
        ResourceTick::persist($before, $state['buildings']);
        $repeated = $db->query('SELECT * FROM cities WHERE id=?', [$world])->fetch();
        foreach ($producers as $code => [$resource]) {
            $extraSeconds = strtotime($repeated['last_resource_update']) - strtotime($persisted['last_resource_update']);
            $maxExtra = (int)ceil($state['production_rates'][$code] * $extraSeconds / 3600);
            productionCheck((int)$repeated[$resource] - (int)$persisted[$resource] <= $maxExtra, "$resource repeated stale settlement cannot duplicate offline earnings");
        }
    }
    productionCheck(WorldContext::id() === 1, 'settling another world preserves active world selection');

    // Retired extra plots still use the raised rate across a completed upgrade.
    $now = time();
    $db->execute('INSERT INTO city_building_plots(city_id,plot_id,building_code,level,level_to,finishes_at) VALUES(1,1,\'gold_mine\',1,2,?)', [gmdate('Y-m-d H:i:s', $now - 1800)]);
    $legacy = BuildingPlotService::production(1, $now - 3600, $now, [], 1);
    productionNear($legacy['gold'], 195 * .5 + 195 * 1.15 * .5, 'legacy plot upgrades use boosted rates on both sides of completion');
    echo "$checks resource production checks passed\n";
} finally {
    $fixture->close();
}
