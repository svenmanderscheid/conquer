<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
date_default_timezone_set('UTC');define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';
use Conquer\Db\Connection;
use Conquer\Game\Hospital\HospitalService as H;
use Conquer\Game\Inventory\InventoryService as I;
use Conquer\Game\Kingdom\KingdomService as K;
$fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();$serial=0;
function checkHospital(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
function command(string $act,array $body=[],?string $key=null,int $player=1):array{global $serial;return H::execute($player,array_merge(['action'=>'hospital.'.$act,'operation_key'=>$key??'hospital_test_'.str_pad((string)++$serial,5,'0',STR_PAD_LEFT),'expected_world_id'=>1],$body));}
function rejected(callable $fn):void{try{$fn();throw new RuntimeException('Invalid operation accepted');}catch(DomainException){}}
function stock(string $key):int{global $db;return (int)$db->query("SELECT $key FROM cities WHERE id=1")->fetchColumn();}
function troop(int $code=50100101):int{global $db;return (int)$db->query('SELECT count FROM city_troops WHERE city_id=1 AND troop_code=?',[$code])->fetchColumn();}
function owned(int $code):int{global $db;return (int)$db->query('SELECT quantity FROM player_inventory WHERE player_id=1 AND item_code=?',[$code])->fetchColumn();}
try{
 foreach([1,2]as$pid){
  $db->execute('INSERT INTO players(id,username,email,password_hash,gems)VALUES(?,?,?,?,10000)',[$pid,'Hospital'.$pid,'hospital'.$pid.'@tests.invalid','unused']);
  $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,castle_level,food,lumber,stone,gold)VALUES(?,?,1,'Hospital',?,65,5,1000000,1000000,1000000,1000000)",[$pid,$pid,65+$pid*8]);
  foreach(\Conquer\Game\City\CityState::BUILDING_CODES as$code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level)VALUES(?,?,5)',[$pid,$code]);
 }
 $db->execute('INSERT INTO city_troops(city_id,troop_code,count)VALUES(1,50100101,10),(1,50200101,10)');
 H::addWounded(1,[50100101=>1000,50200101=>500]);H::processHealed(1);
 $h=H::getStatus(1);checkHospital($h['waiting']===1500&&$h['healing']===0&&$h['active']===null&&troop()===10,'new wounds wait without automatic healing');
 $w=$h['wounded'][0];checkHospital($w['resources']===['food'=>5,'lumber'=>3,'stone'=>0,'gold'=>0]&&$w['seconds_per_troop']===1.0,'early troops heal cheaply and use the troop duration');
 $healing=$generic=$wrong=null;
 foreach(I::allDefs()as$code=>$i){if($i['category']!=='speedup')continue;if($i['duration_seconds']===60){if($i['subcategory']==='healing')$healing=$code;if($i['subcategory']==='generic')$generic=$code;}if($i['subcategory']==='building')$wrong=$code;}
 checkHospital($healing!==null&&$generic!==null,'healing and general speedup definitions exist');
 foreach([$healing,$generic,$wrong]as$code)I::addItems(1,$code,10);
 $initial=owned($healing);
 rejected(fn()=>command('speedup',['item_code'=>$healing,'batch_id'=>'missing']));
 checkHospital(owned($healing)===$initial,'waiting wounds cannot consume speedups');
 foreach([[],[50100101=>0],[50100101=>-1],[50100101=>1.2],[50100101=>'2'],[50100101=>true],[50100101=>1001],[99999=>1],[50100101=>10,50200101=>9999]]as$selection)rejected(fn()=>command('heal',['troops'=>$selection]));
 rejected(fn()=>command('heal',['troops'=>[50100101=>1]],null,2));
 rejected(fn()=>command('heal',['troops'=>[50100101=>1],'expected_world_id'=>2]));
 $db->execute('UPDATE cities SET food=0,last_resource_update=UTC_TIMESTAMP() WHERE id=1');
 rejected(fn()=>command('heal',['troops'=>[50100101=>1000]]));
 checkHospital(H::getStatus(1)['waiting']===1500&&troop()===10,'invalid quantities, other owners and insufficient resources leave wounded unchanged');
 $db->execute('UPDATE cities SET food=1000000,last_resource_update=UTC_TIMESTAMP() WHERE id=1');
 $before=stock('food');$start=command('heal',['troops'=>[50100101=>300,50200101=>100]],'hospital_start_receipt');
 checkHospital($start['duration_seconds']===400&&$start['gems_spent']===0&&$start['troops_healed']===0&&troop()===10,'paid healing starts a timer without crediting troops');
 checkHospital(stock('food')===$before-$start['resources_spent']['food']&&H::getStatus(1)['healing']===400,'resource costs are debited once at the start');
 $paid=stock('food');$retry=command('heal',['troops'=>[50100101=>300,50200101=>100]],'hospital_start_receipt');
 checkHospital($retry===$start&&stock('food')===$paid,'lost-response retry does not charge or start again');
 rejected(fn()=>command('heal',['troops'=>[50100101=>1]]));
 $batch=$start['batch_id'];$end=H::getStatus(1)['active']['ends_at'];H::addWounded(1,[50100101=>50]);
 checkHospital(H::getStatus(1)['active']['ends_at']===$end&&H::getStatus(1)['healing']===400,'new injuries do not join or extend the paid batch');
 rejected(fn()=>command('speedup',['item_code'=>$wrong,'batch_id'=>$batch]));
 rejected(fn()=>command('speedup',['item_code'=>$healing,'batch_id'=>'old_batch']));
 $speed=command('speedup',['item_code'=>$healing,'quantity'=>1,'batch_id'=>$batch],'hospital_speed_receipt');
 checkHospital(strtotime($end)-strtotime(H::getStatus(1)['active']['ends_at'])===60&&owned($healing)===$initial-1,'healing speedup reduces the entire batch and consumes exactly one item');
 command('speedup',['item_code'=>$healing,'quantity'=>1,'batch_id'=>$batch],'hospital_speed_receipt');
 checkHospital(owned($healing)===$initial-1,'replayed speedup is not consumed twice');
 $end=H::getStatus(1)['active']['ends_at'];command('speedup',['item_code'=>$generic,'quantity'=>2,'batch_id'=>$batch]);
 checkHospital(strtotime($end)-strtotime(H::getStatus(1)['active']['ends_at'])===120,'general speedups also accelerate healing');
 $end=H::getStatus(1)['active']['ends_at'];K::action(1,['action'=>'inventory.use','item_code'=>$generic,'queue_type'=>'healing']);
 checkHospital(strtotime($end)-strtotime(H::getStatus(1)['active']['ends_at'])===60,'inventory entry point applies general speedups to the same batch');
 $end=H::getStatus(1)['active']['ends_at'];rejected(fn()=>command('speedup',['item_code'=>$healing,'quantity'=>999,'batch_id'=>$batch]));
 checkHospital(H::getStatus(1)['active']['ends_at']===$end,'insufficient inventory rolls back timer changes');
 $paid=stock('food');$gems=(int)$db->query('SELECT gems FROM players WHERE id=1')->fetchColumn();
 rejected(fn()=>command('finish',['batch_id'=>$batch,'max_gems'=>999]));
 checkHospital(H::getStatus(1)['active']['ends_at']===$end&&troop()===10&&stock('food')===$paid&&(int)$db->query('SELECT gems FROM players WHERE id=1')->fetchColumn()===$gems,'crystals cannot complete healing even with sufficient balance');
 $finish=command('speedup',['item_code'=>$healing,'quantity'=>4,'batch_id'=>$batch],'hospital_finish_speedup');
 checkHospital(troop()===310&&H::getStatus(1)['waiting']===1150&&H::getStatus(1)['active']===null&&stock('food')===$paid,'speedups finish only the paid batch without another resource payment');
 command('speedup',['item_code'=>$healing,'quantity'=>4,'batch_id'=>$batch],'hospital_finish_speedup');checkHospital(troop()===310,'completion replay does not credit troops twice');
 $start=command('heal',['troops'=>[50100101=>10]]);
 rejected(fn()=>command('finish',['batch_id'=>$batch,'max_gems'=>999]));
 $db->execute('UPDATE hospital_wounded SET healing_ends_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE city_id=1 AND healing_count>0');H::processHealed(1);H::processHealed(1);
 checkHospital(troop()===320&&H::getStatus(1)['waiting']===1140,'timer completion credits selected troops once and leaves waiting troops untouched');
 $paid=stock('food');$gems=(int)$db->query('SELECT gems FROM players WHERE id=1')->fetchColumn();
 rejected(fn()=>command('instant',['troops'=>[50100101=>100],'max_gems'=>999],'hospital_instant_receipt'));
 checkHospital(troop()===320&&stock('food')===$paid&&H::getStatus(1)['waiting']===1140&&(int)$db->query('SELECT gems FROM players WHERE id=1')->fetchColumn()===$gems,'direct crystal healing is rejected without changing any balance or troops');
 $times=[1=>1,1,1,2,2,3,4,5,6,8];
 foreach(\Conquer\Game\City\TroopData::all() as$unit)checkHospital($unit['heal_time']===$times[$unit['tier']],'balanced healing time for '.$unit['code']);
 H::addWounded(2,[50101001=>1000,50200501=>100]);
 $db->execute('UPDATE cities SET food=1000000000,lumber=1000000000,stone=1000000000,gold=1000000000 WHERE id=2');
 $high=command('heal',['troops'=>[50101001=>1000,50200501=>100]],null,2);
 checkHospital($high['duration_seconds']===8200,'mixed T10 and T5 batch sums base healing times');
 $end=H::getStatus(2)['active']['ends_at'];
 $db->execute("INSERT INTO player_research(player_id,world_id,research_code,level)VALUES(2,1,'healing_time_reduced',1)");
 checkHospital(H::getStatus(2)['active']['ends_at']===$end,'new healing research preserves the already running timer');
 $db->execute('UPDATE hospital_wounded SET healing_ends_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE city_id=2 AND healing_count>0');H::processHealed(2);
 H::addWounded(2,[50101001=>1000,50200501=>100,50100101=>1]);
 $boosted=command('heal',['troops'=>[50101001=>1000,50200501=>100,50100101=>1]],null,2);
 checkHospital($boosted['duration_seconds']===8119,'one percent research applies to the summed batch before rounding once');
 $db->execute('UPDATE hospital_wounded SET healing_ends_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE city_id=2 AND healing_count>0');H::processHealed(2);
 // Retain promised legacy timers when the schema upgrade is replayed.
 $db->execute('INSERT INTO hospital_wounded(city_id,troop_code,count,healing_ends_at)VALUES(2,50100101,7,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))');
 \Conquer\Db\MigrationSql::apply($db->getPdo(),file_get_contents(ROOT_DIR.'/migrations/0091_hospital_healing.sql'));
 checkHospital(H::getStatus(2)['healing']===7&&H::getStatus(1)['healing']===0,'migration preserves legacy timers while new waiting rows remain waiting');
}finally{$fixture->close();}
