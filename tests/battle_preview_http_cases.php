<?php
declare(strict_types=1);

/** Included only by battle_preview.php, against its disposable database. */
if (PHP_SAPI !== 'cli' || !isset($fixture, $db, $base, $snapshot)) exit(1);
$token=bin2hex(random_bytes(32));$csrf=bin2hex(random_bytes(32));
$db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,user_agent,expires_at,active_world_id) VALUES(1,?,?,'127.0.0.1','Battle preview tests',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),1)",[$token,$csrf]);
$db->execute('UPDATE field_monsters SET expires_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY) WHERE id=1');
$db->execute("INSERT INTO field_monsters(id,world_id,monster_code,coord_x,coord_y,hp_current,monster_type,expires_at) VALUES(2,2,20209901,70,65,10,'solo',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))");
\Conquer\Game\World\LandProgressService::ensureWorld(1);
$db->execute("UPDATE world_land_zones SET status='locked',opened_at=NULL WHERE world_id=1 AND zone_key='center'");
$mapSize=(int)$db->query('SELECT map_size FROM worlds WHERE id=1')->fetchColumn();$center=intdiv($mapSize,2);
foreach([2=>[1,80,65,0],3=>[1,82,65,1],4=>[1,$center,$center,0],5=>[2,80,65,0]]as$pid=>[$world,$x,$y,$hidden]){
    $db->execute('INSERT INTO players(id,username,email,password_hash)VALUES(?,?,?,?)',[$pid,'Profile'.$pid,'profile'.$pid.'@tests.invalid','unused']);
    $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,is_hidden)VALUES(?,?,?,'Private army',?,?,?)",[$pid,$pid,$world,$x,$y,$hidden]);
    $db->execute('INSERT INTO city_troops(city_id,troop_code,count)VALUES(?,50100101,1234)',[$pid]);
}
$httpBefore=$snapshot();
$http=$fixture->serve(<<<'PHP'
$session=\Conquer\Auth\Session::current();
if($session)\Conquer\Game\World\WorldContext::bind((int)$session['active_world_id'],(int)$session['player_id']);
if(preg_match('#^/api/player/profile/([^/]+)$#',parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),$m) && $_SERVER['REQUEST_METHOD']==='GET')\Conquer\Api\Handlers\PlayerHandler::profile(['id'=>$m[1]]);
if(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)==='/api/march/preview' && $_SERVER['REQUEST_METHOD']==='POST')\Conquer\Api\Handlers\BattlePreviewHandler::calculate([]);
\Conquer\Api\Response::error(404,'NOT_FOUND','Not found');
PHP, ['-d','display_errors=0','-d','display_startup_errors=0']); // Match .user.ini: startup notices must not corrupt API JSON/status.
$request=static function(array|string $body,?string $cookie,?string $csrfValue)use($http):array{
    $h=curl_init($http.'/api/march/preview');
    $headers=['Content-Type: application/json'];if($csrfValue!==null)$headers[]='X-CSRF-Token: '.$csrfValue;
    curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>is_array($body)?json_encode($body,JSON_THROW_ON_ERROR):$body,CURLOPT_HTTPHEADER=>$headers]);
    if($cookie!==null)curl_setopt($h,CURLOPT_COOKIE,'conquer_session='.$cookie);
    $raw=curl_exec($h);$status=(int)curl_getinfo($h,CURLINFO_RESPONSE_CODE);curl_close($h);
    return ['status'=>$status,'json'=>json_decode((string)$raw,true,64,JSON_THROW_ON_ERROR)];
};
checkPreview($request($base,null,null)['status']===401,'HTTP preview requires authentication');
checkPreview($request($base,$token,null)['status']===403,'HTTP preview requires CSRF');
checkPreview($request($base,$token,'wrong')['status']===403,'HTTP preview rejects foreign CSRF');
foreach(['{bad','[]','null']as$invalid)checkPreview($request($invalid,$token,$csrf)['status']===400,'HTTP preview requires a JSON object');
checkPreview($request(str_repeat(' ',17000),$token,$csrf)['status']===413,'HTTP preview bounds request size');
checkPreview($request($base+['expected_world_id'=>2],$token,$csrf)['status']===409,'HTTP preview rejects stale world');
checkPreview($request(array_replace($base,['target_id'=>2]),$token,$csrf)['status']===404,'HTTP preview cannot read a target from another world');
checkPreview($request(array_replace($base,['target_x'=>71]),$token,$csrf)['status']===404,'HTTP preview binds target ID to authoritative coordinates');
$mapSize=(int)$db->query('SELECT map_size FROM worlds WHERE id=1')->fetchColumn();$center=intdiv($mapSize,2);
checkPreview($request(array_replace($base,['target_x'=>$center,'target_y'=>$center]),$token,$csrf)['status']===409,'HTTP preview rejects locked central land');
$response=$request($base,$token,$csrf);
checkPreview($response['status']===200&&(float)$response['json']['data']['luck_percent']===0.0,'authenticated HTTP preview returns the zero-luck reference');
$injected=$request($base+['player_id'=>999,'luck_percent'=>10,'seed'=>42],$token,$csrf);
$result=$response['json']['data'];$repeat=$injected['json']['data'];unset($result['calculated_at'],$repeat['calculated_at']);
checkPreview($injected['status']===200&&$result===$repeat,'HTTP caller cannot select player, seed or luck');
checkPreview($snapshot()===$httpBefore,'HTTP previews do not alter armies, targets, resources, AP or reports');

$profile=static function(string $id,?string $sessionToken)use($http):array{
    $h=curl_init($http.'/api/player/profile/'.$id);curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15]);
    if($sessionToken!==null)curl_setopt($h,CURLOPT_COOKIE,'conquer_session='.$sessionToken);
    $raw=curl_exec($h);$status=(int)curl_getinfo($h,CURLINFO_RESPONSE_CODE);curl_close($h);return ['status'=>$status,'json'=>json_decode((string)$raw,true,64,JSON_THROW_ON_ERROR)];
};
checkPreview($profile('2',null)['status']===401,'public player profile still requires authentication');
foreach(['0','-1','2junk','2.0']as$bad)checkPreview($profile($bad,$token)['status']===400,'profile rejects malformed player IDs');
$public=$profile('2',$token);checkPreview($public['status']===200&&$public['json']['data']['username']==='Profile2','visible same-world public profile remains available');
$allowed=['id','username','alliance_tag','alliance_name','castle_level','power','kill_count','vip_level','lord_level','world_id'];
checkPreview(array_diff(array_keys($public['json']['data']),$allowed)===[]&&!array_key_exists('troops',$public['json']['data']),'public profile cannot reveal troop composition or other private fields');
checkPreview($profile('1',$token)['status']===200,'own public profile remains available');
foreach(['3'=>'hidden','4'=>'locked land','5'=>'another world','999'=>'missing']as$id=>$reason)checkPreview($profile((string)$id,$token)['status']===404,'profile conceals '.$reason.' city');
