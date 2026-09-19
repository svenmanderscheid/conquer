<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Bootstrap.php';\Conquer\Bootstrap::init(ROOT_DIR);
$db=\Conquer\Db\Connection::getInstance();$file='0090_training_buildings.sql';
if(!in_array('--apply',$argv,true)){echo 'Training migration: '.($db->query('SELECT filename FROM migrations WHERE filename=?',[$file])->fetchColumn()?'applied':'pending')."\nUse --apply to install.\n";exit;}
$lock='conquer-migrate-training';
if((int)$db->query('SELECT GET_LOCK(?,10)',[$lock])->fetchColumn()!==1)throw new RuntimeException('Training migration is busy.');
try{\Conquer\Db\MigrationSql::apply($db->getPdo(),(string)file_get_contents(ROOT_DIR.'/migrations/'.$file));$db->execute('INSERT IGNORE INTO migrations(filename)VALUES(?)',[$file]);echo "READY $file. Existing troops and training timers retained.\n";}
finally{$db->query('SELECT RELEASE_LOCK(?)',[$lock]);}
