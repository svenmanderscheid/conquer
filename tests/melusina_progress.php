<?php
declare(strict_types=1);
/** Real event hooks and persistent progression in a disposable local schema only. */
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';date_default_timezone_set('UTC');
use Conquer\Db\Connection;
use Conquer\Game\Dungeon\{MelusinaProgress as M,DungeonException};
use Conquer\Game\Inventory\InventoryService as I;
use Conquer\Game\March\{GatherService,MarchTick};
use Conquer\Game\World\{WorldContext as W,WorldMapProfile};
if(($argv[1]??'')==='--craft-worker'){
    Connection::init($argv[2]);W::bind(1,1);
    try{M::craft(1,1,$argv[3]);echo json_encode(['ok'=>true]);}
    catch(Throwable $e){echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);}
    exit;
}
function mc(bool $ok,string $label):void {if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
function md(callable $work,string $label):void {try{$work();}catch(DungeonException){mc(true,$label);return;}throw new RuntimeException('Expected rejection: '.$label);}
function mq(string $sql,array $values=[]):mixed{return Connection::getInstance()->query($sql,$values)->fetchColumn();}
function mh(int $march):array{return json_decode((string)mq('SELECT haul_json FROM marches WHERE id=?',[$march]),true)?:[];}
function mdrop(int $id,int $x=418,int $y=846,int $world=1,int $player=1,int $at=0):array{return Connection::getInstance()->transaction(fn()=>M::drop($player,$world,'monster',$id,$x,$y,$at?:time()));}
function mr(int $world=1):int{$db=Connection::getInstance();$db->execute("INSERT INTO dungeon_runs(world_id,dungeon_code,week_key,leader_player_id,seed)VALUES(?,'melusina_well','2026-10-05',1,42)",[$world]);return $db->lastInsertId();}
function mg(int $x,int $y,int $remaining,int $capacity,int $seconds=10):array{
    $db=Connection::getInstance();
    $db->execute('INSERT INTO field_objects(world_id,coord_x,coord_y,object_type,level,resource_amount,resource_max,expires_at)VALUES(1,?,?,1,1,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))',[$x,$y,$remaining,$remaining]);$node=$db->lastInsertId();
    $db->execute("INSERT INTO marches(player_id,world_id,march_type,origin_city_id,target_x,target_y,target_type,target_id,troops_json,haul_json,departure_time,arrival_time,gathering_finishes_at,state)VALUES(1,1,9,1,?,?,5,?,'{}',?,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 20 SECOND),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 SECOND),DATE_ADD(DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 SECOND),INTERVAL ? SECOND),'arrived')",[$x,$y,$node,json_encode(['gather'=>['capacity'=>$capacity,'rate'=>10]]),$seconds]);$march=$db->lastInsertId();
    $db->execute('UPDATE field_objects SET gatherer_march_id=? WHERE id=?',[$march,$node]);return [$march,$node];
}
$fixture=null;
try{
    $fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();\Conquer\Logger::init(sys_get_temp_dir().'/conquer-melusina-test.log','ERROR');
    \Conquer\Db\MigrationSql::apply($db->getPdo(),file_get_contents(ROOT_DIR.'/migrations/0131_melusina_dungeon.sql'));
    $db->execute("INSERT INTO worlds(id,name,slug,status,map_size)VALUES(2,'Other quest world','other-quest','running',768),(3,'Legacy world','legacy-quest','running',256)");
    WorldMapProfile::configureEmptyWorld(1);WorldMapProfile::configureEmptyWorld(2);
    foreach([1,2] as $player){
        $db->execute("INSERT INTO players(id,username,email,password_hash)VALUES(?,?,?,'unused')",[$player,'MelusinaTester'.$player,'melusina'.$player.'@tests.invalid']);
        foreach([1,2,3] as $world){$city=($world-1)*10+$player;$db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y)VALUES(?,?,?,'Quest city',?,846)",[$city,$player,$world,400+$player*10]);
            foreach(\Conquer\Game\City\CityState::BUILDING_CODES as $code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level)VALUES(?,?,1)',[$city,$code]);
        }
    }
    W::bind(1,1);$r=M::rules();$fragment=(int)$r['fragment_item_code'];$key=(int)$r['key_item_code'];
    mc(M::available(1)&&!M::available(3),'only Luxembourg maps enable the historical quest');
    mc(!M::status(1,1)['accepted']&&mdrop(900001)===[],'unaccepted quest earns no fragment or pity');
    mc(M::status(1,1)['pity_count']===0,'unaccepted action leaves pity unchanged');
    md(fn()=>M::accept(1,1,''),'empty operation identifier is rejected');
    md(fn()=>M::craft(1,1,'craft-before-accept'),'crafting requires quest acceptance');
    M::accept(1,1,'accept-request-1');M::accept(1,1,'accept-request-1');
    mc(M::status(1,1)['accepted']&&(int)mq('SELECT COUNT(*) FROM melusina_operations')===1,'quest acceptance replay remains one operation');
    md(fn()=>M::craft(1,1,'accept-request-1'),'operation identifier cannot switch action');
    mc(mdrop(900002,170,370)===[]&&mdrop(900003,418,846,3)===[]&&mdrop(900004,418,846,2)===[],'other cantons, legacy maps and unaccepted worlds cannot progress');
    mc(mdrop(900005,418,846,1,1,time()-60)===[],'overdue event from before acceptance cannot earn a fragment');
    $db->execute('UPDATE melusina_progress SET pity_count=4 WHERE player_id=1 AND world_id=1');
    $drop=mdrop(900006);mc($drop===[$fragment=>1]&&M::status(1,1)['pity_count']===0,'fifth eligible action guarantees a fragment and resets pity');
    mc(I::quantity(1,$fragment,1)===0,'drop remains an item for the march haul, not immediate inventory');
    mc(mdrop(900006)===[]&&M::status(1,1)['pity_count']===0,'repeated event cannot reroll or advance pity');
    try{$db->transaction(static function()use($db):void{$db->execute('UPDATE melusina_progress SET pity_count=4 WHERE player_id=1 AND world_id=1');mdrop(900007);throw new LogicException('Simulated failed march write');});}catch(LogicException){}
    mc((int)mq("SELECT COUNT(*) FROM melusina_drop_events WHERE event_key='monster:900007'")===0&&M::status(1,1)['pity_count']===0,'failed march transaction rolls back both drop receipt and pity');
    M::accept(2,1,'accept-player-two');mc(mdrop(900006,418,846,1,2)===[],'another player cannot claim the same world event');
    $db->execute('UPDATE melusina_progress SET pity_count=0 WHERE player_id=1 AND world_id=1');$found=false;
    for($n=1;$n<=5;$n++)if(mdrop(900100+$n)){$found=true;break;}
    mc($found,'random rolls never exceed five eligible actions without a fragment');
    I::addItems(1,$fragment,6,1);M::craft(1,1,'craft-request-1');M::craft(1,1,'craft-request-1');
    mc(I::quantity(1,$fragment,1)===3&&M::keyCount(1,1)===1,'craft replay consumes exactly three interchangeable fragments once');
    mc(I::quantity(1,$fragment,2)===0&&M::keyCount(1,2)===0&&I::quantity(2,$fragment,1)===0,'fragment and key inventory is world and player scoped');
    M::craft(1,1,'craft-request-2');md(fn()=>M::craft(1,1,'craft-request-3'),'craft rejects missing fragments without a receipt');
    $run=mr();M::reserve($run,1,1);M::reserve($run,1,1);mc(M::keyCount(1,1)===1&&M::status(1,1)['reserved_keys']===1,'start reserves one group key even when repeated');
    W::bind(2,1);M::settle($run,false,[1,2]);M::settle($run,false,[1,2]);
    mc(M::keyCount(1,1)===2&&M::keyCount(1,2)===0&&M::reservation($run)['status']==='returned','failure/cancellation refunds once to saved world despite another active world');
    $run=mr();M::reserve($run,1,1);M::settle($run,true,[2,1,1]);M::settle($run,true,[1,2]);
    mc(M::keyCount(1,1)===1&&M::reservation($run)['status']==='consumed','success consumes its reserved key without refund');
    mc(M::status(1,1)['completed_runs']===1&&M::status(2,1)['completed_runs']===1&&M::status(1,1)['first_clear_at']!==null,'first-clear chronicle advances once for each party member');
    mc(M::status(1,2)['first_clear_at']===null,'another world retains a separate first-clear chronicle');
    W::bind(1,1);$db->execute('UPDATE melusina_progress SET accepted_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY),pity_count=4 WHERE player_id=1 AND world_id=1');
    [$gather,$node]=mg(418,846,100,100);GatherService::finish($gather);
    mc((mh($gather)['items'][$fragment]??0)===1&&(int)mq('SELECT resource_amount FROM field_objects WHERE id=?',[$node])===0,'real completed full gathering carries guaranteed fragment home');
    mc(M::status(1,1)['in_transit_fragments']===1&&I::quantity(1,$fragment,1)===0,'quest status distinguishes returning fragment from usable stock');
    GatherService::finish($gather);$db->execute('UPDATE marches SET return_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$gather]);MarchTick::runForPlayer(1);MarchTick::runForPlayer(1);
    mc(I::quantity(1,$fragment,1)===1&&M::status(1,1)['in_transit_fragments']===0,'repeated real homecoming pays fragment once in the correct world');
    $before=(int)mq('SELECT COUNT(*) FROM melusina_drop_events');
    [$partial]=mg(422,846,1000,100);GatherService::finish($partial);
    mc(empty(mh($partial)['items'][$fragment])&&(int)mq('SELECT COUNT(*) FROM melusina_drop_events')===$before,'capacity completion without node depletion earns no quest attempt');
    [$recall]=mg(426,846,1000,1000,100);GatherService::finish($recall,true);
    mc(empty(mh($recall)['items'][$fragment])&&(int)mq('SELECT COUNT(*) FROM melusina_drop_events')===$before,'early recall earns neither fragment nor pity');
    [$outside]=mg(170,370,100,100);GatherService::finish($outside);
    mc(empty(mh($outside)['items'][$fragment])&&(int)mq('SELECT COUNT(*) FROM melusina_drop_events')===$before,'full resource node outside Luxembourg canton earns no quest attempt');
    $db->execute("INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current,monster_type)VALUES(1,20209901,430,846,1,'solo')");$monster=$db->lastInsertId();
    $db->execute("INSERT INTO marches(player_id,world_id,march_type,origin_city_id,target_x,target_y,target_type,target_id,troops_json,departure_time,arrival_time,state)VALUES(1,1,5,1,430,846,3,?,'{\"50100101\":100}',DATE_SUB(UTC_TIMESTAMP(),INTERVAL 61 SECOND),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND),'marching')",[$monster]);$march=$db->lastInsertId();
    $db->execute('UPDATE melusina_progress SET pity_count=4 WHERE player_id=1 AND world_id=1');MarchTick::runForPlayer(1);
    $report=json_decode((string)mq('SELECT data_json FROM battle_reports WHERE march_id=?',[$march]),true);
    mc((mh($march)['items'][$fragment]??0)===1&&($report['items'][$fragment]??0)===1,'real monster victory puts the same quest fragment in haul and battle report');
    $receipt=json_decode((string)mq('SELECT reward_snapshot_json FROM monster_kill_receipts WHERE world_id=1 AND field_monster_id=?',[$monster]),true);
    mc(($receipt['items'][$fragment]??0)===1,'monster kill audit receipt includes the actual quest reward');
    mc((int)mq('SELECT COUNT(*) FROM melusina_drop_events WHERE world_id=1 AND event_key=?',['monster:'.$monster])===1,'monster victory has one persistent quest event receipt');
    $db->execute("INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current,monster_type)VALUES(1,20209901,434,846,1000000,'solo')");$lostMonster=$db->lastInsertId();
    $db->execute("INSERT INTO marches(player_id,world_id,march_type,origin_city_id,target_x,target_y,target_type,target_id,troops_json,departure_time,arrival_time,state)VALUES(1,1,5,1,434,846,3,?,'{\"50100101\":1}',DATE_SUB(UTC_TIMESTAMP(),INTERVAL 61 SECOND),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND),'marching')",[$lostMonster]);$lostMarch=$db->lastInsertId();MarchTick::runForPlayer(1);
    mc(empty(mh($lostMarch)['items'][$fragment])&&(int)mq('SELECT COUNT(*) FROM melusina_drop_events WHERE event_key=?',['monster:'.$lostMonster])===0,'lost monster fight earns neither fragment nor pity');
    $db->execute("INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current,monster_type,expires_at)VALUES(1,20209901,438,846,1,'solo',DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 SECOND))");$expiredMonster=$db->lastInsertId();
    $db->execute("INSERT INTO marches(player_id,world_id,march_type,origin_city_id,target_x,target_y,target_type,target_id,troops_json,departure_time,arrival_time,state)VALUES(1,1,5,1,438,846,3,?,'{\"50100101\":100}',DATE_SUB(UTC_TIMESTAMP(),INTERVAL 61 SECOND),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND),'marching')",[$expiredMonster]);$expiredMarch=$db->lastInsertId();MarchTick::runForPlayer(1);
    mc(empty(mh($expiredMarch)['items'][$fragment])&&(int)mq('SELECT COUNT(*) FROM melusina_drop_events WHERE event_key=?',['monster:'.$expiredMonster])===0,'monster expired before arrival earns no quest attempt');
    $db->execute("INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current,monster_type,expires_at)VALUES(1,20209901,442,846,1,'solo',DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND))");$expiredSource=$db->lastInsertId();
    $sources=M::status(1,1)['sources'];mc(count($sources)>0&&!array_filter($sources,fn($source)=>!M::inCanton($source['x'],$source['y'])),'nearest suggested sources use authoritative canton geometry');
    mc(!array_filter($sources,fn($source)=>$source['type']==='monster'&&$source['id']===$expiredSource),'expired monsters are excluded from suggested sources');
    I::addItems(1,$fragment,6,1);$fragmentsBefore=I::quantity(1,$fragment,1);$keysBefore=M::keyCount(1,1);$workers=[];
    $config=sys_get_temp_dir().'/'.mq('SELECT DATABASE()');
    foreach([1,2] as $attempt){
        $process=proc_open([PHP_BINARY,__FILE__,'--craft-worker',$config,'concurrent-craft-request'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,ROOT_DIR,null,['bypass_shell'=>true]);
        if(!is_resource($process))throw new RuntimeException('Craft worker did not start.');fclose($pipes[0]);$workers[]=[$process,$pipes];
    }
    foreach($workers as [$process,$pipes]){$output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($process);if($code!==0||$error!==''||!(json_decode($output,true)['ok']??false))throw new RuntimeException($error?:$output);}
    mc(I::quantity(1,$fragment,1)===$fragmentsBefore-3&&M::keyCount(1,1)===$keysBefore+1,'simultaneous craft replays consume and create exactly once');
    echo "ALL MELUSINA PROGRESS CHECKS PASSED\n";
}finally{if($fixture)$fixture->close();}
