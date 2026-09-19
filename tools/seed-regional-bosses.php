<?php
declare(strict_types=1);
/** Adds at most one missing regional boss per zone, without replacing existing targets. */
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));
$config=require ROOT_DIR.'/config/database.php';
if(!in_array($config['host']??'',['localhost','127.0.0.1'],true))exit("Local database only.\n");
require ROOT_DIR.'/src/Bootstrap.php';\Conquer\Bootstrap::init(ROOT_DIR);
use Conquer\Db\Connection;
use Conquer\Game\World\WorldSettings;
use Conquer\Game\Map\{WorldPlacement,WorldTerrain,MonsterData};
$apply=in_array('--apply',$argv,true);$db=Connection::getInstance();$output=[];
foreach($db->query("SELECT id FROM worlds WHERE status IN ('open','running')")->fetchAll() as $world){
 $id=(int)$world['id'];$cfg=WorldSettings::get($id)['settings'];
 if(!$cfg['enabled']||!WorldSettings::inWindow($cfg,time())||($cfg['monster_weights']['Deathkar']??0)<=0||$cfg['monster_chance_pct']<=0)continue;
 $db->transaction(static function($db)use($id,$cfg,$apply,&$output){
  $size=WorldPlacement::lockWorld($db,$id);$count=(int)$db->query('SELECT COUNT(*) FROM field_monsters WHERE world_id=?',[$id])->fetchColumn();
  $limit=min($cfg['monster_limit'],(int)floor($size*$size*$cfg['monster_density_pct']/100));
  $level=max(1,(int)$cfg['monster_level_min']);if($level>min(10,$cfg['monster_level_max']))return;
  foreach(['forest'=>20202400,'ice'=>20202100,'sand'=>20202200,'lava'=>20202300] as $biome=>$base){
   $present=$db->query('SELECT id,monster_code,coord_x,coord_y FROM field_monsters WHERE world_id=? AND monster_code BETWEEN ? AND ? AND hp_current>0 AND (expires_at>UTC_TIMESTAMP() OR (expires_at IS NULL AND spawned_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL '.(int)$cfg['monster_lifetime_hours'].' HOUR))) ORDER BY id LIMIT 1',[$id,$base+1,$base+10])->fetch();
   if($present){$output[]=['world'=>$id,'zone'=>$biome,'id'=>(int)$present['id'],'name'=>MonsterData::get((int)$present['monster_code'])['name'],'x'=>(int)$present['coord_x'],'y'=>(int)$present['coord_y'],'status'=>'already_present'];continue;}
   if($count>=$limit){$output[]=['world'=>$id,'zone'=>$biome,'status'=>'population_limit'];continue;}
   $spot=null;
   for($attempt=0;$attempt<1000;$attempt++){$x=random_int(1,$size-2);$y=random_int(1,$size-2);if(WorldTerrain::biomeAt($x,$y)===$biome&&WorldPlacement::canPlace($db,$id,'boss',$x,$y)){$spot=[$x,$y];break;}}
   if(!$spot){$output[]=['world'=>$id,'zone'=>$biome,'status'=>'no_free_land'];continue;}
   $def=MonsterData::get($base+$level);
   if($apply)$db->execute("INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current,monster_type,expires_at) VALUES(?,?,?,?,?,'rally',?)",[$id,$base+$level,$spot[0],$spot[1],$def['stats']['hp']*$def['amount'],gmdate('Y-m-d H:i:s',time()+$cfg['monster_lifetime_hours']*3600)]);
   $count++;$output[]=['world'=>$id,'zone'=>$biome,'name'=>$def['name'],'level'=>$level,'x'=>$spot[0],'y'=>$spot[1],'status'=>$apply?'spawned':'preview'];
  }
 });
}
echo json_encode($output,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
