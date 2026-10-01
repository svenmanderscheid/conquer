<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));define('APP_BASE','');date_default_timezone_set('UTC');
require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';
use Conquer\Db\Connection;
use Conquer\Admin\AdminService;
use Conquer\Auth\OAuth;
use Conquer\Game\World\{WorldContext,WorldMapProfile,LuxembourgGeography};
use Conquer\Game\Map\WorldPlacement;
use Conquer\Game\Kingdom\KingdomService;
use Conquer\Game\Inventory\InventoryService;
function luxAppCheck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
function luxAppReject(callable $call,string $label):void{try{$call();}catch(DomainException|InvalidArgumentException $e){luxAppCheck(true,$label);return;}throw new RuntimeException('Accepted: '.$label);}
$fixture=new \ConquerTests\FeatureDatabase();
try{
 $db=Connection::getInstance();$legacy=$db->query('SELECT * FROM worlds WHERE id=1')->fetch();
 $db->execute("INSERT INTO admin_users(id,username,password_hash,role)VALUES(1,'TerritoryAdmin','unused','superadmin')");
 $op=['operation_id'=>bin2hex(random_bytes(16)),'reason'=>'Isolated Luxembourg acceptance','name'=>'New Luxembourg','slug'=>'lux-acceptance','status'=>'running','map_profile'=>'luxembourg'];
 $created=AdminService::execute(1,'world-create',$op);$world=(int)$created['world_id'];$profile=WorldMapProfile::forWorld($world);
 luxAppCheck($profile['width']===768&&$profile['height']===1100,'admin creation uses rectangular Luxembourg profile');
 luxAppCheck((int)$db->query('SELECT COUNT(*) FROM territory_targets WHERE world_id=?',[$world])->fetchColumn()===113,'admin creation imports all 113 live territory objectives');
 luxAppCheck((int)$db->query('SELECT COUNT(*) FROM shrines WHERE world_id=?',[$world])->fetchColumn()===0,'new world does not create old shrine targets');
 $again=AdminService::execute(1,'world-create',$op);luxAppCheck($again['duplicate']&&$again['world_id']===$created['world_id'],'admin retry returns the same created world');
 luxAppCheck($db->query('SELECT * FROM worlds WHERE id=1')->fetch()===$legacy,'creation preserves existing world row');
 $cantons=$db->query("SELECT canton_id FROM territory_targets WHERE world_id=? AND kind='canton' ORDER BY canton_id LIMIT 2",[$world])->fetchAll(PDO::FETCH_COLUMN);
 $rulesBody=['operation_id'=>bin2hex(random_bytes(16)),'reason'=>'Isolated alpha rules','world_id'=>$world,'version'=>1,'territory_scope'=>'alpha','rules'=>['active_cantons'=>$cantons,'income_per_hour'=>'900','canton_limit'=>'2']];
 $rulesSaved=AdminService::execute(1,'world-territory-rules',$rulesBody);
 luxAppCheck($rulesSaved['after']['version']===2&&$rulesSaved['after']['canton_limit']===1&&$rulesSaved['after']['active_cantons']===$cantons,'admin alpha activates whole cantons and one reserved ownership slot');
 luxAppReject(fn()=>AdminService::execute(1,'world-territory-rules',array_replace($rulesBody,['operation_id'=>bin2hex(random_bytes(16))])),'stale admin rule version rejected');
 $rulesFull=array_replace($rulesBody,['operation_id'=>bin2hex(random_bytes(16)),'version'=>2,'territory_scope'=>'full','rules'=>['canton_limit'=>'2']]);
 $rulesSaved=AdminService::execute(1,'world-territory-rules',$rulesFull);luxAppCheck($rulesSaved['after']['active_cantons']===[]&&$rulesSaved['after']['canton_limit']===2,'full-world rules restore all cantons and limit two');
 foreach([1,2]as$pid){$db->transaction(static function($db)use($pid,$world){$db->execute('INSERT INTO players(id,username,email,password_hash)VALUES(?,?,?,?)',[$pid,'LuxApp'.$pid,'luxapp'.$pid.'@tests.invalid','unused']);OAuth::createDefaultCity($db,$pid,'LuxApp'.$pid,$world);});}
 foreach($db->query('SELECT * FROM cities WHERE world_id=?',[$world])->fetchAll()as$city){luxAppCheck(LuxembourgGeography::isDryRectangle($city['coord_x']-1,$city['coord_y']-1,$city['coord_x']+2,$city['coord_y']+2),'OAuth city has a full dry Luxembourg footprint');}
 WorldContext::bind($world,1);$city=WorldContext::city(1);$cityId=(int)$city['id'];
 $target=$db->query("SELECT * FROM territory_targets WHERE world_id=? AND kind='commune' ORDER BY y DESC LIMIT 1",[$world])->fetch();
 $spot=$db->transaction(static function($db)use($world,$target,$cityId){WorldPlacement::lockWorld($db,$world);return WorldPlacement::findNear($db,$world,'city',(int)$target['x']+10,(int)$target['y'],$cityId,60);});
 luxAppCheck($spot!==null&&$spot[1]>767,'southern teleport destination exists beyond old square bound');
 $item=null;foreach(InventoryService::allDefs()as$def)if(($def['teleport_mode']??'')==='advanced'){$item=(int)$def['code'];break;}
 luxAppCheck($item!==null,'advanced teleport item exists');InventoryService::addItems(1,$item,3);
 $teleport=['action'=>'inventory.use','item_code'=>$item,'target_x'=>$spot[0],'target_y'=>$spot[1],'operation_key'=>bin2hex(random_bytes(16))];
 $result=KingdomService::action(1,$teleport);$after=WorldContext::city(1);
 luxAppCheck((int)$after['coord_x']===$spot[0]&&(int)$after['coord_y']===$spot[1],'actual inventory action teleports to the southern region');
 $stock=static fn()=>(int)($db->query('SELECT quantity FROM player_inventory WHERE player_id=1 AND item_code=?',[$item])->fetchColumn()?:0);
 $left=$stock();KingdomService::action(1,$teleport);luxAppCheck($stock()===$left,'teleport retry spends the item once');
 luxAppReject(fn()=>KingdomService::action(1,array_replace($teleport,['target_x'=>0,'target_y'=>1099,'operation_key'=>bin2hex(random_bytes(16))])),'invalid footprint rejected');luxAppCheck($stock()===$left,'failed destination preserves item');
 $db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id)VALUES(1,?,'LuxApp Alliance','LUX',1)",[$world]);
 $db->execute("INSERT INTO alliance_members(alliance_id,world_id,player_id,role)VALUES(1,?,1,'leader')",[$world]);
 $db->execute('UPDATE territory_targets SET owner_alliance_id=1 WHERE world_id=? AND id=?',[$world,$target['id']]);
 $power=KingdomService::state(1)['profile']['power'];
 $db->execute("INSERT INTO territory_garrisons(world_id,continent_id,target_id,alliance_id,player_id,city_id,troops_json,status,departure_at,arrival_at,travel_seconds)VALUES(?,'luxembourg',?,1,1,?,'{\"50100101\":10}','active',UTC_TIMESTAMP(),UTC_TIMESTAMP(),30)",[$world,$target['id'],$cityId]);
 luxAppCheck(KingdomService::state(1)['profile']['power']===$power+10*(int)\Conquer\Game\City\TroopData::get(50100101)['power'],'deployed territory army keeps its power in player standings');
 luxAppReject(fn()=>KingdomService::action(1,array_replace($teleport,['operation_key'=>bin2hex(random_bytes(16))])),'territory garrison blocks ordinary teleport');luxAppCheck($stock()===$left,'blocked garrison teleport preserves item');
 $db->execute("INSERT INTO alliance_members(alliance_id,world_id,player_id,role)VALUES(1,?,2,'member')",[$world]);
 KingdomService::action(1,['action'=>'alliance.transfer','player_id'=>2]);
 KingdomService::action(1,['action'=>'alliance.leave']);
 luxAppCheck($db->query('SELECT status FROM territory_garrisons WHERE player_id=1 AND world_id=?',[$world])->fetchColumn()==='returning','actual alliance-leave action returns deployed garrison');
 luxAppCheck(!$db->query('SELECT alliance_id FROM alliance_members WHERE player_id=1 AND world_id=?',[$world])->fetchColumn(),'membership departure commits with garrison recall');
 $db->execute("INSERT INTO alliance_members(alliance_id,world_id,player_id,role)VALUES(1,?,1,'member')",[$world]);
 $db->execute("UPDATE territory_garrisons SET status='active',return_at=NULL WHERE player_id=1 AND world_id=?",[$world]);
 WorldContext::bind($world,2);KingdomService::action(2,['action'=>'alliance.kick','player_id'=>1]);
 luxAppCheck($db->query('SELECT status FROM territory_garrisons WHERE player_id=1 AND world_id=?',[$world])->fetchColumn()==='returning','actual officer-kick action returns departed member garrison');
 echo "ALL LUXEMBOURG APP INTEGRATION CHECKS PASSED\n";
}finally{$fixture->close();}
