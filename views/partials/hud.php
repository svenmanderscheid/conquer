<?php
declare(strict_types=1);
/**
 * Game HUD — replaces nav.php on all game views.
 * Requires: $session (set by index.php)
 * Optional: $hudCurrentView (string: 'city'|'map'|'research'|..., default 'other')
 */
use Conquer\Db\Connection;

$hudCurrentView = $hudCurrentView ?? 'other';

$_hud_player = [];
$_hud_city   = [];
$_hud_charms = [];
$_hud_unread = 0;

try {
    $_hud_db = Connection::getInstance();

    // Player info + VIP
    $_hud_player = $_hud_db->query(
        'SELECT username, vip_level, vip_points, gems FROM players WHERE id = ?',
        [(int) $session['player_id']]
    )->fetch() ?: [];

    // City resources + power
    $_hud_city = $_hud_db->query(
        'SELECT food, lumber, stone, gold, power FROM cities WHERE player_id = ? LIMIT 1',
        [(int) $session['player_id']]
    )->fetch() ?: [];

    // Active charms
    $_hud_charms = $_hud_db->query(
        'SELECT stat_category, grade, bonus_pct, expires_at FROM player_charms_active
         WHERE player_id = ? AND expires_at > UTC_TIMESTAMP()
         ORDER BY expires_at ASC',
        [(int) $session['player_id']]
    )->fetchAll() ?: [];

    // Unread battle reports count
    $_hud_unread = (int) $_hud_db->query(
        'SELECT COUNT(*) FROM battle_reports WHERE attacker_id = ? AND attacker_read = 0',
        [(int) $session['player_id']]
    )->fetchColumn();
} catch (\Throwable) {}

$_hud_fmt = fn($n) => number_format((int) $n, 0, '.', ',');

$_hud_charm_names = [
    'construction_speed' => 'Bauen',
    'research_speed'     => 'Forschung',
    'troop_training'     => 'Training',
    'resource_production'=> 'Produktion',
    'gathering_speed'    => 'Sammeln',
    'attack_bonus'       => 'Angriff',
    'defense_bonus'      => 'Verteidigung',
    'march_speed'        => 'Marsch',
];

$_hud_grade_colors = [
    'common'    => '#94a3b8',
    'rare'      => '#22c55e',
    'epic'      => '#8b5cf6',
    'legendary' => '#f59e0b',
    'magic'     => '#0ea5e9',
];

$_hud_username  = htmlspecialchars((string) ($_hud_player['username'] ?? ($session['username'] ?? 'Spieler')));
$_hud_vip_level = (int) ($_hud_player['vip_level'] ?? 0);
$_hud_vip_pts   = (int) ($_hud_player['vip_points'] ?? 0);
$_hud_gems      = (int) ($_hud_player['gems'] ?? $session['gems'] ?? 0);
$_hud_power     = (int) ($_hud_city['power'] ?? 0);
$_hud_food      = $_hud_fmt($_hud_city['food']   ?? 0);
$_hud_lumber    = $_hud_fmt($_hud_city['lumber'] ?? 0);
$_hud_stone     = $_hud_fmt($_hud_city['stone']  ?? 0);
$_hud_gold      = $_hud_fmt($_hud_city['gold']   ?? 0);
$_hud_gems_fmt  = $_hud_fmt($_hud_gems);
$_hud_power_fmt = $_hud_fmt($_hud_power);
$_hud_charm_count = count($_hud_charms);

// Bottom nav toggle: city <-> map
if ($hudCurrentView === 'map') {
    $_hud_toggle_href  = '/city';
    $_hud_toggle_icon  = '&#x1F3F0;';
    $_hud_toggle_label = 'Stadt';
} else {
    $_hud_toggle_href  = '/map';
    $_hud_toggle_icon  = '&#x1F5FA;';
    $_hud_toggle_label = 'Karte';
}

// Charms as JSON for JS countdown
$_hud_charms_json = json_encode(array_map(function($c) {
    return [
        'stat_category' => $c['stat_category'],
        'grade'         => $c['grade'],
        'bonus_pct'     => $c['bonus_pct'],
        'expires_at'    => $c['expires_at'],
    ];
}, $_hud_charms), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

$_hud_charm_names_json = json_encode($_hud_charm_names, JSON_HEX_TAG);
$_hud_grade_colors_json = json_encode($_hud_grade_colors, JSON_HEX_TAG);
?>
<style>
/* ── HUD Reset / Base ─────────────────────────────────────────────────────── */
#hud-top,
#hud-bottom,
#hud-charm-popup,
#hud-messages-modal,
#hud-inventory-modal {
    font-family: system-ui, -apple-system, sans-serif;
    user-select: none;
    -webkit-user-select: none;
}

/* ── TOP BAR ─────────────────────────────────────────────────────────────── */
#hud-top {
    position: fixed;
    top: 0; left: 0; right: 0;
    height: 52px;
    z-index: 9000;
    background: var(--c-panel2);
    border-bottom: 2px solid var(--c-border);
    box-shadow: 0 2px 8px var(--c-shadow);
}

#hud-top-inner {
    max-width: 1280px;
    margin: 0 auto;
    height: 100%;
    display: flex;
    align-items: center;
    gap: 0;
    padding: 0 8px;
}

.hud-left {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
}

.hud-avatar {
    width: 36px;
    height: 36px;
    border-radius: 6px;
    background: linear-gradient(135deg, var(--c-gold), var(--c-wood-dark));
    border: 2px solid var(--c-gold);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}

.hud-playerinfo {
    display: flex;
    flex-direction: column;
    gap: 1px;
    line-height: 1;
}

.hud-username {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--c-text);
    white-space: nowrap;
    max-width: 90px;
    overflow: hidden;
    text-overflow: ellipsis;
}

.hud-power {
    font-size: 0.62rem;
    color: var(--c-muted);
    white-space: nowrap;
}

.hud-vip {
    font-size: 0.62rem;
    font-weight: 700;
    color: var(--c-wood-dark);
    background: #e8d0a8;
    border: 1px solid var(--c-border);
    border-radius: 4px;
    padding: 2px 5px;
    white-space: nowrap;
    cursor: default;
    flex-shrink: 0;
}

.hud-charm-btn {
    background: rgba(139,90,43,0.12);
    border: 1px solid var(--c-border);
    border-radius: 5px;
    color: var(--c-wood-dark);
    font-size: 0.68rem;
    font-weight: 700;
    padding: 3px 7px;
    cursor: pointer;
    white-space: nowrap;
    flex-shrink: 0;
    transition: background 0.15s;
    line-height: 1.4;
}

.hud-charm-btn:hover {
    background: rgba(139,90,43,0.22);
}

.hud-logout {
    margin: 0;
    padding: 0;
    flex-shrink: 0;
}

.hud-logout-btn {
    background: rgba(192,96,77,0.1);
    border: 1px solid rgba(192,96,77,0.35);
    border-radius: 5px;
    color: var(--c-danger);
    font-size: 0.75rem;
    padding: 3px 7px;
    cursor: pointer;
    line-height: 1.4;
    transition: background 0.15s;
}

.hud-logout-btn:hover {
    background: rgba(192,96,77,0.22);
}

.hud-sep {
    width: 1px;
    height: 28px;
    background: var(--c-border);
    flex-shrink: 0;
    margin: 0 2px;
}

/* Resources — right side, scrollable on small screens */
.hud-resources {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 6px;
    justify-content: flex-end;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
    padding: 0 4px;
    min-width: 0;
}

.hud-resources::-webkit-scrollbar {
    display: none;
}

.hud-res {
    display: flex;
    align-items: center;
    gap: 3px;
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--c-muted);
    white-space: nowrap;
    flex-shrink: 0;
}

.hud-res-icon {
    font-size: 0.85rem;
    line-height: 1;
}

.hud-res-val {
    color: var(--c-text);
    font-variant-numeric: tabular-nums;
}

.hud-gems .hud-res-val {
    color: var(--c-wood-dark);
}

/* ── CHARM POPUP ─────────────────────────────────────────────────────────── */
#hud-charm-popup {
    position: fixed;
    top: 60px;
    left: 8px;
    width: 240px;
    background: var(--c-panel);
    border: 1px solid var(--c-border);
    border-radius: 10px;
    z-index: 9100;
    box-shadow: 0 4px 20px var(--c-shadow);
    overflow: hidden;
}

.hud-charm-header {
    font-size: 0.65rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--c-wood-dark);
    padding: 8px 12px 6px;
    border-bottom: 1px solid var(--c-border);
}

.hud-charm-empty {
    padding: 12px;
    font-size: 0.75rem;
    color: var(--c-muted);
    text-align: center;
}

.hud-charm-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 7px 12px;
    border-bottom: 1px solid var(--c-border);
}

.hud-charm-item:last-child {
    border-bottom: none;
}

.hud-charm-diamond {
    width: 10px;
    height: 10px;
    transform: rotate(45deg);
    flex-shrink: 0;
}

.hud-charm-info {
    flex: 1;
    min-width: 0;
}

.hud-charm-name {
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--c-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.hud-charm-meta {
    font-size: 0.62rem;
    color: var(--c-muted);
}

.hud-charm-timer {
    font-size: 0.62rem;
    font-family: monospace;
    font-weight: 700;
    color: var(--c-muted);
    white-space: nowrap;
    flex-shrink: 0;
}

/* ── BOTTOM NAV ──────────────────────────────────────────────────────────── */
#hud-bottom {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 9000;
    pointer-events: none;
}

#hud-bottom-inner {
    max-width: 1280px;
    margin: 0 auto;
    padding: 0 12px 12px;
    display: flex;
    justify-content: flex-end;
    align-items: flex-end;
    gap: 8px;
    pointer-events: all;
}

.hud-nav-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 3px;
    width: 58px;
    height: 58px;
    padding: 6px 4px;
    background: linear-gradient(180deg, #c9925a, #9a6535);
    border: none;
    border-bottom: 3px solid #6b4120;
    border-radius: 10px;
    color: #fff8ec;
    font-size: 0.58rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    cursor: pointer;
    text-decoration: none;
    box-shadow: 0 2px 10px var(--c-shadow);
    transition: filter 0.15s, background 0.15s;
    position: relative;
    -webkit-tap-highlight-color: transparent;
}

.hud-nav-btn:hover {
    filter: brightness(1.1);
    text-decoration: none;
    color: #fff8ec;
}

.hud-nav-btn .hud-nav-icon {
    font-size: 1.3rem;
    line-height: 1;
}

.hud-nav-primary {
    background: linear-gradient(180deg, #d4a070, #a06830);
    border-bottom: 3px solid #6b4120;
}

.hud-nav-primary:hover {
    filter: brightness(1.1);
}

.hud-nav-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    background: #ef4444;
    color: #fff;
    font-size: 0.55rem;
    font-weight: 800;
    border-radius: 999px;
    padding: 1px 5px;
    min-width: 16px;
    text-align: center;
    line-height: 1.4;
}

/* ── MODAL BASE ──────────────────────────────────────────────────────────── */
.hud-modal {
    position: fixed;
    inset: 0;
    z-index: 9500;
    background: rgba(74,53,32,0.6);
    display: flex;
    align-items: stretch;
    justify-content: center;
}

.hud-modal-inner {
    width: 100%;
    max-width: 1280px;
    background: var(--c-panel);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.hud-modal-topbar {
    flex-shrink: 0;
    height: 48px;
    background: var(--c-panel3);
    border-bottom: 1px solid var(--c-border);
    display: flex;
    align-items: center;
    padding: 0 12px;
    gap: 10px;
}

.hud-modal-title {
    flex: 1;
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--c-wood-dark);
}

.hud-modal-close {
    background: rgba(192,96,77,0.12);
    border: 1px solid rgba(192,96,77,0.35);
    border-radius: 5px;
    color: var(--c-danger);
    font-size: 0.9rem;
    padding: 3px 10px;
    cursor: pointer;
    line-height: 1.5;
    transition: background 0.15s;
}

.hud-modal-close:hover {
    background: rgba(192,96,77,0.25);
}

/* Modal tab bar */
.hud-tab-bar {
    flex-shrink: 0;
    display: flex;
    background: var(--c-panel2);
    border-bottom: 1px solid var(--c-border);
    overflow-x: auto;
    scrollbar-width: none;
}

.hud-tab-bar::-webkit-scrollbar { display: none; }

.hud-tab-btn {
    padding: 9px 16px;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--c-muted);
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    white-space: nowrap;
    transition: color 0.15s;
    margin-bottom: -1px;
}

.hud-tab-btn:hover {
    color: var(--c-text);
}

.hud-tab-btn.hud-tab-active {
    color: var(--c-wood-dark);
    border-bottom-color: var(--c-gold);
}

.hud-tab-content {
    flex: 1;
    overflow: hidden;
    display: none;
}

.hud-tab-content.hud-tab-visible {
    display: flex;
    flex-direction: column;
}

/* Placeholder panels */
.hud-placeholder {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 8px;
    color: var(--c-muted);
    font-size: 0.85rem;
    font-style: italic;
}

/* ── MESSAGES MODAL — Reports tab ────────────────────────────────────────── */
.hud-reports-layout {
    flex: 1;
    display: flex;
    overflow: hidden;
}

.hud-report-list {
    width: 260px;
    flex-shrink: 0;
    overflow-y: auto;
    border-right: 1px solid var(--c-border);
    background: var(--c-panel2);
}

.hud-report-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 12px;
    border-bottom: 1px solid var(--c-border);
    cursor: pointer;
    transition: background 0.1s;
}

.hud-report-item:hover {
    background: rgba(139,90,43,0.08);
}

.hud-report-item.hud-report-active {
    background: rgba(139,90,43,0.14);
    border-left: 2px solid var(--c-gold);
    padding-left: 10px;
}

.hud-outcome-dot {
    font-size: 0.7rem;
    flex-shrink: 0;
}

.hud-report-name {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--c-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.hud-report-date {
    font-size: 0.62rem;
    color: var(--c-muted);
    white-space: nowrap;
}

.hud-report-list-empty {
    padding: 20px 12px;
    font-size: 0.75rem;
    color: var(--c-muted);
    text-align: center;
    font-style: italic;
}

.hud-report-detail-panel {
    flex: 1;
    overflow: hidden;
    background: var(--c-panel);
    display: flex;
    flex-direction: column;
}

.hud-report-detail-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: var(--c-muted);
    font-size: 0.82rem;
    font-style: italic;
}

/* VS layout in detail panel */
.hud-report-vs {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    gap: 0.75rem;
    padding: 1rem;
    background: var(--c-panel2);
    border-bottom: 1px solid var(--c-border);
    align-items: center;
}

.hud-vs-side {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.hud-vs-side.hud-vs-right {
    align-items: flex-end;
    text-align: right;
}

.hud-vs-name {
    font-size: 0.95rem;
    font-weight: 800;
    color: var(--c-wood-dark);
}

.hud-vs-name-enemy {
    font-size: 0.95rem;
    font-weight: 800;
    color: var(--c-danger);
}

.hud-vs-sub {
    font-size: 0.7rem;
    color: var(--c-muted);
}

.hud-vs-center-col {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    font-size: 1.3rem;
}

.hud-vs-label {
    font-size: 0.6rem;
    font-weight: 800;
    letter-spacing: 0.1em;
    color: var(--c-muted);
    text-transform: uppercase;
}

.hud-report-stats {
    padding: 1rem;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.hud-report-outcome-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 12px;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
}

.hud-report-stat-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.78rem;
    padding: 6px 0;
    border-bottom: 1px solid var(--c-border);
    color: var(--c-muted);
}

.hud-report-stat-row span:last-child {
    font-weight: 700;
    color: var(--c-text);
}

/* Mobile adjustments */
@media (max-width: 600px) {
    #hud-bottom-inner {
        padding: 0 8px 8px;
        gap: 6px;
    }

    .hud-nav-btn {
        width: 52px;
        height: 52px;
        padding: 5px 4px;
    }

    .hud-reports-layout {
        flex-direction: column;
    }

    .hud-report-list {
        width: 100%;
        height: 180px;
        border-right: none;
        border-bottom: 1px solid var(--c-border);
    }

    .hud-report-vs {
        grid-template-columns: 1fr auto 1fr;
        gap: 0.4rem;
        padding: 0.75rem;
    }

    .hud-vs-name,
    .hud-vs-name-enemy {
        font-size: 0.78rem;
    }
}
</style>

<!-- ── TOP BAR ─────────────────────────────────────────────────────────────── -->
<div id="hud-top">
<div id="hud-top-inner">
    <div class="hud-left">
        <div class="hud-avatar">&#x2694;</div>
        <div class="hud-playerinfo">
            <div class="hud-username"><?= $_hud_username ?></div>
            <div class="hud-power">&#x2694; <?= $_hud_power_fmt ?> Macht</div>
        </div>

        <div class="hud-sep"></div>

        <div class="hud-vip" title="VIP <?= $_hud_vip_level ?> — <?= $_hud_vip_pts ?> Punkte">
            &#x2B50; VIP <?= $_hud_vip_level ?>
        </div>

        <button class="hud-charm-btn" id="hud-charm-btn" title="Aktive Charms">
            &#x2728; <?= $_hud_charm_count ?>
        </button>

        <form method="post" action="/auth/logout" class="hud-logout">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($session['csrf_token'] ?? '')) ?>">
            <button type="submit" class="hud-logout-btn" title="Logout">&#x23FB;</button>
        </form>
    </div>

    <div class="hud-resources">
        <div class="hud-res">
            <span class="hud-res-icon">&#x1F33E;</span>
            <span class="hud-res-val"><?= $_hud_food ?></span>
        </div>
        <div class="hud-res">
            <span class="hud-res-icon">&#x1FAB5;</span>
            <span class="hud-res-val"><?= $_hud_lumber ?></span>
        </div>
        <div class="hud-res">
            <span class="hud-res-icon">&#x1FAA8;</span>
            <span class="hud-res-val"><?= $_hud_stone ?></span>
        </div>
        <div class="hud-res">
            <span class="hud-res-icon">&#x1FA99;</span>
            <span class="hud-res-val"><?= $_hud_gold ?></span>
        </div>
        <div class="hud-res hud-gems">
            <span class="hud-res-icon">&#x1F48E;</span>
            <span class="hud-res-val"><?= $_hud_gems_fmt ?></span>
        </div>
    </div>
</div><!-- /hud-top-inner -->
</div><!-- /hud-top -->

<!-- ── CHARM POPUP ─────────────────────────────────────────────────────────── -->
<div id="hud-charm-popup" style="display:none">
    <div class="hud-charm-header">Aktive Charms</div>
    <?php if ($_hud_charm_count === 0): ?>
        <div class="hud-charm-empty">Keine aktiven Charms</div>
    <?php else: ?>
        <?php foreach ($_hud_charms as $_hc): ?>
            <?php
                $_hc_color = $_hud_grade_colors[$_hc['grade']] ?? '#94a3b8';
                $_hc_name  = $_hud_charm_names[$_hc['stat_category']] ?? htmlspecialchars((string) $_hc['stat_category']);
                $_hc_bonus = '+' . (int) $_hc['bonus_pct'] . '%';
            ?>
            <div class="hud-charm-item">
                <div class="hud-charm-diamond" style="background:<?= $_hc_color ?>"></div>
                <div class="hud-charm-info">
                    <div class="hud-charm-name"><?= $_hc_name ?></div>
                    <div class="hud-charm-meta"><?= $_hc_bonus ?></div>
                </div>
                <div class="hud-charm-timer" data-expires="<?= htmlspecialchars((string) $_hc['expires_at']) ?>">--:--:--</div>
            </div>
        <?php endforeach ?>
    <?php endif ?>
</div>

<!-- ── BOTTOM NAV ─────────────────────────────────────────────────────────── -->
<div id="hud-bottom">
<div id="hud-bottom-inner">
    <button onclick="hudOpenInventory()" class="hud-nav-btn" title="Inventory">
        <span class="hud-nav-icon">&#x1F4E6;</span>
        <span>Inventory</span>
    </button>
    <a href="/alliance" class="hud-nav-btn" title="Allianz">
        <span class="hud-nav-icon">&#x2694;</span>
        <span>Allianz</span>
    </a>
    <button onclick="hudOpenMessages()" class="hud-nav-btn hud-nav-messages" title="Nachrichten">
        <span class="hud-nav-icon">&#x2709;</span>
        <span>Nachrichten</span>
        <?php if ($_hud_unread > 0): ?>
            <span class="hud-nav-badge"><?= $_hud_unread ?></span>
        <?php endif ?>
    </button>
    <a href="<?= $_hud_toggle_href ?>" class="hud-nav-btn hud-nav-primary" title="<?= htmlspecialchars($_hud_toggle_label) ?>">
        <span class="hud-nav-icon"><?= $_hud_toggle_icon ?></span>
        <span><?= htmlspecialchars($_hud_toggle_label) ?></span>
    </a>
</div>
</div>

<!-- ── MESSAGES MODAL ─────────────────────────────────────────────────────── -->
<div id="hud-messages-modal" class="hud-modal" style="display:none">
    <div class="hud-modal-inner">
        <div class="hud-modal-topbar">
            <span class="hud-modal-title">&#x2709; Nachrichten</span>
            <button class="hud-modal-close" onclick="hudCloseMessages()">&#x2715;</button>
        </div>
        <div class="hud-tab-bar">
            <button class="hud-tab-btn hud-tab-active" data-msgtab="reports" onclick="hudMsgTab('reports')">Berichte</button>
            <button class="hud-tab-btn" data-msgtab="personal" onclick="hudMsgTab('personal')">Privat</button>
            <button class="hud-tab-btn" data-msgtab="alliance" onclick="hudMsgTab('alliance')">Allianz</button>
            <button class="hud-tab-btn" data-msgtab="system" onclick="hudMsgTab('system')">System</button>
            <button class="hud-tab-btn" data-msgtab="saved" onclick="hudMsgTab('saved')">Gespeichert</button>
        </div>

        <!-- Reports tab -->
        <div class="hud-tab-content hud-tab-visible" id="hud-msgtab-reports">
            <div class="hud-reports-layout">
                <div class="hud-report-list" id="hud-report-list">
                    <div class="hud-report-list-empty" id="hud-report-list-empty">Laden&hellip;</div>
                </div>
                <div class="hud-report-detail-panel" id="hud-report-detail">
                    <div class="hud-report-detail-empty" id="hud-report-detail-empty">Bericht ausw&auml;hlen</div>
                    <iframe id="hud-report-iframe"
                            src="about:blank"
                            style="display:none;width:100%;height:100%;border:none;flex:1"
                            loading="lazy"></iframe>
                </div>
            </div>
        </div>

        <!-- Personal tab -->
        <div class="hud-tab-content" id="hud-msgtab-personal">
            <div class="hud-placeholder">
                <span style="font-size:1.5rem">&#x1F4AC;</span>
                Kommt bald &mdash; Private Nachrichten
            </div>
        </div>

        <!-- Alliance tab -->
        <div class="hud-tab-content" id="hud-msgtab-alliance">
            <div class="hud-placeholder">
                <span style="font-size:1.5rem">&#x2694;</span>
                Kommt bald &mdash; Allianz-Nachrichten
            </div>
        </div>

        <!-- System tab -->
        <div class="hud-tab-content" id="hud-msgtab-system">
            <div class="hud-placeholder">
                <span style="font-size:1.5rem">&#x2139;</span>
                Kommt bald &mdash; System-Nachrichten
            </div>
        </div>

        <!-- Saved tab -->
        <div class="hud-tab-content" id="hud-msgtab-saved">
            <div class="hud-placeholder">
                <span style="font-size:1.5rem">&#x1F4BE;</span>
                Kommt bald &mdash; Gespeicherte Berichte
            </div>
        </div>
    </div>
</div>

<!-- ── INVENTORY MODAL ─────────────────────────────────────────────────────── -->
<div id="hud-inventory-modal" class="hud-modal" style="display:none">
    <div class="hud-modal-inner">
        <div class="hud-modal-topbar">
            <span class="hud-modal-title">&#x1F4E6; Inventar</span>
            <button class="hud-modal-close" onclick="hudCloseInventory()">&#x2715;</button>
        </div>
        <div class="hud-tab-bar">
            <button class="hud-tab-btn hud-tab-active" data-invtab="res" onclick="hudInvTab('res')">Ressourcen</button>
            <button class="hud-tab-btn" data-invtab="boosts" onclick="hudInvTab('boosts')">Boosts</button>
            <button class="hud-tab-btn" data-invtab="speedups" onclick="hudInvTab('speedups')">Speedups</button>
            <button class="hud-tab-btn" data-invtab="misc" onclick="hudInvTab('misc')">Sonstiges</button>
        </div>
        <div class="hud-tab-content hud-tab-visible" id="hud-invtab-res">
            <div class="hud-placeholder">
                <span style="font-size:1.5rem">&#x1F33E;</span>
                Kommt bald &mdash; Ressourcen-Pakete
            </div>
        </div>
        <div class="hud-tab-content" id="hud-invtab-boosts">
            <div class="hud-placeholder">
                <span style="font-size:1.5rem">&#x26A1;</span>
                Kommt bald &mdash; Boosts
            </div>
        </div>
        <div class="hud-tab-content" id="hud-invtab-speedups">
            <div class="hud-placeholder">
                <span style="font-size:1.5rem">&#x23F1;</span>
                Kommt bald &mdash; Speedups
            </div>
        </div>
        <div class="hud-tab-content" id="hud-invtab-misc">
            <div class="hud-placeholder">
                <span style="font-size:1.5rem">&#x1F4CB;</span>
                Kommt bald &mdash; Sonstiges
            </div>
        </div>
    </div>
</div>

<script>
(function () {
'use strict';

// ── Charm popup ────────────────────────────────────────────────────────────
const HUD_CHARMS       = <?= $_hud_charms_json ?>;
const HUD_CHARM_NAMES  = <?= $_hud_charm_names_json ?>;
const HUD_GRADE_COLORS = <?= $_hud_grade_colors_json ?>;
const HUD_CSRF         = <?= json_encode((string) ($session['csrf_token'] ?? '')) ?>;

const charmBtn   = document.getElementById('hud-charm-btn');
const charmPopup = document.getElementById('hud-charm-popup');
let charmOpen    = false;

charmBtn.addEventListener('click', function (e) {
    e.stopPropagation();
    charmOpen = !charmOpen;
    charmPopup.style.display = charmOpen ? 'block' : 'none';
});

document.addEventListener('click', function (e) {
    if (charmOpen && !charmPopup.contains(e.target) && e.target !== charmBtn) {
        charmOpen = false;
        charmPopup.style.display = 'none';
    }
});

// ── Charm countdown timers ─────────────────────────────────────────────────
function hudFmtCountdown(expiresUtc) {
    const diff = Math.max(0, Math.floor(
        (new Date(expiresUtc.replace(' ', 'T') + 'Z').getTime() - Date.now()) / 1000
    ));
    const h = Math.floor(diff / 3600);
    const m = Math.floor((diff % 3600) / 60);
    const s = diff % 60;
    return String(h).padStart(2,'0') + ':' +
           String(m).padStart(2,'0') + ':' +
           String(s).padStart(2,'0');
}

const charmTimerEls = document.querySelectorAll('.hud-charm-timer[data-expires]');

function tickCharms() {
    charmTimerEls.forEach(function (el) {
        el.textContent = hudFmtCountdown(el.dataset.expires);
    });
}

tickCharms();
setInterval(tickCharms, 1000);

// ── Messages modal ─────────────────────────────────────────────────────────
const messagesModal  = document.getElementById('hud-messages-modal');
let reportsLoaded    = false;

window.hudOpenMessages = function () {
    messagesModal.style.display = 'flex';
    if (!reportsLoaded) {
        reportsLoaded = true;
        hudLoadReports();
    }
};

window.hudCloseMessages = function () {
    messagesModal.style.display = 'none';
};

window.hudMsgTab = function (tab) {
    document.querySelectorAll('#hud-messages-modal .hud-tab-btn').forEach(function (b) {
        b.classList.toggle('hud-tab-active', b.dataset.msgtab === tab);
    });
    document.querySelectorAll('#hud-messages-modal .hud-tab-content').forEach(function (c) {
        c.classList.toggle('hud-tab-visible', c.id === 'hud-msgtab-' + tab);
    });
};

// Close messages modal when clicking the backdrop
messagesModal.addEventListener('click', function (e) {
    if (e.target === messagesModal) {
        hudCloseMessages();
    }
});

// ── Reports loading ────────────────────────────────────────────────────────
const OUTCOME_COLORS = {
    attacker_wins: '#22c55e',
    defender_wins: '#ef4444',
    draw:          '#f59e0b',
};
const OUTCOME_LABELS = {
    attacker_wins: 'Sieg',
    defender_wins: 'Niederlage',
    draw:          'Unentschieden',
};

async function hudLoadReports() {
    const listEl  = document.getElementById('hud-report-list');
    const emptyEl = document.getElementById('hud-report-list-empty');

    try {
        const res  = await fetch('/api/battle/reports');
        const json = await res.json();

        if (!json.ok || !json.data || !Array.isArray(json.data.reports)) {
            if (emptyEl) emptyEl.textContent = 'Keine Berichte gefunden.';
            return;
        }

        const reports = json.data.reports;

        if (reports.length === 0) {
            if (emptyEl) emptyEl.textContent = 'Noch keine Kampfberichte.';
            return;
        }

        if (emptyEl) emptyEl.remove();

        reports.forEach(function (r) {
            const color   = OUTCOME_COLORS[r.outcome] ?? '#94a3b8';
            const label   = OUTCOME_LABELS[r.outcome] ?? r.outcome;
            const name    = r.monster_name ?? ('Tile ' + (r.target_x ?? '?') + ',' + (r.target_y ?? '?'));
            const dateStr = (r.created_at ?? '').substring(0, 16).replace('T', ' ');

            const item = document.createElement('div');
            item.className = 'hud-report-item';
            item.innerHTML =
                '<span class="hud-outcome-dot" style="color:' + color + '">&#x25CF;</span>' +
                '<div style="flex:1;min-width:0">' +
                  '<div class="hud-report-name">' + hudEsc(name) + '</div>' +
                  '<div class="hud-report-date">' + hudEsc(dateStr) + ' UTC</div>' +
                '</div>';
            item.addEventListener('click', function () {
                document.querySelectorAll('.hud-report-item').forEach(function (el) {
                    el.classList.remove('hud-report-active');
                });
                item.classList.add('hud-report-active');
                hudLoadReport(r.id);
            });
            listEl.appendChild(item);
        });
    } catch (e) {
        if (emptyEl) emptyEl.textContent = 'Fehler beim Laden.';
    }
}

function hudLoadReport(id) {
    const emptyEl  = document.getElementById('hud-report-detail-empty');
    const iframeEl = document.getElementById('hud-report-iframe');
    if (emptyEl)  emptyEl.style.display  = 'none';
    if (iframeEl) {
        iframeEl.style.display = 'block';
        iframeEl.src = '/reports/' + parseInt(id, 10) + '?embed=1';
    }
}

// ── Inventory modal ────────────────────────────────────────────────────────
const inventoryModal = document.getElementById('hud-inventory-modal');

window.hudOpenInventory = function () {
    inventoryModal.style.display = 'flex';
};

window.hudCloseInventory = function () {
    inventoryModal.style.display = 'none';
};

window.hudInvTab = function (tab) {
    document.querySelectorAll('#hud-inventory-modal .hud-tab-btn').forEach(function (b) {
        b.classList.toggle('hud-tab-active', b.dataset.invtab === tab);
    });
    document.querySelectorAll('#hud-inventory-modal .hud-tab-content').forEach(function (c) {
        c.classList.toggle('hud-tab-visible', c.id === 'hud-invtab-' + tab);
    });
};

// Close inventory modal when clicking the backdrop
inventoryModal.addEventListener('click', function (e) {
    if (e.target === inventoryModal) {
        hudCloseInventory();
    }
});

// ── Utility ────────────────────────────────────────────────────────────────
function hudEsc(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

})();
</script>
