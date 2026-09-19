<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/src/Bootstrap.php';
\Conquer\Bootstrap::init(ROOT_DIR);
$db = \Conquer\Db\Connection::getInstance();
foreach(['0103_security_rate_limits.sql','0104_api_receipts_and_activity.sql'] as $migration) {
    \Conquer\Db\MigrationSql::apply($db->getPdo(), (string)file_get_contents(ROOT_DIR . '/migrations/'.$migration));
    $db->execute('INSERT IGNORE INTO migrations(filename) VALUES(?)', [$migration]);
}
echo "Security rate-limit storage ready.\n";
