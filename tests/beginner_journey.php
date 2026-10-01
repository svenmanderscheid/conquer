<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';
date_default_timezone_set('UTC');

use Conquer\Db\Connection;
use Conquer\Game\City\CityState;
use Conquer\Game\Community\CommunityService;
use Conquer\Game\March\{GatherService,MarchDispatcher,MarchTick};
use Conquer\Game\Tutorial\BeginnerJourney;
use Conquer\Game\World\WorldContext;

function checkJourney(bool $ok, string $label): void { if (!$ok) throw new RuntimeException($label); echo "PASS $label\n"; }
function journeyProgress(int $player=1, int $world=1): array { return WorldContext::run($world, fn()=>BeginnerJourney::state($player,$world)); }
function journeyArrival(int $march): void {
    Connection::getInstance()->execute('UPDATE marches SET departure_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 25 SECOND),arrival_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 5 SECOND) WHERE id=?',[$march]);
    MarchTick::runForPlayer(1);
}
function journeyHome(int $march): void {
    Connection::getInstance()->execute('UPDATE marches SET return_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$march]);
    MarchTick::runForPlayer(1);
}

$fixture=null;$log=tempnam(sys_get_temp_dir(),'conquer-journey-');\Conquer\Logger::init($log,'ERROR');
try {
    $fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();WorldContext::bind(1);
    $db->execute("UPDATE worlds SET status='running',speed_factor=1,gather_factor=1 WHERE id=1");
    $db->execute("INSERT INTO worlds(id,name,slug,status,map_size) VALUES(2,'Journey two','journey-two','running',256)");
    foreach([1,2] as $player){
        $db->execute("INSERT INTO players(id,username,email,password_hash,action_points) VALUES(?,?,?,'unused',200)",[$player,'Journey'.$player,'journey'.$player.'@invalid.test']);
        foreach([1,2] as $world){
            $city=$player+($world-1)*10;
            $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,food,lumber,stone,gold) VALUES(?,?,?,'Journey city',?,20,100000,100000,100000,100000)",[$city,$player,$world,20+$player]);
            foreach(CityState::BUILDING_CODES as $code)$db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(?,?,1)',[$city,$code]);
            $db->execute('INSERT INTO city_troops(city_id,troop_code,count) VALUES(?,50100101,2000)',[$city]);
        }
    }
    $empty=['gather'=>false,'monster'=>false,'charm'=>false,'alliance_help'=>false];
    checkJourney(journeyProgress()['progress']===$empty,'new player has no invented milestones');
    $db->execute("INSERT INTO field_objects(world_id,coord_x,coord_y,object_type,level,resource_amount,resource_max,spawned_at,expires_at) VALUES(1,23,20,1,1,30,30,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))");
    $gather=GatherService::dispatch(1,1,23,20,0,[50100101=>10])['march_id'];
    checkJourney(!journeyProgress()['progress']['gather'],'dispatch alone never completes gathering');
    journeyArrival($gather);
    checkJourney(!journeyProgress()['progress']['gather'],'gathered cargo in transit is not delivered');
    journeyHome($gather);
    checkJourney(journeyProgress()['progress']['gather'],'real harvest homecoming completes gathering');
    checkJourney(journeyProgress(2)['progress']===$empty&&journeyProgress(1,2)['progress']===$empty,'gather progress never crosses player or world');

    $db->execute("INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current,monster_type,expires_at) VALUES(1,20209901,24,20,1,'solo',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))");
    $monster=MarchDispatcher::dispatchMonster(1,1,21,20,24,20,[50100101=>500],'journey_monster_0001');
    checkJourney(!journeyProgress()['progress']['monster'],'a monster dispatch is not a win');
    journeyArrival($monster);
    checkJourney(journeyProgress()['progress']['monster'],'real resolved victory completes the monster step');
    $charm=(int)$db->query("SELECT charm_id FROM monster_kill_receipts WHERE world_id=1 AND source_kind='solo' AND source_id=?",[$monster])->fetchColumn();
    checkJourney($charm>0&&!journeyProgress()['progress']['charm'],'a spawned charm is not a collected charm');
    journeyHome($monster);
    $collector=MarchDispatcher::dispatchCharm(1,1,21,20,24,20,$charm,[50100101=>10],'journey_charm_000001');
    checkJourney(!journeyProgress()['progress']['charm'],'dispatching a collector is not completion');
    journeyArrival($collector);
    checkJourney(journeyProgress()['progress']['charm'],'confirmed charm activation completes the collection step');
    $db->execute('DELETE FROM player_charms_active WHERE player_id=1');
    checkJourney(journeyProgress()['progress']['charm'],'collection history survives an expired or replaced buff');
    journeyHome($collector);

    $db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id) VALUES(1,1,'Journey guild','JNY',1)");
    $db->execute("INSERT INTO alliance_members(alliance_id,player_id,world_id,role) VALUES(1,1,1,'leader'),(1,2,1,'member')");
    checkJourney(journeyProgress()['alliance_member']&&!journeyProgress()['progress']['alliance_help'],'membership does not count as help');
    checkJourney(!journeyProgress()['help_available'],'no actionable request means no help recommendation');
    $db->execute("INSERT INTO building_queue(city_id,building_code,level_to,started_at,finishes_at) VALUES(2,'farm',2,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 10 MINUTE))");
    $queue=(int)$db->lastInsertId();
    CommunityService::action(2,['action'=>'help.request','queue_type'=>'building','queue_id'=>$queue,'request_id'=>'journey_request_0001'],1);
    $request=(int)$db->query('SELECT id FROM community_help_requests WHERE queue_id=?',[$queue])->fetchColumn();
    checkJourney(journeyProgress()['help_available'],'live eligible allied request is recommended');
    checkJourney(!journeyProgress(2)['help_available'],'own request is never recommended for helping');
    $help=['action'=>'help.give','help_id'=>$request,'request_id'=>'journey_help_0000001'];
    CommunityService::action(1,$help,1);CommunityService::action(1,$help,1);
    checkJourney(journeyProgress()['progress']['alliance_help'],'actual time-saving help completes the step');
    checkJourney((int)$db->query('SELECT COUNT(*) FROM community_help_log WHERE helper_id=1')->fetchColumn()===1,'replayed help has one completion receipt');
    $all=journeyProgress();$before=(int)$db->query('SELECT COUNT(*) FROM marches')->fetchColumn();
    checkJourney($all['progress']===array_fill_keys(array_keys($empty),true),'all four activity milestones come from completed play');
    checkJourney($all===journeyProgress()&&(int)$db->query('SELECT COUNT(*) FROM marches')->fetchColumn()===$before,'repeated journey reads are stable and do not start actions');
    checkJourney(journeyProgress(2)['progress']===$empty&&journeyProgress(1,2)['progress']===$empty,'all milestones remain isolated by world and player');
    $db->execute("INSERT INTO battle_reports(world_id,attacker_id,attacker_city_id,target_type,target_x,target_y,outcome,data_json) VALUES(1,2,2,3,24,20,'defender_wins','{\"type\":\"monster_rally\"}')");
    checkJourney(!journeyProgress(2)['progress']['monster'],'a failed rally does not complete the monster goal');
    $db->execute("INSERT INTO battle_reports(world_id,attacker_id,attacker_city_id,target_type,target_x,target_y,outcome,data_json) VALUES(1,2,2,3,24,20,'attacker_wins','{\"type\":\"monster_rally\"}')");
    checkJourney(journeyProgress(2)['progress']['monster'],'successful rally participants receive credit through their personal battle report');
    checkJourney(!journeyProgress(2,2)['progress']['monster'],'a personal rally victory never crosses worlds');
    $db->execute('DELETE FROM battle_reports WHERE attacker_id=1');
    checkJourney(journeyProgress()['progress']['monster'],'winner receipt keeps victory credit after deleting a report');
    $db->execute('DELETE FROM alliance_members WHERE player_id=1 AND world_id=1');
    checkJourney(journeyProgress()['progress']['alliance_help']&&!journeyProgress()['alliance_member'],'past help stays completed after leaving the alliance');
    try{BeginnerJourney::state(1,2);throw new RuntimeException('wrong world accepted');}catch(DomainException $e){checkJourney($e->getCode()===409,'wrong request world rejected');}
    try{BeginnerJourney::state(99999,1);throw new RuntimeException('missing city accepted');}catch(DomainException $e){checkJourney($e->getCode()===403,'no city means no journey access');}
    echo "ALL BEGINNER JOURNEY CHECKS PASSED\n";
} finally {if($fixture)$fixture->close();if(is_file($log))unlink($log);}
