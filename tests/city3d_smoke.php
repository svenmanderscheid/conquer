<?php
declare(strict_types=1);
/** Run only against the isolated local playtest; real queue durations, no timer rewriting. */
if (PHP_SAPI !== 'cli') { exit(1); }
$base = rtrim($argv[1] ?? 'http://localhost/conquer-3d-playtest', '/');
if (!in_array(parse_url($base,PHP_URL_HOST),['localhost','127.0.0.1'],true)
    || parse_url($base,PHP_URL_PATH)!=='/conquer-3d-playtest') { exit("Isolated local playtest only.\n"); }
$jars=[];for($i=0;$i<3;$i++)$jars[]=tempnam(sys_get_temp_dir(),'city3d-');
function check(bool $condition,string $message): void { if(!$condition)throw new RuntimeException($message);echo "PASS $message\n"; }
function request(int $session,string $path,?array $body=null,bool $form=false,string $csrf=''): array {
    global $base,$jars;
    $ch=curl_init($base.$path);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>$jars[$session],CURLOPT_COOKIEJAR=>$jars[$session],CURLOPT_TIMEOUT=>15]);
    if($body!==null){curl_setopt($ch,CURLOPT_POST,true);curl_setopt($ch,CURLOPT_POSTFIELDS,$form?http_build_query($body):json_encode($body));curl_setopt($ch,CURLOPT_HTTPHEADER,$form?[]:['Content-Type: application/json','X-CSRF-Token: '.$csrf]);}
    $text=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_HTTP_CODE);if($text===false)throw new RuntimeException('HTTP request failed');curl_close($ch);return ['status'=>$status,'text'=>$text,'json'=>json_decode($text,true)];
}
function signIn(int $session,string $name,string $password,string $mode): void {
    $page=request($session,'/');preg_match('/name="csrf" value="([a-f0-9]+)"/',$page['text'],$m);check(!empty($m[1]),'login form token present');
    $r=request($session,'/auth/local',['username'=>$name,'password'=>$password,'mode'=>$mode,'alpha_key'=>$mode==='register'?'LOCAL-ALPHA-ACCESS-2026-KEY1':'','csrf'=>$m[1]],true);check($r['status']===302,'account '.$mode.' succeeds');
}
function state(int $session): array {$r=request($session,'/api/city3d/state');check($r['status']===200&&($r['json']['ok']??false),'authoritative 3D state responds');return $r['json']['data'];}
function concurrentUpgrade(array $body,array $tokens): array {
    global $base,$jars;
    $multi=curl_multi_init();$handles=[];
    foreach($tokens as $i=>$token){$ch=curl_init($base.'/api/city3d/upgrade');curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($body),CURLOPT_COOKIEFILE=>$jars[$i],CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-CSRF-Token: '.$token],CURLOPT_TIMEOUT=>15]);curl_multi_add_handle($multi,$ch);$handles[]=$ch;}
    do {curl_multi_exec($multi,$active);if($active)curl_multi_select($multi,.2);}while($active);
    $codes=[];foreach($handles as $ch){$codes[]=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_multi_remove_handle($multi,$ch);curl_close($ch);}curl_multi_close($multi);sort($codes);return $codes;
}
try {
    check(request(0,'/api/city3d/state')['status']===401,'anonymous state rejected');
    check(request(0,'/city/3d')['status']===302,'anonymous 3D view requires login');
    foreach(['/config/database.php','/src/Api/Handlers/City3dHandler.php','/tests/city3d_smoke.php'] as $path)check(request(0,$path)['status']===403,'private path blocked: '.$path);
    $name='Build3D'.date('His');$password=bin2hex(random_bytes(16));signIn(0,$name,$password,'register');signIn(1,$name,$password,'login');
    $s=state(0);$other=state(1);$csrf=$s['player']['csrf'];check($s['city']['id']===$other['city']['id'],'two independent sessions share the same city');
    check(request(0,'/city/3d')['status']===200,'3D view loads for authenticated player');
    check($s['buildings']['castle']['level']===1&&$s['buildings']['castle']['can_upgrade'],'new castle is ready for first upgrade');
    $body=['building_code'=>'castle','expected_level'=>1];
    check(request(0,'/api/city3d/upgrade',$body)['status']===403,'missing CSRF rejected');
    check(request(0,'/api/city3d/upgrade',['building_code'=>'castle','expected_level'=>'1'],false,$csrf)['status']===400,'invalid level type rejected');
    check(request(0,'/api/city3d/upgrade',['building_code'=>'farm','expected_level'=>1],false,$csrf)['status']===422,'castle prerequisite enforced by server');
    // Client cost, timer and city-id fields are deliberately bogus and must be ignored.
    $codes=concurrentUpgrade($body+['city_id'=>999999,'cost'=>0,'seconds'=>0],[$csrf,$other['player']['csrf']]);check($codes===[200,422],'simultaneous starts produce one success and one rejection');
    $queued=state(1);check(count($queued['build_queue'])===1,'second session sees one saved queue');
    $q=$queued['build_queue'][0];$seconds=strtotime($q['finishes_at'].' UTC')-strtotime($q['started_at'].' UTC');check($seconds===$s['buildings']['castle']['seconds']&&$seconds>0,'server chooses duration, ignoring client override');
    foreach($s['buildings']['castle']['cost'] as $res=>$cost){$spent=$s['resources'][$res]-$queued['resources'][$res];check($spent<=$cost&&$spent>=$cost-20,'expected '.$res.' deduction allowing elapsed production');}
    check(request(1,'/api/city3d/upgrade',$body,false,$other['player']['csrf'])['status']===422,'duplicate upgrade from second session rejected');
    $again=state(0);check(count($again['build_queue'])===1,'reload does not create another queue');
    $finish=strtotime($q['finishes_at'].' UTC');echo "Waiting for real build completion with no browser requests.\n";
    while(time()<=$finish){sleep(1);}
    $done=state(1);check($done['buildings']['castle']['level']===2&&!$done['build_queue'],'offline elapsed timer settles to level 2');
    check(request(0,'/api/city3d/upgrade',$body,false,$csrf)['status']===409,'late replay cannot buy another level');
    $repeat=state(0);check($repeat['buildings']['castle']['level']===2&&!$repeat['build_queue'],'repeat settlement does not duplicate completion');
    check(!$repeat['buildings']['castle']['can_upgrade'],'next castle level exposes missing wall prerequisite');
    check(request(0,'/api/auth/logout',[],false,$csrf)['status']===200,'logout succeeds');
    signIn(0,$name,$password,'login');$resumed=state(0);check($resumed['buildings']['castle']['level']===2,'level persists after new login');
    signIn(2,'Other3D'.date('His'),bin2hex(random_bytes(16)),'register');$separate=state(2);check($separate['city']['id']!==$s['city']['id']&&$separate['buildings']['castle']['level']===1,'another account keeps its own city');
    check(request(2,'/api/city3d/upgrade',$body,false,$resumed['player']['csrf'])['status']===403,'another account cannot reuse session CSRF');
    echo "ALL 3D BUILD CHECKS PASSED ($name).\n";
} catch(Throwable $e){fwrite(STDERR,"FAIL ".$e->getMessage()."\n");exit(1);}
finally {foreach($jars as $jar)if(is_file($jar))unlink($jar);}
