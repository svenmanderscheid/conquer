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
 * Rate limit: max 5 login attempts per IP per minute, tracked in memory
 * via a dedicated DB table (admin_login_attempts reusing login_attempts).
 */
final class AdminAuth
{
    private const SESSION_KEY     = 'admin';
    private const MAX_ATTEMPTS    = 5;
    private const ATTEMPT_WINDOW  = 60; // seconds

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
        self::enforceRateLimit($ip);

        $db  = Connection::getInstance();
        $row = $db->query(
            'SELECT id, username, password_hash, role FROM admin_users WHERE username = ? LIMIT 1',
            [$username],
        )->fetch();

        if ($row === false || !password_verify($password, $row['password_hash'])) {
            self::recordFailedAttempt($ip);
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
        header('Location: /admin/login');
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
            header('Location: /admin/login');
            exit;
        }

        return $_SESSION[self::SESSION_KEY];
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
    ): int {
        $hash = password_hash($password, PASSWORD_ARGON2ID);

        $db = Connection::getInstance();
        $db->execute(
            'INSERT INTO admin_users (username, password_hash, role) VALUES (?, ?, ?)',
            [$username, $hash, $role],
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

    // -------------------------------------------------------------------------
    // Rate limiting (re-uses login_attempts table if present, else degrades)
    // -------------------------------------------------------------------------

    private static function enforceRateLimit(string $ip): void
    {
        try {
            $db = Connection::getInstance();
            $count = (int) $db->query(
                'SELECT COUNT(*) FROM login_attempts
                  WHERE ip = ?
                    AND attempted_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? SECOND)',
                [$ip, self::ATTEMPT_WINDOW],
            )->fetchColumn();

            if ($count >= self::MAX_ATTEMPTS) {
                throw new \RuntimeException('Too many login attempts. Please wait a minute.');
            }
        } catch (\RuntimeException $e) {
            // Re-throw rate limit exceptions as-is
            if (str_contains($e->getMessage(), 'Too many')) {
                throw $e;
            }
            // login_attempts table may not exist — degrade gracefully
        }
    }

    private static function recordFailedAttempt(string $ip): void
    {
        try {
            $db = Connection::getInstance();
            $db->execute(
                "INSERT INTO login_attempts (ip, attempted_at) VALUES (?, UTC_TIMESTAMP())",
                [$ip],
            );
        } catch (\Throwable) {
            // Degrade gracefully
        }
    }
}
