<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
$cfg=require ROOT_DIR.'/config/database.php';
if(!in_array($cfg['host'],['localhost','127.0.0.1'],true))exit("Local database only.\n");
$source=$cfg['database'];if(!preg_match('/^[A-Za-z0-9_]+$/D',$source))exit(1);
$pdo=new PDO('mysql:host='.$cfg['host'].';port='.($cfg['port']??3306).';charset=utf8mb4',$cfg['username'],$cfg['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$verify=in_array('--verify',$argv,true);$target=$verify?'conquer_lord_check_'.bin2hex(random_bytes(6)):$source;
try{
 if($verify){$pdo->exec('CREATE DATABASE `'.$target.'`');foreach(['players','cities','migrations']as$t)$pdo->exec('CREATE TABLE `'.$target.'`.`'.$t.'` LIKE `'.$source.'`.`'.$t.'`');}
 $pdo->exec('USE `'.$target.'`');$file='0075_lord_talents.sql';
 \Conquer\Db\MigrationSql::apply($pdo,file_get_contents(ROOT_DIR.'/migrations/'.$file));
 $pdo->prepare('INSERT IGNORE INTO migrations(filename) VALUES(?)')->execute([$file]);
 echo $verify?"Lord schema verified in disposable database.\n":"Lord schema applied.\n";
}finally{if($verify&&preg_match('/^conquer_lord_check_[a-f0-9]{12}$/D',$target))$pdo->exec('DROP DATABASE `'.$target.'`');}
