<?php
declare(strict_types=1);

/**
 * Cron: seed_field_objects.php
 *
 * Cleans up expired field objects and spawns fresh ones for world 1.
 * Run once per day, e.g. via crontab:
 *
 *   0 0 * * * php /home/u171686647/domains/svenmanderscheid.lu/public_html/conquer/cron/seed_field_objects.php
 *
 * The script is safe to run multiple times — it only spawns on tiles that
 * are currently free, so it cannot duplicate existing live objects.
 */

require_once dirname(__DIR__) . '/src/Autoloader.php';
\Conquer\Autoloader::register();

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/app.php';

\Conquer\Bootstrap::init(dirname(__DIR__));

use Conquer\Game\Map\FieldObjectService;
use Conquer\Logger;

$log = Logger::getInstance();
$log->info('[seed_field_objects] Cron started');

// ── Step 1: Remove expired field objects that no march is currently using ──
try {
    FieldObjectService::cleanExpired();
} catch (\Throwable $e) {
    $log->error('[seed_field_objects] cleanExpired failed: ' . $e->getMessage());
    // Continue to spawning even if cleanup had an error
}

// ── Step 2: Spawn fresh field objects for world 1 ─────────────────────────
try {
    FieldObjectService::spawnObjects(worldId: 1);
} catch (\Throwable $e) {
    $log->error('[seed_field_objects] spawnObjects failed: ' . $e->getMessage());
    echo 'ERROR: spawnObjects failed — ' . $e->getMessage() . PHP_EOL;
    exit(1);
}

$log->info('[seed_field_objects] Cron finished');
echo 'Field objects seeded.' . PHP_EOL;
