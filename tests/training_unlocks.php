<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
date_default_timezone_set('UTC');define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';
use Conquer\Db\Connection;
use Conquer\Game\City\{CityState,TroopData,TroopTrainer};
use Conquer\Game\Research\ResearchProcessor;
use Conquer\Game\Defense\DefenseService;
function unlockCheck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);}
function unlockReject(callable $fn):void{try{$fn();}catch(DomainException|RuntimeException $e){return;}throw new LogicException('Locked action accepted');}
$fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();
try{
 $db->execute("INSERT INTO players(id,username,email,password_hash)VALUES(1,'UnlockFixture','unlock@tests.invalid','unused')");
 $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,castle_level,food,lumber,stone,gold,last_resource_update)VALUES(1,1,1,'Unlocks',65,65,30,100000000,100000000,100000000,100000000,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))");
 foreach(CityState::BUILDING_CODES as $code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level)VALUES(1,?,30)',[$code]);
 foreach(TroopData::all() as $code=>$troop){
  $research=$troop['unlock_research'];$academy=$troop['unlock_academy'];
  $state=CityState::loadForPlayer(1);
  if($research){
   unlockCheck(!TroopData::isUnlocked($code,30,30,30,[]),'High buildings never bypass research');
   $before=$db->query('SELECT food,lumber,stone,gold FROM cities WHERE id=1')->fetch();
   unlockReject(fn()=>TroopTrainer::train($state['city'],$state['buildings'],$code,1));
   unlockCheck($before===$db->query('SELECT food,lumber,stone,gold FROM cities WHERE id=1')->fetch(),'Rejected training spends nothing');
   $db->execute('INSERT INTO player_research(player_id,world_id,research_code,level)VALUES(1,1,?,1)',[$research]);
   $db->execute("UPDATE city_buildings SET level=? WHERE city_id=1 AND building_code='academy'",[$academy-1]);
   $low=CityState::loadForPlayer(1);unlockReject(fn()=>TroopTrainer::train($low['city'],$low['buildings'],$code,1));
  }
  $db->execute("UPDATE city_buildings SET level=? WHERE city_id=1 AND building_code='academy'",[$academy]);
  $levels=TroopData::researchLevels(1,1);$state=CityState::loadForPlayer(1);
  $defs=array_column(TroopData::forCity($state,[],$levels,1),null,'code');
  unlockCheck($defs[$code]['unlocked'],'API agrees with exact academy/research threshold');
  TroopTrainer::train($state['city'],$state['buildings'],$code,1);
  $db->execute('UPDATE troop_queue SET finishes_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE city_id=1 AND is_processed=0');
  TroopTrainer::processQueue($db,1);TroopTrainer::processQueue($db,1);
 }
 unlockCheck((int)$db->query('SELECT SUM(count) FROM city_troops')->fetchColumn()===15,'Each of 15 batches credited once');
 $state=CityState::loadForPlayer(1);
 foreach([50100601,50201001,50301101] as $code)unlockReject(fn()=>TroopTrainer::train($state['city'],$state['buildings'],$code,1));
 unlockReject(fn()=>DefenseService::promote(1,1,50100501,1));
 $db->execute("DELETE FROM player_research WHERE research_code='warrior'");
 unlockReject(fn()=>DefenseService::promote(1,1,50100101,1));
 $db->execute("INSERT INTO player_research(player_id,world_id,research_code,level)VALUES(1,1,'warrior',1)");
 $promotion=DefenseService::promote(1,1,50100101,1);DefenseService::cancelPromotion(1,$promotion['promotion_id']);
 $db->execute("INSERT INTO worlds(id,name,slug,status,map_size)VALUES(2,'Other','other-unlocks','running',256)");
 $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,food,lumber,stone,gold)VALUES(2,1,2,'Other',65,65,100,100,100,100)");
 $db->execute("INSERT INTO research_queue(player_id,world_id,research_code,level_to,started_at,finishes_at)VALUES(1,2,'warrior',1,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))");
 $before=$db->query('SELECT food,lumber,stone,gold FROM cities WHERE id=2')->fetch();
 ResearchProcessor::processQueue(1,2);
 unlockCheck($before===$db->query('SELECT food,lumber,stone,gold FROM cities WHERE id=2')->fetch(),'Reinstated pending research is not refunded');
 unlockCheck(!TroopData::isUnlocked(50100201,30,30,30,TroopData::researchLevels(1,2)),'Other world cannot borrow completed research');
 $db->execute('UPDATE research_queue SET finishes_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE world_id=2');
 ResearchProcessor::processQueue(1,1);unlockCheck(!TroopData::researchLevels(1,2),'Processing world one never settles world two');
 ResearchProcessor::processQueue(1,2);ResearchProcessor::processQueue(1,2);
 unlockCheck(TroopData::isUnlocked(50100201,30,30,30,TroopData::researchLevels(1,2)),'Research completion unlocks the correct world');
 echo "PASS: research/academy boundaries for all fifteen units, server rejections, promotion, world isolation and restored research settlement.\n";
}finally{$fixture->close();}

