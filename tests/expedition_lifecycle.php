<?php
declare(strict_types=1);

/**
 * Real MySQL lifecycle and cross-process race checks in a disposable database.
 * Copies only local schema, seeds isolated test players, and removes all fixtures.
 * Usage: C:\xampp\php\php.exe tests/expedition_lifecycle.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR . '/src'))->register();
date_default_timezone_set('UTC');
\Conquer\Logger::init(sys_get_temp_dir() . '/conquer-expedition-tests.log', 'ERROR');

use Conquer\Db\Connection;
use Conquer\Game\Expedition\ExpeditionException;
use Conquer\Game\Expedition\ExpeditionRules;
use Conquer\Game\Expedition\ExpeditionService;

if (($argv[1] ?? '') === '--worker') {
    Connection::init($argv[2]);
    try {
        $body = json_decode(base64_decode($argv[4]), true, 32, JSON_THROW_ON_ERROR);
        if (($body['action'] ?? '') === '_tick') { ExpeditionService::tick((int) $argv[3]); }
        else { ExpeditionService::action((int) $argv[3], $body); }
        echo json_encode(['ok' => true]);
    } catch (ExpeditionException $e) {
        echo json_encode(['ok' => false, 'code' => $e->errorCode]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'unexpected' => $e->getMessage()]);
    }
    exit;
}

function check(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
    echo 'PASS ' . $message . "\n";
}

function rejects(callable $operation, string $code): void
{
    try { $operation(); }
    catch (ExpeditionException $e) { check($e->errorCode === $code, 'reject ' . $code . ' (got ' . $e->errorCode . ')'); return; }
    throw new RuntimeException('Expected rejection: ' . $code);
}

function act(int $player, string $action, int $id = 0, array $extra = []): array
{
    return ExpeditionService::action($player, ['action' => $action, 'expedition_id' => $id] + $extra);
}

function raid(int $id): array
{
    return Connection::getInstance()->query('SELECT * FROM expeditions WHERE id=?', [$id])->fetch();
}

function army(int $player): int
{
    return (int) Connection::getInstance()->query('SELECT SUM(t.count) FROM city_troops t JOIN cities c ON c.id=t.city_id WHERE c.player_id=?', [$player])->fetchColumn();
}

function arrive(int $id): void
{
    Connection::getInstance()->execute("UPDATE expedition_missions SET arrival_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 SECOND),return_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE expedition_id=? AND status IN ('marching','returning')", [$id]);
    ExpeditionService::tick();
}

function pairRaid(int $host, int $guest, int $guestAlliance): int
{
    $id = act($host, 'create')['expedition_id'];
    act($host, 'invite', $id, ['alliance_id' => $guestAlliance]);
    act($guest, 'accept', $id);
    return $id;
}

function concurrent(string $root, array $requests): array
{
    $jobs = [];
    foreach ($requests as [$player, $body]) {
        $pipes = [];
        $process = proc_open([PHP_BINARY, __FILE__, '--worker', $root, (string) $player, base64_encode(json_encode($body, JSON_THROW_ON_ERROR))],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOT_DIR, null, ['bypass_shell' => true]);
        if (!is_resource($process)) { throw new RuntimeException('Could not start concurrent test worker.'); }
        fclose($pipes[0]);
        $jobs[] = [$process, $pipes];
    }
    $results = [];
    foreach ($jobs as [$process, $pipes]) {
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $exit = proc_close($process);
        if ($exit !== 0 || $err !== '') { throw new RuntimeException('Worker error: ' . $err . $out); }
        $result = json_decode($out, true, 32, JSON_THROW_ON_ERROR);
        if (isset($result['unexpected'])) { throw new RuntimeException($result['unexpected']); }
        $results[] = $result;
    }
    return $results;
}

$config = require ROOT_DIR . '/config/database.php';
if (!in_array($config['host'] ?? '', ['127.0.0.1', 'localhost'], true)) {
    fwrite(STDERR, "These disposable-database checks require local MySQL.\n"); exit(1);
}
$database = 'conquer_expedition_test_' . bin2hex(random_bytes(6));
$tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $database;
$admin = null;
$exitCode = 0;
try {
    $source = (string) $config['database'];
    if (!preg_match('/^[a-zA-Z0-9_]+$/D', $source)) { throw new RuntimeException('Unexpected local database identifier.'); }
    $admin = new PDO('mysql:host=' . $config['host'] . ';port=' . (int) ($config['port'] ?? 3306) . ';charset=utf8mb4', $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $admin->exec('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $tables = $admin->query('SHOW TABLES FROM `' . $source . '`')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/D', $table)) { throw new RuntimeException('Unexpected local table identifier.'); }
        $admin->exec('CREATE TABLE `' . $database . '`.`' . $table . '` LIKE `' . $source . '`.`' . $table . '`');
    }
    // Works before or after the parent task has applied the encounter migration.
    $admin->exec('USE `' . $database . '`');
    $admin->exec(file_get_contents(ROOT_DIR . '/migrations/0055_create_expeditions.sql'));
    $admin->exec('INSERT INTO worlds SELECT * FROM `' . $source . '`.worlds WHERE id=1');
    mkdir($tempRoot . '/config', 0700, true);
    $config['database'] = $database;
    file_put_contents($tempRoot . '/config/database.php', "<?php\nreturn " . var_export($config, true) . ";\n");
    $db = Connection::init($tempRoot);
    $seed = random_int(500000000, 900000000);
    [$host, $guest, $member, $outsider] = [$seed, $seed + 1, $seed + 2, $seed + 3];
    foreach ([$host, $guest, $member, $outsider] as $index => $player) {
        $db->execute('INSERT INTO players(id,username,email,password_hash) VALUES(?,?,?,?)', [$player, 'ExpeditionTest' . $index, 'expedition' . $index . '@invalid.test', 'unused']);
        $db->execute('INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,food,lumber,stone,gold) VALUES(?,?,1,?,?,?,10000,10000,10000,5000)', [$player, $player, 'Test city', $index + 1, 1]);
        foreach (\Conquer\Game\City\CityState::BUILDING_CODES as $code) {
            $db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(?,?,1)', [$player, $code]);
        }
        // Current T1 fighters have 1 attack, rather than the historical 45.
        // Scale only fixture armies; keep the real encounter rules unchanged.
        $db->execute('INSERT INTO city_troops(city_id,troop_code,count) VALUES(?,50100101,4500)', [$player]);
    }
    $db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id,member_count) VALUES(101,1,'Test host','HOST',?,2),(102,1,'Test guest','GUEST',?,1)", [$host, $guest]);
    $db->execute("INSERT INTO alliance_members(alliance_id,player_id,role) VALUES(101,?,'leader'),(102,?,'leader'),(101,?,'member')", [$host, $guest, $member]);

    if (($argv[1] ?? '') === '--http') {
        require __DIR__ . '/expedition_http_cases.php';
    } else {
    check(ExpeditionService::state($outsider)['expeditions'] === [], 'players outside alliances see a real empty state');
    rejects(fn () => act($outsider, 'create'), 'LEADER_REQUIRED');
    rejects(fn () => act($member, 'create'), 'LEADER_REQUIRED');
    foreach ([[], [50100101 => -1], [50100101 => 1.2], [50100101 => '2'], [123 => 1], [50100101 => 5001]] as $invalid) {
        rejects(fn () => ExpeditionRules::troops($invalid), 'INVALID_TROOPS');
    }
    check(ExpeditionRules::strength([50100101 => 450], 'pass', []) === 2700, 'the pass uses defense strength and assault uses attack');
    check(ExpeditionRules::strength([50100101 => 450], 'boss', ['infantry_atk' => 0.2, 'vs_monster_attack' => 0.5]) === 810, 'research and monster bonuses affect the reserved army strength');
    $id = act($host, 'create')['expedition_id'];
    rejects(fn () => act($host, 'create'), 'ALLIANCE_BUSY');
    rejects(fn () => act($member, 'invite', $id, ['alliance_id' => 102]), 'LEADER_REQUIRED');
    act($host, 'invite', $id, ['alliance_id' => 102]);
    check(ExpeditionService::state($guest)['expeditions'][0]['permissions']['accept'], 'invited leader sees an actionable invitation');
    rejects(fn () => act($host, 'accept', $id), 'LEADER_REQUIRED');
    rejects(fn () => act($outsider, 'accept', $id), 'LEADER_REQUIRED');
    rejects(fn () => act($host, 'supply', $id, ['amount' => 100]), 'NOT_IN_COALITION');
    act($guest, 'decline', $id);
    act($host, 'invite', $id, ['alliance_id' => 102]);
    act($guest, 'accept', $id);
    check(raid($id)['phase'] === 'preparation', 'acceptance by both leaders begins preparation');
    rejects(fn () => act($guest, 'create'), 'ALLIANCE_BUSY');
    rejects(fn () => act($outsider, 'supply', $id, ['amount' => 100]), 'NOT_IN_COALITION');
    rejects(fn () => act($host, 'dispatch', $id, ['objective' => 'boss', 'troops' => [50100101 => 450]]), 'WRONG_PHASE');
    rejects(fn () => act($host, 'dispatch', $id, ['objective' => 'pass', 'troops' => [50100101 => 450]]), 'WRONG_ROLE');
    rejects(fn () => act($guest, 'dispatch', $id, ['objective' => 'defenses', 'troops' => [50100101 => 450]]), 'WRONG_ROLE');
    $food = (int) $db->query('SELECT food FROM cities WHERE id=?', [$host])->fetchColumn();
    act($host, 'supply', $id, ['amount' => 900]);
    act($host, 'supply', $id, ['amount' => 1000]);
    check((int) $db->query('SELECT food FROM cities WHERE id=?', [$host])->fetchColumn() === $food - 1000, 'supply charges only actual capped requirement');
    rejects(fn () => act($guest, 'supply', $id, ['amount' => 10]), 'OBJECTIVE_COMPLETE');
    $body = ['action' => 'dispatch', 'expedition_id' => $id, 'objective' => 'defenses', 'troops' => [50100101 => 3150]];
    $race = concurrent($tempRoot, [[$host, $body], [$host, $body]]);
    check(count(array_filter($race, fn ($r) => $r['ok'])) === 1 && army($host) === 1350, 'concurrent dispatches cannot reserve the same troops twice');
    act($guest, 'dispatch', $id, ['objective' => 'pass', 'troops' => [50100101 => 450]]);
    arrive($id);
    check(raid($id)['phase'] === 'boss' && army($host) === 4500 && army($guest) === 4500, 'both alliance objectives unlock boss and all armies return');
    check((int) raid($id)['defenses'] === 300 && (int) raid($id)['pass_progress'] === 300, 'overkill never generates excess objective or contribution');
    $historicLog = (string) $db->query("SELECT message FROM expedition_logs WHERE expedition_id=? AND player_id=? AND type='dispatch' ORDER BY id LIMIT 1", [$id, $host])->fetchColumn();
    $db->execute("INSERT INTO kingdom_profiles(player_id,display_name) VALUES(?,'Wachtmeister') ON DUPLICATE KEY UPDATE display_name=VALUES(display_name)", [$host]);
    $renamed = ExpeditionService::state($host)['expeditions'][0];
    $renamedParticipants = array_column($renamed['participants'], 'username', 'player_id');
    $renamedMissions = array_column($renamed['missions'], 'username', 'player_id');
    check($renamedParticipants[$host] === 'Wachtmeister' && $renamedMissions[$host] === 'Wachtmeister', 'current participants and missions use the profile display name');
    check($historicLog === (string) $db->query("SELECT message FROM expedition_logs WHERE expedition_id=? AND player_id=? AND type='dispatch' ORDER BY id LIMIT 1", [$id, $host])->fetchColumn(), 'display-name updates preserve historical log text');
    ExpeditionService::tick();
    check(army($host) === 4500 && army($guest) === 4500, 'repeated cron ticks do not duplicate returned troops');
    rejects(fn () => act($host, 'claim', $id), 'NO_REWARD');
    // Two real simultaneous arrivals overkill a shared health pool safely.
    act($host, 'dispatch', $id, ['objective' => 'boss', 'troops' => [50100101 => 1350]]);
    check(str_starts_with((string) $db->query("SELECT message FROM expedition_logs WHERE expedition_id=? AND player_id=? AND type='dispatch' ORDER BY id DESC LIMIT 1", [$id, $host])->fetchColumn(), 'Wachtmeister'), 'new expedition log entries use the profile display name');
    act($guest, 'dispatch', $id, ['objective' => 'boss', 'troops' => [50100101 => 1350]]);
    $db->execute("UPDATE expedition_missions SET arrival_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 SECOND),return_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE expedition_id=? AND status='marching'", [$id]);
    $arrivalRace = concurrent($tempRoot, [[$host, ['action' => '_tick']], [$guest, ['action' => '_tick']]]);
    check(count(array_filter($arrivalRace, fn ($r) => $r['ok'])) === 2 && army($host) === 4500 && army($guest) === 4500, 'independent concurrent arrival workers settle and return each army once');
    check(raid($id)['phase'] === 'victory' && (int) raid($id)['boss_hp'] === 0, 'coalition defeats boss with shared HP capped at zero');
    $xpSum=(int)$db->query('SELECT SUM(xp) FROM lord_xp_receipts WHERE world_id=1 AND source=?',['expedition:'.$id])->fetchColumn();
    check($xpSum>0&&$xpSum<=500,'boss victory distributes one bounded hunting XP pool');
    check((int)$db->query('SELECT COUNT(*) FROM lord_xp_receipts x WHERE x.source=? AND NOT EXISTS(SELECT 1 FROM expedition_participants p WHERE p.expedition_id=? AND p.player_id=x.player_id AND p.boss_damage>0)',['expedition:'.$id,$id])->fetchColumn()===0,'boss XP goes only to actual damage contributors');
    ExpeditionService::tick();
    check((int)$db->query('SELECT SUM(xp) FROM lord_xp_receipts WHERE world_id=1 AND source=?',['expedition:'.$id])->fetchColumn()===$xpSum,'boss victory cannot award its XP pool twice');
    check((int) $db->query('SELECT SUM(boss_damage) FROM expedition_participants WHERE expedition_id=?', [$id])->fetchColumn() === ExpeditionRules::BOSS_HP, 'credited damage exactly equals boss HP even on overkill');
    $before = (int) $db->query('SELECT gold FROM cities WHERE id=?', [$host])->fetchColumn();
    $claimBody = ['action' => 'claim', 'expedition_id' => $id];
    $race = concurrent($tempRoot, [[$host, $claimBody], [$host, $claimBody]]);
    check(count(array_filter($race, fn ($r) => $r['ok'])) === 1, 'concurrent reward claims pay once');
    check((int) $db->query('SELECT gold FROM cities WHERE id=?', [$host])->fetchColumn() === $before + 600, 'the once-only reward credits actual city resources');
    rejects(fn () => act($host, 'claim', $id), 'ALREADY_CLAIMED');
    rejects(fn () => act($outsider, 'claim', $id), 'NO_REWARD');
    // Earned rewards remain available after membership removal.
    $db->execute('DELETE FROM alliance_members WHERE player_id=?', [$guest]);
    act($guest, 'claim', $id);
    check(ExpeditionService::state($guest)['expeditions'][0]['reward_claimed'], 'earned reward and report survive leaving an alliance');
    $db->execute("INSERT INTO alliance_members(alliance_id,player_id,role) VALUES(102,?,'leader')", [$guest]);

    $cancelled = pairRaid($host, $guest, 102);
    act($member, 'supply', $cancelled, ['amount' => 100]);
    act($host, 'dispatch', $cancelled, ['objective' => 'defenses', 'troops' => [50100101 => 1125]]);
    act($guest, 'dispatch', $cancelled, ['objective' => 'pass', 'troops' => [50100101 => 1125]]);
    rejects(fn () => act($member, 'cancel', $cancelled), 'LEADER_REQUIRED');
    rejects(fn () => act($outsider, 'cancel', $cancelled), 'LEADER_REQUIRED');
    act($guest, 'cancel', $cancelled);
    arrive($cancelled);
    check(army($host) === 4500 && army($guest) === 4500 && raid($cancelled)['phase'] === 'cancelled', 'cancellation safely returns armies from both alliances');
    check((int) $db->query('SELECT food FROM cities WHERE id=?', [$member])->fetchColumn() === 9900, 'cancellation does not invent a supply refund');

    $expired = pairRaid($host, $guest, 102);
    act($host, 'dispatch', $expired, ['objective' => 'defenses', 'troops' => [50100101 => 450]]);
    $db->execute('UPDATE expeditions SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?', [$expired]);
    ExpeditionService::tick();
    arrive($expired);
    check(raid($expired)['phase'] === 'expired' && army($host) === 4500, 'expiry returns a reserved army without applying a late attack');

    $repeat = pairRaid($host, $guest, 102);
    act($host, 'supply', $repeat, ['amount' => 1000]);
    act($host, 'dispatch', $repeat, ['objective' => 'defenses', 'troops' => [50100101 => 450]]);
    act($guest, 'dispatch', $repeat, ['objective' => 'pass', 'troops' => [50100101 => 450]]);
    arrive($repeat);
    act($host, 'dispatch', $repeat, ['objective' => 'boss', 'troops' => [50100101 => 1800]]);
    arrive($repeat);
    rejects(fn () => act($host, 'claim', $repeat), 'REWARD_COOLDOWN');
    $db->execute('UPDATE expedition_rewards SET created_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 HOUR) WHERE player_id=?', [$host]);
    act($host, 'claim', $repeat);
    check((int) $db->query('SELECT COUNT(*) FROM expedition_rewards WHERE player_id=?', [$host])->fetchColumn() === 2, 'repeat encounters work after personal reward cooldown');

    $switched = pairRaid($host, $guest, 102);
    act($member, 'supply', $switched, ['amount' => 100]);
    $db->execute('UPDATE alliance_members SET alliance_id=102 WHERE player_id=?', [$member]);
    rejects(fn () => act($member, 'supply', $switched, ['amount' => 100]), 'ALLIANCE_CHANGED');
    act($host, 'cancel', $switched);

    $supplyRaceId = pairRaid($host, $guest, 102);
    $db->execute('UPDATE cities SET food=100,last_resource_update=UTC_TIMESTAMP() WHERE id=?', [$member]);
    $supplyBody = ['action' => 'supply', 'expedition_id' => $supplyRaceId, 'amount' => 100];
    $supplyRace = concurrent($tempRoot, [[$member, $supplyBody], [$member, $supplyBody]]);
    check(count(array_filter($supplyRace, fn ($r) => $r['ok'])) === 1 && (int) raid($supplyRaceId)['supplies'] === 100,
        'concurrent supply deliveries cannot overspend a city resource balance');
    check((int) $db->query('SELECT food FROM cities WHERE id=?', [$member])->fetchColumn() === 0, 'supply spending preserves a nonnegative real balance');
    act($host, 'cancel', $supplyRaceId);

    $offline = pairRaid($host, $guest, 102);
    act($host, 'supply', $offline, ['amount' => 1000]);
    act($host, 'dispatch', $offline, ['objective' => 'defenses', 'troops' => [50100101 => 450]]);
    act($guest, 'dispatch', $offline, ['objective' => 'pass', 'troops' => [50100101 => 450]]);
    arrive($offline);
    act($host, 'dispatch', $offline, ['objective' => 'boss', 'troops' => [50100101 => 1800]]);
    $db->execute("UPDATE expedition_missions SET arrival_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 SECOND),return_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 8 SECOND) WHERE expedition_id=? AND status='marching'", [$offline]);
    $db->execute('UPDATE expeditions SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 5 SECOND) WHERE id=?', [$offline]);
    ExpeditionService::tick();
    check(raid($offline)['phase'] === 'victory' && army($host) === 4500, 'offline settlement honors an attack that arrived before expiry');
    check(true, 'all lifecycle and cross-process checks finished in disposable storage');
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    $exitCode = 1;
} finally {
    if ($admin !== null && preg_match('/^conquer_expedition_test_[a-f0-9]{12}$/D', $database)) {
        $admin->exec('DROP DATABASE IF EXISTS `' . $database . '`');
    }
    if (is_file($tempRoot . '/config/database.php')) { unlink($tempRoot . '/config/database.php'); }
    if (is_dir($tempRoot . '/config')) { rmdir($tempRoot . '/config'); }
    if (is_dir($tempRoot)) { rmdir($tempRoot); }
}
exit($exitCode);
