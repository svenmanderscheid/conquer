<?php
declare(strict_types=1);
/** Real training queues in a disposable database; never changes a player's timers. */
if (PHP_SAPI !== 'cli') exit(1);
date_default_timezone_set('UTC');
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';

use Conquer\Db\Connection;
use Conquer\Game\Buff\ActiveBuffService;
use Conquer\Game\City\{CityState, TroopData, TroopTrainer};
use Conquer\Game\Defense\DefenseService;
use Conquer\Game\Research\{BuffEngine, ResearchEffects};
use Conquer\Game\Inventory\InventoryService;
use Conquer\Game\Kingdom\KingdomService;

function durationCheck(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
    echo "PASS $label\n";
}

$times=[3,5,9,14,20,27,35,44,54,65];
foreach (TroopData::all() as $code=>$troop) {
    durationCheck($troop['time']===$times[$troop['tier']-1]
        && TroopData::trainingSeconds($code,2000)===$times[$troop['tier']-1]*2000,
        "type {$troop['type']} T{$troop['tier']}: new per-unit and batch duration");
}
durationCheck(TroopData::trainingSeconds(50100401,2000)===28000,'2,000 T4 take 7h 46m 40s before bonuses');
foreach ([1=>'infantry',2=>'ranged',3=>'cavalry'] as $type=>$name) {
    $code=50000401+$type*100000;
    $buffs=['training_speed'=>.1,$name.'_training_speed'=>.2];
    $training=ResearchEffects::training($code,$buffs,1.25);
    durationCheck(abs($training['speed_multiplier']-1.625)<1e-9,'general, type-specific and active training bonuses combine for '.$name);
    $quote=DefenseService::promotionQuote($code-100,2000,$buffs,1.25);
    durationCheck($quote['duration_seconds']===(int)ceil(28000*.5/1.625),'promotion uses half the new target-tier duration for '.$name);
}

$fixture=new \ConquerTests\FeatureDatabase();
$db=Connection::getInstance();
try {
    $db->execute("INSERT INTO players(id,username,email,password_hash) VALUES(1,'DurationFixture','duration@tests.invalid','unused')");
    $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,castle_level,food,lumber,stone,gold,last_resource_update) VALUES(1,1,1,'Duration',65,65,30,100000000,100000000,100000000,100000000,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))");
    foreach (CityState::BUILDING_CODES as $school) $db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(1,?,30)',[$school]);

    // The balance update must never silently reset or reprice a saved order.
    $db->execute("INSERT INTO troop_queue(city_id,troop_code,count,barrack_slot,started_at,finishes_at,cost_json) VALUES(1,50100401,2000,1,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 216000 SECOND),?)",[json_encode(TroopData::trainingCost(50100401,2000))]);
    $legacy=$db->query('SELECT * FROM troop_queue WHERE city_id=1')->fetch();
    CityState::loadForPlayer(1);CityState::loadForPlayer(1);
    $after=$db->query('SELECT * FROM troop_queue WHERE id=?',[$legacy['id']])->fetch();
    durationCheck($after===$legacy,'repeated state loads preserve the old 60-hour queue and its payment receipt');
    TroopTrainer::cancel(1,(int)$legacy['id']);

    foreach ([1.0,1.25] as $boost) {
        if ($boost>1) ActiveBuffService::apply(1,'training_boost',$boost,24);
        foreach ([50100401,50200401,50300401] as $code) {
            $state=CityState::loadForPlayer(1);
            $defs=TroopData::forCity($state,BuffEngine::getBuffs(1),[],ActiveBuffService::getMultiplier(1,'training_boost'));
            $definition=array_values(array_filter($defs,static fn($t)=>$t['code']===$code))[0];
            $expected=(int)ceil($definition['time']*2000/$definition['training']['speed_multiplier']);
            TroopTrainer::train($state['city'],$state['buildings'],$code,2000);
            $row=$db->query('SELECT *,TIMESTAMPDIFF(SECOND,started_at,finishes_at) AS duration FROM troop_queue WHERE city_id=1 AND troop_code=? AND is_processed=0',[$code])->fetch();
            durationCheck((int)$row['duration']===$expected && $expected<28800,"actual $code queue matches API preview with boost $boost");
            durationCheck(json_decode($row['cost_json'],true)===TroopData::trainingCost($code,2000),'shorter duration keeps the full resource receipt');
            if ($boost===1.0) TroopTrainer::cancel(1,(int)$row['id']);
        }
    }
    $queues=$db->query('SELECT id,finishes_at FROM troop_queue WHERE city_id=1 AND is_processed=0 ORDER BY barrack_slot')->fetchAll();
    durationCheck(count($queues)===3,'three faster schools still run independently');
    InventoryService::addItems(1,10103003,2);
    $speedup=['action'=>'inventory.use','item_code'=>10103003,'queue_type'=>'training','queue_id'=>(int)$queues[0]['id'],'operation_key'=>'duration_speedup_20260920','expected_world_id'=>1];
    $item=InventoryService::allDefs()[10103003];
    KingdomService::action(1,$speedup);KingdomService::action(1,$speedup);
    $newEnd=$db->query('SELECT finishes_at FROM troop_queue WHERE id=?',[$queues[0]['id']])->fetchColumn();
    durationCheck(strtotime($queues[0]['finishes_at'])-strtotime($newEnd)===(int)$item['duration_seconds'],'speedup subtracts its full seconds exactly once');
    durationCheck((int)$db->query('SELECT quantity FROM player_inventory WHERE player_id=1 AND item_code=10103003')->fetchColumn()===1,'speedup replay consumes one item');
    foreach (array_slice($queues,1) as $other) durationCheck($db->query('SELECT finishes_at FROM troop_queue WHERE id=?',[$other['id']])->fetchColumn()===$other['finishes_at'],'speedup leaves the other school timer unchanged');
    $db->execute('UPDATE troop_queue SET finishes_at=UTC_TIMESTAMP() WHERE city_id=1 AND is_processed=0');
    TroopTrainer::processQueue($db,1);TroopTrainer::processQueue($db,1);
    durationCheck((int)$db->query('SELECT SUM(count) FROM city_troops WHERE city_id=1')->fetchColumn()===6000,'all three batches credit their 2,000 troops exactly once');
    echo "ALL TRAINING DURATION CHECKS PASSED\n";
} finally {
    $fixture->close();
}
