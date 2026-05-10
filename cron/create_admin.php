<?php
declare(strict_types=1);

/**
 * One-time CLI script to create the initial admin user.
 *
 * Usage:
 *   php cron/create_admin.php <username> <password> [role]
 *
 * Roles: superadmin, moderator (default: superadmin)
 *
 * Example:
 *   php cron/create_admin.php ekki secret superadmin
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script must be run from the command line.' . PHP_EOL);
}

define('ROOT_DIR', dirname(__DIR__));
require_once ROOT_DIR . '/src/Bootstrap.php';
\Conquer\Bootstrap::init(ROOT_DIR);

$args = $argv ?? [];

if (count($args) < 3) {
    echo 'Usage: php cron/create_admin.php <username> <password> [role]' . PHP_EOL;
    echo 'Roles: superadmin, moderator (default: superadmin)' . PHP_EOL;
    exit(1);
}

$username = trim($args[1]);
$password = $args[2];
$role     = $args[3] ?? 'superadmin';

if ($username === '' || strlen($password) < 8) {
    echo 'Error: username must be non-empty, password must be at least 8 characters.' . PHP_EOL;
    exit(1);
}

if (!in_array($role, ['superadmin', 'moderator'], true)) {
    echo 'Error: role must be superadmin or moderator.' . PHP_EOL;
    exit(1);
}

try {
    $id = \Conquer\Auth\AdminAuth::createAdmin($username, $password, $role);
    echo "Admin created: {$username} (id={$id}, role={$role})" . PHP_EOL;
} catch (\Throwable $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
