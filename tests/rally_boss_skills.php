<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';
date_default_timezone_set('UTC');
\Conquer\Logger::init(sys_get_temp_dir().'/uok-rally-boss-skills.log', 'ERROR');

use Conquer\Db\Connection;
use Conquer\Game\City\CityState;
use Conquer\Game\Map\MonsterData;
use Conquer\Game\March\{BattleEngine, BattlePreview, BattleLuck};
use Conquer\Game\Rally\{MonsterRally, RallyService};
use Conquer\Game\Research\BuffEngine;
use Conquer\Game\World\WorldContext;

$checks = 0;
$fixture = null;
$exit = 0;
function skillCheck(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
    $GLOBALS['checks']++;
    echo "PASS $label\n";
}
function skillRally(int $id): array {
    return Connection::getInstance()->query('SELECT * FROM rallies WHERE id=?', [$id])->fetch();
}
function skillTarget(int $code, int $x): array {
    $definition = MonsterData::get($code);
    $hp = (int)($definition['amount'] * $definition['stats']['hp']);
    $db = Connection::getInstance();
    $db->execute("INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current,monster_type,expires_at) VALUES(1,?,?,60,?,'rally',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))", [$code, $x, $hp]);
    return ['id' => $db->lastInsertId(), 'monster_code' => $code, 'coord_x' => $x, 'coord_y' => 60, 'hp_current' => $hp];
}
function skillStart(array $target, array $troops): int {
    Connection::getInstance()->execute('UPDATE players SET action_points=200,last_ap_regen=UTC_TIMESTAMP() WHERE id IN(1,2)');
    return MonsterRally::start(1, 1, $target['coord_x'], $target['coord_y'], $troops, 1, '');
}
function skillArrive(int $id): array {
    RallyService::launch($id, 1);
    Connection::getInstance()->execute('UPDATE rallies SET arrival_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND),return_time=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR) WHERE id=?', [$id]);
    RallyService::tick();
    return json_decode(skillRally($id)['result_json'], true, 512, JSON_THROW_ON_ERROR);
}
function skillHome(int $id): void {
    Connection::getInstance()->execute('UPDATE rallies SET return_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?', [$id]);
    RallyService::tick();
}

try {
    $fixture = new \ConquerTests\FeatureDatabase();
    $db = Connection::getInstance();
    WorldContext::bind(1);
    $db->execute("UPDATE worlds SET status='running',speed_factor=1 WHERE id=1");
    foreach ([1, 2] as $pid) {
        $db->execute("INSERT INTO players(id,username,email,password_hash,action_points,last_ap_regen) VALUES(?,?,?,'unused',200,UTC_TIMESTAMP())", [$pid, 'BossSkillFixture'.$pid, 'boss-skill'.$pid.'@tests.invalid']);
        $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,food,lumber,stone,gold) VALUES(?,?,1,'Boss skill test',?,40,100000,100000,100000,100000)", [$pid, $pid, 20+$pid*5]);
        foreach (CityState::BUILDING_CODES as $building) $db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(?,?,1)', [$pid, $building]);
        foreach ([50100101, 50200101, 50300101] as $troop) $db->execute('INSERT INTO city_troops(city_id,troop_code,count) VALUES(?,?,50000)', [$pid, $troop]);
    }
    $db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id) VALUES(1,1,'Boss skill testers','SKL',1)");
    $db->execute("INSERT INTO alliance_members(alliance_id,player_id,world_id,role) VALUES(1,1,1,'leader'),(1,2,1,'member')");
    $state = static fn() => [
        $db->query('SELECT city_id,troop_code,count FROM city_troops ORDER BY city_id,troop_code')->fetchAll(),
        $db->query('SELECT id,hp_current FROM field_monsters ORDER BY id')->fetchAll(),
        $db->query('SELECT id,action_points FROM players ORDER BY id')->fetchAll(),
        (int)$db->query('SELECT COUNT(*) FROM battle_reports')->fetchColumn(),
    ];
    $x = 60;
    foreach ([20202101 => 50100101, 20202201 => 50300101, 20202301 => 50200101, 20200501 => null] as $code => $counterTroop) {
        $definition = MonsterData::get($code);
        $troops = [$counterTroop === 50100101 ? 50200101 : 50100101 => 1000];
        $target = skillTarget($code, $x);
        $body = ['kind' => 'monster-rally', 'expected_world_id' => 1, 'target_id' => $target['id'], 'target_x' => $x, 'target_y' => 60, 'troops' => $troops];
        $buffs = BuffEngine::getBuffs(1, 1);
        $before = $state();
        $preview = BattlePreview::calculate(1, $body);
        $pure = BattleEngine::previewMonsterArmies([['troops' => $troops, 'buffs' => $buffs]], $target, $definition);
        skillCheck($preview['boss_mechanic'] === $pure['report']['boss_mechanic'] && $preview['monster_hp_after'] === $pure['new_monster_hp'], "$code advisory and combat share the exact rule");
        $spoofed = BattlePreview::calculate(1, $body + ['boss_mechanic' => ['countered' => true, 'active' => false], 'required_power' => 1, 'army_power' => 999999999]);
        skillCheck($spoofed['boss_mechanic'] === $preview['boss_mechanic'] && $spoofed['outcome'] === $preview['outcome'], "$code client fields cannot bypass the skill");
        skillCheck($state() === $before, "$code preview does not alter troops, HP, AP or reports");
        skillCheck(MonsterData::mapData($target)['definition']['boss_mechanic'] === $definition['boss_mechanic'], "$code map exposes the authoritative rule before dispatch");
        $id = skillStart($target, $troops);
        $saved = json_decode(skillRally($id)['result_json'], true, 512, JSON_THROW_ON_ERROR);
        skillCheck($saved['monster']['boss_mechanic'] === $definition['boss_mechanic'], "$code dispatch freezes its rule");
        $result = skillArrive($id);
        $effect = $result['report']['boss_mechanic'];
        skillCheck(!$result['monster_killed'] && $effect['active'] && !$effect['countered'], "$code uncountered actual rally applies its skill");
        if (isset($effect['required_power_after'])) skillCheck($result['report']['required_power'] === $effect['required_power_after'] && $effect['required_power_after'] > $effect['required_power_before'], "$code report records the raised victory threshold");
        if (isset($effect['army_power_after'])) skillCheck(abs($result['report']['army_power_before_luck'] - round($effect['army_power_after'])) < .01 && $effect['army_power_after'] < $effect['army_power_before'], "$code report records reduced power before server luck");
        if (isset($effect['injury_ratio_after'])) skillCheck($effect['injury_ratio_after'] > $effect['injury_ratio_before'] && $effect['injury_ratio_after'] <= .35, "$code backlash increases wounds within the cap");
        skillCheck((int)$db->query('SELECT hp_current FROM field_monsters WHERE id=?', [$target['id']])->fetchColumn() === $result['new_monster_hp'], "$code stores the resolved monster HP");
        $personal = json_decode($db->query("SELECT data_json FROM battle_reports WHERE JSON_UNQUOTE(JSON_EXTRACT(data_json,'$.rally_id'))=?", [$id])->fetchColumn(), true, 512, JSON_THROW_ON_ERROR);
        skillCheck($personal['boss_mechanic'] === $effect && $personal['monster_hp_after'] === $result['new_monster_hp'], "$code report preserves the exact skill outcome");
        $settled = $state(); RallyService::tick();
        skillCheck($state() === $settled, "$code repeated settlement does not repeat skill, wounds or report");
        skillHome($id);

        $x += 4; $target = skillTarget($code, $x);
        $leader = [$counterTroop === 50100101 ? 50200101 : 50100101 => 500];
        $member = $counterTroop ? [$counterTroop => 1000] : [50200101 => 500, 50300101 => 500];
        $id = skillStart($target, $leader);
        RallyService::join(2, 2, $id, $member);
        $db->execute("UPDATE rally_participants SET arrival_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE rally_id=? AND status='joining'", [$id]);
        $result = skillArrive($id);
        skillCheck(count($result['armies']) === 2 && $result['report']['boss_mechanic']['countered'] && !$result['report']['boss_mechanic']['active'], "$code arrived allied formation supplies the counter");
        $reports = $db->query("SELECT data_json FROM battle_reports WHERE JSON_UNQUOTE(JSON_EXTRACT(data_json,'$.rally_id'))=?", [$id])->fetchAll(PDO::FETCH_COLUMN);
        skillCheck(count($reports) === 2 && array_reduce($reports, static fn(bool $ok, string $json): bool => $ok && json_decode($json, true)['boss_mechanic']['countered'], true), "$code both participants retain the combined formation result");
        skillHome($id);

        $x += 4; $target = skillTarget($code, $x);
        $id = skillStart($target, $troops);
        $saved = json_decode(skillRally($id)['result_json'], true, 512, JSON_THROW_ON_ERROR);
        unset($saved['monster']['boss_mechanic']);
        $db->execute('UPDATE rallies SET result_json=? WHERE id=?', [json_encode($saved, JSON_THROW_ON_ERROR), $id]);
        $result = skillArrive($id);
        skillCheck(!isset($result['report']['boss_mechanic']), "$code historical rally without a skill keeps its rules");
        $damage = min($target['hp_current'], max(1, (int)round($target['hp_current'] * min(1, $result['report']['army_power_before_luck'] * BattleLuck::factor($result['report']['luck_percent']) / $result['report']['required_power']))));
        skillCheck($result['new_monster_hp'] === $target['hp_current'] - $damage, "$code legacy damage uses the original power ratio");
        skillHome($id); $x += 4;
    }
    echo "ALL $checks RALLY BOSS SKILL CHECKS PASSED\n";
} catch (Throwable $error) {
    $exit = 1;
    fwrite(STDERR, $error->getMessage()."\n".$error->getTraceAsString()."\n");
} finally {
    if ($fixture) $fixture->close();
}
exit($exit);
