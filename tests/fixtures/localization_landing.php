<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__,2));define('APP_BASE','');
require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
(new \ReflectionProperty(\Conquer\Bootstrap::class,'config'))->setValue(null,['open_alpha'=>($argv[1]??'')==='open']);
$_SERVER['HTTP_HOST']='127.0.0.1';$_SESSION=['login_csrf'=>'synthetic-localization-token'];
$landingCspNonce='localization-test';$loginError='';$waitlistSuccess=false;
if(\Conquer\Db\Connection::isInitialized())throw new RuntimeException('Unexpected database initialization');
require ROOT_DIR.'/views/welcome.php';
if(\Conquer\Db\Connection::isInitialized())throw new RuntimeException('Unexpected database initialization');
