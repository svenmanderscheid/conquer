<?php
declare(strict_types=1);

/** Included by expedition_lifecycle.php --http after disposable fixtures exist. */
if (PHP_SAPI !== 'cli' || !isset($tempRoot, $database, $host, $guest, $member, $outsider, $db)) { exit(1); }

function copyHttpTree(string $source, string $destination): void
{
    mkdir($destination, 0700, true);
    foreach (new DirectoryIterator($source) as $item) {
        if ($item->isDot() || $item->isLink()) { continue; }
        $path = $destination . '/' . $item->getFilename();
        if ($item->isDir()) { copyHttpTree($item->getPathname(), $path); }
        else { copy($item->getPathname(), $path); }
    }
}

function httpRequest(int $player, string $path, array|string|null $body = null, ?string $token = null): array
{
    global $httpBase, $httpSessions;
    $handle = curl_init($httpBase . $path);
    curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
    if ($player > 0) { curl_setopt($handle, CURLOPT_COOKIE, 'conquer_session=' . $httpSessions[$player]['session']); }
    if ($body !== null) {
        curl_setopt_array($handle, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => is_string($body) ? $body : json_encode($body, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-CSRF-Token: ' . ($token ?? ($httpSessions[$player]['csrf'] ?? ''))],
        ]);
    }
    $raw = curl_exec($handle);
    if ($raw === false) { throw new RuntimeException('HTTP failed: ' . curl_error($handle)); }
    $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    curl_close($handle);
    $data = json_decode($raw, true);
    if (!is_array($data)) { throw new RuntimeException('Non-JSON response (' . $status . '): ' . substr($raw, 0, 600)); }
    return ['status' => $status, 'json' => $data];
}

function httpAction(int $player, string $action, int $raid = 0, array $extra = []): array
{
    $result = httpRequest($player, '/api/expeditions/action', ['action' => $action, 'expedition_id' => $raid] + $extra);
    check($result['status'] === 200 && ($result['json']['ok'] ?? false), 'HTTP ' . $action . ' succeeds: ' . ($result['json']['error']['message'] ?? ''));
    return $result['json']['data'];
}

function httpReject(array $result, int $status, string $code, string $label): void
{
    check($result['status'] === $status && ($result['json']['ok'] ?? null) === false
        && ($result['json']['error']['code'] ?? '') === $code && ($result['json']['error']['message'] ?? '') !== '', $label);
}

function httpRaid(int $player, int $id): array
{
    $result = httpRequest($player, '/api/expeditions/state');
    if ($result['status'] !== 200 || !($result['json']['ok'] ?? false)) { throw new RuntimeException('HTTP state poll failed.'); }
    foreach ($result['json']['data']['expeditions'] as $raid) { if ($raid['id'] === $id) { return $raid; } }
    throw new RuntimeException('Encounter absent from HTTP state.');
}

function httpUntil(callable $condition, int $timeout, string $label): void
{
    $end = time() + $timeout;
    do {
        if ($condition()) { check(true, $label); return; }
        usleep(500000);
    } while (time() <= $end);
    throw new RuntimeException('Timed out: ' . $label);
}

$server = null;
$serverRoot = $tempRoot . '/http';
$httpSessions = [];
try {
    copyHttpTree(ROOT_DIR . '/src', $serverRoot . '/src');
    copyHttpTree(ROOT_DIR . '/data', $serverRoot . '/data');
    mkdir($serverRoot . '/config', 0700, true);
    mkdir($serverRoot . '/logs', 0700, true);
    copy(ROOT_DIR . '/index.php', $serverRoot . '/index.php');
    copy($tempRoot . '/config/database.php', $serverRoot . '/config/database.php');
    file_put_contents($serverRoot . '/config/app.php', "<?php\nreturn ['env'=>'development','log_level'=>'ERROR'];\n");
    file_put_contents($serverRoot . '/router.php', "<?php\n\$_SERVER['SCRIPT_NAME']='/index.php'; require __DIR__.'/index.php';\n");
    foreach ([$host, $guest, $member, $outsider] as $player) {
        $httpSessions[$player] = ['session' => bin2hex(random_bytes(32)), 'csrf' => bin2hex(random_bytes(32))];
        $db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,user_agent,expires_at) VALUES(?,?,?,'127.0.0.1','Expedition HTTP test',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))", [$player, $httpSessions[$player]['session'], $httpSessions[$player]['csrf']]);
    }
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if ($socket === false) { throw new RuntimeException('No local test port available.'); }
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $httpBase = 'http://' . $address;
    $server = proc_open([PHP_BINARY, '-S', $address, '-t', $serverRoot, $serverRoot . '/router.php'],
        [0 => ['pipe', 'r'], 1 => ['file', $serverRoot . '/server.log', 'a'], 2 => ['file', $serverRoot . '/server.log', 'a']], $serverPipes,
        $serverRoot, null, ['bypass_shell' => true]);
    if (!is_resource($server)) { throw new RuntimeException('Local test HTTP server could not start.'); }
    fclose($serverPipes[0]);
    usleep(300000);
    httpReject(httpRequest(0, '/api/expeditions/state'), 401, 'UNAUTHENTICATED', 'root HTTP route rejects an unauthenticated state read');
    httpReject(httpRequest(0, '/api/expeditions/action', ['action' => 'create']), 401, 'UNAUTHENTICATED', 'root HTTP route rejects an unauthenticated mutation');
    $state = httpRequest($host, '/api/expeditions/state');
    check($state['status'] === 200 && $state['json']['data']['can_create'] && $state['json']['data']['expeditions'] === [], 'real session authenticates through root route and exposes typed empty state');
    httpReject(httpRequest($host, '/api/expeditions/action', ['action' => 'create'], ''), 403, 'CSRF_INVALID', 'missing CSRF cannot create an encounter');
    httpReject(httpRequest($host, '/api/expeditions/action', ['action' => 'create'], $httpSessions[$guest]['csrf']), 403, 'CSRF_INVALID', 'another player CSRF token is rejected');
    foreach (['{', 'null', '[]', '17', '"string"', '{}'] as $payload) {
        httpReject(httpRequest($host, '/api/expeditions/action', $payload), 400, 'INVALID_INPUT', 'malformed/non-object JSON is rejected: ' . $payload);
    }
    httpReject(httpRequest($host, '/api/expeditions/action', ['action' => []]), 400, 'INVALID_ACTION', 'an array action cannot trigger a PHP type error');
    httpReject(httpRequest($host, '/api/expeditions/action', ['action' => 'create', 'name' => []]), 400, 'INVALID_NAME', 'a non-string encounter name is rejected');
    httpReject(httpRequest($host, '/api/expeditions/action', str_repeat(' ', 8200)), 413, 'INVALID_INPUT', 'oversized HTTP request is rejected');
    $created = httpAction($host, 'create');
    $id = $created['expedition_id'];
    foreach (['1', 1.5, true, [], null, -1] as $badId) {
        httpReject(httpRequest($host, '/api/expeditions/action', ['action' => 'cancel', 'expedition_id' => $badId]), 400, 'INVALID_INPUT', 'typed expedition identifier is validated');
    }
    httpAction($host, 'invite', $id, ['alliance_id' => 102]);
    check(httpRaid($guest, $id)['permissions']['accept'], 'partner sees invitation through its own HTTP session');
    httpReject(httpRequest($member, '/api/expeditions/action', ['action' => 'accept', 'expedition_id' => $id]), 403, 'LEADER_REQUIRED', 'a normal member cannot accept the coalition invitation');
    httpAction($guest, 'accept', $id);
    foreach (['100', 10.5, -10, [], null] as $badAmount) {
        httpReject(httpRequest($host, '/api/expeditions/action', ['action' => 'supply', 'expedition_id' => $id, 'amount' => $badAmount]), 400, 'INVALID_AMOUNT', 'typed supply amount is validated');
    }
    foreach ([[50100101 => '10'], [50100101 => 1.5], [50100101 => -1], [123 => 1], '10'] as $badTroops) {
        httpReject(httpRequest($host, '/api/expeditions/action', ['action' => 'dispatch', 'expedition_id' => $id, 'objective' => 'defenses', 'troops' => $badTroops]), 400, 'INVALID_TROOPS', 'typed troop payload is validated');
    }
    httpAction($member, 'supply', $id, ['amount' => 100]);
    httpAction($host, 'supply', $id, ['amount' => 900]);
    httpAction($member, 'dispatch', $id, ['objective' => 'defenses', 'troops' => [50100101 => 225]]);
    httpAction($host, 'dispatch', $id, ['objective' => 'defenses', 'troops' => [50100101 => 450]]);
    httpAction($guest, 'dispatch', $id, ['objective' => 'pass', 'troops' => [50100101 => 450]]);
    $kick = httpRequest($host, '/api/kingdom/action', ['action' => 'alliance.kick', 'player_id' => $member]);
    check($kick['status'] === 200 && ($kick['json']['ok'] ?? false), 'actual kingdom HTTP action can remove a member with an expedition army away');
    $former = httpRaid($member, $id);
    check($former['my_contribution'] === 10 && !$former['permissions']['supply'] && army($member) === 4275,
        'kicked participant keeps contribution and reserved troops but cannot issue new coalition orders');
    httpReject(httpRequest($member, '/api/expeditions/action', ['action' => 'supply', 'expedition_id' => $id, 'amount' => 100]), 403, 'NOT_IN_COALITION', 'kicked member cannot spend into an old alliance coalition');
    check(httpRaid($host, $id)['missions'][0]['status'] === 'marching' && army($host) === 4050, 'HTTP dispatch reserves actual city troops and reports the march timer');
    httpUntil(fn () => httpRaid($host, $id)['phase'] === 'boss', 25, 'real 20-second mission timers unlock boss without changing fixture timers');
    httpAction($host, 'dispatch', $id, ['objective' => 'boss', 'troops' => [50100101 => 900]]);
    httpAction($guest, 'dispatch', $id, ['objective' => 'boss', 'troops' => [50100101 => 900]]);
    httpUntil(fn () => httpRaid($host, $id)['phase'] === 'victory', 25, 'real HTTP two-alliance attacks defeat the boss');
    $victory = httpRaid($host, $id);
    check($victory['boss']['hp'] === 0 && count($victory['participants']) === 3 && $victory['can_claim'], 'victory exposes contribution standings and a real reward claim');
    httpAction($host, 'claim', $id);
    httpAction($guest, 'claim', $id);
    httpAction($member, 'claim', $id);
    check(httpRaid($member, $id)['reward_claimed'] && army($member) === 4500, 'kicked member keeps earned reward and receives the original army back');
    httpReject(httpRequest($host, '/api/expeditions/action', ['action' => 'claim', 'expedition_id' => $id]), 400, 'ALREADY_CLAIMED', 'HTTP duplicate reward attempt is rejected');
    httpUntil(function () use ($host, $guest, $id): bool {
        httpRaid($host, $id); httpRaid($guest, $id);
        return army($host) === 4500 && army($guest) === 4500;
    }, 25, 'real timed HTTP returns restore every troop exactly once');
    $complete = httpRaid($host, $id);
    check($complete['reward_claimed'] && !$complete['can_claim'] && count($complete['log']) >= 10, 'completed HTTP encounter retains its report and paid reward receipt');
    check((int) $db->query('SELECT COUNT(*) FROM expedition_rewards WHERE expedition_id=?', [$id])->fetchColumn() === 3, 'each actual participant receives exactly one persisted HTTP reward');
    $join = httpRequest($member, '/api/kingdom/action', ['action' => 'alliance.join', 'alliance_id' => 101]);
    check($join['status'] === 200 && ($join['json']['ok'] ?? false), 'former participant can join an alliance through actual kingdom HTTP action');
    $leave = httpRequest($member, '/api/kingdom/action', ['action' => 'alliance.leave']);
    check($leave['status'] === 200 && ($leave['json']['ok'] ?? false) && httpRaid($member, $id)['reward_claimed'], 'voluntary alliance leave also retains completed expedition history');
    echo "ALL EXPEDITION HTTP CHECKS PASSED (disposable database, real timers).\n";
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    // Resolve and verify the generated, private root before recursive cleanup.
    $resolved = realpath($serverRoot);
    $expected = realpath($tempRoot);
    if ($resolved !== false && $expected !== false
        && str_replace('\\', '/', $resolved) === str_replace('\\', '/', $expected) . '/http'
        && preg_match('/^conquer_expedition_test_[a-f0-9]{12}$/D', basename($expected))) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolved, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            if ($item->isDir() && !$item->isLink()) { rmdir($item->getPathname()); }
            else { unlink($item->getPathname()); }
        }
        rmdir($resolved);
    }
}
