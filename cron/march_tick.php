<?php
declare(strict_types=1);
/** Use the same settlement path as the browser, including per-player locks. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/src/Bootstrap.php';
\Conquer\Bootstrap::init(ROOT_DIR);
$db = \Conquer\Db\Connection::getInstance();
\Conquer\Game\Territory\TerritoryService::tick();
$players = $db->query("SELECT DISTINCT player_id FROM marches WHERE state IN ('marching','returning','resolving','arrived')")->fetchAll(PDO::FETCH_COLUMN);
foreach ($players as $playerId) { \Conquer\Game\March\MarchTick::runForPlayer((int) $playerId); }
\Conquer\Game\Expedition\ExpeditionService::tick();
\Conquer\Game\Dungeon\DungeonService::tick();
\Conquer\Game\Rally\RallyService::tick();
\Conquer\Game\Territory\TerritoryService::tick();
\Conquer\Game\Shrine\CongressService::tick();
\Conquer\Game\Community\CommunityService::tick();
foreach ($db->query('SELECT id FROM worlds')->fetchAll(PDO::FETCH_COLUMN) as $worldId) {
    if (!\Conquer\Game\World\WorldMapProfile::isLuxembourg((int)$worldId)) {
        \Conquer\Game\Conquest\EventService::tick((int)$worldId);
    }
}

echo count($players) . " player march queues processed.\n";
