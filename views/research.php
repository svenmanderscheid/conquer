<?php
declare(strict_types=1);
/**
 * Research view — /research
 * Variables: $session (from index.php)
 */

use Conquer\Db\Connection;
use Conquer\Game\Research\ResearchData;
use Conquer\Game\Research\BuffEngine;
use Conquer\Game\Research\ResearchProcessor;

$db       = Connection::getInstance();
$playerId = (int) $session['player_id'];

// Lazy-tick: process finished research
ResearchProcessor::processQueue($playerId);

// Academy level (from city_buildings)
$academyRow = $db->query(
    "SELECT level FROM city_buildings cb
     JOIN cities c ON c.id = cb.city_id
     WHERE c.player_id = ? AND cb.building_code = 'academy' LIMIT 1",
    [$playerId],
)->fetch();
$academyLevel = $academyRow ? (int) $academyRow['level'] : 0;

// Current research levels
$researchRows = $db->query(
    'SELECT research_code, level FROM player_research WHERE player_id = ? AND world_id = 1',
    [$playerId],
)->fetchAll();
$researchLevels = [];
foreach ($researchRows as $r) {
    $researchLevels[$r['research_code']] = (int) $r['level'];
}

// Active queue
$queueRow = $db->query(
    "SELECT * FROM research_queue
     WHERE player_id = ? AND is_processed = 0
     ORDER BY id DESC LIMIT 1",
    [$playerId],
)->fetch() ?: null;

// City resources
$cityRow = $db->query(
    'SELECT food, lumber, stone, gold FROM cities WHERE player_id = ? LIMIT 1',
    [$playerId],
)->fetch() ?: ['food' => 0, 'lumber' => 0, 'stone' => 0, 'gold' => 0];

// Buffs
$buffs = BuffEngine::getBuffs($playerId);

// Load all tree data for the view
$trees = [
    'battle'     => ResearchData::tree('battle'),
    'production' => ResearchData::tree('production'),
    'advanced'   => ResearchData::tree('advanced'),
];

$fmt = fn(mixed $n): string => number_format((int) $n, 0, '.', ',');
$fmtTime = function(int $sec): string {
    if ($sec < 60) return $sec . 's';
    if ($sec < 3600) return floor($sec / 60) . 'm ' . ($sec % 60) . 's';
    $h = floor($sec / 3600); $m = floor(($sec % 3600) / 60);
    return $h . 'h ' . ($m > 0 ? $m . 'm' : '');
};
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conquer — Forschung</title>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:      #0a0e1a;
            --surface: #111827;
            --surface2: #1a2235;
            --border:  #1e3a5f;
            --border2: #2a4a7f;
            --text:    #d1dce8;
            --muted:   #6b82a0;
            --gold:    #d4a017;
            --gold2:   #f0c040;
            --green:   #22c55e;
            --red:     #dc2626;
            --blue:    #1e4080;
            --blue2:   #2563a8;
        }

        html, body {
            min-height: 100%;
            background: var(--bg);
            color: var(--text);
            font-family: system-ui, -apple-system, sans-serif;
            display: flex;
            justify-content: center;
        }

        #game {
            width: 100%;
            max-width: 1200px;
            min-height: calc(100vh - 72px);
            margin-top: 72px;
            display: flex;
            flex-direction: column;
        }

        /* ── Top bar ── */
        .topbar {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 0.5rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        .topbar-back {
            padding: 0.25rem 0.75rem;
            border-radius: 5px;
            background: var(--bg);
            border: 1px solid var(--border);
            color: var(--muted);
            text-decoration: none;
            font-size: 0.78rem;
        }
        .topbar-back:hover { border-color: var(--gold); color: var(--gold); }
        .topbar-title { font-size: 0.9rem; font-weight: 700; color: var(--gold2); }
        .topbar-academy {
            margin-left: auto;
            font-size: 0.75rem;
            color: var(--muted);
        }
        .topbar-academy strong { color: var(--text); }

        /* ── Queue banner ── */
        .queue-banner {
            background: linear-gradient(90deg, var(--blue) 0%, transparent 100%);
            border-bottom: 1px solid var(--border2);
            padding: 0.6rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 0.82rem;
        }
        .queue-banner-label { color: var(--muted); font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; }
        .queue-banner-name  { color: var(--gold2); font-weight: 700; }
        .queue-banner-eta   { margin-left: auto; color: var(--muted); }

        /* ── Layout ── */
        .main-layout {
            display: grid;
            grid-template-columns: 1fr 240px;
            gap: 0;
            flex: 1;
        }
        @media (max-width: 768px) { .main-layout { grid-template-columns: 1fr; } }

        /* ── Tabs ── */
        .tab-bar {
            display: flex;
            border-bottom: 2px solid var(--border);
            background: var(--surface2);
        }
        .tab-btn {
            padding: 0.6rem 1.2rem;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--muted);
            cursor: pointer;
            border: none;
            background: none;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: color .15s;
        }
        .tab-btn:hover { color: var(--text); }
        .tab-btn.active { color: var(--gold2); border-bottom-color: var(--gold2); }

        /* ── Node grid ── */
        .node-grid {
            padding: 1rem;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 0.75rem;
            align-content: start;
        }

        .node-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0.85rem 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            transition: border-color .15s;
        }
        .node-card:hover { border-color: var(--border2); }
        .node-card.locked { opacity: 0.5; }
        .node-card.maxed  { border-color: rgba(212,160,23,.3); }
        .node-card.active { border-color: var(--blue2); background: rgba(30,64,128,.15); }

        .node-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 0.5rem;
        }
        .node-name { font-size: 0.82rem; font-weight: 700; line-height: 1.3; }
        .node-level-badge {
            flex-shrink: 0;
            font-size: 0.65rem;
            font-weight: 800;
            padding: 0.15rem 0.4rem;
            border-radius: 3px;
            background: var(--surface2);
            border: 1px solid var(--border);
            color: var(--muted);
            white-space: nowrap;
        }
        .node-level-badge.maxed { background: rgba(212,160,23,.15); border-color: rgba(212,160,23,.3); color: var(--gold2); }
        .node-level-badge.active { background: rgba(30,64,128,.3); border-color: var(--blue2); color: #7ab4e0; }

        .node-effect {
            font-size: 0.72rem;
            color: var(--green);
            font-weight: 600;
        }
        .node-effect.unlock-type { color: var(--gold2); }

        .node-req {
            font-size: 0.68rem;
            color: var(--muted);
        }

        .node-costs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.2rem;
            font-size: 0.68rem;
            color: var(--muted);
        }
        .node-costs span strong { color: var(--text); }

        .node-time {
            font-size: 0.68rem;
            color: var(--muted);
        }

        .btn-research {
            width: 100%;
            padding: 0.4rem;
            border-radius: 5px;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            border: 1px solid var(--blue2);
            background: var(--blue);
            color: #7ab4e0;
            transition: background .15s, color .15s;
        }
        .btn-research:hover:not(:disabled) {
            background: var(--blue2);
            color: #e2f0ff;
        }
        .btn-research:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        .btn-research.maxed-btn {
            border-color: rgba(212,160,23,.3);
            background: rgba(212,160,23,.08);
            color: var(--gold2);
            cursor: default;
        }

        /* ── Sidebar ── */
        .sidebar {
            border-left: 1px solid var(--border);
            background: var(--surface2);
            padding: 1rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .sidebar-section-title {
            font-size: 0.62rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--muted);
            padding-bottom: 0.4rem;
            border-bottom: 1px solid var(--border);
            margin-bottom: 0.5rem;
        }

        .boost-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.73rem;
            padding: 0.18rem 0;
            border-bottom: 1px solid rgba(255,255,255,0.03);
        }
        .boost-row:last-child { border-bottom: none; }
        .boost-label { color: var(--muted); }
        .boost-val { font-weight: 700; }
        .boost-val.active { color: var(--green); }
        .boost-val.zero   { color: var(--muted); }

        /* ── Toast ── */
        .toast {
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 600;
            z-index: 9000;
        }
        .toast.ok  { background: rgba(34,197,94,.15); border: 1px solid rgba(34,197,94,.4); color: #22c55e; }
        .toast.err { background: rgba(220,38,38,.15); border: 1px solid rgba(220,38,38,.4);  color: #ef4444; }
    </style>
</head>
<body>
<?php require __DIR__ . '/partials/nav.php'; ?>

<div id="game" x-data="researchApp()" x-init="boot()">

    <div class="topbar">
        <a href="/city" class="topbar-back">← Stadt</a>
        <span class="topbar-title">🔬 Forschung</span>
        <div class="topbar-academy">
            Akademie Lv <strong><?= $academyLevel ?></strong>
        </div>
    </div>

    <!-- Active queue banner -->
    <template x-if="queue && !queue.done">
        <div class="queue-banner">
            <div>
                <div class="queue-banner-label">In Forschung</div>
                <div class="queue-banner-name" x-text="queue.name + ' → Lv ' + queue.level_to"></div>
            </div>
            <div class="queue-banner-eta">
                ⏱ <span x-text="formatEta(queue.finishes_at)"></span>
            </div>
            <button @click="instantFinish()" style="padding:.3rem .8rem;border-radius:5px;border:1px solid rgba(167,139,250,.4);background:rgba(167,139,250,.1);color:#c4b5fd;font-size:.75rem;font-weight:700;cursor:pointer">
                💎 Sofort
            </button>
        </div>
    </template>

    <div class="main-layout">

        <!-- Left: research trees -->
        <div style="display:flex;flex-direction:column;overflow:hidden">

            <!-- Tab bar -->
            <div class="tab-bar">
                <button class="tab-btn" :class="{active: tab==='battle'}"     @click="tab='battle'">⚔ Kampf</button>
                <button class="tab-btn" :class="{active: tab==='production'}" @click="tab='production'">🌾 Produktion</button>
                <button class="tab-btn" :class="{active: tab==='advanced'}"   @click="tab='advanced'">🔮 Erweitert</button>
            </div>

            <!-- Nodes -->
            <div class="node-grid" style="overflow-y:auto;max-height:calc(100vh - 180px)">
                <template x-for="node in currentNodes" :key="node.code">
                    <?php /* Alpine template — uses PHP-injected node data via JS */ ?>
                    <div class="node-card"
                         :class="{
                            locked: !canUnlock(node),
                            maxed:  isMaxed(node),
                            active: isInQueue(node)
                         }">
                        <div class="node-header">
                            <div class="node-name" x-text="node.name"></div>
                            <div class="node-level-badge"
                                 :class="{ maxed: isMaxed(node), active: isInQueue(node) }"
                                 x-text="isMaxed(node) ? 'MAX' : (isInQueue(node) ? '→Lv'+queue?.level_to : 'Lv '+(research[node.code]||0)+'/'+node.max_level)">
                            </div>
                        </div>

                        <div class="node-effect"
                             :class="{'unlock-type': node.type==='unlock'}"
                             x-text="nodeEffect(node)">
                        </div>

                        <template x-if="!isMaxed(node) && canUnlock(node)">
                            <div>
                                <div class="node-costs" x-html="nodeCosts(node)"></div>
                                <div class="node-time" x-text="'⏱ ' + nodeTime(node)"></div>
                            </div>
                        </template>

                        <template x-if="!canUnlock(node)">
                            <div class="node-req" x-text="lockReason(node)"></div>
                        </template>

                        <template x-if="isMaxed(node)">
                            <button class="btn-research maxed-btn" disabled>✓ Abgeschlossen</button>
                        </template>
                        <template x-if="!isMaxed(node) && isInQueue(node)">
                            <button class="btn-research" disabled>⏳ In Forschung…</button>
                        </template>
                        <template x-if="!isMaxed(node) && !isInQueue(node) && canUnlock(node)">
                            <button class="btn-research"
                                    :disabled="!!queue || loading"
                                    @click="startResearch(node.code)">
                                Erforschen
                            </button>
                        </template>
                        <template x-if="!isMaxed(node) && !isInQueue(node) && !canUnlock(node)">
                            <button class="btn-research" disabled>🔒 Gesperrt</button>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        <!-- Right: Boost List sidebar -->
        <div class="sidebar" style="overflow-y:auto;max-height:calc(100vh - 180px)">

            <div>
                <div class="sidebar-section-title">Boost List</div>
                <?php
                $boostGroups = [
                    'Allgemein' => [
                        'Truppen HP'      => 'troops_hp',
                        'Truppen ATK'     => 'troops_atk',
                        'Truppen DEF'     => 'troops_def',
                        'Truppen SPD'     => 'troops_spd',
                    ],
                    'Infanterie' => [
                        'HP'   => 'infantry_hp',
                        'ATK'  => 'infantry_atk',
                        'DEF'  => 'infantry_def',
                        'SPD'  => 'infantry_spd',
                    ],
                    'Fernkämpfer' => [
                        'HP'   => 'ranged_hp',
                        'ATK'  => 'ranged_atk',
                        'DEF'  => 'ranged_def',
                        'SPD'  => 'ranged_spd',
                    ],
                    'Kavallerie' => [
                        'HP'   => 'cavalry_hp',
                        'ATK'  => 'cavalry_atk',
                        'DEF'  => 'cavalry_def',
                        'SPD'  => 'cavalry_spd',
                    ],
                    'Sonstiges' => [
                        'Marschgröße'  => 'march_size',
                        'Krankenhaus'  => 'hospital_capacity',
                        'Heilung SPD'  => 'healing_speed',
                        'Bau SPD'      => 'construction_speed',
                    ],
                ];
                foreach ($boostGroups as $groupName => $items):
                ?>
                <div style="margin-bottom:.75rem">
                    <div style="font-size:.6rem;color:var(--gold);font-weight:800;text-transform:uppercase;letter-spacing:.07em;margin-bottom:.3rem"><?= $groupName ?></div>
                    <?php foreach ($items as $label => $key):
                        $rawVal = $buffs[$key] ?? 0;
                        $isFlatStat = in_array($key, ['march_size', 'hospital_capacity'], true);
                        $display = $isFlatStat ? '+' . (int)$rawVal : '+' . round((float)$rawVal * 100, 1) . '%';
                        $isActive = $rawVal > 0;
                    ?>
                    <div class="boost-row">
                        <span class="boost-label"><?= $label ?></span>
                        <span class="boost-val <?= $isActive ? 'active' : 'zero' ?>" id="boost-<?= $key ?>"><?= $display ?></span>
                    </div>
                    <?php endforeach ?>
                </div>
                <?php endforeach ?>
            </div>

        </div>

    </div>

    <!-- Toast -->
    <template x-if="toast.msg">
        <div class="toast" :class="toast.type" x-text="toast.msg"></div>
    </template>

</div>

<script>
function researchApp() {
    return {
        tab: 'battle',
        loading: false,
        toast: { msg: '', type: 'ok' },
        tick: 0,

        // PHP-injected state
        research: <?= json_encode($researchLevels, JSON_THROW_ON_ERROR) ?>,
        academyLevel: <?= $academyLevel ?>,
        queue: <?= $queueRow ? json_encode([
            'code'        => $queueRow['research_code'],
            'name'        => ResearchData::get($queueRow['research_code'])['name'] ?? $queueRow['research_code'],
            'level_to'    => (int)$queueRow['level_to'],
            'finishes_at' => $queueRow['finishes_at'],
            'done'        => false,
        ], JSON_THROW_ON_ERROR) : 'null' ?>,

        trees: <?= json_encode($trees, JSON_THROW_ON_ERROR) ?>,

        get currentNodes() {
            return this.trees[this.tab] ?? [];
        },

        boot() {
            setInterval(() => this.tick++, 1000);
            // Auto-poll every 10s to pick up finished research
            setInterval(() => this.pollState(), 10000);
        },

        isMaxed(node) {
            return (this.research[node.code] ?? 0) >= node.max_level;
        },

        isInQueue(node) {
            return this.queue && !this.queue.done && this.queue.code === node.code;
        },

        nextLevel(node) {
            return (this.research[node.code] ?? 0) + 1;
        },

        levelEntry(node, lvl) {
            return (node.levels ?? []).find(e => e.level === lvl) ?? null;
        },

        canUnlock(node) {
            if (this.isMaxed(node)) return false;
            const nextLvl = this.nextLevel(node);
            const entry   = this.levelEntry(node, nextLvl);
            if (!entry) return false;
            for (const req of (entry.requirements ?? [])) {
                if (req.type === 'academy' && this.academyLevel < req.level) return false;
                if (req.type === 'research') {
                    const dep = req.code ? (this.research[req.code] ?? 0) : 0;
                    if (dep < (req.level ?? 1)) return false;
                }
            }
            return true;
        },

        lockReason(node) {
            const nextLvl = this.nextLevel(node);
            const entry   = this.levelEntry(node, nextLvl);
            if (!entry) return 'Maximales Level';
            for (const req of (entry.requirements ?? [])) {
                if (req.type === 'academy' && this.academyLevel < req.level)
                    return `Akademie Lv ${req.level} benötigt`;
                if (req.type === 'research') {
                    const dep = req.code ? (this.research[req.code] ?? 0) : 0;
                    if (dep < (req.level ?? 1))
                        return `Voraussetzung: ${req.code} Lv ${req.level ?? 1}`;
                }
            }
            return 'Gesperrt';
        },

        nodeEffect(node) {
            if (node.type === 'unlock') return '🔓 Schaltet ' + node.name.replace('Unlock ', '') + ' frei';
            const lvl   = this.nextLevel(node);
            const entry = this.levelEntry(node, lvl);
            if (!entry) return '✓ Max erreicht';
            const v = entry.ability_value;
            if (['march_size','hospital_capacity','march_limit','troops_storage'].includes(node.stat)) {
                return '+' + Number(v).toLocaleString('de') + (node.stat === 'march_size' ? ' Truppen' : '');
            }
            return '+' + (v * 100).toFixed(1) + '% ' + node.name;
        },

        nodeCosts(node) {
            const lvl   = this.nextLevel(node);
            const entry = this.levelEntry(node, lvl);
            if (!entry) return '';
            const r = entry.resources ?? {};
            const fmt = n => Number(n).toLocaleString('de');
            return `<span>🌾 <strong>${fmt(r.food??0)}</strong></span>` +
                   `<span>🪵 <strong>${fmt(r.lumber??0)}</strong></span>` +
                   `<span>🪨 <strong>${fmt(r.stone??0)}</strong></span>` +
                   `<span>🪙 <strong>${fmt(r.gold??0)}</strong></span>`;
        },

        nodeTime(node) {
            const lvl   = this.nextLevel(node);
            const entry = this.levelEntry(node, lvl);
            if (!entry) return '';
            return this.fmtSec(entry.time ?? 0);
        },

        fmtSec(s) {
            s = parseInt(s);
            if (s < 60)   return s + 's';
            if (s < 3600) return Math.floor(s/60) + 'm ' + (s%60) + 's';
            const h = Math.floor(s/3600), m = Math.floor((s%3600)/60);
            return h + 'h ' + (m ? m + 'm' : '');
        },

        formatEta(ts) {
            const _ = this.tick; // reactivity trigger
            if (!ts) return '';
            const diff = Math.max(0, Math.floor((new Date(ts.replace(' ','T')+'Z') - Date.now()) / 1000));
            if (diff === 0) { if (this.queue) this.queue.done = true; return 'Fertig!'; }
            return this.fmtSec(diff);
        },

        async startResearch(code) {
            if (this.loading || this.queue) return;
            this.loading = true;
            try {
                const r = await fetch('/api/research/start', {
                    method: 'POST',
                    headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({ code })
                });
                const j = await r.json();
                if (j.ok) {
                    this.queue    = j.data.queue;
                    this.showToast('Forschung gestartet!', 'ok');
                } else {
                    this.showToast(j.message ?? j.error, 'err');
                }
            } catch(e) {
                this.showToast('Fehler: ' + e.message, 'err');
            } finally {
                this.loading = false;
            }
        },

        async instantFinish() {
            if (!this.queue) return;
            const r = await fetch('/api/research/instant', { method: 'POST' });
            const j = await r.json();
            if (j.ok) {
                this.research[this.queue.code] = this.queue.level_to;
                this.queue = null;
                this.showToast('Forschung abgeschlossen!', 'ok');
                this.pollState();
            } else {
                this.showToast(j.message ?? j.error, 'err');
            }
        },

        async pollState() {
            try {
                const r = await fetch('/api/research/state');
                const j = await r.json();
                if (!j.ok) return;
                this.research     = j.data.research;
                this.queue        = j.data.queue ?? null;
                this.academyLevel = j.data.academy_level ?? this.academyLevel;
            } catch {}
        },

        showToast(msg, type) {
            this.toast = { msg, type };
            setTimeout(() => this.toast = { msg: '', type: 'ok' }, 3500);
        },
    };
}
</script>
</body>
</html>
