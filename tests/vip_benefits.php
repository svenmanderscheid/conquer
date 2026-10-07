<?php
declare(strict_types=1);
/** User-supplied VIP table of 7 October 2026 plus real gameplay consumers. Disposable data only. */
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();date_default_timezone_set('UTC');
use Conquer\Db\Connection;
use Conquer\Game\Vip\VipService;
use Conquer\Game\Research\{BuffEngine,ResearchEffects};
use Conquer\Game\City\{CityState,TroopData,TroopTrainer,BuildingData};
use Conquer\Game\Player\ActionPoints;
use Conquer\Game\March\{GatherService,PvpRules};
use Conquer\Game\Rally\RallyCapacity;
use Conquer\Game\World\WorldContext;
require __DIR__.'/Support/FeatureDatabase.php';$fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();$checks=0;$failed=false;
function vb(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;}
try{
    $thresholds=[0,200,500,1000,5000,10000,20000,50000,100000,150000,200000,250000,500000,1000000,1500000,2000000,3000000,4000000,8000000,12000000];
    $rows=[
        'rally_troop_capacity'=>[0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,5,10,10,15,20],
        'troop_training_cost'=>[0,0,0,0,0,0,0,0,-5,-5,-5,-10,-10,-10,-15,-15,-20,-20,-25,-25],
        'troop_training_speed'=>[0,0,0,0,0,0,0,0,5,5,5,10,10,10,15,15,20,20,25,25],
        'troop_training_amount'=>[0,0,0,0,0,0,0,0,5,5,5,10,10,10,15,15,20,20,25,25],
        'action_points'=>[0,0,0,0,0,0,0,10,10,10,20,20,20,30,30,40,50,60,70,80],
        'mortality_reduction'=>[0,0,0,0,0,0,5,5,5,10,10,10,15,15,15,20,20,25,25,30],
        'troop_limit'=>[0,0,0,0,0,5,5,5,10,10,10,15,15,15,20,20,25,25,30,30],
        'marching_troop_capacity'=>[0,0,0,0,0,5000,5000,5000,10000,10000,10000,15000,15000,15000,20000,25000,30000,35000,40000,45000],
        'troop_dispatch_queue'=>[0,0,0,0,0,1,1,1,1,1,1,1,1,1,1,1,1,2,2,2],
        'additional_building_queue'=>[0,0,0,0,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1],
        'action_point_regeneration'=>[0,0,0,3,6,9,12,15,18,21,25,30,35,40,45,50,55,60,65,70],
        'research_speed'=>[0,0,3,6,9,12,15,18,21,25,30,35,40,45,50,55,60,70,80,100],
        'construction_speed'=>[0,0,3,6,9,12,15,18,21,25,30,35,40,45,50,55,60,70,80,100],
        'gathering_speed'=>[0,6,9,12,15,18,21,24,27,30,35,40,45,50,55,60,65,70,80,100],
        'resource_production'=>[3,3,9,12,15,18,21,24,27,30,35,40,45,50,55,60,65,70,80,100],
    ];
    vb(count(VipService::levels())===20,'exactly 20 selectable levels');
    foreach($thresholds as $i=>$points){$level=$i+1;$bonus=VipService::bonuses($level);vb(VipService::levelForPoints($points)===$level,'threshold '.$level);foreach($rows as$key=>$values)vb($bonus[$key]===$values[$i],$key.' at VIP '.$level);vb($bonus['hunter_points']===$i,'flat mastery allowance '.$level);}
    WorldContext::bind(1);$db->execute("INSERT INTO players(id,username,email,password_hash,action_points)VALUES(1,'VIPBenefits','vip-benefits@invalid.test','unused',100)");
    $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,castle_level,food,lumber,stone,gold)VALUES(1,1,1,'VIP Benefits',30,40,30,1000000,1000000,1000000,1000000)");
    foreach(CityState::BUILDING_CODES as$code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level)VALUES(1,?,30)',[$code]);
    $start=VipService::status(1);vb($start['level']===1&&$start['points']===0,'new world starts at zero');
    vb(VipService::status(1,2)['level']===0&&array_sum(VipService::status(1,2)['bonuses'])===0,'unjoined world receives no perks');
    ActionPoints::get(1);$db->execute('UPDATE players SET last_ap_regen=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR) WHERE id=1');
    VipService::setPoints(1,12000000);$ap=ActionPoints::get(1);vb($ap['current']===112,'level-up settles the elapsed hour at the old AP rate');
    vb($ap['max']===280&&abs($ap['regen_per_hour']-20.4)<1e-9,'VIP20 AP capacity and rate');
    $db->execute('UPDATE players SET last_ap_regen=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR) WHERE id=1');vb(ActionPoints::get(1)['current']===132,'next hour uses the VIP20 AP rate');
    $buffs=BuffEngine::getBuffs(1);$limits=ResearchEffects::limits($buffs);
    vb($limits['march_capacity']===110000&&$limits['march_slots']===5,'VIP20 march percentage, flat capacity and two dispatch queues');
    vb(RallyCapacity::forCity(1,1)['total']===3000000,'VIP20 increases Hall30 rally capacity by 20 percent');
    foreach(['food','lumber','stone','gold','gems']as$resource)vb(abs(GatherService::rate($resource,1,$buffs)/GatherService::rate($resource,1,[])-2)<1e-9,'VIP20 gathering '.$resource);
    foreach([50100101,50200101,50300101]as$code){$training=ResearchEffects::training($code,$buffs);vb($training['max_count']===6250&&$training['speed_multiplier']===1.25,'VIP20 training capacity and speed '.$code);foreach(TroopData::trainingCost($code,1)as$key=>$amount)vb(abs($training['cost'][$key]-$amount*.75)<1e-9,'VIP20 training cost '.$code.' '.$key);}
    $state=CityState::loadForPlayer(1);$training=ResearchEffects::training(50100101,$buffs);TroopTrainer::train($state['city'],$state['buildings'],50100101,10);
    $queue=$db->query('SELECT cost_json,TIMESTAMPDIFF(SECOND,started_at,finishes_at) AS seconds FROM troop_queue WHERE city_id=1 AND is_processed=0')->fetch();
    vb(json_decode($queue['cost_json'],true)===array_map(static fn($n)=>(int)ceil($n*10),$training['cost']),'real training debit matches reduced preview');
    vb((int)$queue['seconds']===(int)ceil(TroopData::trainingSeconds(50100101,10)/1.25),'real training duration matches VIP20 preview');
    $loss=PvpRules::losses([50100101=>1000],.3,$buffs);vb($loss['survivors'][50100101]===700&&$loss['wounded'][50100101]===180&&$loss['dead'][50100101]===120,'mortality reduction saves 30 percentage points of losses');
    vb(BuildingData::getBuildTime('farm',10,VipService::bonuses(20))===(int)ceil(BuildingData::getBuildTime('farm',10)/2),'construction speed is a speed bonus');
    // The migration is repeatable and never rewrites earned points.
    $sql=(string)file_get_contents(ROOT_DIR.'/migrations/0135_vip_benefits.sql');\Conquer\Db\MigrationSql::apply($db->getPdo(),$sql);\Conquer\Db\MigrationSql::apply($db->getPdo(),$sql);
    vb(VipService::status(1)['points']===12000000&&(int)$db->query('SELECT vip_level FROM player_world_vip WHERE player_id=1 AND world_id=1')->fetchColumn()===20,'migration preserves points and refreshes cached level');
    echo "PASS $checks VIP balance and gameplay checks.\n";
}catch(Throwable$e){$failed=true;fwrite(STDERR,'FAIL '.$e->getMessage()."\n".$e->getTraceAsString()."\n");}finally{$fixture->close();}exit($failed?1:0);
