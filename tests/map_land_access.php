<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';

use Conquer\Db\Connection;
use Conquer\Game\World\LandProgressService;

function mapCheck(bool $condition,string $message):void
{
    if(!$condition)throw new RuntimeException($message);
    echo "PASS $message\n";
}

/** @return array{status:int,json:array} */
function mapGet(string $base,string $path,string $token):array
{
    $ch=curl_init($base.$path);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Cookie: conquer_session='.$token],CURLOPT_TIMEOUT=>15]);
    $raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    $json=json_decode((string)$raw,true);
    if(!is_array($json))throw new RuntimeException("Invalid JSON for $path: $raw");
    return ['status'=>$status,'json'=>$json];
}

$fixture=new \ConquerTests\FeatureDatabase();
try{
    $db=Connection::getInstance();
    $db->execute("INSERT INTO worlds(id,name,slug,status,map_size,map_seed,created_at,started_at) VALUES(2,'Kartenwelt','kartenwelt','running',72,91,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY))");
    LandProgressService::ensureWorld(2,false);
    $db->execute("INSERT INTO players(id,username,email,password_hash) VALUES(1,'MapPlayer','map@example.invalid','unused')");
    $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y) VALUES(1,1,2,'Randstadt',8,8)");
    $token=str_repeat('c',64);
    $db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,user_agent,expires_at,active_world_id) VALUES(1,?,?,'127.0.0.1','map test',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),2)",[$token,str_repeat('d',64)]);

    $db->execute("INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current,regional_level_at_spawn,effective_monster_level) VALUES(2,20200101,9,8,100,1,1),(2,20200101,36,36,100,9,1),(1,20200101,9,8,100,1,1)");
    $db->execute("INSERT INTO field_objects(world_id,coord_x,coord_y,object_type,level,resource_amount,resource_max,expires_at) VALUES(2,10,8,1,1,500,1000,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR)),(2,37,36,1,1,500,1000,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR)),(1,10,8,1,1,500,1000,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))");
    $outerObject=(int)$db->query('SELECT id FROM field_objects WHERE world_id=2 AND coord_x=10')->fetchColumn();
    $centerObject=(int)$db->query('SELECT id FROM field_objects WHERE world_id=2 AND coord_x=37')->fetchColumn();
    $foreignObject=(int)$db->query('SELECT id FROM field_objects WHERE world_id=1 AND coord_x=10')->fetchColumn();
    $db->execute("INSERT INTO shrines(world_id,shrine_code,tier,coord_x,coord_y) VALUES(2,'MAP_OUTER','C',11,8),(2,'MAP_CENTER','S',38,36)");
    $db->execute("INSERT INTO map_charms(world_id,coord_x,coord_y,stat_category,grade,charm_code,expires_at) VALUES(2,12,8,'research','normal',10700001,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR)),(2,39,36,'research','epic',10700002,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))");
    $db->execute("INSERT INTO rallies(world_id,leader_player_id,leader_city_id,target_player_id,target_city_id,target_x,target_y,launch_at) VALUES(2,1,1,1,1,13,8,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR)),(2,1,1,1,1,40,36,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))");

    $routes=<<<'PHP'
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if($path==='/info')\Conquer\Api\Handlers\MapHandler::info([]);
if($path==='/tiles')\Conquer\Api\Handlers\MapHandler::tiles([]);
if(preg_match('#^/tile/([^/]+)/([^/]+)$#',$path,$m))\Conquer\Api\Handlers\MapHandler::tile(['x'=>$m[1],'y'=>$m[2]]);
if(preg_match('#^/field-object/(\d+)$#',$path,$m))\Conquer\Api\Handlers\MapHandler::fieldObject(\Conquer\Auth\Session::current()??[],(int)$m[1]);
\Conquer\Api\Response::error(404,'NOT_FOUND','Missing test route.');
PHP;
    $base=$fixture->serve($routes);

    $info=mapGet($base,'/info',$token);
    mapCheck($info['status']===200&&$info['json']['data']['world_id']===2&&$info['json']['data']['map_size']===72,'map info uses the session-selected world and dynamic map size');
    $forged=mapGet($base,'/info?world_id=1',$token);
    mapCheck($forged['status']===409&&$forged['json']['error']['code']==='WORLD_MISMATCH','forged map read scope is rejected');

    $tiles=mapGet($base,'/tiles?x_min=0&y_min=0&x_max=255&y_max=255',$token);
    $entities=$tiles['json']['data']['entities'];$coords=array_map(static fn(array $e):string=>$e['x'].':'.$e['y'],$entities);
    mapCheck($tiles['status']===200&&$tiles['json']['data']['viewport']['x_max']===71&&$tiles['json']['data']['viewport']['y_max']===71,'viewport is clipped to the selected world instead of 256');
    mapCheck(in_array('9:8',$coords,true)&&in_array('10:8',$coords,true)&&in_array('11:8',$coords,true)&&in_array('12:8',$coords,true)&&in_array('13:8',$coords,true),'open outer targets remain visible');
    mapCheck(in_array('36:36',$coords,true)&&in_array('37:36',$coords,true)&&in_array('38:36',$coords,true)&&in_array('39:36',$coords,true)&&in_array('40:36',$coords,true),'central targets are visible from the beginning');
    mapCheck(count(array_filter($entities,static fn(array $e):bool=>$e['type']==='monster'&&$e['x']===9&&$e['y']===8))===1,'same-coordinate entities from another world do not leak');

    $centerTile=mapGet($base,'/tile/36/36',$token);
    mapCheck($centerTile['status']===200&&$centerTile['json']['data']['occupant']['type']==='monster'&&$centerTile['json']['data']['accessible']===true,'direct tile read exposes a central target from the beginning');
    $openTile=mapGet($base,'/tile/9/8',$token);
    mapCheck($openTile['status']===200&&$openTile['json']['data']['occupant']['type']==='monster'&&$openTile['json']['data']['accessible']===true,'direct tile read exposes an open target');
    mapCheck(mapGet($base,'/tile/72/8',$token)['status']===422,'tile coordinates obey the selected map size');

    mapCheck(mapGet($base,'/field-object/'.$outerObject,$token)['status']===200,'open field-object detail remains available');
    mapCheck(mapGet($base,'/field-object/'.$foreignObject,$token)['status']===404,'field-object ids remain scoped to the selected world');
    mapCheck(mapGet($base,'/field-object/'.$centerObject,$token)['status']===200,'central field-object detail is available from the beginning');
    echo "ALL MAP LAND ACCESS CHECKS PASSED\n";
}finally{$fixture->close();}
