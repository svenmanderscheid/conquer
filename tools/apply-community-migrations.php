<?php
declare(strict_types=1);
/** Apply only this feature's additive migrations; never other pending work. */
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
$config=require ROOT_DIR.'/config/database.php';
if(!in_array($config['host']??'',['localhost','127.0.0.1'],true))throw new RuntimeException('This helper is restricted to the local development database. Use the normal deployment migration process on the server.');
\Conquer\Db\Connection::init(ROOT_DIR);$db=\Conquer\Db\Connection::getInstance();
$apply=in_array('--apply',$argv,true);
$files=['0121_community_social.sql','0122_alliance_community.sql','0123_community_news.sql'];
if((int)$db->query("SELECT GET_LOCK('union-community-migrations',10)")->fetchColumn()!==1)throw new RuntimeException('Migration lock unavailable.');
try{
    foreach($files as $file){
        if($db->query('SELECT 1 FROM migrations WHERE filename=?',[$file])->fetchColumn()){echo 'Already applied: '.$file.PHP_EOL;continue;}
        if(!$apply){echo 'Pending: '.$file.PHP_EOL;continue;}
        \Conquer\Db\MigrationSql::apply($db->getPdo(),(string)file_get_contents(ROOT_DIR.'/migrations/'.$file));
        $db->execute('INSERT INTO migrations(filename) VALUES(?)',[$file]);echo 'Applied: '.$file.PHP_EOL;
    }
}finally{$db->query("SELECT RELEASE_LOCK('union-community-migrations')");}
