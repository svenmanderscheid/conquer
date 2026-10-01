<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR . '/src'))->register();
require __DIR__ . '/Support/FeatureDatabase.php';
require __DIR__ . '/Support/HttpApp.php';
use ConquerTests\HttpApp;
function entryAssert(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
    echo "PASS $label\n";
}
$fixture = new \ConquerTests\FeatureDatabase();
try {
    $base = $fixture->serve(HttpApp::source());
    foreach (['unionofkingdoms.com', 'www.unionofkingdoms.com'] as $host) {
        $headers = ['Host: ' . $host];
        $home = HttpApp::request($base, '/', 'GET', $headers);
        entryAssert($home['status'] === 200 && str_contains($home['body'], 'id="waitlist-form"') && !str_contains($home['body'], 'name="password"'), "$host serves only the waitlist, without a hidden credential form");
        entryAssert(str_contains($home['body'], 'href="https://play.unionofkingdoms.com/"'), 'website links to game login');
        foreach (['/?zugang=login' => '/', '/?mode=register' => '/?mode=register', '/auth/recover?backup=1' => '/auth/recover?backup=1', '/auth/reset?token=abc&redirect=https://evil.invalid' => '/auth/reset?token=abc', '/auth/google/callback?code=secret&state=secret' => '/'] as $path => $target) {
            $r = HttpApp::request($base, $path, 'GET', $headers);
            entryAssert($r['status'] === 303 && in_array('location: https://play.unionofkingdoms.com' . $target, $r['headers'], true), "legacy route $path uses the fixed game origin");
        }
        $r = HttpApp::request($base, '/auth/local', 'POST', [...$headers, 'Content-Type: application/x-www-form-urlencoded'], 'mode=register&username=Unwanted&password=secret&alpha_key=secret');
        entryAssert($r['status'] === 303 && in_array('location: https://play.unionofkingdoms.com/?mode=register', $r['headers'], true), 'old credential POST is dropped instead of replayed or reflected');
        entryAssert((int)\Conquer\Db\Connection::getInstance()->query('SELECT COUNT(*) FROM players')->fetchColumn() === 0, 'website POST does not create a player');
    }
    foreach (['play.unionofkingdoms.com', 'localhost', '127.0.0.1'] as $host) {
        $r = HttpApp::request($base, '/', 'GET', ['Host: ' . $host]);
        entryAssert($r['status'] === 200 && str_contains($r['body'], 'name="identifier"') && !str_contains($r['body'], 'id="waitlist-form"'), "$host opens the game login");
        entryAssert(in_array('cache-control: private, no-store', $r['headers'], true), 'session-bound entry cannot be shared-cache stored');
        $r = HttpApp::request($base, '/?mode=register', 'GET', ['Host: ' . $host]);
        entryAssert(str_contains($r['body'], 'name="alpha_key"') && str_contains($r['body'], 'name="email"'), 'game registration retains alpha-key and email fields');
        $r = HttpApp::request($base, '/auth/local', 'POST', ['Host: ' . $host, 'Content-Type: application/x-www-form-urlencoded'], 'mode=login&identifier=Nobody&password=secret');
        entryAssert($r['status'] === 200 && str_contains($r['body'], 'class="play-error"') && !str_contains($r['body'], 'id="waitlist-form"'), 'missing CSRF returns the game error screen');
    }
} finally { $fixture->close(); }
