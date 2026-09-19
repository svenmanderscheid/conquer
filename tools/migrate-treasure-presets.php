<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
$cfg=require ROOT_DIR.'/config/database.php';
if(!in_array($cfg['host'],['localhost','127.0.0.1'],true))exit("Local database only.\n");
$db=\Conquer\Db\Connection::init(ROOT_DIR);$file='0076_treasure_presets.sql';
\Conquer\Db\MigrationSql::apply($db->getPdo(),file_get_contents(ROOT_DIR.'/migrations/'.$file));
$db->execute('INSERT IGNORE INTO migrations(filename) VALUES(?)',[$file]);
echo "Treasure preset schema applied; other migrations and player data unchanged.\n";
