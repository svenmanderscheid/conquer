<?php
declare(strict_types=1);

/**
 * Conquer — Front Controller
 *
 * This is a Sprint 0 placeholder. Real routing is implemented in Sprint 1.
 *
 * For now, just shows a welcome page proving the deployment works.
 */

// Show a friendly placeholder page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conquer — Coming Soon</title>
    <style>
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
        }
        .container {
            text-align: center;
            max-width: 600px;
        }
        h1 {
            font-size: 4rem;
            margin: 0;
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
        .status::before {
            content: '●';
            color: #f59e0b;
            margin-right: 0.5rem;
        }
        footer {
            margin-top: 3rem;
            font-size: 0.85rem;
            color: #475569;
        }
        a {
            color: #0ea5e9;
            text-decoration: none;
        }
        a:hover {
            color: #38bdf8;
        }
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
        
        <div class="status">Sprint 0 — Foundation in progress</div>
        
        <footer>
            PHP <?= PHP_VERSION ?> · 
            Server time: <?= gmdate('Y-m-d H:i:s') ?> UTC
            <br>
            <a href="https://github.com/svenmanderscheid/conquer">github.com/svenmanderscheid/conquer</a>
        </footer>
    </div>
</body>
</html>
