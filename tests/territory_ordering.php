<?php
declare(strict_types=1);
/** Independent equivalence tests: frequent ticks, delayed batches and parallel workers. */
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));date_default_timezone_set('UTC');require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();require __DIR__.'/Support/FeatureDatabase.php';
use Conquer\Db\Connection;
use Conquer\Game\World\{WorldContext,WorldMapProfile};
use Conquer\Game\Territory\{TerritoryService as T,TerritoryRules};
use Conquer\Game\City\{CityState,TroopData};
use Conquer\Game\Research\{BuffEngine,ResearchEffects};
use Conquer\Game\March\{MarchSpeed,PvpRules};

if(($argv[1]??'')==='--worker'){
    $dir=realpath($argv[2]??'');if(!$dir||dirname($dir)!==realpath(sys_get_temp_dir())||!preg_match('/^conquer_feature_test_[a-f0-9]{12}$/D',basename($dir)))exit(2);
    Connection::init($dir);$world=(int)$argv[3];WorldContext::bind($world);
    try{if($argv[4]==='tick'){T::tick($world,(int)$argv[5],1000);$result=['tick'=>true];}else{$body=json_decode($argv[6],true,32,JSON_THROW_ON_ERROR);$result=T::action((int)$argv[5],$body,$world);}echo json_encode(['ok'=>true,'result'=>$result]);}
    catch(Throwable $e){echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'class'=>$e::class]);}exit;
}
function orderCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);echo "PASS $message\n";}
function orderCommand(int $world,int $pid,string $action,array $fields=[]):array{WorldContext::bind($world);return T::action($pid,['action'=>$action,'world_id'=>$world,'expected_world_id'=>$world,'request_id'=>bin2hex(random_bytes(16))]+$fields,$world);}
function orderCharm(int $world,int $pid,string $category,int $expires,int $bonus=100):void{Connection::getInstance()->execute("INSERT INTO player_charms_active(player_id,world_id,stat_category,grade,charm_code,bonus_pct,activated_at,expires_at)VALUES(?,?,?,'normal',1,?,UTC_TIMESTAMP(),?)",[$pid,$world,$category,$bonus,T::date($expires)]);}
function orderBattleAt(int $id,int $at,int $returnSeconds=5):void{$db=Connection::getInstance();$db->execute("UPDATE rallies SET status='marching',launch_at=?,arrival_time=?,return_time=? WHERE id=?",[T::date($at-5),T::date($at),T::date($at+$returnSeconds),$id]);$db->execute("UPDATE territory_campaigns SET status='marching' WHERE rally_id=?",[$id]);}
function orderResult(int $id):array{return json_decode(Connection::getInstance()->query('SELECT result_json FROM rallies WHERE id=?',[$id])->fetchColumn(),true);}
function orderFixture(int $world):array{
    $db=Connection::getInstance();$offset=$world*100;$code=(int)array_key_first(TroopData::all());
    $db->execute("INSERT INTO worlds(id,name,slug,status,map_size)VALUES(?,?,?,'running',256)",[$world,'Ordering '.$world,'ordering-'.$world]);WorldMapProfile::configureEmptyWorld($world);WorldContext::bind($world);T::ensureWorld($world);
    for($i=1;$i<=8;$i++){$pid=$offset+$i;$db->execute('INSERT INTO players(id,username,email,password_hash)VALUES(?,?,?,?)',[$pid,'Order'.$pid,'order'.$pid.'@invalid.test','unused']);$db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,castle_level,food,lumber,stone,gold,last_resource_update)VALUES(?,?,?,'Order fixture',?,500,10,1000000,1000000,1000000,1000000,UTC_TIMESTAMP())",[$pid,$pid,$world,300+$i*6]);foreach(CityState::BUILDING_CODES as$b)$db->execute('INSERT INTO city_buildings(city_id,building_code,level)VALUES(?,?,10)',[$pid,$b]);$db->execute('INSERT INTO city_troops(city_id,troop_code,count)VALUES(?,?,10000)',[$pid,$code]);}
    foreach([[1,1],[2,3],[3,4]]as[$n,$leader])$db->execute('INSERT INTO alliances(id,world_id,name,tag,leader_id)VALUES(?,?,?,?,?)',[$offset+$n,$world,'Order alliance '.($offset+$n),'O'.($offset+$n),$offset+$leader]);
    foreach([[1,1,'leader'],[1,2,'officer'],[2,3,'leader'],[3,4,'leader'],[1,5,'member'],[1,6,'member'],[2,7,'officer'],[2,8,'officer']]as[$alliance,$player,$role])$db->execute('INSERT INTO alliance_members(world_id,alliance_id,player_id,role)VALUES(?,?,?,?)',[$world,$offset+$alliance,$offset+$player,$role]);
    T::saveProfile($world,['pvp_window_start_hour_utc'=>0,'pvp_window_hours'=>24,'npc_troops'=>['commune'=>10,'canton'=>20,'crown'=>20],'crown_anchor'=>gmdate('Y-m-d 00:00:00'),'crown_period_days'=>1,'crown_duration_hours'=>24],1);
    return ['world'=>$world,'offset'=>$offset,'code'=>$code];
}
/** Only event times are simulated; public commands reserve every participating army. */
function orderCombat(array $f,int $base):string{
    extract($f);$db=Connection::getInstance();$target=$db->query("SELECT id FROM territory_targets WHERE world_id=? AND benefit_type='food' ORDER BY id LIMIT 1",[$world])->fetchColumn();
    $db->execute('UPDATE territory_targets SET owner_alliance_id=?,owned_since=?,last_income_at=?,ownership_seq=1 WHERE world_id=? AND id=?',[$offset+1,T::date($base),T::date($base),$world,$target]);
    $guards=[];foreach([[2,200,10],[5,300,30],[6,100,60]]as[$who,$count,$arrival]){$g=orderCommand($world,$offset+$who,'reinforce',['target_id'=>$target,'city_id'=>$offset+$who,'troops'=>[$code=>$count]]);$guards[]=[$g['garrison_id'],$arrival];}
    $rallies=[];foreach([[3,100,20],[4,2000,40]]as[$who,$count,$arrival]){$r=orderCommand($world,$offset+$who,'start',['target_id'=>$target,'city_id'=>$offset+$who,'troops'=>[$code=>$count],'rally_minutes'=>1]);$rallies[]=[$r['rally_id'],$arrival];}
    foreach($guards as[$id,$arrival])$db->execute('UPDATE territory_garrisons SET departure_at=?,arrival_at=?,travel_seconds=? WHERE id=?',[T::date($base),T::date($base+$arrival),$arrival,$id]);
    foreach($rallies as[$id,$arrival]){$db->execute("UPDATE rallies SET status='marching',launch_at=?,arrival_time=?,return_time=? WHERE id=?",[T::date($base+$arrival-5),T::date($base+$arrival),T::date($base+$arrival+5),$id]);$db->execute("UPDATE territory_campaigns SET status='marching' WHERE rally_id=?",[$id]);}
    return $target;
}
function orderSnapshot(array $f):array{
    extract($f);$db=Connection::getInstance();$result=[];
    $result['owners']=$db->query('SELECT id,owner_alliance_id-? AS owner,ownership_seq,income_remainder,owned_since,last_income_at FROM territory_targets WHERE world_id=? AND owner_alliance_id IS NOT NULL ORDER BY id',[$offset,$world])->fetchAll();
    $result['armies']=$db->query('SELECT c.player_id-? AS player,t.troop_code,t.count FROM city_troops t JOIN cities c ON c.id=t.city_id WHERE c.world_id=? ORDER BY c.player_id,t.troop_code',[$offset,$world])->fetchAll();
    $result['wounded']=$db->query('SELECT c.player_id-? AS player,w.troop_code,w.count FROM hospital_wounded w JOIN cities c ON c.id=w.city_id WHERE c.world_id=? ORDER BY c.player_id,w.troop_code',[$offset,$world])->fetchAll();
    $result['garrisons']=$db->query('SELECT player_id-? AS player,target_id,status,troops_json,arrival_at,return_at FROM territory_garrisons WHERE world_id=? ORDER BY player_id,id',[$offset,$world])->fetchAll();
    $result['treasury']=$db->query('SELECT a.id-? AS alliance,COALESCE(t.food,0) food,COALESCE(t.lumber,0) lumber,COALESCE(t.stone,0) stone,COALESCE(t.gold,0) gold FROM alliances a LEFT JOIN alliance_treasury t ON t.alliance_id=a.id WHERE a.world_id=? ORDER BY a.id',[$offset,$world])->fetchAll();
    $result['history']=$db->query('SELECT target_id,old_alliance_id-? AS old_owner,new_alliance_id-? AS new_owner,occurred_at FROM territory_history WHERE world_id=? ORDER BY occurred_at,id',[$offset,$offset,$world])->fetchAll();
    $result['campaigns']=$db->query('SELECT target_id,alliance_id-? AS alliance,status,slot_reserved,resolved_at FROM territory_campaigns WHERE world_id=? ORDER BY id',[$offset,$world])->fetchAll();
    $result['rewards']=$db->query('SELECT player_id-? AS player,alliance_id-? AS alliance,target_id,reward_json,created_at FROM territory_rewards WHERE world_id=? ORDER BY player_id,created_at,id',[$offset,$offset,$world])->fetchAll();
    $result['rallies']=$db->query('SELECT leader_player_id-? AS player,status,launch_at,arrival_time,return_time FROM rallies WHERE world_id=? ORDER BY id',[$offset,$world])->fetchAll();
    $result['reports']=$db->query('SELECT attacker_id-? AS player,outcome,created_at FROM battle_reports WHERE world_id=? ORDER BY created_at,attacker_id,id',[$offset,$world])->fetchAll();
    return $result;
}
function orderParallel(ConquerTests\FeatureDatabase $fixture,array $args):array{
    $dir=(new ReflectionProperty($fixture,'directory'))->getValue($fixture);$jobs=[];
    foreach($args as$arg){$process=proc_open([PHP_BINARY,__FILE__,'--worker',$dir,...array_map('strval',$arg)],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,ROOT_DIR,null,['bypass_shell'=>true]);fclose($pipes[0]);$jobs[]=[$process,$pipes];}
    $answers=[];foreach($jobs as[$process,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);if($exit!==0)throw new RuntimeException($err);$answers[]=json_decode($out,true,128,JSON_THROW_ON_ERROR);}return $answers;
}
function orderCrown(array $f,int $base):void{
    extract($f);$db=Connection::getInstance();$cantons=$db->query("SELECT id FROM territory_targets WHERE world_id=? AND kind='canton' ORDER BY id LIMIT 2",[$world])->fetchAll(PDO::FETCH_COLUMN);
    foreach($cantons as$i=>$id)$db->execute('UPDATE territory_targets SET owner_alliance_id=? WHERE world_id=? AND id=?',[$offset+2+$i,$world,$id]);
    $attacks=[];foreach([[3,'gate',10],[7,'arsenal',20],[8,'throne',30],[4,'gate',99]]as[$who,$objective,$at]){$r=orderCommand($world,$offset+$who,'start',['target_id'=>'crown:krounbuerg','city_id'=>$offset+$who,'troops'=>[$code=>1000],'rally_minutes'=>1,'objective'=>$objective]);$attacks[]=[$r['rally_id'],$at];}
    $db->execute('UPDATE territory_crown_cycles SET starts_at=?,ends_at=? WHERE world_id=?',[T::date($base),T::date($base+100),$world]);$db->execute('UPDATE territory_crown_control c JOIN territory_crown_cycles y ON y.id=c.cycle_id SET c.controlled_since=? WHERE y.world_id=?',[T::date($base),$world]);
    foreach($attacks as[$id,$at]){$db->execute("UPDATE rallies SET status='marching',launch_at=?,arrival_time=?,return_time=? WHERE id=?",[T::date($base+$at-5),T::date($base+$at),T::date($base+$at+20),$id]);$db->execute("UPDATE territory_campaigns SET status='marching' WHERE rally_id=?",[$id]);}
}
function orderBacklog(array $f,int $base):string{
    extract($f);$db=Connection::getInstance();$targets=$db->query("SELECT id FROM territory_targets WHERE world_id=? AND kind='commune' ORDER BY id LIMIT 2",[$world])->fetchAll(PDO::FETCH_COLUMN);[$target,$decoy]=$targets;
    foreach($targets as$id)$db->execute('UPDATE territory_targets SET owner_alliance_id=?,owned_since=?,last_income_at=?,ownership_seq=1 WHERE world_id=? AND id=?',[$offset+1,T::date($base),T::date($base),$world,$id]);
    // One legitimate one-troop garrison per additional synthetic player creates a
    // background arrival backlog. None of these armies defends the attacked target.
    $players=[];$cities=[];$members=[];$guards=[];$p=[];$c=[];$m=[];$g=[];
    for($i=1;$i<=1000;$i++){$id=$world*100000+$i;$players[]='(?,?,?,?)';array_push($p,$id,'Backlog'.$id,'backlog'.$id.'@invalid.test','unused');$cities[]="(?,?,?,'Backlog fixture',?,?)";array_push($c,$id,$id,$world,100+($i%100)*5,100+intdiv($i,100)*5);$members[]="(?,?,?,'member')";array_push($m,$world,$offset+1,$id);$guards[]="(?,'luxembourg',?,?,?,?,?,'inbound',?,?,10)";array_push($g,$world,$decoy,$offset+1,$id,$id,json_encode([$code=>1]),T::date($base),T::date($base+10));}
    $db->execute('INSERT INTO players(id,username,email,password_hash)VALUES'.implode(',',$players),$p);$db->execute('INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y)VALUES'.implode(',',$cities),$c);$db->execute('INSERT INTO alliance_members(world_id,alliance_id,player_id,role)VALUES'.implode(',',$members),$m);$db->execute('INSERT INTO territory_garrisons(world_id,continent_id,target_id,alliance_id,player_id,city_id,troops_json,status,departure_at,arrival_at,travel_seconds)VALUES'.implode(',',$guards),$g);
    $guard=orderCommand($world,$offset+2,'reinforce',['target_id'=>$target,'city_id'=>$offset+2,'troops'=>[$code=>200]]);$rally=orderCommand($world,$offset+3,'start',['target_id'=>$target,'city_id'=>$offset+3,'troops'=>[$code=>100],'rally_minutes'=>1]);
    $db->execute('UPDATE territory_garrisons SET departure_at=?,arrival_at=?,travel_seconds=10 WHERE id=?',[T::date($base),T::date($base+10),$guard['garrison_id']]);$db->execute("UPDATE rallies SET status='marching',launch_at=?,arrival_time=?,return_time=? WHERE id=?",[T::date($base+15),T::date($base+20),T::date($base+25),$rally['rally_id']]);$db->execute("UPDATE territory_campaigns SET status='marching' WHERE rally_id=?",[$rally['rally_id']]);return $target;
}
$fixture=new ConquerTests\FeatureDatabase();
try{
    $db=Connection::getInstance();$base=time()+3600;
    $fast=orderFixture(2);$late=orderFixture(3);$parallel=orderFixture(4);$bounded=orderFixture(5);
    foreach([$fast,$late,$parallel,$bounded]as$f)orderCombat($f,$base);
    foreach([10,20,25,30,40,45,50,60,70,80,100]as$at)T::tick(2,$base+$at,1000);
    $expected=orderSnapshot($fast);orderCheck((int)$expected['owners'][0]['owner']===3,'reinforcements repel the first attacker before a stronger rival captures the commune');
    orderCheck(array_column($expected['garrisons'],'status')===['returned','returned','returned'],'active and still travelling displaced garrisons return all survivors');
    T::tick(3,$base+100,1000);$actual=orderSnapshot($late);orderCheck($actual===$expected,'one delayed tick matches every owner, troop, wound, return, treasury amount, report and reward from frequent ticks');
    $answers=orderParallel($fixture,[[4,'tick',$base+100],[4,'tick',$base+100]]);orderCheck($answers[0]['ok']&&$answers[1]['ok']&&orderSnapshot($parallel)===$expected,'two real concurrent processors settle armies, ownership and money exactly once');
    for($i=0;$i<20;$i++)T::tick(5,$base+100,1);orderCheck(orderSnapshot($bounded)===$expected,'one-event catch-up batches preserve the same chronological outcome and total income');
    T::tick(3,$base+100,1000);orderCheck(orderSnapshot($late)===$actual,'replaying a completed delayed interval changes no army or receipt');
    $crownFast=orderFixture(6);$crownLate=orderFixture(7);foreach([$crownFast,$crownLate]as$f)orderCrown($f,$base);
    foreach([10,20,30,40,50,99,100,120]as$at)T::tick(6,$base+$at,1000);T::tick(7,$base+120,1000);
    $normalizeCrown=static function(array $f)use($db):array{$rows=$db->query('SELECT s.alliance_id-? AS alliance,s.control_seconds,s.objectives_json FROM territory_crown_scores s JOIN territory_crown_cycles c ON c.id=s.cycle_id WHERE c.world_id=? ORDER BY s.alliance_id',[$f['offset'],$f['world']])->fetchAll();return ['owner'=>(int)T::target($f['world'],'crown:krounbuerg')['owner_alliance_id']-$f['offset'],'scores'=>$rows,'armies'=>orderSnapshot($f)];};
    $crownResult=$normalizeCrown($crownFast);orderCheck($crownResult['owner']===2&&array_column($crownResult['scores'],'control_seconds')===[239,1],'three objectives and 239 control seconds win over the rival landing the final attack');
    orderCheck($normalizeCrown($crownLate)===$crownResult,'late processing across the crown deadline yields the identical winner, scores and armies');
    $cap=orderFixture(8);$world=8;$offset=800;$code=$cap['code'];WorldContext::bind(8);T::saveProfile(8,['canton_limit'=>1],2);
    $cantons=$db->query("SELECT id,canton_id FROM territory_targets WHERE world_id=8 AND kind='canton' ORDER BY id LIMIT 2")->fetchAll();foreach($cantons as$c)$db->execute("UPDATE territory_targets SET owner_alliance_id=801 WHERE world_id=8 AND kind='commune' AND canton_id=?",[$c['canton_id']]);
    $commands=[];foreach($cantons as$i=>$c)$commands[]=[8,'action',801+$i,json_encode(['action'=>'start','world_id'=>8,'expected_world_id'=>8,'request_id'=>'ordering_parallel_canton_'.$i,'target_id'=>$c['id'],'city_id'=>801+$i,'troops'=>[$code=>1000],'rally_minutes'=>1])];
    $answers=orderParallel($fixture,$commands);orderCheck(count(array_filter($answers,static fn($r)=>$r['ok']))===1&&(int)$db->query('SELECT COUNT(*) FROM territory_campaigns WHERE world_id=8 AND slot_reserved=1')->fetchColumn()===1,'two simultaneous canton starts share one reserved alpha slot');
    $won=$answers[0]['ok']?0:1;$start=$answers[$won]['result'];$body=['action'=>'cancel','world_id'=>8,'expected_world_id'=>8,'request_id'=>'ordering_cancel_replay_01','rally_id'=>$start['rally_id']];WorldContext::bind(8);$first=T::action(801+$won,$body,8);$retry=T::action(801+$won,$body,8);
    orderCheck($first===$retry&&(int)$db->query('SELECT COUNT(*) FROM territory_campaigns WHERE world_id=8 AND slot_reserved=1')->fetchColumn()===0&&(int)$db->query('SELECT SUM(count) FROM city_troops WHERE city_id IN (801,802)')->fetchColumn()===20000,'cancelling and replaying refunds one army and releases its canton slot exactly once');
    $other=1-$won;orderCommand(8,801+$other,'start',['target_id'=>$cantons[$other]['id'],'city_id'=>801+$other,'troops'=>[$code=>1000],'rally_minutes'=>1]);orderCheck((int)$db->query('SELECT COUNT(*) FROM territory_campaigns WHERE world_id=8 AND slot_reserved=1')->fetchColumn()===1,'a cancelled canton reservation is reusable by the next valid attack');
    $backlogFast=orderFixture(9);$backlogLate=orderFixture(10);$targetFast=orderBacklog($backlogFast,$base);$targetLate=orderBacklog($backlogLate,$base);
    T::tick(9,$base+10,1000);T::tick(9,$base+11,1000);T::tick(9,$base+20,1000);T::tick(10,$base+20,1000);
    orderCheck((int)T::target(9,$targetFast)['owner_alliance_id']===901&&(int)T::target(10,$targetLate)['owner_alliance_id']===1001,'a thousand unrelated arrivals cannot hide the defenders scheduled before a delayed battle');
    $overnight=array_replace(TerritoryRules::defaults(),['pvp_window_start_hour_utc'=>23,'pvp_window_hours'=>4]);$window=TerritoryRules::window($overnight,strtotime('2026-09-29 01:00:00 UTC'));
    orderCheck($window['open']&&$window['start']===strtotime('2026-09-28 23:00:00 UTC')&&$window['end']===strtotime('2026-09-29 03:00:00 UTC'),'announced combat windows remain open across midnight until the actual end');
    orderCheck(TerritoryRules::window($overnight,strtotime('2026-09-28 23:00:00 UTC'))['open']&&!TerritoryRules::window($overnight,strtotime('2026-09-29 03:00:00 UTC'))['open'],'overnight combat windows include the start and exclude the exact end');
    $buffFast=orderFixture(11);$buffLate=orderFixture(12);$buffRallies=[];
    foreach([$buffFast,$buffLate]as$f){
        $world=$f['world'];$pid=$f['offset']+3;$code=$f['code'];WorldContext::bind($world);T::saveProfile($world,['npc_troops'=>['commune'=>95,'canton'=>20,'crown'=>20]],2);
        $target=$db->query("SELECT id,x,y FROM territory_targets WHERE world_id=? AND kind='commune' ORDER BY id LIMIT 1",[$world])->fetch();
        $db->execute('UPDATE cities SET coord_x=?,coord_y=? WHERE id=?',[$target['x']+10,$target['y']+10,$pid]);
        orderCharm($world,$pid,'troops_attack',$base+86400);
        $r=orderCommand($world,$pid,'start',['target_id'=>$target['id'],'city_id'=>$pid,'troops'=>[$code=>100],'rally_minutes'=>1]);$buffRallies[]=$r['rally_id'];
        $db->execute('UPDATE rallies SET launch_at=? WHERE id=?',[T::date($base+1),$r['rally_id']]);
    }
    try{
        // MySQL's session clock simulates the actual worker time independently of
        // the scheduler's event time. Both real launches see the active charm.
        $db->execute('SET timestamp='.($base+1));T::tick(11,$base+1,1000);T::tick(12,$base+1,1000);
        $arrival=T::timestamp($db->query('SELECT arrival_time FROM rallies WHERE id=?',[$buffRallies[0]])->fetchColumn());
        $db->execute('UPDATE player_charms_active SET expires_at=? WHERE player_id IN (1103,1203)',[T::date($arrival+1)]);
        $db->execute('SET timestamp='.$arrival);T::tick(11,$arrival,1000);
        $db->execute('SET timestamp='.($arrival+30));T::tick(12,$arrival+30,1000);
        $outcomes=[];foreach($buffRallies as$id)$outcomes[]=json_decode($db->query('SELECT result_json FROM rallies WHERE id=?',[$id])->fetchColumn(),true)['outcome'];
        orderCheck($outcomes===['attacker_wins','attacker_wins'],'a combat charm expiring after the scheduled battle cannot reverse a delayed result');
    }finally{$db->execute('SET timestamp=0');}
    $join=orderFixture(13);$code=$join['code'];WorldContext::bind(13);T::saveProfile(13,['npc_troops'=>['commune'=>95,'canton'=>20,'crown'=>20]],2);
    $target=$db->query("SELECT id,x,y FROM territory_targets WHERE world_id=13 AND kind='commune' ORDER BY id LIMIT 1")->fetch();
    $db->execute('UPDATE cities SET coord_x=?,coord_y=? WHERE id=1303',[$target['x']+100,$target['y']]);$db->execute('UPDATE cities SET coord_x=?,coord_y=? WHERE id=1307',[$target['x']+105,$target['y']]);
    foreach([1303,1307]as$pid)orderCharm(13,$pid,'march_speed',$base+10);orderCharm(13,1307,'troops_attack',$base+10);
    $r=orderCommand(13,1303,'start',['target_id'=>$target['id'],'city_id'=>1303,'troops'=>[$code=>1],'rally_minutes'=>1]);orderCommand(13,1307,'join',['rally_id'=>$r['rally_id'],'city_id'=>1307,'troops'=>[$code=>100]]);
    $expectedTravel=MarchSpeed::duration(100,MarchSpeed::rally($code,BuffEngine::getBuffs(1303,13),false,1),13);
    $db->execute('UPDATE rallies SET launch_at=? WHERE id=?',[T::date($base+20),$r['rally_id']]);$db->execute('UPDATE rally_participants SET arrival_time=? WHERE rally_id=?',[T::date($base+1),$r['rally_id']]);
    try{$db->execute('SET timestamp='.($base+100));T::tick(13,$base+20,1000);$row=$db->query('SELECT launch_at,arrival_time FROM rallies WHERE id=?',[$r['rally_id']])->fetch();
        orderCheck(T::timestamp($row['arrival_time'])-T::timestamp($row['launch_at'])===$expectedTravel,'march speed is bound for both rally leader and joiner even when the worker launches after buffs expire');
        T::tick(13,T::timestamp($row['arrival_time'])+100,1000);orderCheck(orderResult($r['rally_id'])['outcome']==='attacker_wins','the joining army keeps its own bound combat bonus after expiry');
    }finally{$db->execute('SET timestamp=0');}
    $research=orderFixture(14);$code=$research['code'];$target=$db->query("SELECT id FROM territory_targets WHERE world_id=14 AND kind='commune' ORDER BY id LIMIT 1")->fetchColumn();
    $beforeScore=(int)PvpRules::strength($code,100,ResearchEffects::armyBuffs(BuffEngine::getBuffs(1403,14),[$code=>100],true));
    $r=orderCommand(14,1403,'start',['target_id'=>$target,'city_id'=>1403,'troops'=>[$code=>100],'rally_minutes'=>1]);
    $db->execute("INSERT INTO player_research(player_id,world_id,research_code,level)VALUES(1403,14,'infantry_atk',5)");
    $afterScore=(int)PvpRules::strength($code,100,ResearchEffects::armyBuffs(BuffEngine::getBuffs(1403,14),[$code=>100],true));orderBattleAt($r['rally_id'],$base+20);T::tick(14,$base+50,1000);
    orderCheck($afterScore>$beforeScore&&orderResult($r['rally_id'])['attacker_score']===$beforeScore,'research completed after dispatch does not retroactively change the bound army');
    $defRallies=[];foreach([15,16]as$world){$f=orderFixture($world);$offset=$f['offset'];$code=$f['code'];$target=$db->query("SELECT id FROM territory_targets WHERE world_id=? AND kind='commune' ORDER BY id LIMIT 1",[$world])->fetchColumn();$db->execute('UPDATE territory_targets SET owner_alliance_id=?,owned_since=?,last_income_at=?,ownership_seq=1 WHERE world_id=? AND id=?',[$offset+1,T::date($base),T::date($base),$world,$target]);
        // Choose an army between the unbuffed and buffed guard strengths so
        // the result still detects a lost defense snapshot after catalog changes.
        $unit=TroopData::get($code);$baseUnit=$unit['attack']+.6*$unit['defense']+.2*$unit['hp'];$buffedUnit=$baseUnit+.6*$unit['defense'];
        $attackCount=(int)floor(100*($baseUnit+$buffedUnit)/(2*$baseUnit));
        orderCheck($attackCount*$baseUnit>100*$baseUnit&&$attackCount*$baseUnit<100*$buffedUnit,'defense snapshot fixture straddles the current catalog battle threshold');
        orderCharm($world,$offset+2,'troops_defense',$base+21);$g=orderCommand($world,$offset+2,'reinforce',['target_id'=>$target,'city_id'=>$offset+2,'troops'=>[$code=>100]]);$r=orderCommand($world,$offset+3,'start',['target_id'=>$target,'city_id'=>$offset+3,'troops'=>[$code=>$attackCount],'rally_minutes'=>1]);
        $db->execute('UPDATE territory_garrisons SET departure_at=?,arrival_at=?,travel_seconds=10 WHERE id=?',[T::date($base),T::date($base+10),$g['garrison_id']]);orderBattleAt($r['rally_id'],$base+20);$defRallies[]=$r['rally_id'];
    }
    try{$db->execute('SET timestamp='.($base+20));T::tick(15,$base+20,1000);$db->execute('SET timestamp='.($base+50));T::tick(16,$base+50,1000);orderCheck(orderResult($defRallies[0])['outcome']==='defender_wins'&&orderResult($defRallies[1])['outcome']==='defender_wins','dispatched garrisons retain their own defense snapshot across delayed processing');}finally{$db->execute('SET timestamp=0');}
    $depart=orderFixture(17);$code=$depart['code'];$target=$db->query("SELECT id FROM territory_targets WHERE world_id=17 AND kind='commune' ORDER BY id LIMIT 1")->fetchColumn();$db->execute('UPDATE territory_targets SET owner_alliance_id=1701,owned_since=?,last_income_at=?,ownership_seq=1 WHERE world_id=17 AND id=?',[T::date($base),T::date($base),$target]);
    $g=orderCommand(17,1702,'reinforce',['target_id'=>$target,'city_id'=>1702,'troops'=>[$code=>200]]);$r=orderCommand(17,1703,'start',['target_id'=>$target,'city_id'=>1703,'troops'=>[$code=>1],'rally_minutes'=>1]);orderCommand(17,1707,'join',['rally_id'=>$r['rally_id'],'city_id'=>1707,'troops'=>[$code=>200]]);
    $db->execute('UPDATE territory_garrisons SET departure_at=?,arrival_at=?,travel_seconds=10 WHERE id=?',[T::date($base),T::date($base+10),$g['garrison_id']]);$db->execute('UPDATE rallies SET launch_at=? WHERE id=?',[T::date($base+15),$r['rally_id']]);$db->execute('UPDATE rally_participants SET arrival_time=? WHERE rally_id=?',[T::date($base+1),$r['rally_id']]);T::tick(17,$base+10,1000);
    $db->execute('DELETE FROM alliance_members WHERE world_id=17 AND player_id IN (1702,1707)');T::tick(17,$base+15,1000);$arrival=T::timestamp($db->query('SELECT arrival_time FROM rallies WHERE id=?',[$r['rally_id']])->fetchColumn());T::tick(17,$arrival+10000,1000);
    orderCheck(orderResult($r['rally_id'])['outcome']==='attacker_wins'&&(int)$db->query('SELECT SUM(count) FROM city_troops WHERE city_id IN (1702,1707)')->fetchColumn()===20000&&(int)$db->query("SELECT COUNT(*) FROM territory_garrisons WHERE world_id=17 AND status<>'returned'")->fetchColumn()===0,'bound buffs do not preserve alliance eligibility after a joiner or defender leaves; both armies return intact');
    echo "ALL TERRITORY ORDERING CHECKS PASSED\n";
}finally{$fixture->close();}
