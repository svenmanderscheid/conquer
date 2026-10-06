<?php
declare(strict_types=1);
/** Exact relic rewards, inventory replay and combat returns in a disposable schema. */
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));define('APP_BASE','/conquer');
require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';date_default_timezone_set('UTC');
\Conquer\Logger::init(sys_get_temp_dir().'/conquer-relic-drop-tests.log','ERROR');
set_error_handler(static function(int $severity,string $message,string $file,int $line):never{throw new ErrorException($message,0,$severity,$file,$line);});
use Conquer\Db\Connection;
use Conquer\Admin\RewardEditor;
use Conquer\Game\Rewards\RewardCatalog as R;
use Conquer\Game\Treasure\TreasureService as T;
use Conquer\Game\Treasure\ChestService;
use Conquer\Game\Kingdom\KingdomService;
use Conquer\Game\Inventory\InventoryService;
use Conquer\Game\March\MarchTick;
$checks=0;$fixture=null;$exit=0;
function ckR(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo "PASS $label\n";}
function rejectR(callable $work,string $label):void{try{$work();}catch(InvalidArgumentException|DomainException){ckR(true,$label);return;}throw new RuntimeException('Accepted: '.$label);}
function fragmentsR(int $code):int{return (int)Connection::getInstance()->query('SELECT fragments FROM player_treasures WHERE player_id=1 AND treasure_code=?',[$code])->fetchColumn();}
function saveR(string $type,string $key,array $form):void{
    $config=R::validate($type,$key,$form);
    Connection::getInstance()->execute('INSERT INTO reward_overrides(source_type,source_key,config_json) VALUES(?,?,?) ON DUPLICATE KEY UPDATE config_json=VALUES(config_json)',[$type,$key,json_encode($config,JSON_THROW_ON_ERROR)]);
    R::resetCache();
}
try{
    $fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();
    \Conquer\Game\World\WorldContext::bind(1,1);
    $db->execute("INSERT INTO players(id,username,email,password_hash) VALUES(1,'RelicFixture','relic@tests.invalid','unused')");
    $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y) VALUES(1,1,1,'Relic city',30,40)");
    foreach(\Conquer\Game\City\CityState::BUILDING_CODES as $code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(1,?,1)',[$code]);
    $db->execute("INSERT INTO kingdom_profiles(player_id,display_name,welcome_claimed) VALUES(1,'RelicFixture',1)");
    $form=['rows'=>[],'fragment_rows'=>[['target'=>'treasure:60100001','quantity'=>3,'chance'=>100]],'relic_rows'=>[['target'=>'relic:60100001','quantity'=>2,'chance'=>100],['target'=>'relic:60100002','quantity'=>4,'chance'=>0]]];
    $farm=R::validate('farm','20100101.1',$form);
    ckR($farm['fragment_drops'][0]['treasure_code']===60100001&&R::rollRelics($farm['relic_drops'])===[60100001=>2],'Same relic can have independent whole and fragment drops with exact 0/100% chances');
    ckR(RewardEditor::formConfig('farm',$farm)['relic_rows'][0]['target']==='relic:60100001','Whole relic selections round-trip through the admin form');
    foreach(['relic:99999999','relic:60500101','treasure:60100001','fragment:normal','relic:060100001'] as $target){$bad=$form;$bad['relic_rows'][0]['target']=$target;rejectR(fn()=>R::validate('farm','20100101.1',$bad),'Reject invalid whole relic target '.$target);}
    foreach([['quantity',0],['quantity',100001],['chance',101],['chance',['100']]] as [$field,$value]){$bad=$form;$bad['relic_rows'][0][$field]=$value;rejectR(fn()=>R::validate('farm','20100101.1',$bad),'Reject malformed relic '.$field);}
    $bad=$form;$bad['relic_rows'][]=$bad['relic_rows'][0];rejectR(fn()=>R::validate('farm','20100101.1',$bad),'Duplicate whole relic rows rejected');
    $bad=$form;$bad['relic_rows']=array_fill(0,101,$bad['relic_rows'][0]);rejectR(fn()=>R::validate('farm','20100101.1',$bad),'Whole relic row limit enforced');
    $bad=$form;$bad['relic_rows'][0]['chance']=12.3456;ckR(R::validate('farm','20100101.1',$bad)['relic_drops'][0]['probability']===.123456,'Fractional whole relic probability preserved');
    T::addFragments(1,60100001,7);$grant=T::addRelics(1,60100001);
    ckR($grant['newly_unlocked']&&fragmentsR(60100001)===7,'First whole relic unlocks without spending earlier fragments');
    $grant=T::addRelics(1,60100001,2);ckR(!$grant['newly_unlocked']&&$grant['duplicate_relics']===2&&fragmentsR(60100001)===27,'Owned relic copies each become the authoritative unlock cost in fragments');
    T::addFragments(1,60100002,3);$grant=T::addRelics(1,60100002,3);
    ckR($grant['newly_unlocked']&&$grant['duplicate_relics']===2&&fragmentsR(60100002)===23,'Multiple first-time whole copies unlock once and convert only additional copies');
    rejectR(fn()=>T::addRelics(1,60500101),'Retired whole relic cannot be granted');
    rejectR(fn()=>T::addRelics(1,60100001,0),'Zero whole relic grant rejected');
    $db->execute('UPDATE player_treasures SET fragments=4294967295 WHERE player_id=1 AND treasure_code=60100002');
    rejectR(fn()=>T::addRelics(1,60100002),'Duplicate whole relic cannot overflow fragment storage');
    ckR(fragmentsR(60100002)===4294967295,'Overflow rejection leaves collection unchanged');
    $chest=['rolls'=>2,'rows'=>[['target'=>'treasure:60100003','quantity'=>3,'weight'=>1],['target'=>'relic:60100001','quantity'=>1,'weight'=>0]]];saveR('chest','silver',$chest);
    $rewards=$db->transaction(fn()=>ChestService::grantRewards(1,'silver'));
    ckR(count($rewards)===2&&$rewards[0]['type']==='fragment'&&fragmentsR(60100003)===6,'Single chest grants the configured specific fragment and never selects zero weight');
    $chest=['rolls'=>1,'rows'=>[['target'=>'relic:60100101','quantity'=>1,'weight'=>1],['target'=>'treasure:60100003','quantity'=>9,'weight'=>0]]];saveR('chest','silver',$chest);
    $rewards=ChestService::openFreeChest(1,'silver');ckR($rewards[0]['type']==='relic'&&$rewards[0]['treasure_code']===60100101&&fragmentsR(60100101)===0,'Free chest grants a full relic with correct display metadata');
    $item=array_values(array_filter(InventoryService::allDefs(),static fn(array $d):bool=>($d['category']??'')==='chest'&&($d['chest_type']??'')==='silver'))[0];
    InventoryService::addItems(1,(int)$item['code'],3);
    $body=['action'=>'inventory.use','item_code'=>(int)$item['code'],'use_all'=>true,'operation_key'=>'relic-drop-bulk-once','expected_world_id'=>1];
    $first=KingdomService::action(1,$body)['result'];$second=KingdomService::action(1,$body)['result'];
    ckR($first['drops'][0]['type']==='relic'&&$first['drops'][0]['quantity']===3&&fragmentsR(60100101)===30,'Bulk inventory chests combine whole copies and convert duplicates correctly');
    ckR($first===$second&&InventoryService::quantity(1,(int)$item['code'])===0,'Replayed bulk chest request credits no second reward');
    foreach(['relic:60500101','treasure:60500101','relic:99999999'] as $target){$bad=$chest;$bad['rows'][0]['target']=$target;rejectR(fn()=>R::validate('chest','silver',$bad),'Reject retired/unknown chest target '.$target);}
    $monster=RewardEditor::formConfig('monster',R::defaults('monster','20209901'));
    $monster['rows']=[];$monster['fragment_rows']=[['target'=>'treasure:60100105','quantity'=>3,'chance'=>100]];$monster['relic_rows']=[['target'=>'relic:60100105','quantity'=>1,'chance'=>100],['target'=>'relic:60100107','quantity'=>9,'chance'=>0]];saveR('monster','20209901',$monster);
    $db->execute("INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current,monster_type,expires_at) VALUES(1,20209901,70,70,1,'solo',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))");$monsterId=$db->lastInsertId();
    $db->execute("INSERT INTO marches(player_id,world_id,march_type,origin_city_id,target_x,target_y,target_type,target_id,departure_time,arrival_time,state,troops_json) VALUES(1,1,5,1,70,70,3,?,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 120 SECOND),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND),'marching',?)",[$monsterId,json_encode(['50100101'=>1000])]);$marchId=$db->lastInsertId();
    MarchTick::runForPlayer(1);$haul=json_decode($db->query('SELECT haul_json FROM marches WHERE id=?',[$marchId])->fetchColumn(),true);
    ckR($haul['relics']===[60100105=>1]&&$haul['fragments']===[60100105=>3],'Solo victory freezes separate whole relic and fragment maps');
    ckR((int)$db->query('SELECT COUNT(*) FROM player_treasures WHERE player_id=1 AND treasure_code=60100105')->fetchColumn()===0,'Solo relic waits for homecoming');
    $report=\Conquer\Game\Rewards\RewardPresentation::report(json_decode($db->query('SELECT data_json FROM battle_reports WHERE march_id=?',[$marchId])->fetchColumn(),true));
    ckR($report['relic_rewards'][0]['type']==='relic'&&$report['relic_rewards'][0]['count']===1&&$report['fragment_rewards'][0]['count']===3,'Solo report separates whole relics and fragments');
    $monster['relic_rows']=[];$monster['fragment_rows']=[];saveR('monster','20209901',$monster);
    $db->execute('UPDATE marches SET return_time=UTC_TIMESTAMP() WHERE id=?',[$marchId]);MarchTick::runForPlayer(1);MarchTick::runForPlayer(1);
    ckR(fragmentsR(60100105)===3&&(int)$db->query('SELECT COUNT(*) FROM player_treasure_effects WHERE player_id=1 AND treasure_code=60100105 AND effect_index=0')->fetchColumn()===1,'Solo return grants the frozen relic exactly once after rule changes');
    echo "ALL $checks RELIC DROP CHECKS PASSED\n";
}catch(Throwable $error){$exit=1;fwrite(STDERR,$error->getMessage()."\n".$error->getTraceAsString()."\n");}
finally{if($fixture)$fixture->close();}
exit($exit);
