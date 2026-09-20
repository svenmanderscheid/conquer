<?php
declare(strict_types=1);
/** Disposable database and synthetic admins only. Add --browser for real HTTP/UI checks. */
if (PHP_SAPI !== 'cli') exit(1);
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';
date_default_timezone_set('UTC');
\Conquer\Logger::init(sys_get_temp_dir().'/conquer-alpha-admin-test.log','ERROR');
use Conquer\Admin\{AdminService,AlphaKeyAdmin};
use Conquer\Auth\{AdminAuth,AlphaAccess};
use Conquer\Db\Connection;
$checks=0;$fixture=null;$exit=0;
function checkAlphaAdmin(bool $ok,string $message):void{global $checks;if(!$ok)throw new RuntimeException($message);$checks++;echo "PASS $message\n";}
function rejectAlphaAdmin(callable $fn,string $message):void{try{$fn();}catch(InvalidArgumentException|DomainException){checkAlphaAdmin(true,$message);return;}throw new RuntimeException('Accepted invalid request: '.$message);}
function alphaAdminAction(array $body=[],string $action='alpha-key-create',?string $op=null,int $admin=1):array{
    return AdminService::execute($admin,$action,array_replace(['label'=>'Persönliche Einladung','quantity'=>1,'max_uses'=>1,'expires_at'=>'','reason'=>'Isolated alpha key test','operation_id'=>$op??bin2hex(random_bytes(16))],$body));
}
try{
    $fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();
    \Conquer\Db\MigrationSql::apply($db->getPdo(),(string)file_get_contents(ROOT_DIR.'/migrations/0100_alpha_access_keys.sql'));
    AdminAuth::createAdmin('AlphaAdmin','Fixture-Alpha-2026!','superadmin');
    AdminAuth::createAdmin('AlphaModerator','Fixture-Alpha-2026!','moderator');
    $op=bin2hex(random_bytes(16));$body=['label'=>'Freunde & Familie','quantity'=>2,'max_uses'=>2,'expires_at'=>gmdate('Y-m-d\TH:i',time()+86400)];
    $created=alphaAdminAction($body,'alpha-key-create',$op);$keys=$created['issued_keys'];
    checkAlphaAdmin(count($keys)===2&&$keys[0]['key']!==$keys[1]['key'],'batch creates distinct invitations');
    $row=$db->query('SELECT * FROM alpha_access_keys WHERE id=?',[$keys[0]['id']])->fetch();
    checkAlphaAdmin($row['key_hash']===hash('sha256',AlphaAccess::normalize($keys[0]['key']))&&(int)$row['max_uses']===2&&$row['expires_at']!==null,'stored key hash and limits match issued invitation');
    $again=alphaAdminAction($body,'alpha-key-create',$op);
    checkAlphaAdmin($again['duplicate']&&!isset($again['issued_keys'])&&(int)$db->query('SELECT COUNT(*) FROM alpha_access_keys')->fetchColumn()===2,'retry creates no extra keys and exposes no secret');
    $durable=json_encode([$db->query('SELECT * FROM admin_operations')->fetchAll(),$db->query('SELECT * FROM admin_audit_log')->fetchAll(),$db->query('SELECT * FROM alpha_access_keys')->fetchAll()]);
    foreach($keys as $key)checkAlphaAdmin(!str_contains($durable,$key['key'])&&!str_contains($durable,AlphaAccess::normalize($key['key'])),'plaintext key is absent from database, audit and operation receipts');
    rejectAlphaAdmin(fn()=>alphaAdminAction(array_replace($body,['quantity'=>3]),'alpha-key-create',$op),'changed payload cannot reuse operation');
    rejectAlphaAdmin(fn()=>alphaAdminAction([],'alpha-key-create',null,2),'moderator cannot create keys');
    foreach([['quantity'=>0],['quantity'=>51],['max_uses'=>0],['max_uses'=>65536],['quantity'=>1.5],['label'=>[]],['label'=>str_repeat('x',121)],['expires_at'=>[]],['expires_at'=>'2027-02-30T12:00'],['expires_at'=>'2020-01-01T00:00'],['reason'=>'']] as $bad){
        rejectAlphaAdmin(fn()=>alphaAdminAction($bad),'invalid creation fields rejected: '.implode(',',array_keys($bad)));
    }
    $count=(int)$db->query('SELECT COUNT(*) FROM alpha_access_keys')->fetchColumn();
    $db->execute("CREATE TRIGGER fixture_alpha_audit_failure BEFORE INSERT ON admin_audit_log FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture audit failure'");
    $rollbackOp=bin2hex(random_bytes(16));
    try{alphaAdminAction(['quantity'=>3],'alpha-key-create',$rollbackOp);throw new RuntimeException('failed audit accepted');}catch(PDOException){checkAlphaAdmin(true,'audit failure rejects operation');}
    finally{$db->execute('DROP TRIGGER fixture_alpha_audit_failure');}
    checkAlphaAdmin((int)$db->query('SELECT COUNT(*) FROM alpha_access_keys')->fetchColumn()===$count&&!(int)$db->query('SELECT COUNT(*) FROM admin_operations WHERE operation_id=?',[$rollbackOp])->fetchColumn(),'failed batch rolls back all keys and receipt');
    foreach([1,2] as $_)$db->transaction(fn(Connection $connection)=>AlphaAccess::consume($connection,$keys[0]['key']));
    rejectAlphaAdmin(fn()=>$db->transaction(fn(Connection $connection)=>AlphaAccess::consume($connection,$keys[0]['key'])),'registration usage limit enforced');
    $db->transaction(fn(Connection $connection)=>AlphaAccess::consume($connection,$keys[1]['key']));
    rejectAlphaAdmin(fn()=>alphaAdminAction(['key_id'=>$keys[1]['id']],'alpha-key-revoke',null,2),'moderator cannot revoke keys');
    $revokeOp=bin2hex(random_bytes(16));$revoked=alphaAdminAction(['key_id'=>$keys[1]['id']],'alpha-key-revoke',$revokeOp);
    checkAlphaAdmin($revoked['before']['revoked_at']===null&&$revoked['after']['revoked_at']!==null,'revocation is audited with before and after');
    checkAlphaAdmin(alphaAdminAction(['key_id'=>$keys[1]['id']],'alpha-key-revoke',$revokeOp)['duplicate'],'revocation retry uses receipt');
    rejectAlphaAdmin(fn()=>$db->transaction(fn(Connection $connection)=>AlphaAccess::consume($connection,$keys[1]['key'])),'revoked key cannot consume its remaining use');
    rejectAlphaAdmin(fn()=>alphaAdminAction(['key_id'=>999999],'alpha-key-revoke'),'missing key cannot be revoked');
    AlphaAccess::generate($db,'Abgelaufene Einladung',1,'2020-01-01 00:00:00');
    alphaAdminAction(['label'=>'Aktive Einladung']);
    foreach(['active','used','expired','revoked'] as $status){$list=AlphaKeyAdmin::listing($db,['status'=>$status]);checkAlphaAdmin($list['total']===1&&$list['rows'][0]['status']===$status,'status filter '.$status);}
    $list=AlphaKeyAdmin::listing($db,['q'=>'Familie']);checkAlphaAdmin($list['total']===2,'search by label');
    $list=AlphaKeyAdmin::listing($db,['q'=>(string)$keys[0]['id']]);checkAlphaAdmin($list['total']===1,'search by key ID');
    alphaAdminAction(['label'=>'Seitentest','quantity'=>23]);
    $list=AlphaKeyAdmin::listing($db,['q'=>'Seitentest','page'=>2]);checkAlphaAdmin($list['total']===23&&count($list['rows'])===3&&$list['pages']===2,'pagination retains search scope');
    checkAlphaAdmin(!isset($list['rows'][0]['key_hash']),'overview does not fetch key hashes');
    echo "ALL $checks ALPHA ADMIN CHECKS PASSED\n";
    if(in_array('--browser',$argv,true)){
        $routes= <<<'PHP'
        define('APP_BASE','/conquer');
        $path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
        if(!str_starts_with($path,APP_BASE.'/')){http_response_code(404);exit;}
        $path=substr($path,strlen(APP_BASE));
        if(str_starts_with($path,'/assets/')){
            $file=realpath(ROOT_DIR.$path);$assets=realpath(ROOT_DIR.'/assets').DIRECTORY_SEPARATOR;
            if(!$file||!str_starts_with($file,$assets)||!is_file($file)){http_response_code(404);exit;}
            header('Content-Type: '.(['css'=>'text/css','js'=>'application/javascript','svg'=>'image/svg+xml','png'=>'image/png','webp'=>'image/webp','woff2'=>'font/woff2'][pathinfo($file,PATHINFO_EXTENSION)]??'application/octet-stream'));readfile($file);exit;
        }
        session_name('conquer_alpha_fixture');session_start();
        if($path==='/admin/login'){if($_SERVER['REQUEST_METHOD']==='POST')\Conquer\Admin\AdminController::loginPost();else \Conquer\Admin\AdminController::loginPage();}
        elseif($path==='/admin/logout')\Conquer\Admin\AdminController::logout();
        elseif(str_starts_with($path,'/admin/action/'))\Conquer\Admin\AdminController::handleAction();
        else match($path){'/admin'=>\Conquer\Admin\AdminController::dashboard(),'/admin/alpha-keys'=>\Conquer\Admin\AdminController::alphaKeys(),'/admin/audit'=>\Conquer\Admin\AdminController::auditLog(),default=>http_response_code(404)};
        PHP;
        $base=$fixture->serve($routes).'/conquer';
        $process=proc_open(['node',ROOT_DIR.'/tests/alpha_keys_admin.cjs',$base],[0=>['pipe','r'],1=>STDOUT,2=>STDERR],$pipes,ROOT_DIR,null,['bypass_shell'=>true]);
        if(!is_resource($process))throw new RuntimeException('Browser test did not start.');fclose($pipes[0]);
        if(proc_close($process)!==0)throw new RuntimeException('Browser checks failed.');
    }
}catch(Throwable $e){$exit=1;fwrite(STDERR,'FAIL '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine()."\n");}
finally{if($fixture)$fixture->close();}
exit($exit);
