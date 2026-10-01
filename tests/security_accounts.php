<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
ob_start();define('ROOT_DIR',dirname(__DIR__));define('APP_BASE','/conquer');
require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';
use Conquer\Auth\{AdminAuth,AccountService,AccountMailer,Session};
use Conquer\Db\Connection;
function secAccount(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
function rejectsAccount(callable $fn,string $label):void{try{$fn();}catch(DomainException $e){secAccount(true,$label);return;}throw new RuntimeException($label);}
$fixture=new \ConquerTests\FeatureDatabase();$mail=tempnam(sys_get_temp_dir(),'account-mail-');$log=tempnam(sys_get_temp_dir(),'account-log-');
define('CONQUER_TEST_MAIL_FILE',$mail);require __DIR__.'/Support/AccountMailSink.php';
try{
    $db=Connection::getInstance();\Conquer\Logger::init($log);$_SERVER['REMOTE_ADDR']='127.0.0.1';$_SERVER['HTTP_HOST']='attacker.invalid';
    $config=new ReflectionProperty(\Conquer\Bootstrap::class,'config');$config->setValue(null,['base_url'=>'https://game.example.invalid/conquer']);
    session_name('conquer_account_test');session_start();
    $id=AdminAuth::createAdmin('SecurityAdmin','Admin-password-2026!','superadmin');
    $login=static function()use($db):void{$db->execute('DELETE FROM security_rate_limits');secAccount(AdminAuth::login('SecurityAdmin','Admin-password-2026!'),'admin login succeeds');};
    $login();$db->execute("UPDATE admin_users SET role='moderator' WHERE id=?",[$id]);secAccount(AdminAuth::current()['role']==='moderator','demotion replaces cached admin authority immediately');
    $db->execute("UPDATE admin_users SET role='superadmin',must_change_password=1 WHERE id=?",[$id]);secAccount(AdminAuth::mustChangePassword(),'forced password change is read from database');
    $db->execute('UPDATE admin_users SET must_change_password=0 WHERE id=?',[$id]);
    $_SESSION['admin']['last_seen_at']=time()-1801;secAccount(AdminAuth::current()===null,'idle admin session expires');
    $login();$_SESSION['admin']['authenticated_at']=time()-43201;secAccount(AdminAuth::current()===null,'absolute admin session expires');
    $login();$db->execute('UPDATE admin_users SET password_hash=? WHERE id=?',[password_hash('Other-password-2026!',PASSWORD_ARGON2ID),$id]);secAccount(AdminAuth::current()===null,'password rotation revokes existing admin session');
    $db->execute('UPDATE admin_users SET password_hash=? WHERE id=?',[password_hash('Admin-password-2026!',PASSWORD_ARGON2ID),$id]);$login();$db->execute('DELETE FROM admin_users WHERE id=?',[$id]);secAccount(AdminAuth::current()===null,'removed admin loses reads as well as writes');
    $_SESSION['admin']=['id'=>$id,'role'=>'superadmin'];secAccount(AdminAuth::current()===null,'pre-hardening session fails closed');
    $_SESSION=[];session_destroy();
    $password='Original-password-2026!';$db->execute("INSERT INTO players(id,username,email,password_hash)VALUES(991,'AccountFixture','old@example.invalid',?)",[password_hash($password,PASSWORD_DEFAULT)]);
    $token=static function(string $digit,int $minutes=30)use($db):string{$token=str_repeat($digit,64);$db->execute('INSERT INTO password_reset_tokens(player_id,token_hash,expires_at)VALUES(991,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? MINUTE))',[hash('sha256',$token),$minutes]);return $token;};
    $old=$token('a');AccountService::action(991,['action'=>'password.change','current_password'=>$password,'new_password'=>'Changed-password-2026!']);
    rejectsAccount(fn()=>AccountService::resetWithToken($old,'Attacker-password-2026!'),'password change invalidates earlier reset links');$password='Changed-password-2026!';
    $old=$token('b');$oldVerification=str_repeat('c',64);$db->execute('INSERT INTO email_verification_tokens(player_id,token_hash,expires_at)VALUES(991,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))',[hash('sha256',$oldVerification)]);
    AccountService::action(991,['action'=>'email.change','current_password'=>$password,'email'=>'new@example.invalid']);
    rejectsAccount(fn()=>AccountService::resetWithToken($old,'Attacker-password-2026!'),'email change invalidates old-mailbox reset links');
    secAccount(!AccountService::verifyEmail($oldVerification),'old email token cannot verify new email');
    AccountService::requestPasswordReset('new@example.invalid');$messages=array_map(static fn($line)=>json_decode($line,true),array_filter(explode("\n",(string)file_get_contents($mail))));$last=end($messages);
    secAccount(str_contains($last['body'],'https://game.example.invalid/conquer/auth/reset?token=')&&!str_contains($last['body'],'attacker.invalid'),'reset link ignores attacker-controlled Host and preserves deployment subpath');
    preg_match('/token=([a-f0-9]{64})/',$last['body'],$match);$reset=$match[1];
    $db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,user_agent,expires_at,active_world_id)VALUES(991,?,?,'127.0.0.1','test',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),1)",[str_repeat('e',64),str_repeat('f',64)]);
    AccountService::resetWithToken($reset,'Reset-password-2026!');secAccount(password_verify('Reset-password-2026!',(string)$db->query('SELECT password_hash FROM players WHERE id=991')->fetchColumn()),'legitimate mail reset changes password');
    secAccount((int)$db->query('SELECT COUNT(*) FROM sessions WHERE player_id=991')->fetchColumn()===0,'mail reset revokes all player sessions');
    secAccount((bool)$db->query('SELECT email_verified_at FROM players WHERE id=991')->fetchColumn(),'successful reset proves current email ownership');
    rejectsAccount(fn()=>AccountService::resetWithToken($reset,'Other-password-2026!'),'reset is single use');
    $expired=$token('1',-1);rejectsAccount(fn()=>AccountService::resetWithToken($expired,'Other-password-2026!'),'expired reset denied');
    $banned=$token('2');$db->execute('UPDATE players SET is_banned=1 WHERE id=991');rejectsAccount(fn()=>AccountService::resetWithToken($banned,'Other-password-2026!'),'banned account cannot consume reset');
    $url=new ReflectionMethod(AccountMailer::class,'url');foreach(['','http://remote.example','https://user:pass@example.invalid','javascript:alert(1)']as$invalid){$config->setValue(null,['base_url'=>$invalid]);try{$url->invoke(null,'/auth/reset');throw new LogicException('Unsafe base URL accepted');}catch(RuntimeException $e){secAccount(true,'missing or unsafe configured reset origin fails closed');}}
    $_COOKIE[Session::COOKIE_NAME]=['malformed'];secAccount(Session::current()===null,'array session cookie is anonymous rather than a server error');
    echo "ALL ACCOUNT SECURITY CHECKS PASSED\n";
}finally{$fixture->close();foreach([$mail,$log]as$file)if(is_file($file))unlink($file);if(session_status()===PHP_SESSION_ACTIVE)session_destroy();}
