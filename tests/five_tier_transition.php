<?php
declare(strict_types=1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';
use Conquer\Db\Connection;
use Conquer\Game\City\{TroopTierTransition,CityState,TroopTrainer};
use Conquer\Game\Hospital\HospitalService;
function transitionCheck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);}
$fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();
try{
 $db->execute("INSERT INTO players(id,username,email,password_hash)VALUES(1,'Transition','transition@tests.invalid','unused')");
 $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y)VALUES(1,1,1,'Transition',65,65)");
 foreach(CityState::BUILDING_CODES as $b)$db->execute('INSERT INTO city_buildings(city_id,building_code,level)VALUES(1,?,1)',[$b]);
 $db->execute('INSERT INTO city_troops(city_id,troop_code,count)VALUES(1,50100501,10),(1,50100601,20),(1,50101001,30)');
 $db->execute("INSERT INTO troop_queue(city_id,troop_code,count,barrack_slot,started_at,finishes_at,cost_json)VALUES(1,50100701,3482,1,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY),'{\"food\":123,\"lumber\":456,\"stone\":0,\"gold\":0}')");
 $queue=$db->query('SELECT * FROM troop_queue')->fetch();
 $db->execute("INSERT INTO troop_formations(player_id,slot,name,troops_json)VALUES(1,1,'Legacy','{\"50100501\":3,\"50100601\":7,\"50201001\":9}')");
 $old=json_decode(file_get_contents(ROOT_DIR.'/data/balance-history/monsters-t10-20260929.json'),true)['monsters'];
 $source=current(array_filter($old,static fn($m)=>$m['name']==='Orc'&&$m['level']===3));
 $spawns=json_decode(file_get_contents(ROOT_DIR.'/data/world_spawn.json'),true)['monsters'];
 $spawn=current(array_filter($spawns,static fn($m)=>$m['monster']==='Orc'&&$m['level']===3));
 $oldHp=(int)round($source['amount']*$source['stats']['hp']/2);
 $db->execute("INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current,monster_type,expires_at)VALUES(1,?,80,80,?,'solo',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))",[$spawn['code'],$oldHp]);
 $preview=TroopTierTransition::run($db);
 transitionCheck(count($preview['changes'])===5,'Plan includes two troop merges, saved formation, queue and living monster');
 transitionCheck($db->query('SELECT * FROM troop_queue')->fetch()===$queue,'Preview changes nothing');
 $backedUp=false;
 TroopTierTransition::run($db,true,static function($snapshot)use(&$backedUp,$db,$queue){transitionCheck($db->query('SELECT * FROM troop_queue')->fetch()===$queue,'Backup precedes every write');$backedUp=count($snapshot['changes'])===5;});
 transitionCheck($backedUp,'Complete before-state backed up');
 transitionCheck($db->query('SELECT troop_code,count FROM city_troops')->fetchAll() === [['troop_code'=>50100501,'count'=>60]],'Higher tiers merge into T5 without losing units');
 $after=$db->query('SELECT * FROM troop_queue')->fetch();$expected=$queue;$expected['troop_code']=50100501;
 transitionCheck($after===$expected,'Queued unit identity changes; count, original payment and timer remain identical');
 transitionCheck(json_decode($db->query('SELECT troops_json FROM troop_formations')->fetchColumn(),true)===[50100501=>10,50200501=>9],'Saved formation merges each troop type correctly');
 $new=\Conquer\Game\Map\MonsterData::definition((int)$spawn['code']);
 $hp=(int)$db->query('SELECT hp_current FROM field_monsters')->fetchColumn();
 transitionCheck(abs($hp/($new['amount']*$new['stats']['hp'])-.5)<.0001,'Spawn aliases and remaining monster health percentage preserved');
 transitionCheck(TroopTierTransition::run($db,true)['already_applied'],'Re-running cannot duplicate troops or scale health again');
 $db->execute('UPDATE troop_queue SET finishes_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND)');
 TroopTrainer::processQueue($db,1);TroopTrainer::processQueue($db,1);
 transitionCheck((int)$db->query('SELECT count FROM city_troops WHERE troop_code=50100501')->fetchColumn()===3542,'Converted paid queue finishes once');
 HospitalService::addWounded(1,[50100901=>8]);
 $db->execute('UPDATE hospital_wounded SET healing_count=8,healing_ends_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND)');
 HospitalService::processHealed(1);HospitalService::processHealed(1);
 transitionCheck((int)$db->query('SELECT count FROM city_troops WHERE troop_code=50100501')->fetchColumn()===3550,'Historical hospital batch returns to T5 exactly once');
 echo "PASS: backed-up migration, counts, paid timers, saved formations, monster health, historical healing and repeated runs.\n";
}finally{$fixture->close();}

