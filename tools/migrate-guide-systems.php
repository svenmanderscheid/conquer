<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
$cfg=require ROOT_DIR.'/config/database.php';
if(!in_array($cfg['host'],['localhost','127.0.0.1'],true))exit("Local database only.\n");
$db=\Conquer\Db\Connection::init(ROOT_DIR);
foreach(['0079_monster_rallies.sql','0080_gathering_duration.sql'] as $file){
    \Conquer\Db\MigrationSql::apply($db->getPdo(),file_get_contents(ROOT_DIR.'/migrations/'.$file));
    $db->execute('INSERT IGNORE INTO migrations(filename) VALUES(?)',[$file]);
    echo $file." applied.\n";
}
