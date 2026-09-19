<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(1); }
$base = rtrim($argv[1] ?? 'http://localhost/conquer', '/');
if (!in_array(parse_url($base, PHP_URL_HOST), ['localhost', '127.0.0.1'], true)) { exit("Local hosts only.\n"); }
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/src/Bootstrap.php';
\Conquer\Bootstrap::init(ROOT_DIR);

use Conquer\Auth\AlphaAccess;
use Conquer\Db\Connection;

$db = Connection::getInstance();
$jars = [tempnam(sys_get_temp_dir(), 'alpha-a-'), tempnam(sys_get_temp_dir(), 'alpha-b-'), tempnam(sys_get_temp_dir(), 'alpha-c-')];
$name = 'AlphaAuto' . bin2hex(random_bytes(4));
$password = bin2hex(random_bytes(16));
$label = 'HTTP Alpha-Test ' . $name;
$key = AlphaAccess::generate($db, $label, 1);
$db->execute("DELETE FROM login_attempts WHERE ip_address IN ('127.0.0.1','::1')");
$failure = null;

function alphaRequest(int $session, string $path, ?array $form = null): array
{
    global $base, $jars;
    $handle = curl_init($base . $path);
    curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jars[$session], CURLOPT_COOKIEFILE => $jars[$session], CURLOPT_TIMEOUT => 20]);
    if ($form !== null) { curl_setopt_array($handle, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($form)]); }
    $text = curl_exec($handle);
    if ($text === false) { throw new RuntimeException(curl_error($handle)); }
    $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    curl_close($handle);
    return ['status' => $status, 'text' => $text];
}

function alphaCsrf(int $session): string
{
    $page = alphaRequest($session, '/');
    preg_match('/name="csrf" value="([a-f0-9]+)"/', $page['text'], $match);
    return $match[1] ?? '';
}

function alphaAssert(bool $condition, string $label): void
{
    if (!$condition) { throw new RuntimeException($label); }
    echo "PASS {$label}\n";
}

try {
    $csrf = alphaCsrf(0);
    alphaAssert($csrf !== '', 'public page provides a registration CSRF token');
    $created = alphaRequest(0, '/auth/local', ['mode' => 'register', 'alpha_key' => $key, 'username' => $name, 'password' => $password, 'csrf' => $csrf]);
    alphaAssert($created['status'] === 302, 'valid single-use key creates an account');
    alphaAssert((int) $db->query('SELECT uses_count FROM alpha_access_keys WHERE label=?', [$label])->fetchColumn() === 1, 'successful registration consumes the key exactly once');

    $reuse = alphaRequest(1, '/auth/local', ['mode' => 'register', 'alpha_key' => $key, 'username' => $name . 'X', 'password' => $password, 'csrf' => alphaCsrf(1)]);
    alphaAssert($reuse['status'] === 200 && str_contains($reuse['text'], 'ungültig oder nicht mehr verfügbar'), 'used key cannot create a second account');

    $login = alphaRequest(2, '/auth/local', ['mode' => 'login', 'username' => $name, 'password' => $password, 'csrf' => alphaCsrf(2)]);
    alphaAssert($login['status'] === 302, 'invited player signs in later without resubmitting the key');
    echo "ALL ALPHA REGISTRATION CHECKS PASSED\n";
} catch (Throwable $e) {
    $failure = $e;
} finally {
    $playerId = $db->query('SELECT id FROM players WHERE username IN (?,?)', [$name, $name . 'X'])->fetchAll(PDO::FETCH_COLUMN);
    foreach ($playerId as $id) {
        $db->execute('DELETE FROM cities WHERE player_id=?', [(int) $id]);
        $db->execute('DELETE FROM players WHERE id=?', [(int) $id]);
    }
    $db->execute('DELETE FROM alpha_access_keys WHERE label=?', [$label]);
    foreach ($jars as $jar) { if (is_file($jar)) { unlink($jar); } }
}
if ($failure !== null) {
    fwrite(STDERR, 'FAIL ' . $failure->getMessage() . PHP_EOL);
    exit(1);
}
