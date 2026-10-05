<?php
declare(strict_types=1);
/** Install a copy in the hosting account's private uok-alpha-20261004 directory. */
if (PHP_SAPI!=='cli') { http_response_code(403); exit; }
$root=getenv('CONQUER_ROOT') ?: dirname(__DIR__).'/domains/unionofkingdoms.com/public_html';
if (!is_file($root.'/src/Bootstrap.php')) throw new RuntimeException('Game root unavailable.');
define('ROOT_DIR',realpath($root));
require ROOT_DIR.'/src/Bootstrap.php'; \Conquer\Bootstrap::init(ROOT_DIR);
$private=__DIR__; umask(0077);
if (str_starts_with(realpath($private),ROOT_DIR)) throw new RuntimeException('Install this helper outside the public game directory.');
require_once $private.'/BackupService.php';
$db=\Conquer\Db\Connection::getInstance();
try {
    $head=is_file(ROOT_DIR.'/.git/HEAD')?trim((string)file_get_contents(ROOT_DIR.'/.git/HEAD')):null;
    if ($head && preg_match('#^ref: (refs/[A-Za-z0-9_./-]+)$#D',$head,$match) && !str_contains($match[1],'..')) {
        $ref=ROOT_DIR.'/.git/'.$match[1];
        if (is_file($ref)) $head=trim((string)file_get_contents($ref));
    }
    file_put_contents($private.'/source-head.json',json_encode(['head'=>$head,'checked_at'=>gmdate('c')],JSON_PRETTY_PRINT));
    $verificationFile=is_file($private.'/verification-final.json')?$private.'/verification-final.json':$private.'/expected.json';
    if (in_array('--preflight',$argv,true) || !is_file($private.'/preflight.json') || filemtime($verificationFile)>filemtime($private.'/preflight.json')) {
        $manifest=json_decode((string)file_get_contents($verificationFile),true,512,JSON_THROW_ON_ERROR);
        $hashes=[];
        foreach ($manifest as $file=>$expected) {
            if (!preg_match('#^(?:(?:src|assets|data|views|migrations|cron|tools)/[A-Za-z0-9_./-]+|index\.php|manifest\.php|service-worker\.js|offline\.html|apple-touch-icon\.png|favicon\.ico|\.htaccess|\.user\.ini)$#D',$file) || str_contains($file,'..')) throw new RuntimeException('Invalid manifest path.');
            $hash=is_file(ROOT_DIR.'/'.$file)?hash_file('sha256',ROOT_DIR.'/'.$file):null;
            $hashes[$file]=['sha256'=>$hash,'matches_baseline'=>$hash===$expected['before'],'matches_release'=>$hash===$expected['after']];
        }
        $report=['created_at'=>gmdate('c'),'php'=>PHP_VERSION,'zip'=>class_exists(ZipArchive::class),'process'=>function_exists('proc_open'),
            'files'=>$hashes,'migrations'=>$db->query('SELECT filename FROM migrations ORDER BY filename')->fetchAll(PDO::FETCH_COLUMN),
            'worlds'=>$db->query('SELECT id,slug,name,status,speed_factor,gather_factor FROM worlds ORDER BY id')->fetchAll(),
            'table_count'=>count($db->query('SHOW TABLES')->fetchAll()),
            'events'=>$db->query('SHOW EVENTS')->rowCount()];
        file_put_contents($private.'/preflight.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
        echo "Private preflight report completed.\n";
        if (in_array('--preflight',$argv,true)) exit;
    }
    if (is_file($private.'/release-request.json') && !is_file($private.'/release-receipt.json')) {
        require $private.'/release.php';
    }
    $directory=$private.'/backups';
    $finalManifest=$private.'/verification-final.json';
    $finalReceipt=is_file($private.'/final-backup-receipt.json')?json_decode((string)file_get_contents($private.'/final-backup-receipt.json'),true):[];
    if (is_file($finalManifest) && ($finalReceipt['verification_sha256']??null)!==hash_file('sha256',$finalManifest)) {
        $verified=json_decode((string)file_get_contents($private.'/preflight.json'),true,512,JSON_THROW_ON_ERROR);
        if (!array_filter($verified['files'],fn($file)=>!$file['matches_release'])) {
            $result=\Conquer\Backup\BackupService::create($directory,true,14);
            $receipt=['completed_at'=>gmdate('c'),'verification_sha256'=>hash_file('sha256',$finalManifest)]+$result;
            file_put_contents($private.'/final-backup-receipt.json',json_encode($receipt,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
            file_put_contents($private.'/backup-status.json',json_encode($receipt,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
            echo "Verified Alpha runtime and database backed up together.\n";
            exit;
        }
    }
    $today=gmdate('Ymd'); $hour=gmdate('Ymd\TH'); $full=false; $hourly=false;
    foreach(glob($directory.'/uok-*/manifest.json')?:[] as $file) {
        $info=json_decode((string)file_get_contents($file),true);
        $stamp=basename(dirname($file));
        if(str_starts_with($stamp,'uok-'.$hour))$hourly=true;
        if(str_starts_with($stamp,'uok-'.$today)&&!empty($info['runtime_sha256']))$full=true;
    }
    if ($hourly && $full && !in_array('--force',$argv,true)) { echo "Current hour is already backed up.\n"; exit; }
    $result=\Conquer\Backup\BackupService::create($directory,!$full,14);
    file_put_contents($private.'/backup-status.json',json_encode(['completed_at'=>gmdate('c')]+$result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    echo "Backup completed: ".basename($result['path'])."\n";
} catch (Throwable $e) {
    file_put_contents($private.'/maintenance-error.json',json_encode(['failed_at'=>gmdate('c'),'type'=>get_class($e),'message'=>$e->getMessage()],JSON_PRETTY_PRINT));
    fwrite(STDERR,"Alpha maintenance failed; inspect the private error report.\n"); exit(1);
}
