<?php
declare(strict_types=1);
/** Isolated browser QA; shuts down server and deletes its synthetic database on Enter. */
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require ROOT_DIR.'/tests/Support/FeatureDatabase.php';
$fixture=new \ConquerTests\FeatureDatabase();$db=\Conquer\Db\Connection::getInstance();
$name=(string)$db->query('SELECT DATABASE()')->fetchColumn();$dir=sys_get_temp_dir().DIRECTORY_SEPARATOR.$name;
function copyPreviewTree(string $from,string $to):void{if(!is_dir($to))mkdir($to,0700,true);foreach(new DirectoryIterator($from)as$f){if($f->isDot()||$f->isLink())continue;$target=$to.'/'.$f->getFilename();if($f->isDir())copyPreviewTree($f->getPathname(),$target);else copy($f->getPathname(),$target);}}
function removePreviewTree(string $path,string $base):void{$resolved=realpath($path);$base=realpath($base);if(!$resolved||!$base||($resolved!==$base&&!str_starts_with($resolved,$base.DIRECTORY_SEPARATOR)))throw new RuntimeException('Unsafe preview cleanup.');foreach(new DirectoryIterator($resolved)as$f){if($f->isDot())continue;if($f->isDir()&&!$f->isLink())removePreviewTree($f->getPathname(),$base);else unlink($f->getPathname());}rmdir($resolved);}
$server=null;
try{
 foreach(['src','views','data']as$part)copyPreviewTree(ROOT_DIR.'/'.$part,$dir.'/'.$part);copy(ROOT_DIR.'/index.php',$dir.'/index.php');
 foreach(['css','js','city3d']as$part)copyPreviewTree(ROOT_DIR.'/assets/'.$part,$dir.'/assets/'.$part);
 copyPreviewTree(ROOT_DIR.'/assets/art/items',$dir.'/assets/art/items');
 foreach(['manifest.php','service-worker.js','offline.html']as$publicFile)copy(ROOT_DIR.'/'.$publicFile,$dir.'/'.$publicFile);
 file_put_contents($dir.'/config/app.php',"<?php return ['env'=>'development','log_level'=>'ERROR'];");mkdir($dir.'/logs',0700,true);
 $router='<?php $uri=parse_url($_SERVER["REQUEST_URI"],PHP_URL_PATH);if(str_starts_with($uri,"/assets/")){$file=realpath('.var_export(ROOT_DIR,true).'.$uri);$base=realpath('.var_export(ROOT_DIR.'/assets',true).');$ext=strtolower(pathinfo($file?:"",PATHINFO_EXTENSION));if($file&&str_starts_with($file,$base.DIRECTORY_SEPARATOR)&&in_array($ext,["css","js","png","jpg","svg","gif","webp","json","glb","gltf","bin","woff2"])){$mime=["css"=>"text/css","js"=>"text/javascript","svg"=>"image/svg+xml","png"=>"image/png","jpg"=>"image/jpeg","webp"=>"image/webp","json"=>"application/json"];header("Content-Type: ".($mime[$ext]??"application/octet-stream"));readfile($file);return;}http_response_code(404);return;}if(preg_match("#^/(src|views|config|data|logs|tests)/#",$uri)){http_response_code(403);return;}$_SERVER["SCRIPT_NAME"]="/index.php";require __DIR__."/index.php";';
 $publicRoutes='if(in_array($uri,["/manifest.php","/service-worker.js","/offline.html"],true)){if($uri==="/manifest.php"){$_SERVER["SCRIPT_NAME"]="/manifest.php";require __DIR__.$uri;}else{header("Content-Type: ".($uri==="/service-worker.js"?"text/javascript":"text/html"));readfile(__DIR__.$uri);}return;}';
 $router=str_replace('if(str_starts_with($uri,"/assets/"))',$publicRoutes.'if(str_starts_with($uri,"/assets/"))',$router);
 // Keep scripts/styles in the same snapshot as views and PHP. Serving live
 // workspace JS with copied HTML races parallel edits that add a script tag.
 $router=str_replace('$ext=strtolower(pathinfo($file?:"",PATHINFO_EXTENSION));','$snapshotBase=realpath(__DIR__."/assets");$snapshotFile=realpath(__DIR__.$uri);if($snapshotBase&&$snapshotFile&&str_starts_with($snapshotFile,$snapshotBase.DIRECTORY_SEPARATOR)){$file=$snapshotFile;$base=$snapshotBase;}$ext=strtolower(pathinfo($file?:"",PATHINFO_EXTENSION));',$router);
 file_put_contents($dir.'/router.php',$router);
 $db->execute('INSERT INTO admin_users(username,password_hash,role) VALUES(?,?,?)',['PreviewAdmin',password_hash('PreviewFixture!2026',PASSWORD_DEFAULT),'superadmin']);
 $db->execute('INSERT INTO players(id,username,email,password_hash) VALUES(1,?,?,?)',['PreviewPlayer','preview@tests.invalid',password_hash('PreviewFixture!2026',PASSWORD_DEFAULT)]);
 if(in_array('--march-skins',$argv,true))$db->execute('UPDATE players SET gems=10000 WHERE id=1');
 $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,castle_level,food,lumber,stone,gold) VALUES(1,1,1,'Vorschaukönigreich',65,65,12,100000,100000,100000,100000)");
 foreach(\Conquer\Game\City\CityState::BUILDING_CODES as$code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(1,?,?)',[$code,$code==='castle'?12:7]);
 foreach([50100101,50200101,50300101]as$code)$db->execute('INSERT INTO city_troops(city_id,troop_code,count) VALUES(1,?,500)',[$code]);
 if(in_array('--teleport',$argv,true)){
  $db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id)VALUES(1,1,'Vorschau-Allianz','QA',1)");
  $db->execute("INSERT INTO alliance_members(alliance_id,player_id,world_id,role)VALUES(1,1,1,'leader')");
  foreach([10208001,10208002,10208003]as$teleportCode)\Conquer\Game\Inventory\InventoryService::addItems(1,$teleportCode,3);
 }
 if(in_array('--hospital',$argv,true)){
  \Conquer\Game\Hospital\HospitalService::addWounded(1,[50100101=>620,50200101=>245,50300101=>85]);
  $db->execute('UPDATE players SET gems=5000 WHERE id=1');
  foreach(\Conquer\Game\Inventory\InventoryService::allDefs() as$code=>$item)if($item['category']==='speedup'&&in_array($item['subcategory']??'',['generic','healing'],true)&&$item['duration_seconds']<=3600)\Conquer\Game\Inventory\InventoryService::addItems(1,(int)$code,10);
 }
 if(in_array('--training',$argv,true)){
  $db->execute('UPDATE cities SET food=100000000,lumber=100000000,stone=100000000,gold=100000000,castle_level=30 WHERE id=1');
  $db->execute("UPDATE city_buildings SET level=30 WHERE city_id=1");
  \Conquer\Game\Inventory\InventoryService::addItems(1,10103003,10);
 }
 if(in_array('--balance-import',$argv,true)){
  $db->execute('UPDATE cities SET food=500000000,lumber=500000000,stone=500000000,gold=500000000,castle_level=30 WHERE id=1');
  $db->execute("UPDATE city_buildings SET level=30 WHERE city_id=1");
  $db->execute("UPDATE city_buildings SET level=29 WHERE city_id=1 AND building_code IN('academy','hall_of_alliance','watch_tower')");
  foreach([119000001=>2,119000002=>4999,120601001=>3] as $code=>$count)\Conquer\Game\Inventory\InventoryService::addItems(1,$code,$count);
 }
 if(in_array('--queue-speedups',$argv,true)){
  foreach(['castle'=>13,'farm'=>8] as $code=>$level)$db->execute("INSERT INTO building_queue(city_id,building_code,level_to,started_at,finishes_at)VALUES(1,?,?,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 HOUR))",[$code,$level]);
  $db->execute("INSERT INTO research_queue(player_id,world_id,research_code,level_to,started_at,finishes_at)VALUES(1,1,'food_production',1,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 HOUR))");
  foreach([1=>50100101,2=>50200101,3=>50300101] as $slot=>$code)$db->execute("INSERT INTO troop_queue(city_id,troop_code,count,barrack_slot,started_at,finishes_at)VALUES(1,?,20,?,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 HOUR))",[$code,$slot]);
  foreach(\Conquer\Game\Inventory\InventoryService::allDefs() as $code=>$item)if($item['category']==='speedup'&&$item['duration_seconds']<=3600)\Conquer\Game\Inventory\InventoryService::addItems(1,(int)$code,10);
 }
 \Conquer\Game\World\WorldSettings::get(1);
 if(in_array('--combat-reports',$argv,true))(require ROOT_DIR.'/tests/fixtures/combat_reports.php')($db);
 if(in_array('--march-skin-world',$argv,true)){
  $db->transaction(static function($db){
   \Conquer\Game\Map\WorldPlacement::lockWorld($db,1);
   $spot=\Conquer\Game\Map\WorldPlacement::findNear($db,1,'monster',90,65,null,8,'forest');
   if(!$spot)throw new RuntimeException('No march-skin preview monster position');
   $db->execute("INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current,monster_type,expires_at) VALUES(1,20209901,?,?,1,'solo',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))",[$spot[0],$spot[1]]);
  });
 }
 if(in_array('--gathering',$argv,true)){
  foreach([2=>'EnemyFarmer',3=>'AlliedFarmer'] as $pid=>$username){
   $db->execute('INSERT INTO players(id,username,email,password_hash) VALUES(?,?,?,?)',[$pid,$username,$username.'@tests.invalid',password_hash('PreviewFixture!2026',PASSWORD_DEFAULT)]);
   $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,castle_level,food,lumber,stone,gold) VALUES(?,?,1,?,?,65,12,100000,100000,100000,100000)",[$pid,$pid,$username,70+$pid*5]);
   foreach(\Conquer\Game\City\CityState::BUILDING_CODES as $code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(?,?,7)',[$pid,$code]);
   $db->execute('INSERT INTO city_troops(city_id,troop_code,count) VALUES(?,50100101,10000)',[$pid]);
  }
  $db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id) VALUES(1,1,'Harvest Friends','HF',1)");
  $db->execute("INSERT INTO alliance_members(alliance_id,player_id,world_id,role) VALUES(1,1,1,'leader'),(1,3,1,'member')");
  foreach([[1,65,70],[2,70,69],[3,61,65],[1,71,73]] as [$pid,$x,$y]){
   $db->execute("INSERT INTO field_objects(world_id,coord_x,coord_y,object_type,level,resource_amount,resource_max,expires_at) VALUES(1,?,?,1,2,100000,100000,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))",[$x,$y]);$node=$db->lastInsertId();
   $arrived=$x!==71;
   $db->execute("INSERT INTO marches(player_id,world_id,march_type,origin_city_id,target_x,target_y,target_type,target_id,troops_json,haul_json,departure_time,arrival_time,gathering_finishes_at,state) VALUES(?,1,9,?,?,?,5,?,'{\"50100101\":500}',?,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 20 SECOND),".($arrived?'DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 SECOND)':'DATE_ADD(UTC_TIMESTAMP(),INTERVAL 10 MINUTE)').",".($arrived?'DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 MINUTE)':'NULL').",?)",[$pid,$pid,$x,$y,$node,json_encode(['gather'=>['rate'=>.5,'capacity'=>1000]]),$arrived?'arrived':'marching']);
   if($arrived)$db->execute('UPDATE field_objects SET gatherer_march_id=? WHERE id=?',[$db->lastInsertId(),$node]);
  }
  $db->execute("INSERT INTO marches(player_id,world_id,march_type,origin_city_id,target_x,target_y,target_type,troops_json,haul_json,departure_time,arrival_time,return_time,state) VALUES(1,1,9,1,61,71,5,'{\"50200101\":200}', '{\"survivors\":{\"50200101\":200},\"loot\":{\"food\":250}}',DATE_SUB(UTC_TIMESTAMP(),INTERVAL 20 MINUTE),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 MINUTE),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 10 MINUTE),'returning')");
 }
 // Receipt QA needs a target from the gathering fixture but empty march slots.
 if(in_array('--army-receipts',$argv,true))$db->execute('DELETE FROM marches WHERE player_id=1');
 if(in_array('--dungeons',$argv,true)){
  \Conquer\Db\MigrationSql::apply($db->getPdo(),file_get_contents(ROOT_DIR.'/migrations/0082_create_dungeons.sql'));
  \Conquer\Game\Player\LordLevel::addXp(1,\Conquer\Game\Player\LordLevel::totalForLevel(20),1,'dungeon-preview');
  $db->execute("INSERT INTO player_lord_talents(player_id,world_id,talent_code,rank) VALUES(1,1,'attack_0',5)");
  foreach([2=>'defense',3=>'gather',4=>'hunter'] as $pid=>$role){
   $db->execute('INSERT INTO players(id,username,email,password_hash) VALUES(?,?,?,?)',[$pid,'Dungeon'.$pid,'dungeon'.$pid.'@tests.invalid',password_hash('PreviewFixture!2026',PASSWORD_DEFAULT)]);
   $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,castle_level,food,lumber,stone,gold) VALUES(?,?,1,'Dungeonstadt',?,70,12,100000,100000,100000,100000)",[$pid,$pid,70+$pid*5]);
   foreach(\Conquer\Game\City\CityState::BUILDING_CODES as $code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(?,?,?)',[$pid,$code,$code==='castle'?12:7]);
   foreach([50100101,50200101,50300101] as $code)$db->execute('INSERT INTO city_troops(city_id,troop_code,count) VALUES(?,?,500)',[$pid,$code]);
   \Conquer\Game\Player\LordLevel::addXp($pid,\Conquer\Game\Player\LordLevel::totalForLevel(20),1,'dungeon-preview');
   $db->execute('INSERT INTO player_lord_talents(player_id,world_id,talent_code,rank) VALUES(?,1,?,5)',[$pid,$role.'_0']);
  }
 }

 if(in_array('--talents',$argv,true))\Conquer\Game\Player\LordLevel::addXp(1,\Conquer\Game\Player\LordLevel::totalForLevel(60),1,'preview-level');
 if(in_array('--effects',$argv,true)){
  $db->execute("INSERT INTO player_charms_active(player_id,world_id,stat_category,grade,charm_code,source_map_charm_id,bonus_pct,activated_at,expires_at) VALUES(1,1,'construction','epic',10700002,999,5,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 45 MINUTE),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 75 MINUTE)),(1,1,'gold_production','normal',10600001,NULL,20,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 3 HOUR),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 5 HOUR))");
  $db->execute("INSERT INTO active_buffs(player_id,buff_type,multiplier,expires_at) VALUES(1,'research_boost',0.85,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 MINUTE))");
 }
 if(in_array('--hud',$argv,true)){
  $db->execute("INSERT INTO building_queue(city_id,building_code,level_to,started_at,finishes_at) VALUES(1,'farm',8,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 15 MINUTE),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 MINUTE))");
  $db->execute("INSERT INTO research_queue(player_id,world_id,research_code,level_to,started_at,finishes_at) VALUES(1,1,'food_production',1,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 20 MINUTE),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 40 MINUTE))");
  $db->execute("INSERT INTO troop_queue(city_id,troop_code,count,started_at,finishes_at) VALUES(1,50100101,250,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 MINUTE),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 20 MINUTE))");
 }
 if(in_array('--chat',$argv,true)){
  $db->execute("INSERT INTO players(id,username,email,password_hash) VALUES(2,'Elara','elara@tests.invalid','unused')");
  $db->execute("INSERT INTO kingdom_profiles(player_id,display_name,avatar) VALUES(2,'Elara','archer')");
  $db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id) VALUES(1,1,'Die Morgenwacht','MW',1)");
  $db->execute("INSERT INTO alliance_members(alliance_id,player_id,world_id,role) VALUES(1,1,1,'leader')");
  foreach(['Willkommen in der Welt! Wer erkundet heute den Norden?','Rund um den Wald gibt es noch freie Rohstofffelder.','Wir sammeln uns am Feuerschrein. Kommt gerne dazu!']as$text)$db->execute("INSERT INTO world_chat(world_id,player_id,username,alliance_tag,message) VALUES(1,2,'Elara','MW',?)",[$text]);
  $db->execute("INSERT INTO alliance_messages(alliance_id,player_id,username,message) VALUES(1,2,'Elara','Unser nächster Sammelpunkt ist am Wald. Wer ist dabei?')");
 }
 if(in_array('--guide',$argv,true)){
  $db->execute("INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current,monster_type,expires_at) VALUES(1,20200501,75,75,1000,'rally',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))");
 }
 if(in_array('--regional-bosses',$argv,true)){
  $db->transaction(static function($db){
   \Conquer\Game\Map\WorldPlacement::lockWorld($db,1);
   foreach([[1,54,60],[2,56,66],[3,59,71],[4,69,72],[5,72,66]] as [$type,$x,$y]){
    $spot=\Conquer\Game\Map\WorldPlacement::findNear($db,1,'resource',$x,$y);
    if(!$spot)throw new RuntimeException('No preview resource position');
    $db->execute('INSERT INTO field_objects(world_id,coord_x,coord_y,object_type,level,resource_amount,resource_max,expires_at) VALUES(1,?,?,?,2,50000,50000,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))',[$spot[0],$spot[1],$type]);
   }
  });
  foreach([[20202401,64,64],[20202101,192,64],[20202201,64,192],[20202301,192,192]] as [$code,$x,$y]){
   $db->transaction(static function($db)use($code,$x,$y){
    \Conquer\Game\Map\WorldPlacement::lockWorld($db,1);
    $def=\Conquer\Game\Map\MonsterData::get($code);
    $spot=\Conquer\Game\Map\WorldPlacement::findNear($db,1,\Conquer\Game\Map\WorldPlacement::monsterKind($code),$x,$y,null,24,$def['biome']);
    if(!$spot)throw new RuntimeException('No preview boss position');
    $db->execute("INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current,monster_type,expires_at) VALUES(1,?,?,?,?,'rally',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))",[$code,$spot[0],$spot[1],$def['stats']['hp']*$def['amount']]);
   });
  }
 }
 if(in_array('--map-search',$argv,true)){
  foreach([[48,65],[43,65]] as [$x,$y])$db->transaction(static function($db)use($x,$y):void{
   \Conquer\Game\Map\WorldPlacement::lockWorld($db,1);
   $spot=\Conquer\Game\Map\WorldPlacement::findNear($db,1,'resource',$x,$y);
   if(!$spot)throw new RuntimeException('No map-search fixture position.');
   $db->execute('INSERT INTO field_objects(world_id,coord_x,coord_y,object_type,level,resource_amount,resource_max,expires_at) VALUES(1,?,?,1,2,50000,50000,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))',$spot);
  });
 }
 if(in_array('--mailbox',$argv,true)){require ROOT_DIR.'/tests/Support/MailboxFixture.php';\ConquerTests\MailboxFixture::seed();}
 if(in_array('--monster-reports',$argv,true))require ROOT_DIR.'/tests/fixtures/monster_reports.php';
 if(in_array('--monster-health',$argv,true))require ROOT_DIR.'/tests/fixtures/monster_health.php';
 if(in_array('--inventory-overview',$argv,true)){
  foreach([10101001=>108000,10101011=>106800,10101021=>22200,10101031=>11220,10101041=>700,10104001=>150,10106001=>80,10103003=>1879,10103011=>255,10103021=>890,10103031=>600,10103041=>392]as$code=>$quantity){
   \Conquer\Game\Inventory\InventoryService::addItems(1,$code,$quantity);
  }
 }
 $port=18942;foreach($argv as $arg)if(preg_match('/^--port=([0-9]{4,5})$/D',$arg,$match))$port=(int)$match[1];if($port<1024||$port>65535)throw new RuntimeException('Invalid preview port.');
 mkdir($dir.'/sessions',0700,true);
 $log=$dir.'/logs/server.log';$server=proc_open([PHP_BINARY,'-d','session.save_path='.$dir.'/sessions','-d','display_startup_errors=0','-S','127.0.0.1:'.$port,'-t',$dir,$dir.'/router.php'],[0=>['pipe','r'],1=>['file',$log,'a'],2=>['file',$log,'a']],$pipes,$dir);
 if(!is_resource($server))throw new RuntimeException('Cannot start preview.');
 echo "Synthetic preview ready at http://127.0.0.1:$port\nPress Enter to stop and clean up.\n";fflush(STDOUT);fgets(STDIN);
}finally{if(is_resource($server)){proc_terminate($server);proc_close($server);}foreach(['src','views','data','logs','assets','sessions']as$part)if(is_dir($dir.'/'.$part))removePreviewTree($dir.'/'.$part,$dir);foreach(['index.php','router.php','config/app.php','manifest.php','service-worker.js','offline.html']as$f)if(is_file($dir.'/'.$f))unlink($dir.'/'.$f);$fixture->close();}
