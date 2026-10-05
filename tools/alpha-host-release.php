<?php
declare(strict_types=1);
/** Loaded only by the private CLI maintenance helper after an approved local release is uploaded. */
if (PHP_SAPI!=='cli' || !defined('ROOT_DIR')) { http_response_code(403); exit; }
$releaseLock=fopen(__DIR__.'/release.lock','c');
if (!$releaseLock || !flock($releaseLock,LOCK_EX|LOCK_NB)) return;
try {
    if (is_file(__DIR__.'/release-receipt.json')) return;
    $request=json_decode((string)file_get_contents(__DIR__.'/release-request.json'),true,512,JSON_THROW_ON_ERROR);
    $zipFile=__DIR__.'/release.zip';
    if (!hash_equals((string)$request['zip_sha256'],hash_file('sha256',$zipFile))) throw new RuntimeException('Release archive checksum mismatch.');
    $manifest=json_decode((string)file_get_contents(__DIR__.'/expected.json'),true,512,JSON_THROW_ON_ERROR);
    $zip=new ZipArchive();
    if ($zip->open($zipFile,ZipArchive::CHECKCONS)!==true) throw new RuntimeException('Release archive invalid.');
    $mismatches=[];
    foreach ($manifest as $file=>$hashes) {
        if (!preg_match('#^(?:(?:src|assets|data|views|migrations|cron|tools)/[A-Za-z0-9_./-]+|index\.php|manifest\.php|service-worker\.js|offline\.html|apple-touch-icon\.png|favicon\.ico|\.htaccess|\.user\.ini)$#D',$file) || str_contains($file,'..')) throw new RuntimeException('Release path rejected.');
        $current=is_file(ROOT_DIR.'/'.$file)?hash_file('sha256',ROOT_DIR.'/'.$file):null;
        if ($current!==$hashes['before'] && $current!==$hashes['after']) $mismatches[]=$file;
        $bytes=$zip->getFromName($file);
        if ($bytes===false || !hash_equals($hashes['after'],hash('sha256',$bytes))) throw new RuntimeException('Release entry checksum mismatch: '.$file);
    }
    if ($mismatches) {
        file_put_contents(__DIR__.'/release-drift.json',json_encode($mismatches,JSON_PRETTY_PRINT));
        throw new RuntimeException('Unexpected production file changes; release stopped.');
    }
    $status=\Conquer\Backup\BackupService::create(__DIR__.'/backups',true,14);
    $db=\Conquer\Db\Connection::init(ROOT_DIR);
    file_put_contents(__DIR__.'/backup-status.json',json_encode(['completed_at'=>gmdate('c')]+$status,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    $backup=$status['path'];
    if (!str_starts_with(realpath($backup)?:'',realpath(__DIR__.'/backups').DIRECTORY_SEPARATOR)) throw new RuntimeException('Invalid backup location.');
    $backupInfo=json_decode((string)file_get_contents($backup.'/manifest.json'),true,512,JSON_THROW_ON_ERROR);
    foreach (['database.sql.gz'=>'database_sha256','runtime.zip'=>'runtime_sha256'] as $file=>$key)
        if (!hash_equals((string)$backupInfo[$key],hash_file('sha256',$backup.'/'.$file))) throw new RuntimeException('Private backup checksum mismatch.');
    // Install new dependencies first, server code next, and HTML/script references last.
    $paths=array_keys($manifest);
    usort($paths,static function($a,$b)use($manifest){
        $order=static fn($p)=>$manifest[$p]['before']===null?0:(str_starts_with($p,'views/')||$p==='index.php'?3:(str_starts_with($p,'src/')?2:1));
        return $order($a)<=>$order($b) ?: strcmp($a,$b);
    });
    foreach (['0126_extra_event_button.sql','0127_alpha_entry_and_missions.sql'] as $migration) {
        $sql=$zip->getFromName('migrations/'.$migration);
        if ($sql!==false) {
            \Conquer\Db\MigrationSql::apply($db->getPdo(),$sql);
            $db->execute('INSERT IGNORE INTO migrations(filename) VALUES(?)',[$migration]);
        }
    }
    foreach ($paths as $file) {
        $destination=ROOT_DIR.'/'.$file;
        if (is_file($destination) && hash_file('sha256',$destination)===$manifest[$file]['after']) continue;
        $directory=dirname($destination);
        if (!is_dir($directory)) {
            $mask=umask(0022);
            try { if (!mkdir($directory,0755,true)) throw new RuntimeException('Cannot create release directory.'); }
            finally { umask($mask); }
        }
        $temporary=$destination.'.alpha-'.bin2hex(random_bytes(4)).'.tmp';
        if (file_put_contents($temporary,$zip->getFromName($file))===false) throw new RuntimeException('Cannot stage release file.');
        chmod($temporary,0644);
        if (!rename($temporary,$destination)) throw new RuntimeException('Cannot install release file.');
        if (function_exists('opcache_invalidate')) opcache_invalidate($destination,true);
    }
    $zip->close();
    $db=\Conquer\Db\Connection::init(ROOT_DIR);
    $world=\Conquer\Game\World\AlphaRealm::setup();
    foreach ($manifest as $file=>$hashes)
        if (!hash_equals($hashes['after'],hash_file('sha256',ROOT_DIR.'/'.$file))) throw new RuntimeException('Installed release checksum mismatch.');
    file_put_contents(__DIR__.'/release-receipt.json',json_encode(['released_at'=>gmdate('c'),'files_verified'=>count($manifest),'backup'=>$backup,'realm'=>$world],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    echo "Alpha release installed and new world configured.\n";
} finally {
    flock($releaseLock,LOCK_UN); fclose($releaseLock);
}
