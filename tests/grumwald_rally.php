<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';
date_default_timezone_set('UTC');
\Conquer\Logger::init(sys_get_temp_dir().'/conquer-grumwald-test.log', 'ERROR');

use Conquer\Db\Connection;
use Conquer\Game\City\CityState;
use Conquer\Game\Map\MonsterData;
use Conquer\Game\March\{BattleEngine, BattlePreview};
use Conquer\Game\Rally\{MonsterRally, RallyService};
use Conquer\Game\Research\BuffEngine;
use Conquer\Game\World\WorldContext;

$checks = 0;
$fixture = null;
$exit = 0;
function checkGrumwald(bool $ok, string $label): void
{
    if (!$ok) throw new RuntimeException($label);
    $GLOBALS['checks']++;
    echo "PASS $label\n";
}
function grumwaldRally(int $id): array
{
    return Connection::getInstance()->query('SELECT * FROM rallies WHERE id=?', [$id])->fetch();
}
function grumwaldTarget(int $x): array
{
    $definition = MonsterData::definition(20202401);
    $hp = (int)($definition['amount'] * $definition['stats']['hp']);
    $db = Connection::getInstance();
    $db->execute("INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current,monster_type,expires_at) VALUES(1,20202401,?,60,?,'rally',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))", [$x,$hp]);
    return ['id'=>$db->lastInsertId(),'monster_code'=>20202401,'coord_x'=>$x,'coord_y'=>60,'hp_current'=>$hp];
}
function grumwaldArrive(int $id): array
{
    Connection::getInstance()->execute('UPDATE rallies SET arrival_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND),return_time=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR) WHERE id=?', [$id]);
    RallyService::tick();
    return json_decode(grumwaldRally($id)['result_json'], true, 512, JSON_THROW_ON_ERROR);
}
function grumwaldHome(int $id): void
{
    Connection::getInstance()->execute('UPDATE rallies SET return_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?', [$id]);
    RallyService::tick();
}

try {
    $fixture = new \ConquerTests\FeatureDatabase();
    $db = Connection::getInstance();
    WorldContext::bind(1);
    $db->execute("UPDATE worlds SET status='running',speed_factor=1 WHERE id=1");
    foreach ([1,2] as $pid) {
        $db->execute("INSERT INTO players(id,username,email,password_hash,action_points,last_ap_regen) VALUES(?,?,?,'unused',200,UTC_TIMESTAMP())", [$pid,'GrumwaldFixture'.$pid,'grumwald'.$pid.'@tests.invalid']);
        $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,food,lumber,stone,gold) VALUES(?,?,1,'Grumwald test',?,40,100000,100000,100000,100000)", [$pid,$pid,20+$pid*5]);
        foreach (CityState::BUILDING_CODES as $code) $db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(?,?,1)', [$pid,$code]);
        foreach ([50100101,50200101] as $code) $db->execute('INSERT INTO city_troops(city_id,troop_code,count) VALUES(?,?,20000)', [$pid,$code]);
    }
    $db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id) VALUES(1,1,'Regrowth testers','ROOT',1)");
    $db->execute("INSERT INTO alliance_members(alliance_id,player_id,world_id,role) VALUES(1,1,1,'leader'),(1,2,1,'member')");
    $target = grumwaldTarget(60);
    $definition = MonsterData::get(20202401);
    $body = ['kind'=>'monster-rally','expected_world_id'=>1,'target_id'=>$target['id'],'target_x'=>60,'target_y'=>60,'troops'=>[50100101=>1000]];
    $state = static fn() => [
        $db->query('SELECT troop_code,count FROM city_troops ORDER BY city_id,troop_code')->fetchAll(),
        $db->query('SELECT hp_current FROM field_monsters ORDER BY id')->fetchAll(),
        $db->query('SELECT action_points FROM players ORDER BY id')->fetchAll(),
        (int)$db->query('SELECT COUNT(*) FROM battle_reports')->fetchColumn(),
    ];
    BuffEngine::getBuffs(1,1);
    $before = $state();
    $preview = BattlePreview::calculate(1, $body);
    $pure = BattleEngine::previewMonsterArmies([['troops'=>$body['troops'],'buffs'=>BuffEngine::getBuffs(1,1)]], $target, $definition);
    checkGrumwald($preview['boss_mechanic'] === $pure['report']['boss_mechanic'] && $preview['monster_hp_after'] === $pure['new_monster_hp'], 'public advisory calculation includes the exact shared boss rule');
    $spoofed = BattlePreview::calculate(1, $body + ['boss_mechanic'=>['countered'=>true,'heal_percent'=>0]]);
    checkGrumwald($spoofed['boss_mechanic'] === $preview['boss_mechanic'], 'client-supplied mechanic fields cannot suppress regeneration');
    checkGrumwald($state() === $before, 'preview does not reserve troops, heal the live boss, spend AP or create reports');
    checkGrumwald(MonsterData::mapData($target)['definition']['boss_mechanic'] === $definition['boss_mechanic'], 'map target exposes the same rule before dispatch');

    $id = MonsterRally::start(1,1,60,60,$body['troops'],1,'');
    $saved = json_decode(grumwaldRally($id)['result_json'], true, 512, JSON_THROW_ON_ERROR);
    checkGrumwald($saved['monster']['boss_mechanic'] === $definition['boss_mechanic'], 'rally creation freezes the versioned mechanic in its definition');
    RallyService::launch($id,1);
    $result = grumwaldArrive($id);
    $mechanic = $result['report']['boss_mechanic'];
    checkGrumwald(!$result['monster_killed'] && $mechanic['hp_restored'] > 0, 'an actual underpowered rally triggers regeneration');
    checkGrumwald((int)$db->query('SELECT hp_current FROM field_monsters WHERE id=?', [$target['id']])->fetchColumn() === $mechanic['hp_after_regeneration'], 'database monster HP contains the final regenerated amount');
    $report = json_decode($db->query("SELECT data_json FROM battle_reports WHERE JSON_UNQUOTE(JSON_EXTRACT(data_json,'$.rally_id'))=?", [$id])->fetchColumn(), true);
    checkGrumwald($report['boss_mechanic'] === $mechanic && $report['monster_hp_after'] === $mechanic['hp_after_regeneration'], 'personal report keeps the exact historical mechanic outcome');
    $settled = $state();
    RallyService::tick();
    checkGrumwald($state() === $settled, 'a repeated tick cannot heal again or repeat a report');
    grumwaldHome($id);

    $target = grumwaldTarget(70);
    $id = MonsterRally::start(1,1,70,60,[50100101=>1000],1,'');
    RallyService::join(2,2,$id,[50200101=>1000]);
    $db->execute("UPDATE rally_participants SET arrival_time=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE rally_id=? AND status='joining'", [$id]);
    RallyService::launch($id,1);
    $result = grumwaldArrive($id);
    checkGrumwald(count($result['armies']) === 2 && $result['report']['boss_mechanic']['countered'] && $result['report']['boss_mechanic']['hp_restored'] === 0, 'arrived allied ranged troops suppress the leader-only regeneration');
    $reports = $db->query("SELECT data_json FROM battle_reports WHERE JSON_UNQUOTE(JSON_EXTRACT(data_json,'$.rally_id'))=?", [$id])->fetchAll(PDO::FETCH_COLUMN);
    checkGrumwald(count($reports) === 2 && array_reduce($reports, static fn(bool $ok, string $json): bool => $ok && json_decode($json,true)['boss_mechanic']['countered'], true), 'both participants see the combined-rally counter in their own report');
    grumwaldHome($id);

    $target = grumwaldTarget(80);
    $id = MonsterRally::start(1,1,80,60,[50100101=>1000],1,'');
    // Model a genuine pre-feature saved order: its definition has no mechanic.
    $saved = json_decode(grumwaldRally($id)['result_json'], true, 512, JSON_THROW_ON_ERROR);
    unset($saved['monster']['boss_mechanic']);
    $db->execute('UPDATE rallies SET result_json=? WHERE id=?', [json_encode($saved,JSON_THROW_ON_ERROR),$id]);
    RallyService::launch($id,1);
    $result = grumwaldArrive($id);
    checkGrumwald(!isset($result['report']['boss_mechanic']), 'an already saved legacy rally is not upgraded at launch or settlement');
    $damage = min($target['hp_current'], max(1,(int)round($target['hp_current'] * min(1, $result['report']['army_power_before_luck'] * \Conquer\Game\March\BattleLuck::factor($result['report']['luck_percent']) / $result['report']['required_power']))));
    checkGrumwald($result['new_monster_hp'] === $target['hp_current']-$damage, 'legacy rally keeps its original non-regenerating damage contract');
    grumwaldHome($id);
    echo "ALL $checks GRUMWALD RALLY CHECKS PASSED\n";
} catch (Throwable $e) {
    $exit = 1;
    fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString()."\n");
} finally {
    if ($fixture) $fixture->close();
}
exit($exit);
