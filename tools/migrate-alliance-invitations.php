<?php
declare(strict_types=1);
/** Installs only the additive invitation table, never other pending migrations. */
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));
$config=require ROOT_DIR.'/config/database.php';
if(!in_array($config['host']??'',['localhost','127.0.0.1'],true))throw new RuntimeException('This helper is restricted to the local database.');
require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
$db=\Conquer\Db\Connection::init(ROOT_DIR);$file='0141_alliance_invitations.sql';
if(!in_array('--apply',$argv,true)){echo 'Alliance invitation migration: '.($db->query('SELECT filename FROM migrations WHERE filename=?',[$file])->fetchColumn()?'applied':'pending')."\nUse --apply to install this table only.\n";exit;}
$lock='conquer-migrate-alliance-invitations';
if((int)$db->query('SELECT GET_LOCK(?,10)',[$lock])->fetchColumn()!==1)throw new RuntimeException('Alliance invitation migration is busy.');
try{
    \Conquer\Db\MigrationSql::apply($db->getPdo(),(string)file_get_contents(ROOT_DIR.'/migrations/'.$file));
    $db->execute('INSERT IGNORE INTO migrations(filename)VALUES(?)',[$file]);
    echo "READY $file. Existing memberships and game state retained.\n";
}finally{$db->query('SELECT RELEASE_LOCK(?)',[$lock]);}
