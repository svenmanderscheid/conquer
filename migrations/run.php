<?php
declare(strict_types=1);

/**
 * Migration runner — CLI only.
 *
 * Usage:
 *   php migrations/run.php
 *
 * What it does:
 *   1. Bootstraps the app (loads DB connection)
 *   2. Ensures the `migrations` tracking table exists
 *   3. Scans migrations/ for *.sql files, sorted by filename
 *   4. Applies each file that hasn't been recorded yet — in a transaction
 *   5. Records applied migrations in the `migrations` table
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Migration runner must be executed from the command line.' . PHP_EOL);
}

define('ROOT_DIR', dirname(__DIR__));

require_once ROOT_DIR . '/src/Bootstrap.php';
\Conquer\Bootstrap::init(ROOT_DIR);

$db  = \Conquer\Db\Connection::getInstance();
$log = \Conquer\Logger::getInstance();

// ---------------------------------------------------------------------------
// 1. Ensure the migrations tracking table exists
//    (0001_create_migrations_table.sql itself creates it — we bootstrap it
//    here so the first run can track its own application)
// ---------------------------------------------------------------------------

$db->execute('
    CREATE TABLE IF NOT EXISTS migrations (
        id         BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
        filename   VARCHAR(255) NOT NULL,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY (filename)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
');

// ---------------------------------------------------------------------------
// 2. Load already-applied migrations
// ---------------------------------------------------------------------------

$applied = $db->query('SELECT filename FROM migrations')
    ->fetchAll(\PDO::FETCH_COLUMN);
$applied = array_flip($applied);   // filename → true, for O(1) lookup

// ---------------------------------------------------------------------------
// 3. Collect pending .sql files
// ---------------------------------------------------------------------------

$migrationDir = ROOT_DIR . '/migrations';
$files = glob($migrationDir . '/*.sql');

if ($files === false || $files === []) {
    echo "No migration files found.\n";
    exit(0);
}

sort($files);   // alphabetical = chronological given the 0001_ prefix convention

$pending = array_filter($files, static function (string $path) use ($applied): bool {
    return !isset($applied[basename($path)]);
});

if ($pending === []) {
    echo "All migrations already applied. Nothing to do.\n";
    exit(0);
}

// ---------------------------------------------------------------------------
// 4. Apply pending migrations
// ---------------------------------------------------------------------------

$applied_count = 0;

foreach ($pending as $file) {
    $filename = basename($file);
    $sql      = file_get_contents($file);

    if ($sql === false || trim($sql) === '') {
        echo "[SKIP]  {$filename} — empty or unreadable\n";
        continue;
    }

    echo "[RUN]   {$filename} ... ";

    try {
        // DDL statements (CREATE TABLE etc.) cause an implicit commit in
        // MySQL/MariaDB, so wrapping in a transaction is unreliable.
        // We run the SQL directly and record it immediately after.
        $db->getPdo()->exec($sql);

        $db->execute(
            'INSERT IGNORE INTO migrations (filename) VALUES (?)',
            [$filename],
        );

        echo "OK\n";
        $log->info("Migration applied: {$filename}");
        $applied_count++;
    } catch (\Throwable $e) {
        echo "FAILED\n";
        echo "  Error: " . $e->getMessage() . "\n";
        $log->error("Migration failed: {$filename} — " . $e->getMessage());
        echo "\nMigration run aborted.\n";
        exit(1);
    }
}

echo "\n{$applied_count} migration(s) applied.\n";
exit(0);
