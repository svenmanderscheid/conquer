<?php
declare(strict_types=1);
/** Run every minute. Persisted world intervals and UTC windows decide what is due.
 * * * * * * php /path/to/conquer/cron/world_spawn_tick.php
 */
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}
define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Bootstrap.php';
\Conquer\Bootstrap::init(ROOT_DIR);
$results=\Conquer\Game\World\WorldSpawnService::tick();
echo json_encode($results,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
exit(count(array_filter($results,static fn(array $r):bool=>$r['status']==='failed'))>0?1:0);
