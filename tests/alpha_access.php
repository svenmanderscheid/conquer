<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(1); }
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/src/Bootstrap.php';
\Conquer\Bootstrap::init(ROOT_DIR);

use Conquer\Auth\AlphaAccess;
use Conquer\Db\Connection;

$db = Connection::getInstance();
$prefix = 'Automatischer Alpha-Test ' . bin2hex(random_bytes(4));
function alphaCheck(bool $condition, string $label): void {
    if (!$condition) { throw new RuntimeException($label); }
    echo "PASS {$label}\n";
}

try {
    $key = AlphaAccess::generate($db, $prefix . ' einmalig');
    alphaCheck((bool) preg_match('/^(?:[A-F0-9]{4}-){5}[A-F0-9]{4}$/', $key), 'generated keys use the public grouped format');
    $id = $db->transaction(static fn(Connection $connection): ?int => AlphaAccess::consume($connection, $key));
    alphaCheck(is_int($id) && $id > 0, 'a valid key is atomically consumed');
    try {
        $db->transaction(static fn(Connection $connection): ?int => AlphaAccess::consume($connection, $key));
        throw new RuntimeException('used key was accepted twice');
    } catch (DomainException) {
        alphaCheck(true, 'a single-use key cannot be reused');
    }

    $expired = AlphaAccess::generate($db, $prefix . ' abgelaufen', 1, '2020-01-01 00:00:00');
    try {
        $db->transaction(static fn(Connection $connection): ?int => AlphaAccess::consume($connection, $expired));
        throw new RuntimeException('expired key was accepted');
    } catch (DomainException) {
        alphaCheck(true, 'expired keys are rejected');
    }

    try {
        $db->transaction(static fn(Connection $connection): ?int => AlphaAccess::consume($connection, 'AAAA-BBBB-CCCC-DDDD-EEEE-FFFF'));
        throw new RuntimeException('unknown key was accepted');
    } catch (DomainException) {
        alphaCheck(true, 'unknown keys return the generic rejection');
    }
    echo "ALL ALPHA ACCESS CHECKS PASSED\n";
} finally {
    $db->execute('DELETE FROM alpha_access_keys WHERE label LIKE ?', [$prefix . '%']);
}
