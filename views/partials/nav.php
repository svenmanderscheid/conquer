<?php
declare(strict_types=1);
/**
 * Global navigation bar — included in every view right after <body>.
 * Requires: $session (provided by index.php)
 */

use Conquer\Db\Connection;

$_navPath   = '/' . ltrim((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)), '/');
$_navActive = preg_match('#^/city#', $_navPath) ? '/city' : $_navPath;
$_isActive  = fn(string $href): string => str_starts_with($_navActive, $href) ? ' nav-active' : '';

// Unread battle reports
$_unread = 0;
// City resources (shown in resource bar below nav)
$_res = null;

try {
    $__db = Connection::getInstance();

    $_unread = (int) $__db->query(
        'SELECT COUNT(*) FROM battle_reports WHERE attacker_id = ? AND attacker_read = 0',
        [(int) $session['player_id']],
    )->fetchColumn();

    $_res = $__db->query(
        'SELECT food, lumber, stone, gold FROM cities WHERE player_id = ? LIMIT 1',
        [(int) $session['player_id']],
    )->fetch();
} catch (\Throwable) {}

$_gems = number_format((int) ($session['gems'] ?? 0), 0, '.', '.');
$_fmt  = fn(mixed $n): string => number_format((int) $n, 0, '.', ',');
?>
<style>
    /* ── Conquer Navigation ──────────────────────────────────────────── */
    #conquer-nav {
        position: fixed;
        top: 0; left: 0; right: 0;
        z-index: 9999;
        font-family: system-ui, -apple-system, sans-serif;
        user-select: none;
    }

    /* Top row — main menu */
    .cnav-top {
        height: 40px;
        background: linear-gradient(180deg, #7c1e0e 0%, #4a0d05 55%, #6b1a0a 100%);
        border-bottom: 2px solid #b8860b;
        box-shadow:
            inset 0 1px 0 rgba(255,220,100,0.18),
            inset 0 -1px 0 rgba(0,0,0,0.35),
            0 3px 8px rgba(0,0,0,0.55);
        display: flex;
        align-items: stretch;
        justify-content: center;
    }
    .cnav-top-inner {
        display: flex;
        align-items: stretch;
        width: 100%;
        max-width: 1280px;
    }
    .cnav-left, .cnav-right {
        display: flex;
        align-items: stretch;
    }
    .cnav-spacer { flex: 1; }

    /* Nav items */
    .nav-item {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 0 12px;
        color: #f0d080;
        text-decoration: none;
        font-size: 0.76rem;
        font-weight: 600;
        white-space: nowrap;
        border-right: 1px solid rgba(184,134,11,0.25);
        cursor: pointer;
        transition: background 0.1s, color 0.1s;
        position: relative;
    }
    .cnav-left .nav-item:first-child { border-left: 1px solid rgba(184,134,11,0.25); }
    .cnav-right .nav-item            { border-right: none; border-left: 1px solid rgba(184,134,11,0.25); }

    .nav-item:hover         { background: rgba(255,210,60,0.13); color: #ffd700; text-decoration: none; }
    .nav-item.nav-active    { background: rgba(255,210,60,0.2); color: #ffd700; box-shadow: inset 0 -2px 0 #ffd700; }
    .nav-item.nav-dim       { opacity: 0.42; cursor: default; pointer-events: none; }

    .nav-icon  { font-size: 0.9rem; line-height: 1; }
    .nav-badge { color: #ffd700; font-weight: 700; }

    .nav-gems       { color: #c4b5fd; }
    .nav-gems:hover { color: #ddd6fe !important; background: rgba(167,139,250,0.12) !important; }

    /* Resource bar — second row */
    .cnav-res {
        height: 32px;
        background: linear-gradient(180deg, #3a0c04 0%, #2a0802 100%);
        border-bottom: 1px solid rgba(184,134,11,0.4);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 1.5rem;
        font-size: 0.78rem;
        font-weight: 600;
        color: #f0d080;
        box-shadow: 0 2px 6px rgba(0,0,0,0.45);
    }
    .res-item {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    .res-icon  { font-size: 1rem; line-height: 1; }
    .res-value { color: #ffd700; font-variant-numeric: tabular-nums; }
</style>

<nav id="conquer-nav">

    <!-- ── Main menu row ── -->
    <div class="cnav-top">
        <div class="cnav-top-inner">

            <div class="cnav-left">
                <a href="/city"    class="nav-item<?= $_isActive('/city') ?>">
                    <span class="nav-icon">🏰</span> Übersicht
                </a>
                <a href="/map"     class="nav-item<?= $_isActive('/map') ?>">
                    <span class="nav-icon">🗺</span> Karte
                </a>
                <a href="/reports" class="nav-item<?= $_isActive('/reports') ?>">
                    <span class="nav-icon">📜</span>
                    Berichte<?php if ($_unread > 0): ?>
                        <span class="nav-badge">(<?= $_unread ?>)</span>
                    <?php endif ?>
                </a>
                <a href="#" class="nav-item nav-dim">
                    <span class="nav-icon">🎯</span> Quests
                </a>
            </div>

            <div class="cnav-spacer"></div>

            <div class="cnav-right">
                <a href="/alliance" class="nav-item<?= $_isActive('/alliance') ?>">
                    <span class="nav-icon">⚜</span> Allianz
                </a>
                <a href="#" class="nav-item nav-dim">
                    <span class="nav-icon">👤</span> Profil
                </a>
                <span class="nav-item nav-gems">
                    <span class="nav-icon">💎</span> <?= $_gems ?> +
                </span>
                <a href="#" class="nav-item nav-dim">
                    <span class="nav-icon">⚙</span> Einstellungen
                </a>
            </div>

        </div>
    </div>

    <!-- ── Resource bar ── -->
    <?php if ($_res): ?>
    <div class="cnav-res">
        <div class="res-item">
            <span class="res-icon">🌾</span>
            <span class="res-value"><?= $_fmt($_res['food']) ?></span>
        </div>
        <div class="res-item">
            <span class="res-icon">🪵</span>
            <span class="res-value"><?= $_fmt($_res['lumber']) ?></span>
        </div>
        <div class="res-item">
            <span class="res-icon">🪨</span>
            <span class="res-value"><?= $_fmt($_res['stone']) ?></span>
        </div>
        <div class="res-item">
            <span class="res-icon">🪙</span>
            <span class="res-value"><?= $_fmt($_res['gold']) ?></span>
        </div>
    </div>
    <?php endif ?>

</nav>
