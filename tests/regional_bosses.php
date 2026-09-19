<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';
use Conquer\Db\Connection;
use Conquer\Game\Map\{MonsterData,WorldTerrain,WorldPlacement};
use Conquer\Game\World\{WorldSettings,WorldSpawnService};
function checkBoss(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);echo "PASS $message\n";}
$fixture=null;
try{
 foreach(['forest'=>[50,50,20202400,'grumwald'],'ice'=>[200,50,20202100,'frostgrimm'],'sand'=>[50,200,20202200,'sandmaul'],'lava'=>[200,200,20202300,'glutramm']] as $biome=>[$x,$y,$base,$art]){
  checkBoss(WorldTerrain::biomeAt($x,$y)===$biome,"Biome $biome matches map core");
  for($level=1;$level<=10;$level++){
   $code=WorldSpawnService::regionalMonsterCode(20200500+$level,$x,$y);$def=MonsterData::get($code);
   checkBoss($code===$base+$level&&$def['type']==='rally'&&$def['biome']===$biome&&$def['level']===$level,"$biome level $level has distinct rally definition");
   checkBoss(($def['footprint']??1)===2&&WorldPlacement::monsterKind($code)==='boss',"$biome level $level reserves 2 x 2 tiles");
   checkBoss($def['stats']['hp']>0&&$def['stats']['attack']>0&&$def['stats']['defense']>0&&$def['source_amount']>=$def['amount']&&!empty($def['drops'])&&$def['action_point_cost']===25,"$biome level $level keeps regional stats, playable amount and usable rewards");
  }
  checkBoss(is_file(ROOT_DIR.'/assets/art/monsters/'.$art.'.png'),"$art game sprite exists");
 }
 $replacement=MonsterData::get(20200501);
 checkBoss($replacement['name']==='Dämmerhorn'&&$replacement['type']==='rally'&&$replacement['art']==='monsters/daemmerhorn','Existing Deathkar targets become the Dämmerhorn rally boss');
 checkBoss(MonsterData::isActive(20200501)&&is_file(ROOT_DIR.'/assets/art/monsters/daemmerhorn.png'),'Dämmerhorn is active and its game sprite exists');
 checkBoss(WorldSpawnService::regionalMonsterCode(20209901,200,200)===20209901,'Solo monsters remain unchanged');
 checkBoss(WorldSpawnService::regionalMonsterCode(20200601,200,200)===20200601,'Dragon category remains unchanged');
 $fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();$db->execute("UPDATE worlds SET status='running',map_size=256 WHERE id=1");
 $db->transaction(static function($db){
  WorldPlacement::lockWorld($db,1);
  checkBoss(WorldPlacement::footprint('boss',30,30)===[30,30,31,31],'Boss footprint includes east, south and southeast tiles');
  $db->execute("INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current,monster_type) VALUES(1,20202401,30,30,1000,'rally')");$id=$db->lastInsertId();
  checkBoss(WorldPlacement::canPlace($db,1,'boss',30,30,$id),'Boss ignores its own record during placement repair');
  foreach([[30,30],[31,30],[30,31],[31,31]] as [$x,$y]){
   foreach(['monster','boss','resource','city'] as $kind)checkBoss(!WorldPlacement::canPlace($db,1,$kind,$x,$y),$kind.' cannot occupy boss tile '.$x.','.$y);
  }
  checkBoss(!WorldPlacement::canPlace($db,1,'city',32,32),'City candidate lookup detects the boss southeast corner');
  checkBoss(WorldPlacement::canPlace($db,1,'monster',32,31),'An adjacent unoccupied tile remains available');
  checkBoss(!WorldPlacement::canPlace($db,1,'boss',255,30),'Boss cannot protrude past the map edge');
  $shore=null;for($y=50;$y<80&&$shore===null;$y++)for($x=60;$x<90;$x++)if(!WorldTerrain::isWater($x,$y)&&!WorldTerrain::isDryRectangle($x,$y,$x+1,$y+1)){$shore=[$x,$y];break;}
  checkBoss($shore!==null&&!WorldPlacement::canPlace($db,1,'boss',...$shore),'Boss rejects a dry anchor when another footprint tile is water');
  $db->execute('DELETE FROM field_monsters WHERE id=?',[$id]);
 });
 $cfg=WorldSettings::defaults();$cfg['resource_limit']=0;$cfg['monster_limit']=100;$cfg['batch_limit']=100;
 foreach($cfg['monster_weights'] as $key=>$value)$cfg['monster_weights'][$key]=$key==='Deathkar'?100:0;
 $db->execute('INSERT INTO world_spawn_settings(world_id,settings_json) VALUES(1,?)',[json_encode($cfg)]);
 $result=WorldSpawnService::tick(1,true,'test');checkBoss($result[1]['monsters_spawned']===100,'Spawn worker fills bounded mid-tier population');
 $seen=[];$seenDaemmerhorn=false;
 foreach($db->query('SELECT * FROM field_monsters WHERE world_id=1')->fetchAll() as $m){
  $def=MonsterData::get((int)$m['monster_code']);$biome=WorldTerrain::biomeAt((int)$m['coord_x'],(int)$m['coord_y']);$seen[$biome]=true;
  $seenDaemmerhorn=$seenDaemmerhorn||$def['name']==='Dämmerhorn';
  if(($def['name']!=='Dämmerhorn'&&($def['biome']??null)!==$biome)||$m['monster_type']!=='rally'||(int)$m['hp_current']!==$def['stats']['hp']*$def['amount'])throw new RuntimeException('Wrong spawn biome, HP or type');
  if(!WorldPlacement::canPlace($db,1,WorldPlacement::monsterKind((int)$m['monster_code']),(int)$m['coord_x'],(int)$m['coord_y'],(int)$m['id']))throw new RuntimeException('Invalid terrain/overlap');
 }
 checkBoss(count($seen)===4,'All four zones populated with native mid-tier rallies on valid land');
 checkBoss($seenDaemmerhorn,'Dämmerhorn joins the live rally-boss population');
 WorldSpawnService::tick(1,true,'test');checkBoss((int)$db->query('SELECT COUNT(*) FROM field_monsters')->fetchColumn()===100,'Repeated spawn respects population cap');
 echo "ALL REGIONAL BOSS CHECKS PASSED\n";
}finally{if($fixture)$fixture->close();}
