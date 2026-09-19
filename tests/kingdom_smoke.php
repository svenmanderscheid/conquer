<?php
declare(strict_types=1);
/** Local HTTP integration: separate test accounts, actual auth/CSRF and database persistence. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR . '/src'))->register();
date_default_timezone_set('UTC');
\Conquer\Db\Connection::init(ROOT_DIR);
$db = \Conquer\Db\Connection::getInstance();
$base = rtrim($argv[1] ?? 'http://localhost/conquer', '/');
if (!in_array(parse_url($base, PHP_URL_HOST), ['localhost','127.0.0.1'], true)) { exit("Local test hosts only.\n"); }
$actors = [];
$suffix = bin2hex(random_bytes(4));
function verify(bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } echo "PASS $message\n"; }
function req(int $actor, string $path, ?array $body = null, bool $form = false, bool $token = true): array {
    global $actors,$base;
    $ch=curl_init($base.$path);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEJAR=>$actors[$actor]['cookie'],CURLOPT_COOKIEFILE=>$actors[$actor]['cookie'],CURLOPT_TIMEOUT=>20]);
    if ($body!==null) {
        curl_setopt($ch,CURLOPT_POST,true);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$form?http_build_query($body):json_encode($body));
        curl_setopt($ch,CURLOPT_HTTPHEADER,$form?[]:array_values(array_filter(['Content-Type: application/json',$token?'X-CSRF-Token: '.($actors[$actor]['csrf']??''):null])));
    }
    $text=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    return ['status'=>$status,'json'=>json_decode((string)$text,true),'text'=>$text];
}
function action(int $actor, string $name, array $body = [], int $expect = 200): array {
    $r=req($actor,'/api/kingdom/action',['action'=>$name]+$body);
    verify($r['status']===$expect,"$name HTTP $expect".($r['status']!==$expect?' response '.$r['text']:''));
    verify(is_array($r['json']), "$name returns valid JSON" . (is_array($r['json'])?'':$r['text']));
    return $r['json']['data']??[];
}
function authenticate(int $actor, array $body): array {
    // Parallel local smoke tests share the real IP login limiter; wait for its normal reset.
    for ($attempt=0;$attempt<14;$attempt++) {
        $r=req($actor,'/auth/local',$body,true);
        if ($r['status']!==200 || !str_contains($r['text'],'Zu viele Versuche')) { return $r; }
        if ($attempt===0) { echo "WAIT normal login-rate reset for parallel local tests\n"; }
        sleep(5);
    }
    return $r;
}
try {
    for($i=0;$i<3;$i++) {
        $actors[$i]=['cookie'=>tempnam(sys_get_temp_dir(),'kingdom-test-'),'name'=>'Kingdom'.$suffix.$i,'password'=>bin2hex(random_bytes(12))];
        $page=req($i,'/');preg_match('/name="csrf" value="([a-f0-9]+)"/',$page['text'],$m);
        verify(!empty($m[1]),'local registration token');
        $registration=authenticate($i,['mode'=>'register','username'=>$actors[$i]['name'],'password'=>$actors[$i]['password'],'alpha_key'=>'LOCAL-ALPHA-ACCESS-2026-KEY1','csrf'=>$m[1]]);
        verify($registration['status']===302,'isolated test kingdom registered'.($registration['status']===302?'':strip_tags($registration['text'])));
        $g=req($i,'/api/game/state');verify($g['status']===200,'base state available');
        $actors[$i]['csrf']=$g['json']['data']['player']['csrf'];
        $s=req($i,'/api/kingdom/state');verify($s['status']===200,'supporting state available: '.($s['status']!==200?$s['text']:''));
        $actors[$i]['id']=$s['json']['data']['profile']['id'];
        $actors[$i]['city_id']=$g['json']['data']['city']['id'];
    }
    $s=req(0,'/api/kingdom/state')['json']['data'];
    verify(isset($s['profile'],$s['avatars'],$s['settings'],$s['inventory'],$s['quests'],$s['hospital'],$s['treasures'],$s['rankings'],$s['arena'],$s['queues']),'all menu read models exist');
    $welcomeTreasure=array_values(array_filter(
        $s['treasures']['items'],
        static fn(array $item): bool => (int)($item['treasure_code']??0)===60100002,
    ));
    verify(
        count($s['inventory'])===4
        && count($welcomeTreasure)===1
        && (int)($welcomeTreasure[0]['fragments']??0)>=10
        && ($welcomeTreasure[0]['is_unlocked']??false)===true,
        'one-time welcome supplies available',
    );
    $s2=req(0,'/api/kingdom/state')['json']['data'];
    verify($s2['inventory']===$s['inventory'],'polling cannot repeat welcome rewards');
    verify(req(0,'/api/kingdom/action',['action'=>'profile.save','display_name'=>'Forged','avatar'=>'knight','bio'=>''],false,false)['status']===403,'missing CSRF cannot edit profile');
    action(0,'profile.save',['display_name'=>'Waldhüter Ära','avatar'=>'archer','bio'=>'Für gemeinsame Feldzüge.']);
    action(0,'profile.save',['display_name'=>'<script>x</script>','avatar'=>'archer','bio'=>''],422);
    $public=req(1,'/api/kingdom/state?player_id='.$actors[0]['id'])['json']['data'];
    verify($public['profile']['display_name']==='Waldhüter Ära' && !$public['profile']['is_self'],'public profile uses saved display name');
    verify($db->query('SELECT username FROM players WHERE id=?',[$actors[0]['id']])->fetchColumn()===$actors[0]['name'],'display name edits preserve login identity');
    action(0,'settings.save',['reduced_motion'=>true,'compact_numbers'=>false,'confirm_actions'=>true]);
    verify(req(0,'/api/kingdom/state')['json']['data']['settings']['reduced_motion']===true,'settings persist');
    $a=action(0,'alliance.create',['name'=>'Cooperative '.$suffix,'tag'=>strtoupper(substr($suffix,0,6)),'description'=>'Wir verteidigen die Welt.'])['result']['alliance_id'];
    action(1,'alliance.join',['alliance_id'=>$a]);
    action(1,'alliance.update',['description'=>'Unauthorized'],422);
    action(0,'alliance.leave',[],422);
    action(0,'alliance.update',['description'=>'Zwei Allianzen, ein Ziel.']);
    action(1,'alliance.donate',['resource'=>'gold','amount'=>100]);
    $aState=req(0,'/api/kingdom/state')['json']['data']['alliance'];
    verify($aState['member_count']===2 && $aState['treasury']['gold']===100,'alliance roster and donated gold persist');
    action(1,'alliance.donate',['resource'=>'gold','amount'=>-100],422);
    action(1,'alliance.donate',['resource'=>'gold','amount'=>1000000],422);
    action(2,'alliance.kick',['player_id'=>$actors[1]['id']],422);
    action(0,'alliance.transfer',['player_id'=>$actors[1]['id']]);
    action(0,'alliance.kick',['player_id'=>$actors[1]['id']],422);
    action(1,'alliance.kick',['player_id'=>$actors[0]['id']]);
    verify(req(0,'/api/kingdom/state')['json']['data']['alliance']===null,'leadership transfer enforces new permissions');
    action(0,'alliance.join',['alliance_id'=>$a]);
    action(0,'alliance.leave');
    $pid=$actors[0]['id'];$city=$actors[0]['city_id'];
    $gems=(int)$db->query('SELECT gems FROM players WHERE id=?',[$pid])->fetchColumn();
    action(0,'quest.claim',['quest_code'=>'login_daily']);
    action(0,'quest.claim',['quest_code'=>'login_daily'],422);
    verify((int)$db->query('SELECT gems FROM players WHERE id=?',[$pid])->fetchColumn()===$gems+5,'daily gems credited exactly once');
    $food=(int)$db->query('SELECT food FROM cities WHERE id=?',[$city])->fetchColumn();
    action(0,'inventory.use',['item_code'=>10101001]);
    verify((int)$db->query('SELECT food FROM cities WHERE id=?',[$city])->fetchColumn()>=$food+10000,'resource pack credits its full amount');
    action(0,'inventory.use',['item_code'=>10101001],422);
    $chest=action(0,'inventory.use',['item_code'=>10105001]);
    verify(count($chest['result']['drops'])===3,'silver chest grants three real drops');
    action(0,'inventory.use',['item_code'=>10105001],422);
    action(0,'quest.claim',['quest_code'=>'open_chest_1']);
    action(0,'treasure.equip',['treasure_code'=>60100002,'slot'=>1]);
    $bonus=\Conquer\Game\Research\BuffEngine::getBuffs($pid);
    verify(($bonus['lumber_production']??0)>=.02,'equipped relic affects actual production bonus');
    action(0,'treasure.equip',['treasure_code'=>60100002,'slot'=>6],422);
    action(0,'treasure.unequip',['treasure_code'=>60100002]);
    action(0,'hospital.heal',[],422);
    \Conquer\Game\Hospital\HospitalService::addWounded($city,[50100101=>5]);
    $healing=action(0,'hospital.heal',['troops'=>[50100101=>5],'operation_key'=>'hospital_smoke_start']);
    verify(($healing['result']['resources_spent']??[])===['food'=>125,'lumber'=>75,'stone'=>0,'gold'=>0],'healing charges troop resource costs at start');
    verify((int)$db->query('SELECT SUM(healing_count) FROM hospital_wounded WHERE city_id=?',[$city])->fetchColumn()===5,'resource payment starts the treatment timer');
    $db->execute('UPDATE hospital_wounded SET healing_ends_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE city_id=? AND healing_count>0',[$city]);
    \Conquer\Game\Hospital\HospitalService::processHealed($city);
    verify((int)$db->query('SELECT count FROM city_troops WHERE city_id=? AND troop_code=50100101',[$city])->fetchColumn()===5,'healed troops return exactly once');
    action(0,'hospital.heal',[],422);
    // Training/research fixtures owned by the test accounts prove queue authorization and schema routing.
    $db->execute("INSERT INTO research_queue (player_id,world_id,research_code,level_to,finishes_at) VALUES (?,1,'food_production',1,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))",[$pid]);
    $qid=$db->lastInsertId();
    action(1,'inventory.use',['item_code'=>10103001,'queue_type'=>'research','queue_id'=>$qid],422);
    $before=$db->query('SELECT finishes_at FROM research_queue WHERE id=?',[$qid])->fetchColumn();
    action(0,'inventory.use',['item_code'=>10103001,'queue_type'=>'research','queue_id'=>$qid]);
    $after=$db->query('SELECT finishes_at FROM research_queue WHERE id=?',[$qid])->fetchColumn();
    verify(strtotime($before)-strtotime($after)===300,'research speedup deducts exactly five minutes');
    \Conquer\Game\Inventory\InventoryService::addItems($pid,10102001,2);
    action(0,'inventory.use',['item_code'=>10102001]);
    $multiplier=\Conquer\Game\Buff\ActiveBuffService::getMultiplier($pid,'production_boost');
    verify($multiplier>1,'production booster changes actual production multiplier');
    action(0,'inventory.use',['item_code'=>10102001]);
    verify(\Conquer\Game\Buff\ActiveBuffService::getMultiplier($pid,'production_boost')===$multiplier,'repeated boost extends duration without exponential stacking');
    \Conquer\Game\Inventory\InventoryService::addItems($pid,10102021,1);
    action(0,'inventory.use',['item_code'=>10102021]);
    verify((\Conquer\Game\Research\BuffEngine::getBuffs($pid)['construction_speed']??0)>0,'construction booster changes actual building bonus');
    $db->execute('INSERT INTO city_troops (city_id,troop_code,count) VALUES (?,50100101,3) ON DUPLICATE KEY UPDATE count=3',[$actors[1]['city_id']]);
    $challenge=action(0,'arena.challenge',['opponent_id'=>$actors[1]['id']])['result']['challenge_id'];
    action(2,'arena.accept',['challenge_id'=>$challenge],422);
    action(0,'arena.accept',['challenge_id'=>$challenge],422);
    $battle=action(1,'arena.accept',['challenge_id'=>$challenge]);
    verify($battle['result']['battle']['winner_id']===$pid && $battle['result']['battle']['losses']===0,'consensual sparring resolves virtual result');
    action(1,'arena.accept',['challenge_id'=>$challenge],422);
    verify((int)$db->query('SELECT count FROM city_troops WHERE city_id=? AND troop_code=50100101',[$city])->fetchColumn()===5,'sparring never removes real troops');
    $challenge=action(0,'arena.challenge',['opponent_id'=>$actors[1]['id']])['result']['challenge_id'];
    action(1,'arena.decline',['challenge_id'=>$challenge]);
    $challenge=action(0,'arena.challenge',['opponent_id'=>$actors[1]['id']])['result']['challenge_id'];
    action(0,'arena.cancel',['challenge_id'=>$challenge]);
    $savedName=req(0,'/api/kingdom/state')['json']['data']['profile']['display_name'];
    verify(req(0,'/api/auth/logout',[])['status']===200,'logout succeeds');
    verify(req(0,'/api/kingdom/state')['status']===401,'supporting state requires authentication');
    $page=req(0,'/');preg_match('/name="csrf" value="([a-f0-9]+)"/',$page['text'],$m);
    verify(authenticate(0,['mode'=>'login','username'=>$actors[0]['name'],'password'=>$actors[0]['password'],'csrf'=>$m[1]])['status']===302,'original login name still works');
    verify(req(0,'/api/kingdom/state')['json']['data']['profile']['display_name']===$savedName,'profile survives logout and login');
    echo "ALL KINGDOM INTEGRATION CHECKS PASSED ($suffix)\n";
} catch(Throwable $e) { fwrite(STDERR,'FAIL '.$e->getMessage()."\n"); exit(1); }
finally { foreach($actors as $actor) { if(is_file($actor['cookie'])) { unlink($actor['cookie']); } } }
