#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * CLI helper — create an admin_users record.
 *
 * Usage (run from repo root):
 *   php bin/create_admin.php <username> <password> [superadmin|moderator]
 *
 * Example:
 *   php bin/create_admin.php ekki secret123 superadmin
 */

define('ROOT_DIR', dirname(__DIR__));

require_once ROOT_DIR . '/src/Bootstrap.php';
\Conquer\Bootstrap::init(ROOT_DIR);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

$username = $argv[1] ?? null;
$password = $argv[2] ?? null;
$role     = $argv[3] ?? 'moderator';

if ($username === null || $password === null) {
    fwrite(STDERR, "Usage: php bin/create_admin.php <username> <password> [superadmin|moderator]\n");
    exit(1);
}

if (!in_array($role, ['superadmin', 'moderator'], true)) {
    fwrite(STDERR, "Role must be 'superadmin' or 'moderator'.\n");
    exit(1);
}

try {
    $id = \Conquer\Auth\AdminAuth::createAdmin($username, $password, $role);
    echo "Admin created successfully.\n";
    echo "  ID       : {$id}\n";
    echo "  Username : {$username}\n";
    echo "  Role     : {$role}\n";
} catch (\Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}
