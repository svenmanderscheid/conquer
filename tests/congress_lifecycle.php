<?php
declare(strict_types=1);
/** Congress regression suite uses a disposable local database; existing accounts are never written. */
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();date_default_timezone_set('UTC');
use Conquer\Db\Connection;
use Conquer\Game\Shrine\{CongressService,ShrineService};
use Conquer\Game\Map\{WorldPlacement,WorldTerrain};
use Conquer\Game\March\MarchTick;
function checkCongress(bool $condition,string $label):void{if(!$condition)throw new RuntimeException($label);echo 'PASS '.$label."\n";}
function rejected(callable $fn,string $label,?int $code=null):void{try{$fn();}catch(RuntimeException $e){checkCongress($code===null||$e->getCode()===$code,$label);return;}throw new RuntimeException($label.' accepted invalid action');}
function arrive(Connection $db,int $id):void{$db->execute('UPDATE marches SET departure_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 6 SECOND),arrival_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$id]);CongressService::resolveMarch($id);}
function countAt(Connection $db,int $pid,int $code=50100101):int{return(int)$db->query('SELECT count FROM city_troops WHERE city_id=? AND troop_code=?',[$pid,$code])->fetchColumn();}
function finish(Connection $db,int $id):void{$db->execute('UPDATE marches SET return_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$id]);CongressService::resolveMarch($id);}
$cfg=require ROOT_DIR.'/config/database.php';if(!in_array($cfg['host']??'',['localhost','127.0.0.1'],true))exit("Local MySQL required.\n");
$name='conquer_congress_test_'.bin2hex(random_bytes(6));$temp=sys_get_temp_dir().DIRECTORY_SEPARATOR.$name;$admin=null;$exit=0;
try{
 $source=$cfg['database'];if(!preg_match('/^[a-zA-Z0-9_]+$/D',$source))throw new RuntimeException('Invalid source database');
 $admin=new PDO('mysql:host='.$cfg['host'].';port='.($cfg['port']??3306).';charset=utf8mb4',$cfg['username'],$cfg['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $admin->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
 foreach($admin->query('SHOW TABLES FROM `'.$source.'`')->fetchAll(PDO::FETCH_COLUMN) as $table)$admin->exec('CREATE TABLE `'.$name.'`.`'.$table.'` LIKE `'.$source.'`.`'.$table.'`');
 $admin->exec('INSERT INTO `'.$name.'`.worlds SELECT * FROM `'.$source.'`.worlds WHERE id=1');
 mkdir($temp.'/config',0700,true);$cfg['database']=$name;file_put_contents($temp.'/config/database.php',"<?php\nreturn ".var_export($cfg,true).';');
 $db=Connection::init($temp);\Conquer\Logger::init($temp.'/test.log');
 // This suite models an established world with an already owned central shrine.
 foreach(['0084_land_progression.sql','0085_monster_charms.sql','0086_reward_world_revisions.sql','0087_charm_compatibility.sql'] as $file)
  \Conquer\Db\MigrationSql::apply($db->getPdo(),(string)file_get_contents(ROOT_DIR.'/migrations/'.$file));
 // The shared BuffEngine now reads mastery data from the adjacent progression module.
 $db->getPdo()->exec(file_get_contents(ROOT_DIR.'/migrations/0068_progression_and_events.sql'));
 for($pid=1;$pid<=4;$pid++){$db->execute("INSERT INTO players(id,username,email,password_hash) VALUES(?,?,?,'unused')",[$pid,'CongressFixture'.$pid,'congress'.$pid.'@invalid.test']);$db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y) VALUES(?,?,1,'Test city',?,40)",[$pid,$pid,30+$pid*5]);$db->execute('INSERT INTO city_troops(city_id,troop_code,count) VALUES(?,50100101,200000)',[$pid]);}
 $db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id) VALUES(1,1,'Test Dawn','DAWN',1),(2,1,'Test Dusk','DUSK',3)");
 $db->execute("INSERT INTO alliance_members(alliance_id,player_id,role) VALUES(1,1,'leader'),(1,2,'member'),(2,3,'leader')");
 $db->execute("INSERT INTO shrines(id,world_id,shrine_code,tier,coord_x,coord_y,owner_alliance_id,secured_at) VALUES(71,1,'OLD_CENTRAL','S',128,128,1,UTC_TIMESTAMP())");
 $migration=file_get_contents(ROOT_DIR.'/migrations/0062_central_congress.sql');$db->getPdo()->exec($migration);$db->getPdo()->exec($migration);
 $db->getPdo()->exec(file_get_contents(ROOT_DIR.'/migrations/0070_four_shrines.sql'));
 checkCongress((int)$db->query("SELECT COUNT(*) FROM shrines WHERE world_id=1 AND shrine_code='CONGRESS'")->fetchColumn()===1,'idempotent migration creates one central Congress');
 $s=CongressService::state(1);checkCongress($s['id']===71&&$s['alliance_id']===1&&$s['state']==='secured','existing central shrine ID and legacy ownership survive migration');
 checkCongress($s['bonuses']===[]&&ShrineService::getAllianceBonuses(1)===[],'Congress invents no economy or military passive bonus');
 $db->execute('DELETE FROM shrine_captures');$db->execute('UPDATE shrines SET owner_alliance_id=NULL,secured_at=NULL');
 $s=CongressService::state(1);checkCongress($s['state']==='neutral'&&$s['garrison_total']===1500000&&$s['hold_seconds']===3600,'neutral S-tier catalog garrison and one-hour hold are preserved');
 checkCongress(!$s['can_garrison']&&$s['can_attack']&&!CongressService::state(4)['can_attack'],'read model exposes alliance-dependent actions');
 checkCongress(WorldTerrain::isWater(128,128),'Congress is intentionally at great lake center');
 foreach(['city','resource','monster'] as $kind)checkCongress(!WorldPlacement::canPlace($db,1,$kind,128,128),$kind.' still cannot occupy lake/Congress');
 rejected(fn()=>CongressService::dispatch(4,71,[50100101=>10]),'alliance required',403);
 foreach([[50100101=>0],[50100101=>1.5],[50100101=>'5'],[50100101=>-1],[99999999=>5],[50100101=>50001]] as $troops)rejected(fn()=>CongressService::dispatch(1,71,$troops),'strict troop composition rejects '.json_encode($troops));
 checkCongress(countAt($db,1)===200000&&(int)$db->query('SELECT COUNT(*) FROM marches')->fetchColumn()===0,'failed dispatches reserve no troops or march slots');
 $attack=CongressService::dispatch(1,71,[50100101=>1000]);$mid=$attack['march_id'];
 checkCongress(countAt($db,1)===199000&&$attack['state']==='marching','attack reserves actual city troops and has travel time');
 CongressService::resolveMarch($mid);checkCongress(CongressService::state(1)['garrison_total']===1500000,'capture cannot resolve before arrival');
 arrive($db,$mid);$s=CongressService::state(1);checkCongress($s['state']==='neutral'&&$s['garrison_total']<1500000&&$s['garrison_total']>0,'failed allied attack permanently weakens NPC garrison');
 $snapshot=$s['garrison_total'];CongressService::resolveMarch($mid);checkCongress(CongressService::state(1)['garrison_total']===$snapshot,'repeat settlement cannot damage defenders twice');
 $row=$db->query('SELECT * FROM marches WHERE id=?',[$mid])->fetch();$haul=json_decode($row['haul_json'],true);checkCongress($row['state']==='returning'&&$haul['survivors'][50100101]===200,'defeated survivors return with original maximum80 percent loss rule');
 checkCongress((int)$db->query('SELECT count FROM hospital_wounded WHERE city_id=1 AND troop_code=50100101')->fetchColumn()===240,'thirty percent of casualties enter the real hospital');
 finish($db,$mid);$stock=countAt($db,1);CongressService::resolveMarch($mid);checkCongress($stock===199200&&countAt($db,1)===$stock,'survivors return exactly once');
 $db->execute("UPDATE shrine_captures SET garrison_troops_json=? WHERE shrine_id=71",[json_encode([50100101=>10])]);
 $win=CongressService::dispatch(1,71,[50100101=>1000]);arrive($db,$win['march_id']);$s=CongressService::state(1);
 checkCongress($s['state']==='contested'&&$s['alliance_id']===1&&$s['can_garrison']&&!$s['can_attack'],'victory starts real contested ownership and enables allied reinforcement');
 checkCongress(strtotime($s['contested_until'].' UTC')-time()>=3598,'victory does not instantly secure the Congress');
 checkCongress($s['my_garrison']['total']>0&&$s['can_recall']&&$db->query('SELECT state FROM marches WHERE id=?',[$win['march_id']])->fetchColumn()==='complete','victorious survivors remain as a real Congress garrison');
 $victoryStock=countAt($db,1);$victoryTroops=$s['my_garrison']['total'];$victoryRecall=CongressService::recall(1,71);
 checkCongress(countAt($db,1)===$victoryStock&&CongressService::state(1)['my_garrison']['total']===0,'conquering army recall starts a journey without instant city credit');
 finish($db,$victoryRecall['march_id']);CongressService::resolveMarch($victoryRecall['march_id']);
 checkCongress(countAt($db,1)===$victoryStock+$victoryTroops,'victorious garrison returns exactly once when explicitly recalled');
 rejected(fn()=>CongressService::dispatch(1,71,[50100101=>10]),'same alliance cannot reset its own hold with an attack');
 rejected(fn()=>CongressService::dispatch(3,71,[50100101=>10],true),'rivals cannot garrison another alliance Congress',403);
 $g1=CongressService::dispatch(2,71,[50100101=>20],true);$g2=CongressService::dispatch(2,71,[50100101=>30],true);arrive($db,$g1['march_id']);arrive($db,$g2['march_id']);
 checkCongress(CongressService::state(2)['my_garrison']['total']===50&&countAt($db,2)===199950,'repeated reinforcements accumulate without replacing/loss/duplication');
 $db->execute('UPDATE shrine_captures SET contested_until=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE shrine_id=71');$s=CongressService::state(2);
 checkCongress($s['state']==='secured'&&(int)$db->query('SELECT owner_alliance_id FROM shrines WHERE id=71')->fetchColumn()===1,'elapsed hold secures capture and synchronizes world ownership');
 $switch=CongressService::dispatch(2,71,[50100101=>10],true);$db->execute('DELETE FROM alliance_members WHERE player_id=2');arrive($db,$switch['march_id']);
 checkCongress(CongressService::state(2)['my_garrison']['total']===50&&$db->query('SELECT state FROM marches WHERE id=?',[$switch['march_id']])->fetchColumn()==='returning','departed member cannot add a pending garrison to old alliance');
 $recall=CongressService::recall(2,71);checkCongress(!CongressService::state(2)['can_recall']&&countAt($db,2)===199940,'former member may recall own station with a real return journey');
 finish($db,$recall['march_id']);finish($db,$switch['march_id']);checkCongress(countAt($db,2)===200000,'recall and cancelled arrival preserve every unwounded troop');
 rejected(fn()=>CongressService::recall(2,71),'duplicate recall cannot mint troops');
 $db->execute("INSERT INTO alliance_members(alliance_id,player_id,role) VALUES(1,2,'member')");
 $g=CongressService::dispatch(2,71,[50100101=>100],true);arrive($db,$g['march_id']);
 $rival=CongressService::dispatch(3,71,[50100101=>1000]);arrive($db,$rival['march_id']);$s=CongressService::state(3);
 checkCongress($s['alliance_id']===2&&$s['state']==='contested'&&$s['can_garrison'],'rival victory transfers Congress and starts a fresh hold');
 $retreat=$db->query("SELECT id,haul_json FROM marches WHERE player_id=2 AND state='returning' ORDER BY id DESC LIMIT 1")->fetch();
 checkCongress(CongressService::state(2)['my_garrison']['total']===0&&json_decode($retreat['haul_json'],true)['survivors'][50100101]===50,'defeated garrison survivors retreat instead of being silently deleted');
 finish($db,(int)$retreat['id']);checkCongress(countAt($db,2)===199950,'retreat returns surviving defenders exactly once');
 checkCongress((int)$db->query('SELECT COUNT(*) FROM battle_reports')->fetchColumn()===3,'actual battles each produce one persisted attacker report');
 // The generic march tick must recognize the new types, including delayed offline settlement.
 $db->execute("UPDATE marches SET state='complete'");$off=CongressService::dispatch(1,71,[50100101=>5000]);
 $db->execute('UPDATE marches SET departure_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 6 SECOND),arrival_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$off['march_id']]);
 MarchTick::runForPlayer(1);checkCongress(ShrineService::getShrine(71)['alliance_id']===1,'shared MarchTick resolves Congress instead of unsupported-type return');
 // A fresh world with no central structure also receives one stable Congress.
 $db->execute('DELETE FROM shrines');$db->execute('DELETE FROM shrine_captures');$db->getPdo()->exec($migration);$fresh=CongressService::state(1);$freshId=$fresh['id'];$db->getPdo()->exec($migration);
 checkCongress($fresh['state']==='neutral'&&$freshId===CongressService::state(1)['id'],'fresh seed is neutral and repeated migration preserves ID');
 echo "ALL CONGRESS CHECKS PASSED (isolated database).\n";
}catch(Throwable $e){fwrite(STDERR,'FAIL '.$e->getMessage()."\n".$e->getTraceAsString()."\n");$exit=1;}
finally{
 if($admin&&preg_match('/^conquer_congress_test_[a-f0-9]{12}$/D',$name))$admin->exec('DROP DATABASE IF EXISTS `'.$name.'`');
 $resolved=realpath($temp);$parent=realpath(sys_get_temp_dir());
 if($resolved!==false&&$parent!==false&&str_replace('\\','/',$resolved)===str_replace('\\','/',$parent).'/'.$name&&preg_match('/^conquer_congress_test_[a-f0-9]{12}$/D',basename($resolved))){foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolved,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $item){if($item->isDir()&&!$item->isLink())rmdir($item->getPathname());else unlink($item->getPathname());}rmdir($resolved);}
}
exit($exit);
