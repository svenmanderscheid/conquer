<?php
declare(strict_types=1);
/** Use the same settlement path as the browser, including per-player locks. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/src/Bootstrap.php';
\Conquer\Bootstrap::init(ROOT_DIR);
$db = \Conquer\Db\Connection::getInstance();
$players = $db->query("SELECT DISTINCT player_id FROM marches WHERE state IN ('marching','returning','resolving','arrived')")->fetchAll(PDO::FETCH_COLUMN);
foreach ($players as $playerId) { \Conquer\Game\March\MarchTick::runForPlayer((int) $playerId); }
\Conquer\Game\Expedition\ExpeditionService::tick();
\Conquer\Game\Dungeon\DungeonService::tick();
\Conquer\Game\Rally\RallyService::tick();
\Conquer\Game\Shrine\CongressService::tick();
\Conquer\Game\Community\CommunityService::tick();
\Conquer\Game\Conquest\EventService::tick();

echo count($players) . " player march queues processed.\n";
