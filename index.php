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
// Stub router (replaced in Task 1.6)
// ---------------------------------------------------------------------------

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = '/' . ltrim((string) $path, '/');

if (str_starts_with($path, '/api/')) {
    http_response_code(501);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok'      => false,
        'error'   => 'not_implemented',
        'message' => 'API endpoints are not yet available.',
    ]);
    exit;
}

// ---------------------------------------------------------------------------
// Landing page (Sprint 0 placeholder — kept until city view is ready)
// ---------------------------------------------------------------------------
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

        <div class="status">Sprint 1 — Authentication &amp; City View in progress</div>

        <footer>
            PHP <?= PHP_VERSION ?> ·
            Server time: <?= gmdate('Y-m-d H:i:s') ?> UTC
            <br>
            <a href="https://github.com/svenmanderscheid/conquer">github.com/svenmanderscheid/conquer</a>
        </footer>
    </div>
</body>
</html>
