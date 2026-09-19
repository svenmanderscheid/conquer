<?php
declare(strict_types=1);

/** Apply only the additive dungeon schema, leaving unrelated pending work alone. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/src/Bootstrap.php';
\Conquer\Bootstrap::init(ROOT_DIR);
$db = \Conquer\Db\Connection::getInstance();
$filename = '0082_create_dungeons.sql';
\Conquer\Db\MigrationSql::apply($db->getPdo(), (string) file_get_contents(ROOT_DIR . '/migrations/' . $filename));
$db->execute('INSERT IGNORE INTO migrations(filename) VALUES(?)', [$filename]);
echo "Dungeon schema is ready.\n";
