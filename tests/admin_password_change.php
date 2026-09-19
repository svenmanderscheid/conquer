<?php
declare(strict_types=1);
ob_start();
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/src/Bootstrap.php';
\Conquer\Bootstrap::init(ROOT_DIR);
require __DIR__ . '/Support/FeatureDatabase.php';

use Conquer\Auth\AdminAuth;
use Conquer\Db\Connection;

$fixture = new \ConquerTests\FeatureDatabase();
$db = Connection::getInstance();
$checks = 0;
function adminPasswordCheck(bool $condition, string $message): void {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++; echo "PASS $message\n";
}

try {
    ini_set('session.save_path', sys_get_temp_dir());
    session_name('conquer_admin_password_test');
    session_start();
    $id = AdminAuth::createAdmin('ForcedAdmin', 'Temporary-Password-2026!', 'superadmin', true);
    adminPasswordCheck(AdminAuth::login('ForcedAdmin', 'Temporary-Password-2026!'), 'temporary password authenticates');
    adminPasswordCheck(AdminAuth::mustChangePassword(), 'first login requires password change');
    try { AdminAuth::changeRequiredPassword('wrong', 'Replacement-Password-2026!'); throw new RuntimeException('wrong current password accepted'); }
    catch (DomainException) { adminPasswordCheck(true, 'wrong current password is rejected'); }
    AdminAuth::changeRequiredPassword('Temporary-Password-2026!', 'Replacement-Password-2026!');
    adminPasswordCheck(!AdminAuth::mustChangePassword(), 'successful change unlocks admin session');
    adminPasswordCheck((int)$db->query('SELECT must_change_password FROM admin_users WHERE id=?',[$id])->fetchColumn()===0, 'database requirement is cleared');
    $_SESSION=[];
    adminPasswordCheck(!AdminAuth::login('ForcedAdmin', 'Temporary-Password-2026!'), 'temporary password no longer works');
    adminPasswordCheck(AdminAuth::login('ForcedAdmin', 'Replacement-Password-2026!'), 'new password authenticates');
    adminPasswordCheck((int)$db->query("SELECT COUNT(*) FROM admin_audit_log WHERE admin_id=? AND action='admin.password_changed'",[$id])->fetchColumn()===1, 'password change is audited once');
    echo "ALL $checks ADMIN PASSWORD CHECKS PASSED\n";
} finally {
    $fixture->close();
    ob_end_flush();
}
