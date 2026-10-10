<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
ob_start();
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR . '/src'))->register();
require __DIR__ . '/Support/FeatureDatabase.php';

use Conquer\Auth\AccountService;
use Conquer\Db\Connection;

function resetDeliveryCheck(bool $ok, string $label): void
{
    if (!$ok) throw new RuntimeException($label);
    echo "PASS $label\n";
}

function resetDeliveryRejects(string $token, string $label): void
{
    try {
        AccountService::resetWithToken($token, 'Unwanted-password-2026!');
    } catch (DomainException) {
        resetDeliveryCheck(true, $label);
        return;
    }
    throw new RuntimeException($label);
}

$fixture = new \ConquerTests\FeatureDatabase();
$mailFile = tempnam(sys_get_temp_dir(), 'conquer-delivery-mail-');
$logFile = tempnam(sys_get_temp_dir(), 'conquer-delivery-log-');
define('CONQUER_TEST_MAIL_FILE', $mailFile);
require __DIR__ . '/Support/AccountMailSink.php';
try {
    $db = Connection::getInstance();
    \Conquer\Logger::init($logFile);
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    (new ReflectionProperty(\Conquer\Bootstrap::class, 'config'))->setValue(null, ['base_url' => 'https://game.example.invalid']);
    $email = 'delivery@example.invalid';
    $db->execute('INSERT INTO players(id,username,email,password_hash) VALUES(991,?,?,?)', [
        'DeliveryFixture', $email, password_hash('Original-password-2026!', PASSWORD_DEFAULT),
    ]);
    $messages = static fn(): array => array_map(
        static fn(string $line): array => json_decode($line, true, 512, JSON_THROW_ON_ERROR),
        array_values(array_filter(explode("\n", (string)file_get_contents($mailFile)))),
    );
    $mailToken = static function(int $index) use ($messages): string {
        $sent = $messages();
        if (!preg_match('/token=([a-f0-9]{64})/', $sent[$index]['body'] ?? '', $match)) {
            throw new RuntimeException('Captured reset email has no token.');
        }
        return $match[1];
    };
    $active = static fn(): array => $db->query('SELECT token_hash FROM password_reset_tokens WHERE player_id=991 AND used_at IS NULL AND expires_at>UTC_TIMESTAMP() ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    $rows = static fn(): int => (int)$db->query('SELECT COUNT(*) FROM password_reset_tokens WHERE player_id=991')->fetchColumn();

    AccountService::requestPasswordReset($email);
    $first = $mailToken(0);
    AccountService::requestPasswordReset($email);
    $second = $mailToken(1);
    resetDeliveryCheck(count($messages()) === 2 && $first !== $second, 'retry delivers a distinct recovery link');
    resetDeliveryCheck($active() === [hash('sha256', $first), hash('sha256', $second)], 'retry preserves both unexpired delivered links');
    $attempts = $GLOBALS['CONQUER_TEST_MAIL_ATTEMPTS'];
    AccountService::requestPasswordReset('absent@example.invalid');
    resetDeliveryCheck($GLOBALS['CONQUER_TEST_MAIL_ATTEMPTS'] === $attempts && $rows() === 2, 'unknown account neither contacts mail transport nor creates a token');

    AccountService::resetWithToken($first, 'Restored-from-first-2026!');
    resetDeliveryCheck(password_verify('Restored-from-first-2026!', (string)$db->query('SELECT password_hash FROM players WHERE id=991')->fetchColumn()), 'older delivered link still changes the password after retry');
    resetDeliveryCheck($active() === [], 'successful older link revokes every outstanding recovery link');
    resetDeliveryRejects($first, 'older delivered link remains single use');
    resetDeliveryRejects($second, 'newer delivered link is revoked by the completed reset');

    // Isolate transport cases from rate-limit budgets, which have separate tests.
    $db->execute('DELETE FROM security_rate_limits');
    AccountService::requestPasswordReset($email);
    $delivered = $mailToken(2);
    $beforeRows = $rows();
    $beforeMessages = count($messages());
    foreach (['return_false', 'throw'] as $failure) {
        $GLOBALS['CONQUER_TEST_MAIL_FAILURE'] = $failure;
        $attempts = $GLOBALS['CONQUER_TEST_MAIL_ATTEMPTS'];
        AccountService::requestPasswordReset($email);
        resetDeliveryCheck($GLOBALS['CONQUER_TEST_MAIL_ATTEMPTS'] === $attempts + 1 && count($messages()) === $beforeMessages, "$failure transport failure completes without exposing delivery failure or sending mail");
        resetDeliveryCheck($rows() === $beforeRows && $active() === [hash('sha256', $delivered)], "$failure transport failure removes only the new undelivered token");
    }
    $attempts = $GLOBALS['CONQUER_TEST_MAIL_ATTEMPTS'];
    AccountService::requestPasswordReset('other-absent@example.invalid');
    resetDeliveryCheck($GLOBALS['CONQUER_TEST_MAIL_ATTEMPTS'] === $attempts && $rows() === $beforeRows, 'unknown account still completes normally during transport failure');
    unset($GLOBALS['CONQUER_TEST_MAIL_FAILURE']);

    AccountService::resetWithToken($delivered, 'Restored-after-failure-2026!');
    resetDeliveryCheck(password_verify('Restored-after-failure-2026!', (string)$db->query('SELECT password_hash FROM players WHERE id=991')->fetchColumn()), 'previously delivered link works after rejected and throwing mail attempts');
    resetDeliveryRejects($delivered, 'preserved delivered link remains single use');
    resetDeliveryCheck($active() === [], 'completed recovery leaves no active reset links');
    echo "ALL PASSWORD RESET DELIVERY CHECKS PASSED\n";
} finally {
    unset($GLOBALS['CONQUER_TEST_MAIL_FAILURE'], $GLOBALS['CONQUER_TEST_MAIL_ATTEMPTS']);
    $fixture->close();
    foreach ([$mailFile, $logFile] as $file) if (is_file($file)) unlink($file);
}
