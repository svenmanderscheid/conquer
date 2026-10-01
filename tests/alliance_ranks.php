<?php
declare(strict_types=1);
/** R1–R5 service, persistence and upgrade contracts; disposable local database only. */
if (PHP_SAPI !== 'cli') { exit(1); }
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
date_default_timezone_set('UTC');

use Conquer\Db\Connection;
use Conquer\Db\MigrationSql;
use Conquer\Game\Alliance\AllianceRank;
use Conquer\Game\Community\AllianceCommunityService as Recruitment;
use Conquer\Game\Community\CommunityService as Community;
use Conquer\Game\Kingdom\KingdomService as Kingdom;
use Conquer\Game\World\WorldContext as World;

// Only a parent-created FeatureDatabase directory is accepted by transfer workers.
if (($argv[1] ?? '') === '--transfer-worker') {
    $directory = realpath($argv[2] ?? '');
    if (!$directory || dirname($directory) !== realpath(sys_get_temp_dir())
        || !preg_match('/^conquer_feature_test_[a-f0-9]{12}$/D', basename($directory))) { exit(2); }
    Connection::init($directory);
    World::bind(1);
    try {
        Kingdom::action(1, ['action'=>'alliance.transfer', 'player_id'=>(int)$argv[3], 'expected_world_id'=>1]);
        echo json_encode(['ok'=>true], JSON_THROW_ON_ERROR);
    } catch (DomainException $error) {
        echo json_encode(['ok'=>false, 'denied'=>true, 'message'=>$error->getMessage()], JSON_THROW_ON_ERROR);
    }
    exit;
}

require __DIR__.'/Support/FeatureDatabase.php';
$fixture = new \ConquerTests\FeatureDatabase();
$db = Connection::getInstance();
$serial = 0;
$assertions = 0;
const RANK_ROLES = [1=>'member', 2=>'veteran', 3=>'officer', 4=>'vice_leader', 5=>'leader'];

function rankCheck(bool $condition, string $label): void
{
    global $assertions;
    if (!$condition) { throw new RuntimeException($label); }
    ++$assertions;
    echo "PASS $label\n";
}

function rankDenied(callable $operation, string $label): void
{
    try { $operation(); }
    catch (DomainException) { rankCheck(true, $label); return; }
    throw new RuntimeException('Unexpected acceptance: '.$label);
}

function rankAction(int $player, int $target, mixed $role, ?string $receipt = null, array $extra = []): array
{
    global $serial;
    return Community::action($player, array_replace([
        'action'=>'alliance.role', 'player_id'=>$target, 'role'=>$role,
        'request_id'=>$receipt ?? 'rank_command_'.str_pad((string)++$serial, 10, '0', STR_PAD_LEFT),
        'world_id'=>World::id(), 'expected_world_id'=>World::id(),
    ], $extra), World::id());
}

function recruitmentAction(int $player, string $action, array $body = [], ?string $receipt = null): array
{
    global $serial;
    return Recruitment::action($player, [
        'action'=>$action, 'request_id'=>$receipt ?? 'rank_recruit_'.str_pad((string)++$serial, 10, '0', STR_PAD_LEFT),
        'world_id'=>World::id(), 'expected_world_id'=>World::id(),
    ] + $body)['result'];
}

function rankMembership(int $player, int $world = 1): ?array
{
    $row = Connection::getInstance()->query('SELECT alliance_id,world_id,role,role_level FROM alliance_members WHERE player_id=? AND world_id=?', [$player,$world])->fetch();
    return $row ?: null;
}

function rankSnapshot(): array
{
    $db = Connection::getInstance();
    return [
        $db->query('SELECT id,world_id,leader_id,member_count FROM alliances ORDER BY id')->fetchAll(),
        $db->query('SELECT alliance_id,player_id,world_id,role,role_level FROM alliance_members ORDER BY alliance_id,player_id')->fetchAll(),
    ];
}

function rankUniqueLeader(int $alliance, string $label): void
{
    $db = Connection::getInstance();
    $leader = (int)$db->query('SELECT leader_id FROM alliances WHERE id=?', [$alliance])->fetchColumn();
    $rows = $db->query("SELECT player_id,role_level FROM alliance_members WHERE alliance_id=? AND role='leader'", [$alliance])->fetchAll();
    rankCheck(count($rows) === 1 && (int)$rows[0]['player_id'] === $leader && (int)$rows[0]['role_level'] === 5, $label);
}

/** Each matrix case starts valid, including exactly one canonical R5. */
function rankPair(int $actorRank, int $targetRank): int
{
    $db = Connection::getInstance();
    $leader = $actorRank === 5 ? 1 : ($targetRank === 5 ? 2 : 3);
    $db->execute("UPDATE alliance_members SET role='member',role_level=1 WHERE alliance_id=1");
    $db->execute('UPDATE alliance_members SET role=?,role_level=? WHERE alliance_id=1 AND player_id=1', [RANK_ROLES[$actorRank],$actorRank]);
    // Two different R5 members cannot exist in a valid alliance. Its R5/R5 case is self.
    $target = $actorRank === 5 && $targetRank === 5 ? 1 : 2;
    $db->execute('UPDATE alliance_members SET role=?,role_level=? WHERE alliance_id=1 AND player_id=?', [RANK_ROLES[$targetRank],$targetRank,$target]);
    $db->execute("UPDATE alliance_members SET role='leader',role_level=5 WHERE alliance_id=1 AND player_id=?", [$leader]);
    $db->execute('UPDATE alliances SET leader_id=?,member_count=4 WHERE id=1', [$leader]);
    return $target;
}

function rankPlayer(int $id, array $worlds = [1]): void
{
    $db = Connection::getInstance();
    $db->execute('INSERT INTO players(id,username,email,password_hash) VALUES(?,?,?,?)', [$id,'RankPlayer'.$id,'rank'.$id.'@test.invalid','unused']);
    foreach ($worlds as $world) {
        $db->execute("INSERT INTO cities(player_id,world_id,name,coord_x,coord_y) VALUES(?,?,'Rank fixture',?,?)", [$id,$world,20+$id*3,20+$world*8]);
        $city = $db->lastInsertId();
        foreach (\Conquer\Game\City\CityState::BUILDING_CODES as $building) {
            $db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(?,?,1)', [$city,$building]);
        }
    }
}

try {
    $migration = (string)file_get_contents(ROOT_DIR.'/migrations/0124_alliance_member_ranks.sql');
    MigrationSql::apply($db->getPdo(), $migration);
    $db->execute("UPDATE worlds SET status='running' WHERE id=1");
    $db->execute("INSERT INTO worlds(id,name,slug,status) VALUES(2,'Rank other world','rank-other-world','running')");
    foreach ([1,2,3,4,10,11,12,20,21,22,23,24,81,82,83,84,85,91,92,93,94] as $id) {
        rankPlayer($id, in_array($id,[1,2,12,94],true) ? [1,2] : [1]);
    }
    $db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id,member_count) VALUES
        (1,1,'Rank matrix','RNK',3,4),(2,1,'Other alliance','OTH',10,2),(3,2,'Other world','TWO',12,3)");
    foreach ([[1,1,1,'member',1],[1,2,1,'member',1],[1,3,1,'leader',5],[1,4,1,'member',1],
              [2,10,1,'leader',5],[2,11,1,'member',1],[3,12,2,'leader',5],[3,1,2,'member',1],[3,2,2,'veteran',2]] as $row) {
        $db->execute('INSERT INTO alliance_members(alliance_id,player_id,world_id,role,role_level) VALUES(?,?,?,?,?)', $row);
    }
    World::bind(1);

    foreach (RANK_ROLES as $level=>$role) { rankCheck(AllianceRank::level($role) === $level, "$role maps to R$level"); }
    rankCheck(AllianceRank::level('unknown') === 0 && AllianceRank::level('') === 0, 'unknown roles have no rank or authority');
    rankCheck(AllianceRank::assignableRoles('leader','unknown') === [] && AllianceRank::assignableRoles('unknown','member') === [], 'unknown role pair has no assignable ranks');

    // Expected policy is deliberately independent of the production helper.
    for ($actor=1; $actor<=5; ++$actor) {
        for ($targetRank=1; $targetRank<=5; ++$targetRank) {
            $target = rankPair($actor,$targetRank);
            $allowed = $actor >= 4 && $targetRank < $actor ? array_values(array_slice(RANK_ROLES,0,$actor-1)) : [];
            rankCheck(AllianceRank::assignableRoles(RANK_ROLES[$actor],RANK_ROLES[$targetRank],$target===1) === $allowed, "R$actor / R$targetRank helper choices");
            $state = Community::state(1,1);
            $members = array_column($state['members'],null,'player_id');
            rankCheck($state['role_level'] === $actor && $members[$target]['role_level'] === $targetRank && $members[$target]['assignable_roles'] === $allowed, "R$actor / R$targetRank server choices match authority");
            foreach (RANK_ROLES as $newRank=>$newRole) {
                $target = rankPair($actor,$targetRank);
                $before = rankSnapshot();
                if (in_array($newRole,$allowed,true)) {
                    rankAction(1,$target,$newRole);
                    $stored = rankMembership($target);
                    rankCheck($stored['role'] === $newRole && (int)$stored['role_level'] === $newRank, "R$actor changes R$targetRank to R$newRank and persists both fields");
                } else {
                    rankDenied(fn()=>rankAction(1,$target,$newRole), "R$actor cannot change R$targetRank to R$newRank");
                    rankCheck(rankSnapshot() === $before, 'denied role change preserves every membership and leader');
                }
                rankUniqueLeader(1, 'rank change retains exactly one canonical R5');
            }
            rankPair($actor,$targetRank);
            rankDenied(fn()=>rankAction(1,1,'member'), "R$actor cannot change own role");
        }
    }

    rankPair(1,1);
    $db->execute('UPDATE alliance_members SET role_level=5 WHERE alliance_id=1 AND player_id=1');
    rankCheck(Community::state(1,1)['role_level'] === 1 && Recruitment::state(1,1)['role_level'] === 1, 'stale numeric R5 cannot override authoritative member role in read models');
    rankDenied(fn()=>rankAction(1,2,'officer'), 'stale numeric R5 cannot grant promotion authority');
    rankPair(5,1);
    foreach (['unknown','R5',5,[],null] as $invalidRole) {
        $before = rankSnapshot();
        rankDenied(fn()=>rankAction(1,2,$invalidRole), 'invalid or numeric role cannot bypass enum input');
        rankCheck(rankSnapshot() === $before, 'invalid role has no membership side effect');
    }
    rankAction(1,2,'veteran',null,['role_level'=>5]);
    rankCheck(rankMembership(2)['role'] === 'veteran' && (int)rankMembership(2)['role_level'] === 2, 'client supplied numeric rank cannot grant R5');
    foreach ([11,12,24,999999] as $target) {
        $before = rankSnapshot();
        rankDenied(fn()=>rankAction(1,$target,'officer'), 'outsider, other-world, nonmember or missing target rejected');
        rankCheck(rankSnapshot() === $before, 'out-of-alliance request preserves all memberships');
    }
    rankDenied(fn()=>rankAction(1,2,'officer',null,['expected_world_id'=>2]), 'stale world rejects role change');
    rankDenied(fn()=>Community::action(1,['action'=>'alliance.role','player_id'=>2,'role'=>'officer','request_id'=>'rank_wrong_world_0001'],2), 'explicit service world cannot override bound world');
    $worldTwoBefore = rankMembership(2,2);
    $receipt = 'rank_persistent_promotion_0001';
    $first = rankAction(1,2,'officer',$receipt);
    rankCheck(rankAction(1,2,'officer',$receipt) === $first, 'same promotion receipt replays original response');
    rankDenied(fn()=>rankAction(1,2,'veteran',$receipt), 'changed promotion receipt payload rejected');
    rankCheck(rankMembership(2,2) === $worldTwoBefore, 'world-one promotion leaves same player world-two rank unchanged');
    $directory = (new ReflectionProperty($fixture,'directory'))->getValue($fixture);
    $db = Connection::init($directory);
    rankCheck(rankMembership(2)['role'] === 'officer' && (int)rankMembership(2)['role_level'] === 3, 'promotion survives a fresh database connection');
    $state = Community::state(1,1);
    rankCheck(array_column($state['members'],null,'player_id')[2]['role_level'] === 3, 'fresh community read returns persisted promoted rank');
    World::bind(2);
    rankDenied(fn()=>rankAction(1,2,'officer'), 'world-one leader is only R1 in world two');
    rankCheck(Community::state(1,2)['role_level'] === 1 && Recruitment::state(1,2)['role_level'] === 1, 'read models use current-world rank for same player');
    World::bind(1);
    rankCheck(Community::state(24,1)['role_level'] === 0 && Recruitment::state(24,1)['role_level'] === 0, 'unaffiliated players have rank zero in both read models');

    // Exercise public mutation services, not reflected private write methods.
    $created = Kingdom::action(20,['action'=>'alliance.create','name'=>'Created rank alliance','tag'=>'NEW','description'=>'Rank contract','expected_world_id'=>1]);
    $createdId = (int)$created['result']['alliance_id'];
    rankCheck(rankMembership(20)['role'] === 'leader' && (int)rankMembership(20)['role_level'] === 5, 'alliance creation persists R5');
    rankCheck($created['state']['alliance']['role_level'] === 5 && $created['state']['profile']['alliance']['role_level'] === 5, 'kingdom alliance and profile expose leader rank');
    Kingdom::action(21,['action'=>'alliance.join','alliance_id'=>$createdId,'expected_world_id'=>1]);
    recruitmentAction(22,'alliance.join',['alliance_id'=>$createdId]);
    foreach ([21,22] as $player) { rankCheck(rankMembership($player)['role'] === 'member' && (int)rankMembership($player)['role_level'] === 1, 'both direct join services persist R1'); }
    recruitmentAction(20,'recruitment.save',['language'=>'en','style'=>'pve','activity'=>'evening','timezone'=>'Europe/Luxembourg','mode'=>'application','minimum_power'=>0]);
    $application = recruitmentAction(23,'application.submit',['alliance_id'=>$createdId,'message'=>'Join as R1']);
    $accepted = recruitmentAction(20,'application.accept',['application_id'=>$application['application_id']],'rank_accept_receipt_0001');
    rankCheck(recruitmentAction(20,'application.accept',['application_id'=>$application['application_id']],'rank_accept_receipt_0001') === $accepted, 'application acceptance remains replay safe');
    rankCheck(rankMembership(23)['role'] === 'member' && (int)rankMembership(23)['role_level'] === 1, 'application acceptance persists R1');
    rankUniqueLeader($createdId, 'all joining paths retain exactly one leader');
    $recruitState = Recruitment::state(20,1);
    foreach ($recruitState['recent_members'] as $member) { rankCheck($member['role_level'] === ($member['role']==='leader'?5:1), 'recruitment recent members expose exact rank'); }
    $kingdom = Kingdom::state(20);
    foreach ($kingdom['alliance']['members'] as $member) { rankCheck($member['role_level'] === ($member['role']==='leader'?5:1), 'kingdom members expose exact rank'); }

    rankAction(20,21,'vice_leader');
    foreach (['alliance.transfer','alliance.kick'] as $action) {
        $before = rankSnapshot();
        rankDenied(fn()=>Kingdom::action(21,['action'=>$action,'player_id'=>22,'expected_world_id'=>1]), 'R4 cannot '.$action);
        rankDenied(fn()=>Kingdom::action(20,['action'=>$action,'player_id'=>20,'expected_world_id'=>1]), 'R5 cannot '.$action.' self');
        rankDenied(fn()=>Kingdom::action(20,['action'=>$action,'player_id'=>11,'expected_world_id'=>1]), 'R5 cannot '.$action.' another alliance member');
        rankDenied(fn()=>Kingdom::action(20,['action'=>$action,'player_id'=>12,'expected_world_id'=>1]), 'R5 cannot '.$action.' another world member');
        rankCheck(rankSnapshot() === $before, 'denied leader management leaves ranks and membership intact');
    }
    rankDenied(fn()=>Kingdom::action(20,['action'=>'alliance.leave','expected_world_id'=>1]), 'current R5 must transfer leadership before leaving');
    Kingdom::action(20,['action'=>'alliance.transfer','player_id'=>21,'expected_world_id'=>1]);
    rankCheck(rankMembership(20)['role'] === 'member' && (int)rankMembership(20)['role_level'] === 1 && rankMembership(21)['role'] === 'leader' && (int)rankMembership(21)['role_level'] === 5, 'transfer demotes old leader to R1 and promotes target to R5 atomically');
    rankUniqueLeader($createdId, 'transfer produces exactly one canonical leader');
    rankDenied(fn()=>Kingdom::action(20,['action'=>'alliance.transfer','player_id'=>22,'expected_world_id'=>1]), 'former leader immediately loses transfer authority');
    rankDenied(fn()=>rankAction(20,22,'officer'), 'former leader immediately loses promotion authority');
    Kingdom::action(21,['action'=>'alliance.kick','player_id'=>22,'expected_world_id'=>1]);
    rankCheck(rankMembership(22) === null, 'new R5 can remove an ordinary member');
    rankUniqueLeader($createdId, 'kick leaves canonical leader unchanged');

    // Two real processes compete for the same original leadership. Only one may win.
    rankPair(5,1);
    $workers = [];
    foreach ([2,4] as $target) {
        $process = proc_open([PHP_BINARY,__FILE__,'--transfer-worker',$directory,(string)$target], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, ROOT_DIR, null, ['bypass_shell'=>true]);
        if (!is_resource($process)) { throw new RuntimeException('Transfer worker failed to start.'); }
        fclose($pipes[0]); $workers[] = [$process,$pipes];
    }
    $wins = 0; $denials = 0;
    foreach ($workers as [$process,$pipes]) {
        $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]); $code = proc_close($process);
        $result = json_decode($output,true);
        rankCheck($code === 0 && $error === '' && is_array($result), 'concurrent transfer process completed cleanly: '.$error);
        $wins += (int)($result['ok'] ?? false); $denials += (int)($result['denied'] ?? false);
    }
    rankCheck($wins === 1 && $denials === 1, 'competing transfers accept exactly one request');
    rankUniqueLeader(1, 'concurrent transfers cannot create two leaders');
    rankCheck(rankMembership(1)['role'] === 'member' && (int)rankMembership(1)['role_level'] === 1, 'concurrent transfer synchronizes former leader rank');

    // Upgrade seeded legacy inconsistencies; missing canonical membership is not invented.
    $db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id,member_count) VALUES
        (81,1,'Legacy valid anchor','LEG',81,5),(91,1,'Missing anchor','MIS',91,1),(93,1,'Wrong world anchor','WRG',94,1)");
    foreach ([[81,81,1,'veteran',2],[81,82,1,'leader',5],[81,83,1,'officer',5],[81,84,1,'vice_leader',1],[81,85,1,'veteran',1],
              [91,92,1,'leader',1],[93,93,1,'officer',5],[93,94,2,'member',4]] as $row) {
        $db->execute('INSERT INTO alliance_members(alliance_id,player_id,world_id,role,role_level) VALUES(?,?,?,?,?)', $row);
    }
    MigrationSql::apply($db->getPdo(),$migration);
    rankCheck(rankMembership(81)['role'] === 'leader' && (int)rankMembership(81)['role_level'] === 5, 'upgrade promotes existing canonical same-world member');
    rankCheck(rankMembership(82)['role'] === 'member' && (int)rankMembership(82)['role_level'] === 1, 'upgrade demotes duplicate noncanonical leader to R1');
    foreach ([83=>3,84=>4,85=>2] as $player=>$level) { rankCheck((int)rankMembership($player)['role_level'] === $level && rankMembership($player)['role'] === RANK_ROLES[$level], 'upgrade derives stale numeric rank from enum'); }
    rankUniqueLeader(81,'upgrade repairs duplicate leadership with a valid anchor');
    rankCheck(rankMembership(91) === null && rankMembership(92)['role'] === 'leader' && (int)rankMembership(92)['role_level'] === 5, 'missing canonical membership is not invented or reassigned');
    rankCheck(rankMembership(93)['role'] === 'officer' && (int)rankMembership(93)['role_level'] === 3 && rankMembership(94,2)['role'] === 'member' && (int)rankMembership(94,2)['role_level'] === 1, 'wrong-world anchor grants no leadership and levels still follow enum');
    $afterUpgrade = rankSnapshot();
    MigrationSql::apply($db->getPdo(),$migration);
    rankCheck(rankSnapshot() === $afterUpgrade, 'rank backfill and leader repair are idempotent');
    echo "PASS alliance ranks: $assertions assertions\n";
} finally {
    $fixture->close();
}
