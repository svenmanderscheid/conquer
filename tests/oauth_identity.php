<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require ROOT_DIR.'/tests/Support/FeatureDatabase.php';

use Conquer\Auth\OAuth;
use Conquer\Db\Connection;

function identityCheck(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
    echo "PASS $label\n";
}
$fixture = new \ConquerTests\FeatureDatabase();
$logFile = tempnam(sys_get_temp_dir(), 'conquer-oauth-');
try {
    $db = Connection::getInstance();
    \Conquer\Logger::init($logFile);
    $db->execute("INSERT INTO players(id,username,email,password_hash) VALUES(991,'OAuthVictim','victim@example.invalid','unused')");
    $oauth = new OAuth([]);
    $normalize = new ReflectionMethod(OAuth::class, 'normalizeUser');
    $link = new ReflectionMethod(OAuth::class, 'findOrCreatePlayer');
    foreach (['google'=>'verified_email','discord'=>'verified'] as $provider=>$claim) {
        foreach ([false,null,'true',1,'missing'] as $value) {
            $raw = ['id'=>'123456','email'=>'victim@example.invalid',$claim=>$value];
            if ($value === 'missing') unset($raw[$claim]);
            try {
                $user = $normalize->invoke($oauth,$provider,$raw);
                $link->invoke($oauth,$provider,$user);
                throw new LogicException('Unverified provider identity accepted');
            } catch (DomainException $e) { /* Expected closed boundary. */ }
            identityCheck((int)$db->query('SELECT COUNT(*) FROM oauth_accounts')->fetchColumn()===0, "$provider rejects unverified/malformed claim");
            identityCheck(!$db->query('SELECT email_verified_at FROM players WHERE id=991')->fetchColumn(), 'Rejected identity cannot mark email verified');
        }
        foreach (['',[],null] as $subject) {
            try {
                $user=$normalize->invoke($oauth,$provider,['id'=>$subject,'email'=>'victim@example.invalid',$claim=>true]);
                $link->invoke($oauth,$provider,$user);
                throw new LogicException('Invalid subject accepted');
            } catch (DomainException $e) { /* Expected. */ }
        }
        $user=$normalize->invoke($oauth,$provider,['id'=>'123456','email'=>'VICTIM@example.invalid',$claim=>true]);
        try {
            $link->invoke($oauth,$provider,$user);
            throw new LogicException('Unverified local password account was linked');
        } catch (DomainException $e) { /* Prevent pre-hijacking of an unverified local address. */ }
        identityCheck((int)$db->query('SELECT COUNT(*) FROM oauth_accounts')->fetchColumn()===0, 'Unverified local account cannot be pre-hijacked through social login');
        $db->execute('UPDATE players SET email_verified_at=UTC_TIMESTAMP() WHERE id=991');
        $db->execute('UPDATE players SET is_banned=1 WHERE id=991');
        try {$link->invoke($oauth,$provider,$user);throw new LogicException('Banned account linked through OAuth');}
        catch (DomainException $e) { /* Ban applies before creating a provider link. */ }
        identityCheck((int)$db->query('SELECT COUNT(*) FROM oauth_accounts')->fetchColumn()===0,'Banned account cannot create a social link');
        $db->execute('UPDATE players SET is_banned=0 WHERE id=991');
        identityCheck($link->invoke($oauth,$provider,$user)===991, "$provider verified email links correct player");
        identityCheck((bool)$db->query('SELECT identity_verified_at FROM oauth_accounts')->fetchColumn(), 'New link records identity validation');
        identityCheck($link->invoke($oauth,$provider,$user)===991, "$provider existing link resumes");
        $db->execute('UPDATE players SET is_banned=1 WHERE id=991');
        try {$link->invoke($oauth,$provider,$user);throw new LogicException('Banned linked account accepted');}
        catch (DomainException $e) { /* Ban applies before callback login side effects. */ }
        identityCheck(true,'Banned subject cannot enter login side effects');
        $db->execute('UPDATE players SET is_banned=0 WHERE id=991');
        // Existing links must not turn an unverified provider claim into verified email.
        $db->execute('UPDATE players SET email_verified_at=NULL WHERE id=991');
        $unverified=$normalize->invoke($oauth,$provider,['id'=>'123456','email'=>'victim@example.invalid',$claim=>false]);
        identityCheck($link->invoke($oauth,$provider,$unverified)===991, 'Existing subject identity remains usable without email claim');
        identityCheck(!$db->query('SELECT email_verified_at FROM players WHERE id=991')->fetchColumn(), 'Existing link cannot falsely verify email');
        // Historical links cannot inherit trust merely because a link row exists.
        $db->execute('UPDATE oauth_accounts SET identity_verified_at=NULL');
        foreach ([$unverified,$user] as $legacyUser) {
            try {$link->invoke($oauth,$provider,$legacyUser);throw new LogicException('Unverified legacy link accepted');}
            catch (DomainException $e) { /* Local account ownership is not yet established. */ }
        }
        $db->execute('UPDATE players SET email_verified_at=UTC_TIMESTAMP() WHERE id=991');
        $otherEmail=$user;$otherEmail['email']='other@example.invalid';
        foreach ([$unverified,$otherEmail] as $legacyUser) {
            try {$link->invoke($oauth,$provider,$legacyUser);throw new LogicException('Legacy link with invalid provider ownership accepted');}
            catch (DomainException $e) { /* Legacy link needs fresh matching provider proof. */ }
        }
        identityCheck(!$db->query('SELECT identity_verified_at FROM oauth_accounts')->fetchColumn(),'Rejected historical link remains untrusted');
        identityCheck($link->invoke($oauth,$provider,$user)===991,'Historical link resumes after local and provider ownership validation');
        identityCheck((bool)$db->query('SELECT identity_verified_at FROM oauth_accounts')->fetchColumn(),'Historical link stores fresh validation');
        $db->execute('DELETE FROM oauth_accounts');
        $db->execute('UPDATE players SET email_verified_at=NULL WHERE id=991');
    }
    echo "ALL OAUTH IDENTITY CHECKS PASSED\n";
} finally {
    $fixture->close();
    if (is_file($logFile)) unlink($logFile);
}
