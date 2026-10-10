<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Bootstrap.php';
\Conquer\Bootstrap::init(ROOT_DIR);
$result = \Conquer\Game\Notification\PushWorker::run();
echo json_encode($result,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES).PHP_EOL;
exit(($result['available'] ?? true) ? 0 : 2);
