<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';date_default_timezone_set('UTC');
use Conquer\Db\Connection;
use Conquer\Game\City\{BuildingData,BuildingUpgrader,CityState};
use Conquer\Game\Inventory\InventoryService;
use Conquer\Game\World\WorldContext;
use Conquer\Game\March\{GatherService,MarchTick};
use Conquer\Game\Map\MonsterData;
function checkBalance(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
function rejectBalance(callable $work,string $label):void{try{$work();}catch(RuntimeException|DomainException $e){if($e instanceof PDOException)throw $e;checkBalance(true,$label);return;}throw new LogicException('Unexpected acceptance: '.$label);}
function balanceStock():array{return Connection::getInstance()->query('SELECT food,lumber,stone,gold FROM cities WHERE id=1')->fetch();}
function balanceStart(string $code):array{$s=CityState::loadForPlayer(1);return BuildingUpgrader::start(1,$code,$s['city'],$s['buildings'],0,$s['vip']['bonuses']);}
function balanceOwned(int $code):int{return (int)Connection::getInstance()->query('SELECT quantity FROM player_inventory WHERE player_id=1 AND item_code=?',[$code])->fetchColumn();}
$fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();\Conquer\Logger::init(sys_get_temp_dir().'/conquer-balance-tests.log','ERROR');
try{
 WorldContext::bind(1);
 $db->execute("INSERT INTO players(id,username,email,password_hash)VALUES(1,'BalanceFixture','balance@tests.invalid','unused')");
 $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,castle_level,food,lumber,stone,gold)VALUES(1,1,1,'Balance',65,65,30,500000000,500000000,500000000,500000000)");
 foreach(CityState::BUILDING_CODES as $code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level)VALUES(1,?,30)',[$code]);
 $db->execute("UPDATE city_buildings SET level=29 WHERE city_id=1 AND building_code='academy'");
 $before=balanceStock();rejectBalance(fn()=>balanceStart('academy'),'missing golden pillar blocks L30');
 checkBalance(balanceStock()===$before,'rejected item cost preserves every resource');
 InventoryService::addItems(1,119000001,1);
 $db->execute("UPDATE city_buildings SET level=29 WHERE city_id=1 AND building_code='gold_mine'");
 rejectBalance(fn()=>balanceStart('academy'),'secondary building prerequisite enforced by real upgrade service');
 checkBalance(balanceOwned(119000001)===1,'failed prerequisite does not consume pillar');
 $db->execute("UPDATE city_buildings SET level=30 WHERE city_id=1 AND building_code='gold_mine'");
 $job=balanceStart('academy');$queue=$db->query("SELECT * FROM building_queue WHERE city_id=1 AND is_processed=0")->fetch();
 $paid=json_decode($queue['cost_json'],true,32,JSON_THROW_ON_ERROR);$cost=BuildingData::getCost('academy',30);
 checkBalance($paid['resources']===$cost&&$paid['items']==[119000001=>1]&&balanceOwned(119000001)===0,'source resource and item costs are charged and snapshotted');
 foreach($cost as $key=>$amount)checkBalance((int)$before[$key]-(int)balanceStock()[$key]===$amount,'exact debit '.$key);
 checkBalance(strtotime($job['finishes_at'])-strtotime($job['started_at'])===BuildingData::getBuildTime('academy',30),'queue uses source seconds');
 rejectBalance(fn()=>balanceStart('academy'),'repeated upgrade cannot charge twice');
 BuildingUpgrader::cancel(1,(int)$queue['id']);
 checkBalance(balanceStock()===$before&&balanceOwned(119000001)===1,'cancellation refunds resources and material exactly');
 rejectBalance(fn()=>BuildingUpgrader::cancel(1,(int)$queue['id']),'repeat cancellation cannot refund twice');
 $db->execute("UPDATE city_buildings SET level=29 WHERE city_id=1 AND building_code='hall_of_alliance'");
 InventoryService::addItems(1,119000002,4999);
 rejectBalance(fn()=>balanceStart('hall_of_alliance'),'4999 alliance badges do not satisfy 5000');
 checkBalance(balanceOwned(119000001)===1&&balanceOwned(119000002)===4999&&balanceStock()===$before,'partial material debit rolls back on later missing item');
 InventoryService::addItems(1,119000002,1);balanceStart('hall_of_alliance');
 $id=(int)$db->query('SELECT id FROM building_queue WHERE is_processed=0')->fetchColumn();BuildingUpgrader::cancel(1,$id);
 checkBalance(balanceOwned(119000002)===5000&&balanceOwned(119000001)===1,'all source materials refunded');
 $db->execute("INSERT INTO building_queue(city_id,building_code,level_to,started_at,finishes_at)VALUES(1,'farm',8,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))");$id=(int)$db->lastInsertId();
 $refund=BuildingUpgrader::cancel(1,$id);checkBalance($refund['refunded_lumber']===BuildingData::legacyCost('farm',8)['lumber']&&$refund['refunded_items']===[],'legacy job refunds old cost without inventing a pillar');
 $db->execute("UPDATE city_buildings SET level=7 WHERE building_code='watch_tower'");balanceStart('watch_tower');
 $db->execute('UPDATE building_queue SET finishes_at=UTC_TIMESTAMP() WHERE is_processed=0');CityState::loadForPlayer(1);
 checkBalance((int)$db->query("SELECT level FROM city_buildings WHERE building_code='watch_tower'")->fetchColumn()===8,'watchtower upgrades and settles through the existing building queue');
 rejectBalance(fn()=>BuildingUpgrader::cancel(1,(int)$db->query('SELECT MAX(id) FROM building_queue')->fetchColumn()),'finished job cannot be refunded');

 // Exhaust a L10 field: slot 3 has probability 1; partial recalls must never roll it.
 $db->execute("INSERT INTO city_troops(city_id,troop_code,count)VALUES(1,50100101,10)");
 $db->execute("INSERT INTO field_objects(world_id,coord_x,coord_y,object_type,level,resource_amount,resource_max,expires_at)VALUES(1,90,90,1,10,100,100,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))");$node=(int)$db->lastInsertId();
 $db->execute("INSERT INTO marches(player_id,world_id,march_type,origin_city_id,target_x,target_y,target_type,target_id,troops_json,haul_json,departure_time,arrival_time,state,gathering_finishes_at)VALUES(1,1,9,1,90,90,5,?,'{\"50100101\":1}',?,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 SECOND),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 20 SECOND),'arrived',DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 SECOND))",[$node,json_encode(['gather'=>['rate'=>10,'capacity'=>100]])]);$march=(int)$db->lastInsertId();
 $db->execute('UPDATE field_objects SET gatherer_march_id=? WHERE id=?',[$march,$node]);
 GatherService::finish($march);GatherService::finish($march);
 $haul=json_decode($db->query('SELECT haul_json FROM marches WHERE id=?',[$march])->fetchColumn(),true);
 checkBalance($haul['items'][120603026]===1&&$haul['loot']['food']===100,'depleted field carries its guaranteed source drop and resources home');
 $db->execute('UPDATE marches SET return_time=UTC_TIMESTAMP() WHERE id=?',[$march]);MarchTick::runForPlayer(1);MarchTick::runForPlayer(1);
 checkBalance(balanceOwned(120603026)===1,'gathering drop credited exactly once at return');
 $db->execute("INSERT INTO marches(player_id,world_id,march_type,origin_city_id,target_x,target_y,target_type,target_id,troops_json,haul_json,departure_time,arrival_time,state,gathering_finishes_at)VALUES(1,1,9,1,90,90,5,?,'{\"50100101\":1}',?,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 12 SECOND),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 SECOND),'arrived',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 100 SECOND))",[$node,json_encode(['gather'=>['rate'=>10,'capacity'=>100]])]);$march=(int)$db->lastInsertId();
 $db->execute('UPDATE field_objects SET resource_amount=100,gatherer_march_id=? WHERE id=?',[$march,$node]);
 GatherService::finish($march,true);$haul=json_decode($db->query('SELECT haul_json FROM marches WHERE id=?',[$march])->fetchColumn(),true);
 checkBalance($haul['items']===[]&&$haul['loot']['food']<100,'partial recall cannot farm source drops');
 checkBalance(abs(GatherService::rate('food',1,[])-40000/3600)<.000001&&abs(GatherService::rate('food',1,['gathering_speed'=>.5],2)-40000/1200)<.000001,'source hourly gathering rate combines with world and player bonuses');

 $db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id)VALUES(1,1,'Balance allies','BAL',1)");
 $db->execute("INSERT INTO alliance_members(alliance_id,player_id,world_id,role)VALUES(1,1,1,'leader')");
 $source=MonsterData::source(20200202,1);
 $definition=MonsterData::get(20200101);$definition['alliance_gift']=$source['alliance_gift'];
 $db->execute("INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current,monster_type)VALUES(1,20200101,95,95,1,'solo')");$monsterId=(int)$db->lastInsertId();
 $monster=$db->query('SELECT * FROM field_monsters WHERE id=?',[$monsterId])->fetch();
 for($i=0;$i<2;$i++)$db->transaction(fn()=>\Conquer\Game\Charm\MonsterCharmLifecycle::settle(1,$monster,$definition,'solo',999,1,1,[]));
 checkBalance((int)$db->query('SELECT COUNT(*) FROM alliance_gifts')->fetchColumn()===1,'source alliance gift created once with the durable kill receipt');
 $gift=json_decode($db->query('SELECT gift_json FROM alliance_gifts')->fetchColumn(),true);
 checkBalance($gift['item_code']===$source['alliance_gift']['item_code']&&$gift['quantity']===$source['alliance_gift']['count'],'alliance gift retains source item and count');
 InventoryService::addItems(1,10201001,0);$packsBefore=balanceOwned(10201001);
 $db->execute('UPDATE city_troops SET count=count+1000 WHERE city_id=1 AND troop_code=50100101');
 $db->execute("INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current,monster_type)VALUES(1,20200101,97,65,1,'solo')");
 $march=\Conquer\Game\March\MarchDispatcher::dispatchMonster(1,1,65,65,97,65,[50100101=>100]);
 $db->execute('UPDATE marches SET departure_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 61 SECOND),arrival_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$march]);
 MarchTick::runForPlayer(1);
 $haul=json_decode($db->query('SELECT haul_json FROM marches WHERE id=?',[$march])->fetchColumn(),true);
 checkBalance($haul['items'][10201001]===5&&array_sum($haul['loot'])===0,'actual Orc fight uses source 5 x 1000 food packs without extra flat loot');
 checkBalance(balanceOwned(10201001)===$packsBefore,'source monster packs remain on the returning army');
 $db->execute('UPDATE marches SET return_time=UTC_TIMESTAMP() WHERE id=?',[$march]);MarchTick::runForPlayer(1);MarchTick::runForPlayer(1);
 checkBalance(balanceOwned(10201001)===$packsBefore+5,'source monster packs paid exactly once at homecoming');
 // Existing profiles and map views read the cached city power directly.
 $db->execute('UPDATE cities SET power=1 WHERE id=1');
 $stateBefore=[];
 foreach(['city_buildings','building_queue','player_inventory'] as $table)$stateBefore[$table]=$db->query('SELECT * FROM '.$table)->fetchAll();
 $stockBefore=balanceStock();$savedArgs=$argv;$argv=['migrate-balance.php','--apply'];
 try { require ROOT_DIR.'/tools/migrate-balance.php'; require ROOT_DIR.'/tools/migrate-balance.php'; }
 finally { $argv=$savedArgs; }
 $power=(int)$db->query('SELECT power FROM cities WHERE id=1')->fetchColumn();
 checkBalance($power===CityState::loadForPlayer(1)['city']['power']&&$power>1,'repeatable migration refreshes existing profile and map power');
 foreach($stateBefore as $table=>$rows)checkBalance($db->query('SELECT * FROM '.$table)->fetchAll()===$rows,'balance migration preserves '.$table);
 checkBalance(balanceStock()===$stockBefore,'balance migration preserves resources');
 $db->execute("INSERT INTO players(id,username,email,password_hash)VALUES(2,'NewBalanceCity','new-balance@tests.invalid','unused')");
 \Conquer\Auth\OAuth::createDefaultCity($db,2,'NewBalanceCity',1);
 $newCity=$db->query('SELECT id,power FROM cities WHERE player_id=2 AND world_id=1')->fetch();
 $expectedStartPower=BuildingData::calculateCityPower(array_fill_keys(CityState::BUILDING_CODES,['level'=>1]));
 checkBalance((int)$newCity['power']===$expectedStartPower&&(int)$db->query("SELECT level FROM city_buildings WHERE city_id=? AND building_code='watch_tower'",[$newCity['id']])->fetchColumn()===1,'new cities start with the watchtower and correct source building power');
 echo "ALL BALANCE LIFECYCLE CHECKS PASSED\n";
}finally{$fixture->close();}
