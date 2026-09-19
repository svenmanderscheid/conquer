<?php
declare(strict_types=1);
/** Weekly shrine events and landmark placement, using only a disposable local database. */
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();date_default_timezone_set('UTC');
use Conquer\Db\Connection;
use Conquer\Game\Shrine\{CongressService,ShrineEvent,ShrineService};
use Conquer\Game\Map\{WorldPlacement,WorldTerrain,FrontierService};
function expectShrine(bool $condition,string $label):void{if(!$condition)throw new RuntimeException($label);echo 'PASS '.$label."\n";}
function closedShrine(callable $fn,string $label):void{try{$fn();}catch(RuntimeException $e){expectShrine($e->getCode()===403,$label);return;}throw new RuntimeException($label.' accepted forbidden action');}
function setEventClock(Connection $db,string $date):int{$timestamp=strtotime($date.' UTC');$db->execute('SET timestamp='.(int)$timestamp);return $timestamp;}
function armyStock(Connection $db,int $pid):int{return(int)$db->query('SELECT count FROM city_troops WHERE city_id=? AND troop_code=50100101',[$pid])->fetchColumn();}
function settleAt(Connection $db,int $id,string $date):void{setEventClock($db,$date);CongressService::resolveMarch($id);}
function finishShrineReturn(Connection $db,int $id):void{$db->execute('UPDATE marches SET return_time=UTC_TIMESTAMP() WHERE id=?',[$id]);CongressService::resolveMarch($id);}
$before=ShrineEvent::state(strtotime('2026-09-19 15:59:59 UTC'));
$start=ShrineEvent::state(strtotime('2026-09-19 16:00:00 UTC'));
$last=ShrineEvent::state(strtotime('2026-09-19 19:59:59 UTC'));
$end=ShrineEvent::state(strtotime('2026-09-19 20:00:00 UTC'));
expectShrine(!$before['active']&&$start['active']&&$last['active']&&!$end['active'],'UTC weekly window is start-inclusive and end-exclusive');
expectShrine($before['next_starts_at']==='2026-09-19 16:00:00'&&$start['next_starts_at']==='2026-09-26 16:00:00'&&$end['starts_at']==='2026-09-26 16:00:00','next occurrence advances exactly one week');
expectShrine($start['instance_id']===$last['instance_id']&&$start['instance_id']!==$end['instance_id'],'each weekly event has one distinct stable instance ID');
expectShrine(ShrineEvent::arrivalAllowed($start['instance_id'],strtotime('2026-09-19 19:59:59 UTC'))&&!ShrineEvent::arrivalAllowed($start['instance_id'],strtotime('2026-09-19 20:00:00 UTC'))&&!ShrineEvent::arrivalAllowed($start['instance_id'],strtotime('2026-09-26 16:00:00 UTC')),'arrival requires the same active event instance');
$shift=ShrineEvent::definition();$shift['schedule']=['timezone'=>'UTC','iso_weekday'=>7,'starts_at'=>'23:00','duration_seconds'=>7200];
expectShrine(ShrineEvent::state(strtotime('2026-09-21 00:30:00 UTC'),$shift)['active'],'data-configured schedules may safely cross midnight and week boundaries');
foreach(ShrineEvent::definition()['shrines'] as $s)expectShrine(abs($s['coord_x']-128)===28&&abs($s['coord_y']-128)===28&&WorldTerrain::isDryRectangle(...WorldPlacement::footprint('shrine',$s['coord_x'],$s['coord_y'])),$s['code'].' occupies a dry symmetric6x6 footprint');
expectShrine(!WorldTerrain::isDryRectangle(151,151,153,153),'rejected original southeast corner intersects water');
expectShrine(WorldPlacement::footprint('shrine',100,100)===[98,98,103,103]&&WorldPlacement::footprint('congress',128,128)===[125,125,131,131],'landmark footprint definitions reserve6x6 and7x7 tiles');
expectShrine(!WorldTerrain::isDryRectangle(...WorldPlacement::footprint('shrine',155,155)),'old radius27 southeast location cannot fit the enlarged6x6 shrine');
$cfg=require ROOT_DIR.'/config/database.php';if(!in_array($cfg['host']??'',['localhost','127.0.0.1'],true))exit("Local MySQL required.\n");
$name='conquer_shrines_test_'.bin2hex(random_bytes(6));$temp=sys_get_temp_dir().DIRECTORY_SEPARATOR.$name;$admin=null;$exit=0;
try{
 $source=$cfg['database'];if(!preg_match('/^[a-zA-Z0-9_]+$/D',$source))throw new RuntimeException('Invalid source database');
 $admin=new PDO('mysql:host='.$cfg['host'].';port='.($cfg['port']??3306).';charset=utf8mb4',$cfg['username'],$cfg['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $admin->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
 foreach($admin->query('SHOW TABLES FROM `'.$source.'`')->fetchAll(PDO::FETCH_COLUMN) as $table)$admin->exec('CREATE TABLE `'.$name.'`.`'.$table.'` LIKE `'.$source.'`.`'.$table.'`');
 $admin->exec('INSERT INTO `'.$name.'`.worlds SELECT * FROM `'.$source.'`.worlds WHERE id=1');
 mkdir($temp.'/config',0700,true);$cfg['database']=$name;file_put_contents($temp.'/config/database.php',"<?php\nreturn ".var_export($cfg,true).';');
 $db=Connection::init($temp);\Conquer\Logger::init($temp.'/test.log');
 // Adjacent module schemas are required by the current shared BuffEngine, only in this test DB.
 $db->getPdo()->exec(file_get_contents(ROOT_DIR.'/migrations/0068_progression_and_events.sql'));
 for($pid=1;$pid<=3;$pid++){$db->execute("INSERT INTO players(id,username,email,password_hash) VALUES(?,?,?,'unused')",[$pid,'ShrineFixture'.$pid,'shrine'.$pid.'@invalid.test']);$db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y) VALUES(?,?,1,'Test city',?,40)",[$pid,$pid,30+$pid*5]);$db->execute('INSERT INTO city_troops(city_id,troop_code,count) VALUES(?,50100101,200000)',[$pid]);}
 $db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id) VALUES(1,1,'Forest Fixture','FOR',1),(2,1,'Lava Fixture','LAV',3)");
 $db->execute("INSERT INTO alliance_members(alliance_id,player_id,role) VALUES(1,1,'leader'),(1,2,'member'),(2,3,'leader')");
 for($n=1;$n<=45;$n++)$db->execute("INSERT INTO shrines(world_id,shrine_code,tier,coord_x,coord_y) VALUES(1,?,'C',?,10)",['LEGACY_'.$n,10+$n*3]);
 $db->getPdo()->exec(file_get_contents(ROOT_DIR.'/migrations/0062_central_congress.sql'));
 $db->execute("INSERT INTO shrines(id,world_id,shrine_code,tier,coord_x,coord_y,owner_alliance_id,secured_at) VALUES(100,1,'SHRINE_FOREST','B',90,90,1,UTC_TIMESTAMP())");
 $db->execute("INSERT INTO shrine_garrisons(shrine_id,player_id,city_id,troops_json,alliance_id,buffs_json,travel_seconds) VALUES(100,2,2,'{\"50100101\":37}',1,'{}',12)");
 $migration=file_get_contents(ROOT_DIR.'/migrations/0070_four_shrines.sql');$db->getPdo()->exec($migration);$db->getPdo()->exec($migration);
 $expansion=file_get_contents(ROOT_DIR.'/migrations/0071_expand_shrine_footprints.sql');
 $db->execute("INSERT INTO marches(player_id,world_id,march_type,origin_city_id,target_x,target_y,target_type,target_id,troops_json,departure_time,arrival_time,state) VALUES(1,1,13,1,101,101,4,100,'{}',UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),'marching')");$blockedMarch=$db->lastInsertId();
 try{$db->getPdo()->exec($expansion);throw new RuntimeException('Expansion accepted active shrine march');}catch(PDOException $e){expectShrine($e->getCode()==='23000'&&str_contains($e->getMessage(),'shrine_expansion_waits_for_active_marches'),'migration explicitly refuses to move a shrine with an active march');}
 expectShrine((int)$db->query('SELECT coord_x FROM shrines WHERE id=100')->fetchColumn()===101,'blocked expansion leaves original shrine coordinates intact');
 $db->execute("UPDATE marches SET state='complete' WHERE id=?",[$blockedMarch]);
 $db->getPdo()->exec($expansion);$db->getPdo()->exec($expansion);
 $list=CongressService::eventShrines(2);$forest=$list[0];$ids=array_column($list,'id','element');
 expectShrine(count($list)===4&&array_column($list,'element')===['forest','ice','sand','lava']&&count(array_unique(array_column($list,'id')))===4,'read model contains exactly four correctly ordered unique elemental shrines');
 expectShrine($forest['id']===100&&$forest['shrine_tier']==='B'&&$forest['alliance_id']===1&&$forest['my_garrison']['total']===37,'migration reuses existing code and retains tier ownership and garrison');
 expectShrine(array_map(static fn($s)=>[$s['coord_x'],$s['coord_y']],$list)===[[100,100],[156,100],[100,156],[156,156]],'additive expansion migration relocates the same four IDs to the minimal dry square');
 expectShrine((int)$db->query('SELECT COUNT(*) FROM shrines')->fetchColumn()===50,'forty-five legacy shrines and Kongress remain without duplicate event landmarks');
 foreach($list as $s)expectShrine($s['kind']==='shrine'&&$s['art_key']===$s['shrine_code']&&$s['event']['name']==='Krieg der vier Schreine'&&$s['hold_seconds']===3600,'detail contract for '.$s['element']);
 $db->transaction(function(Connection $db)use($list):void{WorldPlacement::lockWorld($db,1);foreach($list as $s){$x=$s['coord_x'];$y=$s['coord_y'];
   foreach(['monster','resource','city'] as $kind){$blocked=0;for($dy=-2;$dy<=3;$dy++)for($dx=-2;$dx<=3;$dx++)$blocked+=!WorldPlacement::canPlace($db,1,$kind,$x+$dx,$y+$dy);expectShrine($blocked===36,'all36 '.$kind.' placements blocked on '.$s['element'].' footprint');}
   expectShrine(WorldPlacement::canPlace($db,1,'monster',$x+4,$y),'immediately adjacent dry tile remains usable beyond '.$s['element'].' six columns');
   foreach([[-4,-4],[-4,4],[4,-4],[4,4]] as [$dx,$dy])expectShrine(!WorldPlacement::canPlace($db,1,'city',$x+$dx,$y+$dy),'outside city anchor intersects only the '.$s['element'].' corner at '.$dx.','.$dy);
 }
   // Relocate only this fixture Kongress to dry land so water does not mask reservation tests.
   $db->execute("UPDATE shrines SET coord_x=115,coord_y=100 WHERE shrine_code='CONGRESS'");
   foreach(['monster','resource','city'] as $kind){$blocked=0;for($dy=-3;$dy<=3;$dy++)for($dx=-3;$dx<=3;$dx++)$blocked+=!WorldPlacement::canPlace($db,1,$kind,115+$dx,100+$dy);expectShrine($blocked===49,'all49 '.$kind.' placements blocked on unchanged Kongress footprint');}
   expectShrine(!WorldPlacement::canPlace($db,1,'city',110,95)&&!WorldPlacement::canPlace($db,1,'city',119,104),'Kongress SQL lookup covers city anchors beyond both extreme corners');
   expectShrine(WorldPlacement::canPlace($db,1,'monster',119,100),'Kongress reservation ends after seven tiles');
   $db->execute("UPDATE shrines SET coord_x=128,coord_y=128 WHERE shrine_code='CONGRESS'");
   foreach(['monster','resource','city'] as $kind)expectShrine(!WorldPlacement::canPlace($db,1,$kind,128,128),$kind.' remains forbidden on the central lake');
 });
 // A legacy resource and idle city overlapping the expanded reservation move without resetting contents.
 $db->execute('UPDATE cities SET coord_x=104,coord_y=104 WHERE id=2');$cityBefore=$db->query('SELECT * FROM cities WHERE id=2')->fetch();
 $db->execute("INSERT INTO marches(player_id,world_id,march_type,origin_city_id,target_x,target_y,troops_json,departure_time,arrival_time,state) VALUES(2,1,5,2,40,40,'{}',UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),'marching')");$cityArmy=$db->lastInsertId();
 expectShrine($db->transaction(fn($db)=>WorldPlacement::repairCities($db,1,2))===0&&$db->query('SELECT coord_x FROM cities WHERE id=2')->fetchColumn()===104,'city with a marching army stays fixed despite touching the enlarged shrine corner');
 $db->execute("UPDATE marches SET state='complete' WHERE id=?",[$cityArmy]);
 $db->transaction(fn($db)=>WorldPlacement::repairCities($db,1,2));$cityAfter=$db->query('SELECT * FROM cities WHERE id=2')->fetch();
 expectShrine([$cityAfter['coord_x'],$cityAfter['coord_y']]!==[104,104],'repairCities finds an outside city anchor touching only the expanded southeast corner');
 unset($cityBefore['coord_x'],$cityBefore['coord_y'],$cityAfter['coord_x'],$cityAfter['coord_y']);expectShrine($cityBefore===$cityAfter,'city placement repair preserves all non-coordinate state');
 $db->execute('UPDATE cities SET coord_x=96,coord_y=96 WHERE id=2');
 expectShrine($db->transaction(fn($db)=>WorldPlacement::repairCities($db,1,2))===1,'repairCities also finds the outside northwest corner city');
 $db->execute('INSERT INTO field_objects(world_id,coord_x,coord_y,object_type,level,resource_amount,resource_max,expires_at) VALUES(1,103,103,1,1,321,1000,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))');$resourceId=$db->lastInsertId();
 $db->execute('INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current) VALUES(1,20209901,100,100,77)');$monsterId=$db->lastInsertId();
 $db->execute("INSERT INTO marches(player_id,world_id,march_type,origin_city_id,target_x,target_y,target_type,target_id,troops_json,departure_time,arrival_time,state) VALUES(1,1,5,1,100,100,3,?,'{}',UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),'marching')",[$monsterId]);$busy=$db->lastInsertId();
 $db->transaction(function(Connection $db):void{WorldPlacement::lockWorld($db,1);FrontierService::repairCollisions($db,1);});
 $resource=$db->query('SELECT * FROM field_objects WHERE id=?',[$resourceId])->fetch();$monster=$db->query('SELECT * FROM field_monsters WHERE id=?',[$monsterId])->fetch();
 expectShrine([$resource['coord_x'],$resource['coord_y']]!==[103,103]&&(int)$resource['resource_amount']===321,'expanded shrine repair moves an idle resource from the new outer corner without resetting stock');
 expectShrine([$monster['coord_x'],$monster['coord_y']]===[100,100]&&(int)$monster['hp_current']===77,'expanded shrine repair preserves a busy target until its army returns');
 $db->execute("UPDATE marches SET state='complete' WHERE id=?",[$busy]);$db->transaction(function(Connection $db):void{WorldPlacement::lockWorld($db,1);FrontierService::repairCollisions($db,1);});
 $monster=$db->query('SELECT * FROM field_monsters WHERE id=?',[$monsterId])->fetch();expectShrine([$monster['coord_x'],$monster['coord_y']]!==[100,100]&&(int)$monster['hp_current']===77,'newly idle monster moves out of shrine footprint retaining its identity and damage');
 $db->execute('DELETE FROM shrine_garrisons');$db->execute('DELETE FROM shrine_captures');$db->execute('UPDATE shrines SET owner_alliance_id=NULL,secured_at=NULL');
 setEventClock($db,'2026-09-19 15:59:59');
 closedShrine(fn()=>CongressService::dispatch(1,$ids['forest'],[50100101=>100]),'event closed one second before start');
 expectShrine(armyStock($db,1)===200000&&!CongressService::detail($ids['forest'],1)['can_attack'],'closed event reserves no troops and disables attack read model');
 $congress=CongressService::state(1);expectShrine($congress['can_attack']&&!isset($congress['event']),'Kongress remains independent of the four-shrine schedule');
 $congressMarch=CongressService::dispatch(1,$congress['id'],[50100101=>1]);expectShrine($congressMarch['state']==='marching','Kongress accepts a real march while shrine event is closed');
 $db->execute("UPDATE marches SET state='complete' WHERE id=?",[$congressMarch['march_id']]);
 setEventClock($db,'2026-09-19 16:00:00');
 $late=CongressService::dispatch(1,$ids['forest'],[50100101=>100]);
 expectShrine($db->query('SELECT event_instance FROM shrine_march_orders WHERE march_id=?',[$late['march_id']])->fetchColumn()===$start['instance_id'],'dispatch snapshots the authoritative active event instance');
 // A delayed march at the exact closing boundary returns every troop without battle.
 $db->execute("UPDATE marches SET arrival_time='2026-09-19 20:00:00' WHERE id=?",[$late['march_id']]);
 $stock=armyStock($db,1);settleAt($db,$late['march_id'],'2026-09-19 20:00:00');$m=$db->query('SELECT * FROM marches WHERE id=?',[$late['march_id']])->fetch();
 expectShrine($m['state']==='returning'&&json_decode($m['haul_json'],true)['survivors'][50100101]===100&&(int)$db->query('SELECT COUNT(*) FROM battle_reports')->fetchColumn()===0,'arrival at event end returns full army without casualties or report');
 finishShrineReturn($db,$late['march_id']);CongressService::resolveMarch($late['march_id']);expectShrine(armyStock($db,1)===$stock+100,'out-of-window return is credited exactly once');
 setEventClock($db,'2026-09-19 16:00:00');$old=CongressService::dispatch(1,$ids['ice'],[50100101=>100]);$db->execute("UPDATE marches SET arrival_time='2026-09-26 16:01:00' WHERE id=?",[$old['march_id']]);settleAt($db,$old['march_id'],'2026-09-26 16:01:00');
 expectShrine($db->query('SELECT state FROM marches WHERE id=?',[$old['march_id']])->fetchColumn()==='returning'&&CongressService::detail($ids['ice'],1)['state']==='neutral','next active week cannot accept a previous event army');finishShrineReturn($db,$old['march_id']);
 setEventClock($db,'2026-09-19 19:58:00');$db->execute('INSERT INTO shrine_captures(shrine_id,garrison_troops_json) VALUES(?,?)',[$ids['sand'],json_encode([50100101=>1])]);$win=CongressService::dispatch(1,$ids['sand'],[50100101=>1000]);
 $db->execute("UPDATE marches SET arrival_time='2026-09-19 19:59:59' WHERE id=?",[$win['march_id']]);settleAt($db,$win['march_id'],'2026-09-19 20:10:00');$sand=CongressService::detail($ids['sand'],1);
 expectShrine($sand['state']==='contested'&&$sand['alliance_id']===1&&$sand['my_garrison']['total']>0,'last-second valid arrival captures and stations survivors even if processed after window closes');
 $hold=$sand['contested_until'];setEventClock($db,'2026-09-19 21:10:01');CongressService::tick();$sand=CongressService::detail($ids['sand'],1);
 expectShrine($sand['state']==='secured'&&!$sand['event']['active']&&$sand['alliance_id']===1,'one-hour hold may complete after event end and ownership persists');
 $reinforce=CongressService::dispatch(2,$ids['sand'],[50100101=>20],true);$db->execute('UPDATE marches SET arrival_time=UTC_TIMESTAMP() WHERE id=?',[$reinforce['march_id']]);CongressService::resolveMarch($reinforce['march_id']);
 expectShrine(CongressService::detail($ids['sand'],2)['my_garrison']['total']===20,'owner alliance may reinforce outside the event');
 $recall=CongressService::recall(2,$ids['sand']);$stock=armyStock($db,2);finishShrineReturn($db,$recall['march_id']);CongressService::resolveMarch($recall['march_id']);expectShrine(armyStock($db,2)===$stock+20,'garrison recall works outside event and returns once');
 closedShrine(fn()=>CongressService::dispatch(3,$ids['sand'],[50100101=>100]),'rival cannot attack secured shrine outside event');
 expectShrine($db->query('SELECT contested_until FROM shrine_captures WHERE shrine_id=?',[$ids['sand']])->fetchColumn()===$hold,'closed rival attack cannot reset hold or owner');
 $db->getPdo()->exec($expansion);expectShrine(CongressService::detail($ids['sand'],1)['alliance_id']===1&&CongressService::detail($ids['sand'],1)['my_garrison']['total']>0,'repeat expansion migration after actual capture preserves current owner and army');
 echo "ALL FOUR-SHRINE CHECKS PASSED (isolated database).\n";
}catch(Throwable $e){fwrite(STDERR,'FAIL '.$e->getMessage()."\n".$e->getTraceAsString()."\n");$exit=1;}
finally{
 if($admin&&preg_match('/^conquer_shrines_test_[a-f0-9]{12}$/D',$name))$admin->exec('DROP DATABASE IF EXISTS `'.$name.'`');
 $resolved=realpath($temp);$parent=realpath(sys_get_temp_dir());
 if($resolved!==false&&$parent!==false&&str_replace('\\','/',$resolved)===str_replace('\\','/',$parent).'/'.$name&&preg_match('/^conquer_shrines_test_[a-f0-9]{12}$/D',basename($resolved))){foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolved,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $item){if($item->isDir()&&!$item->isLink())rmdir($item->getPathname());else unlink($item->getPathname());}rmdir($resolved);}
}
exit($exit);
