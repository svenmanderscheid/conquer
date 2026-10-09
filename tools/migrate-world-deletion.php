<?php
declare(strict_types=1);
/** Apply only the purchase-history schema needed by world deletion, on local development data. */
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
$config=require ROOT_DIR.'/config/database.php';
if(!in_array($config['host']??'',['localhost','127.0.0.1'],true))throw new RuntimeException('Local development database only. Use normal release migrations on the server.');
$db=\Conquer\Db\Connection::init(ROOT_DIR);$file='0137_world_deletion_purchase_history.sql';
if((int)$db->query("SELECT GET_LOCK('union-world-deletion-migration',5)")->fetchColumn()!==1)throw new RuntimeException('Migration is busy.');
try{
    if($db->query('SELECT 1 FROM migrations WHERE filename=?',[$file])->fetchColumn()){echo "World deletion migration already applied.\n";exit;}
    if(!in_array('--apply',$argv,true)){echo "World deletion migration pending. Run with --apply to preserve purchase history when deleting worlds.\n";exit;}
    \Conquer\Db\MigrationSql::apply($db->getPdo(),(string)file_get_contents(ROOT_DIR.'/migrations/'.$file));
    $db->execute('INSERT INTO migrations(filename) VALUES(?)',[$file]);
    echo "World deletion purchase-history migration applied.\n";
}finally{$db->query("SELECT RELEASE_LOCK('union-world-deletion-migration')");}
