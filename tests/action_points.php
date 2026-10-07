<?php
declare(strict_types=1);
/** AP overflow, passive regeneration and item receipts in a disposable database. */
if(PHP_SAPI!=='cli')exit(1);
date_default_timezone_set('UTC');
define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';
\Conquer\Logger::init(sys_get_temp_dir().'/conquer-ap-test.log','ERROR');
use Conquer\Db\Connection;
use Conquer\Game\Player\ActionPoints;
use Conquer\Game\Kingdom\KingdomService;
use Conquer\Game\Inventory\InventoryService;
use Conquer\Game\World\WorldContext;
$fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();$checks=0;
function checkAp(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;}
function seedAp(int $balance,int $seconds=0,float $fraction=0,float $rate=1):void{
    $db=Connection::getInstance();
    $db->execute('UPDATE players SET action_points=?,last_ap_regen=DATE_SUB(UTC_TIMESTAMP(),INTERVAL ? SECOND) WHERE id=1',[$balance,$seconds]);
    $db->execute('INSERT INTO player_ap_regeneration(player_id,rate,fraction) VALUES(1,?,?) ON DUPLICATE KEY UPDATE rate=VALUES(rate),fraction=VALUES(fraction)',[$rate,$fraction]);
}
function fractionAp():float{return (float)Connection::getInstance()->query('SELECT fraction FROM player_ap_regeneration WHERE player_id=1')->fetchColumn();}
try{
    WorldContext::bind(1);
    $db->execute("INSERT INTO players(id,username,email,password_hash,action_points,last_ap_regen) VALUES(1,'ApFixture','ap@tests.invalid','unused',200,UTC_TIMESTAMP())");
    $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y) VALUES(1,1,1,'AP city',30,40)");
    foreach(\Conquer\Game\City\CityState::BUILDING_CODES as $code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(1,?,?)',[$code,$code==='castle'?1:0]);
    $db->execute("INSERT INTO kingdom_profiles(player_id,display_name,welcome_claimed) VALUES(1,'ApFixture',1)");
    $db->execute("INSERT INTO player_lord_talents(player_id,world_id,talent_code,rank) VALUES(1,1,'monster_9',1)");
    checkAp(ActionPoints::get(1)['max']===210,'talent maximum is 210');

    seedAp(300,172800,.75,1.5);
    checkAp(ActionPoints::get(1)['current']===300 && fractionAp()===0.0,'overflow survives elapsed time and discards banked regeneration');
    checkAp(ActionPoints::get(1)['current']===300,'repeated reads preserve 300/210');
    seedAp(210,3600,.75);
    checkAp(ActionPoints::get(1)['current']===210 && fractionAp()===0.0,'exact maximum does not regenerate');
    seedAp(209,3600);
    checkAp(ActionPoints::get(1)['current']===210 && fractionAp()===0.0,'passive regeneration stops exactly at the maximum');
    seedAp(100,600,.5,1.5);
    checkAp(ActionPoints::get(1)['current']===103 && abs(fractionAp()-.5)<.02,'below maximum retains fractional regeneration and the elapsed talent rate');

    seedAp(300,86400,.9);
    ActionPoints::deduct(1,75);
    checkAp(ActionPoints::get(1)['current']===225,'spending above maximum preserves the remaining overflow');
    $db->execute('UPDATE players SET last_ap_regen=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY) WHERE id=1');
    ActionPoints::deduct(1,25);
    checkAp(ActionPoints::get(1)['current']===200 && fractionAp()<.02,'crossing below maximum starts without banked time');
    $db->execute('UPDATE players SET last_ap_regen=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 150 SECOND) WHERE id=1');
    checkAp(ActionPoints::get(1)['current']===200 && abs(fractionAp()-.5)<.02,'first partial regeneration interval after spending');
    $db->execute('UPDATE players SET last_ap_regen=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 150 SECOND) WHERE id=1');
    checkAp(ActionPoints::get(1)['current']===201,'regeneration resumes below maximum');

    seedAp(209,0,.75);
    checkAp(ActionPoints::credit(1,50)['current']===259 && fractionAp()===0.0,'refill grants its full value and clears a fraction on crossing the maximum');
    ActionPoints::refund(1,25);
    checkAp(ActionPoints::get(1)['current']===284,'refund returns all paid AP above maximum');
    ActionPoints::refund(1,0);
    checkAp(ActionPoints::get(1)['current']===284,'zero refund is harmless');
    seedAp(205);
    ActionPoints::refund(1,25);
    checkAp(ActionPoints::get(1)['current']===230,'refund crossing maximum retains the full refund');
    $db->getPdo()->beginTransaction();ActionPoints::credit(1,100);$db->getPdo()->rollBack();
    checkAp(ActionPoints::get(1)['current']===230,'credit participates in caller transaction rollback');

    seedAp(200);
    InventoryService::addItems(1,10204001,3);
    $request=['action'=>'inventory.use','item_code'=>10204001,'quantity'=>1,'expected_world_id'=>1,'operation_key'=>'ap_single_once_123456'];
    $response=KingdomService::action(1,$request);$result=$response['result'];
    checkAp($result['amount']===100 && $response['state']['profile']['action_points']===300,'single refill returns 300/210 after the actual state refresh');
    checkAp(KingdomService::action(1,$request)['result']===$result && InventoryService::quantity(1,10204001)===2,'single refill replay never consumes twice');
    $request['quantity']=2;$request['operation_key']='ap_selected_once_123456';
    $result=KingdomService::action(1,$request)['result'];
    checkAp($result['quantity']===2 && $result['amount']===200 && ActionPoints::get(1)['current']===500,'selected quantity works when already overfilled');
    checkAp(KingdomService::action(1,$request)['result']===$result && InventoryService::quantity(1,10204001)===0,'selected quantity replay credits exactly once');
    InventoryService::addItems(1,10104002,10000);
    $result=KingdomService::action(1,['action'=>'inventory.use','item_code'=>10104002,'quantity'=>10000,'expected_world_id'=>1,'operation_key'=>'ap_large_stack_123456'])['result'];
    checkAp($result['amount']===2000000 && ActionPoints::get(1)['current']===2000500,'large refill stacks exceed the former SMALLINT storage without loss');
    $db->execute("DELETE FROM player_lord_talents WHERE player_id=1 AND world_id=1");
    checkAp(ActionPoints::get(1)['max']===200 && ActionPoints::get(1)['current']===2000500,'lower maximum retains existing overflow');
    foreach(InventoryService::allDefs()as $def)if($def['category']==='ap_refill'){
        seedAp(300);InventoryService::addItems(1,(int)$def['code'],1);
        $result=KingdomService::action(1,['action'=>'inventory.use','item_code'=>(int)$def['code'],'expected_world_id'=>1,'operation_key'=>'ap_catalog_'.$def['code'].'_once'])['result'];
        checkAp($result['amount']===$def['ap_amount'] && ActionPoints::get(1)['current']===300+$def['ap_amount'],'full catalog refill above maximum: '.$def['code']);
    }
    echo "Action points: $checks checks passed.\n";
}finally{$fixture->close();}
