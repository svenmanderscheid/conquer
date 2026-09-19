<?php
declare(strict_types=1);
/** Existing inventory command must bind speedups and retries to one owned queue. */
if(PHP_SAPI!=='cli')exit(1);
date_default_timezone_set('UTC');define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';
use Conquer\Db\Connection;
use Conquer\Game\City\CityState;
use Conquer\Game\Kingdom\KingdomService;
use Conquer\Game\Inventory\InventoryService;
use Conquer\Game\World\WorldContext;
function qsCheck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
function qsReject(callable $fn,string $label):void{try{$fn();}catch(DomainException){qsCheck(true,$label);return;}throw new RuntimeException('Accepted: '.$label);}
$fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();
try{
 WorldContext::bind(1);
 foreach([1,2] as $id){
  $db->execute("INSERT INTO players(id,username,email,password_hash)VALUES(?,?,?,'unused')",[$id,'Speedup'.$id,'speedup'.$id.'@invalid.test']);
  $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y)VALUES(?,?,1,'Speedup city',?,65)",[$id,$id,65+$id*5]);
  foreach(CityState::BUILDING_CODES as $code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level)VALUES(?,?,7)',[$id,$code]);
 }
 $db->execute("INSERT INTO worlds(id,name,slug,map_size,status)VALUES(2,'Speedup other','speedup-other',256,'open')");
 $items=[];foreach(InventoryService::allDefs() as $code=>$item)if($item['category']==='speedup'&&in_array($item['subcategory'],['generic','building','research','training'],true)&&$item['duration_seconds']===60){$items[$item['subcategory']]=(int)$code;InventoryService::addItems(1,(int)$code,10);}
 qsCheck(count($items)===4,'generic and all three specialised one-minute items exist');
 $jobs=[];
 foreach([1,2] as $city){$db->execute("INSERT INTO building_queue(city_id,building_code,level_to,started_at,finishes_at)VALUES(?,'farm',8,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 HOUR))",[$city]);$jobs['building'][$city]=$db->lastInsertId();}
 foreach([1,2] as $world){$db->execute("INSERT INTO research_queue(player_id,world_id,research_code,level_to,started_at,finishes_at)VALUES(1,?,'food_production',1,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 HOUR))",[$world]);$jobs['research'][$world]=$db->lastInsertId();}
 foreach([1,2] as $city){$db->execute("INSERT INTO troop_queue(city_id,troop_code,count,barrack_slot,started_at,finishes_at)VALUES(?,50100101,20,1,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 HOUR))",[$city]);$jobs['training'][$city]=$db->lastInsertId();}
 foreach(['building'=>'building_queue','research'=>'research_queue','training'=>'troop_queue'] as $type=>$table){
  $id=$jobs[$type][1];$other=$jobs[$type][2];$end=fn($qid)=>(string)$db->query("SELECT finishes_at FROM $table WHERE id=?",[$qid])->fetchColumn();$otherEnd=$end($other);
  foreach([$type,'generic'] as $sub){
   $code=$items[$sub];$before=$end($id);$stock=(int)$db->query('SELECT quantity FROM player_inventory WHERE player_id=1 AND item_code=?',[$code])->fetchColumn();
   $body=['action'=>'inventory.use','queue_type'=>$type,'queue_id'=>$id,'item_code'=>$code,'operation_key'=>'queue_speedup_'.$type.'_'.$sub,'expected_world_id'=>1];
   KingdomService::action(1,$body);KingdomService::action(1,$body);
   qsCheck(strtotime($before)-strtotime($end($id))===60,'one '.$sub.' item shortens '.$type.' by 60 seconds even on retry');
   qsCheck((int)$db->query('SELECT quantity FROM player_inventory WHERE player_id=1 AND item_code=?',[$code])->fetchColumn()===$stock-1,'retry consumes exactly one '.$sub.' item');
   qsCheck($end($other)===$otherEnd,'other city or world '.$type.' is untouched');
   qsReject(fn()=>KingdomService::action(1,array_replace($body,['queue_id'=>$other])),'receipt cannot target a different queue');
  }
  $reject=['action'=>'inventory.use','queue_type'=>$type,'queue_id'=>$other,'item_code'=>$items['generic'],'operation_key'=>'foreign_queue_speedup_'.$type,'expected_world_id'=>1];
  qsReject(fn()=>KingdomService::action(1,$reject),'foreign '.$type.' queue is rejected');
  $wrong=$type==='research'?'building':'research';
  qsReject(fn()=>KingdomService::action(1,array_replace($reject,['queue_id'=>$id,'item_code'=>$items[$wrong]])),'wrong specialised item is rejected for '.$type);
  $db->execute("UPDATE $table SET finishes_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 SECOND) WHERE id=?",[$id]);
  $finish=['action'=>'inventory.use','queue_type'=>$type,'queue_id'=>$id,'item_code'=>$items[$type],'operation_key'=>'finish_queue_speedup_'.$type,'expected_world_id'=>1];
  KingdomService::action(1,$finish);KingdomService::action(1,$finish);
  qsCheck((int)$db->query("SELECT is_processed FROM $table WHERE id=?",[$id])->fetchColumn()===1,'finishing '.$type.' settles once and can safely be replayed');
  qsReject(fn()=>KingdomService::action(1,array_replace($finish,['operation_key'=>'completed_queue_speedup_'.$type])),'completed '.$type.' queue rejects a new speedup');
 }

 foreach(['building'=>'building_queue','research'=>'research_queue','training'=>'troop_queue'] as $type=>$table){
  if($type==='building')$db->execute("INSERT INTO building_queue(city_id,building_code,level_to,started_at,finishes_at)VALUES(1,'lumbermill',8,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 5 MINUTE))");
  elseif($type==='research')$db->execute("INSERT INTO research_queue(player_id,world_id,research_code,level_to,started_at,finishes_at)VALUES(1,1,'lumber_production',1,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 5 MINUTE))");
  else $db->execute("INSERT INTO troop_queue(city_id,troop_code,count,barrack_slot,started_at,finishes_at)VALUES(1,50200101,20,2,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 5 MINUTE))");
  $id=$db->lastInsertId();$code=$items[$type];
  $end=fn()=>(string)$db->query("SELECT finishes_at FROM $table WHERE id=?",[$id])->fetchColumn();
  $stock=fn()=>(int)$db->query('SELECT quantity FROM player_inventory WHERE player_id=1 AND item_code=?',[$code])->fetchColumn();
  $before=$end();$beforeStock=$stock();
  $batch=['action'=>'inventory.use','queue_type'=>$type,'queue_id'=>$id,'item_code'=>$code,'quantity'=>3,'operation_key'=>'batch_queue_speedup_'.$type,'expected_world_id'=>1];
  $first=KingdomService::action(1,$batch);$retry=KingdomService::action(1,$batch);
  qsCheck(strtotime($before)-strtotime($end())===180,'three items atomically shorten '.$type.' by 180 seconds');
  qsCheck($stock()===$beforeStock-3,'batch retry consumes exactly three '.$type.' items');
  qsCheck(($first['result']['quantity']??null)===3&&($first['result']['seconds']??null)===180,'batch response reports quantity and total seconds for '.$type);
  qsCheck(($retry['result']['quantity']??null)===3,'batch retry returns the original '.$type.' receipt');
  qsReject(fn()=>KingdomService::action(1,array_replace($batch,['quantity'=>2])),'receipt binds quantity for '.$type);
  $afterBatch=$end();$afterStock=$stock();
  qsReject(fn()=>KingdomService::action(1,array_replace($batch,['quantity'=>3,'operation_key'=>'excess_time_'.$type])),'quantity beyond remaining '.$type.' time is rejected');
  qsCheck($end()===$afterBatch&&$stock()===$afterStock,'excess '.$type.' quantity rolls back without spending');
 }

 $validationQueue=$db->lastInsertId();
 $validation=['action'=>'inventory.use','queue_type'=>'training','queue_id'=>$validationQueue,'item_code'=>$items['training'],'operation_key'=>'invalid_quantity_training','expected_world_id'=>1];
 qsReject(fn()=>KingdomService::action(1,$validation+['quantity'=>0]),'zero speedup quantity is rejected');
 qsReject(fn()=>KingdomService::action(1,array_replace($validation,['quantity'=>10001])),'speedup quantity above 10000 is rejected');
 $db->execute("INSERT INTO troop_queue(city_id,troop_code,count,barrack_slot,started_at,finishes_at)VALUES(1,50300101,20,3,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 HOUR))");$stockQueue=$db->lastInsertId();
 $available=(int)$db->query('SELECT quantity FROM player_inventory WHERE player_id=1 AND item_code=?',[$items['training']])->fetchColumn();
 $stockEnd=(string)$db->query('SELECT finishes_at FROM troop_queue WHERE id=?',[$stockQueue])->fetchColumn();
 qsReject(fn()=>KingdomService::action(1,['action'=>'inventory.use','queue_type'=>'training','queue_id'=>$stockQueue,'item_code'=>$items['training'],'quantity'=>$available+1,'operation_key'=>'stock_overrun_training','expected_world_id'=>1]),'batch cannot exceed owned stock');
 qsCheck((string)$db->query('SELECT finishes_at FROM troop_queue WHERE id=?',[$stockQueue])->fetchColumn()===$stockEnd,'stock rejection leaves queue unchanged');
 $resourceCode=0;foreach(InventoryService::allDefs() as $candidate=>$def)if(($def['category']??'')==='resource_pack'){$resourceCode=(int)$candidate;break;}
 InventoryService::addItems(1,$resourceCode,2);
 qsReject(fn()=>KingdomService::action(1,['action'=>'inventory.use','item_code'=>$resourceCode,'quantity'=>2,'expected_world_id'=>1]),'non-speedup items remain single-use');
 echo "ALL QUEUE SPEEDUP CHECKS PASSED (disposable database).\n";
}finally{$fixture->close();}
