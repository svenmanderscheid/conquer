<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';date_default_timezone_set('UTC');\Conquer\Logger::init(sys_get_temp_dir().'/conquer-monster-rallies-test.log','ERROR');
use Conquer\Db\Connection;
use Conquer\Game\World\WorldContext;
use Conquer\Game\Rally\{MonsterRally,RallyService};
use Conquer\Game\Research\{BuffEngine,ResearchEffects};
use Conquer\Game\March\{BattleEngine,MarchDispatcher};
use Conquer\Game\City\{CityState,BuildingData};
$testMonsterCode=(int)(getenv('CONQUER_TEST_MONSTER_CODE')?:20202101);
$checks=0;$fixture=null;$exit=0;
function ck(bool $condition,string $message):void{global $checks;if(!$condition)throw new RuntimeException($message);$checks++;echo "PASS $message\n";}
function rejects(callable $fn,string $message):void{try{$fn();}catch(DomainException|RuntimeException $e){if($e instanceof PDOException)throw $e;ck(true,$message);return;}throw new RuntimeException('Allowed: '.$message);}
function stock(int $city):int{return (int)Connection::getInstance()->query('SELECT count FROM city_troops WHERE city_id=? AND troop_code=50100101',[$city])->fetchColumn();}
function rally(int $id):array{return Connection::getInstance()->query('SELECT * FROM rallies WHERE id=?',[$id])->fetch();}
function monster(int $x=60,int $hp=1000,int $world=1):int{$db=Connection::getInstance();$db->execute("INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current,monster_type,expires_at) VALUES(?,?,?,60,?,'rally',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))",[$world,$GLOBALS['testMonsterCode'],$x,$hp]);return $db->lastInsertId();}
function arrive(int $id):void{Connection::getInstance()->execute('UPDATE rallies SET arrival_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND),return_time=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR) WHERE id=?',[$id]);RallyService::tick();}
function home(int $id):void{Connection::getInstance()->execute('UPDATE rallies SET return_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$id]);RallyService::tick();}
try{
 $fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();
 \Conquer\Db\MigrationSql::apply($db->getPdo(),file_get_contents(ROOT_DIR.'/migrations/0079_monster_rallies.sql'));
 \Conquer\Db\MigrationSql::apply($db->getPdo(),file_get_contents(ROOT_DIR.'/migrations/0079_monster_rallies.sql'));
 $db->execute("UPDATE worlds SET status='running' WHERE id=1");$db->execute("INSERT INTO worlds(id,name,slug,status,map_size) VALUES(2,'Other','other','running',256)");
 for($pid=1;$pid<=3;$pid++){
  $db->execute("INSERT INTO players(id,username,email,password_hash,action_points,last_ap_regen,beginner_shield_until) VALUES(?,?,?,'unused',200,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))",[$pid,'RallyFixture'.$pid,'rally'.$pid.'@invalid.test']);
  $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,food,lumber,stone,gold) VALUES(?,?,1,'Rally city',?,40,100000,100000,100000,100000)",[$pid,$pid,20+$pid*5]);
  foreach(CityState::BUILDING_CODES as $code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(?,?,1)',[$pid,$code]);
  $db->execute('INSERT INTO city_troops(city_id,troop_code,count) VALUES(?,50100101,25000)',[$pid]);
 }
 $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y) VALUES(4,1,2,'Other city',40,40)");
 foreach(CityState::BUILDING_CODES as $code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(4,?,1)',[$code]);
 $db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id) VALUES(1,1,'Rally group','RLY',1)");$db->execute("INSERT INTO alliance_members(alliance_id,player_id,world_id,role) VALUES(1,1,1,'leader'),(1,2,1,'member')");
 ck(MonsterRally::capacity(1,1)===20000,'Hall level 1 capacity');
 foreach([5=>50000,10=>100000,20=>250000,30=>500000] as $level=>$expected){$db->execute("UPDATE city_buildings SET level=? WHERE city_id=1 AND building_code='hall_of_alliance'",[$level]);ck(MonsterRally::capacity(1,1)===$expected,'Hall capacity '.$level);}
 $db->execute("UPDATE city_buildings SET level=1 WHERE city_id=1 AND building_code='hall_of_alliance'");
 $mid=monster(60,400);
 rejects(fn()=>MonsterRally::start(3,3,60,60,[50100101=>100],1,''),'membership required');
 rejects(fn()=>MonsterRally::start(1,2,60,60,[50100101=>100],1,''),'foreign origin rejected');
 rejects(fn()=>MonsterRally::start(1,1,60,60,[50100101=>20001],1,''),'hall total capacity enforced');
 rejects(fn()=>MonsterRally::start(1,1,60,60,[50100101=>'100'],1,''),'string troop count rejected');
 rejects(fn()=>MarchDispatcher::dispatchMonster(1,1,25,40,60,60,[50100101=>100]),'solo dispatch rejected before spending or reservation');
 $id=MonsterRally::start(1,1,60,60,[50100101=>5000],5,'Together');
 ck(stock(1)===20000&&(int)$db->query('SELECT action_points FROM players WHERE id=1')->fetchColumn()===175,'captain reserves troops and pays catalog AP');
 ck($db->query('SELECT beginner_shield_until FROM players WHERE id=1')->fetchColumn()!==null,'monster rally preserves beginner protection');
 rejects(fn()=>RallyService::join(3,3,$id,[50100101=>100]),'outsider cannot join');
 RallyService::join(2,2,$id,[50100101=>5000]);
 ck(stock(2)===20000&&(int)$db->query('SELECT action_points FROM players WHERE id=2')->fetchColumn()===200,'joiner reserves troops without AP');
 rejects(fn()=>RallyService::join(2,2,$id,[50100101=>50]),'repeat join cannot reserve again');
 rejects(fn()=>RallyService::cancel($id,2),'only captain can cancel');
 ck(count(RallyService::listForAlliance(1))===1&&str_contains(RallyService::listForAlliance(1)[0]['target_name'],\Conquer\Game\Map\MonsterData::get($testMonsterCode)['name']),'monster rallies appear in alliance list without a player target');
 WorldContext::bind(2);rejects(fn()=>RallyService::launch($id,1),'cross-world launch rejected');WorldContext::bind(1);
 RallyService::launch($id,1);$before=(int)$db->query('SELECT gold FROM cities WHERE id=1')->fetchColumn();arrive($id);
 $result=json_decode(rally($id)['result_json'],true);ck($result['monster_killed']&&rally($id)['status']==='returning','combined army kills monster and begins return');
 ck((int)$db->query('SELECT COUNT(*) FROM field_monsters WHERE id=?',[$mid])->fetchColumn()===0,'monster removed exactly once');
 $rallyCharm=$db->query("SELECT c.* FROM monster_kill_receipts k JOIN map_charms c ON c.id=k.charm_id WHERE k.source_kind='rally' AND k.source_id=?",[$id])->fetch();
 ck($rallyCharm&&(int)$rallyCharm['coord_x']===60&&(int)$rallyCharm['coord_y']===60,'rally kill creates one public charm at the stored monster location');
 ck((int)$db->query('SELECT COUNT(*) FROM battle_reports')->fetchColumn()===2,'each owner receives a report');
 $reports=$db->query('SELECT attacker_id,data_json FROM battle_reports ORDER BY attacker_id')->fetchAll();
 ck(json_decode($reports[0]['data_json'],true)['troops'][0]['sent']===5000&&json_decode($reports[1]['data_json'],true)['troops'][0]['sent']===5000,'personal reports show only their owners sent troops');
 ck(count(json_decode($reports[0]['data_json'],true)['item_rewards'])>0,'personal report names the received inventory rewards');
 ck((int)json_decode($reports[0]['data_json'],true)['charm']['id']===(int)$rallyCharm['id']&&json_decode($reports[0]['data_json'],true)['charm']['ownership']===null,'rally report identifies the guaranteed public charm');
 ck((int)$db->query('SELECT gold FROM cities WHERE id=1')->fetchColumn()===$before&&stock(1)===20000,'troops and haul wait for return');
 RallyService::tick();ck((int)$db->query('SELECT COUNT(*) FROM battle_reports')->fetchColumn()===2,'repeated tick does not repeat rewards');
 home($id);home($id);ck(stock(1)===25000&&stock(2)===25000,'both armies return exactly once');
 ck((int)$db->query('SELECT SUM(quantity) FROM player_inventory WHERE item_code=10201025')->fetchColumn()===5,'shared item pool is neither multiplied nor lost');
 ck((int)$db->query('SELECT SUM(xp) FROM player_lord_progress')->fetchColumn()===20,'shared Lord XP conserved');
 $mid=monster();$id=MonsterRally::start(1,1,60,60,[50100101=>100],1,'');RallyService::join(2,2,$id,[50100101=>100]);RallyService::cancel($id,1);
 ck(stock(1)===25000&&stock(2)===25000,'cancelling refunds both reserved armies');
 ck((int)$db->query('SELECT action_points FROM players WHERE id=1')->fetchColumn()===175,'pre-launch cancellation refunds only its AP');rejects(fn()=>RallyService::cancel($id,1),'duplicate cancel cannot refund again');
 $id=MonsterRally::start(1,1,60,60,[50100101=>100],1,'');$db->execute('DELETE FROM field_monsters WHERE id=?',[$mid]);RallyService::launch($id,1);ck(rally($id)['status']==='cancelled'&&stock(1)===25000,'vanished target before launch cancels safely');
 $mid=monster();$id=MonsterRally::start(1,1,60,60,[50100101=>100],1,'');RallyService::launch($id,1);$db->execute('DELETE FROM field_monsters WHERE id=?',[$mid]);$new=monster();arrive($id);ck((int)$db->query('SELECT hp_current FROM field_monsters WHERE id=?',[$new])->fetchColumn()===1000,'replacement at same coordinates cannot be attacked');home($id);ck(stock(1)===25000,'unavailable target after launch returns troops');
 $db->execute('DELETE FROM field_monsters');$mid=monster(60,1000000);$id=MonsterRally::start(1,1,60,60,[50100101=>10],1,'');RallyService::join(2,2,$id,[50100101=>10]);RallyService::launch($id,1);arrive($id);$result=json_decode(rally($id)['result_json'],true);
 ck(!$result['monster_killed']&&$result['new_monster_hp']<1000000,'failed attack persists partial monster damage');
 ck((int)$db->query('SELECT SUM(count) FROM hospital_wounded')->fetchColumn()===3,'early failed rallies wound only a small share');home($id);ck(stock(1)===24998&&stock(2)===24999,'only surviving troops return after defeat');
 $db->execute("UPDATE worlds SET status='paused' WHERE id=1");rejects(fn()=>MonsterRally::start(1,1,60,60,[50100101=>1],1,''),'paused worlds reject new rallies');$db->execute("UPDATE worlds SET status='running' WHERE id=1");
 foreach(\Conquer\Game\Charm\CharmEffects::KEYS as $category=>$key){
  $db->execute('DELETE FROM player_charms_active');$base=BuffEngine::getBuffs(1);
  $db->execute("INSERT INTO player_charms_active(player_id,stat_category,grade,charm_code,bonus_pct,expires_at) VALUES(1,?,'normal',10700001,10,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))",[$category]);
  $buffs=BuffEngine::getBuffs(1);ck(abs(($buffs[$key]??0)-($base[$key]??0)-.1)<1e-8,'map charm contributes '.$category.' -> '.$key);
  $db->execute('UPDATE player_charms_active SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND)');ck(abs((BuffEngine::getBuffs(1)[$key]??0)-($base[$key]??0))<1e-8,'expired charm stops '.$category);
 }
 $db->execute('DELETE FROM player_charms_active');
 $all=\Conquer\Game\City\TroopData::t1();$code=(int)$all[0]['code'];
 $armies=[['player_id'=>1,'troops'=>[$code=>100],'buffs'=>['troops_atk'=>1]],['player_id'=>2,'troops'=>[$code=>100],'buffs'=>[]]];
 $fight=BattleEngine::resolveMonsterArmies($armies,['hp_current'=>1000000],['stats'=>['hp'=>100,'attack'=>1,'defense'=>0]]);
 ck($fight['report']['attacker_damage']===300.0&&$fight['armies'][0]['combat_snapshot']['attack']===200.0&&$fight['armies'][1]['combat_snapshot']['attack']===100.0,'one owners attack bonus never applies to the other army');
 ck(array_sum(BattleEngine::splitAmount(1,[100,1]))===1&&BattleEngine::splitAmount(1,[100,1])[0]===1,'small rewards allocated without rounding loss');
 foreach(json_decode(file_get_contents(ROOT_DIR.'/data/rally_rewards.json'),true) as $pool)if(is_array($pool))foreach($pool as $drop)ck(\Conquer\Game\Inventory\InventoryService::getItemDef($drop['item_code'])!==null,'rally reward has a usable inventory definition '.$drop['item_code']);
 echo "ALL $checks MONSTER RALLY AND CHARM CHECKS PASSED\n";
}catch(Throwable $e){$exit=1;fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString()."\n");}
finally{if($fixture)$fixture->close();}
exit($exit);
