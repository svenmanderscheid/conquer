<?php
declare(strict_types=1);
/** An incoming campaign cannot restore an obsolete income rate for the old owner. */
if (PHP_SAPI !== 'cli') exit(1);
define('ROOT_DIR', dirname(__DIR__));
date_default_timezone_set('UTC');
require ROOT_DIR . '/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR . '/src'))->register();
require __DIR__ . '/Support/FeatureDatabase.php';

use Conquer\Db\Connection;
use Conquer\Game\City\CityState;
use Conquer\Game\Territory\{TerritoryService, TerritoryEconomy};
use Conquer\Game\World\{WorldContext, WorldMapProfile};

function incomeCheck(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException($message);
    echo "PASS $message\n";
}

$fixture = new \ConquerTests\FeatureDatabase();
try {
    $db = Connection::getInstance();
    $db->execute("INSERT INTO worlds(id,name,slug,status,map_size) VALUES(2,'Income transition fixture','income-transition','running',768)");
    WorldMapProfile::configureEmptyWorld(2);
    WorldContext::bind(2);
    TerritoryService::ensureWorld(2);
    $rules = TerritoryService::saveProfile(2, ['pvp_window_start_hour_utc'=>0, 'pvp_window_hours'=>24], 1);
    $target = $db->query("SELECT * FROM territory_targets WHERE world_id=2 AND kind='commune' AND benefit_type='food' ORDER BY id LIMIT 1")->fetch();
    foreach ([1, 2] as $player) {
        $db->execute("INSERT INTO players(id,username,email,password_hash) VALUES(?,?,?,'unused')", [$player, 'IncomeFixture'.$player, 'income'.$player.'@invalid.test']);
        $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y) VALUES(?,?,2,'Income fixture',?,?)", [$player, $player, (int)$target['x'] + 25 + $player * 5, (int)$target['y']]);
        foreach (CityState::BUILDING_CODES as $building) $db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(?,?,10)', [$player, $building]);
        $db->execute('INSERT INTO city_troops(city_id,troop_code,count) VALUES(?,50100101,10000)', [$player]);
        $db->execute('INSERT INTO alliances(id,world_id,name,tag,leader_id) VALUES(?,2,?,?,?)', [$player, 'Income alliance '.$player, 'I'.$player, $player]);
        $db->execute("INSERT INTO alliance_members(world_id,alliance_id,player_id,role) VALUES(2,?,?,'leader')", [$player, $player]);
    }
    $db->transaction(fn()=>TerritoryEconomy::transfer($target, 1, time()-3600, 0, $rules, 'fixture:original-owner'));
    $attack = TerritoryService::action(2, [
        'action'=>'start', 'world_id'=>2, 'expected_world_id'=>2,
        'request_id'=>'income_transition_attack_001', 'target_id'=>$target['id'],
        'city_id'=>2, 'troops'=>[50100101=>1000], 'rally_minutes'=>1,
    ], 2);
    $campaign = $db->query('SELECT rules_json FROM territory_campaigns WHERE id=?', [$attack['campaign_id']])->fetchColumn();
    $snapshot = json_decode($campaign, true, 32, JSON_THROW_ON_ERROR);
    $updated = TerritoryService::saveProfile(2, ['income_per_hour'=>3600, 'conquest_reward_gold'=>900], $rules['version']);
    $atChange = TerritoryService::target(2, $target['id']);
    $bankAtChange = (int)$db->query('SELECT food FROM alliance_treasury WHERE alliance_id=1')->fetchColumn();
    incomeCheck($bankAtChange >= 1200 && (int)$snapshot['income_per_hour']===1200 && (int)$updated['income_per_hour']===3600, 'profile change settles the old rate while preserving the incoming campaign snapshot');

    $launch = strtotime($db->query('SELECT launch_at FROM rallies WHERE id=?', [$attack['rally_id']])->fetchColumn().' UTC');
    TerritoryService::tick(2, $launch, 1);
    $arrival = strtotime($db->query('SELECT arrival_time FROM rallies WHERE id=?', [$attack['rally_id']])->fetchColumn().' UTC');
    TerritoryService::tick(2, $arrival, 1);
    $after = TerritoryService::target(2, $target['id']);
    incomeCheck((int)$after['owner_alliance_id']===2, 'the original incoming campaign still captures the commune after the profile change');
    $expected = $bankAtChange + $arrival - strtotime($atChange['last_income_at'].' UTC');
    $actual = (int)$db->query('SELECT food FROM alliance_treasury WHERE alliance_id=1')->fetchColumn();
    incomeCheck($actual===$expected, 'old owner receives the new continuous income rate until the actual capture (expected '.$expected.', actual '.$actual.')');
    $reward = json_decode($db->query('SELECT reward_json FROM territory_rewards WHERE player_id=2 AND event_key=?', ['campaign:'.$attack['campaign_id']])->fetchColumn(), true);
    incomeCheck((int)$reward['gold']===500, 'the attack reward remains bound to its original campaign rules');
    TerritoryService::tick(2, $arrival, 1);
    incomeCheck((int)$db->query('SELECT food FROM alliance_treasury WHERE alliance_id=1')->fetchColumn()===$expected, 'repeated settlement cannot credit the old owner again');
    echo "ALL TERRITORY INCOME TRANSITION CHECKS PASSED\n";
} finally {
    $fixture->close();
}
