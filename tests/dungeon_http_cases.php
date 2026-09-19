<?php
declare(strict_types=1);

/** Included by dungeon_lifecycle.php; fixture owns the server and its cleanup. */
if (PHP_SAPI !== 'cli' || !isset($fixture,$db,$create)) { exit(1); }
$sessions=[];
foreach ([1,2,3,4] as $pid) {
    $sessions[$pid]=['token'=>bin2hex(random_bytes(32)),'csrf'=>bin2hex(random_bytes(32))];
    $db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,user_agent,expires_at,active_world_id) VALUES(?,?,?,'127.0.0.1','Dungeon tests',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),1)",[$pid,$sessions[$pid]['token'],$sessions[$pid]['csrf']]);
}
$httpBase=$fixture->serve(<<<'PHP'
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if ($path==='/api/dungeons/state' && $_SERVER['REQUEST_METHOD']==='GET') \Conquer\Api\Handlers\DungeonHandler::state([]);
if ($path==='/api/dungeons/action' && $_SERVER['REQUEST_METHOD']==='POST') \Conquer\Api\Handlers\DungeonHandler::action([]);
\Conquer\Api\Response::error(404,'NOT_FOUND','Not found');
PHP);
function requestDungeon(int $pid, string $path, array|string|null $body=null, ?string $csrf=null): array {
    global $sessions,$httpBase;
    $curl=curl_init($httpBase.'/api/dungeons/'.$path);
    curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20]);
    if ($pid) { curl_setopt($curl,CURLOPT_COOKIE,'conquer_session='.$sessions[$pid]['token']); }
    if ($body!==null) {
        curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>is_array($body)?json_encode($body,JSON_THROW_ON_ERROR):$body,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-CSRF-Token: '.($csrf??$sessions[$pid]['csrf']??'')]]);
    }
    $raw=curl_exec($curl);
    if ($raw===false) { throw new RuntimeException(curl_error($curl)); }
    $status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);
    $json=json_decode($raw,true);
    if (!is_array($json)) { throw new RuntimeException('Invalid JSON HTTP '.$status.': '.substr($raw,0,500)); }
    return ['status'=>$status,'json'=>$json];
}
ck(requestDungeon(0,'state')['status']===401,'HTTP state requires authentication');
ck(requestDungeon(0,'action',['action'=>'create'])['status']===401,'HTTP mutation requires authentication');
ck(requestDungeon(1,'action',['action'=>'create'],'wrong')['status']===403,'HTTP rejects invalid CSRF token');
ck(requestDungeon(1,'action','{invalid')['status']===400,'HTTP malformed JSON rejected');
ck(requestDungeon(1,'action','[]')['status']===400,'HTTP requires object body');
ck(requestDungeon(1,'action',str_repeat(' ',17000))['status']===413,'HTTP oversized body rejected');
$response=requestDungeon(1,'state');
ck($response['status']===200&&$response['json']['ok']&&count($response['json']['data']['rotation'])===3,'authenticated HTTP state returns real rotation');
$beforePreview=stock(1);$response=requestDungeon(1,'action',['action'=>'preview','expected_world_id'=>1]+$create);
ck($response['status']===200&&$response['json']['data']['forecast']['status']==='missing_members'&&$response['json']['data']['forecast']['label']==='Weiterer Spieler benötigt'&&$response['json']['data']['forecast']['missing_roles']===[]&&isset($response['json']['data']['guidance']['requirements']['normal']['skip']),'HTTP preview returns member gap and matching guidance');
ck(stock(1)===$beforePreview,'HTTP preview does not reserve troops');
$response=requestDungeon(1,'action',['action'=>'create','expected_world_id'=>2]+$create);
ck($response['status']===409,'HTTP stale world cannot reserve troops');
$response=requestDungeon(1,'action',['action'=>'create','expected_world_id'=>1]+$create);
ck($response['status']===200&&$response['json']['ok'],'HTTP creates an expedition through authenticated handler');
$httpId=(int)$response['json']['data']['run_id'];
$response=requestDungeon(2,'action',['action'=>'cancel','run_id'=>$httpId,'expected_world_id'=>1]);
ck(in_array($response['status'],[403,422],true),'HTTP outsider cannot cancel another party');
$response=requestDungeon(1,'action',['action'=>'cancel','run_id'=>$httpId,'expected_world_id'=>1]);
ck($response['status']===200,'HTTP leader cancellation succeeds');
\Conquer\Game\Dungeon\DungeonService::tick();
ck(stock(1)===20000,'HTTP cancellation restores reserved troops');
