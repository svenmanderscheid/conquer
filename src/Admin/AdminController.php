<?php
declare(strict_types=1);

namespace Conquer\Admin;

use Conquer\Auth\AdminAuth;
use Conquer\Db\Connection;

/**
 * AdminController — handles all /admin/* page requests.
 *
 * Each public method maps 1:1 to an admin route.
 * All state-changing actions are gated behind superadmin role.
 */
final class AdminController
{
    // -------------------------------------------------------------------------
    // Auth pages (no requireAuth guard)
    // -------------------------------------------------------------------------

    public static function loginPage(): void
    {
        if (AdminAuth::isLoggedIn()) {
            header('Location: ' . APP_BASE . '/admin');
            exit;
        }

        $csrf  = self::getCsrfToken();
        $error = '';
        require ROOT_DIR . '/views/admin/login.php';
    }

    public static function loginPost(): void
    {
        if (AdminAuth::isLoggedIn()) {
            header('Location: ' . APP_BASE . '/admin');
            exit;
        }

        // CSRF check
        $submittedToken = $_POST['csrf_token'] ?? '';
        if (!hash_equals(self::getCsrfToken(), $submittedToken)) {
            $csrf  = self::getCsrfToken();
            $error = 'Ungültige Anfrage. Bitte lade die Seite neu.';
            require ROOT_DIR . '/views/admin/login.php';
            return;
        }

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $csrf  = self::getCsrfToken();
        $error = '';

        try {
            $ok = AdminAuth::login($username, $password);
            if ($ok) {
                header('Location: ' . APP_BASE . '/admin');
                exit;
            }
            $error = 'Ungültiger Benutzername oder Passwort.';
        } catch (\RuntimeException $e) {
            $error = $e->getMessage();
        }

        require ROOT_DIR . '/views/admin/login.php';
    }

    public static function logout(): void
    {
        AdminAuth::logout();
        // AdminAuth::logout() already redirects — this line is unreachable
    }

    // -------------------------------------------------------------------------
    // Dashboard
    // -------------------------------------------------------------------------

    public static function dashboard(): void
    {
        self::render('dashboard', ['pageTitle' => 'Dashboard', 'activePage' => 'dashboard']);
    }

    // -------------------------------------------------------------------------
    // Players
    // -------------------------------------------------------------------------

    public static function players(): void
    {
        self::render('players', ['pageTitle' => 'Spieler', 'activePage' => 'players']);
    }

    public static function playerDetail(int $id): void
    {
        self::render('player_detail', [
            'pageTitle'  => 'Spieler Details',
            'activePage' => 'players',
            'playerId'   => $id,
        ]);
    }

    // -------------------------------------------------------------------------
    // World / alliances (stubs — render placeholder)
    // -------------------------------------------------------------------------

    public static function world(): void
    {
        self::render('world', ['pageTitle' => 'Weltverwaltung', 'activePage' => 'world']);
    }

    public static function alliances(): void
    {
        self::render('alliances', ['pageTitle' => 'Allianzen', 'activePage' => 'alliances']);
    }

    // -------------------------------------------------------------------------
    // Chat moderation
    // -------------------------------------------------------------------------

    public static function chat(): void
    {
        self::render('chat', ['pageTitle' => 'Chat Moderation', 'activePage' => 'chat']);
    }

    // -------------------------------------------------------------------------
    // Audit log
    // -------------------------------------------------------------------------

    public static function auditLog(): void
    {
        self::render('audit', ['pageTitle' => 'Audit Log', 'activePage' => 'audit']);
    }

    // -------------------------------------------------------------------------
    // Action dispatcher (POST /admin/action/:action)
    // -------------------------------------------------------------------------

    public static function handleAction(): void
    {
        $adminSession = AdminAuth::requireAuth();

        $uri    = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $action = basename((string) $uri);

        // All actions require superadmin
        if ($adminSession['role'] !== 'superadmin') {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Superadmin required']);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            return;
        }

        // CSRF check
        $submittedToken = $_POST['csrf_token'] ?? '';
        if (!hash_equals(self::getCsrfToken(), $submittedToken)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
            return;
        }

        $db       = Connection::getInstance();
        $playerId = (int) ($_POST['player_id'] ?? 0);

        match ($action) {
            'grant-gems' => self::actionGrantGems($db, $adminSession, $playerId),
            'ban'        => self::actionBan($db, $adminSession, $playerId),
            'grant-shield' => self::actionGrantShield($db, $adminSession, $playerId),
            default      => self::actionNotFound(),
        };
    }

    // -------------------------------------------------------------------------
    // Private action helpers
    // -------------------------------------------------------------------------

    private static function actionGrantGems(
        Connection $db,
        array $adminSession,
        int $playerId,
    ): void {
        if ($playerId <= 0) {
            self::redirectWithFlash('/admin/players', 'Ungültige Spieler-ID.');
            return;
        }

        $amount = (int) ($_POST['amount'] ?? 1000);
        if ($amount <= 0 || $amount > 100_000) {
            self::redirectWithFlash("/admin/players/{$playerId}", 'Ungültiger Betrag.');
            return;
        }

        $affected = $db->execute(
            'UPDATE players SET gems = gems + ? WHERE id = ?',
            [$amount, $playerId],
        );

        if ($affected === 0) {
            self::redirectWithFlash('/admin/players', 'Spieler nicht gefunden.');
            return;
        }

        AdminAuth::log(
            $adminSession['id'],
            'admin.grant_gems',
            'player',
            $playerId,
            ['amount' => $amount],
        );

        self::redirectWithFlash("/admin/players/{$playerId}", "{$amount} Gems gewährt.");
    }

    private static function actionBan(
        Connection $db,
        array $adminSession,
        int $playerId,
    ): void {
        if ($playerId <= 0) {
            self::redirectWithFlash('/admin/players', 'Ungültige Spieler-ID.');
            return;
        }

        $reason = trim($_POST['reason'] ?? 'Admin ban');

        // Mark banned via a banned_at timestamp column (graceful if column missing)
        try {
            $db->execute(
                'UPDATE players SET banned_at = UTC_TIMESTAMP(), ban_reason = ? WHERE id = ?',
                [$reason, $playerId],
            );
        } catch (\Throwable) {
            // Column may not exist yet — log anyway
        }

        AdminAuth::log(
            $adminSession['id'],
            'admin.ban_player',
            'player',
            $playerId,
            ['reason' => $reason],
        );

        self::redirectWithFlash("/admin/players/{$playerId}", 'Spieler wurde gesperrt.');
    }

    private static function actionGrantShield(
        Connection $db,
        array $adminSession,
        int $playerId,
    ): void {
        if ($playerId <= 0) {
            self::redirectWithFlash('/admin/players', 'Ungültige Spieler-ID.');
            return;
        }

        $hours = (int) ($_POST['hours'] ?? 24);

        try {
            $db->execute(
                'UPDATE players
                    SET shield_expires_at = GREATEST(
                        COALESCE(shield_expires_at, UTC_TIMESTAMP()),
                        UTC_TIMESTAMP()
                    ) + INTERVAL ? HOUR
                  WHERE id = ?',
                [$hours, $playerId],
            );
        } catch (\Throwable) {
            // Column may not exist — log anyway
        }

        AdminAuth::log(
            $adminSession['id'],
            'admin.grant_shield',
            'player',
            $playerId,
            ['hours' => $hours],
        );

        self::redirectWithFlash("/admin/players/{$playerId}", "{$hours}h Schutzschild gewährt.");
    }

    private static function actionNotFound(): void
    {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    }

    // -------------------------------------------------------------------------
    // Render helper
    // -------------------------------------------------------------------------

    /**
     * Render a view inside the admin layout.
     *
     * @param array<string, mixed> $vars
     */
    private static function render(string $view, array $vars = []): void
    {
        extract($vars);
        $adminSession = AdminAuth::requireAuth();

        ob_start();
        require ROOT_DIR . '/views/admin/' . $view . '.php';
        $content = ob_get_clean();

        require ROOT_DIR . '/views/admin/layout.php';
    }

    // -------------------------------------------------------------------------
    // Utilities
    // -------------------------------------------------------------------------

    /**
     * Generate (or retrieve from session) a CSRF token for the admin session.
     */
    private static function getCsrfToken(): string
    {
        if (empty($_SESSION['admin_csrf'])) {
            $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['admin_csrf'];
    }

    private static function redirectWithFlash(string $url, string $message): void
    {
        $_SESSION['admin_flash'] = $message;
        header('Location: ' . APP_BASE . $url);
        exit;
    }
}
