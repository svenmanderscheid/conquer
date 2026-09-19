<?php
declare(strict_types=1);
/** Validate enlarged boss footprints; relocate only idle overlapping targets. */
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));
$cfg=require ROOT_DIR.'/config/database.php';
if(!in_array($cfg['host']??'',['localhost','127.0.0.1'],true))exit("Local database only.\n");
require ROOT_DIR.'/src/Bootstrap.php';\Conquer\Bootstrap::init(ROOT_DIR);
use Conquer\Db\Connection;
use Conquer\Game\Map\{WorldPlacement,MonsterData};
$db=Connection::getInstance();$apply=in_array('--apply',$argv,true);$report=[];
foreach($db->query('SELECT id FROM worlds')->fetchAll() as $world){
 $worldId=(int)$world['id'];
 $db->transaction(static function($db)use($worldId,$apply,&$report){
  WorldPlacement::lockWorld($db,$worldId);
  foreach($db->query('SELECT * FROM field_monsters WHERE world_id=? AND hp_current>0 ORDER BY id FOR UPDATE',[$worldId])->fetchAll() as $monster){
   $id=(int)$monster['id'];$code=(int)$monster['monster_code'];if(WorldPlacement::monsterKind($code)!=='boss')continue;
   $x=(int)$monster['coord_x'];$y=(int)$monster['coord_y'];$def=MonsterData::definition($code);
   $entry=['world'=>$worldId,'id'=>$id,'name'=>$def['name'],'x'=>$x,'y'=>$y,'footprint'=>2];
   if(WorldPlacement::canPlace($db,$worldId,'boss',$x,$y,$id)){$report[]=$entry+['status'=>'valid'];continue;}
   $busy=$db->query("SELECT id FROM marches WHERE world_id=? AND ((target_x=? AND target_y=?) OR (target_type=3 AND target_id=?)) AND state IN ('marching','resolving','returning') LIMIT 1 FOR UPDATE",[$worldId,$x,$y,$id])->fetchColumn();
   $rally=$db->query("SELECT id FROM rallies WHERE world_id=? AND target_x=? AND target_y=? AND status IN ('gathering','marching','returning') LIMIT 1 FOR UPDATE",[$worldId,$x,$y])->fetchColumn();
   if($busy!==false||$rally!==false){$report[]=$entry+['status'=>'busy_deferred'];continue;}
   $spot=WorldPlacement::findNear($db,$worldId,'boss',$x,$y,$id,24,$def['biome']);
   if(!$spot){$report[]=$entry+['status'=>'no_free_land'];continue;}
   if($apply)$db->execute('UPDATE field_monsters SET coord_x=?,coord_y=? WHERE id=? AND world_id=?',[$spot[0],$spot[1],$id,$worldId]);
   $report[]=$entry+['destination'=>$spot,'status'=>$apply?'moved':'preview'];
  }
 });
}
echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
