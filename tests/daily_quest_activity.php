<?php
declare(strict_types=1);
/** Daily activity, durable progress, atomic grants and UTC reset in a disposable DB only. */
if (PHP_SAPI !== 'cli') exit(1);
date_default_timezone_set('UTC');
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();

use Conquer\Db\Connection;
use Conquer\Game\City\CityState;
use Conquer\Game\Community\CommunityService;
use Conquer\Game\Inventory\InventoryService;
use Conquer\Game\Kingdom\KingdomService;
use Conquer\Game\Quest\DailyQuestService;
use Conquer\Game\World\WorldContext;

if (($argv[1] ?? '') === '--claim-worker') {
    $directory = realpath($argv[2] ?? '');
    $temporary = realpath(sys_get_temp_dir());
    if (!$directory || !$temporary || dirname($directory) !== $temporary || !preg_match('/^conquer_feature_test_[a-f0-9]{12}$/D', basename($directory))) exit(2);
    Connection::init($directory);
    WorldContext::bind(1, 1);
    try { DailyQuestService::claimReward(1, 'daily_activity_40'); echo json_encode(['ok'=>true]); }
    catch (RuntimeException $e) { echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); }
    exit;
}

require __DIR__.'/Support/FeatureDatabase.php';
$fixture = new \ConquerTests\FeatureDatabase();
$db = Connection::getInstance();
$checks = 0;
$exit = 0;
function dailyCheck(bool $condition, string $label): void {
    global $checks;
    if (!$condition) throw new RuntimeException($label);
    $checks++;
}
function dailyReject(callable $fn, string $label): void {
    try { $fn(); }
    catch (RuntimeException|DomainException $e) { dailyCheck(true, $label); return; }
    throw new RuntimeException('Expected rejection: '.$label);
}
function dailyBalances(int $player=1): array {
    $db = Connection::getInstance();
    return ['gems'=>(int)$db->query('SELECT gems FROM players WHERE id=?',[$player])->fetchColumn(),
        'items'=>$db->query('SELECT item_code,quantity FROM player_inventory WHERE player_id=? ORDER BY item_code',[$player])->fetchAll()];
}
function dailyRows(int $player=1): array {
    return array_column(KingdomService::questState($player)['quests'], null, 'quest_code');
}
function dailyPoints(): int { return DailyQuestService::getState(1)['quest_activity']['points']; }

try {
    $catalog = json_decode(file_get_contents(ROOT_DIR.'/data/daily_quests.json'), true, 32, JSON_THROW_ON_ERROR);
    $definitions = array_column($catalog['quests'], null, 'code');
    $milestoneDefinitions = array_column($catalog['activity']['milestones'], null, 'code');
    dailyCheck(count($definitions)===13 && array_sum(array_column($definitions,'activity_points'))===130, 'Thirteen daily quests provide 130 optional activity points');
    dailyCheck(array_column($milestoneDefinitions,'target')===[20,40,60,80,100], 'Five activity thresholds are canonical server data');
    foreach ($definitions + $milestoneDefinitions as $definition) foreach ($definition['rewards'] as $reward) {
        if (!isset($reward['item_code'])) continue;
        $item = InventoryService::getItemDef($reward['item_code']);
        dailyCheck($item !== null && ($item['is_usable'] ?? true) && $reward['quantity']>0, 'Reward references a usable existing item');
        dailyCheck(is_file(ROOT_DIR.'/assets/art/items/'.$item['icon']), 'Reward icon exists');
    }
    foreach ([10201003=>'food',10201010=>'lumber',10201018=>'stone',10201026=>'gold'] as $code=>$resource) {
        $item = InventoryService::getItemDef($code);
        dailyCheck($item['resource']===$resource && $item['amount']===10000, 'Resource reward pack contains exactly 10k '.$resource);
    }
    foreach ([20=>20000,40=>40000,60=>60000,80=>80000,100=>100000] as $threshold=>$amount) {
        $rewards=$milestoneDefinitions['daily_activity_'.$threshold]['rewards'];
        $stock=array_column(array_filter($rewards,static fn($r)=>isset($r['item_code'])),'quantity','item_code');
        foreach ([10201003,10201010,10201018,10201026] as $code) dailyCheck(($stock[$code]??0)*10000===$amount, 'Milestone grants exact requested amount of every resource');
        dailyCheck(array_sum(array_column($rewards,'gems'))===([80=>50,100=>100][$threshold]??0), 'Milestone grants exact requested gems');
        dailyCheck(($stock[10105001]??0)===([80=>1,100=>3][$threshold]??0), 'Milestone grants exact requested silver chests');
        dailyCheck(($stock[10106001]??0)===($threshold===100?1:0), 'Only final milestone grants the 100 VIP point pack');
        dailyCheck(count($rewards)===($threshold===100?8:($threshold===80?7:4)), 'Milestone has no extra legacy rewards');
    }
    foreach ([10207011=>'normal',10207013=>'epic'] as $code=>$grade) {
        $item=InventoryService::getItemDef($code);
        dailyCheck($item['fragment_grade']===$grade&&$item['fragment_amount']===1&&!isset($item['treasure_code']), 'Single fragment item has correct grade and is not tied to one relic');
    }
    dailyCheck(InventoryService::getItemDef(10106001)['vip_points']===100, 'VIP reward contains exactly 100 points');
    foreach ([10103001=>300,10103002=>900,10103003=>3600,10103011=>3600,10103021=>3600] as $code=>$seconds) {
        dailyCheck(InventoryService::getItemDef($code)['duration_seconds']===$seconds, 'Speedup reward has expected duration');
    }

    $db->execute("INSERT INTO worlds(id,name,slug,status,map_size,map_seed) VALUES(2,'Second daily fixture','second-daily-fixture','running',256,43)");
    foreach ([1,2] as $player) {
        $db->execute('INSERT INTO players(id,username,email,password_hash,gems) VALUES(?,?,?,?,0)', [$player,'DailyFixture'.$player,'daily'.$player.'@tests.invalid','unused']);
        $db->execute('INSERT INTO kingdom_profiles(player_id,display_name,welcome_claimed) VALUES(?,?,1)', [$player,'DailyFixture'.$player]);
        foreach ([1,2] as $world) {
            $city = ($player-1)*2+$world;
            $db->execute('INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,food,lumber,stone,gold) VALUES(?,?,?,?,?,?,0,0,0,0)', [$city,$player,$world,'Daily city '.$city,30+$player,40]);
            foreach (CityState::BUILDING_CODES as $building) $db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(?,?,1)', [$city,$building]);
        }
    }
    WorldContext::bind(1, 1);
    // The first event of a UTC day must seed rows before adding its progress.
    DailyQuestService::trackProgress(2, 'attack_monster');
    dailyCheck(dailyRows(2)['attack_monster_1']['completed'], 'First event without a prior page view is retained');
    $initial = KingdomService::questState(1);
    dailyCheck($initial['quest_activity']['points']===0 && $initial['quest_activity']['max_points']===100, 'Unclaimed login has no activity points');
    dailyCheck(count($initial['quest_activity']['milestones'])===5, 'Milestones are exposed separately');
    dailyCheck(count(array_filter($initial['quests'], static fn($q)=>str_starts_with($q['quest_code'],'daily_activity_')))===0, 'Milestones never appear as ordinary quests');
    dailyCheck(count(array_filter($initial['quests'], static fn($q)=>($q['activity_points']??0)===10))===13, 'Only ordinary dailies advertise points');
    dailyReject(fn()=>DailyQuestService::claimReward(1,'daily_activity_20'), 'Locked milestone cannot be claimed');

    $gather = static function(int $player, int $world, array $loot, string $time='UTC_TIMESTAMP()', string $state='complete') use($db): void {
        $db->execute("INSERT INTO marches(player_id,world_id,march_type,origin_city_id,target_x,target_y,troops_json,departure_time,arrival_time,return_time,state,haul_json) VALUES(?,?,9,?,1,1,'{}',UTC_TIMESTAMP(),UTC_TIMESTAMP(),$time,?,?)",
            [$player,$world,($player-1)*2+$world,$state,json_encode(['loot'=>$loot],JSON_THROW_ON_ERROR)]);
    };
    $gather(1,1,['food'=>15000,'lumber'=>10000,'stone'=>25000]);
    $gather(1,2,['food'=>10000,'wood'=>15000,'gold'=>24999]);
    $gather(1,1,['gold'=>100000],'DATE_SUB(UTC_DATE(),INTERVAL 1 SECOND)');
    $gather(1,1,['gold'=>100000],'DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR)');
    $gather(1,1,['gold'=>100000],'UTC_TIMESTAMP()','returning');
    $gather(2,1,['gold'=>100000]);
    $quests = dailyRows();
    foreach (['food','lumber','stone'] as $resource) dailyCheck($quests['gather_'.$resource.'_25000']['progress']===25000 && $quests['gather_'.$resource.'_25000']['completed'], 'Completed cross-world gathering counts '.$resource.' once');
    dailyCheck($quests['gather_gold_25000']['progress']===24999 && !$quests['gather_gold_25000']['completed'], 'Old, future, pending and other-player gathering is excluded');
    dailyCheck($quests['collect_resources']['progress']===50000, 'Existing total-gather quest keeps its target and cap');
    dailyCheck(dailyRows()===$quests, 'Repeated reconciliation never adds gathering twice');
    WorldContext::bind(2,1);
    dailyCheck(dailyRows()['gather_food_25000']===$quests['gather_food_25000'], 'World switching preserves account-wide gathering');
    WorldContext::bind(1,1);
    $gather(1,2,['gold'=>1]);
    dailyCheck(dailyRows()['gather_gold_25000']['completed'], 'The last resource unit completes its daily quest');

    // Exercise real alliance help commands and their replay protection, not synthetic quest progress.
    foreach ([1,2] as $world) {
        $db->execute('INSERT INTO alliances(id,world_id,name,tag,leader_id) VALUES(?,?,?,?,2)', [$world,$world,'Daily alliance '.$world,'DQ'.$world]);
        foreach ([1,2] as $player) $db->execute('INSERT INTO alliance_members(alliance_id,player_id,world_id,role) VALUES(?,?,?,?)', [$world,$player,$world,$player===2?'leader':'member']);
    }
    for ($i=1;$i<=5;$i++) {
        $world = $i===5 ? 2 : 1;
        $db->execute("INSERT INTO building_queue(city_id,building_code,level_to,started_at,finishes_at,is_processed) VALUES(?,'farm',2,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),0)", [2+$world]);
        $queue = $db->lastInsertId();
        WorldContext::bind($world,2);
        $request = CommunityService::action(2, ['action'=>'help.request','queue_id'=>$queue,'queue_type'=>'building','request_id'=>'daily_help_request_'.$i],$world)['result'];
        WorldContext::bind($world,1);
        $body = ['action'=>'help.give','help_id'=>$request['id'],'request_id'=>'daily_help_give_'.$i];
        CommunityService::action(1,$body,$world);
        CommunityService::action(1,$body,$world);
    }
    WorldContext::bind(1,1);
    dailyCheck((int)$db->query('SELECT COUNT(*) FROM community_help_log WHERE helper_id=1')->fetchColumn()===5, 'Replayed help commands create only five durable helps');
    dailyCheck(dailyRows()['alliance_help_5']['completed'] && dailyRows()['alliance_help_5']['progress']===5, 'Helps across worlds complete the optional alliance quest once');
    dailyCheck(dailyPoints()===0, 'Completed but unclaimed quests still yield zero activity');

    // Permanent missions neither contribute nor reset with the daily activity track.
    $db->execute("UPDATE city_buildings SET level=2 WHERE city_id=1 AND building_code='barrack'");
    DailyQuestService::claimReward(1,'starter_barrack_2');
    dailyCheck(dailyPoints()===0, 'Claiming a permanent starter mission gives no daily points');
    $db->execute("INSERT INTO player_daily_quests(player_id,quest_code,quest_date,progress,target,completed,claimed) VALUES(1,'retired_daily',UTC_DATE(),1,1,1,1)");
    dailyCheck(dailyPoints()===0, 'An unknown historic quest row contributes no activity');
    DailyQuestService::claimReward(1,'login_daily');
    dailyCheck(dailyPoints()===10, 'Claiming one daily contributes its configured points');
    dailyReject(fn()=>DailyQuestService::claimReward(1,'daily_activity_20'), 'Ten points cannot unlock the twenty-point chest');
    DailyQuestService::claimReward(1,'gather_food_25000');
    dailyCheck(dailyPoints()===20, 'Second daily unlocks first milestone');

    $token = bin2hex(random_bytes(32));
    $db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,user_agent,expires_at,active_world_id) VALUES(1,?,'daily-activity-test','127.0.0.1','daily-activity-test',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),1)",[$token]);
    $url = $fixture->serve(<<<'PHP'
switch (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) {
    case '/quests': \Conquer\Api\Handlers\QuestHandler::list([]); break;
    case '/claim': \Conquer\Api\Handlers\QuestHandler::claim([]); break;
    case '/action': \Conquer\Api\Handlers\KingdomHandler::action([]); break;
    default: http_response_code(404);
}
PHP);
    $http = static function(string $path, ?array $body=null, int $expected=200, bool $auth=true, bool $csrf=true) use($url,$token): array {
        $curl=curl_init($url.$path);
        $headers=['Content-Type: application/json'];
        if($auth)$headers[]='Cookie: conquer_session='.$token;
        if($csrf)$headers[]='X-CSRF-Token: daily-activity-test';
        curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_HTTPHEADER=>$headers]);
        if($body!==null)curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($body,JSON_THROW_ON_ERROR)]);
        $raw=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);
        $response=json_decode((string)$raw,true);
        dailyCheck($status===$expected && is_array($response), 'HTTP '.$path.' returns '.$expected.': '.$raw);
        return $response;
    };
    $http('/claim',['quest_code'=>'daily_activity_20'],401,false);
    $http('/claim',['quest_code'=>'daily_activity_20'],403,true,false);
    // A concurrent kingdom update must produce a structured retryable response on both routes.
    $busyKey='conquer-player-1';
    dailyCheck((int)$db->query('SELECT GET_LOCK(?,0)',[$busyKey])->fetchColumn()===1, 'Fixture acquires the player lock for busy-response checks');
    try {
        $busyList=$http('/quests',null,503);
        $busyClaim=$http('/claim',['quest_code'=>'daily_activity_20'],503);
        dailyCheck($busyList['error']['code']==='QUEST_BUSY' && $busyClaim['error']['code']==='QUEST_BUSY', 'List and claim return structured busy errors instead of a fatal response');
    } finally { $db->query('SELECT RELEASE_LOCK(?)',[$busyKey]); }
    $preview=$http('/quests')['data'];
    dailyCheck($preview['quest_activity']===DailyQuestService::getState(1)['quest_activity'] && isset($preview['quest_resets_at']), 'Legacy list exposes the same authoritative activity and reset shape');
    dailyCheck(array_column($preview['quests'],null,'quest_code')['alliance_help_5']['completed'], 'Legacy list reconciles completed help');

    // Force a late item grant failure to prove both claim marker and earlier rewards roll back.
    $before=dailyBalances();
    $db->execute("CREATE TRIGGER daily_activity_fail BEFORE INSERT ON player_inventory FOR EACH ROW BEGIN IF NEW.player_id=1 AND NEW.item_code=10201026 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Fixture activity grant failure'; END IF; END");
    try {
        $http('/claim',['quest_code'=>'daily_activity_20'],422);
        dailyCheck(dailyBalances()===$before, 'Failed activity grant rolls back earlier resource packs');
        dailyCheck(!DailyQuestService::getState(1)['quest_activity']['milestones'][0]['claimed'] && dailyPoints()===20, 'Failed activity grant leaves chest claimable without consuming points');
    } finally { $db->execute('DROP TRIGGER daily_activity_fail'); }
    $reward=$http('/claim',['quest_code'=>'daily_activity_20'])['data']['rewards'];
    dailyCheck($reward===$milestoneDefinitions['daily_activity_20']['rewards'], 'Milestone payout exactly matches preview');
    $after=dailyBalances();
    $http('/action',['action'=>'quest.claim','quest_code'=>'daily_activity_20'],422);
    dailyCheck(dailyBalances()===$after && dailyPoints()===20, 'Cross-endpoint repeat does not duplicate reward or activity');

    foreach (['gather_lumber_25000','gather_stone_25000'] as $code) DailyQuestService::claimReward(1,$code);
    dailyCheck(dailyPoints()===40, 'Four ordinary claims reach forty points');
    $before=dailyBalances();
    $workers=[];
    for($i=0;$i<2;$i++) {
        $process=proc_open([PHP_BINARY,__FILE__,'--claim-worker',$fixture->sessionPath()],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,ROOT_DIR,null,['bypass_shell'=>true]);
        if(!is_resource($process))throw new RuntimeException('Unable to start concurrency worker.');
        fclose($pipes[0]);$workers[]=[$process,$pipes];
    }
    $wins=0;
    foreach($workers as [$process,$pipes]) {
        $raw=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$status=proc_close($process);$result=json_decode($raw,true);
        dailyCheck($status===0 && is_array($result),'Concurrent claim worker returned normally: '.$error.$raw);
        if($result['ok'])$wins++;
    }
    dailyCheck($wins===1, 'Two concurrent milestone claims produce exactly one winner');
    $stock=array_column(dailyBalances()['items'],'quantity','item_code');
    $oldStock=array_column($before['items'],'quantity','item_code');
    foreach ([10201003,10201010,10201018,10201026] as $code) dailyCheck(($stock[$code]??0)-($oldStock[$code]??0)===4, 'Concurrent reward grants each resource pack exactly once');
    dailyCheck(dailyPoints()===40, 'Concurrent reward grants no recursive activity');

    foreach (['gather_gold_25000','alliance_help_5','collect_resources'] as $code) DailyQuestService::claimReward(1,$code);
    DailyQuestService::trackProgress(1,'attack_monster',3);
    DailyQuestService::trackProgress(1,'open_chest');
    foreach (['attack_monster_1','attack_monster_3','open_chest_1'] as $code) DailyQuestService::claimReward(1,$code);
    dailyCheck(dailyPoints()===100, 'Ten daily claims reach the final chest without completing every quest');
    foreach ([60,80,100] as $threshold) {
        $code='daily_activity_'.$threshold;
        $before=dailyBalances();
        $response=$http('/action',['action'=>'quest.claim','quest_code'=>$code])['data'];
        dailyCheck($response['result']['rewards']===$milestoneDefinitions[$code]['rewards'], 'Kingdom claim grants exact milestone rewards');
        dailyCheck($response['state']['quest_activity']['points']===100, 'Activity chests do not count toward later milestones');
        $after=dailyBalances();$oldStock=array_column($before['items'],'quantity','item_code');$stock=array_column($after['items'],'quantity','item_code');
        foreach ($milestoneDefinitions[$code]['rewards'] as $reward) if(isset($reward['item_code'])) dailyCheck(($stock[$reward['item_code']]??0)-($oldStock[$reward['item_code']]??0)===$reward['quantity'], 'Claim credits exact inventory amount');
        dailyCheck($after['gems']-$before['gems']===([80=>50,100=>100][$threshold]??0), 'Claim credits exact gem balance');
    }
    foreach ([10207011=>'normal',10207013=>'epic'] as $fragmentCode=>$grade) {
        dailyCheck(InventoryService::quantity(1,$fragmentCode)===5, 'Milestone grants exactly five single fragment items');
        for($i=0;$i<5;$i++){
            $drop=KingdomService::action(1,['action'=>'inventory.use','item_code'=>$fragmentCode])['result']['drops'][0];
            dailyCheck($drop['quantity']===1&&\Conquer\Game\Treasure\TreasureData::get($drop['treasure_code'])['grade']===$grade, 'Each fragment item credits one fragment of the requested rarity');
        }
        dailyCheck(InventoryService::quantity(1,$fragmentCode)===0, 'Five fragments consume exactly five inventory items');
    }
    DailyQuestService::trackProgress(1,'upgrade_building');
    DailyQuestService::trackProgress(1,'train_troops',100);
    DailyQuestService::trackProgress(1,'research_complete');
    foreach (['upgrade_building_1','train_troops_100','research_complete_1'] as $code) DailyQuestService::claimReward(1,$code);
    dailyCheck(dailyPoints()===130 && DailyQuestService::getState(1)['quest_activity']['max_points']===100, 'Optional extra quests retain rewards beyond the final chest');

    // Advance only this isolated connection's clock; no clock change affects the user's game.
    $oldDate=DailyQuestService::ensureDailyQuests(1);
    $newTime=(new DateTimeImmutable($oldDate.' 00:00:00',new DateTimeZone('UTC')))->modify('+1 day')->getTimestamp();
    $db->execute('SET timestamp = '.($newTime+1));
    DailyQuestService::trackProgress(1,'attack_monster');
    $next=KingdomService::questState(1);$nextRows=array_column($next['quests'],null,'quest_code');
    dailyCheck($next['quest_activity']['points']===0 && !array_filter($next['quest_activity']['milestones'],static fn($m)=>$m['claimed']||$m['completed']), 'New UTC day resets all activity and claim flags');
    dailyCheck($nextRows['attack_monster_1']['completed'] && !$nextRows['attack_monster_1']['claimed'], 'First event after midnight seeds and completes a fresh quest');
    dailyCheck($nextRows['gather_food_25000']['progress']===0 && $nextRows['alliance_help_5']['progress']===0, 'Yesterday gathering and help do not carry over');
    dailyCheck($nextRows['starter_barrack_2']['claimed'], 'Permanent starter claim survives the daily reset');
    dailyCheck((int)$db->query('SELECT COUNT(*) FROM player_daily_quests WHERE player_id=1 AND quest_date=? AND quest_code LIKE ? AND claimed=1',[$oldDate,'daily_activity_%'])->fetchColumn()===5, 'Reset preserves historical milestone claims');
    dailyReject(fn()=>DailyQuestService::claimReward(1,'daily_activity_100'), 'Yesterday final chest cannot be reclaimed before today points');
    DailyQuestService::claimReward(1,'login_daily');DailyQuestService::claimReward(1,'attack_monster_1');
    DailyQuestService::claimReward(1,'daily_activity_20');
    dailyCheck(dailyPoints()===20 && DailyQuestService::getState(1)['quest_activity']['milestones'][0]['claimed'], 'Next day can earn and claim a fresh milestone');
    echo "PASS $checks daily quest activity checks (isolated database).\n";
} catch(Throwable $e) {
    $exit=1;fwrite(STDERR,'FAIL '.$e->getMessage()."\n".$e->getTraceAsString()."\n");
} finally {
    $db->execute('SET timestamp = 0');
    $fixture->close();
}
exit($exit);
