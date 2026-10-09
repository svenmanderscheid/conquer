<?php
declare(strict_types=1);
/** XAMPP Apache integration: disposable document root, no game bootstrap or DB.
 * Covers request limits, private artifacts and maintenance-script HTTP guards.
 * Run: C:/xampp/php/php.exe tests/apache_request_limits.php
 * Optional CONQUER_APACHE_ROOT / CONQUER_PHP_ROOT override the XAMPP paths.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$apache = str_replace('\\','/',getenv('CONQUER_APACHE_ROOT') ?: 'C:/xampp/apache');
$php = str_replace('\\','/',getenv('CONQUER_PHP_ROOT') ?: 'C:/xampp/php');
$binary = $apache.'/bin/httpd.exe';
if (!is_file($binary) || !is_file($php.'/php8apache2_4.dll')) {
    fwrite(STDERR,"XAMPP Apache/PHP module required; no server configuration changed.\n"); exit(2);
}
$root = str_replace('\\','/',dirname(__DIR__)).'/.codex-tmp/conquer_http_limits_'.bin2hex(random_bytes(6));
$server = null; $checks = 0;
function checkHttp(bool $ok, string $label): void {
    global $checks;
    if (!$ok) throw new RuntimeException($label);
    ++$checks; echo "PASS $label\n";
}
function requestLimit(int $port, string $path, int $size, bool $chunked=false, string $method='POST'): array {
    $ch=curl_init('http://127.0.0.1:'.$port.$path);
    $headers=['Content-Type: application/octet-stream','Expect:'];
    if($chunked)$headers[]='Transfer-Encoding: chunked';
    curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_POSTFIELDS=>str_repeat('x',$size),
        CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,
        CURLOPT_TIMEOUT=>15,CURLOPT_HTTP_VERSION=>CURL_HTTP_VERSION_1_1,CURLOPT_PATH_AS_IS=>true,
        CURLOPT_NOBODY=>$method==='HEAD']);
    $response=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_error($ch);curl_close($ch);
    if($response===false)throw new RuntimeException("HTTP fixture request $method $path failed: $error");
    return [$status,(string)$response,$error];
}
function removeFixture(string $path, string $root): void {
    $resolved=str_replace('\\','/',(string)realpath($path));
    if($resolved!==$root&&!str_starts_with($resolved,$root.'/'))throw new RuntimeException('Unsafe fixture cleanup path');
    if(is_dir($path)){
        foreach(new DirectoryIterator($path) as $entry)if(!$entry->isDot())removeFixture($entry->getPathname(),$root);
        rmdir($path);
    }else unlink($path);
}
try {
    mkdir($root.'/www/conquer',0700,true);
    $source=(string)file_get_contents(dirname(__DIR__).'/.htaccess');
    // Read the complete entity so Apache must enforce chunked limits as well.
    $handler='<?php $raw=file_get_contents("php://input"); header("Content-Type: text/plain"); $path=parse_url($_SERVER["REQUEST_URI"],PHP_URL_PATH); if(!preg_match("~^/(?:conquer/)?(?:index\\.php$|city$|api/)~",$path)){http_response_code(404);exit;} echo "fixture-bytes=".strlen((string)$raw);';
    $privateDirs=['output','outputs','preview','.playwright-cli'];
    $helpers=['apply-hunter-vip-dependency.php','check-hunter-schema.php'];
    foreach([$root.'/www',$root.'/www/conquer'] as $site){
        file_put_contents($site.'/.htaccess',$source);file_put_contents($site.'/index.php',$handler);
        foreach([...$privateDirs,'assets','output-public'] as $dir){
            mkdir($site.'/'.$dir.'/nested',0700,true);
            foreach(['probe.txt','nested/probe.zip','nested/probe.log'] as $file)file_put_contents($site.'/'.$dir.'/'.$file,'private-fixture-marker');
            file_put_contents($site.'/'.$dir.'/probe.php','<?php echo "private-fixture-marker";');
        }
        // Copies can reach only this tripwire, never an application autoloader or DB.
        mkdir($site.'/src');mkdir($site.'/cli-probes');
        file_put_contents($site.'/src/Autoloader.php','<?php echo "fixture-cli-entry"; exit;');
        foreach($helpers as $helper){
            $original=dirname(__DIR__).'/output/'.$helper;
            if(is_file($original))copy($original,$site.'/cli-probes/'.$helper);
        }
        // Exercise the directory-level fallback independently of the root name rule.
        if(is_file(dirname(__DIR__).'/output/.htaccess')){
            mkdir($site.'/directory-guard');
            copy(dirname(__DIR__).'/output/.htaccess',$site.'/directory-guard/.htaccess');
            file_put_contents($site.'/directory-guard/probe.txt','private-fixture-marker');
        }
    }
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
    if(!$socket)throw new RuntimeException($error);
    $address=stream_socket_get_name($socket,false);fclose($socket);$port=(int)substr(strrchr($address,':'),1);
    $modules='';
    foreach(['authz_core','alias','rewrite','headers','mime'] as $module)$modules.="LoadModule {$module}_module \"$apache/modules/mod_$module.so\"\n";
    $config="ServerRoot \"$apache\"\nListen 127.0.0.1:$port\nServerName localhost\nPidFile \"$root/httpd.pid\"\nErrorLog \"$root/error.log\"\nLogLevel warn\n".$modules
        ."LoadFile \"$php/php8ts.dll\"\nLoadFile \"$php/libpq.dll\"\nLoadFile \"$php/libsqlite3.dll\"\nLoadModule php_module \"$php/php8apache2_4.dll\"\nPHPIniDir \"$php\"\n"
        ."TypesConfig \"$apache/conf/mime.types\"\nDocumentRoot \"$root/www\"\n<Directory />\nAllowOverride None\nRequire all denied\n</Directory>\n<Directory \"$root/www\">\nAllowOverride All\nOptions FollowSymLinks\nRequire all granted\n</Directory>\n"
        ."<FilesMatch \\\.php$>\nSetHandler application/x-httpd-php\n</FilesMatch>\n";
    file_put_contents($root.'/httpd.conf',$config);
    $nativeConfig='httpd.conf';
    $syntax=proc_open([$binary,'-t','-f',$nativeConfig,'-d',$root],[0=>['pipe','r'],1=>['file',$root.'/syntax.log','w'],2=>['file',$root.'/syntax.log','a']],$pipes,$root,null,['bypass_shell'=>true]);
    fclose($pipes[0]);checkHttp(is_resource($syntax)&&proc_close($syntax)===0,'isolated Apache configuration syntax');
    $server=proc_open([$binary,'-f',$nativeConfig,'-d',$root,'-DFOREGROUND'],[0=>['pipe','r'],1=>['file',$root.'/server.log','a'],2=>['file',$root.'/server.log','a']],$pipes,$root,null,['bypass_shell'=>true]);
    if(!is_resource($server))throw new RuntimeException('Cannot start isolated Apache');fclose($pipes[0]);
    $ready=false;
    for($attempt=0;$attempt<50;$attempt++){$probe=@fsockopen('127.0.0.1',$port,$errno,$error,.1);if($probe){fclose($probe);$ready=true;break;}usleep(100000);}
    checkHttp($ready,'isolated Apache available');
    foreach(['','/conquer'] as $base){
        foreach([false,true] as $chunked){
            $mode=$chunked?'chunked':'Content-Length';
            foreach([
                ['/api/kingdom/action',65536,200],['/api/kingdom/action',65537,413],
                ['/api/kingdom/profile-image',5500000,200],['/api/kingdom/profile-image',5500001,413],
                ['/api/bug-reports',1300000,200],['/api/bug-reports',1300001,413],
                ['/api/kingdom/profile-image?check=1',70000,200],['/api/bug-reports?check=1',70000,200],
                ['/index.php?route=/api/kingdom/profile-image',70000,413],
                ['/api/kingdom/action?next=/api/bug-reports',70000,413],
                ['/api/bug-reports/extra',70000,413],['/api/kingdom/profile-image-other',70000,413],
            ] as [$path,$size,$expected]){
                [$status,$body,$error]=requestLimit($port,$base.$path,$size,$chunked);
                checkHttp($status===$expected,"$mode $base$path $size bytes => $expected (got $status $error)");
                if($status===200)checkHttp(str_contains($body,'fixture-bytes='.$size),'body reaches rewritten PHP intact');
            }
            [$status]=requestLimit($port,$base.'/api/kingdom/profile-image',70000,$chunked,'DELETE');
            checkHttp($status===413,"$mode non-upload method retains 64 KiB limit");
        }
        foreach($privateDirs as $dir){
            foreach(['GET','HEAD','POST'] as $method){
                foreach(['/probe.php','','/probe.txt','/nested/probe.zip','/nested/probe.log'] as $suffix){
                    [$status,$body]=requestLimit($port,$base.'/'.$dir.$suffix,0,false,$method);
                    checkHttp($status===403&&!str_contains($body,'private-fixture-marker'),"$method $base/$dir$suffix denied before content or execution (got $status)");
                }
            }
        }
        foreach(['/%6futput/probe.php','/%2eplaywright-cli/nested/probe.log','/OuTpUt/probe.txt','/output/probe.php/extra','/output/probe.php?next=/assets/probe.txt','/assets/../output/probe.php','//output//probe.php'] as $path){
            [$status,$body]=requestLimit($port,$base.$path,0,false,'GET');
            checkHttp($status===403&&!str_contains($body,'private-fixture-marker'),"private path variant $base$path denied");
        }
        foreach(['/output%2fprobe.php','/output%5cprobe.php','/output./probe.php','/output%20/probe.php'] as $path){
            [$status,$body]=requestLimit($port,$base.$path,0,false,'GET');
            checkHttp(in_array($status,[400,403,404],true)&&!str_contains($body,'private-fixture-marker'),"platform path alias $base$path rejected");
        }
        // NTFS can expose dot-directories through a generated 8.3 name.
        $shortNameProcess=proc_open('cmd.exe /d /c for %I in (.playwright-cli) do @echo %~snxI',[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root.'/www'.$base,null,['bypass_shell'=>true]);
        if(!is_resource($shortNameProcess))throw new RuntimeException('Cannot inspect Windows directory alias');
        fclose($pipes[0]);$shortName=trim((string)stream_get_contents($pipes[1]));$shortError=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        $shortExit=proc_close($shortNameProcess);
        checkHttp($shortExit===0&&$shortName!==''&&$shortError==='',"Windows directory alias resolved (exit $shortExit, name $shortName, error $shortError)");
        if(strcasecmp($shortName,'.playwright-cli')!==0){
            [$status,$body]=requestLimit($port,$base.'/'.rawurlencode($shortName).'/nested/probe.log',0,false,'GET');
            checkHttp($status===403&&!str_contains($body,'private-fixture-marker'),"Windows short directory alias $base/$shortName denied (got $status)");
        }
        foreach(['/assets/probe.txt','/output-public/probe.txt'] as $path){
            [$status,$body]=requestLimit($port,$base.$path,0,false,'GET');
            checkHttp($status===200&&str_contains($body,'private-fixture-marker'),"$base$path public fixture accessible");
        }
        foreach(['/city','/api/game/state'] as $path){
            [$status,$body]=requestLimit($port,$base.$path,0,false,'GET');
            checkHttp($status===200&&str_contains($body,'fixture-bytes=0'),"$base$path still reaches front controller");
        }
        if(is_dir($root.'/www'.$base.'/directory-guard')){
            [$status,$body]=requestLimit($port,$base.'/directory-guard/probe.txt',0,false,'GET');
            checkHttp($status===403&&!str_contains($body,'private-fixture-marker'),'directory-level fallback denies independently');
        }
        foreach($helpers as $helper){
            $copy=$root.'/www'.$base.'/cli-probes/'.$helper;
            // These are local-only tools and may be absent from a clean release checkout.
            if(!is_file($copy))continue;
            foreach(['GET','POST','HEAD'] as $method){
                [$status,$body]=requestLimit($port,$base.'/cli-probes/'.$helper,0,false,$method);
                checkHttp($status===403&&!str_contains($body,'fixture-cli-entry'),"$method $helper rejects HTTP even outside the private directory");
            }
            $cli=proc_open([PHP_BINARY,$copy],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root,null,['bypass_shell'=>true]);
            if(!is_resource($cli))throw new RuntimeException('Cannot check CLI control');
            fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
            checkHttp(proc_close($cli)===0&&$out==='fixture-cli-entry'&&$err==='',"$helper retains its CLI entry path without opening a database");
        }
    }
    echo "ALL $checks APACHE REQUEST-LIMIT CHECKS PASSED\n";
} catch(Throwable $e) {
    fwrite(STDERR,'FAIL '.$e->getMessage()."\n");
    foreach(['syntax.log','error.log'] as $log)if(is_file($root.'/'.$log))fwrite(STDERR,(string)file_get_contents($root.'/'.$log));
    $failed=true;
} finally {
    if(is_resource($server)){
        // This is a console instance, not the user's installed Apache service.
        // Terminate only the process tree we started; -k shutdown targets a service.
        $serverPid=(int)proc_get_status($server)['pid'];
        $stop=proc_open(['taskkill.exe','/PID',(string)$serverPid,'/T','/F'],[0=>['pipe','r'],1=>['file',$root.'/stop.log','a'],2=>['file',$root.'/stop.log','a']],$pipes,$apache.'/bin',null,['bypass_shell'=>true]);
        if(is_resource($stop)){fclose($pipes[0]);proc_close($stop);}
        proc_close($server);
    }
    if(is_dir($root))removeFixture($root,$root);
}
exit(empty($failed)?0:1);
