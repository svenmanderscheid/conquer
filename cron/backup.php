<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(403); exit; }
define('ROOT_DIR',dirname(__DIR__)); require ROOT_DIR.'/src/Bootstrap.php'; \Conquer\Bootstrap::init(ROOT_DIR);
try {
    $directory=getenv('CONQUER_BACKUP_DIR');
    if (!$directory) throw new RuntimeException('Configure CONQUER_BACKUP_DIR outside the web root.');
    $result=\Conquer\Backup\BackupService::create($directory,in_array('--files',$argv,true));
    if (in_array('--verify',$argv,true)) $result=array_replace($result,\Conquer\Backup\BackupService::verifyRestore($result['path']));
    echo json_encode($result,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $e) { fwrite(STDERR,'Backup failed: '.$e->getMessage()."\n"); exit(1); }
