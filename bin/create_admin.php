#!/usr/bin/env php
<?php
declare(strict_types=1);
/** php bin/create_admin.php <username> [superadmin|moderator|support] [--must-change] [--password-stdin] */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
define('ROOT_DIR', dirname(__DIR__));
require_once ROOT_DIR . '/src/Auth/AdminCli.php';
exit(\Conquer\Auth\AdminCli::run(array_slice($argv, 1), static function (string $username, string $password, string $role, bool $mustChange): int {
    require_once ROOT_DIR . '/src/Bootstrap.php';
    \Conquer\Bootstrap::init(ROOT_DIR);
    return \Conquer\Auth\AdminAuth::createAdmin($username, $password, $role, $mustChange);
}, STDIN, STDOUT, STDERR));
