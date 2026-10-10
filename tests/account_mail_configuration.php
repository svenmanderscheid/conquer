<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR . '/src'))->register();

use Conquer\Auth\AccountMailer;
use Conquer\Bootstrap;
use Conquer\Logger;

function mailConfigCheck(bool $ok, string $label): void
{
    if (!$ok) throw new RuntimeException($label);
    echo "PASS $label\n";
}

// Synthetic configuration only: never load the installation's private files.
$directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'conquer-mail-config-' . bin2hex(random_bytes(6));
mkdir($directory . '/config', 0700, true);
$mailFile = $directory . '/captured-mail.jsonl';
$logFile = $directory . '/test.log';
file_put_contents($mailFile, '');
define('CONQUER_TEST_MAIL_FILE', $mailFile);
require __DIR__ . '/Support/AccountMailSink.php';
try {
    $loadConfig = new ReflectionMethod(Bootstrap::class, 'loadConfig');
    $configProperty = new ReflectionProperty(Bootstrap::class, 'config');
    $writeConfig = static function(string $name, mixed $value) use ($directory): void {
        file_put_contents($directory . '/config/' . $name . '.php', '<?php return ' . var_export($value, true) . ';');
    };
    mailConfigCheck($loadConfig->invoke(null, $directory) === [], 'missing configuration files preserve empty defaults');
    $appConfig = [
        'env' => 'production',
        'base_url' => 'https://game.example.invalid/subpath',
        'rate_limit_per_minute' => 137,
        'paths' => ['logs' => 'synthetic-log-path'],
        'mail' => ['transport' => 'php', 'from_address' => 'old-sender@example.invalid', 'from_name' => 'Fixture Kingdom'],
    ];
    $writeConfig('app', $appConfig);
    mailConfigCheck($loadConfig->invoke(null, $directory) === $appConfig, 'absent private mail override preserves all app settings');
    $privateMail = [
        'transport' => 'hostinger',
        'from_address' => 'new-sender@example.invalid',
        'hostinger' => ['api_key' => 'SYNTHETIC-TEST-VALUE', 'domain' => 'example.invalid', 'mailbox' => 'fixture'],
    ];
    $writeConfig('mail', $privateMail);
    $loaded = $loadConfig->invoke(null, $directory);
    mailConfigCheck($loaded['mail'] === array_replace($appConfig['mail'], $privateMail), 'private mail settings override only matching mail keys');
    $unrelated = $loaded;
    unset($unrelated['mail']);
    $expectedUnrelated = $appConfig;
    unset($expectedUnrelated['mail']);
    mailConfigCheck($unrelated === $expectedUnrelated, 'mail override preserves unrelated application configuration');
    unlink($directory . '/config/app.php');
    mailConfigCheck($loadConfig->invoke(null, $directory) === ['mail' => $privateMail], 'private mail config loads independently of app config');
    $writeConfig('mail', 'malformed-test-value');
    try {
        $loadConfig->invoke(null, $directory);
        throw new LogicException('Malformed private mail config was accepted.');
    } catch (RuntimeException $error) {
        mailConfigCheck(!str_contains($error->getMessage(), 'malformed-test-value'), 'malformed private mail config fails without disclosing its value');
    }

    Logger::init($logFile);
    $mailConfig = [
        'base_url' => 'https://game.example.invalid/subpath',
        'mail' => ['from_address' => 'sender-fixture@example.invalid', 'from_name' => 'Fixture Kingdom'],
    ];
    $configProperty->setValue(null, $mailConfig);
    $recipient = 'recipient-fixture@example.invalid';
    $token = bin2hex(random_bytes(32));
    $messages = static fn(): array => array_map(
        static fn(string $line): array => json_decode($line, true, 512, JSON_THROW_ON_ERROR),
        array_values(array_filter(explode("\n", (string)file_get_contents($mailFile)))),
    );
    mailConfigCheck(AccountMailer::passwordReset($recipient, $token), 'default PHP transport delivers reset mail into the isolated sink');
    mailConfigCheck(AccountMailer::verification($recipient, $token), 'default PHP transport delivers verification mail into the isolated sink');
    $sent = $messages();
    mailConfigCheck(count($sent) === 2 && $sent[0]['to'] === $recipient && str_contains($sent[0]['body'], '/subpath/auth/reset?token=' . $token), 'reset mail preserves recipient and configured subpath');
    mailConfigCheck(str_contains($sent[1]['body'], '/subpath/auth/verify-email?token=' . $token) && str_contains($sent[1]['headers'], 'MIME-Version: 1.0'), 'verification mail has its own link and MIME headers');

    foreach (['return_false', 'throw'] as $failure) {
        $GLOBALS['CONQUER_TEST_MAIL_FAILURE'] = $failure;
        $attempts = $GLOBALS['CONQUER_TEST_MAIL_ATTEMPTS'];
        mailConfigCheck(!AccountMailer::passwordReset($recipient, $token), "$failure reset transport failure returns false");
        mailConfigCheck(!AccountMailer::verification($recipient, $token), "$failure verification transport failure returns false");
        mailConfigCheck($GLOBALS['CONQUER_TEST_MAIL_ATTEMPTS'] === $attempts + 2 && count($messages()) === 2, "$failure failures do not emit captured messages");
    }
    unset($GLOBALS['CONQUER_TEST_MAIL_FAILURE']);
    $unknown = $mailConfig;
    $unknown['mail']['transport'] = 'unsupported-fixture-transport';
    $configProperty->setValue(null, $unknown);
    $attempts = $GLOBALS['CONQUER_TEST_MAIL_ATTEMPTS'];
    mailConfigCheck(!AccountMailer::passwordReset($recipient, $token) && !AccountMailer::verification($recipient, $token), 'unknown transport fails closed for both account emails');
    mailConfigCheck($GLOBALS['CONQUER_TEST_MAIL_ATTEMPTS'] === $attempts, 'unknown transport never falls back to PHP mail');

    $configProperty->setValue(null, $mailConfig);
    foreach (['not-an-address', "victim@example.invalid\r\nBcc: injected@example.invalid"] as $invalidRecipient) {
        mailConfigCheck(!AccountMailer::passwordReset($invalidRecipient, $token), 'malformed recipient is rejected');
    }
    foreach (['not-an-address', "sender@example.invalid\r\nBcc: injected@example.invalid"] as $invalidSender) {
        $invalid = $mailConfig;
        $invalid['mail']['from_address'] = $invalidSender;
        $configProperty->setValue(null, $invalid);
        mailConfigCheck(!AccountMailer::verification($recipient, $token), 'malformed sender is rejected');
    }
    foreach (["Fixture\rBcc: injected@example.invalid", "Fixture\nBcc: injected@example.invalid"] as $invalidName) {
        $invalid = $mailConfig;
        $invalid['mail']['from_name'] = $invalidName;
        $configProperty->setValue(null, $invalid);
        mailConfigCheck(!AccountMailer::passwordReset($recipient, $token), 'header injection through sender name is rejected');
    }
    mailConfigCheck($GLOBALS['CONQUER_TEST_MAIL_ATTEMPTS'] === $attempts && count($messages()) === 2, 'invalid addresses and header names never reach mail transport');

    $log = (string)file_get_contents($logFile);
    mailConfigCheck(str_contains($log, 'Account email transport failed (RuntimeException).') && str_contains($log, 'transport configuration is invalid'), 'transport failures retain useful generic diagnostics');
    foreach ([$recipient, $mailConfig['mail']['from_address'], $token, $sent[0]['body'], $sent[1]['body'], 'Simulated account mail transport failure.', 'SYNTHETIC-TEST-VALUE'] as $privateValue) {
        mailConfigCheck(!str_contains($log, $privateValue), 'diagnostics omit message content, token, address, credentials and raw exception text');
    }
    echo "ALL ACCOUNT MAIL CONFIGURATION CHECKS PASSED\n";
} finally {
    unset($GLOBALS['CONQUER_TEST_MAIL_FAILURE'], $GLOBALS['CONQUER_TEST_MAIL_ATTEMPTS']);
    foreach (['config/app.php', 'config/mail.php', 'captured-mail.jsonl', 'test.log'] as $relative) {
        $file = $directory . '/' . $relative;
        if (is_file($file)) unlink($file);
    }
    rmdir($directory . '/config');
    rmdir($directory);
}
