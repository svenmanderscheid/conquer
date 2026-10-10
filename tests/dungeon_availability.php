<?php
declare(strict_types=1);

/** Dungeon shutdown behavior against disposable fixture data only. */
if (PHP_SAPI !== 'cli') exit(1);
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';
date_default_timezone_set('UTC');
\Conquer\Logger::init(sys_get_temp_dir().'/conquer-dungeon-availability-test.log', 'ERROR');

use Conquer\Db\Connection;
use Conquer\Game\Dungeon\{DungeonException,DungeonRules as R,DungeonService as D};
use Conquer\Game\Player\LordLevel;
use Conquer\Game\World\{WorldContext as W,WorldMapProfile};

$checks=0;
function ck(bool $ok, string $label): void {
    global $checks;
    if (!$ok) throw new RuntimeException($label);
    $checks++; echo "PASS $label\n";
}
function denied(callable $fn, string $code, string $label): void {
    try { $fn(); }
    catch (DungeonException $e) { ck($e->errorCode===$code, $label.' ('.$e->errorCode.')'); return; }
    throw new RuntimeException('Allowed invalid action: '.$label);
}
function act(int $pid, string $action, ?int $runId=null, array $extra=[]): array {
    W::bind(1, $pid);
    return D::action($pid, ['action'=>$action,'expected_world_id'=>1]+($runId===null?[]:['run_id'=>$runId])+$extra);
}
function stock(int $pid): int {
    return (int) Connection::getInstance()->query('SELECT count FROM city_troops WHERE city_id=? AND troop_code=50100101', [$pid])->fetchColumn();
}
function run(int $id): array {
    return Connection::getInstance()->query('SELECT * FROM dungeon_runs WHERE id=?', [$id])->fetch();
}
function party(): int {
    $id=(int)act(1, 'create', null, ['dungeon_code'=>'melusina_well','difficulty'=>'normal','stance'=>'cautious','role'=>'attack','troops'=>[50100101=>40000]])['run_id'];
    act(2, 'join', $id, ['role'=>'defense','troops'=>[50100101=>40000]]);
    return $id;
}

$fixture=null; $exit=0;
try {
    $fixture=new \ConquerTests\FeatureDatabase(); $db=Connection::getInstance();
    WorldMapProfile::configureEmptyWorld(1);
    foreach ([1=>'attack',2=>'defense',3=>'gather'] as $pid=>$role) {
        $db->execute('INSERT INTO players(id,username,email,password_hash) VALUES(?,?,?,?)', [$pid,'AvailabilityTester'.$pid,'availability'.$pid.'@tests.invalid','unused']);
        $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,castle_level,food,lumber,stone,gold) VALUES(?,?,1,'Fixture city',?,40,12,100000,100000,100000,100000)", [$pid,$pid,30+$pid*5]);
        foreach (\Conquer\Game\City\CityState::BUILDING_CODES as $code) {
            $db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(?,?,?)', [$pid,$code,$code==='castle'?12:5]);
        }
        $db->execute('INSERT INTO city_troops(city_id,troop_code,count) VALUES(?,50100101,40000)', [$pid]);
        LordLevel::addXp($pid, LordLevel::totalForLevel(20), 1, 'fixture-level');
        $db->execute('INSERT INTO player_lord_talents(player_id,world_id,talent_code,rank) VALUES(?,1,?,5)', [$pid,$role.'_0']);
    }

    W::bind(1,1); $state=D::state(1);
    ck($state['rotation']===[]&&$state['next_rotation']===[]&&array_column($state['permanent_dungeons'],'dungeon_code')===['melusina_well'], 'only Melusina is offered now and next week');
    $disabled=array_values(array_filter(R::catalog()['dungeons'], static fn(array $d): bool => !R::isEnabled($d['dungeon_code'])));
    ck(count($disabled)===6&&R::isEnabled('melusina_well'), 'all six other dungeons are disabled');
    foreach ($disabled as $definition) {
        $body=['dungeon_code'=>$definition['dungeon_code'],'difficulty'=>'normal','stance'=>'cautious','role'=>'attack','troops'=>[50100101=>40000]];
        denied(fn()=>act(1,'create',null,$body), 'DUNGEON_UNAVAILABLE', $definition['dungeon_code'].' creation is rejected');
        denied(fn()=>act(1,'preview',null,$body), 'DUNGEON_UNAVAILABLE', $definition['dungeon_code'].' new-party preview is rejected');
    }
    ck(stock(1)===40000&&(int)$db->query('SELECT COUNT(*) FROM dungeon_runs')->fetchColumn()===0&&(int)$db->query('SELECT COUNT(*) FROM dungeon_members')->fetchColumn()===0, 'blocked requests create no runs or troop reservations');

    $id=party();
    ck(run($id)['status']==='recruiting'&&stock(1)===0&&stock(2)===0, 'Melusina still accepts new parties and reserves their armies');
    D::tick();
    ck(run($id)['status']==='recruiting', 'enabled Melusina recruitment survives a background tick');

    // Model a party created before shutdown, retaining its former enabled flag.
    $saved=$disabled[0]; $saved['enabled']=true;
    $db->execute('UPDATE dungeon_runs SET dungeon_code=?,definition_json=? WHERE id=?', [$saved['dungeon_code'],json_encode($saved,JSON_THROW_ON_ERROR),$id]);
    $preview=['dungeon_code'=>$saved['dungeon_code'],'difficulty'=>'normal','stance'=>'cautious','role'=>'attack','troops'=>[50100101=>40000]];
    denied(fn()=>act(1,'preview',$id,$preview), 'DUNGEON_UNAVAILABLE', 'saved enabled flag cannot authorize a recruiting preview');
    denied(fn()=>act(3,'join',$id,['role'=>'gather','troops'=>[50100101=>40000]]), 'DUNGEON_UNAVAILABLE', 'disabled historical party cannot be joined');
    denied(fn()=>act(1,'start',$id), 'DUNGEON_UNAVAILABLE', 'disabled historical party cannot start');
    D::tick(); D::tick();
    ck(run($id)['status']==='cancelled'&&stock(1)===40000&&stock(2)===40000&&stock(3)===40000, 'shutdown cancels recruitment and returns each reserved army exactly once');
    ck((int)$db->query('SELECT COUNT(*) FROM dungeon_members WHERE run_id=? AND returned_at IS NOT NULL',[$id])->fetchColumn()===2&&(int)$db->query('SELECT COUNT(*) FROM dungeon_rewards WHERE run_id=?',[$id])->fetchColumn()===0, 'cancelled recruitment records both returns and creates no rewards');

    // Existing departures keep their persisted combat definition and rewards.
    $active=party();
    $members=array_map(static fn(array $m): array => json_decode($m['stats_json'],true,64,JSON_THROW_ON_ERROR), $db->query('SELECT stats_json FROM dungeon_members WHERE run_id=? ORDER BY player_id',[$active])->fetchAll());
    $simulation=R::simulate($saved,$members,'normal','cautious',42,null);
    ck($simulation['decision_required'], 'historical active fixture reaches its real combat decision');
    $db->execute("UPDATE dungeon_runs SET dungeon_code=?,definition_json=?,seed=42,status='running',started_at=UTC_TIMESTAMP(),decision_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),decision_deadline=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 HOUR),simulation_json=? WHERE id=?", [$saved['dungeon_code'],json_encode($saved,JSON_THROW_ON_ERROR),json_encode($simulation,JSON_THROW_ON_ERROR),$active]);
    D::tick();
    ck(run($active)['status']==='running'&&stock(1)===0&&stock(2)===0, 'shutdown preserves a run that already departed');
    $db->execute('UPDATE dungeon_runs SET decision_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$active]);
    D::tick();
    ck(run($active)['status']==='decision', 'disabled historical run still opens its scheduled decision');
    act(1,'vote',$active,['choice'=>'skip']); act(2,'vote',$active,['choice'=>'skip']);
    $db->execute('UPDATE dungeon_runs SET decision_deadline=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$active]);
    D::tick();
    ck(run($active)['status']==='running'&&run($active)['choice']==='skip', 'existing members can resolve the historical decision');
    $db->execute('UPDATE dungeon_runs SET finishes_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$active]);
    D::tick();
    ck(run($active)['status']==='completed'&&stock(1)===40000&&stock(2)===40000, 'historical run completes through real combat and restores both armies');
    $before=(int)$db->query('SELECT COALESCE(SUM(fragments),0) FROM player_treasures WHERE player_id=1')->fetchColumn();
    act(1,'claim',$active);
    $after=(int)$db->query('SELECT COALESCE(SUM(fragments),0) FROM player_treasures WHERE player_id=1')->fetchColumn();
    ck($after>$before, 'rewards from a disabled historical dungeon remain claimable');
    denied(fn()=>act(1,'claim',$active), 'ALREADY_CLAIMED', 'historical reward cannot be claimed twice');
    D::tick(); D::tick();
    ck(stock(1)===40000&&stock(2)===40000&&(int)$db->query('SELECT COALESCE(SUM(fragments),0) FROM player_treasures WHERE player_id=1')->fetchColumn()===$after, 'repeated settlement does not duplicate historical armies or rewards');
    echo "OK: $checks dungeon availability checks.\n";
} catch (Throwable $e) { fwrite(STDERR,(string)$e."\n"); $exit=1; }
finally { $fixture?->close(); }
exit($exit);
