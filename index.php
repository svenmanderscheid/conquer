<?php
declare(strict_types=1);

/**
 * Conquer — Front Controller
 *
 * Every request enters here. Bootstrap initializes the application,
 * then a stub router dispatches to the appropriate handler.
 *
 * Sprint 1: Router is a stub — API returns 501, everything else gets
 * the landing page. Real routing lands in Task 1.6.
 */

define('ROOT_DIR', __DIR__);

require_once ROOT_DIR . '/src/Bootstrap.php';
\Conquer\Bootstrap::init(ROOT_DIR);

// ---------------------------------------------------------------------------
// Admin Panel — separate session, separate auth, no game session needed
// ---------------------------------------------------------------------------

if (str_starts_with((string) ($_SERVER['REQUEST_URI'] ?? '/'), '/admin')) {
    session_name('conquer_admin');
    session_start();

    $adminUri = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $adminUri = '/' . ltrim((string) $adminUri, '/');

    $m = [];
    match (true) {
        $adminUri === '/admin/login' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
            => \Conquer\Admin\AdminController::loginPage(),
        $adminUri === '/admin/login' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
            => \Conquer\Admin\AdminController::loginPost(),
        $adminUri === '/admin/logout'
            => \Conquer\Admin\AdminController::logout(),
        $adminUri === '/admin' || $adminUri === '/admin/'
            => \Conquer\Admin\AdminController::dashboard(),
        $adminUri === '/admin/players'
            => \Conquer\Admin\AdminController::players(),
        (bool) preg_match('#^/admin/players/(\d+)$#', $adminUri, $m)
            => \Conquer\Admin\AdminController::playerDetail((int) $m[1]),
        $adminUri === '/admin/alliances'
            => \Conquer\Admin\AdminController::alliances(),
        $adminUri === '/admin/world'
            => \Conquer\Admin\AdminController::world(),
        $adminUri === '/admin/chat'
            => \Conquer\Admin\AdminController::chat(),
        $adminUri === '/admin/audit'
            => \Conquer\Admin\AdminController::auditLog(),
        str_starts_with($adminUri, '/admin/action')
            => \Conquer\Admin\AdminController::handleAction(),
        default => (static function (): void {
            http_response_code(404);
            echo '<!DOCTYPE html><html lang="de"><body style="background:#0f172a;color:#f87171;font-family:system-ui;padding:40px;">'
                . '<h2>404 &mdash; Admin-Seite nicht gefunden</h2></body></html>';
        })(),
    };
    exit;
}

// ---------------------------------------------------------------------------
// Router
// ---------------------------------------------------------------------------

$path   = '/' . ltrim((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)), '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// JSON API — all /api/* requests are handled here.
if (str_starts_with($path, '/api/')) {
    $router  = new \Conquer\Router();
    $session = \Conquer\Auth\Session::current() ?? [];

    // Auth
    $router->get('/api/auth/me',      [\Conquer\Api\Handlers\AuthHandler::class, 'me']);
    $router->post('/api/auth/logout', [\Conquer\Api\Handlers\AuthHandler::class, 'logout']);

    // City
    $router->get('/api/city/state',                         [\Conquer\Api\Handlers\CityHandler::class, 'state']);
    $router->post('/api/city/upgrade-building',             [\Conquer\Api\Handlers\CityHandler::class, 'upgradeBuilding']);
    $router->post('/api/city/instant-build/:queue_id',      [\Conquer\Api\Handlers\CityHandler::class, 'instantBuild']);
    $router->post('/api/city/wall-repair',                  fn() => \Conquer\Api\Handlers\CityHandler::repairWall($session));

    // Troops
    $router->get('/api/troops/list',         [\Conquer\Api\Handlers\TroopHandler::class, 'list']);
    $router->post('/api/troops/train',       [\Conquer\Api\Handlers\TroopHandler::class, 'train']);

    // March
    $router->post('/api/march/dispatch',         [\Conquer\Api\Handlers\MarchHandler::class, 'dispatch']);
    $router->post('/api/march/dispatch-charm',   [\Conquer\Api\Handlers\MarchHandler::class, 'dispatchCharm']);
    $router->post('/api/march/dispatch-player',  [\Conquer\Api\Handlers\MarchHandler::class, 'dispatchPlayer']);
    $router->post('/api/march/dispatch-scout',   [\Conquer\Api\Handlers\MarchHandler::class, 'dispatchScout']);
    $router->post('/api/march/dispatch-gather',  fn() => \Conquer\Api\Handlers\MarchHandler::dispatchGather($session));
    $router->post('/api/march/recall',           fn() => \Conquer\Api\Handlers\MarchHandler::recall($session));
    $router->get('/api/march/list',              [\Conquer\Api\Handlers\MarchHandler::class, 'list']);
    $router->get('/api/map/marches',             [\Conquer\Api\Handlers\MarchHandler::class, 'listAll']);

    // Battle reports
    $router->get('/api/battle/reports',      [\Conquer\Api\Handlers\BattleHandler::class, 'reports']);
    $router->get('/api/battle/report/:id',   [\Conquer\Api\Handlers\BattleHandler::class, 'report']);

    // Map
    $router->get('/api/map/info',              [\Conquer\Api\Handlers\MapHandler::class, 'info']);
    $router->get('/api/map/tiles',             [\Conquer\Api\Handlers\MapHandler::class, 'tiles']);
    $router->get('/api/map/tile/:x/:y',        [\Conquer\Api\Handlers\MapHandler::class, 'tile']);
    $router->get('/api/map/field-object/:id',  fn($p) => \Conquer\Api\Handlers\MapHandler::fieldObject($session, (int) $p['id']));

    // Research
    $router->get('/api/research/state',      [\Conquer\Api\Handlers\ResearchHandler::class, 'state']);
    $router->post('/api/research/start',     [\Conquer\Api\Handlers\ResearchHandler::class, 'start']);
    $router->post('/api/research/instant',   [\Conquer\Api\Handlers\ResearchHandler::class, 'instant']);

    // Trading / Caravan
    $router->get('/api/trading/caravan',      [\Conquer\Api\Handlers\TradingHandler::class, 'caravan']);
    $router->post('/api/trading/caravan/buy', [\Conquer\Api\Handlers\TradingHandler::class, 'buy']);

    // Alliance
    $router->get('/api/alliance/my',            [\Conquer\Api\Handlers\AllianceHandler::class, 'my']);
    $router->post('/api/alliance/create',        [\Conquer\Api\Handlers\AllianceHandler::class, 'create']);
    $router->post('/api/alliance/join',          [\Conquer\Api\Handlers\AllianceHandler::class, 'join']);
    $router->post('/api/alliance/leave',         [\Conquer\Api\Handlers\AllianceHandler::class, 'leave']);
    $router->get('/api/alliance/search',         [\Conquer\Api\Handlers\AllianceHandler::class, 'search']);
    $router->get('/api/alliance/members',        [\Conquer\Api\Handlers\AllianceHandler::class, 'members']);
    $router->get('/api/alliance/chat',           [\Conquer\Api\Handlers\AllianceHandler::class, 'chat']);
    $router->post('/api/alliance/chat',          [\Conquer\Api\Handlers\AllianceHandler::class, 'sendChat']);
    $router->get('/api/alliance/help-requests',  fn() => \Conquer\Api\Handlers\AllianceHandler::helpRequests($session));
    $router->post('/api/alliance/help',          fn() => \Conquer\Api\Handlers\AllianceHandler::help($session));
    $router->get('/api/alliance/treasury',       fn() => \Conquer\Api\Handlers\AllianceHandler::treasury($session));
    $router->post('/api/alliance/donate',        fn() => \Conquer\Api\Handlers\AllianceHandler::donate($session));
    $router->get('/api/alliance/diplomacy',      fn() => \Conquer\Api\Handlers\AllianceHandler::getDiplomacy($session));
    $router->post('/api/alliance/diplomacy',     fn() => \Conquer\Api\Handlers\AllianceHandler::setDiplomacy($session));
    $router->delete('/api/alliance/diplomacy/:id', fn($p) => \Conquer\Api\Handlers\AllianceHandler::removeDiplomacy($session, (int) $p['id']));

    // World Chat
    $router->get('/api/world-chat',              fn() => \Conquer\Api\Handlers\AllianceHandler::worldChat($session));
    $router->post('/api/world-chat/send',        fn() => \Conquer\Api\Handlers\AllianceHandler::sendWorldChat($session));

    // Player
    $router->get('/api/player/me',                    [\Conquer\Api\Handlers\PlayerHandler::class, 'me']);
    $router->get('/api/player/profile/:id',           [\Conquer\Api\Handlers\PlayerHandler::class, 'profile']);
    $router->get('/api/player/formations',            [\Conquer\Api\Handlers\PlayerHandler::class, 'formations']);
    $router->post('/api/player/formations/:slot',     [\Conquer\Api\Handlers\PlayerHandler::class, 'saveFormation']);
    $router->post('/api/player/emoji',                [\Conquer\Api\Handlers\PlayerHandler::class, 'setEmoji']);
    $router->get('/api/player/skins',                 [\Conquer\Api\Handlers\PlayerHandler::class, 'skins']);
    $router->post('/api/player/skin/equip',           [\Conquer\Api\Handlers\PlayerHandler::class, 'equipSkin']);

    // Rally
    $router->post('/api/rally/start',  [\Conquer\Api\Handlers\RallyHandler::class, 'start']);
    $router->post('/api/rally/join',   [\Conquer\Api\Handlers\RallyHandler::class, 'join']);
    $router->get('/api/rally/list',    [\Conquer\Api\Handlers\RallyHandler::class, 'list']);
    $router->get('/api/rally/:id',     [\Conquer\Api\Handlers\RallyHandler::class, 'detail']);

    // Shrine System
    $router->get('/api/shrines',                  fn() => \Conquer\Api\Handlers\ShrineHandler::list($session));
    $router->get('/api/shrines/:id',              fn($p) => \Conquer\Api\Handlers\ShrineHandler::detail($session, (int) $p['id']));
    $router->post('/api/shrines/:id/garrison',    fn($p) => \Conquer\Api\Handlers\ShrineHandler::garrison($session, (int) $p['id']));
    $router->post('/api/shrines/:id/recall',      fn($p) => \Conquer\Api\Handlers\ShrineHandler::recall($session, (int) $p['id']));

    // Conquest Event
    $router->get('/api/conquest/event',           fn() => \Conquer\Api\Handlers\ConquestHandler::current($session));
    $router->get('/api/conquest/leaderboard',     fn() => \Conquer\Api\Handlers\ConquestHandler::leaderboard($session));

    // Hospital
    $router->get('/api/hospital/status',        [\Conquer\Api\Handlers\HospitalHandler::class, 'status']);
    $router->post('/api/hospital/instant-heal', [\Conquer\Api\Handlers\HospitalHandler::class, 'instantHeal']);

    // Inventory
    $router->get('/api/inventory',      [\Conquer\Api\Handlers\InventoryHandler::class, 'list']);
    $router->post('/api/inventory/use', [\Conquer\Api\Handlers\InventoryHandler::class, 'use']);

    // Treasure
    $router->get('/api/treasure/list',         [\Conquer\Api\Handlers\TreasureHandler::class, 'list']);
    $router->post('/api/treasure/equip',       [\Conquer\Api\Handlers\TreasureHandler::class, 'equip']);
    $router->post('/api/treasure/unequip',     [\Conquer\Api\Handlers\TreasureHandler::class, 'unequip']);
    $router->get('/api/treasure/chest-status', [\Conquer\Api\Handlers\TreasureHandler::class, 'chestStatus']);
    $router->post('/api/treasure/open-chest',  [\Conquer\Api\Handlers\TreasureHandler::class, 'openChest']);

    // Daily quests
    $router->get('/api/quests/daily',  [\Conquer\Api\Handlers\QuestHandler::class, 'list']);
    $router->post('/api/quests/claim', [\Conquer\Api\Handlers\QuestHandler::class, 'claim']);

    // Notifications / poll
    $router->get('/api/notifications/poll',  [\Conquer\Api\Handlers\NotificationHandler::class, 'poll']);
    $router->post('/api/notifications/read', [\Conquer\Api\Handlers\NotificationHandler::class, 'markRead']);

    if (!$router->dispatch($method, $path)) {
        \Conquer\Api\Response::error(404, 'NOT_FOUND', 'API endpoint not found.');
    }
    exit;
}

// Auth routes
if (preg_match('#^/auth/(google|discord)$#', $path, $m)) {
    $provider = $m[1];
    try {
        $oauth = new \Conquer\Auth\OAuth(\Conquer\Bootstrap::getConfig()['oauth'] ?? []);
        header('Location: ' . $oauth->redirectUrl($provider));
    } catch (\Throwable $e) {
        \Conquer\Logger::getInstance()->error('OAuth redirect failed: ' . $e->getMessage());
        header('Location: /?auth_error=config');
    }
    exit;
}

if (preg_match('#^/auth/(google|discord)/callback$#', $path, $m)) {
    $provider = $m[1];
    $code     = $_GET['code']  ?? '';
    $state    = $_GET['state'] ?? '';

    if ($code === '' || $state === '') {
        header('Location: /?auth_error=cancelled');
        exit;
    }

    try {
        $oauth    = new \Conquer\Auth\OAuth(\Conquer\Bootstrap::getConfig()['oauth'] ?? []);
        $playerId = $oauth->handleCallback($provider, $code, $state);

        \Conquer\Auth\Session::create(
            $playerId,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
        );
        header('Location: /');
    } catch (\Throwable $e) {
        \Conquer\Logger::getInstance()->error('OAuth callback failed: ' . $e->getMessage());
        header('Location: /?auth_error=failed');
    }
    exit;
}

if ($path === '/auth/logout') {
    \Conquer\Auth\Session::destroy();
    header('Location: /');
    exit;
}

// ---------------------------------------------------------------------------
// Battle reports view
// ---------------------------------------------------------------------------

if ($path === '/reports') {
    $session = \Conquer\Auth\Session::current();
    if ($session === null) {
        header('Location: /');
        exit;
    }
    require ROOT_DIR . '/views/reports.php';
    exit;
}

if (preg_match('#^/reports/(\d+)$#', $path, $m)) {
    $session = \Conquer\Auth\Session::current();
    if ($session === null) {
        header('Location: /');
        exit;
    }
    $reportId = (int) $m[1];
    require ROOT_DIR . '/views/report_detail.php';
    exit;
}

// ---------------------------------------------------------------------------
// Alliance view
// ---------------------------------------------------------------------------

if ($path === '/alliance') {
    $session = \Conquer\Auth\Session::current();
    if ($session === null) { header('Location: /'); exit; }
    require ROOT_DIR . '/views/alliance.php';
    exit;
}

if ($path === '/alliance/battle') {
    $session = \Conquer\Auth\Session::current();
    if ($session === null) { header('Location: /'); exit; }
    require ROOT_DIR . '/views/alliance_battle.php';
    exit;
}

// ---------------------------------------------------------------------------
// Research view
// ---------------------------------------------------------------------------

if ($path === '/research') {
    $session = \Conquer\Auth\Session::current();
    if ($session === null) {
        header('Location: /');
        exit;
    }
    require ROOT_DIR . '/views/research.php';
    exit;
}

// ---------------------------------------------------------------------------
// Map view (Sprint 2)
// ---------------------------------------------------------------------------

if ($path === '/map') {
    $session = \Conquer\Auth\Session::current();
    if ($session === null) {
        header('Location: /');
        exit;
    }
    require ROOT_DIR . '/views/map.php';
    exit;
}

// ---------------------------------------------------------------------------
// City view (Task 1.7)
// ---------------------------------------------------------------------------

if ($path === '/city') {
    $session = \Conquer\Auth\Session::current();
    if ($session === null) {
        header('Location: /');
        exit;
    }

    $state = \Conquer\Game\City\CityState::loadForPlayer((int) $session['player_id']);
    if ($state === null) {
        // No city yet — show landing page with a notice.
        header('Location: /?notice=no_city');
        exit;
    }

    require ROOT_DIR . '/views/city.php';
    exit;
}

// ---------------------------------------------------------------------------
// Building detail / upgrade page — /city/building/:code
// ---------------------------------------------------------------------------

if (preg_match('#^/city/building/([a-z_]+)$#', $path, $m)) {
    $session = \Conquer\Auth\Session::current();
    if ($session === null) {
        header('Location: /');
        exit;
    }

    $buildingCode = $m[1];

    // Academy → Forschungsbaum (außer im Modal-Modus, wo das Upgrade-Fenster gezeigt wird)
    if ($buildingCode === 'academy' && !isset($_GET['modal'])) {
        header('Location: /research');
        exit;
    }

    if (!in_array($buildingCode, \Conquer\Game\City\CityState::BUILDING_CODES, true)) {
        header('Location: /city');
        exit;
    }

    $state = \Conquer\Game\City\CityState::loadForPlayer((int) $session['player_id']);
    if ($state === null) {
        header('Location: /');
        exit;
    }

    require ROOT_DIR . '/views/building.php';
    exit;
}

// ---------------------------------------------------------------------------
// Dev UI Preview (local only)
// ---------------------------------------------------------------------------

if ($path === '/dev/ui-preview') {
    $appCfg = require ROOT_DIR . '/config/app.php';
    if (($appCfg['env'] ?? 'production') !== 'production') {
        require ROOT_DIR . '/views/dev/ui_preview.php';
        exit;
    }
    http_response_code(404);
    exit;
}

// ---------------------------------------------------------------------------
// Landing page — redirect logged-in players straight to their city
// ---------------------------------------------------------------------------

$session = \Conquer\Auth\Session::current();

if ($session !== null && $path === '/') {
    header('Location: /city');
    exit;
}
$authError = match ($_GET['auth_error'] ?? '') {
    'config'    => 'Login is not configured yet.',
    'cancelled' => 'Login was cancelled.',
    'failed'    => 'Login failed. Please try again.',
    default     => '',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conquer — Coming Soon</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .container { text-align: center; max-width: 600px; }
        h1 {
            font-size: 4rem;
            background: linear-gradient(135deg, #0ea5e9, #8b5cf6);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .codename {
            color: #64748b;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        p {
            font-size: 1.1rem;
            line-height: 1.6;
            color: #cbd5e1;
            margin: 2rem 0;
        }
        .status {
            display: inline-block;
            padding: 0.4rem 1rem;
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 999px;
            font-size: 0.875rem;
            color: #94a3b8;
            margin-top: 1rem;
        }
        .status::before { content: '●'; color: #22c55e; margin-right: 0.5rem; }
        footer {
            margin-top: 3rem;
            font-size: 0.85rem;
            color: #475569;
        }
        a { color: #0ea5e9; text-decoration: none; }
        a:hover { color: #38bdf8; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Conquer</h1>
        <div class="codename">working codename — final name TBD</div>

        <p>
            A browser-based 4X strategy MMO inspired by League of Kingdoms.
            <br>
            Built with care, no pay-to-win, no shortcuts.
        </p>

        <?php if ($session !== null): ?>
            <div class="status" style="background:#14532d;border-color:#16a34a;color:#86efac">
                Logged in as <strong><?= htmlspecialchars($session['username']) ?></strong>
            </div>
            <form method="post" action="/auth/logout" style="margin-top:1.5rem">
                <button type="submit" style="background:#1e293b;border:1px solid #334155;color:#94a3b8;padding:.4rem 1rem;border-radius:6px;cursor:pointer">
                    Log out
                </button>
            </form>
        <?php else: ?>
            <?php if ($authError !== ''): ?>
                <div style="color:#f87171;margin-bottom:1rem;font-size:.9rem"><?= htmlspecialchars($authError) ?></div>
            <?php endif ?>
            <div style="margin-top:1.5rem">
                <a href="/auth/google" style="display:inline-flex;align-items:center;gap:.5rem;padding:.6rem 1.2rem;background:#1e293b;border:1px solid #334155;border-radius:8px;color:#e2e8f0;font-size:.95rem;text-decoration:none">
                    <svg width="18" height="18" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.31-8.16 2.31-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
                    Continue with Google
                </a>
            </div>
        <?php endif ?>

        <div class="status" style="margin-top:2rem">Sprint 1 — Authentication &amp; City View in progress</div>

        <footer>
            PHP <?= PHP_VERSION ?> ·
            Server time: <?= gmdate('Y-m-d H:i:s') ?> UTC
            <br>
            <a href="https://github.com/svenmanderscheid/conquer">github.com/svenmanderscheid/conquer</a>
        </footer>
    </div>
</body>
</html>
