<?php
declare(strict_types=1);

/**
 * HTTP checks for one school's training flow, including concurrent requests.
 * Creates its own test account in the isolated playtest; never edits saved games
 * or database timers. Run after syncing the implementation to that installation.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }

$base = rtrim($argv[1] ?? 'http://localhost/conquer-3d-playtest', '/');
$url = parse_url($base);
if (!$url || ($url['scheme'] ?? '') !== 'http'
    || !in_array($url['host'] ?? '', ['localhost', '127.0.0.1'], true)
    || ($url['path'] ?? '') !== '/conquer-3d-playtest'
    || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment'])) {
    fwrite(STDERR, "Only the isolated local /conquer-3d-playtest installation is allowed.\n");
    exit(1);
}

$jars = [];
$exitCode = 0;

function trainingCheck(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
    echo 'PASS ' . $message . "\n";
}

function trainingRequest(int $session, string $path, array|string|null $body = null,
    string $csrf = '', bool $form = false): array
{
    global $base, $jars;
    $handle = curl_init($base . $path);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEFILE => $jars[$session],
        CURLOPT_COOKIEJAR => $jars[$session],
        CURLOPT_TIMEOUT => 15,
    ]);
    if ($body !== null) {
        $payload = is_string($body) ? $body : ($form ? http_build_query($body)
            : json_encode($body, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $form ? [] : ['Content-Type: application/json', 'X-CSRF-Token: ' . $csrf],
        ]);
    }
    $text = curl_exec($handle);
    if ($text === false) { throw new RuntimeException('HTTP request failed: ' . curl_error($handle)); }
    $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    curl_close($handle);
    return ['status' => $status, 'text' => $text, 'json' => json_decode($text, true)];
}

function trainingSignIn(int $session, string $name, string $password, string $mode): void
{
    $page = trainingRequest($session, '/');
    preg_match('/name="csrf" value="([a-f0-9]+)"/', $page['text'], $match);
    trainingCheck(!empty($match[1]), 'login form token is available');
    $result = trainingRequest($session, '/auth/local', [
        'username' => $name, 'password' => $password, 'mode' => $mode,
        'alpha_key' => $mode === 'register' ? 'LOCAL-ALPHA-ACCESS-2026-KEY1' : '', 'csrf' => $match[1],
    ], '', true);
    trainingCheck($result['status'] === 302, 'test account ' . $mode . ' succeeds');
}

function trainingState(int $session): array
{
    $result = trainingRequest($session, '/api/city3d/state');
    trainingCheck($result['status'] === 200 && ($result['json']['ok'] ?? false), '3D state loads');
    return $result['json']['data'];
}

function trainingRejected(array $result, int $status, string $code, string $label): void
{
    trainingCheck($result['status'] === $status && ($result['json']['ok'] ?? null) === false
        && ($result['json']['error']['code'] ?? '') === $code
        && ($result['json']['error']['message'] ?? '') !== '', $label);
}

/** Two independent login sessions must not buy two jobs for the same slot. */
function trainingConcurrent(array $body, array $tokens): array
{
    global $base, $jars;
    $multi = curl_multi_init();
    $handles = [];
    foreach ($tokens as $session => $token) {
        $handle = curl_init($base . '/api/troops/train');
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body, JSON_THROW_ON_ERROR),
            CURLOPT_COOKIEFILE => $jars[$session], CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-CSRF-Token: ' . $token],
        ]);
        curl_multi_add_handle($multi, $handle);
        $handles[] = $handle;
    }
    do {
        $result = curl_multi_exec($multi, $active);
        if ($result !== CURLM_OK) { throw new RuntimeException('Concurrent HTTP requests failed.'); }
        if ($active) { curl_multi_select($multi, 0.2); }
    } while ($active);
    $statuses = [];
    foreach ($handles as $handle) {
        $statuses[] = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($multi, $handle);
        curl_close($handle);
    }
    curl_multi_close($multi);
    sort($statuses);
    return $statuses;
}

try {
    for ($i = 0; $i < 2; $i++) {
        $jar = tempnam(sys_get_temp_dir(), 'conquer-training-');
        if ($jar === false) { throw new RuntimeException('Could not create a temporary cookie jar.'); }
        $jars[] = $jar;
    }
    $body = ['troop_code' => 50200101, 'count' => 4];
    trainingRejected(trainingRequest(0, '/api/troops/train', $body), 401, 'UNAUTHENTICATED',
        'anonymous training is rejected');

    $name = 'Train3D' . bin2hex(random_bytes(6));
    $password = bin2hex(random_bytes(16));
    trainingSignIn(0, $name, $password, 'register');
    trainingSignIn(1, $name, $password, 'login');
    $initial = trainingState(0);
    $other = trainingState(1);
    trainingCheck($initial['city']['id'] === $other['city']['id'], 'two sessions share the new test city');
    trainingCheck(!$initial['troop_queue'] && array_sum($initial['troops']) === 0,
        'new test city has no troops or training jobs');
    $csrf = $initial['player']['csrf'];
    trainingRejected(trainingRequest(0, '/api/troops/train', $body), 403, 'CSRF_INVALID',
        'missing CSRF is rejected');
    trainingRejected(trainingRequest(0, '/api/troops/train', $body, 'invalid-token'), 403, 'CSRF_INVALID',
        'incorrect CSRF is rejected');

    foreach (['{', 'null', '[]', 'true', '1', '"text"', '{}'] as $raw) {
        trainingRejected(trainingRequest(0, '/api/troops/train', $raw, $csrf), 400, 'INVALID_INPUT',
            'invalid request object is rejected: ' . $raw);
    }
    $invalid = [
        'troop_code' => ['50200101', 50200101.0, 50200101.5, true, null, [], 0, -1, 99999999],
        'count' => ['4', 4.0, 4.5, true, null, [], 0, -1, 50001, PHP_INT_MAX],
        'barrack_slot' => ['2', 2.0, 2.5, true, null, [], 0, -1, 1, 3],
    ];
    foreach ($invalid as $field => $values) {
        foreach ($values as $value) {
            $invalidBody = $body;
            $invalidBody[$field] = $value;
            trainingRejected(trainingRequest(0, '/api/troops/train', $invalidBody, $csrf),
                400, 'INVALID_INPUT', 'invalid ' . $field . ' rejected: '
                    . json_encode($value, JSON_PRESERVE_ZERO_FRACTION));
        }
    }
    foreach (['troop_code', 'count'] as $field) {
        $missing = $body;
        unset($missing[$field]);
        trainingRejected(trainingRequest(0, '/api/troops/train', $missing, $csrf),
            400, 'INVALID_INPUT', 'missing ' . $field . ' is rejected');
    }
    trainingRejected(trainingRequest(0, '/api/troops/train', ['troop_code' => 50200201, 'count' => 1], $csrf),
        400, 'TRAIN_FAILED', 'locked academy tier is rejected');
    trainingRejected(trainingRequest(0, '/api/troops/train', ['troop_code' => 50200101, 'count' => 50000], $csrf),
        400, 'TRAIN_FAILED', 'valid upper count boundary still requires sufficient resources');
    $before = trainingState(0);
    trainingCheck(!$before['troop_queue'] && array_sum($before['troops']) === 0,
        'rejected requests created no troops or jobs');
    foreach ($initial['resources'] as $resource => $amount) {
        trainingCheck($before['resources'][$resource] >= $amount, 'rejected requests spent no ' . $resource);
    }

    // No barrack_slot retains compatibility with the existing 2D clients.
    // Client-supplied price, duration and city fields cannot override server rules.
    $statuses = trainingConcurrent($body + ['cost' => 0, 'seconds' => 0, 'city_id' => 0],
        [$csrf, $other['player']['csrf']]);
    trainingCheck($statuses === [200, 400], 'simultaneous training produces one success and one occupied-slot rejection');
    $queued = trainingState(1);
    trainingCheck(count($queued['troop_queue']) === 1, 'one saved job survives reload in another session');
    $job = $queued['troop_queue'][0];
    trainingCheck((int) $job['troop_code'] === $body['troop_code'] && (int) $job['count'] === $body['count']
        && (int) $job['barrack_slot'] === 2, 'saved archer job uses its own school');
    $definitions = array_column($before['troop_defs'], null, 'code');
    $definition = $definitions[$body['troop_code']];
    $duration = strtotime($job['finishes_at'] . ' UTC') - strtotime($job['started_at'] . ' UTC');
    trainingCheck($duration === (int) $definition['time'] * $body['count'] && $duration > 0,
        'server uses the real training duration');
    $producers = ['food' => 'farm', 'lumber' => 'lumber_camp', 'stone' => 'quarry', 'gold' => 'gold_mine'];
    foreach ($producers as $resource => $producer) {
        $cost = (int) $definition['need_' . $resource] * $body['count'];
        $spent = $before['resources'][$resource] - $queued['resources'][$resource];
        $elapsed = max(0, $queued['server_time'] - $before['server_time']) + 2;
        $productionAllowance = (int) ceil($before['production_rates'][$producer] * $elapsed / 3600) + 1;
        trainingCheck($spent <= $cost && $spent >= $cost - $productionAllowance,
            'exact ' . $resource . ' cost is charged once, allowing only elapsed production');
    }
    trainingRejected(trainingRequest(1, '/api/troops/train', $body + ['barrack_slot' => 2], $other['player']['csrf']),
        400, 'TRAIN_FAILED', 'occupied explicit slot is rejected after reload');
    $reload = trainingState(0);
    trainingCheck(count($reload['troop_queue']) === 1 && (int) $reload['troop_queue'][0]['id'] === (int) $job['id'],
        'rejected repeat does not replace or duplicate the saved job');

    echo "Waiting for real training completion with no browser requests.\n";
    $finish = strtotime($job['finishes_at'] . ' UTC');
    while (time() <= $finish) { sleep(1); }
    $done = trainingState(1);
    trainingCheck(!$done['troop_queue'] && (int) ($done['troops'][$body['troop_code']] ?? 0) === $body['count'],
        'offline completion frees the slot and credits exactly the trained count');
    $completed = array_values(array_filter($done['recent_training'] ?? [],
        static fn(array $entry): bool => (int) $entry['id'] === (int) $job['id']));
    trainingCheck(count($completed) === 1 && (int) $completed[0]['count'] === $body['count']
        && (int) $completed[0]['troop_code'] === $body['troop_code'],
        'confirmed history records the completed job ID and count');
    $again = trainingState(0);
    trainingCheck($again['troops'] === $done['troops'] && !$again['troop_queue']
        && ($again['recent_training'] ?? []) === $done['recent_training'],
        'a second state request does not credit troops or history twice');
    $listed = trainingRequest(0, '/api/troops/list');
    trainingCheck($listed['status'] === 200 && ($listed['json']['data']['troops'] ?? null) === $done['troops']
        && ($listed['json']['data']['troop_queue'] ?? null) === [], 'legacy troop status agrees with 3D completion');
    echo 'ALL TRAINING CHECKS PASSED (' . $name . ").\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL ' . $error->getMessage() . "\n");
    $exitCode = 1;
} finally {
    foreach ($jars as $jar) { if (is_file($jar)) { unlink($jar); } }
}
exit($exitCode);
