<?php
declare(strict_types=1);
namespace Conquer\Auth;

use Conquer\Db\Connection;

final class AccountService
{
    public static function state(int $playerId): array
    {
        $db=Connection::getInstance();return ['has_password'=>(bool)$db->query('SELECT password_hash FROM players WHERE id=?',[$playerId])->fetchColumn(),
            'has_recovery_code'=>(bool)$db->query('SELECT id FROM account_recovery_codes WHERE player_id=? AND used_at IS NULL',[$playerId])->fetchColumn(),
            'sessions'=>$db->query('SELECT id,user_agent,created_at,expires_at FROM sessions WHERE player_id=? AND expires_at>UTC_TIMESTAMP() ORDER BY id DESC',[$playerId])->fetchAll()];
    }

    public static function action(int $playerId,array $body): array
    {
        $db=Connection::getInstance();$limitKey='account:'.$playerId;
        if((int)$db->query('SELECT COUNT(*) FROM login_attempts WHERE ip_address=? AND attempted_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 15 MINUTE)',[$limitKey])->fetchColumn()>=10)throw new \DomainException('Zu viele Passwortversuche. Warte bitte 15 Minuten.');
        $db->execute('INSERT INTO login_attempts(ip_address) VALUES(?)',[$limitKey]);
        return $db->transaction(function()use($db,$playerId,$body,$limitKey):array{
            $row=$db->query('SELECT password_hash FROM players WHERE id=? FOR UPDATE',[$playerId])->fetch();
            $password=$body['current_password']??null;
            if(!is_string($password)||strlen($password)>200||!$row||!$row['password_hash']||!password_verify($password,$row['password_hash']))throw new \DomainException('Das aktuelle Passwort stimmt nicht.');
            $db->execute('DELETE FROM login_attempts WHERE ip_address=?',[$limitKey]);
            switch($body['action']??''){
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

    private static function password(mixed $value): string
    {
        // Bcrypt processes at most 72 bytes; never silently truncate a new password.
        if(!is_string($value)||strlen($value)<10||strlen($value)>72)throw new \DomainException('Das Passwort benötigt mindestens 10 Zeichen und darf höchstens 72 UTF-8-Bytes lang sein.');return $value;
    }
}
