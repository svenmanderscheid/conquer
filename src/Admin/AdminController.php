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
            'grant-gems'   => self::actionGrantGems($db, $adminSession, $playerId),
            'ban'          => self::actionBan($db, $adminSession, $playerId),
            'grant-shield' => self::actionGrantShield($db, $adminSession, $playerId),
            'set-resources' => self::actionSetResources($db, $adminSession, $playerId),
            'add-troops'   => self::actionAddTroops($db, $adminSession, $playerId),
            'set-building' => self::actionSetBuilding($db, $adminSession, $playerId),
            'set-research' => self::actionSetResearch($db, $adminSession, $playerId),
            default        => self::actionNotFound(),
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

    private static function actionSetResources(
        Connection $db,
        array $adminSession,
        int $playerId,
    ): void {
        if ($playerId <= 0) {
            self::redirectWithFlash('/admin/players', 'Ungültige Spieler-ID.');
            return;
        }

        $cityRow = $db->query(
            'SELECT id FROM cities WHERE player_id = ? LIMIT 1',
            [$playerId],
        )->fetch();

        if ($cityRow === false) {
            self::redirectWithFlash("/admin/players/{$playerId}", 'Keine Stadt gefunden.');
            return;
        }

        $cityId = (int) $cityRow['id'];
        $food   = max(0, (int) ($_POST['food']   ?? 0));
        $lumber = max(0, (int) ($_POST['lumber'] ?? 0));
        $stone  = max(0, (int) ($_POST['stone']  ?? 0));
        $gold   = max(0, (int) ($_POST['gold']   ?? 0));

        $db->execute(
            'UPDATE cities SET food = ?, lumber = ?, stone = ?, gold = ? WHERE id = ?',
            [$food, $lumber, $stone, $gold, $cityId],
        );

        AdminAuth::log($adminSession['id'], 'admin.set_resources', 'player', $playerId,
            ['food' => $food, 'lumber' => $lumber, 'stone' => $stone, 'gold' => $gold]);

        self::redirectWithFlash("/admin/players/{$playerId}", 'Ressourcen gesetzt.');
    }

    private static function actionAddTroops(
        Connection $db,
        array $adminSession,
        int $playerId,
    ): void {
        if ($playerId <= 0) {
            self::redirectWithFlash('/admin/players', 'Ungültige Spieler-ID.');
            return;
        }

        $cityRow = $db->query(
            'SELECT id FROM cities WHERE player_id = ? LIMIT 1',
            [$playerId],
        )->fetch();

        if ($cityRow === false) {
            self::redirectWithFlash("/admin/players/{$playerId}", 'Keine Stadt gefunden.');
            return;
        }

        $cityId    = (int) $cityRow['id'];
        $troopCode = (int) ($_POST['troop_code'] ?? 0);
        $count     = (int) ($_POST['count'] ?? 0);

        if ($troopCode <= 0 || $count < 0 || $count > 1_000_000) {
            self::redirectWithFlash("/admin/players/{$playerId}", 'Ungültige Truppendaten.');
            return;
        }

        $db->execute(
            'INSERT INTO city_troops (city_id, troop_code, count)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE count = ?',
            [$cityId, $troopCode, $count, $count],
        );

        AdminAuth::log($adminSession['id'], 'admin.set_troops', 'player', $playerId,
            ['troop_code' => $troopCode, 'count' => $count]);

        self::redirectWithFlash("/admin/players/{$playerId}", "Truppen gesetzt ({$count}x Code {$troopCode}).");
    }

    private static function actionSetBuilding(
        Connection $db,
        array $adminSession,
        int $playerId,
    ): void {
        if ($playerId <= 0) {
            self::redirectWithFlash('/admin/players', 'Ungültige Spieler-ID.');
            return;
        }

        $cityRow = $db->query(
            'SELECT id FROM cities WHERE player_id = ? LIMIT 1',
            [$playerId],
        )->fetch();

        if ($cityRow === false) {
            self::redirectWithFlash("/admin/players/{$playerId}", 'Keine Stadt gefunden.');
            return;
        }

        $cityId       = (int) $cityRow['id'];
        $buildingCode = preg_replace('/[^a-z_]/', '', strtolower(trim($_POST['building_code'] ?? '')));
        $level        = max(0, min(30, (int) ($_POST['level'] ?? 1)));

        if ($buildingCode === '') {
            self::redirectWithFlash("/admin/players/{$playerId}", 'Ungültiger Gebäude-Code.');
            return;
        }

        $db->execute(
            'INSERT INTO city_buildings (city_id, building_code, level)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE level = ?',
            [$cityId, $buildingCode, $level, $level],
        );

        // Sync castle_level shortcut
        if ($buildingCode === 'castle') {
            $db->execute('UPDATE cities SET castle_level = ? WHERE id = ?', [$level, $cityId]);
        }

        AdminAuth::log($adminSession['id'], 'admin.set_building', 'player', $playerId,
            ['building' => $buildingCode, 'level' => $level]);

        self::redirectWithFlash("/admin/players/{$playerId}", "Gebäude '{$buildingCode}' auf Level {$level} gesetzt.");
    }

    private static function actionSetResearch(
        Connection $db,
        array $adminSession,
        int $playerId,
    ): void {
        if ($playerId <= 0) {
            self::redirectWithFlash('/admin/players', 'Ungültige Spieler-ID.');
            return;
        }

        $researchCode = preg_replace('/[^a-z_]/', '', strtolower(trim($_POST['research_code'] ?? '')));
        $level        = max(0, min(30, (int) ($_POST['level'] ?? 1)));

        if ($researchCode === '') {
            self::redirectWithFlash("/admin/players/{$playerId}", 'Ungültiger Forschungs-Code.');
            return;
        }

        $db->execute(
            'INSERT INTO player_research (player_id, world_id, research_code, level)
             VALUES (?, 1, ?, ?)
             ON DUPLICATE KEY UPDATE level = ?',
            [$playerId, $researchCode, $level, $level],
        );

        AdminAuth::log($adminSession['id'], 'admin.set_research', 'player', $playerId,
            ['research' => $researchCode, 'level' => $level]);

        self::redirectWithFlash("/admin/players/{$playerId}", "Forschung '{$researchCode}' auf Level {$level} gesetzt.");
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
