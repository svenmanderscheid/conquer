<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';date_default_timezone_set('UTC');
use Conquer\Db\Connection;
use Conquer\Game\City\ReturnSummary;
$fixture=new \ConquerTests\FeatureDatabase();register_shutdown_function(static fn()=>$fixture->close());
$db=Connection::getInstance();
function checkReturn(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
$db->execute("INSERT INTO worlds(id,name,slug,status,map_size) VALUES(2,'Other','return-other','running',256)");
foreach([1,2]as$id){$db->execute('INSERT INTO players(id,username,email,password_hash) VALUES(?,?,?,?)',[$id,'Return'.$id,'return'.$id.'@tests.invalid','unused']);}
foreach([[1,1,1],[2,2,1],[3,1,2]]as[$id,$player,$world]){
 $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y) VALUES(?,?,?,'Return',?,65)",[$id,$player,$world,65+$id]);
 $db->execute("INSERT INTO building_queue(city_id,building_code,level_to,started_at,finishes_at,is_processed) VALUES(?,'farm',2,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 HOUR),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 20 MINUTE),1)",[$id]);
 $db->execute("INSERT INTO troop_queue(city_id,troop_code,count,started_at,finishes_at,is_processed) VALUES(?,50100101,40,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 HOUR),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 20 MINUTE),1)",[$id]);
 $db->execute("INSERT INTO research_queue(player_id,world_id,research_code,level_to,started_at,finishes_at,is_processed) VALUES(?,?,'food_production',1,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 HOUR),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 20 MINUTE),1)",[$player,$world]);
 foreach(['complete','returning']as$state)$db->execute("INSERT INTO marches(player_id,world_id,march_type,origin_city_id,target_x,target_y,target_type,troops_json,haul_json,departure_time,arrival_time,return_time,state) VALUES(?,?,9,?,66,65,5,'{}','{}',DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 HOUR),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 20 MINUTE),?)",[$player,$world,$id,$state]);
}
// Due but not processed, future and old jobs must not count as confirmed finishes.
foreach([[0,-600],[1,600],[1,-900000]]as[$processed,$delta])$db->execute("INSERT INTO troop_queue(city_id,troop_code,count,started_at,finishes_at,is_processed) VALUES(1,50100101,100,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 20 DAY),?,?)",[gmdate('Y-m-d H:i:s',time()+$delta),$processed]);
$db->execute("INSERT INTO marches(player_id,world_id,march_type,origin_city_id,target_x,target_y,target_type,troops_json,haul_json,departure_time,arrival_time,return_time,state) VALUES(1,1,13,1,66,65,5,'{}','{\"garrisoned\":{\"50100101\":10}}',DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 HOUR),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 20 MINUTE),'complete')");
$city=['id'=>1,'player_id'=>1,'world_id'=>1];$now=time();$result=ReturnSummary::since($city,$now-3600,$now);
checkReturn(count($result['buildings'])===1&&(int)$result['buildings'][0]['level']===2,'building finishes exclude other players and worlds');
checkReturn($result['trained']===40,'only processed training in the absence interval counts');
checkReturn($result['research']===1,'research is player and world scoped');
checkReturn($result['marches']===1,'only completed returns count; ongoing and garrison arrivals excluded');
$again=ReturnSummary::since($city,$now-3600,$now);checkReturn($again===$result,'read is repeatable without consuming rewards or jobs');
$empty=ReturnSummary::since($city,$now,$now);checkReturn(!$empty['buildings']&&!$empty['trained']&&!$empty['research']&&!$empty['marches'],'already seen interval is empty');
$bounded=ReturnSummary::since($city,1,$now);checkReturn($bounded['since']===$now-7*86400&&$bounded['trained']===40,'history bounded to seven days');
foreach([[1,1,1],[2,1,2],[3,2,1]]as[$id,$world,$leader])$db->execute("INSERT INTO rallies(id,world_id,leader_player_id,leader_city_id,target_player_id,target_city_id,target_x,target_y,status,launch_at,return_time) VALUES(?,?,?,?,2,2,67,65,'complete',DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 HOUR),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 20 MINUTE))",[$id,$world,$leader,$world===2?3:$leader]);
$db->execute("INSERT INTO rally_participants(rally_id,player_id,city_id,status) VALUES(2,1,1,'returned')");
$withRallies=ReturnSummary::since($city,$now-3600,$now);checkReturn($withRallies['marches']===3,'completed leader and participant rallies count once; foreign world excluded');
echo "PASS return summary\n";
