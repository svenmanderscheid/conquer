<?php
declare(strict_types=1);
/**
 * Dev-only UI preview — shows all Cozy-Stardew UI components.
 * Access: http://localhost/conquer/dev/ui-preview
 * NOT linked from anywhere in production.
 */
// Block on production
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(dirname(__DIR__)));
}
if (file_exists(ROOT_DIR . '/config/app.php')) {
    $cfg = require ROOT_DIR . '/config/app.php';
    if (($cfg['env'] ?? 'production') === 'production') {
        http_response_code(404);
        exit('Not found.');
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Conquer — UI Preview (Dev)</title>
<link rel="stylesheet" href="/conquer/assets/css/main.css">
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>
body { padding: 32px; max-width: 1100px; margin: 0 auto; }
.section { margin-bottom: 48px; }
.section-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--c-wood-dark);
    border-bottom: 2px solid var(--c-border);
    padding-bottom: 8px;
    margin-bottom: 20px;
    text-transform: uppercase;
    letter-spacing: 0.07em;
}
.row { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-start; margin-bottom: 16px; }
.demo-box { padding: 20px; background: var(--c-panel); border: 1px solid var(--c-border); border-radius: 10px; }
code { background: var(--c-stone); padding: 2px 6px; border-radius: 4px; font-size: 0.78rem; color: var(--c-wood-dark); }
</style>
</head>
<body x-data="{
    modal1: false,
    modal2: false,
    toast: false,
    toastMsg: '',
    toastType: 'ok',
    tooltip: false,
    showToast(msg, type) {
        this.toastMsg = msg;
        this.toastType = type;
        this.toast = true;
        setTimeout(() => this.toast = false, 4000);
    }
}">

<h1 style="font-size:1.6rem;color:var(--c-wood-dark);margin-bottom:8px;">🎮 Conquer — Cozy Theme Preview</h1>
<p style="color:var(--c-muted);margin-bottom:32px;">Dev-only. Zeigt alle UI-Komponenten des Cozy-Stardew Themes.</p>

<!-- ── Color Palette ──────────────────────────────────────────────────── -->
<div class="section">
    <div class="section-title">Color Palette</div>
    <div class="row">
        <?php
        $colors = [
            '--c-bg'       => '#f0e8d0',
            '--c-panel'    => '#f4e4c1',
            '--c-panel2'   => '#ede0c4',
            '--c-panel3'   => '#e8d8b0',
            '--c-gold'     => '#c08858',
            '--c-gold-l'   => '#d4a070',
            '--c-wood-dark'=> '#8b5a2b',
            '--c-stone'    => '#d4cfc4',
            '--c-text'     => '#4a3520',
            '--c-muted'    => '#8b6f47',
            '--c-danger'   => '#c0604d',
            '--c-success'  => '#7fb069',
            '--c-info'     => '#5f9ea0',
            '--c-warning'  => '#d4824d',
        ];
        foreach ($colors as $name => $hex):
        ?>
        <div style="text-align:center;width:90px;">
            <div style="width:60px;height:60px;border-radius:8px;background:<?= $hex ?>;border:1px solid rgba(139,90,43,0.3);margin:0 auto 6px;box-shadow:0 2px 6px rgba(139,90,43,0.15);"></div>
            <div style="font-size:0.65rem;color:var(--c-text);font-weight:600;"><?= $name ?></div>
            <div style="font-size:0.6rem;color:var(--c-muted);"><?= $hex ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ── Buttons ───────────────────────────────────────────────────────── -->
<div class="section">
    <div class="section-title">Buttons — .btn-game</div>
    <div class="row">
        <button class="btn-game">Default (Wood)</button>
        <button class="btn-game btn-game-blue">Blue</button>
        <button class="btn-game btn-game-green">Green</button>
        <button class="btn-game btn-game-red">Red</button>
        <button class="btn-game btn-game-purple">Purple</button>
    </div>
    <div class="row">
        <button class="btn-game btn-game-sm">Small</button>
        <button class="btn-game">Normal</button>
        <button class="btn-game btn-game-lg">Large</button>
        <button class="btn-game" disabled>Disabled</button>
    </div>
</div>

<!-- ── Panels ────────────────────────────────────────────────────────── -->
<div class="section">
    <div class="section-title">Panels — .game-panel</div>
    <div class="row">
        <div class="game-panel" style="width:280px;">
            <div class="game-panel-header">🏰 Gebäude-Info</div>
            <div class="game-panel-body">
                <div class="stat-row"><span class="label">Level</span><span class="value">12</span></div>
                <div class="stat-row"><span class="label">Produktion</span><span class="value">450/h</span></div>
                <div class="stat-row"><span class="label">Bauzeit</span><span class="value">2h 15m</span></div>
                <div style="margin-top:12px;">
                    <button class="btn-game btn-game-sm">Upgraden</button>
                </div>
            </div>
        </div>
        <div class="game-panel" style="width:240px;">
            <div class="game-panel-header">⚔️ Truppen</div>
            <div class="game-panel-body">
                <p style="color:var(--c-muted);font-size:0.82rem;line-height:1.5;">Schlichtes Panel ohne Inhalt — für Truppenlisten etc.</p>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal (large) ─────────────────────────────────────────────────── -->
<div class="section">
    <div class="section-title">Modal Dialog (Large)</div>
    <div class="row">
        <button class="btn-game" @click="modal1 = true">Modal öffnen</button>
    </div>

    <!-- Modal -->
    <div x-show="modal1" x-cloak
         style="position:fixed;inset:0;background:rgba(74,53,32,0.6);z-index:4000;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(2px);"
         @click.self="modal1 = false">
        <div style="background:var(--c-panel);border:2px solid var(--c-border);border-radius:12px;width:480px;max-width:92vw;box-shadow:0 8px 32px rgba(139,90,43,0.3);"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100">
            <!-- Header -->
            <div style="background:linear-gradient(180deg,var(--c-panel3),var(--c-panel2));border-bottom:1px solid var(--c-border);border-radius:12px 12px 0 0;padding:14px 20px;display:flex;justify-content:space-between;align-items:center;">
                <span style="font-weight:700;color:var(--c-wood-dark);font-size:0.95rem;text-transform:uppercase;letter-spacing:0.05em;">🏯 Burg — Level 10</span>
                <button @click="modal1 = false" style="background:none;border:none;color:var(--c-muted);font-size:1.2rem;cursor:pointer;line-height:1;padding:2px 6px;border-radius:6px;"
                        onmouseover="this.style.background='rgba(139,90,43,0.12)'" onmouseout="this.style.background='none'">✕</button>
            </div>
            <!-- Body -->
            <div style="padding:20px;display:flex;gap:16px;">
                <img src="/conquer/assets/sprites/pixel/buildings/castle.png" alt="Castle"
                     style="width:96px;height:96px;image-rendering:pixelated;border:1px solid var(--c-border);border-radius:8px;background:var(--c-panel2);padding:4px;flex-shrink:0;">
                <div style="flex:1;">
                    <div class="stat-row"><span class="label">Power</span><span class="value">12,450</span></div>
                    <div class="stat-row"><span class="label">Ressourcenschutz</span><span class="value">100,000</span></div>
                    <div class="stat-row"><span class="label">Max. Gebäude-Level</span><span class="value">10</span></div>
                    <div class="stat-row"><span class="label">Upgrade-Zeit</span><span class="value">3h 20m</span></div>
                </div>
            </div>
            <hr style="border:none;border-top:1px solid var(--c-border);margin:0 20px;">
            <!-- Footer -->
            <div style="padding:16px 20px;display:flex;gap:10px;justify-content:flex-end;">
                <button class="btn-game btn-game-sm" style="background:var(--c-stone);color:var(--c-text);border-bottom-color:#b0a898;"
                        @click="modal1 = false">Schließen</button>
                <button class="btn-game btn-game-sm btn-game-green">Upgraden ▲</button>
            </div>
        </div>
    </div>
</div>

<!-- ── Confirmation Dialog ────────────────────────────────────────────── -->
<div class="section">
    <div class="section-title">Confirmation Dialog</div>
    <div class="row">
        <button class="btn-game btn-game-red" @click="modal2 = true">Bestätigung anfordern</button>
    </div>

    <div x-show="modal2" x-cloak
         style="position:fixed;inset:0;background:rgba(74,53,32,0.6);z-index:4000;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(2px);"
         @click.self="modal2 = false">
        <div style="background:var(--c-panel);border:2px solid rgba(192,96,77,0.5);border-radius:12px;width:340px;max-width:92vw;padding:28px;box-shadow:0 8px 32px rgba(139,90,43,0.3);text-align:center;"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100">
            <div style="font-size:2rem;margin-bottom:12px;">⚠️</div>
            <div style="font-weight:700;color:var(--c-wood-dark);font-size:1rem;margin-bottom:8px;">Allianz verlassen?</div>
            <div style="color:var(--c-muted);font-size:0.85rem;margin-bottom:20px;line-height:1.5;">Bist du sicher? Du verlierst alle Allianz-Boni und musst 24h warten bevor du wieder beitreten kannst.</div>
            <div style="display:flex;gap:10px;justify-content:center;">
                <button class="btn-game btn-game-sm" style="background:var(--c-stone);color:var(--c-text);border-bottom-color:#b0a898;"
                        @click="modal2 = false">Abbrechen</button>
                <button class="btn-game btn-game-sm btn-game-red" @click="modal2 = false; showToast('Allianz verlassen.', 'err')">Ja, verlassen</button>
            </div>
        </div>
    </div>
</div>

<!-- ── Toasts ────────────────────────────────────────────────────────── -->
<div class="section">
    <div class="section-title">Toast / Notifications</div>
    <div class="row">
        <button class="btn-game btn-game-green btn-game-sm" @click="showToast('Gebäude erfolgreich upgradet!', 'ok')">✓ Erfolg-Toast</button>
        <button class="btn-game btn-game-red btn-game-sm"   @click="showToast('Nicht genug Ressourcen!', 'err')">✗ Fehler-Toast</button>
        <button class="btn-game btn-game-blue btn-game-sm"  @click="showToast('Marsch kommt in 5 Minuten an.', 'info')">ℹ Info-Toast</button>
    </div>

    <!-- Toast -->
    <div x-show="toast" x-cloak
         style="position:fixed;bottom:1.5rem;right:1.5rem;z-index:9000;background:var(--c-panel);border:1px solid var(--c-border);border-radius:8px;padding:12px 16px;font-size:0.85rem;font-weight:600;color:var(--c-text);box-shadow:0 4px 16px rgba(139,90,43,0.25);min-width:240px;max-width:340px;"
         :style="toastType === 'ok' ? 'border-left:4px solid var(--c-success);color:#3a6a28;' : toastType === 'err' ? 'border-left:4px solid var(--c-danger);color:#7a2a20;' : 'border-left:4px solid var(--c-info);color:#2a5a60;'"
         x-transition:enter="transition ease-out duration-250"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-end="opacity-0">
        <span x-text="toastMsg"></span>
    </div>
</div>

<!-- ── Tooltip ───────────────────────────────────────────────────────── -->
<div class="section">
    <div class="section-title">Tooltip</div>
    <div class="row">
        <div style="position:relative;display:inline-block;" x-data="{tip:false}">
            <button class="btn-game btn-game-sm" @mouseenter="tip=true" @mouseleave="tip=false">
                🌾 Farm Lv.8 — Hover für Info
            </button>
            <div x-show="tip" x-cloak
                 style="position:absolute;bottom:calc(100% + 8px);left:50%;transform:translateX(-50%);background:var(--c-panel);border:1px solid var(--c-border);border-radius:8px;padding:10px 14px;font-size:0.78rem;color:var(--c-text);box-shadow:0 4px 12px rgba(139,90,43,0.2);white-space:nowrap;z-index:100;pointer-events:none;">
                <div style="font-weight:700;color:var(--c-wood-dark);margin-bottom:4px;">🌾 Farm</div>
                <div style="color:var(--c-muted);">Nahrung: <strong style="color:var(--c-text);">1,200/h</strong></div>
                <div style="color:var(--c-muted);">Power: <strong style="color:var(--c-text);">400</strong></div>
                <!-- Arrow -->
                <div style="position:absolute;bottom:-5px;left:50%;transform:translateX(-50%);width:9px;height:9px;background:var(--c-panel);border-right:1px solid var(--c-border);border-bottom:1px solid var(--c-border);transform:translateX(-50%) rotate(45deg);"></div>
            </div>
        </div>
    </div>
</div>

<!-- ── Building Info Panel ────────────────────────────────────────────── -->
<div class="section">
    <div class="section-title">Building Info Panel</div>
    <div class="row">
        <div style="background:var(--c-panel);border:1px solid var(--c-border);border-radius:10px;width:280px;box-shadow:0 3px 12px rgba(139,90,43,0.15);">
            <div style="background:linear-gradient(180deg,var(--c-panel3),var(--c-panel2));border-bottom:1px solid var(--c-border);border-radius:10px 10px 0 0;padding:12px 16px;">
                <div style="font-weight:700;color:var(--c-wood-dark);font-size:0.88rem;text-transform:uppercase;letter-spacing:0.05em;">Kaserne</div>
                <div style="font-size:0.75rem;color:var(--c-muted);">Level 7 / 30</div>
            </div>
            <div style="padding:16px;text-align:center;">
                <img src="/conquer/assets/sprites/pixel/buildings/barracks.png" alt="Kaserne"
                     style="width:96px;height:96px;image-rendering:pixelated;border:1px solid var(--c-border);border-radius:8px;background:var(--c-bg);padding:4px;margin-bottom:12px;">
                <div style="font-size:0.78rem;color:var(--c-muted);margin-bottom:12px;line-height:1.4;">Rekrutiert Infanterie, Schützen und Kavallerie für deine Armee.</div>
            </div>
            <hr style="border:none;border-top:1px solid var(--c-border);">
            <div style="padding:12px 16px;">
                <div class="stat-row"><span class="label">Training-Geschw.</span><span class="value">+35%</span></div>
                <div class="stat-row"><span class="label">Max. Queue</span><span class="value">5 Slots</span></div>
                <div class="stat-row"><span class="label">Power</span><span class="value">1,750</span></div>
            </div>
            <div style="padding:12px 16px;display:flex;gap:8px;">
                <button class="btn-game btn-game-sm btn-game-blue" style="flex:1;">Info</button>
                <button class="btn-game btn-game-sm btn-game-green" style="flex:1;">Upgrade ▲</button>
            </div>
        </div>
    </div>
</div>

<!-- ── Badges ────────────────────────────────────────────────────────── -->
<div class="section">
    <div class="section-title">Badges</div>
    <div class="row">
        <span class="badge badge-wood">Gewöhnlich</span>
        <span class="badge badge-green">Selten</span>
        <span class="badge badge-blue">Episch</span>
        <span class="badge badge-purple">Legendär</span>
        <span class="badge badge-red">Mythisch</span>
        <span class="badge badge-stone">Inaktiv</span>
    </div>
</div>

<!-- ── Progress Bars ─────────────────────────────────────────────────── -->
<div class="section">
    <div class="section-title">Progress Bars</div>
    <div style="display:flex;flex-direction:column;gap:10px;max-width:400px;">
        <div>
            <div style="font-size:0.75rem;color:var(--c-muted);margin-bottom:4px;">Nahrung (75%)</div>
            <div class="progress-track"><div class="progress-fill" style="width:75%"></div></div>
        </div>
        <div>
            <div style="font-size:0.75rem;color:var(--c-muted);margin-bottom:4px;">HP Mauer (40%)</div>
            <div class="progress-track"><div class="progress-fill red" style="width:40%"></div></div>
        </div>
        <div>
            <div style="font-size:0.75rem;color:var(--c-muted);margin-bottom:4px;">Forschung (90%)</div>
            <div class="progress-track"><div class="progress-fill green" style="width:90%"></div></div>
        </div>
        <div>
            <div style="font-size:0.75rem;color:var(--c-muted);margin-bottom:4px;">Aktionspunkte (60%)</div>
            <div class="progress-track"><div class="progress-fill blue" style="width:60%"></div></div>
        </div>
    </div>
</div>

<!-- ── Table ─────────────────────────────────────────────────────────── -->
<div class="section">
    <div class="section-title">Table — .game-table</div>
    <div class="game-panel">
        <table class="game-table">
            <thead>
                <tr>
                    <th>Spieler</th>
                    <th>Power</th>
                    <th>Kills</th>
                    <th>Allianz</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <tr><td>Ekki</td><td>125,400</td><td>42</td><td>[CNQ]</td><td><span class="badge badge-green">Online</span></td></tr>
                <tr><td>Svenix</td><td>98,200</td><td>18</td><td>[CNQ]</td><td><span class="badge badge-stone">Offline</span></td></tr>
                <tr><td>Thorvald</td><td>77,800</td><td>31</td><td>—</td><td><span class="badge badge-stone">Offline</span></td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Form elements ─────────────────────────────────────────────────── -->
<div class="section">
    <div class="section-title">Form Inputs</div>
    <div style="display:flex;flex-direction:column;gap:12px;max-width:360px;">
        <input type="text" class="game-input" placeholder="Allianztag (z.B. CNQ)">
        <input type="text" class="game-input" placeholder="Suche nach Spieler..." value="Ekki">
        <input type="number" class="game-input" placeholder="Anzahl Truppen">
        <button class="btn-game btn-game-green" style="align-self:flex-start;">Bestätigen</button>
    </div>
</div>

<p style="color:var(--c-muted);font-size:0.78rem;text-align:center;margin-top:48px;padding-top:16px;border-top:1px solid var(--c-border);">
    Dev-Preview — nur sichtbar im development-Modus
</p>

</body>
</html>
