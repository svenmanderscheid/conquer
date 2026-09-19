<?php
declare(strict_types=1);
/** Focused service regressions using a temporary, isolated account; no real player is changed. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/src/Bootstrap.php';
\Conquer\Bootstrap::init(ROOT_DIR);
use Conquer\Db\Connection;
use Conquer\Game\Kingdom\KingdomService;
require __DIR__.'/Support/FeatureDatabase.php';$fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();$pid=0;$city=0;$aid=0;$eid=0;$failed=false;
function expectKingdom(bool $ok,string $label): void { if(!$ok) { throw new RuntimeException($label); } echo "PASS $label\n"; }
try {
    $name='KingdomReg'.bin2hex(random_bytes(4));
    $pid=$db->transaction(static function(Connection $db) use($name): int {
        $db->execute('INSERT INTO players(username,email,password_hash) VALUES(?,?,?)',[$name,$name.'@tests.invalid',password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT)]);
        $id=$db->lastInsertId();\Conquer\Auth\OAuth::createDefaultCity($db,$id,$name);return $id;
    });
    $city=(int)$db->query('SELECT id FROM cities WHERE player_id=?',[$pid])->fetchColumn();
    $s=KingdomService::state($pid);$basePower=$s['profile']['power'];
    expectKingdom($s['profile']['city_skin']==='default','new kingdoms use the default village skin');
    $unownedDenied=false;try{KingdomService::action($pid,['action'=>'skin.save','city_skin'=>'ironkeep']);}catch(DomainException){$unownedDenied=true;}
    expectKingdom($unownedDenied,'new kingdoms cannot equip an unowned premium village skin');
    foreach(['ironkeep','rosehall','dragon']as$skin)$db->execute('INSERT INTO player_castle_skins(player_id,skin_code) VALUES(?,?)',[$pid,$skin]);
    foreach(['ironkeep','rosehall','dragon','default'] as $skin) {
        KingdomService::action($pid,['action'=>'skin.save','city_skin'=>$skin]);
        expectKingdom(KingdomService::state($pid)['profile']['city_skin']===$skin,'village skin persists: '.$skin);
    }
    $rejected=false;
    try { KingdomService::action($pid,['action'=>'skin.save','city_skin'=>'unknown']); } catch(\Throwable $e) { $rejected=true; }
    expectKingdom($rejected&&KingdomService::state($pid)['profile']['city_skin']==='default','invalid skin cannot change saved appearance');

    $db->execute('UPDATE players SET gems=123,action_points=77,last_ap_regen=UTC_TIMESTAMP(),vip_points=40 WHERE id=?',[$pid]);
    $own=KingdomService::state($pid)['profile'];
    expectKingdom($own['gems']===123 && $own['action_points']===77 && $own['action_points_max']===200 && $own['prestige_points']===40,'own profile shows exact persisted reward balances');
    $publicTarget=(int)$db->query('SELECT player_id FROM cities WHERE player_id<>? AND world_id=1 LIMIT 1',[$pid])->fetchColumn();
    if($publicTarget) {
        $public=KingdomService::state($pid,$publicTarget)['profile'];
        expectKingdom(!array_key_exists('gems',$public) && !array_key_exists('action_points',$public) && !array_key_exists('action_points_max',$public) && !array_key_exists('prestige_points',$public),'public profiles do not expose another players private currencies');
    }
    if(in_array('--balances-only',$argv,true)) { echo "KINGDOM BALANCE CHECKS PASSED\n";return; }
    $db->execute('INSERT INTO city_troops(city_id,troop_code,count) VALUES(?,50100101,10)',[$city]);
    $db->execute('INSERT INTO hospital_wounded(city_id,troop_code,count,healing_ends_at) VALUES(?,50100101,3,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))',[$city]);
    $db->execute("INSERT INTO alliances(world_id,name,tag,leader_id) VALUES(1,?,?,?)",[$name,strtoupper(substr($name,-6)),$pid]);$aid=$db->lastInsertId();
    $db->execute("INSERT INTO alliance_members(alliance_id,player_id,role) VALUES(?,?,'leader')",[$aid,$pid]);
    $db->execute("INSERT INTO expeditions(name,host_alliance_id,created_by,expires_at) VALUES(?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))",[$name,$aid,$pid]);$eid=$db->lastInsertId();
    $db->execute("INSERT INTO expedition_missions(expedition_id,player_id,city_id,alliance_id,objective,troops_json,potential_damage,arrival_at,return_at) VALUES(?,?,?,?,'defenses',?,180,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 HOUR))",[$eid,$pid,$city,$aid,json_encode([50100101=>4])]);
    $db->execute("INSERT INTO marches(player_id,world_id,march_type,origin_city_id,target_x,target_y,troops_json,departure_time,arrival_time,return_time,state,haul_json)
        VALUES(?,1,5,?,1,1,?,UTC_TIMESTAMP(),UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),'returning',?)",[$pid,$city,json_encode([50100101=>10]),json_encode(['survivors'=>[50100101=>2],'loot'=>[]])]);
    $s=KingdomService::state($pid);
    expectKingdom($s['profile']['stats']['troops']===19 && $s['profile']['stats']['garrison']===10,'power counts garrison, wounded, reserved troops and actual return survivors');
    $starterPower=(int)(\Conquer\Game\City\TroopData::get(50100101)['power']??0);
    expectKingdom($s['profile']['power']===$basePower+19*$starterPower,'reserved armies do not lose power or count dead soldiers twice');
    $db->execute("INSERT INTO building_queue(city_id,building_code,level_to,started_at,finishes_at,is_processed) VALUES(?,'farm',2,UTC_TIMESTAMP(),UTC_TIMESTAMP(),1)",[$city]);
    $db->execute('INSERT INTO troop_queue(city_id,troop_code,count,barrack_slot,started_at,finishes_at,is_processed) VALUES(?,50100101,100,1,UTC_TIMESTAMP(),UTC_TIMESTAMP(),1)',[$city]);
    $db->execute("INSERT INTO research_queue(player_id,world_id,research_code,level_to,started_at,finishes_at,is_processed) VALUES(?,1,'food_production',1,UTC_TIMESTAMP(),UTC_TIMESTAMP(),1)",[$pid]);
    $db->execute("INSERT INTO marches(player_id,world_id,march_type,origin_city_id,target_x,target_y,troops_json,departure_time,arrival_time,return_time,state,haul_json)
        VALUES(?,1,9,?,1,1,'{}',UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP(),'complete',?)",[$pid,$city,json_encode(['survivors'=>[],'loot'=>['food'=>50000]])]);
    $s=KingdomService::state($pid);$quests=array_column($s['quests'],null,'quest_code');
    foreach(['upgrade_building_1','train_troops_100','research_complete_1','collect_resources'] as $code) { expectKingdom($quests[$code]['completed']===true,"durable completion records fulfil $code"); }
    $again=array_column(KingdomService::state($pid)['quests'],null,'quest_code');
    expectKingdom($again['train_troops_100']['progress']===100 && $again['collect_resources']['progress']===50000,'quest reconciliation stays capped and idempotent');
    $claimed=KingdomService::action($pid,['action'=>'quest.claim','quest_code'=>'train_troops_100']);
    expectKingdom($claimed['state']['quests']!==[],'completed derived daily quest can be claimed normally');
    try { KingdomService::action($pid,['action'=>'quest.claim','quest_code'=>'train_troops_100']);throw new RuntimeException('double reward accepted'); }
    catch(DomainException) { expectKingdom(true,'derived quest reward remains one-time'); }
    $unsupported=60500002;
    \Conquer\Game\Treasure\TreasureService::addFragments($pid,$unsupported,1000);
    $items=array_column(KingdomService::state($pid)['treasures']['items'],null,'treasure_code');
    expectKingdom($items[$unsupported]['is_usable'] && !isset($items[$unsupported]['unsupported_stats']['hospital_capacity']) && isset($items[$unsupported]['stats_at_level']['hospital_capacity']),'hospital relic effect is enabled in the catalog');
    $equipped=KingdomService::action($pid,['action'=>'treasure.equip','treasure_code'=>$unsupported,'slot'=>1]);
    expectKingdom(isset($equipped['state']['treasures']['bonuses']['food_production']) && isset($equipped['state']['treasures']['bonuses']['hospital_capacity']),'mixed relic grants both production and hospital effects');
    echo "ALL KINGDOM REGRESSIONS PASSED\n";
} catch(Throwable $e) {
    $failed=true;fwrite(STDERR,'FAIL '.$e->getMessage()."\n");
} finally {
    // Explicit fixture IDs, dependent records first. No broad player-name matching or state reset.
    if($eid) { $db->execute('DELETE FROM expeditions WHERE id=?',[$eid]); }
    if($aid) { $db->execute('DELETE FROM alliance_members WHERE alliance_id=?',[$aid]);$db->execute('DELETE FROM alliances WHERE id=?',[$aid]); }
    if($pid) {
        foreach(['marches','player_inventory','player_daily_quests','player_treasures','research_queue','player_research','active_buffs','player_charms_active'] as $table) { $db->execute("DELETE FROM $table WHERE player_id=?",[$pid]); }
    }
    if($city) {
        foreach(['hospital_wounded','troop_queue','building_queue','city_troops'] as $table) { $db->execute("DELETE FROM $table WHERE city_id=?",[$city]); }
        $db->execute('DELETE FROM cities WHERE id=?',[$city]);
    }
    if($pid) { $db->execute('DELETE FROM players WHERE id=?',[$pid]); }
}
$fixture->close();
if($failed) { exit(1); }
