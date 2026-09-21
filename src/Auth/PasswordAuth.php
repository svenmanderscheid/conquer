<?php
declare(strict_types=1);
namespace Conquer\Auth;

use Conquer\Db\Connection;

/** Password accounts share the same cities and sessions as OAuth accounts. */
final class PasswordAuth
{
    public static function submit(): string
    {
        if (!is_string($_POST['csrf'] ?? null) || empty($_SESSION['login_csrf']) || !hash_equals($_SESSION['login_csrf'], $_POST['csrf'])) {
            return 'Die Sitzung ist abgelaufen. Bitte lade die Seite neu.';
        }
        $db = Connection::getInstance();
        $mode=($_POST['mode']??'')==='register'?'register':'login';
        $name = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
        $email = is_string($_POST['email'] ?? null) ? strtolower(trim($_POST['email'])) : '';
        $identifier=$mode==='register'?$email:(is_string($_POST['identifier']??null)?trim($_POST['identifier']):$name);
        $retry = \Conquer\Security\RateLimit::consume('login.ip', \Conquer\Security\RateLimit::ip(), 5, 60);
        if (!$retry) $retry = \Conquer\Security\RateLimit::consume('login.account', strtolower($identifier), 10, 900);
        if ($retry > 0) {
            http_response_code(429);
            header('Retry-After: ' . $retry);
            return 'Zu viele Versuche. Bitte warte kurz.';
        }
        $ip = substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45);
        $attempts = (int) $db->query('SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempted_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE)', [$ip])->fetchColumn();
        if ($attempts >= 5) { return 'Zu viele Versuche. Bitte warte eine Minute.'; }
        $db->execute('INSERT INTO login_attempts (ip_address) VALUES (?)', [$ip]);
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        if (strlen($password) > 200) return 'Ungültige Anmeldedaten.';
        if ($mode === 'register') {
            if(!preg_match('/^[A-Za-z0-9_]{3,25}$/',$name))return 'Dein Name braucht 3–25 Zeichen: Buchstaben, Zahlen oder Unterstriche.';
            if(strlen($email)>254||!filter_var($email,FILTER_VALIDATE_EMAIL))return 'Bitte gib eine gültige E-Mail-Adresse ein.';
            if (strlen($password) < 10 || strlen($password) > 72) { return 'Bitte wähle ein Passwort mit 10 bis 72 UTF-8-Bytes.'; }
            try {
                $alphaKey = $_POST['alpha_key'] ?? null;
                $id = $db->transaction(static function (Connection $db) use ($name,$email,$password,$alphaKey): int {
                    $alphaKeyId = AlphaAccess::consume($db, $alphaKey);
                    $db->execute('INSERT INTO players (username,email,password_hash,alpha_access_key_id,last_login) VALUES (?,?,?,?,UTC_TIMESTAMP())',
                        [$name,$email,password_hash($password,PASSWORD_DEFAULT),$alphaKeyId]);
                    $id = $db->lastInsertId();
                    OAuth::createDefaultCity($db, $id, $name);
                    return $id;
                });
                AccountService::sendVerification($id);
            } catch (\DomainException $e) {return $e->getMessage();
            } catch (\PDOException $e) {
                if ($e->getCode() === '23000') { return 'Dieser Spielername oder diese E-Mail-Adresse ist bereits vergeben.'; }
                throw $e;
            }
        } else {
            if($identifier===''||strlen($identifier)>254)return 'E-Mail oder Spielername und Passwort stimmen nicht.';
            $player = $db->query('SELECT id,password_hash,is_banned FROM players WHERE email = ? OR username = ? LIMIT 1', [strtolower($identifier),$identifier])->fetch();
            if (!$player || !$player['password_hash'] || !password_verify($password, $player['password_hash']) || $player['is_banned']) {
                return 'E-Mail oder Spielername und Passwort stimmen nicht.';
            }
            $id = (int) $player['id'];
            $db->execute('UPDATE players SET last_login = UTC_TIMESTAMP() WHERE id = ?', [$id]);
        }
        Session::create($id, $ip, $_SERVER['HTTP_USER_AGENT'] ?? '');
        session_regenerate_id(true);
        header('Location: ' . APP_BASE . '/city');
        exit;
    }
}
