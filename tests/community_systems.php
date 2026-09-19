<?php
declare(strict_types=1);
/** Community behavior and HTTP authorization, entirely inside a disposable local database. */
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();date_default_timezone_set('UTC');
use Conquer\Db\Connection;
use Conquer\Game\Community\CommunityService as Community;
use Conquer\Game\Alliance\AllianceResearchService as Research;
use Conquer\Game\City\BuildingData;
use Conquer\Game\World\WorldContext;

if(($argv[1]??'')==='--worker'){
    $workerRoot=realpath($argv[2]??'');$tempRoot=realpath(sys_get_temp_dir());
    if(!$workerRoot||!$tempRoot||dirname($workerRoot)!==$tempRoot||!preg_match('/^conquer_community_test_[a-f0-9]{12}$/D',basename($workerRoot)))exit(2);
    Connection::init($workerRoot);
    try{$payload=json_decode($argv[4]??'{}',true,24,JSON_THROW_ON_ERROR);if(($payload['action']??'')==='tick'){Community::tick();$result=[];}else$result=Community::action((int)$argv[3],$payload);echo json_encode(['ok'=>true,'result'=>$result],JSON_THROW_ON_ERROR);}
    catch(DomainException $e){echo json_encode(['ok'=>false,'message'=>$e->getMessage()],JSON_THROW_ON_ERROR);}
    exit;
}

function checkCommunity(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);echo 'PASS '.$message."\n";}
function denyCommunity(callable $fn,string $message):void{try{$fn();}catch(DomainException){checkCommunity(true,$message);return;}throw new RuntimeException('Expected rejection: '.$message);}
function command(int $player,string $action,array $body=[],int $world=1):array{return WorldContext::run($world,fn()=>Community::action($player,['action'=>$action,'request_id'=>bin2hex(random_bytes(16))]+$body,$world)['result']);}
function raceCommunity(array $jobs):array{
    global $temp;$workers=[];
    foreach($jobs as[$player,$payload]){$process=proc_open([PHP_BINARY,__FILE__,'--worker',$temp,(string)$player,json_encode($payload,JSON_THROW_ON_ERROR)],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,ROOT_DIR,null,['bypass_shell'=>true]);if(!is_resource($process))throw new RuntimeException('No concurrency worker.');fclose($pipes[0]);$workers[]=[$process,$pipes];}
    $results=[];foreach($workers as[$process,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($process);$json=json_decode($out,true);if($code!==0||!is_array($json))throw new RuntimeException('Worker failed: '.$err.' '.$out);$results[]=$json;}return $results;
}
function httpCommunity(string $path,array|string|null $body=null,bool $auth=true,bool $csrf=true):array{
    global $url,$session,$token;$h=curl_init($url.$path);$headers=['Content-Type: application/json'];if($csrf)$headers[]='X-CSRF-Token: '.$token;
    curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_HTTPHEADER=>$headers,CURLOPT_COOKIE=>$auth?'conquer_session='.$session:'']);
    if($body!==null)curl_setopt_array($h,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>is_array($body)?json_encode($body,JSON_PRESERVE_ZERO_FRACTION):$body]);
    $raw=curl_exec($h);if($raw===false)throw new RuntimeException(curl_error($h));$code=(int)curl_getinfo($h,CURLINFO_HTTP_CODE);curl_close($h);$json=json_decode($raw,true);if(!is_array($json))throw new RuntimeException('Invalid HTTP response: '.substr($raw,0,500));return ['code'=>$code,'json'=>$json];
}
$cfg=require ROOT_DIR.'/config/database.php';if(!in_array($cfg['host']??'', ['127.0.0.1','localhost'],true))exit("Local database only.\n");
$name='conquer_community_test_'.bin2hex(random_bytes(6));$temp=sys_get_temp_dir().DIRECTORY_SEPARATOR.$name;$admin=null;$server=null;$exit=0;
try{
    $source=$cfg['database'];if(!preg_match('/^[a-zA-Z0-9_]+$/D',$source))throw new RuntimeException('Invalid database name.');
    $admin=new PDO('mysql:host='.$cfg['host'].';port='.($cfg['port']??3306).';charset=utf8mb4',$cfg['username'],$cfg['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $admin->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    foreach($admin->query('SHOW TABLES FROM `'.$source.'`')->fetchAll(PDO::FETCH_COLUMN)as$table){if(!preg_match('/^[a-zA-Z0-9_]+$/D',$table))throw new RuntimeException('Invalid table.');$admin->exec('CREATE TABLE `'.$name.'`.`'.$table.'` LIKE `'.$source.'`.`'.$table.'`');}
    mkdir($temp.'/config',0700,true);$cfg['database']=$name;file_put_contents($temp.'/config/database.php',"<?php\nreturn ".var_export($cfg,true).";\n");$db=Connection::init($temp);
    foreach(['0066_community_systems.sql','0068_progression_and_events.sql','0096_private_chat.sql','0098_shared_battle_reports.sql','0101_alliance_territory.sql']as$m)$db->getPdo()->exec(file_get_contents(ROOT_DIR.'/migrations/'.$m));
    $db->execute("INSERT INTO worlds(id,name,slug,status)VALUES(1,'Community Fixture','community-test','running'),(2,'Other Fixture','other-test','running')");
    for($id=1;$id<=38;$id++){
        $db->execute('INSERT INTO players(id,username,email,password_hash,vip_level)VALUES(?,?,?,?,0)',[$id,'Fixture'.$id,'fixture'.$id.'@invalid.test','unused']);
        $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,food,lumber,stone,gold,last_resource_update)VALUES(?,?,?,'Community fixture',?,50,2000000,2000000,2000000,2000000,UTC_TIMESTAMP())",[$id,$id,$id===5?2:1,50+$id]);
        foreach(\Conquer\Game\City\CityState::BUILDING_CODES as$code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level)VALUES(?,?,1)',[$id,$code]);
    }
    $db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id)VALUES(1,1,'First alliance','FST',1),(2,1,'Second alliance','SND',4),(3,2,'Other world','OTH',5)");
    foreach([[1,1,'leader'],[1,2,'member'],[1,3,'vice_leader'],[2,4,'leader'],[3,5,'leader']]as$row)$db->execute('INSERT INTO alliance_members(alliance_id,player_id,role)VALUES(?,?,?)',$row);
    for($id=6;$id<=38;$id++)$db->execute("INSERT INTO alliance_members(alliance_id,player_id,role)VALUES(1,?,'member')",[$id]);
    $db->execute('UPDATE alliance_members m JOIN alliances a ON a.id=m.alliance_id SET m.world_id=a.world_id');
    $db->execute('INSERT INTO alliance_treasury(alliance_id,food,lumber,stone,gold)VALUES(1,10000,10000,10000,10000),(2,10000,10000,10000,10000)');

    $chat=['action'=>'chat.send','channel'=>'world','message'=>'<script>alert(1)</script> & Grüße','request_id'=>'chat_fixture_receipt_0001'];
    $first=Community::action(1,$chat);$repeat=Community::action(1,$chat);checkCommunity($first['result']['id']===$repeat['result']['id']&&(int)$db->query('SELECT COUNT(*) FROM world_chat')->fetchColumn()===1,'chat retries deliver exactly once');
    denyCommunity(fn()=>command(1,'chat.send',['channel'=>'alliance','message'=>'Too fast']),'chat cooldown applies across channels');
    $db->execute('UPDATE world_chat SET created_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 5 SECOND)');command(1,'chat.send',['channel'=>'alliance','message'=>'Only first alliance']);
    $s=Community::state(4);checkCommunity(count($s['alliance_chat'])===0&&count($s['world_chat'])===1,'alliance chat is scoped while world chat is shared');
    $s2=WorldContext::run(2,fn()=>Community::state(5,2));checkCommunity(!$s2['world_chat']&&!$s2['alliance_chat'],'cross-world chat is isolated');
    denyCommunity(fn()=>Community::state(1,2),'state rejects nonexistent world membership');
    denyCommunity(fn()=>Community::action(1,$chat+['unknown'=>'changed']),'reused receipt rejects changed payload');

    $mail=command(1,'mail.send',['player_id'=>2,'subject'=>'Private <subject>','body'=>"Line 1\n<script>private</script>"]);
    checkCommunity(count(Community::state(2)['mail'])===1&&Community::state(2)['unread']===1&&count(Community::state(4)['mail'])===0,'private mail is visible only to sender and recipient');
    denyCommunity(fn()=>command(4,'mail.read',['mail_id'=>$mail['id']]),'outsider cannot mark another letter read');
    command(2,'mail.read',['mail_id'=>$mail['id']]);checkCommunity(Community::state(2)['unread']===0,'recipient can mark mail read');
    denyCommunity(fn()=>command(1,'mail.send',['player_id'=>5,'subject'=>'Cross world','body'=>'No']),'mail cannot cross worlds');
    $db->execute("INSERT INTO admin_operations(operation_id,admin_id,action,payload_hash)VALUES(?,1,'send-gift',?)",[str_repeat('f',32),str_repeat('f',64)]);
    $db->execute("INSERT INTO admin_gifts(operation_id,player_id,world_id,title,message,rewards_json,before_json,after_json)VALUES(?,2,1,'Test gift','Gift message',?, '{}','{}')",[str_repeat('f',32),json_encode(['food'=>500,'gems'=>25,'item_code'=>0,'quantity'=>0])]);
    $gift=Community::state(2)['gifts'][0];checkCommunity($gift['title']==='Test gift'&&$gift['rewards']['food']===500&&!isset($gift['before_json'])&&!Community::state(1)['gifts']&&!WorldContext::run(2,fn()=>Community::state(5,2))['gifts'],'admin gift receipts show safe reward details only to their same-world recipient');

    command(3,'alliance.role',['player_id'=>2,'role'=>'officer']);checkCommunity($db->query('SELECT role FROM alliance_members WHERE player_id=2')->fetchColumn()==='officer','vice leader can promote a lower ranked member');
    denyCommunity(fn()=>command(3,'alliance.role',['player_id'=>1,'role'=>'member']),'vice leader cannot demote leader');
    denyCommunity(fn()=>command(2,'alliance.role',['player_id'=>6,'role'=>'member']),'officer cannot manage ranks');
    denyCommunity(fn()=>command(3,'alliance.role',['player_id'=>2,'role'=>'vice_leader']),'vice leader cannot create peer rank');
    command(1,'alliance.role',['player_id'=>2,'role'=>'member']);

    $db->execute("INSERT INTO building_queue(city_id,building_code,level_to,started_at,finishes_at)VALUES(1,'farm',2,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 300 SECOND))");$queue=$db->lastInsertId();
    denyCommunity(fn()=>command(2,'help.request',['queue_type'=>'building','queue_id'=>$queue]),'request help validates queue ownership');
    $help=command(1,'help.request',['queue_type'=>'building','queue_id'=>$queue]);$again=command(1,'help.request',['queue_type'=>'building','queue_id'=>$queue]);checkCommunity($help['id']===$again['id'],'only one help request can exist per job');
    denyCommunity(fn()=>command(1,'help.give',['help_id'=>$help['id']]),'self-help rejected');
    denyCommunity(fn()=>command(4,'help.give',['help_id'=>$help['id']]),'outsider help rejected');
    $remaining=(int)$db->query('SELECT UNIX_TIMESTAMP(finishes_at) FROM building_queue WHERE id=?',[$queue])->fetchColumn();
    command(2,'help.give',['help_id'=>$help['id']]);denyCommunity(fn()=>command(2,'help.give',['help_id'=>$help['id']]),'same helper cannot reduce same job twice');
    command(3,'help.give',['help_id'=>$help['id']]);command(6,'help.give',['help_id'=>$help['id']]);
    denyCommunity(fn()=>command(7,'help.give',['help_id'=>$help['id']]),'help reduction capped at 30 percent of initial remaining duration');
    $after=(int)$db->query('SELECT UNIX_TIMESTAMP(finishes_at) FROM building_queue WHERE id=?',[$queue])->fetchColumn();checkCommunity($remaining-$after<=90&&$remaining-$after>=89,'bounded help actually shortens the building queue');
    $db->execute("INSERT INTO research_queue(player_id,world_id,research_code,level_to,finishes_at)VALUES(1,1,'food_production',1,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 3600 SECOND))");$rq=$db->lastInsertId();$rh=command(1,'help.request',['queue_type'=>'research','queue_id'=>$rq]);command(2,'help.give',['help_id'=>$rh['id']]);
    checkCommunity((int)$db->query('SELECT reduced_seconds FROM community_help_requests WHERE id=?',[$rh['id']])->fetchColumn()===30,'research help shortens a real research job');
    $db->execute('DELETE FROM alliance_members WHERE player_id=2');denyCommunity(fn()=>command(2,'help.give',['help_id'=>$rh['id']]),'departed helper immediately loses access');$db->execute("INSERT INTO alliance_members(alliance_id,player_id,role)VALUES(1,2,'member')");
    for($i=1;$i<=30;$i++)$db->execute('INSERT INTO community_help_log(request_id,helper_id,seconds_removed)VALUES(?,7,1)',[10000+$i]);
    denyCommunity(fn()=>command(7,'help.give',['help_id'=>$rh['id']]),'daily helper limit enforced');
    $races=raceCommunity([[8,['action'=>'help.give','help_id'=>$rh['id'],'request_id'=>str_repeat('a',32)]],[8,['action'=>'help.give','help_id'=>$rh['id'],'request_id'=>str_repeat('b',32)]]]);
    checkCommunity(count(array_filter($races,fn($r)=>$r['ok']))===1&&(int)$db->query('SELECT COUNT(*) FROM community_help_log WHERE request_id=? AND helper_id=8',[$rh['id']])->fetchColumn()===1,'concurrent distinct requests from one helper shorten a job only once');

    $foodBefore=(int)$db->query('SELECT food FROM cities WHERE id=1')->fetchColumn();$donate=['action'=>'treasury.donate','resource'=>'food','amount'=>500,'request_id'=>'donation_fixture_receipt_001'];
    Community::action(1,$donate);Community::action(1,$donate);checkCommunity((int)$db->query('SELECT food FROM cities WHERE id=1')->fetchColumn()===$foodBefore-500&&(int)$db->query('SELECT food FROM alliance_treasury WHERE alliance_id=1')->fetchColumn()===10500,'donation debit and credit occur exactly once');
    denyCommunity(fn()=>command(1,'treasury.donate',['resource'=>'gems','amount'=>50]),'donations reject unsupported resources');
    denyCommunity(fn()=>command(1,'treasury.donate',['resource'=>'gold','amount'=>1.2]),'donations reject fractional amounts');
    denyCommunity(fn()=>command(2,'research.start',['code'=>'ally_troops_atk']),'research restricted to leadership');
    $research=command(1,'research.start',['code'=>'ally_troops_atk']);denyCommunity(fn()=>command(3,'research.start',['code'=>'ally_food_prod']),'alliance can run only one research project');
    checkCommunity((int)$db->query('SELECT gold FROM alliance_treasury WHERE alliance_id=1')->fetchColumn()===9250,'research atomically debits its cost');
    $db->execute('UPDATE alliance_research_queue SET finishes_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$research['id']]);Community::tick();Community::tick();checkCommunity(Community::bonuses(2)['troops_atk']===.005,'completed alliance research grants real combat fraction once');
    checkCommunity(Community::bonuses(5,2)===[],'alliance combat buffs are world scoped');
    $db->execute("INSERT INTO alliance_research(alliance_id,research_code,level)VALUES(1,'ally_food_prod',1),(1,'ally_march_limit',10)");
    checkCommunity(Community::bonuses(2)['march_limit']===1,'ten levels grant one extra march slot');
    $bonus=Research::getProductionBonuses(1);$normal=BuildingData::getHourlyRate('farm',1,[]);$boosted=BuildingData::getHourlyRate('farm',1,['food_prod_pct'=>$bonus['food_pct']]);checkCommunity(abs($boosted/$normal-1.01)<.00001,'production research is one percent rather than 100 percent');
    $db->execute("INSERT INTO alliance_structures(alliance_id,world_id,structure_type,coord_x,coord_y,placed_by)VALUES(1,1,'center',51,50,1),(1,1,'outpost',80,80,1)");
    $centerBonus=\Conquer\Game\Alliance\AllianceTerritoryService::bonusesAt(2,1,52,50);$outpostBonus=\Conquer\Game\Alliance\AllianceTerritoryService::bonusesAt(2,1,80,80);
    checkCommunity(abs(($centerBonus['gathering_speed']??0)-.10)<.00001&&abs(($centerBonus['troops_atk']??0)-.05)<.00001,'alliance center grants its configured territorial effects');
    checkCommunity(abs(($outpostBonus['troops_hp']??0)-.05)<.00001&&abs(($outpostBonus['troops_def']??0)-.05)<.00001,'outpost grants attack, life and defense without stacking centers');

    denyCommunity(fn()=>command(2,'treaty.propose',['alliance_id'=>2,'relation'=>'nap']),'ordinary members cannot offer treaties');
    denyCommunity(fn()=>command(1,'treaty.propose',['alliance_id'=>3,'relation'=>'nap']),'treaty target must share a world');
    $proposal=command(1,'treaty.propose',['alliance_id'=>2,'relation'=>'nap']);checkCommunity(Community::protectedRelation(1,4)===null,'pending proposal grants no attack protection');
    denyCommunity(fn()=>command(1,'treaty.accept',['proposal_id'=>$proposal['id']]),'proposer cannot accept own treaty');
    command(4,'treaty.accept',['proposal_id'=>$proposal['id']]);checkCommunity(Community::protectedRelation(1,4)==='nap'&&Community::protectedRelation(4,1)==='nap','accepted treaty protects both directions');
    command(1,'treaty.end',['alliance_id'=>2]);checkCommunity(Community::protectedRelation(1,4)===null&&Community::protectedRelation(4,1)===null,'termination clears both directions');
    $proposal=command(1,'treaty.propose',['alliance_id'=>2,'relation'=>'ally']);$db->execute('UPDATE community_treaty_proposals SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$proposal['id']]);Community::tick();denyCommunity(fn()=>command(4,'treaty.accept',['proposal_id'=>$proposal['id']]),'expired proposal cannot be accepted');

    $senderBefore=(int)$db->query('SELECT gold FROM cities WHERE id=1')->fetchColumn();$recipientBefore=(int)$db->query('SELECT gold FROM cities WHERE id=2')->fetchColumn();
    $payload=['action'=>'shipment.send','player_id'=>2,'resource'=>'gold','amount'=>1000,'request_id'=>'shipment_fixture_receipt_001'];
    $delivery=Community::action(1,$payload)['result'];Community::action(1,$payload);checkCommunity((int)$db->query('SELECT gold FROM cities WHERE id=1')->fetchColumn()===$senderBefore-1000&&(int)$db->query('SELECT gold FROM cities WHERE id=2')->fetchColumn()===$recipientBefore,'shipment debits once and credits only after arrival');
    checkCommunity($delivery['duration_seconds']>=60,'shipment has a real minimum travel delay');
    Community::tick();checkCommunity((int)$db->query('SELECT gold FROM cities WHERE id=2')->fetchColumn()===$recipientBefore,'early shipment settlement cannot credit resources');
    $db->execute('UPDATE community_shipments SET arrives_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$delivery['id']]);Community::tick();Community::tick();checkCommunity((int)$db->query('SELECT gold FROM cities WHERE id=2')->fetchColumn()===$recipientBefore+1000,'due shipment settlement credits once across repeated ticks');
    denyCommunity(fn()=>command(1,'shipment.send',['player_id'=>4,'resource'=>'gold','amount'=>10]),'shipments reject non-members');
    denyCommunity(fn()=>command(1,'shipment.send',['player_id'=>5,'resource'=>'gold','amount'=>10]),'shipments reject cross-world cities');
    denyCommunity(fn()=>command(1,'shipment.send',['player_id'=>2,'resource'=>'gold','amount'=>-10]),'shipments reject negative resources');
    for($i=0;$i<5;$i++)command(1,'shipment.send',['player_id'=>2,'resource'=>'gold','amount'=>10]);denyCommunity(fn()=>command(1,'shipment.send',['player_id'=>2,'resource'=>'gold','amount'=>10]),'outgoing shipments capped at five');
    $racingPayload=['action'=>'shipment.send','player_id'=>1,'resource'=>'gold','amount'=>100,'request_id'=>str_repeat('c',32)];
    $raceBefore=(int)$db->query('SELECT gold FROM cities WHERE id=2')->fetchColumn();$races=raceCommunity([[2,$racingPayload],[2,$racingPayload]]);
    checkCommunity($races[0]['ok']&&$races[1]['ok']&&$races[0]['result']['result']['id']===$races[1]['result']['result']['id']&&(int)$db->query('SELECT gold FROM cities WHERE id=2')->fetchColumn()===$raceBefore-100,'concurrent retried shipment produces one debit and one shipment');
    $raceDelivery=$races[0]['result']['result']['id'];$raceCredit=(int)$db->query('SELECT gold FROM cities WHERE id=1')->fetchColumn();$db->execute('UPDATE community_shipments SET arrives_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$raceDelivery]);raceCommunity([[1,['action'=>'tick']],[2,['action'=>'tick']]]);
    checkCommunity((int)$db->query('SELECT gold FROM cities WHERE id=1')->fetchColumn()===$raceCredit+100,'two settlement workers credit an arrived shipment only once');
    $db->execute('UPDATE cities SET gold=100,last_resource_update=UTC_TIMESTAMP() WHERE id=9');$db->execute("UPDATE city_buildings SET level=0 WHERE city_id=9 AND building_code='gold_mine'");
    $races=raceCommunity([[9,['action'=>'treasury.donate','resource'=>'gold','amount'=>100,'request_id'=>str_repeat('d',32)]],[9,['action'=>'treasury.donate','resource'=>'gold','amount'=>100,'request_id'=>str_repeat('e',32)]]]);
    checkCommunity(count(array_filter($races,fn($r)=>$r['ok']))===1&&(int)$db->query('SELECT gold FROM cities WHERE id=9')->fetchColumn()===0,'concurrent donations cannot double-spend the same balance');
    $races=raceCommunity([[1,['action'=>'research.start','code'=>'ally_troops_def','request_id'=>str_repeat('1',32)]],[3,['action'=>'research.start','code'=>'ally_troops_hp','request_id'=>str_repeat('2',32)]]]);
    checkCommunity(count(array_filter($races,fn($r)=>$r['ok']))===1&&(int)$db->query('SELECT COUNT(*) FROM alliance_research_queue WHERE alliance_id=1 AND is_processed=0')->fetchColumn()===1,'concurrent leadership commands create one research queue');

    $session=bin2hex(random_bytes(32));$token=bin2hex(random_bytes(32));$db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,user_agent,expires_at)VALUES(1,?,?,'127.0.0.1','Community QA',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))",[$session,$token]);
    $router='<?php declare(strict_types=1); define("ROOT_DIR",'.var_export(ROOT_DIR,true).'); require ROOT_DIR."/src/Autoloader.php";(new \\Conquer\\Autoloader(ROOT_DIR."/src"))->register(); date_default_timezone_set("UTC"); \\Conquer\\Db\\Connection::init(__DIR__); if($_SERVER["REQUEST_METHOD"]==="POST")\\Conquer\\Api\\Handlers\\CommunityHandler::action([]); else \\Conquer\\Api\\Handlers\\CommunityHandler::state([]);';
    file_put_contents($temp.'/router.php',$router);$socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$err);$address=stream_socket_get_name($socket,false);fclose($socket);$url='http://'.$address;
    $server=proc_open([PHP_BINARY,'-S',$address,'-t',$temp,$temp.'/router.php'],[0=>['pipe','r'],1=>['file',$temp.'/server.log','a'],2=>['file',$temp.'/server.log','a']],$pipes,$temp,null,['bypass_shell'=>true]);if(!is_resource($server))throw new RuntimeException('No test HTTP server.');fclose($pipes[0]);usleep(200000);
    checkCommunity(httpCommunity('/api/community/state',null,false)['code']===401,'HTTP state requires authentication');
    checkCommunity(httpCommunity('/api/community/action',['action'=>'mail.read','mail_id'=>$mail['id'],'request_id'=>str_repeat('a',16)],true,false)['code']===403,'HTTP mutation enforces CSRF');
    checkCommunity(httpCommunity('/api/community/action','{broken')['code']===400,'HTTP malformed JSON rejected');
    checkCommunity(httpCommunity('/api/community/action',['action'=>'shipment.send','player_id'=>2,'resource'=>'gold','amount'=>1.0,'request_id'=>str_repeat('b',16)])['code']===422,'HTTP decimals cannot bypass integer validation');
    checkCommunity(httpCommunity('/api/community/state?world_id[]=1')['code']===422,'HTTP array world input rejected');
    checkCommunity(httpCommunity('/api/community/state?world_id=2')['code']===409,'HTTP world spoofing rejected');
    checkCommunity(httpCommunity('/api/community/state')['code']===200,'HTTP community read succeeds for authenticated player');
    echo "ALL COMMUNITY CHECKS PASSED (isolated database).\n";
}catch(Throwable $e){fwrite(STDERR,'FAIL '.$e->getMessage()."\n".$e->getTraceAsString()."\n");$exit=1;}
finally{
    if(is_resource($server)){proc_terminate($server);proc_close($server);}
    if($admin&&preg_match('/^conquer_community_test_[a-f0-9]{12}$/D',$name))$admin->exec('DROP DATABASE IF EXISTS `'.$name.'`');
    $resolved=realpath($temp);$parent=realpath(sys_get_temp_dir());if($resolved&&$parent&&str_replace('\\','/',$resolved)===str_replace('\\','/',$parent).'/'.$name&&preg_match('/^conquer_community_test_[a-f0-9]{12}$/D',basename($resolved))){foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolved,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST)as$item){if($item->isDir()&&!$item->isLink())rmdir($item->getPathname());else unlink($item->getPathname());}rmdir($resolved);}
}
exit($exit);
