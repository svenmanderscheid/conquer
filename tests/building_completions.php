<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
date_default_timezone_set('UTC');
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR . '/src'))->register();
require __DIR__ . '/Support/FeatureDatabase.php';
require __DIR__ . '/Support/HttpApp.php';

use Conquer\Db\Connection;
use Conquer\Game\City\{CityState, TroopTrainer};
use Conquer\Game\Hospital\HospitalService;
use Conquer\Game\Notification\NotificationService as Notices;
use Conquer\Game\Research\ResearchProcessor;
use Conquer\Game\World\WorldContext;

function completionCheck(bool $ok, string $label): void
{
    if (!$ok) throw new RuntimeException($label);
    echo "PASS $label\n";
}

$fixture = new \ConquerTests\FeatureDatabase();
$db = Connection::getInstance();
try {
    foreach ([1,2] as $player) $db->execute(
        'INSERT INTO players(id,username,email,password_hash) VALUES(?,?,?,?)',
        [$player, 'Completion'.$player, 'completion'.$player.'@tests.invalid', 'unused'],
    );
    $db->execute("INSERT INTO worlds(id,name,slug,status,map_size,map_seed) VALUES(2,'Other Realm','other-realm','running',256,43)");
    foreach ([[1,1,1],[2,2,1],[3,1,2]] as [$city,$player,$world]) {
        $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,castle_level) VALUES(?,?,?,'Completion',?,65,5)", [$city,$player,$world,65+$city*8]);
        foreach (CityState::BUILDING_CODES as $code) $db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(?,?,5)', [$city,$code]);
    }
    WorldContext::bind(1,1);
    $db->execute("INSERT INTO building_queue(city_id,building_code,level_to,started_at,finishes_at) VALUES(1,'farm',6,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 MINUTE),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND))");
    foreach ([50100101,50200101,50300101] as $i=>$troop) $db->execute(
        'INSERT INTO troop_queue(city_id,troop_code,count,barrack_slot,started_at,finishes_at) VALUES(1,?,10,?,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 MINUTE),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND))', [$troop,$i+1],
    );
    $db->execute("INSERT INTO research_queue(player_id,world_id,research_code,level_to,finishes_at) VALUES(1,1,'food_production',1,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND))");
    foreach ([50100101=>5,50200101=>7] as $troop=>$count) $db->execute(
        "INSERT INTO hospital_wounded(city_id,troop_code,count,healing_count,healing_started_at,healing_ends_at,healing_batch) VALUES(1,?,?,?,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 MINUTE),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND),'completed_batch')", [$troop,$count,$count],
    );
    ResearchProcessor::processQueue(1,1);
    CityState::loadForPlayer(1,1);
    $notices = Notices::buildingCompletions(1,1,1);
    completionCheck(count($notices)===6, 'finished building, three training schools, research and healing create durable markers');
    $training = array_values(array_filter($notices, static fn(array $n):bool=>$n['type']==='train_complete'));
    $schools = array_column(array_column($training,'data'),'building_code'); sort($schools);
    completionCheck($schools===['archery_range','barrack','stable'], 'troop completions point to their actual training school');
    $heal = array_values(array_filter($notices, static fn(array $n):bool=>$n['type']==='heal_complete'))[0];
    completionCheck($heal['data']['count']===12 && $heal['data']['batch_id']==='completed_batch', 'one healing marker combines all troops in the paid batch');
    completionCheck((int)$db->query('SELECT SUM(count) FROM city_troops WHERE city_id=1')->fetchColumn()===42, 'completion markers preserve automatic troop and healing credit');
    ResearchProcessor::processQueue(1,1); CityState::loadForPlayer(1,1); HospitalService::processHealed(1);
    completionCheck(count(Notices::buildingCompletions(1,1,1))===6 && (int)$db->query('SELECT SUM(count) FROM city_troops WHERE city_id=1')->fetchColumn()===42, 'repeated processing and state reads neither duplicate notices nor credits');

    $db->execute('INSERT INTO troop_queue(city_id,troop_code,count,barrack_slot,started_at,finishes_at) VALUES(1,50100101,4,1,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))');
    $futureId=$db->lastInsertId(); TroopTrainer::processQueue($db,1);
    completionCheck(count(Notices::buildingCompletions(1,1,1))===6, 'running training never appears ready');
    $db->execute('DELETE FROM troop_queue WHERE id=?',[$futureId]); TroopTrainer::processQueue($db,1);
    completionCheck(count(Notices::buildingCompletions(1,1,1))===6, 'removed or cancelled jobs do not produce false completion markers');

    $db->execute('INSERT INTO troop_queue(city_id,troop_code,count,barrack_slot,started_at,finishes_at) VALUES(1,50100101,4,1,UTC_TIMESTAMP(),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND))');
    $rollbackId=$db->lastInsertId();
    try { $db->transaction(static function() use($db):void { TroopTrainer::processQueue($db,1); throw new RuntimeException('fixture rollback'); }); }
    catch (RuntimeException $e) { if($e->getMessage()!=='fixture rollback')throw $e; }
    completionCheck(count(Notices::buildingCompletions(1,1,1))===6 && (int)$db->query('SELECT SUM(count) FROM city_troops WHERE city_id=1')->fetchColumn()===42, 'completion and troop credit roll back together');
    $db->execute('DELETE FROM troop_queue WHERE id=?',[$rollbackId]);

    Notices::pushCityCompletion(2,Notices::TYPE_TRAIN_COMPLETE,['building_code'=>'barrack','count'=>99]); $otherPlayerId=$db->lastInsertId();
    Notices::pushCityCompletion(3,Notices::TYPE_TRAIN_COMPLETE,['building_code'=>'barrack','count'=>88]); $otherWorldId=$db->lastInsertId();
    for($i=0;$i<220;$i++) Notices::push(1,'unrelated_notice',['city_id'=>1]);
    completionCheck(count(Notices::buildingCompletions(1,1,1))===6, 'unrelated unread notices do not crowd completion markers out of the response');
    completionCheck(Notices::buildingCompletions(1,2,1)===[] && Notices::buildingCompletions(1,1,2)===[], 'completion reads isolate player, city and world');
    completionCheck(count(Notices::buildingCompletions(1,3,2))===1, 'background completion uses the owning city world instead of the active request world');
    $db->execute("UPDATE notifications SET created_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 8 DAY) WHERE player_id=1 AND type IN('build_complete','train_complete','research_complete','heal_complete')");
    Notices::poll(1,1);
    completionCheck(count(Notices::buildingCompletions(1,1,1))===6, 'unacknowledged building markers survive notification retention cleanup');
    Notices::markRead(1,[$notices[0]['id'],$otherPlayerId,$otherWorldId]);
    completionCheck(count(Notices::buildingCompletions(1,1,1))===5 && count(Notices::buildingCompletions(2,2,1))===1 && count(Notices::buildingCompletions(1,3,2))===1, 'acknowledgement clears only owned notifications in the active world');
    Notices::markRead(1,array_column($notices,'id')); Notices::markRead(1,array_column($notices,'id'));
    ResearchProcessor::processQueue(1,1); CityState::loadForPlayer(1,1);
    completionCheck(Notices::buildingCompletions(1,1,1)===[], 'acknowledgement is repeatable and dismissed completions stay dismissed after refresh');

    // Legacy speedup endpoints settle directly instead of going through the lazy processors.
    $token=bin2hex(random_bytes(32)); $csrf=bin2hex(random_bytes(32));
    $db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,user_agent,expires_at,active_world_id) VALUES(1,?,?,'127.0.0.1','completion fixture',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),1)", [$token,$csrf]);
    $speedup=null;
    foreach(\Conquer\Game\Inventory\InventoryService::allDefs() as $code=>$item) {
        if($item['category']==='speedup' && $item['subcategory']==='generic' && $item['duration_seconds']===60){$speedup=(int)$code;break;}
    }
    completionCheck($speedup!==null, 'one-minute generic speedup fixture is available');
    \Conquer\Game\Inventory\InventoryService::addItems(1,$speedup,10);
    $db->execute("INSERT INTO building_queue(city_id,building_code,level_to,started_at,finishes_at) VALUES(1,'farm',7,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 SECOND))"); $buildId=$db->lastInsertId();
    $db->execute("INSERT INTO research_queue(player_id,world_id,research_code,level_to,finishes_at) VALUES(1,1,'food_production',2,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 SECOND))"); $researchId=$db->lastInsertId();
    $url=$fixture->serve(<<<'PHP'
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(!preg_match('#^/(building|research)/(speedup|instant)/(\d+)$#',$path,$parts)){http_response_code(404);exit;}
$params=['queue_id'=>(int)$parts[3]];
if($parts[1]==='building'){
    if($parts[2]==='speedup')\Conquer\Api\Handlers\CityHandler::speedupBuild($params);
    \Conquer\Api\Handlers\CityHandler::instantBuild($params);
}
if($parts[2]==='speedup')\Conquer\Api\Handlers\ResearchHandler::speedup($params);
\Conquer\Api\Handlers\ResearchHandler::instant($params);
PHP);
    $request=static function(string $path, bool $authenticated=true, bool $withCsrf=true) use($url,$token,$csrf,$speedup):array {
        $headers=['Content-Type: application/json'];
        if($authenticated)$headers[]='Cookie: conquer_session='.$token;
        if($withCsrf)$headers[]='X-CSRF-Token: '.$csrf;
        return \ConquerTests\HttpApp::request($url,$path,'POST',$headers,json_encode(['item_code'=>$speedup],JSON_THROW_ON_ERROR));
    };
    completionCheck($request('/building/speedup/'.$buildId,false)['status']===401 && $request('/research/speedup/'.$researchId,true,false)['status']===403 && Notices::buildingCompletions(1,1,1)===[], 'legacy completion routes require authentication and CSRF before producing notices');
    foreach(['building'=>$buildId,'research'=>$researchId] as $kind=>$queueId) {
        $result=$request('/'.$kind.'/speedup/'.$queueId);
        completionCheck($result['status']===200 && $result['json']['data']['instantly_finished']===true, 'legacy '.$kind.' speedup completes its queue');
    }
    $speedNotices=Notices::buildingCompletions(1,1,1);
    completionCheck(count($speedNotices)===2 && count(array_filter($speedNotices,static fn(array $n):bool=>($n['type']==='build_complete' && $n['data']['queue_id']===$buildId) || ($n['type']==='research_complete' && $n['data']['queue_id']===$researchId)))===2, 'direct building and research settlement persist correctly scoped source IDs');
    completionCheck($request('/building/speedup/'.$buildId)['status']===404 && $request('/research/speedup/'.$researchId)['status']===404 && count(Notices::buildingCompletions(1,1,1))===2, 'retrying completed legacy speedups never duplicates notices');
    $db->execute("INSERT INTO building_queue(city_id,building_code,level_to,started_at,finishes_at) VALUES(1,'wall',6,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))"); $partialBuild=$db->lastInsertId();
    $db->execute("INSERT INTO research_queue(player_id,world_id,research_code,level_to,finishes_at) VALUES(1,1,'infantry_hp',1,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))"); $partialResearch=$db->lastInsertId();
    foreach(['building'=>$partialBuild,'research'=>$partialResearch] as $kind=>$queueId) {
        $partial=$request('/'.$kind.'/speedup/'.$queueId);
        completionCheck($partial['status']===200 && $partial['json']['data']['instantly_finished']===false && count(Notices::buildingCompletions(1,1,1))===2, 'partial '.$kind.' speedup does not show a premature completion');
        completionCheck($request('/'.$kind.'/instant/'.$queueId)['status']===422 && count(Notices::buildingCompletions(1,1,1))===2, 'retired instant '.$kind.' action remains disabled without producing a notice');
    }
    echo "ALL BUILDING COMPLETION CHECKS PASSED\n";
} finally { $fixture->close(); }
