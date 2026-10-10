<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Bootstrap.php';
\Conquer\Bootstrap::init(ROOT_DIR);
$days=max(7,min(365,(int)($argv[1]??30)));
$removed=\Conquer\Observability\EventLog::prune($days,1000);
echo 'Removed '.$removed.' diagnostic events older than '.$days." days (maximum 1000 per run).\n";
