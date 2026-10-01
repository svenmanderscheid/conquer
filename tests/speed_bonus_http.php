<?php
declare(strict_types=1);
/** Real VIP10/relic stacks, retired items, API previews and stored queue durations in a disposable world. */
if (PHP_SAPI !== 'cli') exit(1);
define('ROOT_DIR', dirname(__DIR__));
date_default_timezone_set('UTC');
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';
require __DIR__.'/Support/HttpApp.php';

use Conquer\Db\Connection;
use Conquer\Game\City\CityState;
use Conquer\Game\Research\{BuffEngine,ResearchData};
use Conquer\Game\Player\{LordLevel,MasteryService};
use Conquer\Game\Treasure\{TreasureData,TreasureService};
use Conquer\Game\Inventory\InventoryService;
use Conquer\Game\Kingdom\KingdomService;
use Conquer\Game\World\WorldContext;
use ConquerTests\HttpApp;

function speedCheck(bool $ok, string $label, array $details = []): void {
    if (!$ok) throw new RuntimeException($label.' '.json_encode($details));
    echo "PASS $label\n";
}
function speedNear(float $a, float $b): bool { return abs($a-$b)<1e-8; }
function speedElapsed(array $queue): int { return strtotime($queue['finishes_at'])-strtotime($queue['started_at']); }

$fixture = new \ConquerTests\FeatureDatabase();
try {
    $db = Connection::getInstance();
    WorldContext::bind(1);
    \Conquer\Logger::init(sys_get_temp_dir().'/conquer-speed-bonus-test.log','ERROR');
    $db->execute("UPDATE worlds SET status='running' WHERE id=1");
    $db->execute("INSERT INTO players(id,username,email,password_hash,vip_points,vip_level) VALUES(1,'SpeedFixture','speed@tests.invalid','unused',200000,10)");
    $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,castle_level,food,lumber,stone,gold) VALUES(1,1,1,'Speed',65,65,30,500000000,500000000,500000000,500000000)");
    foreach (CityState::BUILDING_CODES as $code) {
        $db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(1,?,?)',[$code,$code==='barrack'?29:30]);
    }
    foreach (ResearchData::allNodes() as $node) {
        if ($node['code']!=='crusader') $db->execute('INSERT INTO player_research(player_id,world_id,research_code,level) VALUES(1,1,?,?)',[$node['code'],$node['max_level']]);
    }
    $ranks = MasteryService::validate(['gather_0'=>5,'gather_1'=>5,'gather_2'=>5,'gather_3'=>5,'gather_5'=>5],60);
    $db->execute('INSERT INTO player_lord_progress(player_id,world_id,xp) VALUES(1,1,?)',[LordLevel::totalForLevel(60)]);
    foreach ($ranks as $code=>$rank) $db->execute('INSERT INTO player_lord_talents(player_id,world_id,talent_code,rank) VALUES(1,1,?,?)',[$code,$rank]);
    $db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id) VALUES(1,1,'Speed Alliance','SPD',1)");
    $db->execute("INSERT INTO alliance_members(alliance_id,player_id,world_id,role) VALUES(1,1,1,'leader')");
    $db->execute("INSERT INTO alliance_research(alliance_id,research_code,level) VALUES(1,'ally_construction_speed',20),(1,'ally_research_speed',20)");
    foreach (['construction','research'] as $category) {
        $db->execute("INSERT INTO player_charms_active(player_id,world_id,stat_category,grade,charm_code,bonus_pct,expires_at) VALUES(1,1,?,'legendary',10700003,10,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 4 HOUR))",[$category]);
    }
    foreach ([60300108,60300115,60300117,60400003,60400102,60400112] as $slot=>$code) {
        $db->execute('INSERT INTO player_treasures(player_id,treasure_code,fragments) VALUES(1,?,1000)',[$code]);
        foreach (TreasureData::get($code)['effects'] as $index=>$effect) {
            $db->execute('INSERT INTO player_treasure_effects(player_id,treasure_code,effect_index,parts) VALUES(1,?,?,5)',[$code,$index]);
        }
        speedCheck(TreasureService::equipTreasure(1,$code,$slot+1,30,1),'max-level relic equips in available slot '.($slot+1));
    }
    foreach ([10102021,10202010,10102031,10202011] as $code) {
        // Simulate ownership saved before removal. It stays in storage, but cannot be shown, granted or used.
        $db->execute('INSERT INTO player_inventory(player_id,item_code,quantity) VALUES(1,?,2)',[$code]);
        InventoryService::addItems(1,$code,1);
        speedCheck((int)$db->query('SELECT quantity FROM player_inventory WHERE player_id=1 AND item_code=?',[$code])->fetchColumn()===2,'retired item cannot be granted '.$code);
        speedCheck(InventoryService::getItemDef($code)===null && !isset(InventoryService::allDefs()[$code]),'retired item is absent from definitions '.$code);
        foreach (['inventory.use','inventory.buy'] as $action) {
            $rejected=false;
            try { KingdomService::action(1,['action'=>$action,'item_code'=>$code,'quantity'=>1,'request_id'=>'retired_item_check_'.$code,'expected_world_id'=>1]); }
            catch (DomainException) { $rejected=true; }
            speedCheck($rejected,'retired item rejects '.$action.' '.$code);
        }
    }
    speedCheck(!array_intersect(InventoryService::RETIRED_ITEMS,array_column(InventoryService::getInventory(1),'item_code')),'old owned items are hidden in inventory');
    $rewardConfig=['rolls'=>4,'drop_table'=>[['item_code'=>10102021,'quantity'=>1,'weight'=>1000],['item_code'=>10101011,'quantity'=>1,'weight'=>1]]];
    $db->execute("INSERT INTO reward_overrides(source_type,source_key,config_json,updated_by) VALUES('chest','silver',?,1)",[json_encode($rewardConfig)]);
    \Conquer\Game\Rewards\RewardCatalog::resetCache();
    $drops=\Conquer\Game\Treasure\ChestService::rollDropTable('silver');
    speedCheck(count($drops)===4 && array_unique(array_column($drops,'item_code'))===[10101011],'historical chest overrides cannot roll retired items');
    array_pop($rewardConfig['drop_table']);
    $db->execute("UPDATE reward_overrides SET config_json=? WHERE source_type='chest' AND source_key='silver'",[json_encode($rewardConfig)]);
    \Conquer\Game\Rewards\RewardCatalog::resetCache();
    $drops=\Conquer\Game\Treasure\ChestService::rollDropTable('silver');
    speedCheck(count($drops)===4 && !array_intersect(InventoryService::RETIRED_ITEMS,array_column($drops,'item_code')),'all-retired historical chest pool falls back to current rewards without losing rolls');
    $db->execute("DELETE FROM reward_overrides WHERE source_type='chest' AND source_key='silver'");
    \Conquer\Game\Rewards\RewardCatalog::resetCache();
    foreach (['construction_speed'=>10102021,'research_speed'=>10102031] as $category=>$code) {
        $db->execute("INSERT INTO player_charms_active(player_id,stat_category,grade,charm_code,bonus_pct,expires_at) VALUES(1,?,'normal',?,25,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 8 HOUR))",[$category,$code]);
    }
    $db->execute("INSERT INTO active_buffs(player_id,buff_type,multiplier,expires_at) VALUES(1,'research_boost',1.25,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 8 HOUR))");
    speedCheck(\Conquer\Game\Buff\ActiveBuffService::getMultiplier(1,'research_boost')===1.0,'previously activated research item no longer changes speed');
    speedCheck(\Conquer\Game\Buff\ActiveBuffService::getActive(1)===[],'retired research multiplier is absent from active item list');
    $effects=\Conquer\Game\Buff\ActiveEffectService::forPlayer(1,1);
    speedCheck(count($effects)===2 && !array_intersect(['construction_speed','research_speed'],array_column($effects,'stat_category')),'map runes remain visible but removed item effects do not');
    $buffs = BuffEngine::getBuffs(1,1);
    $city = CityState::loadForPlayer(1);
    speedCheck(speedNear($city['vip']['bonuses']['construction_speed'],155) && speedNear($buffs['research_speed'],1.62),'actual catalogue stack is +155% construction and +162% research without removed items');
    speedCheck(speedNear($buffs['talent_construction_speed'],.1) && speedNear($buffs['talent_research_speed'],.1),'both legal talent bonuses reach ten percent');

    $token=str_repeat('d',64); $csrf=str_repeat('c',64);
    $db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,user_agent,expires_at,active_world_id) VALUES(1,?,?,'127.0.0.1','speed-http',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),1)",[$token,$csrf]);
    $base=$fixture->serve(HttpApp::source(),['-d','display_errors=0']);
    $call=static function(string $path,string $method='GET',array $body=[]) use($base,$token,$csrf): array {
        $r=HttpApp::request($base,$path,$method,['Content-Type: application/json','Cookie: conquer_session='.$token,'X-CSRF-Token: '.$csrf,'X-World-ID: 1'],$method==='GET'?null:json_encode($body));
        speedCheck($r['status']===200 && ($r['json']['ok']??false),"$method $path succeeds",$r);
        return $r['json']['data'];
    };
    $preview=$call('/api/game/state');
    $researchPreview=$call('/api/research/state');
    speedCheck(speedNear($preview['research_duration_factor'],1/(2.62*1.1)) && speedNear($researchPreview['research_duration_factor'],$preview['research_duration_factor']),'main app and research page share the actual stacked duration factor');
    speedCheck($preview['buildings']['barrack']['seconds']===308022,'ten-day building preview remains over three days with all VIP10 bonuses');
    $build=$call('/api/city/upgrade-building','POST',['building_code'=>'barrack','expected_level'=>29,'expected_world_id'=>1])['queue_entry'];
    speedCheck(speedElapsed($build)===308022 && speedElapsed($build)===$preview['buildings']['barrack']['seconds'],'real building queue matches its preview exactly');
    $research=$call('/api/research/start','POST',['code'=>'crusader','level_to'=>1,'expected_world_id'=>1])['queue_entry'];
    $source=ResearchData::get('crusader')['levels'][0];
    speedCheck(speedElapsed($research)===1349064 && speedElapsed($research)===(int)ceil($source['time']*$preview['research_duration_factor']),'real T5 research lasts 15 days 14 hours 44 minutes 24 seconds and matches preview');

    // Castle 30 has a 35-day base and cannot use Academy 30's final construction research yet.
    $db->execute('UPDATE cities SET castle_level=29 WHERE id=1');
    $db->execute("UPDATE city_buildings SET level=29 WHERE city_id=1 AND building_code IN('castle','academy')");
    $db->execute("UPDATE player_research SET level=9 WHERE player_id=1 AND world_id=1 AND research_code='advanced_construction_speed'");
    $castlePreview=$call('/api/game/state');
    speedCheck($castlePreview['buildings']['castle']['seconds']===1099637,'35-day castle upgrade takes 12 days 17 hours 27 minutes 17 seconds with the pre-castle30 maximum');
    $castle=$call('/api/city/upgrade-building','POST',['building_code'=>'castle','expected_level'=>29,'expected_world_id'=>1])['queue_entry'];
    speedCheck(speedElapsed($castle)===1099637,'real castle queue uses the approved 35-day base and its current speed bonuses');

    // Expiring bonuses must change only future previews, never a paid running order.
    $db->execute('UPDATE player_charms_active SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE player_id=1');
    $db->execute('UPDATE active_buffs SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE player_id=1');
    $after=$call('/api/game/state');
    $afterResearch=$call('/api/research/state');
    speedCheck($after['research_duration_factor']>$preview['research_duration_factor'],'expired runes and items increase only the next research duration');
    speedCheck($after['buildings']['archery_range']['seconds']===0,'maximum building remains unavailable');
    speedCheck($afterResearch['queue']['finishes_at']===$research['finishes_at'],'running research keeps its originally paid completion time after expiry');
    $stored=$db->query("SELECT started_at,finishes_at FROM building_queue WHERE city_id=1 AND building_code='barrack' AND is_processed=0")->fetch();
    speedCheck($stored['finishes_at']===$build['finishes_at'] && speedElapsed($stored)===308022,'running construction keeps its originally paid completion time after expiry');
    echo "ALL SPEED BONUS HTTP CHECKS PASSED\n";
} finally {
    $fixture->close();
}
