<?php
declare(strict_types=1);
/** Loot presentation and mobile retries use only a disposable database. */
if (PHP_SAPI !== 'cli') exit(1);
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR.'/src/Bootstrap.php';
\Conquer\Bootstrap::init(ROOT_DIR);
require __DIR__.'/Support/FeatureDatabase.php';

use Conquer\Db\Connection;
use Conquer\Game\Inventory\InventoryService;
use Conquer\Game\Kingdom\KingdomService;
use Conquer\Game\Map\MonsterData;
use Conquer\Game\March\BattleReportService;
use Conquer\Game\Treasure\{ChestService,TreasureData};

$fixture = new \ConquerTests\FeatureDatabase();
$db = Connection::getInstance();
$checks = 0;
$exit = 0;
function lootCheck(bool $ok, string $label): void {
    global $checks;
    if (!$ok) throw new RuntimeException($label);
    $checks++;
}
function lootReject(callable $fn, string $label): void {
    try { $fn(); } catch (DomainException) { lootCheck(true, $label); return; }
    throw new RuntimeException('Accepted: '.$label);
}
function lootBalances(): array {
    $db=Connection::getInstance();
    return [
        $db->query('SELECT item_code,quantity FROM player_inventory WHERE player_id=1 ORDER BY item_code')->fetchAll(),
        $db->query('SELECT treasure_code,fragments FROM player_treasures WHERE player_id=1 ORDER BY treasure_code')->fetchAll(),
        $db->query('SELECT * FROM player_chests WHERE player_id=1')->fetch(),
    ];
}
function lootUse(int $code, string $key): array {
    return KingdomService::action(1,['action'=>'inventory.use','item_code'=>$code,'operation_key'=>$key])['result'];
}
try {
    $db->execute("UPDATE worlds SET status='open',speed_factor=1 WHERE id=1");
    $db->execute("INSERT INTO players(id,username,email,password_hash,action_points)VALUES(1,'LootFixture','loot@tests.invalid','unused',0)");
    $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y)VALUES(1,1,1,'Loot city',30,40)");
    foreach (\Conquer\Game\City\CityState::BUILDING_CODES as $code) {
        $db->execute('INSERT INTO city_buildings(city_id,building_code,level)VALUES(1,?,1)',[$code]);
    }
    // Skip the welcome parcel so a chest really can be the last one owned.
    $db->execute("INSERT INTO kingdom_profiles(player_id,display_name,welcome_claimed)VALUES(1,'LootFixture',1)");
    KingdomService::state(1);
    $cache=new ReflectionProperty(ChestService::class,'dropTableCache');
    $cache->setValue(null,['chests'=>[
        'silver'=>['rolls'=>2,'drop_table'=>[['item_code'=>10105002,'quantity'=>3,'weight'=>1]]],
        'gold'=>['rolls'=>2,'drop_table'=>[['fragment_grade'=>'epic','quantity'=>5,'weight'=>1]]],
        'platinum'=>['rolls'=>1,'drop_table'=>[['item_code'=>99999999,'quantity'=>1,'weight'=>1]]],
    ]]);

    InventoryService::addItems(1,10105001,1);
    $first=lootUse(10105001,'loot_last_chest_once');
    $after=lootBalances();
    lootCheck(lootUse(10105001,'loot_last_chest_once')===$first && lootBalances()===$after,'last chest retry returns original rewards without reroll or consumption');
    lootCheck((int)$db->query('SELECT quantity FROM player_inventory WHERE player_id=1 AND item_code=10105001')->fetchColumn()===0,'exactly the last chest was consumed');
    lootCheck((int)$db->query('SELECT quantity FROM player_inventory WHERE player_id=1 AND item_code=10105002')->fetchColumn()===6,'duplicate drop slots award their real quantities');
    lootCheck(count($first['drops'])===2 && $first['drops'][0]['icon']===InventoryService::getItemDef(10105002)['icon'] && $first['drops'][0]['rarity']==='epic','gold chest has chest icon, rarity and quantity in result');
    lootReject(fn()=>lootUse(10105001,'loot_last_chest_again'),'new request cannot consume absent chest');
    lootReject(fn()=>lootUse(10105002,'loot_last_chest_once'),'receipt cannot be reused for another item');
    lootReject(fn()=>lootUse(10105002,''),'invalid operation key never consumes');
    lootCheck(lootBalances()===$after,'rejected requests preserve all rewards');

    $fragmentsBefore=(int)$db->query('SELECT COALESCE(SUM(fragments),0) FROM player_treasures WHERE player_id=1')->fetchColumn();
    $gold=lootUse(10105002,'loot_gold_fragments_once');
    foreach ($gold['drops'] as $drop) {
        $def=TreasureData::get($drop['treasure_code']);
        lootCheck($drop['type']==='fragment' && $drop['quantity']===5 && $drop['name']===$def['name_de'] && $drop['icon']===$def['icon'] && $drop['grade']==='epic','fragment reward names the actual selected treasure and icon');
    }
    lootCheck((int)$db->query('SELECT COALESCE(SUM(fragments),0) FROM player_treasures WHERE player_id=1')->fetchColumn()===$fragmentsBefore+10,'displayed fragments match real credit');
    $after=lootBalances();
    lootCheck(lootUse(10105002,'loot_gold_fragments_once')===$gold && lootBalances()===$after,'fragment reward receipt retains original random picks');

    InventoryService::addItems(1,10300003,1);
    $fragment=lootUse(10300003,'loot_named_fragment_once')['drops'][0];
    lootCheck($fragment['treasure_code']===60300115 && $fragment['name']===TreasureData::get(60300115)['name_de'] && !empty($fragment['icon']),'specific Portalsphaere fragment supplies full metadata');
    InventoryService::addItems(1,10205001,1);
    $box=lootUse(10205001,'loot_resource_box_once');
    lootCheck($box['drops'][0]['type']==='resource' && $box['drops'][0]['quantity']===$box['amount'] && $box['drops'][0]['resource']===$box['resource'],'random resource box reveals actual credited resource and amount');
    lootCheck(lootUse(10205001,'loot_resource_box_once')===$box,'resource box retry retains random outcome');

    InventoryService::addItems(1,10105003,1);
    $before=lootBalances();
    try { lootUse(10105003,'loot_invalid_reward_once'); throw new LogicException('Invalid reward accepted'); }
    catch (RuntimeException $e) { lootCheck(!($e instanceof LogicException),'invalid configured reward is rejected'); }
    lootCheck(lootBalances()===$before,'failed grant rolls back inventory consumption');
    lootCheck((int)$db->query("SELECT COUNT(*) FROM game_operation_receipts WHERE operation_key='loot_invalid_reward_once'")->fetchColumn()===0,'failed grant leaves no success receipt');

    $freeBody=['action'=>'chest.free','chest_type'=>'gold','operation_key'=>'loot_free_chest_once'];
    $free=KingdomService::action(1,$freeBody)['result'];
    $after=lootBalances();
    lootCheck(KingdomService::action(1,$freeBody)['result']===$free && lootBalances()===$after,'free chest retry restores the exact result through cooldown');
    lootReject(fn()=>KingdomService::action(1,array_replace($freeBody,['operation_key'=>'loot_free_chest_again'])),'new free chest request still respects cooldown');

    foreach ([119000001=>'building',120104103=>'unavailable'] as $code=>$context) {
        InventoryService::addItems(1,$code,1);
        $def=InventoryService::getItemDef($code);
        lootCheck($def['usage_context']===$context && !empty($def['usage_hint']),'material explains its real usage context');
        try { lootUse($code,'loot_material_'.$code); throw new RuntimeException('Material consumed'); }
        catch (DomainException $e) { lootCheck($e->getMessage()===$def['usage_hint'],'material action explains why it cannot be directly consumed'); }
        lootCheck((int)$db->query('SELECT quantity FROM player_inventory WHERE player_id=1 AND item_code=?',[$code])->fetchColumn()===1,'material remains owned');
    }

    foreach (json_decode(file_get_contents(ROOT_DIR.'/data/monsters.json'),true)['monsters'] as $monster) {
        foreach (MonsterData::get((int)$monster['code'])['drops'] ?? [] as $drop) {
            $def=InventoryService::getItemDef((int)$drop['item_code']);
            lootCheck($drop['icon']===$def['icon'] && $drop['rarity']===$def['rarity'],'monster reward preview uses actual catalog icon and rarity');
        }
    }
    $present=new ReflectionMethod(BattleReportService::class,'present');
    $old=$present->invoke(null,['data_json'=>json_encode(['items'=>[10105002=>2],'attacker_damage'=>731]),'march_state'=>'complete','rally_state'=>null,'target_type'=>3]);
    lootCheck($old['details']['item_rewards'][0]['count']===2 && $old['details']['item_rewards'][0]['icon']===InventoryService::getItemDef(10105002)['icon'] && $old['details']['attacker_damage']===731,'old report recovers reward icons without rewriting combat or quantities');

    // Exercise real authenticated HTTP handlers. The worker has a fresh PHP cache.
    $token=bin2hex(random_bytes(32));
    $db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,user_agent,expires_at,active_world_id)VALUES(1,?,'loot-test','127.0.0.1','loottest',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 10 MINUTE),1)",[$token]);
    $url=$fixture->serve('if (str_contains($_SERVER[\'REQUEST_URI\'],\'open-chest\')) \\Conquer\\Api\\Handlers\\TreasureHandler::openChest([]); else \\Conquer\\Api\\Handlers\\InventoryHandler::use([]);');
    $request=static function(string $path,array $body,int $expected=200)use($url,$token):array {
        $curl=curl_init($url.$path);
        curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($body),CURLOPT_HTTPHEADER=>['Content-Type: application/json','Cookie: conquer_session='.$token,'X-CSRF-Token: loot-test'],CURLOPT_TIMEOUT=>15]);
        $raw=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);
        $result=json_decode((string)$raw,true);
        lootCheck($status===$expected && is_array($result),'handler status '.$expected.' actual '.$status.' '.$raw);
        return $result;
    };
    InventoryService::addItems(1,10105001,1);
    $body=['item_code'=>10105001,'operation_key'=>'loot_http_inventory_once'];
    $http=$request('/use',$body);$after=lootBalances();
    lootCheck($request('/use',$body)['data']===$http['data'] && lootBalances()===$after,'legacy inventory HTTP route forwards and replays its operation key');
    lootCheck(!empty($http['data']['drops'][0]['icon']),'legacy inventory route returns displayable loot');
    ChestService::addChest(1,'gold',1);
    $body=['chest_type'=>'gold','operation_key'=>'loot_http_owned_chest_once'];
    $http=$request('/open-chest',$body);$after=lootBalances();
    lootCheck($request('/open-chest',$body)['data']['rewards']===$http['data']['rewards'] && lootBalances()===$after,'legacy owned chest HTTP route safely replays the last chest');
    $request('/open-chest',array_replace($body,['chest_type'=>'platinum']),400);
    lootCheck(lootBalances()===$after,'legacy receipt binds chest type');
    echo "PASS $checks inventory reward and retry checks (isolated database).\n";
} catch (Throwable $e) {
    $exit=1;fwrite(STDERR,'FAIL '.$e->getMessage()."\n".$e->getTraceAsString()."\n");
} finally {
    $fixture->close();
}
exit($exit);
