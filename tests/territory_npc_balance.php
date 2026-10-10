<?php
declare(strict_types=1);
/** Real territory campaigns use only an isolated schema and synthetic armies. */
if (PHP_SAPI !== 'cli') exit(1);
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';
date_default_timezone_set('UTC');
\Conquer\Logger::init(sys_get_temp_dir().'/conquer-territory-npc-test.log', 'ERROR');

use Conquer\Db\Connection;
use Conquer\Game\City\CityState;
use Conquer\Game\March\PvpRules;
use Conquer\Game\Rally\RallyCapacity;
use Conquer\Game\Research\{BuffEngine, ResearchEffects};
use Conquer\Game\Territory\{TerritoryRules, TerritoryService};
use Conquer\Game\World\{WorldContext, WorldMapProfile};

function npcCheck(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException($message);
    echo "PASS $message\n";
}

function npcCommand(int $player, string $action, array $body): array
{
    return TerritoryService::action($player, [
        'action'=>$action, 'world_id'=>2, 'expected_world_id'=>2,
        'request_id'=>bin2hex(random_bytes(16)),
    ]+$body, 2);
}

/** Advance through the genuine scheduled launch, battle and return exactly once. */
function npcFinish(int $rally): array
{
    $db = Connection::getInstance();
    foreach (['launch_at', 'arrival_time', 'return_time'] as $field) {
        $when = $db->query('SELECT '.$field.' FROM rallies WHERE id=?', [$rally])->fetchColumn();
        if (!$when) throw new RuntimeException('Campaign did not reach '.$field);
        TerritoryService::tick(2, TerritoryService::timestamp($when), 100);
    }
    npcCheck($db->query('SELECT status FROM rallies WHERE id=?', [$rally])->fetchColumn()==='complete', 'campaign returns every surviving army');
    return json_decode($db->query('SELECT result_json FROM rallies WHERE id=?', [$rally])->fetchColumn(), true, 512, JSON_THROW_ON_ERROR);
}

/** One lawful 50,000-troop march per participant; the leader has Hall level 21. */
function npcCampaign(string $target, int $members): array
{
    $army = [50100301=>16667, 50200301=>16667, 50300301=>16666];
    $receipt = npcCommand(1, 'start', ['target_id'=>$target, 'city_id'=>1, 'troops'=>$army, 'rally_minutes'=>30]);
    for ($player=2; $player<=$members; $player++) {
        npcCommand($player, 'join', ['rally_id'=>$receipt['rally_id'], 'city_id'=>$player, 'troops'=>$army]);
    }
    return npcFinish($receipt['rally_id']);
}

$fixture = new \ConquerTests\FeatureDatabase();
try {
    $db = Connection::getInstance();
    $db->execute("INSERT INTO worlds(id,name,slug,status,map_size) VALUES(2,'NPC balance fixture','npc-balance-fixture','running',768)");
    WorldMapProfile::configureEmptyWorld(2);
    WorldContext::bind(2);
    TerritoryService::ensureWorld(2);
    $rules = TerritoryService::saveProfile(2, ['pvp_window_start_hour_utc'=>0, 'pvp_window_hours'=>24], 1);
    $targets = $db->query("SELECT * FROM territory_targets WHERE world_id=2 AND kind='commune' ORDER BY id LIMIT 3")->fetchAll();
    $target = $targets[0];

    for ($player=1; $player<=22; $player++) {
        $level = $player===22 ? 10 : 30;
        $db->execute("INSERT INTO players(id,username,email,password_hash) VALUES(?,?,?,'unused')", [$player, 'NpcBalance'.$player, 'npc-balance'.$player.'@invalid.test']);
        $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y) VALUES(?,?,2,'NPC fixture city',?,?)", [$player, $player, (int)$target['x']+25+$player, (int)$target['y']]);
        foreach (CityState::BUILDING_CODES as $building) {
            $buildingLevel = $building==='hall_of_alliance' ? ($player===22 ? 10 : 21) : $level;
            $db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(?,?,?)', [$player, $building, $buildingLevel]);
        }
        foreach ([50100101, 50100201, 50100301, 50200301, 50300301] as $code) {
            $db->execute('INSERT INTO city_troops(city_id,troop_code,count) VALUES(?,?,100000)', [$player, $code]);
        }
    }
    $db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id) VALUES(1,2,'NPC balance alliance','NPC',1),(2,2,'NPC level ten alliance','TEN',22)");
    for ($player=1; $player<=22; $player++) {
        $db->execute('INSERT INTO alliance_members(world_id,alliance_id,player_id,role) VALUES(2,?,?,?)', [$player===22 ? 2 : 1, $player, in_array($player,[1,22],true) ? 'leader' : 'member']);
    }
    npcCheck(RallyCapacity::forCity(1,1)['total']>=1050000, 'a level 21 Hall can assemble the reference rally');
    npcCheck(ResearchEffects::limits(BuffEngine::getBuffs(1,2))['march_capacity']>=50000, 'each reference contribution fits the real personal march limit');

    $soloCapacity = ResearchEffects::limits(BuffEngine::getBuffs(22,2))['march_capacity'];
    $solo = npcCommand(22, 'start', ['target_id'=>$target['id'], 'city_id'=>22, 'troops'=>[50100201=>$soloCapacity], 'rally_minutes'=>1]);
    $lost = npcFinish($solo['rally_id']);
    npcCheck($lost['outcome']==='defender_wins' && TerritoryService::target(2,$target['id'])['owner_alliance_id']===null, 'a full level 10 solo T2 march cannot capture a neutral Commune');

    $small = npcCampaign($target['id'], 18);
    npcCheck($small['outcome']==='defender_wins' && count($small['armies'])===18, '900,000 unbuffed mixed T3 troops do not beat the Commune guards');
    $large = npcCampaign($target['id'], 21);
    npcCheck($large['outcome']==='attacker_wins' && count($large['armies'])===21 && (int)TerritoryService::target(2,$target['id'])['owner_alliance_id']===1, '1,050,000 mixed T3 troops capture the Commune through a real multiplayer rally');
    npcCheck($small['defender_score']===$large['defender_score'], 'failed territory attacks do not permanently chip away neutral NPC defenses');
    npcCheck($large['npc_troops']===TerritoryService::detail(1,2,$targets[1]['id'])['npc_troops'], 'displayed NPC count agrees with the guards actually used in combat');

    $referenceScore = (float)$large['attacker_score'];
    foreach (['canton'=>'Shrine', 'crown'=>'Royal Castle'] as $kind=>$name) {
        $defense = PvpRules::strength(TerritoryRules::NPC_TROOP_CODE, $rules['npc_troops'][$kind], [])*1.1;
        npcCheck($defense>$referenceScore, 'a Commune-sized reference rally remains too weak for the '.$name);
    }

    // Owned objectives are defended by player garrisons. NPC rebalancing must not
    // invent a replacement militia when a real owner has left its target empty.
    $db->execute('UPDATE territory_targets SET owner_alliance_id=1,owned_since=UTC_TIMESTAMP(),last_income_at=UTC_TIMESTAMP() WHERE world_id=2 AND id=?', [$targets[1]['id']]);
    $pvp = npcCommand(22, 'start', ['target_id'=>$targets[1]['id'], 'city_id'=>22, 'troops'=>[50100101=>1000], 'rally_minutes'=>1]);
    $pvpResult = npcFinish($pvp['rally_id']);
    npcCheck($pvpResult['outcome']==='attacker_wins' && $pvpResult['npc_troops']===0, 'an unguarded player-owned Commune retains the existing PvP rules');

    // Model an order already admitted under the previous release: its immutable
    // rules remain old, while the world profile is upgraded for future orders.
    $legacy = $rules;
    unset($legacy['npc_balance_revision']);
    $legacy['npc_troops'] = ['commune'=>120, 'canton'=>800, 'crown'=>1600];
    $old = npcCommand(22, 'start', ['target_id'=>$targets[2]['id'], 'city_id'=>22, 'troops'=>[50100101=>1000], 'rally_minutes'=>30]);
    $legacyJson = json_encode($legacy, JSON_THROW_ON_ERROR);
    $db->execute('UPDATE territory_campaigns SET rules_json=? WHERE id=?', [$legacyJson, $old['campaign_id']]);
    $db->execute('UPDATE territory_profiles SET rules_json=? WHERE world_id=2', [$legacyJson]);
    $upgraded = TerritoryService::profile(2);
    npcCheck($upgraded['npc_troops']===$rules['npc_troops'], 'existing worlds receive the stronger legacy default garrisons');
    npcCheck($db->query('SELECT rules_json FROM territory_profiles WHERE world_id=2')->fetchColumn()===$legacyJson, 'reading a legacy profile does not rewrite stored player-world state');
    $saved = TerritoryService::saveProfile(2, ['support_cost'=>1001], $rules['version']);
    npcCheck($saved['npc_troops']===$rules['npc_troops'] && $saved['npc_balance_revision']===2, 'an unrelated admin update retains the normalized balance and records its revision');
    npcCheck($db->query('SELECT rules_json FROM territory_campaigns WHERE id=?', [$old['campaign_id']])->fetchColumn()===$legacyJson, 'updating the world profile leaves an existing campaign snapshot untouched');
    $oldResult = npcFinish($old['rally_id']);
    npcCheck($oldResult['outcome']==='attacker_wins' && $oldResult['npc_troops']===120, 'a previously admitted campaign settles against its original defenders');

    $custom = $legacy;
    $custom['npc_troops']['commune'] = 765432;
    $normalized = TerritoryRules::currentProfile($custom);
    npcCheck($normalized['npc_troops']['commune']===765432 && $normalized['npc_troops']['canton']===$rules['npc_troops']['canton'], 'legacy normalization preserves custom tuning while upgrading untouched default tiers');
    $custom['npc_balance_revision'] = 2;
    $custom['npc_troops']['commune'] = 120;
    npcCheck(TerritoryRules::currentProfile($custom)['npc_troops']['commune']===120, 'an explicitly revised custom profile can still choose a small test garrison');

    echo "ALL TERRITORY NPC BALANCE CHECKS PASSED\n";
} finally {
    $fixture->close();
}
