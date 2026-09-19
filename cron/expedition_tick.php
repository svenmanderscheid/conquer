<?php
declare(strict_types=1);

/** Run once per minute; all arrivals and returns also settle on browser polling. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/src/Bootstrap.php';
\Conquer\Bootstrap::init(ROOT_DIR);
\Conquer\Game\Expedition\ExpeditionService::tick();
echo "Expedition arrivals, expiries and troop returns processed.\n";
