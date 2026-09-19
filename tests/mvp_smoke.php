<?php
declare(strict_types=1);
/** End-to-end checks against local HTTP + MySQL. Creates a named test kingdom. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$base = rtrim($argv[1] ?? 'http://localhost/conquer', '/');
if (!in_array(parse_url($base, PHP_URL_HOST), ['localhost','127.0.0.1'], true)) { exit("Local test hosts only.\n"); }
$cookie = tempnam(sys_get_temp_dir(), 'conquer-test-');
$csrf = '';
$name = 'MvpAuto' . date('His');
$password = bin2hex(random_bytes(16));
function check(bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } echo "PASS $message\n"; }
function request(string $path, ?array $body = null, bool $form = false, bool $token = true): array {
    global $base,$cookie,$csrf;
    $ch = curl_init($base . $path);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_TIMEOUT=>15]);
    if ($body !== null) {
        curl_setopt($ch,CURLOPT_POST,true);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$form?http_build_query($body):json_encode($body));
        curl_setopt($ch,CURLOPT_HTTPHEADER,$form?[]:array_filter(['Content-Type: application/json',$token?'X-CSRF-Token: '.$csrf:null]));
    }
    $text = curl_exec($ch); $status = curl_getinfo($ch,CURLINFO_HTTP_CODE);
    if ($text === false) { throw new RuntimeException(curl_error($ch)); }
    curl_close($ch);
    return ['status'=>$status,'text'=>$text,'json'=>json_decode($text,true)];
}
function state(): array { $r=request('/api/game/state'); check($r['status']===200 && ($r['json']['ok']??false),'game state responds'); return $r['json']['data']; }
function waitUntil(callable $condition, int $seconds): array {
    $until=time()+$seconds;
    do { sleep(2); $s=state(); if ($condition($s)) { return $s; } } while(time()<$until);
    throw new RuntimeException('Timed out waiting for real queue settlement');
}
try {
    check(request('/api/game/state')['status']===401,'unauthenticated state is denied');
    $page=request('/'); preg_match('/name="csrf" value="([a-f0-9]+)"/',$page['text'],$m);
    check(!empty($m[1]),'registration form has CSRF protection');
    check(request('/auth/local',['mode'=>'register','username'=>$name,'password'=>$password,'alpha_key'=>'LOCAL-ALPHA-ACCESS-2026-KEY1','csrf'=>$m[1]],true)['status']===302,'register creates a kingdom');
    $s=state(); $csrf=$s['player']['csrf'];
    check(count($s['buildings'])===13 && $s['buildings']['castle']['level']===1,'new player gets all initial buildings');
    check(($s['congress']['shrine_code']??null)==='CONGRESS' && count($s['shrines']??[])===4,'world snapshot includes Congress and four shrines');
    check(request('/api/city/upgrade-building',['building_code'=>'castle'],false,false)['status']===403,'missing CSRF cannot spend resources');
    check(request('/api/city/upgrade-building',['building_code'=>'farm'])['status']===422,'castle level requirement enforced');
    check(request('/api/troops/train',['troop_code'=>50100101,'count'=>-1])['status']===400,'negative training rejected');
    check(request('/api/troops/train',['troop_code'=>50100101,'count'=>50000])['status']===400,'unaffordable training rejected');
    check(request('/api/city/upgrade-building',['building_code'=>'castle'])['status']===200,'castle upgrade starts');
    check(request('/api/city/upgrade-building',['building_code'=>'castle'])['status']===422,'duplicate building queue rejected');
    check(request('/api/troops/train',['troop_code'=>50100101,'count'=>20])['status']===200,'twenty soldiers enter training');
    check(request('/api/troops/train',['troop_code'=>50100101,'count'=>1])['status']===400,'occupied training slot rejected');
    check(request('/api/research/start',['code'=>'food_production','level_to'=>1])['status']===200,'research starts');
    $s=waitUntil(fn($s)=>($s['troops'][50100101]??0)===20 && $s['buildings']['castle']['level']===2 && ($s['research']['food_production']??0)===1,90);
    check($s['city']['lumber']>=3300 && $s['city']['lumber']<3400,'build, training and research deduct the expected wood');
    $scouts=array_values(array_filter($s['monsters'],fn($m)=>$m['monster_code']===20209901));
    check(count($scouts)>0,'approachable starter monster is available');
    $m=$scouts[0];
    check(request('/api/march/dispatch',['target_x'=>$m['coord_x'],'target_y'=>$m['coord_y'],'troops'=>[50100101=>10]])['status']===200,'monster march dispatches');
    $node=$s['nodes'][0];
    check(request('/api/march/dispatch-gather',['target_x'=>$node['coord_x'],'target_y'=>$node['coord_y'],'troop_count'=>11])['status']===400,'cannot gather with more troops than owned');
    check(request('/api/march/dispatch-gather',['target_x'=>$node['coord_x'],'target_y'=>$node['coord_y'],'troop_count'=>10])['status']===200,'gather march dispatches');
    $away=state(); check(($away['troops'][50100101]??0)===0,'dispatched troops leave city');
    $s=waitUntil(fn($s)=>count($s['marches'])===0 && count($s['reports'])>0,90);
    check(($s['troops'][50100101]??0)===20,'all surviving troops return exactly once');
    check($s['reports'][0]['outcome']==='attacker_wins','starter monster defeated');
    check(($s['reports'][0]['details']['loot']['gold']??0)===75,'battle report records actual reward');
    check($s['city']['gold'] >= $away['city']['gold']+75,'monster reward delivered');
    $resource=[1=>'food',2=>'lumber',3=>'stone',4=>'gold'][$node['object_type']];
    $battleReward=$s['reports'][0]['details']['loot'][$resource]??0;
    check($s['city'][$resource] >= $away['city'][$resource]+100+$battleReward,'gather haul delivered');
    $again=state();check(($again['troops'][50100101]??0)===20,'polling does not duplicate returning troops');
    check(request('/api/auth/logout',[])['status']===200,'logout succeeds');
    check(request('/api/game/state')['status']===401,'logout revokes access');
    $page=request('/'); preg_match('/name="csrf" value="([a-f0-9]+)"/',$page['text'],$m);
    check(request('/auth/local',['mode'=>'login','username'=>$name,'password'=>$password,'csrf'=>$m[1]],true)['status']===302,'password login resumes account');
    $s=state();check($s['buildings']['castle']['level']===2 && count($s['reports'])>0,'progress persists after logout and login');
    echo "ALL MVP CHECKS PASSED for $name\n";
} catch (Throwable $e) { fwrite(STDERR,"FAIL ".$e->getMessage()."\n"); exit(1); }
finally { if(is_file($cookie)) { unlink($cookie); } }
