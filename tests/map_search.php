<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';
use Conquer\Db\Connection;
use Conquer\Game\Map\MapSearchService;
use Conquer\Game\World\{WorldContext,LandProgressService};
function checkSearch(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);echo "PASS $message\n";}
$fixture=new \ConquerTests\FeatureDatabase();
try{
 $db=Connection::getInstance();WorldContext::bind(1,1);
 $db->execute("INSERT INTO players(id,username,email,password_hash) VALUES(1,'SearchPlayer','search@tests.invalid','unused')");
 $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,castle_level) VALUES(1,1,1,'Search City',10,10,1)");
 LandProgressService::ensureWorld(1);
 $catalog=MapSearchService::search(1,[])['categories'];
 checkSearch(count($catalog)===7&&$catalog['solo'][0]===1&&max($catalog['lumber'])===10&&max($catalog['gold'])===10,'catalog uses active monster and resource definitions');
 foreach([['category'=>'bogus','level'=>1],['category'=>'solo','level'=>999],['category'=>['solo'],'level'=>1],['category'=>'solo','level'=>[1]],['category'=>'food','level'=>'1.5']] as $bad){
  try{MapSearchService::search(1,$bad);throw new RuntimeException('Invalid query accepted');}catch(DomainException){checkSearch(true,'invalid search input rejected');}
 }
 $addNode=static function(int $x,int $y,int $type=1,int $level=2,int $amount=10000,string $expires='+1 DAY')use($db):int{
  $db->execute("INSERT INTO field_objects(world_id,coord_x,coord_y,object_type,level,resource_amount,resource_max,expires_at) VALUES(1,?,?,?,?,?,10000,DATE_ADD(UTC_TIMESTAMP(),INTERVAL $expires))",[$x,$y,$type,$level,$amount]);return $db->lastInsertId();
 };
 $dead=$addNode(11,10,1,2,0);$expired=$addNode(12,10,1,2,10000,'-1 DAY');$otherType=$addNode(13,10,2);$wrongLevel=$addNode(14,10,1,3);
 $occupied=$addNode(11,12);
 $db->execute("INSERT INTO marches(player_id,world_id,march_type,origin_city_id,target_x,target_y,target_type,target_id,troops_json,departure_time,arrival_time,state) VALUES(1,1,9,1,11,12,5,?,'{}',UTC_TIMESTAMP(),UTC_TIMESTAMP(),'arrived')",[$occupied]);
 $db->execute('UPDATE field_objects SET gatherer_march_id=? WHERE id=?',[$db->lastInsertId(),$occupied]);
 $nearest=$addNode(80,10);$farther=$addNode(100,10);
 $found=MapSearchService::search(1,['category'=>'food','level'=>2]);
 checkSearch((int)$found['target']['data']['id']===$nearest,'finds nearest correct free resource beyond the loaded viewport');
 checkSearch($found['target']['data']['gather_rate']>0,'resource carries a server-side gathering estimate');
 checkSearch($found['target']['kind']==='nodes'&&$found['world_id']===1,'result identifies world and target kind');
 $tie=$addNode(10,80);
 $second=MapSearchService::search(1,['category'=>'food','level'=>2,'cursor'=>$found['cursor']]);
 checkSearch((int)$second['target']['data']['id']===$tie,'equal-distance targets advance in stable id order');
 $third=MapSearchService::search(1,['category'=>'food','level'=>2,'cursor'=>$second['cursor']]);
 checkSearch((int)$third['target']['data']['id']===$farther,'each continuation advances to the next farther target');
 $wrapped=MapSearchService::search(1,['category'=>'food','level'=>2,'cursor'=>$third['cursor']]);
 checkSearch((int)$wrapped['target']['data']['id']===$nearest&&$wrapped['wrapped'],'after the last match the search wraps to the nearest');
 checkSearch((int)MapSearchService::search(1,['category'=>'lumber','level'=>2,'cursor'=>$found['cursor']])['target']['data']['id']===$otherType,'changed criteria reset the cursor');
 $db->execute('UPDATE field_objects SET resource_amount=0 WHERE id=?',[$tie]);
 checkSearch((int)MapSearchService::search(1,['category'=>'food','level'=>2,'cursor'=>$found['cursor']])['target']['data']['id']===$farther,'continuation skips targets depleted after the first search');
 foreach(['invalid',str_repeat('x',1025),['bad'],base64_encode('[1,2]')] as $badCursor){try{MapSearchService::search(1,['category'=>'food','level'=>2,'cursor'=>$badCursor]);throw new RuntimeException('Invalid cursor accepted');}catch(DomainException){checkSearch(true,'invalid cursor rejected');}}
 $db->execute('DELETE FROM field_objects WHERE id=?',[$nearest]);
 checkSearch((int)MapSearchService::search(1,['category'=>'food','level'=>2,'cursor'=>$found['cursor']])['target']['data']['id']===$farther,'continuation survives removal of the previously shown object');
 checkSearch((int)MapSearchService::search(1,['category'=>'food','level'=>2])['target']['data']['id']===$farther,'removed targets no longer appear');
 checkSearch(MapSearchService::search(1,['category'=>'gems','level'=>1])['target']===null,'no matching target returns an explicit empty result');
 $db->execute("INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current,monster_type,expires_at) VALUES(1,20209901,18,10,100,'solo',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY)),(1,20209901,16,10,0,'solo',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY)),(1,20209901,15,10,100,'solo',DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY))");
 $monster=MapSearchService::search(1,['category'=>'solo','level'=>1])['target'];
 checkSearch($monster['kind']==='monsters'&&(int)$monster['data']['coord_x']===18&&$monster['data']['definition']['type']==='solo','solo search excludes defeated and expired monsters');
 $db->execute('INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current) VALUES(1,20209901,14,11,100)');
 checkSearch((int)MapSearchService::search(1,['category'=>'solo','level'=>1])['target']['data']['coord_x']===14,'permanent frontier monsters without expiry remain searchable');
 checkSearch(MapSearchService::search(1,['category'=>'rally','level'=>1])['target']===null,'rally search never returns solo monsters');
 $db->execute("INSERT INTO worlds(id,name,slug,status,map_size,map_seed) VALUES(2,'Other','search-other','running',256,91)");
 $db->execute("INSERT INTO field_objects(world_id,coord_x,coord_y,object_type,level,resource_amount,resource_max,expires_at) VALUES(2,10,11,1,2,10000,10000,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))");
 checkSearch((int)MapSearchService::search(1,['category'=>'food','level'=>2])['target']['data']['id']===$farther,'targets in another world cannot leak');
 $db->execute("UPDATE world_land_zones SET status='locked' WHERE world_id=1 AND zone_key='center'");LandProgressService::invalidate(1);
 $mapSize=(int)$db->query('SELECT map_size FROM worlds WHERE id=1')->fetchColumn();$locked=$addNode(intdiv($mapSize,2),intdiv($mapSize,2),5,1);
 checkSearch(MapSearchService::search(1,['category'=>'gems','level'=>1])['target']===null,'locked center is excluded');
 checkSearch(MapSearchService::search(1,['category'=>'lumber','level'=>2])['target']['data']['id']==$otherType,'resource categories remain distinct');
 $token=str_repeat('e',64);
 $db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,user_agent,expires_at,active_world_id) VALUES(1,?,?,'127.0.0.1','search test',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),1)",[$token,str_repeat('f',64)]);
 $base=$fixture->serve('\\Conquer\\Api\\Handlers\\MapSearchHandler::search([]);');
 $get=static function(string $query,bool $auth=true)use($base,$token):array{
  $curl=curl_init($base.'/?'.$query);curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_HTTPHEADER=>$auth?['Cookie: conquer_session='.$token]:[]]);
  $raw=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);return [$status,json_decode((string)$raw,true,512,JSON_THROW_ON_ERROR)];
 };
 checkSearch($get('',false)[0]===401,'HTTP endpoint requires authentication');
 checkSearch($get('category=solo&level=999')[0]===422,'HTTP endpoint rejects invalid levels');
 checkSearch(count($get('')[1]['data']['categories'])===7,'authenticated catalog endpoint returns seven categories');
 checkSearch((int)$get('category=food&level=2')[1]['data']['target']['data']['id']===$farther,'authenticated search selects the correct target');
 $continued=$get(http_build_query(['category'=>'food','level'=>2,'cursor'=>$found['cursor']]));
 checkSearch($continued[0]===200&&(int)($continued[1]['data']['target']['data']['id']??0)===$farther,'HTTP continuation accepts the returned cursor: '.json_encode($continued));
 echo "ALL MAP SEARCH CHECKS PASSED\n";
}finally{$fixture->close();}
