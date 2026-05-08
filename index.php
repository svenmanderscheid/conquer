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
// Router
// ---------------------------------------------------------------------------

$path   = '/' . ltrim((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)), '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// JSON API — all /api/* requests are handled here.
if (str_starts_with($path, '/api/')) {
    $router = new \Conquer\Router();

    // Auth
    $router->get('/api/auth/me',      [\Conquer\Api\Handlers\AuthHandler::class, 'me']);
    $router->post('/api/auth/logout', [\Conquer\Api\Handlers\AuthHandler::class, 'logout']);

    // City
    $router->get('/api/city/state',                         [\Conquer\Api\Handlers\CityHandler::class, 'state']);
    $router->post('/api/city/upgrade-building',             [\Conquer\Api\Handlers\CityHandler::class, 'upgradeBuilding']);
    $router->post('/api/city/instant-build/:queue_id',      [\Conquer\Api\Handlers\CityHandler::class, 'instantBuild']);

    // Troops
    $router->get('/api/troops/list',         [\Conquer\Api\Handlers\TroopHandler::class, 'list']);
    $router->post('/api/troops/train',       [\Conquer\Api\Handlers\TroopHandler::class, 'train']);

    // March
    $router->post('/api/march/dispatch',     [\Conquer\Api\Handlers\MarchHandler::class, 'dispatch']);
    $router->get('/api/march/list',          [\Conquer\Api\Handlers\MarchHandler::class, 'list']);

    // Battle reports
    $router->get('/api/battle/reports',      [\Conquer\Api\Handlers\BattleHandler::class, 'reports']);
    $router->get('/api/battle/report/:id',   [\Conquer\Api\Handlers\BattleHandler::class, 'report']);

    // Map
    $router->get('/api/map/info',            [\Conquer\Api\Handlers\MapHandler::class, 'info']);
    $router->get('/api/map/tiles',           [\Conquer\Api\Handlers\MapHandler::class, 'tiles']);
    $router->get('/api/map/tile/:x/:y',      [\Conquer\Api\Handlers\MapHandler::class, 'tile']);

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
