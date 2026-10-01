<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
use Conquer\Db\Connection;
use Conquer\Game\World\WorldMigrationPreview;
if(count($argv)!==2||!ctype_digit($argv[1])||(int)$argv[1]<1){fwrite(STDERR,"Usage: php tools/preview-luxembourg-migration.php WORLD_ID > private-preview.json\nRead-only. Contains complete world-state inventory; keep the result private.\n");exit(1);}
Connection::init(ROOT_DIR);$db=Connection::getInstance();$db->query('SET TRANSACTION READ ONLY');$db->getPdo()->beginTransaction();
try{$preview=WorldMigrationPreview::build((int)$argv[1]);echo json_encode($preview,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";}finally{$db->getPdo()->rollBack();}
