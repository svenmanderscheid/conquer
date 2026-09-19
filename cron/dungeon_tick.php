<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Bootstrap.php';
\Conquer\Bootstrap::init(ROOT_DIR);
\Conquer\Game\Dungeon\DungeonService::tick();
echo "Dungeon decisions, battles and troop returns processed.\n";
