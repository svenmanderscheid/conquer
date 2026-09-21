<?php
declare(strict_types=1);
namespace Conquer\Auth;

use Conquer\Db\Connection;

final class AccountService
{
    public const RESET_MESSAGE='Wenn die E-Mail-Adresse zu einem Konto gehört, wurde ein Wiederherstellungslink versendet.';
    public static function state(int $playerId): array
    {
        $db=Connection::getInstance();$profile=$db->query('SELECT email,email_verified_at FROM players WHERE id=?',[$playerId])->fetch()?:[];return ['has_password'=>(bool)$db->query('SELECT password_hash FROM players WHERE id=?',[$playerId])->fetchColumn(),
            'email'=>str_ends_with((string)($profile['email']??''),'@accounts.invalid')?'':(string)($profile['email']??''),
            'email_verified'=>(bool)($profile['email_verified_at']??null),
            'has_recovery_code'=>(bool)$db->query('SELECT id FROM account_recovery_codes WHERE player_id=? AND used_at IS NULL',[$playerId])->fetchColumn(),
            'sessions'=>$db->query('SELECT id,user_agent,created_at,expires_at FROM sessions WHERE player_id=? AND expires_at>UTC_TIMESTAMP() ORDER BY id DESC',[$playerId])->fetchAll()];
    }

    public static function action(int $playerId,array $body): array
    {
        $db=Connection::getInstance();$limitKey='account:'.$playerId;
        if((int)$db->query('SELECT COUNT(*) FROM login_attempts WHERE ip_address=? AND attempted_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 15 MINUTE)',[$limitKey])->fetchColumn()>=10)throw new \DomainException('Zu viele Passwortversuche. Warte bitte 15 Minuten.');
        $db->execute('INSERT INTO login_attempts(ip_address) VALUES(?)',[$limitKey]);
        $result=$db->transaction(function()use($db,$playerId,$body,$limitKey):array{
            $row=$db->query('SELECT password_hash FROM players WHERE id=? FOR UPDATE',[$playerId])->fetch();
            $password=$body['current_password']??null;
            if(!is_string($password)||strlen($password)>200||!$row||!$row['password_hash']||!password_verify($password,$row['password_hash']))throw new \DomainException('Das aktuelle Passwort stimmt nicht.');
            $db->execute('DELETE FROM login_attempts WHERE ip_address=?',[$limitKey]);
            switch($body['action']??''){
                case 'email.change':
                    $email=is_string($body['email']??null)?strtolower(trim($body['email'])):'';
                    if(strlen($email)>254||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new \DomainException('Bitte gib eine gültige E-Mail-Adresse ein.');
                    if($db->query('SELECT id FROM players WHERE email=? AND id<>?',[$email,$playerId])->fetchColumn())throw new \DomainException('Diese E-Mail-Adresse wird bereits verwendet.');
                    $db->execute('UPDATE players SET email=?,email_verified_at=NULL WHERE id=?',[$email,$playerId]);
                    return ['message'=>'E-Mail-Adresse gespeichert. Bitte bestätige sie über den zugesandten Link.','send_verification'=>true];
                case 'password.change':
                    $new=self::password($body['new_password']??null);$db->execute('UPDATE players SET password_hash=? WHERE id=?',[password_hash($new,PASSWORD_DEFAULT),$playerId]);
                    $db->execute('DELETE FROM sessions WHERE player_id=? AND token<>?',[$playerId,$_COOKIE[Session::COOKIE_NAME]??'']);
                    return ['message'=>'Passwort geändert. Andere Sitzungen wurden abgemeldet.'];
                case 'recovery.generate':
                    $code=strtoupper(bin2hex(random_bytes(16)));$db->execute('DELETE FROM account_recovery_codes WHERE player_id=?',[$playerId]);
                    $db->execute('INSERT INTO account_recovery_codes(player_id,code_hash) VALUES(?,?)',[$playerId,hash('sha256',$code)]);
                    return ['message'=>'Bewahre diesen einmalig angezeigten Wiederherstellungscode sicher auf.','recovery_code'=>implode('-',str_split($code,4))];
                case 'sessions.revoke':$db->execute('DELETE FROM sessions WHERE player_id=? AND token<>?',[$playerId,$_COOKIE[Session::COOKIE_NAME]??'']);return ['message'=>'Andere Sitzungen beendet.'];
                default:throw new \DomainException('Unbekannte Kontoaktion.');
            }
        });
        if(!empty($result['send_verification'])){unset($result['send_verification']);self::sendVerification($playerId);}
        return $result;
    }

    public static function recover(string $name,string $code,mixed $new): void
    {
        $retry = \Conquer\Security\RateLimit::consume('recovery.ip', \Conquer\Security\RateLimit::ip(), 10, 900);
        if (!$retry) $retry = \Conquer\Security\RateLimit::consume('recovery.account', strtolower(trim($name)), 10, 900);
        if ($retry > 0) {
            http_response_code(429);
            header('Retry-After: ' . $retry);
            throw new \DomainException('Zu viele Versuche. Bitte warte kurz.');
        }
        $db=Connection::getInstance();$ip=substr($_SERVER['REMOTE_ADDR']??'',0,45);
        if((int)$db->query('SELECT COUNT(*) FROM login_attempts WHERE ip_address=? AND attempted_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 15 MINUTE)',[$ip])->fetchColumn()>=10)throw new \DomainException('Zu viele Versuche. Warte bitte 15 Minuten.');
        $db->execute('INSERT INTO login_attempts(ip_address) VALUES(?)',[$ip]);$new=self::password($new);
        $code=strtoupper(str_replace('-','',trim($code)));
        if(!preg_match('/^[A-F0-9]{32}$/D',$code))throw new \DomainException('Name oder Wiederherstellungscode stimmt nicht.');
        $db->transaction(function()use($db,$name,$code,$new):void{
            $p=$db->query('SELECT id,is_banned FROM players WHERE username=? FOR UPDATE',[$name])->fetch();
            $r=$p?$db->query('SELECT id FROM account_recovery_codes WHERE player_id=? AND code_hash=? AND used_at IS NULL FOR UPDATE',[$p['id'],hash('sha256',$code)])->fetch():false;
            if(!$p||$p['is_banned']||!$r)throw new \DomainException('Name oder Wiederherstellungscode stimmt nicht.');
            $db->execute('UPDATE players SET password_hash=? WHERE id=?',[password_hash($new,PASSWORD_DEFAULT),$p['id']]);
            $db->execute('UPDATE account_recovery_codes SET used_at=UTC_TIMESTAMP() WHERE player_id=?',[$p['id']]);$db->execute('DELETE FROM sessions WHERE player_id=?',[$p['id']]);
        });
    }

    public static function requestPasswordReset(string $email): void
    {
        $email=strtolower(trim($email));
        $retry=\Conquer\Security\RateLimit::consume('password-reset.ip',\Conquer\Security\RateLimit::ip(),5,900);
        if(!$retry)$retry=\Conquer\Security\RateLimit::consume('password-reset.account',hash('sha256',$email),3,900);
        if($retry>0){http_response_code(429);header('Retry-After: '.$retry);throw new \DomainException('Bitte warte kurz, bevor du einen weiteren Link anforderst.');}
        if(strlen($email)>254||!filter_var($email,FILTER_VALIDATE_EMAIL))return;
        $db=Connection::getInstance();$player=$db->query("SELECT id,email FROM players WHERE email=? AND email NOT LIKE '%@accounts.invalid' AND is_banned=0 LIMIT 1",[$email])->fetch();
        if(!$player)return;
        $token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);
        $db->transaction(function()use($db,$player,$hash):void{
            $db->execute('UPDATE password_reset_tokens SET used_at=UTC_TIMESTAMP() WHERE player_id=? AND used_at IS NULL',[$player['id']]);
            $db->execute('INSERT INTO password_reset_tokens(player_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 MINUTE))',[$player['id'],$hash]);
        });
        AccountMailer::passwordReset((string)$player['email'],$token);
    }

    public static function resetWithToken(string $token,mixed $new): void
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$token))throw new \DomainException('Der Wiederherstellungslink ist ungültig oder abgelaufen.');
        $new=self::password($new);$db=Connection::getInstance();$hash=hash('sha256',$token);
        $db->transaction(function()use($db,$hash,$new):void{
            $row=$db->query('SELECT id,player_id FROM password_reset_tokens WHERE token_hash=? AND used_at IS NULL AND expires_at>UTC_TIMESTAMP() FOR UPDATE',[$hash])->fetch();
            if(!$row)throw new \DomainException('Der Wiederherstellungslink ist ungültig oder abgelaufen.');
            $db->execute('UPDATE players SET password_hash=? WHERE id=?',[password_hash($new,PASSWORD_DEFAULT),$row['player_id']]);
            $db->execute('UPDATE password_reset_tokens SET used_at=UTC_TIMESTAMP() WHERE player_id=? AND used_at IS NULL',[$row['player_id']]);
            $db->execute('DELETE FROM sessions WHERE player_id=?',[$row['player_id']]);
        });
    }

    public static function sendVerification(int $playerId): void
    {
        $db=Connection::getInstance();$player=$db->query("SELECT email,email_verified_at FROM players WHERE id=? AND email NOT LIKE '%@accounts.invalid'",[$playerId])->fetch();
        if(!$player||$player['email_verified_at'])return;
        $token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);
        $db->transaction(function()use($db,$playerId,$hash):void{
            $db->execute('UPDATE email_verification_tokens SET used_at=UTC_TIMESTAMP() WHERE player_id=? AND used_at IS NULL',[$playerId]);
            $db->execute('INSERT INTO email_verification_tokens(player_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 24 HOUR))',[$playerId,$hash]);
        });
        AccountMailer::verification((string)$player['email'],$token);
    }

    public static function verifyEmail(string $token): bool
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$token))return false;
        $db=Connection::getInstance();$hash=hash('sha256',$token);
        return $db->transaction(function()use($db,$hash):bool{
            $row=$db->query('SELECT id,player_id FROM email_verification_tokens WHERE token_hash=? AND used_at IS NULL AND expires_at>UTC_TIMESTAMP() FOR UPDATE',[$hash])->fetch();
            if(!$row)return false;
            $db->execute('UPDATE players SET email_verified_at=UTC_TIMESTAMP() WHERE id=?',[$row['player_id']]);
            $db->execute('UPDATE email_verification_tokens SET used_at=UTC_TIMESTAMP() WHERE player_id=? AND used_at IS NULL',[$row['player_id']]);
            return true;
        });
    }

    private static function password(mixed $value): string
    {
        // Bcrypt processes at most 72 bytes; never silently truncate a new password.
        if(!is_string($value)||strlen($value)<10||strlen($value)>72)throw new \DomainException('Das Passwort benötigt mindestens 10 Zeichen und darf höchstens 72 UTF-8-Bytes lang sein.');return $value;
    }
}
