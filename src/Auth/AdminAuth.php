<?php
declare(strict_types=1);

namespace Conquer\Auth;

use Conquer\Db\Connection;

/**
 * AdminAuth — session-based authentication for the Admin Panel.
 *
 * Uses a separate PHP session name ('conquer_admin') from the game session.
 * Admin accounts are stored in admin_users — completely separate from
 * game player accounts.
 *
 * Atomic limits are shared across workers, by IP and account name.
 */
final class AdminAuth
{
    private const SESSION_KEY     = 'admin';

    // -------------------------------------------------------------------------
    // Login / Logout
    // -------------------------------------------------------------------------

    /**
     * Validate credentials and start an admin session.
     *
     * @return bool  true on success, false on invalid credentials
     * @throws \RuntimeException  when rate-limited
     */
    public static function login(string $username, string $password): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $retry = \Conquer\Security\RateLimit::consume('admin.ip', $ip, 5, 60);
        if (!$retry) $retry = \Conquer\Security\RateLimit::consume('admin.account', strtolower(trim($username)), 5, 900);
        if ($retry > 0) {
            header('Retry-After: ' . $retry);
            http_response_code(429);
            throw new \RuntimeException('Zu viele Anmeldeversuche. Bitte warte kurz.');
        }

        $db  = Connection::getInstance();
        $row = $db->query(
            'SELECT id, username, password_hash, role, must_change_password FROM admin_users WHERE username = ? LIMIT 1',
            [$username],
        )->fetch();

        if ($row === false || !password_verify($password, $row['password_hash'])) {
            return false;
        }

        // Update last_login_at
        $db->execute(
            'UPDATE admin_users SET last_login_at = UTC_TIMESTAMP() WHERE id = ?',
            [$row['id']],
        );

        // Regenerate session to prevent fixation
        session_regenerate_id(true);

        $_SESSION[self::SESSION_KEY] = [
            'id'       => (int) $row['id'],
            'username' => $row['username'],
            'role'     => $row['role'],
            'must_change_password' => (bool) $row['must_change_password'],
        ];

        self::log((int) $row['id'], 'admin.login');

        return true;
    }

    /**
     * Destroy the admin session and redirect to login.
     */
    public static function logout(): void
    {
        if (isset($_SESSION[self::SESSION_KEY])) {
            $admin = $_SESSION[self::SESSION_KEY];
            self::log((int) $admin['id'], 'admin.logout');
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly'],
            );
        }

        session_destroy();
        header('Location: ' . APP_BASE . '/admin/login');
        exit;
    }

    /**
     * Guard: return the admin session data or redirect to login.
     *
     * @return array{id: int, username: string, role: string}
     */
    public static function requireAuth(): array
    {
        if (!self::isLoggedIn()) {
            header('Location: ' . APP_BASE . '/admin/login');
            exit;
        }

        $admin = $_SESSION[self::SESSION_KEY];
        if (!empty($admin['must_change_password'])) {
            header('Location: ' . APP_BASE . '/admin/change-password');
            exit;
        }

        return $admin;
    }

    /**
     * Check whether an admin session is active.
     */
    public static function isLoggedIn(): bool
    {
        return isset($_SESSION[self::SESSION_KEY])
            && is_array($_SESSION[self::SESSION_KEY])
            && !empty($_SESSION[self::SESSION_KEY]['id']);
    }

    /** Return the active session without applying the password-change redirect. */
    public static function current(): ?array
    {
        return self::isLoggedIn() ? $_SESSION[self::SESSION_KEY] : null;
    }

    public static function mustChangePassword(): bool
    {
        return !empty($_SESSION[self::SESSION_KEY]['must_change_password']);
    }

    /** Replace an initial password and unlock the remaining admin area. */
    public static function changeRequiredPassword(string $currentPassword, string $newPassword): void
    {
        $admin = self::current();
        if ($admin === null) throw new \DomainException('Bitte melde dich erneut an.');
        if (!self::mustChangePassword()) throw new \DomainException('Für dieses Konto ist kein Passwortwechsel erforderlich.');
        if (strlen($newPassword) < 14 || strlen($newPassword) > 200) throw new \DomainException('Das neue Passwort muss mindestens 14 Zeichen lang sein.');
        if (strcasecmp($newPassword, (string) $admin['username']) === 0) throw new \DomainException('Benutzername und Passwort dürfen nicht identisch sein.');

        $db = Connection::getInstance();
        $row = $db->query('SELECT password_hash FROM admin_users WHERE id = ? LIMIT 1', [(int) $admin['id']])->fetch();
        if ($row === false || !password_verify($currentPassword, (string) $row['password_hash'])) throw new \DomainException('Das bisherige Passwort ist nicht korrekt.');
        if (password_verify($newPassword, (string) $row['password_hash'])) throw new \DomainException('Wähle ein neues Passwort.');

        $hash = password_hash($newPassword, PASSWORD_ARGON2ID);
        $db->execute('UPDATE admin_users SET password_hash = ?, must_change_password = 0 WHERE id = ?', [$hash, (int) $admin['id']]);
        $_SESSION[self::SESSION_KEY]['must_change_password'] = false;
        session_regenerate_id(true);
        self::log((int) $admin['id'], 'admin.password_changed');
    }

    // -------------------------------------------------------------------------
    // Admin management
    // -------------------------------------------------------------------------

    /**
     * Create a new admin account. Returns the new admin's id.
     */
    public static function createAdmin(
        string $username,
        string $password,
        string $role = 'moderator',
        bool $mustChangePassword = false,
    ): int {
        $hash = password_hash($password, PASSWORD_ARGON2ID);

        $db = Connection::getInstance();
        $db->execute(
            'INSERT INTO admin_users (username, password_hash, role, must_change_password) VALUES (?, ?, ?, ?)',
            [$username, $hash, $role, $mustChangePassword ? 1 : 0],
        );

        return $db->lastInsertId();
    }

    // -------------------------------------------------------------------------
    // Audit logging
    // -------------------------------------------------------------------------

    /**
     * Append an entry to admin_audit_log.
     *
     * @param array<string, mixed> $details  Arbitrary JSON payload
     */
    public static function log(
        int $adminId,
        string $action,
        ?string $targetType = null,
        ?int $targetId = null,
        array $details = [],
    ): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        try {
            $db = Connection::getInstance();
            $db->execute(
                'INSERT INTO admin_audit_log
                    (admin_id, action, target_type, target_id, details, ip)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [
                    $adminId,
                    $action,
                    $targetType,
                    $targetId,
                    empty($details) ? null : json_encode($details, JSON_UNESCAPED_UNICODE),
                    $ip,
                ],
            );
        } catch (\Throwable) {
            // Audit log failure must never crash the request
        }
    }

}
