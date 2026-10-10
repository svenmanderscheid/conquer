<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR . '/src'))->register();
require __DIR__ . '/Support/FeatureDatabase.php';
require __DIR__ . '/Support/HttpApp.php';

use Conquer\Db\Connection;
use ConquerTests\HttpApp;

function resetHttpCheck(bool $ok, string $label): void
{
    if (!$ok) throw new RuntimeException($label);
    echo "PASS $label\n";
}

function resetHttpField(string $html, string $name): string
{
    if (!preg_match('/<input\b[^>]*name="' . preg_quote($name, '/') . '"[^>]*value="([^"]*)"/i', $html, $match)) {
        throw new RuntimeException('Expected recovery form field is missing: ' . $name);
    }
    return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

$fixture = new \ConquerTests\FeatureDatabase();
$mailFile = tempnam(sys_get_temp_dir(), 'conquer-reset-mail-');
$cookieFile = tempnam(sys_get_temp_dir(), 'conquer-reset-cookies-');
try {
    $db = Connection::getInstance();
    $oldPassword = 'Original-password-2026!';
    $newPassword = 'Restored-password-2026!';
    $email = 'reset-http@example.invalid';
    $db->execute('INSERT INTO players(id,username,email,password_hash) VALUES(991,?,?,?)', [
        'ResetHttpFixture', $email, password_hash($oldPassword, PASSWORD_DEFAULT),
    ]);
    $oldSession = bin2hex(random_bytes(32));
    $db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,user_agent,expires_at,active_world_id) VALUES(991,?,?,'127.0.0.1','reset fixture',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),1)", [
        $oldSession, bin2hex(random_bytes(32)),
    ]);
    // The real front controller runs against a disposable database. Every email
    // is captured by the namespace sink; this test cannot send production mail.
    $base = $fixture->serve(HttpApp::source(['base_url' => 'https://game.example.invalid'], $mailFile));
    $get = static fn(string $path): array => HttpApp::request($base, $path, 'GET', [], null, $cookieFile);
    $post = static fn(string $path, array $body): array => HttpApp::request(
        $base, $path, 'POST', ['Content-Type: application/x-www-form-urlencoded'], http_build_query($body), $cookieFile,
    );
    $messages = static fn(): array => array_map(
        static fn(string $line): array => json_decode($line, true, 512, JSON_THROW_ON_ERROR),
        array_values(array_filter(explode("\n", (string)file_get_contents($mailFile)))),
    );
    $passwordHash = static fn(): string => (string)$db->query('SELECT password_hash FROM players WHERE id=991')->fetchColumn();

    $recover = $get('/auth/recover');
    resetHttpCheck($recover['status'] === 200 && str_contains($recover['body'], 'name="email"'), 'recovery page opens anonymously');
    $csrf = resetHttpField($recover['body'], 'csrf');
    $rejected = $post('/auth/recover', ['recovery_mode' => 'email', 'email' => $email, 'csrf' => 'invalid']);
    resetHttpCheck($rejected['status'] === 200 && str_contains($rejected['body'], 'name="email"') && count($messages()) === 0, 'invalid request CSRF does not send mail');
    resetHttpCheck((int)$db->query('SELECT COUNT(*) FROM password_reset_tokens')->fetchColumn() === 0, 'invalid request CSRF creates no recovery token');

    $requested = $post('/auth/recover', ['recovery_mode' => 'email', 'email' => strtoupper($email), 'csrf' => $csrf]);
    $sent = $messages();
    resetHttpCheck($requested['status'] === 200 && !str_contains($requested['body'], 'name="email"') && count($sent) === 1, 'known email receives one captured recovery message');
    resetHttpCheck($sent[0]['to'] === $email, 'case-insensitive email lookup selects the correct recipient');
    resetHttpCheck((bool)preg_match('~https://game\.example\.invalid/auth/reset\?token=([a-f0-9]{64})~', $sent[0]['body'], $link), 'mail links to the configured public recovery origin');
    $token = $link[1];
    $resetPath = '/auth/reset?token=' . rawurlencode($token);
    $unknown = $post('/auth/recover', ['recovery_mode' => 'email', 'email' => 'absent@example.invalid', 'csrf' => $csrf]);
    resetHttpCheck($unknown['status'] === $requested['status'] && $unknown['body'] === $requested['body'] && count($messages()) === 1, 'unknown email gets the same response without sending mail');

    $reset = $get($resetPath);
    resetHttpCheck($reset['status'] === 200 && resetHttpField($reset['body'], 'token') === $token, 'mail link opens the password form and retains its token');
    $resetCsrf = resetHttpField($reset['body'], 'csrf');
    $rejected = $post($resetPath, ['token' => $token, 'csrf' => 'invalid', 'new_password' => $newPassword]);
    resetHttpCheck($rejected['status'] === 200 && str_contains($rejected['body'], 'name="new_password"') && password_verify($oldPassword, $passwordHash()), 'invalid reset CSRF preserves the old password');
    resetHttpCheck((int)$db->query('SELECT COUNT(*) FROM password_reset_tokens WHERE used_at IS NULL')->fetchColumn() === 1, 'invalid reset CSRF does not consume the link');

    $saved = $post($resetPath, ['token' => $token, 'csrf' => $resetCsrf, 'new_password' => $newPassword]);
    resetHttpCheck($saved['status'] === 200 && !str_contains($saved['body'], 'name="new_password"') && password_verify($newPassword, $passwordHash()), 'submitting the mail token saves the new password');
    resetHttpCheck((int)$db->query('SELECT COUNT(*) FROM sessions WHERE player_id=991')->fetchColumn() === 0, 'password reset revokes pre-existing player sessions');
    resetHttpCheck((bool)$db->query('SELECT email_verified_at FROM players WHERE id=991')->fetchColumn(), 'successful email recovery verifies the mailbox');
    $oldSessionRead = HttpApp::request($base, '/api/auth/me', 'GET', ['Cookie: conquer_session=' . $oldSession]);
    resetHttpCheck($oldSessionRead['status'] === 401, 'revoked game session cannot read the authenticated API');

    $reused = $post($resetPath, ['token' => $token, 'csrf' => $resetCsrf, 'new_password' => 'Unwanted-password-2026!']);
    resetHttpCheck($reused['status'] === 200 && str_contains($reused['body'], 'name="new_password"') && password_verify($newPassword, $passwordHash()), 'used recovery link cannot change the password again');
    $expiredToken = bin2hex(random_bytes(32));
    $db->execute('INSERT INTO password_reset_tokens(player_id,token_hash,expires_at) VALUES(991,?,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 MINUTE))', [hash('sha256', $expiredToken)]);
    $expiredPage = $get('/auth/reset?token=' . rawurlencode($expiredToken));
    $expired = $post('/auth/reset', ['token' => $expiredToken, 'csrf' => resetHttpField($expiredPage['body'], 'csrf'), 'new_password' => 'Unwanted-password-2026!']);
    resetHttpCheck($expired['status'] === 200 && str_contains($expired['body'], 'name="new_password"') && password_verify($newPassword, $passwordHash()), 'expired recovery link cannot change the password');

    $login = $get('/');
    $loginCsrf = resetHttpField($login['body'], 'csrf');
    $oldLogin = $post('/auth/local', ['mode' => 'login', 'identifier' => $email, 'password' => $oldPassword, 'csrf' => $loginCsrf]);
    resetHttpCheck($oldLogin['status'] === 200 && str_contains($oldLogin['body'], 'class="play-error"'), 'old password is rejected by the real login endpoint');
    $newLogin = $post('/auth/local', ['mode' => 'login', 'identifier' => $email, 'password' => $newPassword, 'csrf' => $loginCsrf]);
    resetHttpCheck(in_array($newLogin['status'], [302, 303], true) && in_array('location: /city', $newLogin['headers'], true), 'new password signs in through the real login endpoint');
    $current = $get('/api/auth/me');
    resetHttpCheck($current['status'] === 200 && ($current['json']['data']['player_id'] ?? null) === 991, 'new login cookie authenticates the recovered account');
    echo "ALL PASSWORD RESET HTTP CHECKS PASSED\n";
} finally {
    $fixture->close();
    foreach ([$mailFile, $cookieFile] as $file) if (is_file($file)) unlink($file);
}
