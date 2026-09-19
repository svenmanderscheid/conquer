<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
$config=require ROOT_DIR.'/config/database.php';if(!in_array($config['host']??'',['localhost','127.0.0.1'],true))exit("Local database only.\n");
$pdo=new PDO('mysql:host='.$config['host'].';port='.($config['port']??3306).';charset=utf8mb4',$config['username'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$source=$config['database'];if(!preg_match('/^[A-Za-z0-9_]+$/D',$source))exit(1);
$verify=in_array('--verify',$argv,true);$target=$verify?'conquer_migration_check_'.bin2hex(random_bytes(6)):$source;
try{
 if($verify){$pdo->exec('CREATE DATABASE `'.$target.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');foreach($pdo->query('SHOW TABLES FROM `'.$source.'`')->fetchAll(PDO::FETCH_COLUMN)as$t){if(!preg_match('/^[A-Za-z0-9_]+$/D',$t))throw new RuntimeException('Invalid table name');$pdo->exec('CREATE TABLE `'.$target.'`.`'.$t.'` LIKE `'.$source.'`.`'.$t.'`');}$pdo->exec('INSERT INTO `'.$target.'`.migrations SELECT * FROM `'.$source.'`.migrations');}
 $pdo->exec('USE `'.$target.'`');$done=$pdo->query('SELECT filename FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
 foreach(glob(ROOT_DIR.'/migrations/00*.sql')as$file){$base=basename($file);$number=(int)substr($base,0,4);if($number<63||$number>73||in_array($base,$done,true))continue;\Conquer\Db\MigrationSql::apply($pdo,file_get_contents($file));$stmt=$pdo->prepare('INSERT INTO migrations(filename) VALUES(?)');$stmt->execute([$base]);echo 'OK '.$base."\n";}
 echo $verify?"Feature schema verified in disposable database.\n":"Feature schema applied.\n";
}finally{if($verify&&preg_match('/^conquer_migration_check_[a-f0-9]{12}$/D',$target))$pdo->exec('DROP DATABASE `'.$target.'`');}
