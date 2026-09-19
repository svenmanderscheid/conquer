<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
date_default_timezone_set('UTC');define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';
use Conquer\Db\Connection;
use Conquer\Game\City\CityState;
use Conquer\Game\City\TroopData;
use Conquer\Game\City\TroopTrainer;
use Conquer\Game\Operation;
use Conquer\Game\Defense\DefenseService;
use Conquer\Game\Kingdom\KingdomService;
use Conquer\Game\Inventory\InventoryService;
$fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();
function checkTraining(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
function rejectTraining(callable $fn):void{try{$fn();}catch(DomainException|RuntimeException $e){return;}throw new LogicException('Invalid training accepted');}
function startTraining(int $code,int $count=20,?int $slot=null):array{$s=CityState::loadForPlayer(1);TroopTrainer::train($s['city'],$s['buildings'],$code,$count,$slot);return ['message'=>'started'];}
function queued():int{global $db;return (int)$db->query('SELECT COUNT(*) FROM troop_queue WHERE city_id=1 AND is_processed=0')->fetchColumn();}
try{
 $db->execute("INSERT INTO players(id,username,email,password_hash)VALUES(1,'TrainingFixture','training@tests.invalid','unused')");
 $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,castle_level,food,lumber,stone,gold)VALUES(1,1,1,'Training',65,65,30,100000000,100000000,100000000,100000000)");
 foreach(CityState::BUILDING_CODES as $code)if(!in_array($code,['stable','archery_range'],true))$db->execute('INSERT INTO city_buildings(city_id,building_code,level)VALUES(1,?,30)',[$code]);
 $migration=file_get_contents(ROOT_DIR.'/migrations/0090_training_buildings.sql');
 \Conquer\Db\MigrationSql::apply($db->getPdo(),$migration);\Conquer\Db\MigrationSql::apply($db->getPdo(),$migration);
 checkTraining((int)$db->query("SELECT SUM(level) FROM city_buildings WHERE city_id=1 AND building_code IN('archery_range','stable')")->fetchColumn()===60,'migration preserves former barrack progress and is repeatable');
 checkTraining(count(TroopData::all())===30&&TroopData::get(50101101)===null,'three types support T1–T10; T11 is excluded');
 foreach([1=>[94,14,20,20,13],2=>[99,22,15,14,21],3=>[94,20,15,14,19]] as $type=>$stats){$t=TroopData::get(50001001+$type*100000);checkTraining(array_map(fn($k)=>$t[$k],['power','attack','defense','hp','lethality'])===$stats&&$t['carry']===379&&$t['speed']===11,'T10 reference values for type '.$type);}
 rejectTraining(fn()=>startTraining(50100101,20,2));rejectTraining(fn()=>startTraining(50101101));
 $receipt=['action'=>'troops.train','operation_key'=>'training_replay_0001','world_id'=>1,'troop_code'=>50100101,'count'=>20,'barrack_slot'=>1];
 Operation::run(1,$receipt,fn()=>startTraining(50100101));$food=$db->query('SELECT food FROM cities WHERE id=1')->fetchColumn();
 Operation::run(1,$receipt,fn()=>startTraining(50100101));
 checkTraining(queued()===1&&$food===$db->query('SELECT food FROM cities WHERE id=1')->fetchColumn(),'retry charges once and creates one training batch');
 startTraining(50200101);startTraining(50300101);
 checkTraining(queued()===3&&$db->query('SELECT DISTINCT barrack_slot FROM troop_queue ORDER BY barrack_slot')->fetchAll(PDO::FETCH_COLUMN)===[1,2,3],'all three schools train in parallel');
 rejectTraining(fn()=>startTraining(50200201));rejectTraining(fn()=>DefenseService::promote(1,1,50100101,1));
 $s=CityState::loadForPlayer(1);$defs=TroopData::forCity($s,\Conquer\Game\Research\BuffEngine::getBuffs(1),[],1);
 checkTraining(count(array_filter($defs,fn($t)=>$t['unlocked']))===30,'all tiers unlock with each school and town center, without research');
 $s['buildings']['archery_range']['level']=1;$defs=TroopData::forCity($s,[],[],1);
 checkTraining(!$defs[19]['unlocked']&&TroopData::isUnlocked(50101001,30,30),'school-level gates are independent');
 $qid=(int)$db->query('SELECT id FROM troop_queue WHERE city_id=1 AND barrack_slot=2')->fetchColumn();
 InventoryService::addItems(1,10103003,5);
 $speed=['action'=>'inventory.use','item_code'=>10103003,'queue_type'=>'training','queue_id'=>$qid,'operation_key'=>'training_speedup_001','expected_world_id'=>1];
 $first=KingdomService::action(1,$speed);$second=KingdomService::action(1,$speed);
 checkTraining((int)$db->query('SELECT quantity FROM player_inventory WHERE player_id=1 AND item_code=10103003')->fetchColumn()===4,'speedup retry consumes exactly one item');
 CityState::loadForPlayer(1);checkTraining(queued()===2,'speedup finishes only the selected school');
 $db->execute('UPDATE troop_queue SET finishes_at=UTC_TIMESTAMP() WHERE city_id=1');TroopTrainer::processQueue($db,1);TroopTrainer::processQueue($db,1);
 checkTraining((int)$db->query('SELECT SUM(count) FROM city_troops WHERE city_id=1')->fetchColumn()===60,'queue settlement credits every batch once');
 Operation::run(1,$receipt,fn()=>startTraining(50100101));checkTraining(queued()===0,'old request cannot restart after its batch has completed');
 rejectTraining(fn()=>Operation::run(1,array_replace($receipt,['count'=>21]),fn()=>startTraining(50100101,21)));
 startTraining(50200101);$promotion=DefenseService::promote(1,1,50100101,2);
 checkTraining(DefenseService::hasPromotion(1,1)&&!DefenseService::hasPromotion(1,3),'infantry promotion coexists with archer training');
 rejectTraining(fn()=>startTraining(50100101));startTraining(50300101);
 checkTraining(queued()===2,'promotion occupies only its own school');
 $cancelId=(int)$db->query('SELECT id FROM troop_queue WHERE city_id=1 AND barrack_slot=3 AND is_processed=0')->fetchColumn();
 $db->execute('UPDATE troop_queue SET cost_json=? WHERE id=?',[json_encode(['food'=>17,'lumber'=>11,'stone'=>0,'gold'=>3]),$cancelId]);
 $refund=TroopTrainer::cancel(1,$cancelId);checkTraining($refund['refunded_food']<=17&&$refund['refunded_lumber']<=11&&$refund['refunded_gold']<=3,'cancellation refunds only the recorded amount actually paid');
 rejectTraining(fn()=>TroopTrainer::cancel(1,$cancelId));checkTraining(queued()===1,'cancelled batches cannot be refunded twice');
 echo "ALL TRAINING BUILDING CHECKS PASSED\n";
}finally{$fixture->close();}
