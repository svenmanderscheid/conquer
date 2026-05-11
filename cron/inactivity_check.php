<?php
declare(strict_types=1);

/**
 * Inactivity Check — runs once daily via cron.
 *
 * Cron line (add via Hostinger panel):
 *   0 3 * * *  php /path/to/conquer/cron/inactivity_check.php
 *
 * What it does:
 *   1. Finds players inactive for >30 days (last_active_at older than 30 days)
 *   2. Sets players.is_hidden = 1
 *   3. Sets cities.is_hidden = 1 for those players
 *
 * On next login, OAuth::restoreHiddenCityOnLogin() reverses the effect.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Inactivity check must be executed from the command line.' . PHP_EOL);
}

define('ROOT_DIR', dirname(__DIR__));
require_once ROOT_DIR . '/src/Bootstrap.php';
\Conquer\Bootstrap::init(ROOT_DIR);

$db  = \Conquer\Db\Connection::getInstance();
$log = \Conquer\Logger::getInstance();

// ---------------------------------------------------------------------------
// Find inactive player IDs (not logged in for >30 days, not already hidden)
// ---------------------------------------------------------------------------

try {
    $inactivePlayers = $db->query(
        "SELECT id FROM players
         WHERE  is_hidden         = 0
           AND  last_active_at    < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)
           AND  last_active_at IS NOT NULL",
        [],
    )->fetchAll();
} catch (\PDOException $e) {
    $log->error('inactivity_check: query failed: ' . $e->getMessage());
    exit(1);
}

if (empty($inactivePlayers)) {
    $log->info('inactivity_check: no inactive players found.');
    echo "No inactive players.\n";
    exit(0);
}

$playerIds   = array_column($inactivePlayers, 'id');
$count       = count($playerIds);
$placeholders = implode(',', array_fill(0, $count, '?'));

// ---------------------------------------------------------------------------
// Hide players + their cities in two UPDATE statements
// ---------------------------------------------------------------------------

try {
    $db->execute(
        "UPDATE players SET is_hidden = 1 WHERE id IN ({$placeholders})",
        $playerIds,
    );

    $db->execute(
        "UPDATE cities SET is_hidden = 1
         WHERE player_id IN ({$placeholders}) AND world_id = 1",
        $playerIds,
    );
} catch (\PDOException $e) {
    $log->error('inactivity_check: UPDATE failed: ' . $e->getMessage());
    exit(1);
}

$log->info("inactivity_check: {$count} players hidden due to 30+ day inactivity.");
echo "Done. {$count} players hidden.\n";
