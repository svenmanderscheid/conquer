<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
define('ROOT_DIR', dirname(__DIR__, 2));
define('APP_BASE', '/conquer');
require ROOT_DIR . '/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR . '/src'))->register();
$_SESSION = ['login_csrf' => 'synthetic-layout-token'];
$_GET['mode'] = ($argv[1] ?? '') === 'register' ? 'register' : 'login';
$landingCspNonce = 'login-layout-test';
require ROOT_DIR . '/views/play_login.php';
