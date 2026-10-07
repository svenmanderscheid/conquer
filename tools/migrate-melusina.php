<?php
declare(strict_types=1);
/** Add only Melusina's progress, key receipts and stable map entrances. */
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Bootstrap.php';
\Conquer\Bootstrap::init(ROOT_DIR);
$db=\Conquer\Db\Connection::getInstance();
foreach(['0131_melusina_dungeon.sql','0132_dungeon_entrances.sql'] as $filename){
    \Conquer\Db\MigrationSql::apply($db->getPdo(),(string)file_get_contents(ROOT_DIR.'/migrations/'.$filename));
    $db->execute('INSERT IGNORE INTO migrations(filename)VALUES(?)',[$filename]);
}
echo "Melusina dungeon schema is ready.\n";
