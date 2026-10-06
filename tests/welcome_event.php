<?php
declare(strict_types=1);
/** UTC visits, world-scoped milestones, authenticated receipts and atomic grants. */
if (PHP_SAPI !== 'cli') exit(1);
date_default_timezone_set('UTC');
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();

use Conquer\Db\Connection;
use Conquer\Db\MigrationSql;
use Conquer\Game\City\CityState;
use Conquer\Game\Conquest\WelcomeEventService;
use Conquer\Game\Inventory\InventoryService;
use Conquer\Game\World\WorldContext;

if (($argv[1] ?? '') === '--claim-worker') {
    $directory = realpath($argv[2] ?? '');
    $temporary = realpath(sys_get_temp_dir());
    if (!$directory || !$temporary || dirname($directory) !== $temporary || !preg_match('/^conquer_feature_test_[a-f0-9]{12}$/D', basename($directory))) exit(2);
    Connection::init($directory);
    WorldContext::bind(1, 1);
    try { WelcomeEventService::claim(1, 'login_5'); echo json_encode(['ok'=>true]); }
    catch (DomainException $e) { echo json_encode(['ok'=>false]); }
    exit;
}

require __DIR__.'/Support/FeatureDatabase.php';
$fixture = new \ConquerTests\FeatureDatabase();
$db = Connection::getInstance();
$checks = 0;
$exit = 0;
function welcomeCheck(bool $ok, string $message): void {
    global $checks;
    if (!$ok) throw new RuntimeException($message);
    $checks++;
}
function welcomeReject(callable $fn, string $message): void {
    try { $fn(); } catch (DomainException $e) { welcomeCheck(true, $message); return; }
    throw new RuntimeException('Expected rejection: '.$message);
}
function welcomeStock(int $player=1): array {
    return Connection::getInstance()->query('SELECT world_id,item_code,quantity FROM player_inventory WHERE player_id=? ORDER BY world_id,item_code', [$player])->fetchAll();
}
function welcomeRows(int $player=1): array {
    $state = WelcomeEventService::state($player);
    return array_column(array_merge($state['login_rewards'], $state['growth_rewards']), null, 'code');
}

try {
    $catalog = json_decode(file_get_contents(ROOT_DIR.'/data/welcome_event.json'), true, 32, JSON_THROW_ON_ERROR);
    $definitions = array_column($catalog['milestones'], null, 'code');
    welcomeCheck(count($definitions)===13, 'Seven visit rewards and six growth rewards have stable unique codes');
    foreach ($definitions as $definition) foreach ($definition['rewards'] as $reward) {
        $item = InventoryService::getItemDef($reward['item_code']);
        welcomeCheck($item !== null && InventoryService::isDropEligible($reward['item_code']) && $reward['quantity']>0, 'Reward uses an eligible existing item');
        welcomeCheck(is_file(ROOT_DIR.'/assets/art/items/'.$item['icon']), 'Reward has a shipped icon');
    }
    $db->execute("INSERT INTO worlds(id,name,slug,status,map_size,map_seed) VALUES(2,'Other welcome realm','other-welcome-realm','running',256,43)");
    foreach ([1,2] as $player) {
        $db->execute("INSERT INTO players(id,username,email,password_hash,gems,created_at,last_login) VALUES(?,?,?,?,0,'2020-01-01 00:00:00','2020-01-01 00:00:00')", [$player,'WelcomeFixture'.$player,'welcome'.$player.'@tests.invalid','unused']);
        $db->execute('INSERT INTO kingdom_profiles(player_id,display_name,welcome_claimed) VALUES(?,?,1)', [$player,'WelcomeFixture'.$player]);
        foreach ([1,2] as $world) {
            $city = ($player-1)*2+$world;
            $db->execute('INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,food,lumber,stone,gold) VALUES(?,?,?,?,?,?,0,0,0,0)', [$city,$player,$world,'Welcome city '.$city,30+$player,40]);
            foreach (CityState::BUILDING_CODES as $building) $db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(?,?,1)', [$city,$building]);
        }
    }
    WorldContext::bind(1, 1);
    $initial = WelcomeEventService::state(1);
    welcomeCheck($initial['available'] && $initial['visit_days']===1 && $initial['claimable_count']===1, 'Existing account starts on first actual visit without phantom logins');
    welcomeCheck($initial['player_id']===1 && $initial['world_id']===1, 'State is explicitly bound to its player and world');
    welcomeCheck($initial['next_visit_at']===gmdate('Y-m-d 00:00:00', time()+86400), 'Next visit countdown ends at the next UTC midnight');
    welcomeCheck(welcomeStock()===[], 'State reads never automatically grant rewards');
    for ($i=0; $i<3; $i++) WelcomeEventService::state(1);
    welcomeCheck(WelcomeEventService::state(1)['visit_days']===1, 'Repeated same-day visits count once');
    welcomeReject(fn()=>WelcomeEventService::claim(1, 'login_2'), 'Future visit reward cannot be claimed');
    welcomeReject(fn()=>WelcomeEventService::claim(1, ['login_1']), 'Malformed reward code rejected');
    welcomeReject(fn()=>WelcomeEventService::claim(1, 'client_created_reward'), 'Unknown reward code rejected');
    welcomeCheck(welcomeStock()===[], 'Rejected claims give no items');

    $db->execute("UPDATE player_welcome_events SET last_visit_date=DATE_SUB(UTC_DATE(),INTERVAL 20 DAY) WHERE player_id=1 AND world_id=1");
    welcomeCheck(WelcomeEventService::state(1)['visit_days']===2, 'Twenty missed days add exactly one visit without resetting');
    $db->execute("UPDATE player_welcome_events SET last_visit_date=DATE_SUB(UTC_DATE(),INTERVAL 1 DAY) WHERE player_id=1 AND world_id=1");
    welcomeCheck(WelcomeEventService::state(1)['visit_days']===3, 'Next UTC date adds one visit');
    for ($i=0; $i<7; $i++) {
        $db->execute("UPDATE player_welcome_events SET last_visit_date=DATE_SUB(UTC_DATE(),INTERVAL 1 DAY) WHERE player_id=1 AND world_id=1");
        WelcomeEventService::state(1);
    }
    welcomeCheck(WelcomeEventService::state(1)['visit_days']===7, 'Visit days cap at seven and old rewards remain claimable');
    welcomeCheck(WelcomeEventService::state(2)['visit_days']===1, 'Other players receive independent visit progress');
    WorldContext::bind(2, 1);
    welcomeCheck(WelcomeEventService::state(1)['visit_days']===1, 'Other worlds receive independent visit progress');
    welcomeReject(fn()=>WelcomeEventService::claim(1, 'login_7'), 'Visits from another world cannot unlock its reward');
    WorldContext::bind(1, 1);

    // Pending queues, another player's city and another world must not contribute.
    $db->execute("INSERT INTO troop_queue(city_id,troop_code,count,started_at,finishes_at,is_processed) VALUES(1,50100101,500,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY),0),(2,50100101,2000,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 DAY),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY),1),(3,50100101,2000,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 DAY),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY),1)");
    $db->execute("INSERT INTO player_research(player_id,world_id,research_code,level) VALUES(1,2,'infantry_atk',10),(2,1,'infantry_atk',10)");
    $rows = welcomeRows();
    welcomeCheck($rows['trained_500']['progress']===0 && $rows['research_3']['progress']===0, 'Other players, other worlds and unfinished queues never contribute');
    welcomeReject(fn()=>WelcomeEventService::claim(1, 'trained_500'), 'Queued troops do not unlock a training reward');
    $db->execute("UPDATE troop_queue SET is_processed=1 WHERE city_id=1");
    welcomeCheck(welcomeRows()['trained_500']['progress']===0, 'A processed flag with a future finish time is not enough');
    $db->execute("UPDATE troop_queue SET finishes_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 MINUTE) WHERE city_id=1");
    $db->execute("UPDATE city_buildings SET level=3 WHERE city_id=1 AND building_code='castle'");
    $db->execute("INSERT INTO player_research(player_id,world_id,research_code,level) VALUES(1,1,'infantry_atk',2),(1,1,'infantry_def',1)");
    $rows = welcomeRows();
    welcomeCheck($rows['trained_500']['completed'] && !$rows['trained_2000']['completed'], 'Completed training reaches the exact milestone');
    welcomeCheck($rows['castle_3']['completed'] && !$rows['castle_5']['completed'], 'Castle progress uses the completed building level');
    welcomeCheck($rows['research_3']['completed'] && !$rows['research_10']['completed'], 'Research progress sums completed levels');

    $before = welcomeStock();
    $db->execute("CREATE TRIGGER welcome_fail BEFORE INSERT ON player_inventory FOR EACH ROW BEGIN IF NEW.player_id=1 AND NEW.item_code=10103001 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Fixture grant failure'; END IF; END");
    try {
        try { WelcomeEventService::claim(1, 'login_1'); throw new RuntimeException('Grant should have failed'); }
        catch (PDOException $e) { welcomeCheck(true, 'Fixture causes the last item grant to fail'); }
        welcomeCheck(welcomeStock()===$before, 'A late grant failure rolls back every earlier item');
        welcomeCheck(!welcomeRows()['login_1']['claimed'], 'A failed grant rolls back its claim marker');
    } finally { $db->execute('DROP TRIGGER welcome_fail'); }

    // Real handler: session, CSRF, wrong-world and operation-key guards remain mandatory.
    $token = bin2hex(random_bytes(32));
    $db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,user_agent,expires_at,active_world_id) VALUES(1,?,'welcome-event-test','127.0.0.1','welcome-event-test',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),1)", [$token]);
    $url = $fixture->serve(<<<'PHP'
switch (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) {
    case '/state': \Conquer\Api\Handlers\KingdomHandler::state([]); break;
    case '/claim': \Conquer\Api\Handlers\KingdomHandler::action([]); break;
    default: http_response_code(404);
}
PHP);
    $request = static function(?array $body=null, int $expectedStatus=200, bool $auth=true, bool $csrf=true) use($url,$token): array {
        $curl = curl_init($url.($body===null ? '/state' : '/claim'));
        $headers = ['Content-Type: application/json'];
        if ($auth) $headers[] = 'Cookie: conquer_session='.$token;
        if ($csrf) $headers[] = 'X-CSRF-Token: welcome-event-test';
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>20]);
        if ($body!==null) curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($body, JSON_THROW_ON_ERROR)]);
        $raw = curl_exec($curl); $status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE); curl_close($curl);
        $response = json_decode((string)$raw,true);
        welcomeCheck($status===$expectedStatus && is_array($response), 'HTTP '.($body===null ? 'state' : 'claim').' expected '.$expectedStatus.', got '.$status.': '.$raw);
        return $response;
    };
    $claim = ['action'=>'welcome.claim','milestone_code'=>'login_1','operation_key'=>'welcome_event_claim_login1','expected_world_id'=>1];
    $request($claim,401,false);
    $request($claim,403,true,false);
    $request(array_replace($claim,['expected_world_id'=>2]),422);
    $noKey=$claim;unset($noKey['operation_key']);$request($noKey,422);
    welcomeCheck(welcomeStock()===$before, 'Rejected API requests grant nothing');
    $apiState=$request()['data']['welcome_event'];
    welcomeCheck($apiState['available'] && $apiState['claimable_count']===10, 'Real kingdom API exposes visit and completed growth rewards');
    $response=$request($claim)['data'];
    welcomeCheck($response['result']['rewards']===$definitions['login_1']['rewards'], 'Claim returns exactly the rewards shown in the preview');
    $after=welcomeStock();
    welcomeCheck($request($claim)['data']['result']===$response['result'] && welcomeStock()===$after, 'A lost-response retry returns the original receipt without another grant');
    $request(array_replace($claim,['operation_key'=>'welcome_event_new_claim_login1']),422);
    $request(array_replace($claim,['milestone_code'=>'login_2']),422);
    welcomeCheck(welcomeStock()===$after, 'New-key duplicates and changed-payload replays cannot duplicate rewards');
    $forged = array_replace($claim,['milestone_code'=>'castle_3','operation_key'=>'welcome_event_claim_castle3','rewards'=>[['item_code'=>10103003,'quantity'=>999999]]]);
    welcomeCheck($request($forged)['data']['result']['rewards']===$definitions['castle_3']['rewards'], 'Client-supplied reward quantities are ignored');

    // Simultaneous independent claims with distinct callers still grant only once.
    $beforeConcurrent=InventoryService::quantity(1,10103031);
    $workers=[];
    for($i=0;$i<2;$i++) {
        $pipes=[];
        $process=proc_open([PHP_BINARY,__FILE__,'--claim-worker',$fixture->sessionPath()],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,ROOT_DIR,null,['bypass_shell'=>true]);
        if(!is_resource($process)) throw new RuntimeException('Claim worker did not start.');
        fclose($pipes[0]); $workers[]=[$process,$pipes];
    }
    $successes=0;
    foreach($workers as [$process,$pipes]) {
        $output=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$status=proc_close($process);
        welcomeCheck($status===0 && is_array(json_decode($output,true)), 'Concurrent claim worker completes: '.$errors);
        if(json_decode($output,true)['ok'])$successes++;
    }
    welcomeCheck($successes===1 && InventoryService::quantity(1,10103031)===$beforeConcurrent+1, 'Only one racing claimant receives the reward');
    welcomeCheck(welcomeRows()['login_5']['claimed'], 'Concurrent claim is durable after refresh');

    // The same reward can be earned separately in a second world, never transferred by its request.
    WorldContext::bind(2,1);
    $other=WelcomeEventService::claim(1,'login_1');
    welcomeCheck($other['rewards']===$definitions['login_1']['rewards'] && welcomeRows()['login_1']['claimed'], 'Second world has its own one-time claim');
    WorldContext::bind(1,1);
    $db->execute("UPDATE worlds SET status='closed' WHERE id=1");
    welcomeReject(fn()=>WelcomeEventService::claim(1,'login_2'), 'Closed world rejects new claims');
    $db->execute("UPDATE worlds SET status='running' WHERE id=1");

    // Rollout before schema installation returns unavailable instead of breaking all kingdom state.
    $db->execute('DROP TABLE player_welcome_event_claims');
    welcomeCheck(WelcomeEventService::state(1)['available']===false, 'Missing additive schema has a compatible read result');
    welcomeReject(fn()=>WelcomeEventService::claim(1,'login_2'), 'Claims are unavailable without the additive schema');
    MigrationSql::apply($db->getPdo(), file_get_contents(ROOT_DIR.'/migrations/0133_welcome_events.sql'));
    echo "PASS $checks welcome event checks (isolated database).\n";
} catch (Throwable $e) {
    $exit=1;
    fwrite(STDERR,'FAIL '.$e->getMessage()."\n".$e->getTraceAsString()."\n");
} finally { $fixture->close(); }
exit($exit);
