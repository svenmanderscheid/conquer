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

    // Unread battle reports count (as attacker OR as scouted defender)
    $_hud_unread = (int) $_hud_db->query(
        'SELECT COUNT(*) FROM battle_reports
          WHERE (attacker_id = ? AND attacker_read = 0)
             OR (defender_id = ? AND defender_read = 0)',
        [(int) $session['player_id'], (int) $session['player_id']]
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

/* ── BOTTOM NAV / ACTION BAR (bottom-right) ──────────────────────────────── */
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
    pointer-events: none;
}

/* Action bar panel wrapping all nav buttons */
#hud-action-bar {
    pointer-events: all;
    display: flex;
    gap: 6px;
    align-items: flex-end;
    background: rgba(74,53,32,0.72);
    border: 1px solid var(--c-border);
    border-radius: 14px 14px 0 0;
    padding: 8px 10px 0;
    box-shadow: 0 -2px 12px rgba(74,53,32,0.3);
}

.hud-nav-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 3px;
    width: 60px;
    height: 60px;
    padding: 6px 4px;
    background: rgba(244,228,193,0.12);
    border: 1px solid rgba(244,228,193,0.22);
    border-bottom: none;
    border-radius: 10px 10px 0 0;
    color: #fff8ec;
    font-size: 0.58rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    cursor: pointer;
    text-decoration: none;
    box-shadow: 0 2px 10px var(--c-shadow);
    transition: background 0.15s, border-color 0.15s, color 0.15s;
    position: relative;
    -webkit-tap-highlight-color: transparent;
}

.hud-nav-btn:hover {
    background: rgba(192,136,88,0.35);
    border-color: var(--c-gold);
    color: var(--c-gold);
    text-decoration: none;
}

.hud-nav-btn .hud-nav-icon {
    font-size: 1.4rem;
    line-height: 1;
}

.hud-nav-primary {
    background: rgba(201,146,90,0.3);
    border-color: rgba(201,146,90,0.5);
}

.hud-nav-primary:hover {
    background: rgba(201,146,90,0.5);
    border-color: var(--c-gold);
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
    animation: overlay-fade-in 0.18s ease;
}

.hud-modal-inner {
    width: 100%;
    max-width: 1280px;
    background: var(--c-panel);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    animation: modal-slide-in 0.22s cubic-bezier(0.34, 1.3, 0.64, 1);
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

<!-- ── BOTTOM NAV / ACTION BAR (bottom-right, LoK style) ─────────────────── -->
<div id="hud-bottom">
<div id="hud-bottom-inner">
    <div id="hud-action-bar">
        <button onclick="hudOpenQuestLog()" class="hud-nav-btn" title="Quests">
            <span class="hud-nav-icon">&#x1F3AF;</span>
            <span>Quest</span>
        </button>
        <button onclick="hudOpenInventory()" class="hud-nav-btn" title="Inventory">
            <span class="hud-nav-icon">&#x1F392;</span>
            <span>Inv</span>
        </button>
        <button onclick="hudOpenMessages()" class="hud-nav-btn hud-nav-messages" title="Nachrichten">
            <span class="hud-nav-icon">&#x1F4DC;</span>
            <span>Berichte</span>
            <?php if ($_hud_unread > 0): ?>
                <span class="hud-nav-badge"><?= $_hud_unread ?></span>
            <?php endif ?>
        </button>
        <a href="/alliance" class="hud-nav-btn" title="Allianz">
            <span class="hud-nav-icon">&#x269C;</span>
            <span>Allianz</span>
        </a>
        <a href="<?= $_hud_toggle_href ?>" class="hud-nav-btn hud-nav-primary" title="<?= htmlspecialchars($_hud_toggle_label) ?>">
            <span class="hud-nav-icon"><?= $_hud_toggle_icon ?></span>
            <span><?= htmlspecialchars($_hud_toggle_label) ?></span>
        </a>
    </div>
</div>
</div>

<!-- ── BOTTOM CHAT WIDGET ─────────────────────────────────────────────────── -->
<?php
$_hud_in_alliance = false;
try {
    $__chatMember = Connection::getInstance()->query(
        'SELECT am.alliance_id FROM alliance_members am WHERE am.player_id = ?',
        [(int) $session['player_id']]
    )->fetch();
    $_hud_in_alliance = ($__chatMember !== false);
} catch (\Throwable) {}
?>
<style>
/* ── Bottom Chat bar (preview strip, always visible) ────────────────────── */
#hud-chat-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 8900;
    pointer-events: none;
}

#hud-chat-bar-inner {
    max-width: 1280px;
    margin: 0 auto;
    display: flex;
    align-items: flex-end;
    padding: 0 12px;
    pointer-events: all;
}

#hud-chat-preview {
    width: 340px;
    background: var(--c-panel3, #e8d8b0);
    border: 1px solid var(--c-border, rgba(139,90,43,0.35));
    border-bottom: none;
    border-radius: 10px 10px 0 0;
    overflow: hidden;
    cursor: pointer;
    font-family: system-ui, -apple-system, sans-serif;
    box-shadow: 0 -2px 10px var(--c-shadow, rgba(139,90,43,0.18));
    flex-shrink: 0;
    user-select: none;
    -webkit-user-select: none;
}

#hud-chat-preview-header {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 5px 10px 4px;
    border-bottom: 1px solid var(--c-border, rgba(139,90,43,0.35));
    background: var(--c-panel3, #e8d8b0);
}

#hud-chat-preview-title {
    flex: 1;
    font-size: 0.68rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: var(--c-wood-dark, #8b5a2b);
}

.hud-chat-open-btn {
    font-size: 0.62rem;
    font-weight: 700;
    color: var(--c-muted, #8b6f47);
    background: none;
    border: none;
    cursor: pointer;
    padding: 0 2px;
    line-height: 1;
    font-family: inherit;
    transition: color 0.15s;
}

.hud-chat-open-btn:hover {
    color: var(--c-wood-dark, #8b5a2b);
}

#hud-chat-preview-msgs {
    padding: 4px 10px 6px;
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-height: 40px;
}

.hud-chat-preview-msg {
    font-size: 0.72rem;
    line-height: 1.35;
    color: var(--c-text, #4a3520);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.hud-chat-preview-msg-user {
    font-weight: 800;
    color: var(--c-gold, #c08858);
    margin-right: 3px;
}

.hud-chat-preview-empty {
    font-size: 0.7rem;
    color: var(--c-muted, #8b6f47);
    font-style: italic;
    padding: 4px 0;
}

/* ── Chat Modal ─────────────────────────────────────────────────────────── */
#hud-chat-modal {
    position: fixed;
    inset: 0;
    z-index: 9600;
    background: rgba(74,53,32,0.55);
    display: none;
    align-items: center;
    justify-content: center;
}

#hud-chat-modal.hud-chat-modal-open {
    display: flex;
    animation: overlay-fade-in 0.18s ease;
}

#hud-chat-modal.hud-chat-modal-open #hud-chat-modal-box {
    animation: modal-slide-in 0.22s cubic-bezier(0.34, 1.3, 0.64, 1);
}

#hud-chat-modal-box {
    width: 600px;
    max-width: calc(100vw - 24px);
    height: 500px;
    max-height: calc(100vh - 80px);
    background: var(--c-panel, #f4e4c1);
    border: 1px solid var(--c-border, rgba(139,90,43,0.35));
    border-radius: 14px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 8px 40px rgba(74,53,32,0.4);
    font-family: system-ui, -apple-system, sans-serif;
}

#hud-chat-modal-topbar {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 0 14px;
    height: 44px;
    background: var(--c-panel3, #e8d8b0);
    border-bottom: 1px solid var(--c-border, rgba(139,90,43,0.35));
}

#hud-chat-modal-title {
    flex: 1;
    font-size: 0.82rem;
    font-weight: 800;
    color: var(--c-wood-dark, #8b5a2b);
}

.hud-chat-modal-close {
    background: rgba(192,96,77,0.1);
    border: 1px solid rgba(192,96,77,0.35);
    border-radius: 5px;
    color: var(--c-danger, #c0604d);
    font-size: 0.85rem;
    padding: 3px 9px;
    cursor: pointer;
    line-height: 1.5;
    transition: background 0.15s;
}

.hud-chat-modal-close:hover { background: rgba(192,96,77,0.22); }

/* Tab bar inside modal */
.hud-chat-modal-tabs {
    flex-shrink: 0;
    display: flex;
    background: var(--c-panel2, #ede0c4);
    border-bottom: 1px solid var(--c-border, rgba(139,90,43,0.35));
}

.hud-chat-tab-btn {
    flex: 1;
    padding: 7px 12px;
    font-size: 0.68rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--c-muted, #8b6f47);
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    transition: color 0.15s;
    margin-bottom: -1px;
    font-family: inherit;
}

.hud-chat-tab-btn:hover { color: var(--c-text, #4a3520); }
.hud-chat-tab-btn.hud-chat-tab-active {
    color: var(--c-wood-dark, #8b5a2b);
    border-bottom-color: var(--c-gold, #c08858);
}

/* Message list */
.hud-chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 8px 14px;
    display: flex;
    flex-direction: column;
    gap: 5px;
    scrollbar-width: thin;
    scrollbar-color: var(--c-border, rgba(139,90,43,0.35)) transparent;
}

.hud-chat-messages::-webkit-scrollbar { width: 4px; }
.hud-chat-messages::-webkit-scrollbar-thumb { background: var(--c-border, rgba(139,90,43,0.35)); border-radius: 2px; }

.hud-chat-msg {
    font-size: 0.8rem;
    line-height: 1.4;
    word-break: break-word;
}

.hud-chat-msg-user {
    font-weight: 800;
    color: var(--c-gold, #c08858);
    margin-right: 4px;
}

.hud-chat-msg-text {
    color: var(--c-text, #4a3520);
}

.hud-chat-msg-time {
    font-size: 0.62rem;
    color: var(--c-muted, #8b6f47);
    margin-left: 5px;
    font-family: monospace;
}

/* Input row */
.hud-chat-input-row {
    flex-shrink: 0;
    display: flex;
    gap: 6px;
    padding: 8px 12px;
    border-top: 1px solid var(--c-border, rgba(139,90,43,0.35));
    background: var(--c-panel2, #ede0c4);
}

.hud-chat-input {
    flex: 1;
    background: #fdf6e8;
    border: 1px solid var(--c-border, rgba(139,90,43,0.35));
    border-radius: 6px;
    color: var(--c-text, #4a3520);
    font-size: 0.8rem;
    padding: 6px 10px;
    outline: none;
    transition: border-color 0.15s;
    font-family: inherit;
    min-width: 0;
}

.hud-chat-input:focus { border-color: var(--c-gold, #c08858); }
.hud-chat-input::placeholder { color: var(--c-muted, #8b6f47); font-size: 0.75rem; }

.hud-chat-send-btn {
    flex-shrink: 0;
    background: linear-gradient(180deg, #c9925a, #9a6535);
    border: none;
    border-bottom: 2px solid #6b4120;
    border-radius: 6px;
    color: #fff8ec;
    font-size: 0.72rem;
    font-weight: 800;
    padding: 5px 14px;
    cursor: pointer;
    font-family: inherit;
    transition: filter 0.15s;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.hud-chat-send-btn:hover { filter: brightness(1.1); }
.hud-chat-send-btn:disabled { filter: grayscale(0.5) opacity(0.6); cursor: not-allowed; }

@media (max-width: 480px) {
    #hud-chat-preview { width: 240px; }
    #hud-chat-modal-box { height: 420px; }
}
</style>

<!-- ── Chat preview bar ──────────────────────────────────────────────────── -->
<div id="hud-chat-bar">
<div id="hud-chat-bar-inner">
    <div id="hud-chat-preview" onclick="hudChatOpenModal()">
        <div id="hud-chat-preview-header">
            <span id="hud-chat-preview-title">&#x1F4AC; Chat</span>
            <button class="hud-chat-open-btn" onclick="event.stopPropagation();hudChatOpenModal()" title="Chat öffnen">&#x2B0C;</button>
        </div>
        <div id="hud-chat-preview-msgs">
            <div class="hud-chat-preview-empty" id="hud-chat-preview-empty">Lade&hellip;</div>
        </div>
    </div>
</div>
</div>

<!-- ── Chat Modal ────────────────────────────────────────────────────────── -->
<div id="hud-chat-modal">
    <div id="hud-chat-modal-box">
        <div id="hud-chat-modal-topbar">
            <span id="hud-chat-modal-title">&#x1F4AC; Chat</span>
            <button class="hud-chat-modal-close" onclick="hudChatCloseModal()">&#x2715;</button>
        </div>
        <div class="hud-chat-modal-tabs">
            <button class="hud-chat-tab-btn hud-chat-tab-active" id="hud-chat-tab-world" onclick="hudChatSwitchTab('world')">&#x1F30D; Welt</button>
            <?php if ($_hud_in_alliance): ?>
            <button class="hud-chat-tab-btn" id="hud-chat-tab-alliance" onclick="hudChatSwitchTab('alliance')">&#x2694; Allianz</button>
            <?php endif ?>
        </div>
        <div class="hud-chat-messages" id="hud-chat-msgs"></div>
        <div class="hud-chat-input-row">
            <input type="text" class="hud-chat-input" id="hud-chat-input" placeholder="Nachricht..." maxlength="200"
                   onkeydown="if(event.key==='Enter')hudChatSend()">
            <button class="hud-chat-send-btn" id="hud-chat-send" onclick="hudChatSend()">Senden</button>
        </div>
    </div>
</div>

<script>
(function () {
'use strict';

const HUD_APP_BASE    = <?= json_encode(APP_BASE) ?>;
const HUD_CHAT_CSRF   = <?= json_encode((string) ($session['csrf_token'] ?? '')) ?>;
const HUD_IN_ALLIANCE = <?= $_hud_in_alliance ? 'true' : 'false' ?>;

let hudChatModalOpen  = false;
let hudChatTab        = 'world';
let hudChatLastId     = 0;
let hudChatAliLastId  = 0;
let hudChatPollTimer  = null;
let hudChatSending    = false;

// Preview messages (last 2 per tab)
let hudChatPreviewMsgs = [];

const modalEl   = document.getElementById('hud-chat-modal');
const msgsEl    = document.getElementById('hud-chat-msgs');
const inputEl   = document.getElementById('hud-chat-input');
const sendBtn   = document.getElementById('hud-chat-send');
const previewEl = document.getElementById('hud-chat-preview-msgs');

// ── Open / close modal ─────────────────────────────────────────────────────
window.hudChatOpenModal = function () {
    hudChatModalOpen = true;
    modalEl.classList.add('hud-chat-modal-open');
    msgsEl.innerHTML = '';
    hudChatLastId    = 0;
    hudChatAliLastId = 0;
    hudChatLoad(false);
    setTimeout(function () { inputEl.focus(); }, 80);
};

window.hudChatCloseModal = function () {
    hudChatModalOpen = false;
    modalEl.classList.remove('hud-chat-modal-open');
};

// Close when clicking the backdrop
modalEl.addEventListener('click', function (e) {
    if (e.target === modalEl) hudChatCloseModal();
});

// ── Tab switch ─────────────────────────────────────────────────────────────
window.hudChatSwitchTab = function (tab) {
    hudChatTab = tab;
    document.querySelectorAll('.hud-chat-tab-btn').forEach(function (b) {
        b.classList.toggle('hud-chat-tab-active', b.id === 'hud-chat-tab-' + tab);
    });
    msgsEl.innerHTML = '';
    hudChatLastId    = 0;
    hudChatAliLastId = 0;
    hudChatLoad(false);
};

// ── Render preview strip (last 2 msgs) ────────────────────────────────────
function hudChatUpdatePreview(msgs) {
    if (!msgs || msgs.length === 0) return;
    hudChatPreviewMsgs = msgs.slice(-2);
    const emptyEl = document.getElementById('hud-chat-preview-empty');
    if (emptyEl) emptyEl.remove();
    previewEl.innerHTML = '';
    hudChatPreviewMsgs.forEach(function (m) {
        const tag  = m.alliance_tag ? '[' + hudChatEsc(m.alliance_tag) + '] ' : '';
        const div  = document.createElement('div');
        div.className = 'hud-chat-preview-msg';
        div.innerHTML =
            '<span class="hud-chat-preview-msg-user">' + tag + hudChatEsc(m.username) + ':</span> ' +
            hudChatEsc(m.message);
        previewEl.appendChild(div);
    });
}

// ── Load / poll messages ───────────────────────────────────────────────────
async function hudChatLoad(poll) {
    let url;
    if (hudChatTab === 'world') {
        const sid = poll && hudChatLastId > 0 ? '?since_id=' + hudChatLastId : '';
        url = HUD_APP_BASE + '/api/chat/world' + sid;
    } else {
        const sid = poll && hudChatAliLastId > 0 ? '?since_id=' + hudChatAliLastId : '';
        url = HUD_APP_BASE + '/api/chat/alliance' + sid;
    }

    try {
        const res  = await fetch(url);
        const json = await res.json();
        if (!json.ok) return;

        const msgs = json.data.messages ?? [];
        if (msgs.length === 0 && poll) return;

        // Update preview strip (always, even when modal is closed)
        if (!poll || msgs.length > 0) {
            hudChatUpdatePreview(msgs);
        }

        // Only update the modal message list when it's open
        if (hudChatModalOpen) {
            if (!poll) {
                msgsEl.innerHTML = '';
            }

            msgs.forEach(function (m) {
                const tag  = m.alliance_tag ? '[' + hudChatEsc(m.alliance_tag) + '] ' : '';
                const time = (m.created_at ?? m.sent_at ?? '').substring(11, 16);
                const div  = document.createElement('div');
                div.className = 'hud-chat-msg';
                div.innerHTML =
                    '<span class="hud-chat-msg-user">' + tag + hudChatEsc(m.username) + '</span>' +
                    '<span class="hud-chat-msg-text">' + hudChatEsc(m.message) + '</span>' +
                    '<span class="hud-chat-msg-time">' + hudChatEsc(time) + '</span>';
                msgsEl.appendChild(div);
            });

            if (msgs.length > 0) {
                msgsEl.scrollTop = msgsEl.scrollHeight;
            }
        }

        if (msgs.length > 0) {
            const lastMsg = msgs[msgs.length - 1];
            if (hudChatTab === 'world') {
                hudChatLastId = lastMsg.id ?? hudChatLastId;
            } else {
                hudChatAliLastId = lastMsg.id ?? hudChatAliLastId;
            }
        }
    } catch { /* silent on poll */ }
}

// ── Send message ───────────────────────────────────────────────────────────
window.hudChatSend = async function () {
    const msg = (inputEl.value ?? '').trim();
    if (!msg || hudChatSending) return;

    hudChatSending   = true;
    sendBtn.disabled = true;

    const url = HUD_APP_BASE + (hudChatTab === 'world' ? '/api/chat/world' : '/api/chat/alliance');

    try {
        const res  = await fetch(url, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': HUD_CHAT_CSRF },
            body:    JSON.stringify({ message: msg }),
        });
        const json = await res.json();
        if (json.ok) {
            inputEl.value = '';
            await hudChatLoad(true);
        }
    } catch { /* silent */ }

    hudChatSending   = false;
    sendBtn.disabled = false;
};

// ── Auto-poll ──────────────────────────────────────────────────────────────
function hudChatStartPoll() {
    if (hudChatPollTimer) clearInterval(hudChatPollTimer);
    hudChatPollTimer = setInterval(function () {
        hudChatLoad(true);
    }, 8000);
}

// ── Init ───────────────────────────────────────────────────────────────────
hudChatLoad(false);
hudChatStartPoll();

function hudChatEsc(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

})();
</script>

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
<style>
/* ── Inventory modal extras ──────────────────────────────────────────────── */
.inv-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
    gap: 8px;
    padding: 12px 14px;
}

.inv-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    background: var(--c-panel2, #ede0c4);
    border: 1px solid var(--c-border, rgba(139,90,43,0.35));
    border-radius: 8px;
    padding: 8px 6px;
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s;
    text-align: center;
}

.inv-item:hover {
    border-color: var(--c-gold, #c08858);
    background: #e8d5af;
}

.inv-item-icon {
    font-size: 1.5rem;
    line-height: 1;
}

.inv-item-name {
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--c-text, #4a3520);
    line-height: 1.2;
    word-break: break-word;
    hyphens: auto;
}

.inv-item-qty {
    font-size: 0.72rem;
    font-weight: 800;
    color: var(--c-wood-dark, #8b5a2b);
    background: rgba(139,90,43,0.12);
    border-radius: 999px;
    padding: 1px 8px;
    min-width: 28px;
    text-align: center;
}

.inv-use-btn {
    width: 100%;
    background: linear-gradient(180deg, #c9925a, #9a6535);
    border: none;
    border-bottom: 2px solid #6b4120;
    border-radius: 5px;
    color: #fff8ec;
    font-size: 0.62rem;
    font-weight: 800;
    padding: 3px 0;
    cursor: pointer;
    font-family: inherit;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    transition: filter 0.15s;
    margin-top: 2px;
}

.inv-use-btn:hover { filter: brightness(1.1); }
.inv-use-btn:disabled { filter: grayscale(0.5) opacity(0.6); cursor: not-allowed; }

.inv-loading {
    padding: 24px 0;
    text-align: center;
    color: var(--c-muted, #8b6f47);
    font-size: 0.82rem;
    font-style: italic;
}

.inv-empty {
    padding: 24px 0;
    text-align: center;
    color: var(--c-muted, #8b6f47);
    font-size: 0.82rem;
    font-style: italic;
}

.inv-toast {
    position: absolute;
    bottom: 60px;
    left: 50%;
    transform: translateX(-50%);
    background: var(--c-panel3, #e8d8b0);
    border: 1px solid var(--c-border, rgba(139,90,43,0.35));
    border-radius: 8px;
    padding: 7px 18px;
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--c-wood-dark, #8b5a2b);
    white-space: nowrap;
    z-index: 1;
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.2s;
}

.inv-toast.inv-toast-ok  { color: #16a34a; border-color: #16a34a; }
.inv-toast.inv-toast-err { color: #c0604d; border-color: #c0604d; }
.inv-toast.inv-toast-show { opacity: 1; }
</style>

<div id="hud-inventory-modal" class="hud-modal" style="display:none">
    <div class="hud-modal-inner" style="max-width:760px;margin:auto;height:auto;max-height:80vh;border-radius:14px;position:relative;">
        <div class="hud-modal-topbar">
            <span class="hud-modal-title">&#x1F4E6; Inventar</span>
            <button class="hud-modal-close" onclick="hudCloseInventory()">&#x2715;</button>
        </div>
        <div class="hud-tab-bar">
            <button class="hud-tab-btn hud-tab-active" data-invtab="all" onclick="hudInvTab('all')">Alle</button>
            <button class="hud-tab-btn" data-invtab="resource_pack" onclick="hudInvTab('resource_pack')">Ressourcen</button>
            <button class="hud-tab-btn" data-invtab="boost" onclick="hudInvTab('boost')">Boosts</button>
            <button class="hud-tab-btn" data-invtab="speedup" onclick="hudInvTab('speedup')">Speedups</button>
            <button class="hud-tab-btn" data-invtab="misc" onclick="hudInvTab('misc')">Sonstiges</button>
        </div>
        <div id="hud-inv-body" style="overflow-y:auto;max-height:calc(80vh - 100px)">
            <div class="inv-loading" id="hud-inv-loading">Lade Inventar&hellip;</div>
            <div class="inv-grid" id="hud-inv-grid" style="display:none"></div>
            <div class="inv-empty" id="hud-inv-empty" style="display:none">Keine Items in dieser Kategorie.</div>
        </div>
        <div class="inv-toast" id="hud-inv-toast"></div>
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
const inventoryModal  = document.getElementById('hud-inventory-modal');
const invGridEl       = document.getElementById('hud-inv-grid');
const invLoadingEl    = document.getElementById('hud-inv-loading');
const invEmptyEl      = document.getElementById('hud-inv-empty');
const invToastEl      = document.getElementById('hud-inv-toast');

let invItems        = [];   // all items from API
let invTab          = 'all';
let invLoaded       = false;
let invUsing        = null; // item_code being used

// Category icons for inventory items
const INV_ICONS = {
    speedup:       '&#x23F1;',
    boost:         '&#x26A1;',
    resource_pack: '&#x1F4E6;',
    chest:         '&#x1F4DC;',
    teleport:      '&#x1F5FA;',
    shield:        '&#x1F6E1;',
    vip:           '&#x2B50;',
    other:         '&#x1F4CB;',
};

function invIcon(cat) {
    return INV_ICONS[cat] ?? INV_ICONS.other;
}

function invShowToast(msg, type) {
    invToastEl.innerHTML   = msg;
    invToastEl.className   = 'inv-toast inv-toast-' + type + ' inv-toast-show';
    setTimeout(function () { invToastEl.classList.remove('inv-toast-show'); }, 2500);
}

function invRender() {
    const filtered = invTab === 'all'
        ? invItems
        : invItems.filter(function (it) { return it.category === invTab; });

    invGridEl.innerHTML = '';

    if (filtered.length === 0) {
        invGridEl.style.display  = 'none';
        invEmptyEl.style.display = 'block';
        return;
    }

    invEmptyEl.style.display = 'none';
    invGridEl.style.display  = 'grid';

    filtered.forEach(function (it) {
        const card = document.createElement('div');
        card.className = 'inv-item';
        card.innerHTML =
            '<div class="inv-item-icon">' + invIcon(it.category) + '</div>' +
            '<div class="inv-item-name">' + hudEsc(it.name) + '</div>' +
            '<div class="inv-item-qty">x' + hudEsc(String(it.quantity)) + '</div>' +
            '<button class="inv-use-btn" data-code="' + (it.item_code|0) + '"' +
            (invUsing !== null ? ' disabled' : '') + '>Benutzen</button>';

        card.querySelector('.inv-use-btn').addEventListener('click', function (e) {
            e.stopPropagation();
            hudInvUse(it.item_code, card.querySelector('.inv-use-btn'));
        });

        invGridEl.appendChild(card);
    });
}

async function hudInvUse(itemCode, btn) {
    if (invUsing !== null) return;
    invUsing     = itemCode;
    btn.disabled = true;
    btn.textContent = '...';

    try {
        const res  = await fetch('/api/inventory/use', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': HUD_CSRF },
            body:    JSON.stringify({ item_code: itemCode, quantity: 1 }),
        });
        const json = await res.json();
        if (json.ok) {
            invShowToast('Verwendet!', 'ok');
            // Reload inventory
            invLoaded = false;
            await hudInvLoad();
        } else {
            invShowToast(json.message ?? json.error ?? 'Fehler', 'err');
            btn.disabled    = false;
            btn.textContent = 'Benutzen';
        }
    } catch {
        invShowToast('Netzwerkfehler', 'err');
        btn.disabled    = false;
        btn.textContent = 'Benutzen';
    }

    invUsing = null;
}

async function hudInvLoad() {
    if (invLoaded) {
        invRender();
        return;
    }
    invLoadingEl.style.display = 'block';
    invGridEl.style.display    = 'none';
    invEmptyEl.style.display   = 'none';

    try {
        const res  = await fetch('/api/inventory');
        const json = await res.json();
        if (json.ok && Array.isArray(json.data.items)) {
            invItems  = json.data.items.filter(function (it) { return it.quantity > 0; });
            invLoaded = true;
        }
    } catch { /* silent */ }

    invLoadingEl.style.display = 'none';
    invRender();
}

window.hudOpenInventory = function () {
    inventoryModal.style.display = 'flex';
    hudInvLoad();
};

window.hudCloseInventory = function () {
    inventoryModal.style.display = 'none';
};

window.hudInvTab = function (tab) {
    invTab = tab;
    document.querySelectorAll('#hud-inventory-modal .hud-tab-btn').forEach(function (b) {
        b.classList.toggle('hud-tab-active', b.dataset.invtab === tab);
    });
    invRender();
};

// Close inventory modal when clicking the backdrop
inventoryModal.addEventListener('click', function (e) {
    if (e.target === inventoryModal) {
        hudCloseInventory();
    }
});

// ── Quest log (stub — Sprint 5+) ───────────────────────────────────────────
window.hudOpenQuestLog = function () {
    alert('Quest-Log kommt in Sprint 5!');
};

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
