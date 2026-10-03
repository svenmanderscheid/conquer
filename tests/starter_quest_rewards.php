<?php
declare(strict_types=1);
/** Guaranteed starter quest rewards, atomic claims and redemption in a disposable DB only. */
if (PHP_SAPI !== 'cli') exit(1);
date_default_timezone_set('UTC');
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';

use Conquer\Db\Connection;
use Conquer\Game\City\CityState;
use Conquer\Game\Inventory\InventoryService;
use Conquer\Game\Locale;
use Conquer\Game\Quest\DailyQuestService;

$fixture = new \ConquerTests\FeatureDatabase();
$db = Connection::getInstance();
$checks = 0;
$exit = 0;
function starterQuestCheck(bool $ok, string $message): void {
    global $checks;
    if (!$ok) throw new RuntimeException($message);
    $checks++;
}
function starterQuestBalances(int $player): array {
    $db = Connection::getInstance();
    return [
        'gems'=>(int)$db->query('SELECT gems FROM players WHERE id=?', [$player])->fetchColumn(),
        'items'=>$db->query('SELECT item_code,quantity FROM player_inventory WHERE player_id=? ORDER BY item_code', [$player])->fetchAll(),
        'cities'=>$db->query('SELECT id,food,lumber,stone,gold FROM cities WHERE player_id=? ORDER BY id', [$player])->fetchAll(),
    ];
}

try {
    $expected = [
        'upgrade_building_1'=>[
            ['item_code'=>10103011,'quantity'=>1], ['gems'=>15],
            ['item_code'=>10201010,'quantity'=>1], ['item_code'=>10201018,'quantity'=>1], ['item_code'=>10201026,'quantity'=>1],
        ],
        'train_troops_100'=>[
            ['item_code'=>10103031,'quantity'=>1], ['gems'=>15],
            ['item_code'=>10201003,'quantity'=>2], ['item_code'=>10201026,'quantity'=>1],
        ],
        'research_complete_1'=>[
            ['item_code'=>10103021,'quantity'=>1], ['gems'=>20],
            ['item_code'=>10201010,'quantity'=>1], ['item_code'=>10201018,'quantity'=>1], ['item_code'=>10201026,'quantity'=>1],
        ],
    ];
    $packs = [10201003=>'food',10201010=>'lumber',10201018=>'stone',10201026=>'gold'];
    foreach ($packs as $code=>$resource) {
        $def = InventoryService::getItemDef($code);
        starterQuestCheck($def !== null && $def['category']==='resource_pack' && $def['resource']===$resource && $def['amount']===10000, 'Existing pack has the exact resource and amount: '.$code);
        starterQuestCheck(is_file(ROOT_DIR.'/assets/art/items/'.$def['icon']), 'Existing pack icon is available: '.$code);
        starterQuestCheck(Locale::text($def['name'],'en')==='10,000 '.$resource, 'English pack name is available: '.$code);
        starterQuestCheck(str_starts_with(Locale::text($def['description_de'],'en'),'Instantly adds 10,000 '), 'English pack description is available: '.$code);
    }

    $db->execute("INSERT INTO worlds(id,name,slug,status,map_size,map_seed) VALUES(2,'Other Fixture Realm','other-fixture-realm','running',256,43)");
    $tokens = [];
    foreach ([1,2] as $player) {
        $db->execute('INSERT INTO players(id,username,email,password_hash,gems) VALUES(?,?,?,?,0)', [$player,'StarterQuest'.$player,'starter-quest'.$player.'@tests.invalid','unused']);
        $db->execute('INSERT INTO kingdom_profiles(player_id,display_name,welcome_claimed) VALUES(?,?,1)', [$player,'StarterQuest'.$player]);
        foreach ([1,2] as $world) {
            $city = ($player-1)*2+$world;
            $db->execute('INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,food,lumber,stone,gold) VALUES(?,?,?,?,?,?,0,0,0,0)', [$city,$player,$world,'Starter city '.$city,30+$player,40]);
            foreach (CityState::BUILDING_CODES as $building) {
                $db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(?,?,?)', [$city,$building,in_array($building,['castle','treasure_house'],true)?1:0]);
            }
        }
        DailyQuestService::ensureDailyQuests($player);
        $tokens[$player] = bin2hex(random_bytes(32));
        $db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,user_agent,expires_at,active_world_id) VALUES(?,?,'starter-quest-test','127.0.0.1','starter-quest-test',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 10 MINUTE),2)", [$player,$tokens[$player]]);
    }
    $url = $fixture->serve(<<<'PHP'
switch (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) {
    case '/quests': \Conquer\Api\Handlers\QuestHandler::list([]); break;
    case '/legacy-claim': \Conquer\Api\Handlers\QuestHandler::claim([]); break;
    case '/action': \Conquer\Api\Handlers\KingdomHandler::action([]); break;
    default: http_response_code(404);
}
PHP);
    $request = static function(int $player, string $path, ?array $body=null, int $expectedStatus=200) use ($url,$tokens): array {
        $curl = curl_init($url.$path);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>[
            'Content-Type: application/json','Cookie: conquer_session='.$tokens[$player], 'X-CSRF-Token: starter-quest-test',
        ],CURLOPT_TIMEOUT=>20]);
        if ($body !== null) curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($body,JSON_THROW_ON_ERROR)]);
        $raw = curl_exec($curl);
        $status = (int)curl_getinfo($curl,CURLINFO_HTTP_CODE);
        curl_close($curl);
        $response = json_decode((string)$raw,true);
        starterQuestCheck($status===$expectedStatus && is_array($response), 'HTTP '.$path.' expected '.$expectedStatus.', got '.$status.': '.$raw);
        return $response;
    };

    // Both real authenticated entry points must give precisely what their preview promises.
    foreach ([1=>'/action',2=>'/legacy-claim'] as $player=>$path) {
        $preview = array_column($request($player,'/quests')['data']['quests'],null,'quest_code');
        $before = starterQuestBalances($player);
        foreach ($expected as $quest=>$rewards) {
            starterQuestCheck($preview[$quest]['rewards']===$rewards, 'Preview contains all unchanged and new rewards: '.$quest);
            $body = ['action'=>'quest.claim','quest_code'=>$quest,'expected_world_id'=>2];
            $request($player,$path,$body,422);
        }
        starterQuestCheck(starterQuestBalances($player)===$before, 'Incomplete quests award nothing on '.$path);
        DailyQuestService::trackProgress($player,'train_troops',99);
        $request($player,$path,['action'=>'quest.claim','quest_code'=>'train_troops_100','expected_world_id'=>2],422);
        starterQuestCheck(starterQuestBalances($player)===$before, '99 of 100 trained troops still award nothing');
        DailyQuestService::trackProgress($player,'train_troops',1);
        DailyQuestService::trackProgress($player,'upgrade_building');
        DailyQuestService::trackProgress($player,'research_complete');

        // Fail after the speedup, gems, wood and stone have been granted.
        // The direct legacy route must be just as atomic as the main action.
        $db->execute("CREATE TRIGGER starter_quest_fail BEFORE INSERT ON player_inventory FOR EACH ROW BEGIN IF NEW.player_id=$player AND NEW.item_code=10201026 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Fixture grant failure'; END IF; END");
        try {
            $request($player,$path,['action'=>'quest.claim','quest_code'=>'upgrade_building_1','expected_world_id'=>2],422);
            starterQuestCheck(starterQuestBalances($player)===$before, 'Failed grant rolls back every reward on '.$path);
            starterQuestCheck((int)$db->query("SELECT claimed FROM player_daily_quests WHERE player_id=? AND quest_code='upgrade_building_1' AND quest_date=UTC_DATE()",[$player])->fetchColumn()===0, 'Failed grant leaves the reward claimable');
        } finally {
            $db->execute('DROP TRIGGER starter_quest_fail');
        }

        foreach ($expected as $quest=>$rewards) {
            $body = ['action'=>'quest.claim','quest_code'=>$quest,'expected_world_id'=>2];
            $response = $request($player,$path,$body)['data'];
            $delivered = $path==='/action' ? $response['result']['rewards'] : $response['rewards'];
            starterQuestCheck($delivered===$rewards, 'Claim result matches exact preview: '.$quest);
            $after = starterQuestBalances($player);
            $request($player,$path,$body,422);
            starterQuestCheck(starterQuestBalances($player)===$after, 'Repeated claim does not grant again: '.$quest);
        }
        $stock = array_column(starterQuestBalances($player)['items'],'quantity','item_code');
        starterQuestCheck($stock===[10103011=>1,10103021=>1,10103031=>1,10201003=>2,10201010=>2,10201018=>2,10201026=>3], 'All three daily quests grant exact guaranteed inventory totals');
        starterQuestCheck(starterQuestBalances($player)['gems']===50, 'All existing gem rewards are preserved');
        starterQuestCheck(starterQuestBalances($player)['cities']===$before['cities'], 'Claimed packs stay in inventory until opened');

        $after = starterQuestBalances($player);
        $refreshed = array_column($request($player,'/quests')['data']['quests'],null,'quest_code');
        foreach ($expected as $quest=>$rewards) starterQuestCheck($refreshed[$quest]['claimed']===true, 'Previously claimed quest remains claimed after refresh');
        starterQuestCheck(starterQuestBalances($player)===$after, 'Refresh never retroactively re-rewards claimed quests');
    }

    // Open the actual earned stacks in the authenticated active second world.
    foreach ($packs as $code=>$resource) {
        $amount = $resource==='gold'?30000:20000;
        $body = ['action'=>'inventory.use','item_code'=>$code,'use_all'=>true,'operation_key'=>'starter_quest_pack_'.$code,'expected_world_id'=>2];
        $result = $request(1,'/action',$body)['data']['result'];
        starterQuestCheck($result['resource']===$resource && $result['amount']===$amount, 'Redemption returns full '.$resource.' value');
        starterQuestCheck((int)$db->query("SELECT $resource FROM cities WHERE player_id=1 AND world_id=2")->fetchColumn()===$amount, 'Full '.$resource.' amount arrives in the active world');
        starterQuestCheck((int)$db->query("SELECT $resource FROM cities WHERE player_id=1 AND world_id=1")->fetchColumn()===0, 'Other world receives no '.$resource);
        $after = starterQuestBalances(1);
        starterQuestCheck($request(1,'/action',$body)['data']['result']===$result && starterQuestBalances(1)===$after, 'Redemption retry preserves balances and returns its original receipt');
    }
    echo "PASS $checks starter quest reward checks (isolated database).\n";
} catch (Throwable $e) {
    $exit = 1;
    fwrite(STDERR,'FAIL '.$e->getMessage()."\n".$e->getTraceAsString()."\n");
} finally {
    $fixture->close();
}
exit($exit);
