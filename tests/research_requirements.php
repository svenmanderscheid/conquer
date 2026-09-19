<?php
declare(strict_types=1);
/** Exact prerequisite levels through the actual local HTTP endpoint; removes its own fixture. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/src/Bootstrap.php';
\Conquer\Bootstrap::init(ROOT_DIR);
$base = rtrim($argv[1] ?? 'http://localhost/conquer', '/');
if (!in_array(parse_url($base, PHP_URL_HOST), ['localhost','127.0.0.1'], true)) { exit("Local test hosts only.\n"); }
$db = \Conquer\Db\Connection::getInstance();
$pid = 0; $cityId = 0; $failed = false;
$token = bin2hex(random_bytes(32)); $csrf = bin2hex(random_bytes(32));
function researchCheck(bool $ok, string $label): void {
    if (!$ok) { throw new RuntimeException($label); }
    echo "PASS $label\n";
}
function researchCall(string $code, int $level, int $status, ?string $error = null): array {
    global $base, $token, $csrf;
    for ($attempt=0; $attempt<12; $attempt++) {
        $ch = curl_init($base . '/api/research/start');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true,
            CURLOPT_POSTFIELDS=>json_encode(['code'=>$code, 'level_to'=>$level]),
            CURLOPT_HTTPHEADER=>['Content-Type: application/json', 'Cookie: conquer_session='.$token, 'X-CSRF-Token: '.$csrf], CURLOPT_TIMEOUT=>20]);
        $raw = curl_exec($ch); $actual = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($actual !== 429) { break; }
        sleep(1);
    }
    $body = json_decode((string) $raw, true);
    researchCheck($actual === $status && is_array($body) && ($error === null || ($body['error']['code'] ?? null) === $error),
        "$code level $level HTTP $status" . ($error ? " $error" : '') . ($actual === $status ? '' : ': '.$raw));
    return $body;
}
function researchSeed(string $code, int $level): void {
    global $db, $pid;
    $db->execute('INSERT INTO player_research(player_id, world_id, research_code, level) VALUES(?,1,?,?) ON DUPLICATE KEY UPDATE level=VALUES(level)', [$pid,$code,$level]);
}
function researchBalance(): array {
    global $db, $cityId;
    return array_map('intval', $db->query('SELECT food,lumber,stone,gold FROM cities WHERE id=?', [$cityId])->fetch());
}
function researchClearQueue(): void {
    global $db,$pid;
    $db->execute('DELETE FROM research_queue WHERE player_id=?', [$pid]);
}
try {
    $name = 'ResearchReq' . bin2hex(random_bytes(5));
    $db->execute('INSERT INTO players(username,email,password_hash) VALUES(?,?,?)', [$name,$name.'@tests.invalid',password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT)]);
    $pid = (int) $db->lastInsertId();
    \Conquer\Auth\OAuth::createDefaultCity($db,$pid,$name);
    $cityId = (int) $db->query('SELECT id FROM cities WHERE player_id=?',[$pid])->fetchColumn();
    $db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,user_agent,expires_at) VALUES(?,?,?,'127.0.0.1','research requirements regression',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 10 MINUTE))", [$pid,$token,$csrf]);
    $db->execute("UPDATE city_buildings SET level=3 WHERE city_id=? AND building_code='academy'",[$cityId]);
    // Balances above production caps keep exact debit checks independent of elapsed wall-clock time.
    $db->execute('UPDATE cities SET food=10000000,lumber=10000000,stone=10000000,gold=10000000,last_resource_update=UTC_TIMESTAMP() WHERE id=?',[$cityId]);
    researchCall('warrior',1,400,'UNKNOWN_RESEARCH');
    researchSeed('infantry_hp',1);
    $before = researchBalance();
    $denied = researchCall('infantry_def',1,400,'PREREQUISITE_NOT_MET');
    researchCheck(str_contains($denied['error']['message'],'Stufe 2') && str_contains($denied['error']['message'],'aktuell: 1'), 'error reports actual required and owned predecessor levels');
    researchCheck(researchBalance() === $before && (int)$db->query('SELECT COUNT(*) FROM research_queue WHERE player_id=?',[$pid])->fetchColumn() === 0, 'locked research spends nothing and creates no queue');
    researchSeed('infantry_hp',2);
    $started = researchCall('infantry_def',1,200);
    $definition = \Conquer\Game\Research\ResearchData::get('infantry_def')['levels'][0];
    $after = researchBalance();
    foreach ($before as $key=>$value) { researchCheck($after[$key] === $value - $definition['resources'][$key], 'exact '.$key.' cost charged after predecessor reaches level 2'); }
    researchCheck(($started['data']['queue_entry']['code'] ?? null) === 'infantry_def', 'unlocked successor enters actual research queue');
    researchClearQueue();

    researchSeed('food_production',2); researchSeed('wood_production',2); researchSeed('stone_production',1);
    $before = researchBalance();
    researchCall('gold_production',1,400,'PREREQUISITE_NOT_MET');
    researchCheck(researchBalance() === $before, 'all three economy predecessors are required, not only the first');
    researchSeed('stone_production',2);
    researchCall('gold_production',1,200);
    researchClearQueue();

    researchSeed('infantry_def',2);
    $before = researchBalance();
    researchCall('infantry_def',3,400,'ACADEMY_TOO_LOW');
    researchCheck(researchBalance() === $before, 'later upgrade checks its higher academy requirement without charging');
    $db->execute("UPDATE city_buildings SET level=4 WHERE city_id=? AND building_code='academy'",[$cityId]);
    researchCall('infantry_def',3,200);
    researchClearQueue();
    researchSeed('gold_production',2);
    researchCall('research_speed',1,400,'ACADEMY_TOO_LOW');
    $db->execute("UPDATE city_buildings SET level=17 WHERE city_id=? AND building_code='academy'",[$cityId]);
    $db->execute('UPDATE cities SET food=3000000,lumber=3000000,stone=3000000,gold=3000000,last_resource_update=UTC_TIMESTAMP() WHERE id=?',[$cityId]);
    researchCall('research_speed',1,400,'PREREQUISITE_NOT_MET');
    researchSeed('infantry_storage',2); researchSeed('ranged_storage',2); researchSeed('cavalry_storage',1);
    researchCall('research_speed',1,400,'PREREQUISITE_NOT_MET');
    researchSeed('cavalry_storage',2);
    researchCall('research_speed',1,200);
    researchClearQueue();
    researchCheck((int)$db->query('SELECT COUNT(*) FROM player_research WHERE player_id=? AND research_code IN (?,?,?) AND level=2',[$pid,'infantry_storage','ranged_storage','cavalry_storage'])->fetchColumn() === 3, 'original development path requires all three real troop carrying technologies at level 2');
    researchSeed('research_speed',1);
    researchCall('construction_speed',1,400,'ACADEMY_TOO_LOW');
    $db->execute("UPDATE city_buildings SET level=19 WHERE city_id=? AND building_code='academy'",[$cityId]);
    researchCall('construction_speed',1,400,'PREREQUISITE_NOT_MET');
    researchSeed('research_speed',2);
    researchCall('construction_speed',1,200);
    researchClearQueue();
    echo "ALL RESEARCH REQUIREMENT HTTP CHECKS PASSED\n";
} catch (Throwable $e) {
    $failed = true; fwrite(STDERR, 'FAIL '.$e->getMessage()."\n");
} finally {
    if ($pid) {
        foreach (['sessions','research_queue','player_research','player_inventory','player_daily_quests','player_treasures'] as $table) {
            $db->execute("DELETE FROM $table WHERE player_id=?",[$pid]);
        }
        if ($cityId) { $db->execute('DELETE FROM cities WHERE id=?',[$cityId]); }
        $db->execute('DELETE FROM players WHERE id=?',[$pid]);
    }
}
if ($failed) { exit(1); }
