<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Bootstrap.php';
\Conquer\Bootstrap::init(ROOT_DIR);
$db=\Conquer\Db\Connection::getInstance();
\Conquer\Db\MigrationSql::apply($db->getPdo(),(string)file_get_contents(ROOT_DIR.'/migrations/0117_ui_layout.sql'));
$db->execute('INSERT IGNORE INTO migrations(filename) VALUES(?)',['0117_ui_layout.sql']);
echo "Union of Kingdoms: Layout-Speicher bereit.\n";
