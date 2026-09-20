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
// Normalize request path — strip app sub-directory prefix (e.g. /conquer/)
// This ensures routing works both on localhost/conquer/ and on production root.
// ---------------------------------------------------------------------------

$_rawPath   = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '/';
$_scriptDir = rtrim(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')), '/\\');

// APP_BASE: the sub-directory prefix (e.g. "/conquer" on localhost, "" on production)
// Used for generating correct redirect URLs from controllers.
define('APP_BASE', $_scriptDir === '/' ? '' : $_scriptDir);
// Strip the base directory (e.g. /conquer) from the path when running in a sub-folder
if ($_scriptDir !== '' && $_scriptDir !== '/' && str_starts_with($_rawPath, $_scriptDir)) {
    $_rawPath = substr($_rawPath, strlen($_scriptDir));
}
$_normalizedPath = '/' . ltrim($_rawPath, '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Public discovery endpoints stay available without the game database.
// Authentication and every private game route continue below the DB guard.
$_appConfig = \Conquer\Bootstrap::getConfig();
$_configuredOrigin = rtrim((string) ($_appConfig['base_url'] ?? ''), '/');
$_requestHost = strtolower((string) parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
if (in_array($_requestHost, ['unionofkingdoms.com', 'www.unionofkingdoms.com', 'play.unionofkingdoms.com'], true)) {
    // The public website and game login share one deployment, but each keeps
    // its own canonical host for links, metadata, robots and the sitemap.
    $_configuredOrigin = 'https://' . $_requestHost;
}
if (!filter_var($_configuredOrigin, FILTER_VALIDATE_URL)) {
    $_configuredOrigin = (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}
// base_url is the canonical public application root and may itself contain a path.
$_publicRoot = $_configuredOrigin;
$_landingCspNonce = base64_encode(random_bytes(18));
$_landingCsp = "default-src 'self'; img-src 'self' data:; style-src 'self'; font-src 'self'; script-src 'self' 'nonce-{$_landingCspNonce}'; connect-src 'self'; manifest-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none'";

if ($_normalizedPath === '/robots.txt' && $method === 'GET') {
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    echo "User-agent: *\nAllow: /\nDisallow: " . APP_BASE . "/api/\nDisallow: " . APP_BASE . "/admin/\nDisallow: " . APP_BASE . "/auth/\nDisallow: " . APP_BASE . "/city\nDisallow: " . APP_BASE . "/game\nSitemap: " . $_publicRoot . "/sitemap.xml\n";
    exit;
}
if ($_normalizedPath === '/sitemap.xml' && $method === 'GET') {
    header('Content-Type: application/xml; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    $location = htmlspecialchars($_publicRoot . '/', ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $lastModified = gmdate('Y-m-d', (int) filemtime(ROOT_DIR . '/views/welcome.php'));
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>' . $location . '</loc><lastmod>'
        . $lastModified . '</lastmod><changefreq>weekly</changefreq><priority>1.0</priority></url></urlset>';
    exit;
}
if (($_normalizedPath === '/' && $method === 'GET') || $_normalizedPath === '/alpha/waitlist') {
    session_name('conquer_login');
    session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax', 'cookie_secure' => !empty($_SERVER['HTTPS'])]);
    $_SESSION['login_csrf'] ??= bin2hex(random_bytes(32));
    $loginError = '';
    $waitlistError = '';
    $waitlistSuccess = !empty($_SESSION['alpha_waitlist_success']);
    unset($_SESSION['alpha_waitlist_success']);
    $landingCspNonce = $_landingCspNonce;
    header('Content-Security-Policy: ' . $_landingCsp);
    // The page embeds a session-bound CSRF token; never let a shared cache reuse it.
    header('Cache-Control: private, no-store');
    if ($_normalizedPath === '/' && $_requestHost === 'play.unionofkingdoms.com') {
        if (\Conquer\Auth\Session::current() !== null) {
            header('Location: ' . APP_BASE . '/city');
            exit;
        }
        require ROOT_DIR . '/views/play_login.php';
        exit;
    }
    if ($_normalizedPath === '/alpha/waitlist') {
        header('X-Robots-Tag: noindex, nofollow');
        if ($method !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            exit;
        }
        $accessMode = 'waitlist';
        $waitlistSuccess = false;
        $waitlistError = \Conquer\Auth\AlphaWaitlist::submit();
    }
    require ROOT_DIR . '/views/welcome.php';
    exit;
}

header('X-Robots-Tag: noindex, nofollow');

// All game routes need the database, including login and existing sessions.
if (!\Conquer\Db\Connection::isInitialized()) {
    http_response_code(503);
    header('Retry-After: 30');
    if (str_starts_with($_normalizedPath, '/api/')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'DATABASE_UNAVAILABLE', 'message' => 'Die Spieldatenbank ist gerade nicht erreichbar. Bitte versuche es gleich noch einmal.']);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        $localHint = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
            ? '<p>Öffne XAMPP und klicke bei <strong>MySQL</strong> auf <strong>Start</strong>. Lade danach diese Seite neu.</p>' : '';
        echo '<!doctype html><html lang="de"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Conquer – kurz nicht erreichbar</title><body style="margin:0;background:#173e33;color:#fff5dd;font:18px/1.6 system-ui;padding:8vw">'
            . '<main style="max-width:620px;margin:auto"><h1>Dein Königreich wartet auf dich.</h1>'
            . '<p>Die Spieldatenbank ist gerade nicht erreichbar.</p>' . $localHint
            . '<p><a href="" style="color:#ffda79">Erneut versuchen</a></p></main></body></html>';
    }
    exit;
}

// ---------------------------------------------------------------------------
// Admin Panel — separate session, separate auth, no game session needed
// ---------------------------------------------------------------------------

if (str_starts_with($_normalizedPath, '/admin')) {
    session_name('conquer_admin');
    session_start();

    $adminUri = $_normalizedPath;

    $m = [];
    match (true) {
        $adminUri === '/admin/login' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
            => \Conquer\Admin\AdminController::loginPage(),
        $adminUri === '/admin/login' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
            => \Conquer\Admin\AdminController::loginPost(),
        $adminUri === '/admin/change-password' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
            => \Conquer\Admin\AdminController::changePasswordPage(),
        $adminUri === '/admin/change-password' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
            => \Conquer\Admin\AdminController::changePasswordPost(),
        $adminUri === '/admin/logout'
            => \Conquer\Admin\AdminController::logout(),
        $adminUri === '/admin' || $adminUri === '/admin/'
            => \Conquer\Admin\AdminController::dashboard(),
        $adminUri === '/admin/players'
            => \Conquer\Admin\AdminController::players(),
        $adminUri === '/admin/alpha-keys'
            => \Conquer\Admin\AdminController::alphaKeys(),
        $adminUri === '/admin/alpha-waitlist'
            => \Conquer\Admin\AdminController::alphaWaitlist(),
        $adminUri === '/admin/rewards'
            => \Conquer\Admin\AdminController::rewards(),
        $adminUri === '/admin/lands'
            => \Conquer\Admin\AdminController::lands(),
        $adminUri === '/admin/items'
            => \Conquer\Admin\AdminController::items(),
        (bool) preg_match('#^/admin/players/(\d+)$#', $adminUri, $m)
            => \Conquer\Admin\AdminController::playerDetail((int) $m[1]),
        $adminUri === '/admin/alliances'
            => \Conquer\Admin\AdminController::alliances(),
        $adminUri === '/admin/world'
            => \Conquer\Admin\AdminController::world(),
        $adminUri === '/admin/world-create'
            => \Conquer\Admin\AdminController::worldCreate(),
        $adminUri === '/admin/chat'
            => \Conquer\Admin\AdminController::chat(),
        $adminUri === '/admin/bug-reports'
            => \Conquer\Admin\AdminController::bugReports(),
        (bool) preg_match('#^/admin/bug-reports/(\d+)/screenshot$#', $adminUri, $m)
            => \Conquer\Admin\AdminController::bugReportScreenshot((int) $m[1]),
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

$path   = $_normalizedPath;

if ($path === '/auth/recover') {
    session_name('conquer_login');
    session_start(['cookie_httponly'=>true,'cookie_samesite'=>'Lax','cookie_secure'=>!empty($_SERVER['HTTPS'])]);
    $_SESSION['login_csrf'] ??= bin2hex(random_bytes(32));
    $recoveryMessage='';$recoveryDone=false;
    if($method==='POST'){
        if(!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['login_csrf'],$_POST['csrf'])){$recoveryMessage='Die Sitzung ist abgelaufen. Lade die Seite neu.';}
        else{try{
            \Conquer\Auth\AccountService::recover((string)($_POST['username']??''),(string)($_POST['code']??''),$_POST['new_password']??null);
            $recoveryDone=true;$recoveryMessage='Dein Passwort wurde geändert. Du kannst dich jetzt wieder anmelden.';
        }catch(\DomainException $e){$recoveryMessage=$e->getMessage();}}
    }
    header('Cache-Control: no-store');require ROOT_DIR.'/views/recover.php';exit;
}

if ($path === '/auth/local') {
    if ($method !== 'POST') {
        header('Location: ' . APP_BASE . '/', true, 303); exit;
    }
    session_name('conquer_login');
    session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax', 'cookie_secure' => !empty($_SERVER['HTTPS'])]);
    $_SESSION['login_csrf'] ??= bin2hex(random_bytes(32));
    $loginError = \Conquer\Auth\PasswordAuth::submit();
    $landingCspNonce = $_landingCspNonce;
    header('Content-Security-Policy: ' . $_landingCsp);
    require ROOT_DIR . ($_requestHost === 'play.unionofkingdoms.com' ? '/views/play_login.php' : '/views/welcome.php');
    exit;
}

// JSON API — all /api/* requests are handled here.
if (str_starts_with($path, '/api/')) {
    \Conquer\Security\ApiGuard::beforeSession();
    $router  = new \Conquer\Router();
    // Provider callbacks authenticate with their adapter signature, never with a player cookie.
    if ($method==='POST' && preg_match('#^/api/theme-bundles/provider/([a-z0-9_-]{1,24})$#D',$path,$providerMatch)) {
        \Conquer\Api\Handlers\ThemeBundleHandler::notification(['provider'=>$providerMatch[1]]);
    }
    $session = \Conquer\Auth\Session::current() ?? [];
    if (!isset($session['player_id'])) {
        \Conquer\Security\ApiGuard::limit('api.anonymous', \Conquer\Security\RateLimit::ip(), 60, 60);
        \Conquer\Api\Response::error(401, 'UNAUTHENTICATED', 'Bitte melde dich an.');
    }
    \Conquer\Security\ApiGuard::authenticated($session, $method, $path);
    // The new client uses the validated kingdom/expedition/market actions.
    // Retired mutators must not bypass their ownership, reward and alliance rules.
    if (in_array($method,['POST','DELETE','PUT','PATCH'],true) && (preg_match('#^/api/(alliance|inventory|treasure|quests|trading|shrine|conquest|vip|chat|player)/#', $path)
        || in_array($path,['/api/troops/promote','/api/world-chat/send'],true))) {
        \Conquer\Api\Response::error(410, 'ACTION_RETIRED', 'Diese Aktion ist hier nicht verfügbar. Öffne die aktuelle Spielansicht.');
    }

    // Serialize requests for one player: queue settlement and spending must
    // not race across tabs or between a poll and an action.
    if (isset($session['player_id'])) {
        $requestDb = \Conquer\Db\Connection::getInstance();
        $lockName = 'conquer-player-' . $session['player_id'];
        if ((int) $requestDb->query('SELECT GET_LOCK(?, 5)', [$lockName])->fetchColumn() !== 1) {
            \Conquer\Api\Response::error(409, 'BUSY', 'Bitte versuche es gleich noch einmal.');
        }
        register_shutdown_function(static function () use ($requestDb, $lockName): void {
            \Conquer\Security\ApiOperation::abort();
            $requestDb->query('SELECT RELEASE_LOCK(?)', [$lockName]);
        });
        $fresh=$requestDb->query('SELECT s.active_world_id,p.is_banned FROM sessions s JOIN players p ON p.id=s.player_id WHERE s.id=? AND s.expires_at>UTC_TIMESTAMP()',[$session['id']])->fetch();
        if(!$fresh||$fresh['is_banned'])\Conquer\Api\Response::error(401,'UNAUTHENTICATED','Bitte melde dich erneut an.');
        \Conquer\Auth\Session::setActiveWorld((int)$fresh['active_world_id']);
        $session['active_world_id']=(int)$fresh['active_world_id'];
    }
    if(\Conquer\Game\World\WorldContext::id()!==1&&$method==='GET'
        &&preg_match('#^/api/(map/(?!marches)|alliance/|chat/|player/|world-chat$|conquest/)#',$path)){
        \Conquer\Api\Response::error(410,'LEGACY_WORLD_API','Verwende die aktuelle Spielansicht für diese Welt.');
    }
    // An old browser tab must never spend from the newly selected world.
    $worldRule='WORLD_CHANGED';
    try {
        $expected=$_SERVER['HTTP_X_WORLD_ID']??$_GET['expected_world_id']??null;
        if($path!=='/api/worlds/action')\Conquer\Game\World\WorldContext::current($expected);
        if(in_array($method,['POST','PUT','PATCH','DELETE'],true)
            && !in_array($path,['/api/worlds/action','/api/auth/logout'],true)) {
            $input=json_decode(file_get_contents('php://input')?:'{}',true);
            if(is_array($input))\Conquer\Game\World\WorldContext::current($input['expected_world_id']??null);
            $account=$path==='/api/bug-reports'||($path==='/api/progression/action'&&in_array($input['action']??'',['password.change','recovery.generate','sessions.revoke'],true))
                ||($path==='/api/kingdom/action'&&($input['action']??'')==='theme_bundle.checkout');
            $returning=in_array($path,['/api/march/recall','/api/march/recall-reinforce'],true)||preg_match('#^/api/shrines/\d+/recall$#',$path);
            $worldRule='WORLD_UNAVAILABLE';
            if(!$account&&!$returning)\Conquer\Game\World\WorldContext::assertActionAvailable();
        }
    }catch(\DomainException $e){\Conquer\Api\Response::error(409,$worldRule,$e->getMessage());}
    if($path==='/api/game/state')\Conquer\Game\Conquest\EventService::tick(\Conquer\Game\World\WorldContext::id());
    $router->get('/api/worlds/state', [\Conquer\Api\Handlers\WorldHandler::class, 'state']);
    $router->post('/api/worlds/action', [\Conquer\Api\Handlers\WorldHandler::class, 'action']);
    $router->get('/api/community/state', [\Conquer\Api\Handlers\CommunityHandler::class, 'state']);
    $router->get('/api/mailbox/state', [\Conquer\Api\Handlers\MailboxHandler::class, 'state']);
    $router->get('/api/mailbox/message', [\Conquer\Api\Handlers\MailboxHandler::class, 'message']);
    $router->get('/api/community/chat', [\Conquer\Api\Handlers\CommunityHandler::class, 'chat']);
    $router->get('/api/community/shared-report/:id', [\Conquer\Api\Handlers\CommunityHandler::class, 'sharedReport']);
    $router->post('/api/community/action', [\Conquer\Api\Handlers\CommunityHandler::class, 'action']);
    $router->get('/api/defense/state', [\Conquer\Api\Handlers\DefenseHandler::class, 'state']);
    $router->post('/api/defense/action', [\Conquer\Api\Handlers\DefenseHandler::class, 'action']);
    $router->get('/api/progression/state', [\Conquer\Api\Handlers\ProgressionHandler::class, 'state']);
    $router->post('/api/progression/action', [\Conquer\Api\Handlers\ProgressionHandler::class, 'action']);
    $router->post('/api/bug-reports', [\Conquer\Api\Handlers\BugReportHandler::class, 'submit']);
    $router->get('/api/game/state', [\Conquer\Api\Handlers\GameHandler::class, 'state']);
    $router->post('/api/march/preview', [\Conquer\Api\Handlers\BattlePreviewHandler::class, 'calculate']);
    $router->get('/api/map/search', [\Conquer\Api\Handlers\MapSearchHandler::class, 'search']);
    $router->get('/api/land/state', [\Conquer\Api\Handlers\LandHandler::class, 'state']);
    $router->get('/api/land/:id', [\Conquer\Api\Handlers\LandHandler::class, 'detail']);
    $router->post('/api/land/:id/donate', [\Conquer\Api\Handlers\LandHandler::class, 'donate']);
    $router->get('/api/kingdom/state', static fn(array $p) => \Conquer\Api\Handlers\KingdomHandler::state($p));
    $router->post('/api/kingdom/action', static fn(array $p) => \Conquer\Api\Handlers\KingdomHandler::action($p));
    $router->get('/api/theme-bundles/state', [\Conquer\Api\Handlers\ThemeBundleHandler::class, 'state']);
    $router->get('/api/expeditions/state', static fn(array $p) => \Conquer\Api\Handlers\ExpeditionHandler::state($p));
    $router->post('/api/expeditions/action', static fn(array $p) => \Conquer\Api\Handlers\ExpeditionHandler::action($p));
    $router->get('/api/dungeons/state', static fn(array $p) => \Conquer\Api\Handlers\DungeonHandler::state($p));
    $router->post('/api/dungeons/action', static fn(array $p) => \Conquer\Api\Handlers\DungeonHandler::action($p));
    $router->get('/api/market/state', [\Conquer\Api\Handlers\MarketHandler::class, 'state']);
    $router->post('/api/market/action', [\Conquer\Api\Handlers\MarketHandler::class, 'action']);
    $router->get('/api/city3d/state', [\Conquer\Api\Handlers\City3dHandler::class, 'snapshot']);
    $router->post('/api/city3d/plot', [\Conquer\Api\Handlers\City3dHandler::class, 'plot']);
    $router->post('/api/city3d/upgrade', [\Conquer\Api\Handlers\City3dHandler::class, 'upgrade']);

    // Auth
    $router->get('/api/auth/me',      [\Conquer\Api\Handlers\AuthHandler::class, 'me']);
    $router->post('/api/auth/logout', [\Conquer\Api\Handlers\AuthHandler::class, 'logout']);

    // City
    $router->get('/api/city/state',                         [\Conquer\Api\Handlers\CityHandler::class, 'state']);
    $router->post('/api/city/upgrade-building',             [\Conquer\Api\Handlers\CityHandler::class, 'upgradeBuilding']);
    $router->post('/api/city/instant-build/:queue_id',      [\Conquer\Api\Handlers\CityHandler::class, 'instantBuild']);
    $router->post('/api/city/cancel-build/:queue_id',       [\Conquer\Api\Handlers\CityHandler::class, 'cancelBuild']);
    $router->post('/api/city/speedup-build/:queue_id',      [\Conquer\Api\Handlers\CityHandler::class, 'speedupBuild']);
    $router->post('/api/city/wall-repair',                  fn() => \Conquer\Api\Handlers\CityHandler::repairWall($session));

    // Troops
    $router->get('/api/troops/list',               [\Conquer\Api\Handlers\TroopHandler::class, 'list']);
    $router->post('/api/troops/train',             [\Conquer\Api\Handlers\TroopHandler::class, 'train']);
    $router->post('/api/troops/cancel-train/:queue_id', [\Conquer\Api\Handlers\TroopHandler::class, 'cancelTrain']);
    $router->post('/api/troops/speedup-train/:queue_id', [\Conquer\Api\Handlers\TroopHandler::class, 'speedupTrain']);
    $router->post('/api/troops/promote',           [\Conquer\Api\Handlers\TroopHandler::class, 'promote']);
    $router->post('/api/troops/heal',              [\Conquer\Api\Handlers\TroopHandler::class, 'heal']);

    // March
    $router->post('/api/march/dispatch',         [\Conquer\Api\Handlers\MarchHandler::class, 'dispatch']);
    $router->post('/api/march/dispatch-charm',   [\Conquer\Api\Handlers\MarchHandler::class, 'dispatchCharm']);
    $router->post('/api/march/dispatch-player',  [\Conquer\Api\Handlers\MarchHandler::class, 'dispatchPlayer']);
    $router->post('/api/march/dispatch-scout',   [\Conquer\Api\Handlers\MarchHandler::class, 'dispatchScout']);
    $router->post('/api/march/dispatch-gather',  fn() => \Conquer\Api\Handlers\MarchHandler::dispatchGather($session));
    $router->post('/api/march/dispatch-field-attack', fn() => \Conquer\Api\Handlers\MarchHandler::dispatchGather($session,true));
    $router->post('/api/march/recall',            fn() => \Conquer\Api\Handlers\MarchHandler::recall($session));
    $router->post('/api/march/reinforce',         fn() => \Conquer\Api\Handlers\MarchHandler::dispatchReinforce($session));
    $router->post('/api/march/recall-reinforce',  fn() => \Conquer\Api\Handlers\MarchHandler::recallReinforcement($session));
    $router->get('/api/march/reinforcements',     fn() => \Conquer\Api\Handlers\MarchHandler::listReinforcements($session));
    $router->get('/api/march/list',              [\Conquer\Api\Handlers\MarchHandler::class, 'list']);
    $router->get('/api/map/marches',             [\Conquer\Api\Handlers\MarchHandler::class, 'listAll']);

    // Battle reports
    $router->get('/api/battle/reports',      [\Conquer\Api\Handlers\BattleHandler::class, 'reports']);
    $router->get('/api/battle/report/:id',   [\Conquer\Api\Handlers\BattleHandler::class, 'report']);
    $router->post('/api/battle/report/:id/delete', [\Conquer\Api\Handlers\BattleHandler::class, 'delete']);

    // Map
    $router->get('/api/map/info',              [\Conquer\Api\Handlers\MapHandler::class, 'info']);
    $router->get('/api/map/tiles',             [\Conquer\Api\Handlers\MapHandler::class, 'tiles']);
    $router->get('/api/map/tile/:x/:y',        [\Conquer\Api\Handlers\MapHandler::class, 'tile']);
    $router->get('/api/map/field-object/:id',  fn($p) => \Conquer\Api\Handlers\MapHandler::fieldObject($session, (int) $p['id']));

    // Research
    $router->get('/api/research/state',                  [\Conquer\Api\Handlers\ResearchHandler::class, 'state']);
    $router->post('/api/research/start',                 [\Conquer\Api\Handlers\ResearchHandler::class, 'start']);
    $router->post('/api/research/instant',               [\Conquer\Api\Handlers\ResearchHandler::class, 'instant']);
    $router->post('/api/research/cancel/:queue_id',      [\Conquer\Api\Handlers\ResearchHandler::class, 'cancel']);
    $router->post('/api/research/speedup/:queue_id',     [\Conquer\Api\Handlers\ResearchHandler::class, 'speedup']);

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

    // Alliance Research
    $router->get('/api/alliance/research/data',  fn() => \Conquer\Api\Handlers\AllianceHandler::researchData($session));
    $router->get('/api/alliance/research/state', fn() => \Conquer\Api\Handlers\AllianceHandler::researchState($session));
    $router->post('/api/alliance/research/start', fn() => \Conquer\Api\Handlers\AllianceHandler::researchStart($session));

    // World Chat (legacy routes — kept for backward compat)
    $router->get('/api/world-chat',              fn() => \Conquer\Api\Handlers\AllianceHandler::worldChat($session));
    $router->post('/api/world-chat/send',        fn() => \Conquer\Api\Handlers\AllianceHandler::sendWorldChat($session));

    // Chat (new unified routes used by HUD chat widget)
    $router->get('/api/chat/world',     fn() => \Conquer\Api\Handlers\ChatHandler::worldChat($session));
    $router->post('/api/chat/world',    fn() => \Conquer\Api\Handlers\ChatHandler::sendWorldChat($session));
    $router->get('/api/chat/alliance',  fn() => \Conquer\Api\Handlers\ChatHandler::allianceChat($session));
    $router->post('/api/chat/alliance', fn() => \Conquer\Api\Handlers\ChatHandler::sendAllianceChat($session));

    // Player
    $router->get('/api/player/me',                    [\Conquer\Api\Handlers\PlayerHandler::class, 'me']);
    $router->get('/api/player/profile/:id',           [\Conquer\Api\Handlers\PlayerHandler::class, 'profile']);
    $router->get('/api/player/formations',            [\Conquer\Api\Handlers\PlayerHandler::class, 'formations']);
    $router->post('/api/player/formations/:slot',     [\Conquer\Api\Handlers\PlayerHandler::class, 'saveFormation']);
    $router->post('/api/player/emoji',                [\Conquer\Api\Handlers\PlayerHandler::class, 'setEmoji']);
    $router->get('/api/player/skins',                 [\Conquer\Api\Handlers\PlayerHandler::class, 'skins']);
    $router->post('/api/player/skin/equip',           [\Conquer\Api\Handlers\PlayerHandler::class, 'equipSkin']);

    // Rally
    $router->post('/api/rally/start-monster', [\Conquer\Api\Handlers\RallyHandler::class, 'startMonster']);
    $router->post('/api/rally/start',  [\Conquer\Api\Handlers\RallyHandler::class, 'start']);
    $router->post('/api/rally/join',   [\Conquer\Api\Handlers\RallyHandler::class, 'join']);
    $router->post('/api/rally/:id/launch', [\Conquer\Api\Handlers\RallyHandler::class, 'launch']);
    $router->post('/api/rally/:id/cancel', [\Conquer\Api\Handlers\RallyHandler::class, 'cancel']);
    $router->get('/api/rally/list',    [\Conquer\Api\Handlers\RallyHandler::class, 'list']);
    $router->get('/api/rally/:id',     [\Conquer\Api\Handlers\RallyHandler::class, 'detail']);

    // Shrine System
    $router->get('/api/shrines',                  fn() => \Conquer\Api\Handlers\ShrineHandler::list($session));
    $router->get('/api/shrines/:id',              fn($p) => \Conquer\Api\Handlers\ShrineHandler::detail($session, (int) $p['id']));
    $router->post('/api/shrines/:id/attack', fn($p) => \Conquer\Api\Handlers\ShrineHandler::attack($session, (int) $p['id']));
    $router->post('/api/shrines/:id/garrison',    fn($p) => \Conquer\Api\Handlers\ShrineHandler::garrison($session, (int) $p['id']));
    $router->post('/api/shrines/:id/recall',      fn($p) => \Conquer\Api\Handlers\ShrineHandler::recall($session, (int) $p['id']));

    // Conquest Event
    $router->get('/api/conquest/event',           fn() => \Conquer\Api\Handlers\ConquestHandler::current($session));
    $router->get('/api/conquest/leaderboard',     fn() => \Conquer\Api\Handlers\ConquestHandler::leaderboard($session));

    // Hospital
    $router->get('/api/hospital/status',        [\Conquer\Api\Handlers\HospitalHandler::class, 'status']);
    $router->post('/api/hospital/heal',         [\Conquer\Api\Handlers\HospitalHandler::class, 'heal']);
    $router->post('/api/hospital/finish',       [\Conquer\Api\Handlers\HospitalHandler::class, 'finish']);
    $router->post('/api/hospital/speedup',      [\Conquer\Api\Handlers\HospitalHandler::class, 'speedup']);
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

    \Conquer\Security\ApiOperation::begin((int)$session['player_id'],$method,$path);
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

        // Destroy any previous session before creating the new one.
        \Conquer\Auth\Session::destroy();
        \Conquer\Auth\Session::create(
            $playerId,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
        );
        header('Location: ' . APP_BASE . '/city');
    } catch (\Throwable $e) {
        \Conquer\Logger::getInstance()->error('OAuth callback failed: ' . $e->getMessage());
        header('Location: /?auth_error=failed');
    }
    exit;
}

if ($path === '/auth/logout') {
    \Conquer\Auth\Session::destroy();
    header('Location: ' . APP_BASE . '/');
    exit;
}

// ---------------------------------------------------------------------------
// Battle reports view
// ---------------------------------------------------------------------------

$currentPages = ['/reports'=>'reports','/alliance'=>'alliance','/alliance/battle'=>'expeditions','/research'=>'research','/map'=>'world'];
if ($method === 'GET' && isset($currentPages[$path])) {
    header('Location: ' . APP_BASE . '/city#' . $currentPages[$path]); exit;
}
if ($method === 'GET' && preg_match('#^/city/building/([a-z_]+)$#',$path,$buildingMatch)) {
    $buildingTabs=['barrack'=>'army','academy'=>'research','hospital'=>'army','hall_of_alliance'=>'alliance','treasure_house'=>'inventory','trading_post'=>'market'];
    header('Location: ' . APP_BASE . '/city#' . ($buildingTabs[$buildingMatch[1]] ?? 'city')); exit;
}
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

if ($path === '/city' || $path === '/city/3d') {
    $session = \Conquer\Auth\Session::current();
    if ($session === null) {
        header('Location: ' . APP_BASE . '/');
        exit;
    }

    $state = \Conquer\Game\City\CityState::loadForPlayer((int) $session['player_id']);
    if ($state === null) {
        // No city yet — show landing page with a notice.
        header('Location: /?notice=no_city');
        exit;
    }

    require ROOT_DIR . ($path === '/city/3d' && ($_GET['embed'] ?? '') === '1' ? '/views/city3d.php' : '/views/game.php');
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
    <meta name="theme-color" content="#5c4270">
    <title>Conquer — Coming Soon</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(APP_BASE, ENT_QUOTES) ?>/assets/css/fantasy-fonts.css?v=<?= filemtime(ROOT_DIR.'/assets/css/fantasy-fonts.css') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(APP_BASE, ENT_QUOTES) ?>/assets/css/village-theme.css?v=<?= filemtime(ROOT_DIR.'/assets/css/village-theme.css') ?>">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: var(--ui-font);
            background: linear-gradient(160deg, var(--ui-paper), var(--ui-inset));
            color: var(--ui-ink);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .container { text-align:center; max-width:600px; padding:clamp(24px,6vw,42px); background:var(--ui-card); border:3px solid var(--ui-frame); border-radius:20px; box-shadow:var(--ui-shadow); }
        h1 {
            font-size: 4rem;
            color: var(--ui-primary);
        }
        .codename {
            color: var(--ui-muted);
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        p {
            font-size: 1.1rem;
            line-height: 1.6;
            color: var(--ui-ink);
            margin: 2rem 0;
        }
        .status {
            display: inline-block;
            padding: 0.4rem 1rem;
            background: var(--ui-inset);
            border: 1px solid var(--ui-line);
            border-radius: 999px;
            font-size: 0.875rem;
            color: var(--ui-muted);
            margin-top: 1rem;
        }
        .status::before { content: '●'; color: #22c55e; margin-right: 0.5rem; }
        footer {
            margin-top: 3rem;
            font-size: 0.85rem;
            color: var(--ui-muted);
        }
        a { color: var(--ui-primary-dark); text-decoration: none; }
        a:hover { color: var(--ui-primary); }
        button { font-family:var(--ui-font); }
        @media(max-width:360px){body{padding:12px}.container{padding:22px 16px}h1{font-size:3rem}}
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
            <div class="status" style="background:var(--ui-green-soft);border-color:var(--ui-green-dark);color:var(--ui-green-dark)">
                Logged in as <strong><?= htmlspecialchars($session['username']) ?></strong>
            </div>
            <form method="post" action="/auth/logout" style="margin-top:1.5rem">
                <button type="submit" style="background:var(--ui-card);border:1px solid var(--ui-line);color:var(--ui-ink);padding:.4rem 1rem;border-radius:8px;cursor:pointer">
                    Log out
                </button>
            </form>
        <?php else: ?>
            <?php if ($authError !== ''): ?>
                <div style="color:var(--ui-red);margin-bottom:1rem;font-size:.9rem"><?= htmlspecialchars($authError) ?></div>
            <?php endif ?>
            <div style="margin-top:1.5rem">
                <a href="/auth/google" style="display:inline-flex;align-items:center;gap:.5rem;padding:.6rem 1.2rem;background:var(--ui-card);border:1px solid var(--ui-line);border-radius:9px;color:var(--ui-ink);font-size:.95rem;text-decoration:none">
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
