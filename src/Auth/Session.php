<?php
declare(strict_types=1);

namespace Conquer\Auth;

use Conquer\Db\Connection;

/**
 * DB-backed session management.
 *
 * Sessions are stored in the `sessions` table.
 * The session token travels as an httpOnly cookie.
 */
final class Session
{
    public const COOKIE_NAME    = 'conquer_session';
    private const LIFETIME_SECS = 60 * 60 * 24 * 7; // 7 days

    /** Cached session data for the current request. */
    private static ?array $current = null;

    // -------------------------------------------------------------------------
    // Create / destroy
    // -------------------------------------------------------------------------

    public static function create(int $playerId, string $ip, string $userAgent): void
    {
        $db         = Connection::getInstance();
        $token      = bin2hex(random_bytes(32));
        $csrfToken  = bin2hex(random_bytes(32));
        $expiresAt  = gmdate('Y-m-d H:i:s', time() + self::LIFETIME_SECS);
        $activeWorld=(int)($db->query('SELECT world_id FROM cities WHERE player_id=? ORDER BY world_id LIMIT 1',[$playerId])->fetchColumn()?:1);

        $db->execute(
            'INSERT INTO sessions (player_id, token, csrf_token, ip_address, user_agent, expires_at, active_world_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$playerId, $token, $csrfToken, $ip, substr($userAgent, 0, 255), $expiresAt,$activeWorld],
        );

        self::setCookie($token, self::LIFETIME_SECS);
        self::$current = null; // force re-load on next current() call
    }

    public static function destroy(): void
    {
        $token = $_COOKIE[self::COOKIE_NAME] ?? null;
        if ($token !== null && self::isValidTokenFormat($token)) {
            Connection::getInstance()->execute(
                'DELETE FROM sessions WHERE token = ?',
                [$token],
            );
        }
        self::$current = null;
        \Conquer\Game\World\WorldContext::bind(1);
        self::setCookie('', -1);
    }

    // -------------------------------------------------------------------------
    // Load current session
    // -------------------------------------------------------------------------

    /**
     * Returns the session row (joined with players) for the current request,
     * or null if there is no valid session cookie.
     *
     * @return array<string, mixed>|null
     */
    public static function current(): ?array
    {
        if (self::$current !== null) {
            return self::$current;
        }

        $token = $_COOKIE[self::COOKIE_NAME] ?? null;
        if ($token === null || !self::isValidTokenFormat($token)) {
            return null;
        }

        $db  = Connection::getInstance();
        $row = $db->query(
            'SELECT s.id, s.player_id, s.csrf_token, s.expires_at,s.active_world_id,
                    p.username, p.is_banned, p.vip_level, p.gems
             FROM   sessions s
             JOIN   players  p ON p.id = s.player_id
             WHERE  s.token = ?
               AND  s.expires_at > UTC_TIMESTAMP()',
            [$token],
        )->fetch();

        if ($row === false) {
            return null;
        }

        if ((int) $row['is_banned'] === 1) {
            self::destroy();
            return null;
        }
        $owned=$db->query('SELECT id FROM cities WHERE player_id=? AND world_id=?',[$row['player_id'],$row['active_world_id']])->fetchColumn();
        if(!$owned){$fallback=$db->query('SELECT world_id FROM cities WHERE player_id=? ORDER BY world_id LIMIT 1',[$row['player_id']])->fetchColumn();if($fallback){$row['active_world_id']=(int)$fallback;$db->execute('UPDATE sessions SET active_world_id=? WHERE id=?',[$fallback,$row['id']]);}}
        \Conquer\Game\World\WorldContext::bind((int)$row['active_world_id'],(int)$row['player_id']);

        // Touch last_active — best-effort, no exception on failure
        try {
            $db->execute(
                'UPDATE sessions SET last_active = UTC_TIMESTAMP() WHERE id = ?',
                [(int) $row['id']],
            );
            // One durable row per active minute: accurate enough for operations,
            // bounded in size, and independent from session cleanup/revocation.
            $db->execute(
                'INSERT IGNORE INTO player_activity_minutes(player_id,world_id,minute_slot) VALUES(?,?,DATE_FORMAT(UTC_TIMESTAMP(),\'%Y-%m-%d %H:%i:00\'))',
                [(int)$row['player_id'],(int)$row['active_world_id']],
            );
        } catch (\Throwable) {
            // non-critical
        }

        self::$current = $row;
        return $row;
    }

    public static function isLoggedIn(): bool
    {
        return self::current() !== null;
    }

    /** The world service already persists and validates selection before updating the request cache. */
    public static function setActiveWorld(int $worldId): void
    {
        if(self::$current!==null)self::$current['active_world_id']=$worldId;
        \Conquer\Game\World\WorldContext::bind($worldId,self::$current===null?null:(int)self::$current['player_id']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private static function isValidTokenFormat(string $token): bool
    {
        return strlen($token) === 64 && ctype_xdigit($token);
    }

    private static function setCookie(string $value, int $maxAge): void
    {
        setcookie(self::COOKIE_NAME, $value, [
            'expires'  => time() + $maxAge,
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
