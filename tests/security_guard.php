<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
ob_start(); // Exercise header-setting login paths without CLI output causing warnings.
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR . '/src'))->register();
require ROOT_DIR . '/tests/Support/FeatureDatabase.php';

use Conquer\Db\Connection;
use Conquer\Security\RateLimit;

function securityCheck(bool $condition, string $label): void {
    if (!$condition) throw new RuntimeException($label);
    echo "PASS $label\n";
}
if (($argv[1] ?? '') === '--worker') {
    $root=$argv[2] ?? '';
    if (!preg_match('/^conquer_feature_test_[a-f0-9]{12}$/D', basename($root)) || realpath(dirname($root))!==realpath(sys_get_temp_dir())) exit(2);
    Connection::init($root);
    \Conquer\Logger::init(ROOT_DIR.'/logs/security-test.log');
    echo RateLimit::consume('concurrent','shared',3,3600)===0?'allowed':'denied';
    exit;
}
$fixture = new \ConquerTests\FeatureDatabase();
try {
    $db = Connection::getInstance();
    \Conquer\Db\MigrationSql::apply($db->getPdo(), (string)file_get_contents(ROOT_DIR . '/migrations/0103_security_rate_limits.sql'));
    \Conquer\Logger::init(ROOT_DIR . '/logs/security-test.log');
    for ($i=0; $i<3; $i++) securityCheck(RateLimit::consume('test', 'a', 3, 60) === 0, 'bucket permits initial capacity');
    securityCheck(RateLimit::consume('test', 'a', 3, 60) > 0, 'bucket rejects excess');
    securityCheck(RateLimit::consume('test', 'b', 3, 60) === 0, 'other identity independent');
    securityCheck(RateLimit::consume('other', 'a', 3, 60) === 0, 'other action budget independent');
    $db->execute('UPDATE security_rate_limits SET updated_at=updated_at-61 WHERE bucket_key=?', [hash('sha256','test:a')]);
    securityCheck(RateLimit::consume('test', 'a', 3, 60) === 0, 'bucket recovers after time passes');
    $_SERVER['REMOTE_ADDR']='127.0.0.1'; $_SERVER['HTTP_X_FORWARDED_FOR']='1.2.3.4';
    securityCheck(RateLimit::ip()==='127.0.0.1', 'spoofed proxy headers ignored');
    $tempRoot=sys_get_temp_dir().DIRECTORY_SEPARATOR.$db->query('SELECT DATABASE()')->fetchColumn();
    $workers=[];
    for($i=0;$i<12;$i++) {
        $process=proc_open([PHP_BINARY,__FILE__,'--worker',$tempRoot],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if(!is_resource($process))throw new RuntimeException('Worker unavailable');
        fclose($pipes[0]);$workers[]=[$process,$pipes];
    }
    $allowed=0;$workerErrors=[];
    foreach($workers as[$process,$pipes]) {
        $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);
        fclose($pipes[1]);fclose($pipes[2]);
        if(proc_close($process)!==0)$workerErrors[]=$err;
        if($out==='allowed')$allowed++;
    }
    securityCheck(!$workerErrors,'parallel limiter workers completed: '.implode(';',$workerErrors));
    securityCheck($allowed===3,'12 concurrent workers cannot exceed shared capacity of 3');
    for($i=0;$i<5;$i++) securityCheck(!\Conquer\Auth\AdminAuth::login('absent_security_admin','bad'),'admin invalid credentials rejected');
    try { \Conquer\Auth\AdminAuth::login('absent_security_admin','bad'); throw new LogicException('Admin limiter bypassed'); }
    catch(RuntimeException $e) { securityCheck(str_contains($e->getMessage(),'Zu viele'),'admin sixth attempt rate-limited'); }

    $db->execute("INSERT INTO players(id,username,email,password_hash) VALUES(991,'SecurityTester','security@test.invalid','unused')");
    $tokens=[bin2hex(random_bytes(32)),bin2hex(random_bytes(32))];
    $csrf=bin2hex(random_bytes(32));
    foreach($tokens as $token) $db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,user_agent,expires_at,active_world_id) VALUES(991,?,?,'127.0.0.1','security',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),1)",[$token,$csrf]);
    // Execute the real front controller, using only the already initialized disposable DB.
    $source=(string)file_get_contents(ROOT_DIR.'/index.php');
    $source=str_replace(['declare(strict_types=1);', "define('ROOT_DIR', __DIR__);", "require_once ROOT_DIR . '/src/Bootstrap.php';", '\\Conquer\\Bootstrap::init(ROOT_DIR);'], '', $source);
    $source=preg_replace('/^<\?php\s*/', '', $source);
    $base=$fixture->serve("\$_SERVER['SCRIPT_NAME']='/index.php'; " . $source, ['-d','display_errors=0','-d','display_startup_errors=0']);
    $request=static function(string $path,string $method='GET',string $token='',string $supplied='',string $body='{}')use($base):array {
        $headers=[];$h=curl_init($base.$path);
        curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_CUSTOMREQUEST=>$method,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-CSRF-Token: '.$supplied,'Cookie: conquer_session='.$token],
            CURLOPT_HEADERFUNCTION=>static function($h,$line)use(&$headers){$headers[]=strtolower(trim($line));return strlen($line);}]);
        if($method!=='GET')curl_setopt($h,CURLOPT_POSTFIELDS,$body);
        $raw=curl_exec($h);$code=curl_getinfo($h,CURLINFO_HTTP_CODE);curl_close($h);
        return [$code,json_decode((string)$raw,true),$headers,substr((string)$raw,0,800)];
    };
    securityCheck($request('/api/auth/me')[0]===401,'root rejects anonymous reads');
    $ok=$request('/api/auth/me','GET',$tokens[0]);
    securityCheck($ok[0]===200 && $ok[1]['data']['player_id']===991,'authenticated root read succeeds');
    securityCheck(in_array('cache-control: private, no-store',$ok[2],true),'private API is never cacheable');
    foreach(['POST','PUT','PATCH','DELETE']as$method) {
        $r=$request('/api/security-nonexistent',$method,$tokens[0]);
        securityCheck($r[0]===403 && $r[1]['error']['code']==='CSRF_INVALID',"central CSRF runs before any $method handler");
    }
    $r=$request('/api/auth/logout','POST',$tokens[0],'wrong');
    securityCheck($r[0]===403,'invalid CSRF cannot log out');
    securityCheck($request('/api/auth/me','GET',$tokens[0])[0]===200,'rejected mutation preserves session');
    $db->execute('UPDATE security_rate_limits SET tokens=0,updated_at=UNIX_TIMESTAMP(UTC_TIMESTAMP(6))+60 WHERE bucket_key=?',[hash('sha256','api.write:991')]);
    $r=$request('/api/auth/logout','POST',$tokens[1],$csrf);
    securityCheck($r[0]===429 && count(preg_grep('/^retry-after: [1-9]/',$r[2]))===1,'second session cannot bypass player write budget; retry header sent');
    securityCheck($request('/api/auth/me','GET',$tokens[1])[0]===200,'write limit leaves read polling available');
    $large=$request('/api/auth/logout','POST',$tokens[1],$csrf,str_repeat('x',65537));
    securityCheck($large[0]===413,'oversized body rejected');
    $db->execute('DELETE FROM security_rate_limits WHERE bucket_key=?',[hash('sha256','api.write:991')]);
    securityCheck($request('/api/auth/logout','POST',$tokens[1],$csrf)[0]===200,'valid mutation succeeds after limit resets');
    securityCheck($request('/api/auth/me','GET',$tokens[1])[0]===401,'successful logout revokes session');
    $db->execute('DROP TABLE security_rate_limits');
    $r=$request('/api/auth/me','GET',$tokens[0]);
    securityCheck($r[0]===503 && $r[1]['error']['code']==='SECURITY_UNAVAILABLE','missing limiter storage fails closed');
    echo "ALL SECURITY GUARD CHECKS PASSED\n";
} finally { $fixture->close(); }
