<?php
declare(strict_types=1);
namespace Conquer\Backup;

/** CLI-only, private, atomic snapshots. Credentials never appear in process arguments. */
final class BackupService
{
    public static function create(string $directory,bool $files=false,int $retentionDays=14): array
    {
        if (PHP_SAPI!=='cli') throw new \RuntimeException('Backups require CLI.');
        if (!is_dir($directory) && !mkdir($directory,0700,true)) throw new \RuntimeException('Cannot create private backup directory.');
        $base=realpath($directory); $root=realpath(ROOT_DIR);
        if (!$base || !$root || self::within($base,$root)) throw new \RuntimeException('Backups must be outside the web root.');
        $lock=fopen($base.'/backup.lock','c');
        if (!$lock || !flock($lock,LOCK_EX|LOCK_NB)) throw new \RuntimeException('Another backup is already running.');
        $name='uok-'.gmdate('Ymd\THis\Z').'-'.bin2hex(random_bytes(4));
        $target=$base.'/'.$name; mkdir($target,0700); $success=false;
        try {
            $tables=\Conquer\Db\Connection::getInstance()->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
            $cfg=require ROOT_DIR.'/config/database.php';
            $options=$target.'/.client.cnf'; self::options($options,$cfg);
            $partial=$target.'/database.sql.gz.partial'; $errors=$target.'/.dump-error';
            $dump=self::binary('mysqldump');
            $process=proc_open([$dump,'--defaults-extra-file='.$options,'--single-transaction','--quick','--hex-blob','--routines','--triggers','--events','--skip-lock-tables','--default-character-set=utf8mb4',$cfg['database']],
                [0=>['pipe','r'],1=>['pipe','w'],2=>['file',$errors,'w']],$pipes,null,null,['bypass_shell'=>true]);
            if (!is_resource($process)) throw new \RuntimeException('Cannot launch database backup.');
            fclose($pipes[0]); $gzip=gzopen($partial,'wb6');
            if (!$gzip) { proc_terminate($process); throw new \RuntimeException('Cannot write database snapshot.'); }
            while (!feof($pipes[1])) { $chunk=fread($pipes[1],1048576); if ($chunk===false || ($chunk!=='' && gzwrite($gzip,$chunk)!==strlen($chunk))) { proc_terminate($process); throw new \RuntimeException('Database backup write failed.'); } }
            fclose($pipes[1]); gzclose($gzip); $exit=proc_close($process);
            unlink($options); if (is_file($errors)) unlink($errors);
            if ($exit!==0 || filesize($partial)<100) throw new \RuntimeException('Database backup failed; no completed snapshot was published.');
            rename($partial,$target.'/database.sql.gz'); chmod($target.'/database.sql.gz',0600);
            $archive=null;
            if ($files) {
                $archive=$target.'/runtime.zip'; $zip=new \ZipArchive();
                if ($zip->open($archive,\ZipArchive::CREATE|\ZipArchive::OVERWRITE)!==true) throw new \RuntimeException('Cannot create runtime snapshot.');
                foreach (['src','views','data','assets','config','cron','tools','migrations','bin'] as $part) {
                    $dir=ROOT_DIR.'/'.$part;
                    if (!is_dir($dir)) continue;
                    foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir,\FilesystemIterator::SKIP_DOTS)) as $file) {
                        if ($file->isLink() || !$file->isFile()) continue;
                        $relative=str_replace('\\','/',substr($file->getPathname(),strlen(ROOT_DIR)+1));
                        if (!$zip->addFile($file->getPathname(),$relative)) throw new \RuntimeException('Runtime snapshot write failed.');
                    }
                }
                foreach (['index.php','manifest.php','service-worker.js','offline.html','.htaccess','.user.ini','favicon.ico','apple-touch-icon.png'] as $file)
                    if (is_file(ROOT_DIR.'/'.$file)) $zip->addFile(ROOT_DIR.'/'.$file,$file);
                if (!$zip->close()) throw new \RuntimeException('Runtime snapshot finalization failed.');
                chmod($archive,0600);
            }
            $manifest=['version'=>1,'created_at'=>gmdate('c'),'database_sha256'=>hash_file('sha256',$target.'/database.sql.gz'),
                'database_bytes'=>filesize($target.'/database.sql.gz'),'runtime_sha256'=>$archive?hash_file('sha256',$archive):null,
                'tables'=>$tables,
                'restore_verified'=>false];
            file_put_contents($target.'/manifest.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)); chmod($target.'/manifest.json',0600);
            $success=true;
            // Only completed snapshots owned by this tool are eligible for bounded retention.
            $cutoff=time()-max(1,$retentionDays)*86400;
            foreach (glob($base.'/uok-*',GLOB_ONLYDIR)?:[] as $old) {
                if (!preg_match('/^uok-\d{8}T\d{6}Z-[a-f0-9]{8}$/D',basename($old)) || is_link($old) || filemtime($old)>=$cutoff || !is_file($old.'/manifest.json')) continue;
                // Preserve the latest full file snapshot even across a long development break.
                if (is_file($old.'/runtime.zip') && !$archive) continue;
                self::removeSnapshot($old);
            }
            return ['path'=>$target,'database_bytes'=>$manifest['database_bytes'],'runtime_snapshot'=>$files,'restore_verified'=>false];
        } finally {
            foreach (['.client.cnf','.dump-error'] as $file) if (is_file($target.'/'.$file)) unlink($target.'/'.$file);
            if (!$success) self::removeSnapshot($target);
            flock($lock,LOCK_UN); fclose($lock);
        }
    }

    /** Restore only to a newly created disposable database; never overwrites the source. */
    public static function verifyRestore(string $snapshot): array
    {
        if (PHP_SAPI!=='cli') throw new \RuntimeException('Restore verification requires CLI.');
        $manifest=json_decode((string)file_get_contents($snapshot.'/manifest.json'),true,512,JSON_THROW_ON_ERROR);
        if (!hash_equals($manifest['database_sha256'],hash_file('sha256',$snapshot.'/database.sql.gz'))) throw new \RuntimeException('Backup checksum mismatch.');
        $cfg=require ROOT_DIR.'/config/database.php';
        $db=new \PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4',$cfg['host'],$cfg['port']??3306),$cfg['username'],$cfg['password'],[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION]);
        $name='uok_restore_check_'.bin2hex(random_bytes(8));
        $db->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $options=$snapshot.'/.restore-client-'.bin2hex(random_bytes(4)).'.cnf'; $errors=$options.'.err';
        try {
            self::options($options,$cfg);
            $process=proc_open([self::binary('mysql'),'--defaults-extra-file='.$options,$name],[0=>['pipe','r'],1=>['file',$errors,'w'],2=>['file',$errors,'a']],$pipes,null,null,['bypass_shell'=>true]);
            if (!is_resource($process)) throw new \RuntimeException('Cannot start restore verification.');
            $gzip=gzopen($snapshot.'/database.sql.gz','rb');
            while (!gzeof($gzip)) { $chunk=gzread($gzip,1048576); if ($chunk===false) throw new \RuntimeException('Cannot decompress backup.'); $offset=0; while ($offset<strlen($chunk)) { $written=@fwrite($pipes[0],substr($chunk,$offset)); if (!$written) throw new \RuntimeException('Restore verification import failed.'); $offset+=$written; } }
            gzclose($gzip); fclose($pipes[0]); $exit=proc_close($process);
            if ($exit!==0) throw new \RuntimeException('Restore verification import failed.');
            $tables=$db->query('SHOW TABLES FROM `'.$name.'`')->fetchAll(\PDO::FETCH_COLUMN); sort($tables); sort($manifest['tables']);
            if ($tables!==$manifest['tables']) throw new \RuntimeException('Restored table inventory mismatch.');
            if ($manifest['runtime_sha256']) {
                if (!hash_equals($manifest['runtime_sha256'],hash_file('sha256',$snapshot.'/runtime.zip'))) throw new \RuntimeException('Runtime checksum mismatch.');
                $zip=new \ZipArchive(); if ($zip->open($snapshot.'/runtime.zip',\ZipArchive::CHECKCONS)!==true) throw new \RuntimeException('Runtime archive integrity check failed.'); $zip->close();
            }
            $manifest['restore_verified']=true; $manifest['restore_verified_at']=gmdate('c'); $manifest['restored_tables']=count($tables);
            file_put_contents($snapshot.'/manifest.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
            return ['restored_tables'=>count($tables),'restore_verified'=>true];
        } catch (\Throwable $e) {
            if (is_file($errors)) { copy($errors,$snapshot.'/restore-failure.log'); chmod($snapshot.'/restore-failure.log',0600); }
            throw $e;
        } finally {
            // The exact name is created above and can never identify a user database.
            $db->exec('DROP DATABASE `'.$name.'`');
            foreach ([$options,$errors] as $file) if (is_file($file)) unlink($file);
        }
    }
    private static function options(string $path,array $cfg): void
    {
        $out="[client]\n";
        foreach (['host'=>'host','port'=>'port','user'=>'username','password'=>'password'] as $key=>$source) {
            $value=(string)($cfg[$source]??($key==='port'?3306:''));
            if (strpbrk($value,"\r\n\0")!==false) throw new \RuntimeException('Unsupported client configuration.');
            $out.=$key.'="'.str_replace(['\\','"'],['\\\\','\\"'],$value)."\"\n";
        }
        if (file_put_contents($path,$out)===false) throw new \RuntimeException('Cannot write private client configuration.'); chmod($path,0600);
    }
    private static function binary(string $name): string
    {
        $configured=getenv('CONQUER_'.strtoupper($name).'_BINARY');
        if ($configured) return $configured;
        if (PHP_OS_FAMILY==='Windows' && is_file('C:/xampp/mysql/bin/'.$name.'.exe')) return 'C:/xampp/mysql/bin/'.$name.'.exe';
        return $name;
    }
    private static function within(string $path,string $root): bool
    {
        $path=strtolower(str_replace('\\','/',$path));$root=strtolower(str_replace('\\','/',$root));
        return $path===$root || str_starts_with($path,$root.'/');
    }
    private static function removeSnapshot(string $path): void
    {
        // No recursion and no symlink following: only this tool's known files.
        foreach (['database.sql.gz','database.sql.gz.partial','runtime.zip','manifest.json','.client.cnf','.dump-error'] as $file) if (is_file($path.'/'.$file)) unlink($path.'/'.$file);
        if (is_dir($path)) rmdir($path);
    }
}
