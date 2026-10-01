<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';
require __DIR__.'/Support/HttpApp.php';

use Conquer\Db\Connection;
use Conquer\Game\City\CityState;
use Conquer\Game\Map\MonsterData;
use Conquer\Game\March\MarchSpeed;
use Conquer\Game\Rally\{MonsterRally, RallyService};
use Conquer\Game\Research\BuffEngine;
use Conquer\Game\World\WorldContext;
use ConquerTests\HttpApp;

date_default_timezone_set('UTC');
\Conquer\Logger::init(sys_get_temp_dir().'/uok-rally-join-test.log', 'ERROR');
$checks = 0;
$fixture = null;
$exit = 0;
function rallyJoinCheck(bool $ok, string $label): void
{
    if (!$ok) throw new RuntimeException($label);
    $GLOBALS['checks']++;
    echo "PASS $label\n";
}

try {
    // All HTTP requests use the real front controller with a disposable database.
    $fixture = new \ConquerTests\FeatureDatabase();
    $db = Connection::getInstance();
    WorldContext::bind(1);
    $db->execute("UPDATE worlds SET status='running',speed_factor=1 WHERE id=1");
    $db->execute("INSERT INTO worlds(id,name,slug,status) VALUES(2,'Rally other world','rally-other','running')");
    $tokens = [];
    $csrf = str_repeat('b', 64);
    foreach ([1,2,3,4] as $pid) {
        $db->execute("INSERT INTO players(id,username,email,password_hash,action_points,last_ap_regen,beginner_shield_until) VALUES(?,?,?,'unused',200,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))", [$pid,'RallyJoinFixture'.$pid,'rally-join'.$pid.'@tests.invalid']);
        $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,food,lumber,stone,gold) VALUES(?,?,1,'Rally join city',?,40,100000,100000,100000,100000)", [$pid,$pid,20+$pid*10]);
        foreach (CityState::BUILDING_CODES as $code) $db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(?,?,1)', [$pid,$code]);
        foreach ([50100101,50200101] as $code) $db->execute('INSERT INTO city_troops(city_id,troop_code,count) VALUES(?,?,10000)', [$pid,$code]);
        $tokens[$pid] = bin2hex(random_bytes(32));
        $db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,user_agent,expires_at,active_world_id) VALUES(?,?,?,'127.0.0.1','rally join',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),1)", [$pid,$tokens[$pid],$csrf]);
    }
    $db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id) VALUES(1,1,'Rally join testers','JOIN',1)");
    $db->execute("INSERT INTO alliance_members(alliance_id,player_id,world_id,role) VALUES(1,1,1,'leader'),(1,2,1,'member'),(1,3,1,'member')");
    $definition = MonsterData::get(20202401);
    $db->execute("INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current,monster_type,expires_at) VALUES(1,20202401,70,60,?,'rally',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))", [(int)($definition['amount']*$definition['stats']['hp'])]);
    $rallyId = MonsterRally::start(1,1,70,60,[50100101=>100],5,'Join fixture');
    $stock = static fn(int $city, int $code=50100101): int => (int)$db->query('SELECT count FROM city_troops WHERE city_id=? AND troop_code=?',[$city,$code])->fetchColumn();
    $row = static fn(): array => $db->query('SELECT * FROM rallies WHERE id=?',[$rallyId])->fetch();
    $base = $fixture->serve(HttpApp::source(), ['-d','disable_functions=mail','-d','display_errors=0']);
    $call = static function(string $path, ?array $body=null, int $pid=2, bool $withCsrf=true, ?int $world=null) use ($base,$tokens,$csrf,$db): array {
        $db->execute('DELETE FROM security_rate_limits');
        $headers = ['Content-Type: application/json'];
        if ($pid) $headers[]='Cookie: conquer_session='.$tokens[$pid];
        if ($withCsrf) $headers[]='X-CSRF-Token: '.$csrf;
        if ($world!==null) $headers[]='X-World-ID: '.$world;
        return HttpApp::request($base,'/api/'.$path,$body===null?'GET':'POST',$headers,$body===null?null:json_encode($body,JSON_THROW_ON_ERROR));
    };
    $join = ['rally_id'=>$rallyId,'troops'=>[50100101=>120,50200101=>80],'operation_key'=>'rally_join_fixture_001','expected_world_id'=>1];
    rallyJoinCheck($call('rally/list',null,0)['status']===401 && $call('rally/join',$join,0)['status']===401, 'rally list and join require authentication');
    rallyJoinCheck($call('rally/join',$join,2,false)['status']===403, 'deploy requires the session CSRF token');
    rallyJoinCheck($call('rally/join',$join,2,true,2)['status']===409, 'deploy rejects an inactive world');
    rallyJoinCheck($call('rally/'.$rallyId,null,4)['status']===403 && $call('rally/join',$join,4)['status']===400, 'unallied players cannot inspect or join the rally');
    rallyJoinCheck($call('rally/list',null,4)['json']['data']['rallies']===[], 'unallied map has no rally menu data');
    $listing = $call('rally/list')['json']['data']['rallies'];
    rallyJoinCheck(count($listing)===1 && (int)$listing[0]['id']===$rallyId && $listing[0]['leader']['coord_x']===30 && $listing[0]['leader']['coord_y']===40, 'allied list exposes the rally and the actual host city coordinates');
    rallyJoinCheck((int)$listing[0]['target_x']===70 && $listing[0]['troops'][50100101]===100 && (int)$listing[0]['capacity']>=300, 'list supplies target, captain troops and authoritative capacity');
    $first = $call('rally/join',$join);
    rallyJoinCheck($first['status']===200 && $first['json']['data']['joined']===true, 'deploy sends a real allied army: '.json_encode($first['json']));
    rallyJoinCheck($stock(2)===9880 && $stock(2,50200101)===9920, 'deploy reserves exactly the selected troop types and counts');
    $member = $db->query('SELECT * FROM rally_participants WHERE rally_id=? AND player_id=2',[$rallyId])->fetch();
    $speed = min(MarchSpeed::rally(50100101,BuffEngine::getBuffs(2,1),true),MarchSpeed::rally(50200101,BuffEngine::getBuffs(2,1),true));
    $travel = MarchSpeed::duration(10,$speed,1);
    rallyJoinCheck($member['status']==='joining' && strtotime($member['arrival_time'].' UTC')-strtotime($member['joined_at'].' UTC')===$travel, 'saved arrival uses distance to the host and the slowest selected troop');
    rallyJoinCheck((int)$db->query('SELECT action_points FROM players WHERE id=2')->fetchColumn()===200 && strtotime($db->query('SELECT beginner_shield_until FROM players WHERE id=2')->fetchColumn().' UTC')>time(), 'joining a monster rally preserves member AP and city protection');
    $routes = RallyService::activeMarchesForPlayer(2);
    rallyJoinCheck(count($routes)===1 && $routes[0]['march_type']==='rally_join' && $routes[0]['target_x']===30 && $routes[0]['target_y']===40 && $routes[0]['origin_x']===40, 'map march travels from the member city to the host, not the monster');
    $again = $call('rally/join',$join);
    rallyJoinCheck($again['json']===$first['json'] && $stock(2)===9880, 'lost-response retry returns the same success without reserving again');
    $duplicate = $join; $duplicate['operation_key']='rally_join_fixture_duplicate';
    rallyJoinCheck($call('rally/join',$duplicate)['status']===400 && $stock(2)===9880 && (int)$db->query('SELECT COUNT(*) FROM rally_participants WHERE rally_id=?',[$rallyId])->fetchColumn()===1, 'a second deploy key cannot add the same player twice');
    $detail = $call('rally/'.$rallyId)['json']['data'];
    rallyJoinCheck($detail['rally']['leader']['name']==='RallyJoinFixture1' && $detail['participants'][0]['profile']['name']==='RallyJoinFixture2' && $detail['participants'][0]['troops']==[50100101=>120,50200101=>80] && $detail['participants'][0]['status']==='joining' && $detail['participants'][0]['arrival_time']===$member['arrival_time'], 'detail retains names, troop composition and live arrival status');
    $meta = json_decode($row()['result_json'],true,512,JSON_THROW_ON_ERROR);
    $meta['capacity']=350;
    $db->execute('UPDATE rallies SET result_json=? WHERE id=?',[json_encode($meta,JSON_THROW_ON_ERROR),$rallyId]);
    $third = ['rally_id'=>$rallyId,'troops'=>[50100101=>51],'operation_key'=>'rally_join_capacity_fixture','expected_world_id'=>1];
    rallyJoinCheck($call('rally/join',$third,3)['status']===400 && $stock(3)===10000, 'in-transit armies already consume rally capacity');
    $third['troops']=[50100101=>50]; $third['operation_key']='rally_join_late_fixture';
    $db->execute('UPDATE rallies SET launch_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$rallyId]);
    $late = $call('rally/join',$third,3);
    rallyJoinCheck($late['status']===400 && str_contains($late['json']['error']['message'],'after departure') && $stock(3)===10000, 'server rejects arrival after departure with localized English feedback and no reservation');
    rallyJoinCheck((int)$db->query('SELECT COUNT(*) FROM rally_participants WHERE rally_id=? AND player_id=3',[$rallyId])->fetchColumn()===0, 'late deploy creates no participant or successful order');
    $db->execute('UPDATE rallies SET launch_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 5 MINUTE) WHERE id=?',[$rallyId]);
    $third['operation_key']='rally_join_last_capacity_fixture';
    rallyJoinCheck($call('rally/join',$third,3)['status']===200 && $stock(3)===9950, 'last available troop spaces remain joinable');
    $db->execute('UPDATE rally_participants SET arrival_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE rally_id=?',[$rallyId]);
    $detail = $call('rally/'.$rallyId)['json']['data'];
    rallyJoinCheck(count($detail['participants'])===2 && array_unique(array_column($detail['participants'],'status'))===['pending'], 'arrived members remain in the detail roster with an arrived status');
    RallyService::cancel($rallyId,1);
    rallyJoinCheck($stock(1)===10000 && $stock(2)===10000 && $stock(2,50200101)===10000 && $stock(3)===10000, 'cancelling returns every reserved army exactly once');
    RallyService::tick();
    rallyJoinCheck($stock(2)===10000 && RallyService::activeMarchesForPlayer(2)===[] && $call('rally/list')['json']['data']['rallies']===[], 'finished cancellation clears map/list activity without duplicating troops');
    echo "ALL $checks RALLY JOIN SERVER CHECKS PASSED\n";
} catch (Throwable $error) {
    $exit=1;
    fwrite(STDERR,$error->getMessage()."\n".$error->getTraceAsString()."\n");
} finally {
    if ($fixture) $fixture->close();
}
exit($exit);
