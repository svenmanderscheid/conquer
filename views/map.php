<?php
declare(strict_types=1);
/**
 * Map View — Sprint 2
 *
 * Layout: Left = large detail map canvas | Right = minimap + info panel.
 * index.php guarantees the player is logged in before including this file.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Conquer — World Map</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html, body {
            width: 100%; height: 100%;
            overflow: hidden;
            background: #060e1c;
            color: #e2e8f0;
            font-family: system-ui, -apple-system, sans-serif;
            -webkit-user-select: none; user-select: none;
            display: flex;
            justify-content: center;
            align-items: stretch;
        }

        /* ── Root layout ── */
        #app {
            display: flex;
            width: 100%;
            max-width: 1280px;
            height: calc(100% - 72px);
            margin-top: 72px;
        }

        /* ── Left: detail map ── */
        #map-wrap {
            position: relative;
            flex: 1 1 auto;
            min-width: 0;
            height: 100%;
        }

        #map-canvas {
            display: block;
            width: 100%; height: 100%;
            cursor: grab;
            image-rendering: pixelated;
            image-rendering: crisp-edges;
        }

        /* Coord display at bottom of main canvas */
        #coord-bar {
            position: absolute;
            bottom: 10px; left: 50%; transform: translateX(-50%);
            background: rgba(15,23,42,0.85);
            border: 1px solid #334155; border-radius: 6px;
            padding: 3px 12px;
            font-size: 0.75rem; color: #64748b; white-space: nowrap;
            pointer-events: none;
        }
        #coord-bar strong { color: #94a3b8; }

        /* Nav bar top-left of map */
        #navbar {
            position: absolute; top: 10px; left: 10px;
            display: flex; gap: 8px;
            pointer-events: auto; z-index: 5;
        }
        .nav-btn {
            padding: 5px 13px;
            background: rgba(15,23,42,0.88); border: 1px solid #334155; border-radius: 6px;
            color: #94a3b8; font-size: 0.8rem; line-height: 1.4;
            cursor: pointer; text-decoration: none;
            transition: background 0.15s, color 0.15s;
        }
        .nav-btn:hover  { background: #1e293b; color: #e2e8f0; }
        .nav-btn.active { border-color: #0ea5e9; color: #0ea5e9; background: rgba(14,165,233,0.08); }

        /* ── Right: sidebar ── */
        #sidebar {
            flex: 0 0 270px;
            height: 100%;
            background: #0c1628;
            border-left: 1px solid #1e293b;
            display: flex; flex-direction: column;
            overflow-y: auto;
        }

        .panel {
            padding: 12px 14px;
            border-bottom: 1px solid #1e293b;
        }
        .panel-title {
            font-size: 0.7rem; font-weight: 700; letter-spacing: 0.07em;
            color: #475569; text-transform: uppercase; margin-bottom: 10px;
        }

        /* Minimap canvas */
        #minimap-wrap {
            position: relative;
            width: 100%;
            aspect-ratio: 1 / 1;
        }
        #minimap-canvas {
            display: block;
            width: 100%; height: 100%;
            cursor: crosshair;
            image-rendering: pixelated;
            background: #2b7fb8; /* ocean base visible before terrain renders */
        }

        /* Zoom controls */
        .zoom-row {
            display: flex; align-items: center; gap: 8px;
        }
        .ctrl-btn {
            width: 32px; height: 32px;
            background: #1e293b; border: 1px solid #334155; border-radius: 5px;
            color: #e2e8f0; font-size: 1.1rem; line-height: 1;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: background 0.15s;
            flex-shrink: 0;
        }
        .ctrl-btn:hover { background: #273548; }
        .zoom-label { font-size: 0.8rem; color: #64748b; flex: 1; }
        .zoom-label strong { color: #94a3b8; }

        /* Jump to city btn */
        .jump-btn {
            display: block; width: 100%; padding: 7px 0;
            background: #1e293b; border: 1px solid #334155; border-radius: 6px;
            color: #94a3b8; font-size: 0.82rem;
            cursor: pointer; text-align: center;
            transition: background 0.15s, color 0.15s;
            margin-top: 10px;
        }
        .jump-btn:hover { background: #273548; color: #e2e8f0; }

        /* Legend */
        .legend-grid {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: 5px 12px;
        }
        .leg-row { display: flex; align-items: center; gap: 6px; font-size: 0.72rem; color: #64748b; }
        .leg-swatch { width: 10px; height: 10px; border-radius: 2px; flex-shrink: 0; }

        /* Tile info */
        #tile-info {
            font-size: 0.78rem; color: #64748b; min-height: 60px;
        }
        .tile-info-row { margin-bottom: 5px; display: flex; justify-content: space-between; align-items: center; }
        .tile-info-row .lbl { color: #475569; }
        .tile-info-row .val { color: #94a3b8; font-weight: 600; text-align: right; }
        .tile-info-empty { color: #334155; font-style: italic; }
        .tile-info-name {
            font-size: 0.9rem; font-weight: 700; color: #e2e8f0;
            margin-bottom: 8px; padding-bottom: 6px;
            border-bottom: 1px solid #1e293b;
        }
        /* HP bar */
        .hp-bar-wrap {
            margin-bottom: 8px;
        }
        .hp-bar-label {
            display: flex; justify-content: space-between;
            font-size: 0.7rem; color: #64748b; margin-bottom: 3px;
        }
        .hp-bar-track {
            height: 6px; background: #1e293b; border-radius: 3px; overflow: hidden;
        }
        .hp-bar-fill {
            height: 100%; border-radius: 3px;
            transition: width 0.3s ease;
        }
        /* Stats mini-grid */
        .stats-grid {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: 4px 8px; margin-top: 6px;
        }
        .stat-cell {
            background: #0f172a; border: 1px solid #1e293b; border-radius: 4px;
            padding: 4px 6px; text-align: center;
        }
        .stat-cell .stat-val { font-size: 0.85rem; font-weight: 700; color: #e2e8f0; }
        .stat-cell .stat-lbl { font-size: 0.62rem; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; }

        /* ── Attack button ── */
        .btn-attack {
            display: block; width: 100%; margin-top: 10px;
            padding: 0.45rem; border-radius: 6px; border: none;
            background: #ef4444; color: #fff; font-size: 0.82rem; font-weight: 700;
            cursor: pointer;
        }
        .btn-attack:hover { opacity: 0.85; }

        /* ── Attack modal ── */
        #attack-modal {
            position: fixed; inset: 0; z-index: 200;
            background: rgba(0,0,0,0.7);
            display: flex; align-items: center; justify-content: center;
        }
        .modal-box {
            background: #1e293b; border: 1px solid #334155;
            border-radius: 12px; padding: 1.5rem;
            width: 360px; max-width: 95vw; max-height: 90vh; overflow-y: auto;
        }
        .modal-title { font-size: 1rem; font-weight: 700; margin-bottom: 1rem; color: #ef4444; }
        .modal-sub   { font-size: 0.7rem; color: #64748b; text-transform: uppercase;
                       letter-spacing: 0.08em; margin: 0.75rem 0 0.4rem; }
        .troop-pick  {
            background: #0f172a; border: 1px solid #334155;
            border-radius: 6px; padding: 0.5rem 0.6rem; margin-bottom: 0.4rem;
        }
        .troop-pick-header {
            display: flex; justify-content: space-between; align-items: baseline;
            margin-bottom: 0.35rem;
        }
        .troop-pick-name  { font-size: 0.82rem; font-weight: 600; }
        .troop-pick-avail { font-size: 0.72rem; color: #64748b; }
        .troop-pick-controls {
            display: flex; align-items: center; gap: 0.4rem;
        }
        .troop-pick-slider {
            flex: 1; accent-color: #ef4444; cursor: pointer; height: 4px;
        }
        .troop-pick-slider:disabled { opacity: 0.3; cursor: not-allowed; }
        .troop-pick-input {
            width: 68px; padding: 0.2rem 0.35rem; border-radius: 4px;
            border: 1px solid #334155; background: #1e293b;
            color: #e2e8f0; font-size: 0.8rem; text-align: right;
        }
        .troop-pick-max {
            padding: 0.2rem 0.45rem; border-radius: 4px; font-size: 0.72rem;
            background: #1e293b; border: 1px solid #475569;
            color: #94a3b8; cursor: pointer; white-space: nowrap;
        }
        .troop-pick-max:hover { border-color: #ef4444; color: #ef4444; }
        .modal-actions { display: flex; gap: 0.5rem; margin-top: 1rem; }
        .modal-btn {
            flex: 1; padding: 0.55rem; border-radius: 6px; border: none;
            font-size: 0.85rem; font-weight: 700; cursor: pointer;
        }
        .modal-btn-cancel  { background: #334155; color: #94a3b8; }
        .modal-btn-confirm { background: #ef4444; color: #fff; }
        .modal-btn-confirm:disabled { background: #7f1d1d; color: #fca5a5; cursor: not-allowed; }

        /* ── Toast ── */
        #map-toast {
            position: fixed; bottom: 1.2rem; left: 50%; transform: translateX(-50%);
            background: #1e293b; border: 1px solid #334155; border-radius: 8px;
            padding: 0.5rem 1.1rem; font-size: 0.82rem; z-index: 300;
            white-space: nowrap; display: none;
        }
        #map-toast.ok  { border-color: #22c55e; color: #22c55e; }
        #map-toast.err { border-color: #ef4444; color: #ef4444; }

        /* ── Active march badge ── */
        .march-badge {
            display: flex; justify-content: space-between; align-items: center;
            border-radius: 6px; padding: 0.35rem 0.55rem; margin-bottom: 0.4rem;
            font-size: 0.72rem;
        }
        .march-badge.monster  { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.35); }
        .march-badge.pvp      { background: rgba(127,29,29,0.2); border: 1px solid rgba(153,27,27,0.5);  }
        .march-badge.return   { background: rgba(226,232,240,0.07); border: 1px solid rgba(226,232,240,0.25); }
        .march-badge-label    { color: #e2e8f0; font-weight: 600; }
        .march-badge-state    { color: #ef4444; font-variant-numeric: tabular-nums; }
        .march-badge.return .march-badge-state { color: #e2e8f0; }
        .march-badge.pvp    .march-badge-state { color: #fca5a5; }
    </style>
</head>
<body x-data="mapApp()" x-init="boot()">
<?php require __DIR__ . '/partials/nav.php'; ?>
<div id="app">

    <!-- ── Left: Detail Map ── -->
    <div id="map-wrap">
        <canvas id="map-canvas"></canvas>

        <nav id="navbar">
            <a href="/city" class="nav-btn">City</a>
            <span class="nav-btn active">Map</span>
        </nav>

        <div id="coord-bar">
            Zoom <strong x-text="zoom + '×'"></strong>
            <span x-show="hoverTile"> &nbsp;·&nbsp; (<strong x-text="hoverTile"></strong>)</span>
        </div>
    </div>

    <!-- ── Right: Sidebar ── -->
    <aside id="sidebar">

        <!-- Minimap -->
        <div class="panel" style="padding: 10px 10px 8px">
            <div class="panel-title">World Overview</div>
            <div id="minimap-wrap">
                <canvas id="minimap-canvas"></canvas>
            </div>
        </div>

        <!-- Controls -->
        <div class="panel">
            <div class="panel-title">View</div>
            <div class="zoom-row">
                <button class="ctrl-btn" title="Zoom out" @click="doZoomOut()">−</button>
                <span class="zoom-label">Zoom <strong x-text="zoom + '×'"></strong></span>
                <button class="ctrl-btn" title="Zoom in"  @click="doZoomIn()">+</button>
            </div>
            <button class="jump-btn" @click="jumpToCity()">⌖ Jump to my city</button>
        </div>

        <!-- Selected Tile Info -->
        <div class="panel">
            <div class="panel-title">Tile Info</div>
            <div id="tile-info">

                <!-- No selection yet -->
                <template x-if="!tileInfo">
                    <div class="tile-info-empty">Klicke auf ein Tile um Details zu sehen</div>
                </template>

                <template x-if="tileInfo">
                    <div>
                        <!-- Coordinates always shown -->
                        <div class="tile-info-row" style="margin-bottom:8px">
                            <span class="lbl">Koordinaten</span>
                            <span class="val" x-text="tileInfo.x + ', ' + tileInfo.y"></span>
                        </div>

                        <!-- Empty tile -->
                        <template x-if="!tileInfo.occupant">
                            <div class="tile-info-empty">Leeres Tile</div>
                        </template>

                        <!-- City -->
                        <template x-if="tileInfo.occupant?.type === 'city'">
                            <div>
                                <div class="tile-info-name" style="color:#f59e0b">
                                    🏰 <span x-text="tileInfo.occupant.name"></span>
                                </div>
                                <div class="tile-info-row">
                                    <span class="lbl">Spieler</span>
                                    <span class="val" x-text="tileInfo.occupant.player"></span>
                                </div>
                                <div class="tile-info-row">
                                    <span class="lbl">Castle</span>
                                    <span class="val" x-text="'Lv ' + tileInfo.occupant.level"></span>
                                </div>
                                <div class="tile-info-row">
                                    <span class="lbl">Power</span>
                                    <span class="val" x-text="(tileInfo.occupant.power ?? 0).toLocaleString()"></span>
                                </div>
                            </div>
                        </template>

                        <!-- Monster -->
                        <template x-if="tileInfo.occupant?.type === 'monster'">
                            <div>
                                <div class="tile-info-name" style="color:#ef4444">
                                    ☠ <span x-text="tileInfo.occupant.name + ' Lv ' + tileInfo.occupant.level"></span>
                                </div>

                                <!-- HP bar -->
                                <div class="hp-bar-wrap">
                                    <div class="hp-bar-label">
                                        <span>HP</span>
                                        <span x-text="tileInfo.occupant.hp_current.toLocaleString() + ' / ' + tileInfo.occupant.hp_max.toLocaleString()"></span>
                                    </div>
                                    <div class="hp-bar-track">
                                        <div class="hp-bar-fill" style="background:#ef4444"
                                             :style="'width:' + Math.round(tileInfo.occupant.hp_current / tileInfo.occupant.hp_max * 100) + '%'">
                                        </div>
                                    </div>
                                </div>

                                <!-- Attack / Defense / Unit count -->
                                <div class="stats-grid">
                                    <div class="stat-cell">
                                        <div class="stat-val" x-text="tileInfo.occupant.attack ?? '—'"></div>
                                        <div class="stat-lbl">Angriff</div>
                                    </div>
                                    <div class="stat-cell">
                                        <div class="stat-val" x-text="tileInfo.occupant.defense ?? '—'"></div>
                                        <div class="stat-lbl">Verteidigung</div>
                                    </div>
                                    <template x-if="tileInfo.occupant.amount">
                                        <div class="stat-cell" style="grid-column: span 2">
                                            <div class="stat-val" x-text="tileInfo.occupant.amount.toLocaleString()"></div>
                                            <div class="stat-lbl">Einheiten</div>
                                        </div>
                                    </template>
                                </div>

                                <!-- Drop table -->
                                <template x-if="tileInfo.occupant.drops && tileInfo.occupant.drops.length">
                                    <div style="margin-top:8px">
                                        <div style="font-size:0.65rem;color:#475569;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:4px">Drops</div>
                                        <template x-for="drop in tileInfo.occupant.drops" :key="drop">
                                            <div style="font-size:0.72rem;color:#64748b;padding:2px 0;border-bottom:1px solid #0f172a" x-text="drop"></div>
                                        </template>
                                    </div>
                                </template>

                                <!-- Attack button -->
                                <button class="btn-attack" @click="openAttackModal()">⚔ Angriff starten</button>
                            </div>
                        </template>

                        <!-- Resource node -->
                        <template x-if="tileInfo.occupant?.type === 'resource'">
                            <div>
                                <div class="tile-info-name" style="color:#22c55e">
                                    ◆ <span x-text="tileInfo.occupant.label"></span>
                                </div>
                                <div class="tile-info-row">
                                    <span class="lbl">Verbleibend</span>
                                    <span class="val" x-text="(tileInfo.occupant.remaining ?? 0).toLocaleString()"></span>
                                </div>
                            </div>
                        </template>

                        <!-- Shrine -->
                        <template x-if="tileInfo.occupant?.type === 'shrine'">
                            <div>
                                <div class="tile-info-name" :style="'color:' + shrineColor(tileInfo.occupant.tier)">
                                    ⬠ Shrine <span x-text="tileInfo.occupant.shrine_code"></span>
                                </div>
                                <div class="tile-info-row">
                                    <span class="lbl">Tier</span>
                                    <span class="val" :style="'color:' + shrineColor(tileInfo.occupant.tier)" x-text="tileInfo.occupant.tier"></span>
                                </div>
                                <div class="tile-info-row">
                                    <span class="lbl">Besitzer</span>
                                    <span class="val" x-text="tileInfo.occupant.owner_alliance_id ? 'Allianz #' + tileInfo.occupant.owner_alliance_id : 'Frei'"></span>
                                </div>
                            </div>
                        </template>

                        <!-- Charm -->
                        <template x-if="tileInfo.occupant?.type === 'charm'">
                            <div>
                                <div class="tile-info-name" :style="'color:' + charmGradeColor(tileInfo.occupant.grade)">
                                    ✨ <span x-text="charmGradeLabel(tileInfo.occupant.grade) + ' Charm'"></span>
                                </div>
                                <div class="tile-info-row">
                                    <span class="lbl">Kategorie</span>
                                    <span class="val" x-text="charmCatLabel(tileInfo.occupant.stat_category)"></span>
                                </div>
                                <div class="tile-info-row">
                                    <span class="lbl">Bonus</span>
                                    <span class="val" :style="'color:' + charmGradeColor(tileInfo.occupant.grade)"
                                          x-text="'+' + tileInfo.occupant.bonus_pct + '%'"></span>
                                </div>
                                <div class="tile-info-row">
                                    <span class="lbl">Verfällt in</span>
                                    <span class="val" x-text="charmTimeLeft(tileInfo.occupant.expires_at, tick)"></span>
                                </div>

                                <!-- Troop selection for collect -->
                                <div style="margin-top:8px">
                                    <div style="font-size:.7rem;color:#64748b;margin-bottom:4px">Truppen auswählen:</div>
                                    <template x-if="charmTroopsLoading">
                                        <div style="font-size:.7rem;color:#475569;padding:4px 0">Laden…</div>
                                    </template>
                                    <template x-if="!charmTroopsLoading && charmTroops.length === 0">
                                        <div style="font-size:.7rem;color:#ef4444">Keine Truppen verfügbar</div>
                                    </template>
                                    <template x-for="t in charmTroops" :key="t.code">
                                        <label style="display:flex;align-items:center;gap:6px;font-size:.72rem;color:#cbd5e1;margin-bottom:3px">
                                            <input type="number" :id="'charm-t-' + t.code"
                                                   min="0" :max="t.count"
                                                   x-model.number="t.toSend"
                                                   style="width:52px;padding:2px 4px;border-radius:3px;border:1px solid #334155;background:#1e293b;color:#e2e8f0">
                                            <span x-text="t.name + ' (' + t.count + ')'"></span>
                                        </label>
                                    </template>
                                </div>

                                <button
                                    style="margin-top:8px;width:100%;padding:6px;border:none;border-radius:5px;
                                           background:linear-gradient(180deg,#a855f7,#7e22ce);color:#fff;
                                           font-weight:700;font-size:.8rem;cursor:pointer"
                                    :disabled="charmCollecting || charmTroops.reduce((s,t)=>s+(t.toSend||0),0)===0"
                                    @click="collectCharm(tileInfo.occupant.id, tileInfo.x, tileInfo.y)"
                                    x-text="charmCollecting ? '…' : '✨ Einsammeln'">
                                </button>
                            </div>
                        </template>

                    </div>
                </template>
            </div>
        </div>

        <!-- Legend -->
        <div class="panel">
            <div class="panel-title">Legend — Terrain</div>
            <div class="legend-grid">
                <div class="leg-row"><div class="leg-swatch" style="background:#1a5f8a"></div>Water</div>
                <div class="leg-row"><div class="leg-swatch" style="background:#c8a95e"></div>Desert</div>
                <div class="leg-row"><div class="leg-swatch" style="background:#7ec850"></div>Plains</div>
                <div class="leg-row"><div class="leg-swatch" style="background:#2d6a2d"></div>Forest</div>
                <div class="leg-row"><div class="leg-swatch" style="background:#7a6248"></div>Mountains</div>
                <div class="leg-row"><div class="leg-swatch" style="background:#d0e4ef"></div>Snow</div>
            </div>
            <div class="panel-title" style="margin-top:10px">Legend — Objects</div>
            <div class="legend-grid">
                <div class="leg-row">
                    <div class="leg-swatch" style="background:#f59e0b"></div>City
                </div>
                <div class="leg-row">
                    <div class="leg-swatch" style="background:#ef4444;border-radius:50%"></div>Monster
                </div>
                <div class="leg-row">
                    <div class="leg-swatch" style="background:#22c55e;transform:rotate(45deg)"></div>Resource
                </div>
            </div>
        </div>

        <!-- Active Marches -->
        <div class="panel" x-show="marches.length > 0">
            <div class="panel-title">Aktive Märsche</div>
            <template x-for="m in marches" :key="m.id">
                <div class="march-badge"
                     :class="m.state === 'returning' ? 'return' : (m.march_type == 5 || m.march_type == 6 ? 'monster' : 'pvp')"
                     style="cursor:pointer"
                     @click="jumpToMarch(m)"
                     title="Auf Karte springen">
                    <span class="march-badge-label">
                        <template x-if="m.state === 'marching' && m.march_type == 5">
                            <span>⚔ Monster (<span x-text="m.target_x + ',' + m.target_y"></span>)</span>
                        </template>
                        <template x-if="m.state === 'marching' && m.march_type == 6">
                            <span>✨ Charm (<span x-text="m.target_x + ',' + m.target_y"></span>)</span>
                        </template>
                        <template x-if="m.state === 'marching' && m.march_type != 5 && m.march_type != 6">
                            <span>⚔ Dorf (<span x-text="m.target_x + ',' + m.target_y"></span>)</span>
                        </template>
                        <template x-if="m.state === 'returning'">
                            <span>↩ Rückkehr</span>
                        </template>
                    </span>
                    <span class="march-badge-state"
                          x-text="marchEta(m, tick)"></span>
                </div>
            </template>
        </div>

    </aside>
</div><!-- #app -->

<!-- Attack modal -->
<div id="attack-modal" x-show="attackModal" @click.self="attackModal = false" style="display:none">
    <div class="modal-box">
        <div class="modal-title">⚔ Monster angreifen</div>

        <div style="font-size:0.82rem;color:#94a3b8;margin-bottom:0.75rem"
             x-text="attackTarget ? attackTarget.name + ' bei (' + attackTarget.x + ', ' + attackTarget.y + ')' : ''"></div>

        <template x-if="attackLoading">
            <div style="text-align:center;color:#475569;padding:1rem">Truppen laden…</div>
        </template>

        <template x-if="!attackLoading">
            <div>
                <div class="modal-sub">Truppen auswählen</div>

                <template x-for="t in attackTroops" :key="t.code">
                    <div class="troop-pick">
                        <div class="troop-pick-header">
                            <span class="troop-pick-name" x-text="t.name"></span>
                            <span class="troop-pick-avail"
                                  x-text="(t.toSend || 0).toLocaleString() + ' / ' + t.available.toLocaleString()"></span>
                        </div>
                        <div class="troop-pick-controls">
                            <input type="range" class="troop-pick-slider"
                                   min="0" :max="t.available" step="1"
                                   x-model.number="t.toSend"
                                   :disabled="t.available === 0">
                            <input type="number" class="troop-pick-input"
                                   min="0" :max="t.available"
                                   x-model.number="t.toSend"
                                   :disabled="t.available === 0">
                            <button class="troop-pick-max"
                                    :disabled="t.available === 0"
                                    @click="t.toSend = t.available">Max</button>
                        </div>
                    </div>
                </template>

                <div style="font-size:0.72rem;color:#64748b;margin-top:0.5rem"
                     x-text="'Gesamt: ' + attackTroops.reduce((s,t) => s + (t.toSend||0), 0).toLocaleString() + ' Truppen'">
                </div>
            </div>
        </template>

        <div class="modal-actions">
            <button class="modal-btn modal-btn-cancel" @click="attackModal = false">Abbrechen</button>
            <button class="modal-btn modal-btn-confirm"
                    :disabled="attackLoading || attackTroops.reduce((s,t)=>s+(t.toSend||0),0) === 0"
                    @click="sendMarch()">
                Marschieren
            </button>
        </div>
    </div>
</div>

<div id="map-toast"></div>

<script src="/assets/js/map.js?v=<?= filemtime(ROOT_DIR . '/assets/js/map.js') ?>"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
const CSRF = <?= json_encode($session['csrf_token']) ?>;

function mapApp() {
    return {
        zoom:         4,
        myCity:       null,
        tileInfo:     null,
        hoverTile:    '',
        marches:      [],
        tick:         0,

        // Attack modal state
        attackModal:   false,
        attackTarget:  null,
        attackTroops:  [],
        attackLoading: false,

        // Charm collect state
        charmTroops:        [],
        charmTroopsLoading: false,
        charmCollecting:    false,

        MONSTER_TYPES: {
            202001: 'Orc',        202002: 'Skeleton',   202003: 'Golem',
            202004: 'Treasure Goblin', 202005: 'Deathkar',
            202006: 'Green Dragon',    202007: 'Red Dragon',
            202008: 'Gold Dragon',     202009: 'Magdar',
        },

        monsterLabel(code) {
            const type  = Math.floor(code / 100);
            const level = code % 100;
            return (this.MONSTER_TYPES[type] ?? 'Unknown') + ' Lv ' + level;
        },

        shrineColor(tier) {
            return { S: '#f59e0b', A: '#a78bfa', B: '#60a5fa', C: '#94a3b8' }[tier] ?? '#94a3b8';
        },

        // Returns "Xm Ys" remaining time; tick parameter forces Alpine reactivity each second
        marchEta(m, _tick) {
            const target = m.state === 'marching'
                ? m.arrival_time
                : m.return_time;
            if (!target) return '';
            const secsLeft = Math.max(0, Math.round(
                (new Date(target.replace(' ', 'T') + 'Z').getTime() - Date.now()) / 1000
            ));
            if (secsLeft === 0) return 'Ankunft...';
            const m_ = Math.floor(secsLeft / 60);
            const s  = secsLeft % 60;
            return m_ > 0 ? `${m_}m ${s}s` : `${s}s`;
        },

        async boot() {
            const r = await fetch('/api/map/info');
            const j = await r.json();
            if (!j.ok) { window.location.href = '/'; return; }

            this.myCity = j.data.my_city;

            ConquerMap.init({
                canvas:     document.getElementById('map-canvas'),
                minimap:    document.getElementById('minimap-canvas'),
                seed:       j.data.map_seed,
                mapSize:    j.data.map_size,
                cityCoords: j.data.my_city,
                onTileInfo: (info) => {
                    this.tileInfo = info;
                    // Load troop list when a charm tile is selected
                    if (info?.occupant?.type === 'charm') {
                        this.loadCharmTroops();
                    } else {
                        this.charmTroops = [];
                    }
                },
                onHover:    (x, y) => { this.hoverTile = x !== null ? `${x}, ${y}` : ''; },
            });
            this.zoom = ConquerMap.currentZoom();
            this.pollMarches();
            setInterval(() => this.tick++, 1000);
        },

        doZoomIn()   { ConquerMap.zoomIn();     this.zoom = ConquerMap.currentZoom(); },
        doZoomOut()  { ConquerMap.zoomOut();    this.zoom = ConquerMap.currentZoom(); },
        jumpToCity() { ConquerMap.jumpToCity(); },

        jumpToMarch(m) {
            const tx = m.state === 'returning' ? (this.myCity?.x ?? m.target_x) : m.target_x;
            const ty = m.state === 'returning' ? (this.myCity?.y ?? m.target_y) : m.target_y;
            ConquerMap.jumpTo(tx, ty);
        },

        // ── Active march polling ──────────────────────────────────────────────
        async pollMarches() {
            try {
                const r = await fetch('/api/march/list');
                const j = await r.json();
                if (j.ok) {
                    const prev = this.marches;
                    this.marches = j.data.marches;
                    ConquerMap.setMarches(j.data.marches);

                    // If any march just resolved (count dropped or state changed)
                    // force entity re-fetch so killed monsters vanish immediately.
                    const prevCount = prev.length;
                    const nowCount  = j.data.marches.length;
                    if (nowCount < prevCount || j.data.marches.some((m, i) => m.state !== (prev[i]?.state))) {
                        ConquerMap.refreshEntities();
                    }
                }
            } catch {}
            setTimeout(() => this.pollMarches(), 5000);
        },

        // ── Attack modal ──────────────────────────────────────────────────────
        async openAttackModal() {
            if (!this.tileInfo?.occupant) return;
            const occ = this.tileInfo.occupant;

            this.attackTarget  = {
                x:    this.tileInfo.x,
                y:    this.tileInfo.y,
                name: occ.name + ' Lv ' + occ.level,
            };
            this.attackModal   = true;
            this.attackLoading = true;
            this.attackTroops  = [];

            try {
                const r = await fetch('/api/troops/list');
                const j = await r.json();
                if (j.ok) {
                    this.attackTroops = j.data.definitions
                        .filter(d => (j.data.troops[d.code] ?? 0) > 0)
                        .map(d => ({
                            code:      d.code,
                            name:      d.name,
                            tier:      d.tier,
                            type:      d.type,
                            available: j.data.troops[d.code] ?? 0,
                            toSend:    0,
                        }));
                }
            } catch {}

            this.attackLoading = false;
        },

        async sendMarch() {
            const troops = {};
            let total = 0;
            for (const t of this.attackTroops) {
                if (t.toSend > 0) { troops[t.code] = t.toSend; total += t.toSend; }
            }
            if (total === 0) return;

            this.attackLoading = true;
            try {
                const r = await fetch('/api/march/dispatch', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                    body:    JSON.stringify({ target_x: this.attackTarget.x, target_y: this.attackTarget.y, troops }),
                });
                const j = await r.json();

                if (j.ok) {
                    this.attackModal = false;
                    this.showToast('⚔ Marsch gestartet! (ID ' + j.data.march_id + ')', 'ok');
                    this.pollMarches();
                } else {
                    this.showToast(j.error?.message ?? j.error ?? 'Fehler', 'err');
                }
            } catch {
                this.showToast('Netzwerkfehler', 'err');
            }
            this.attackLoading = false;
        },

        // ── Charm helpers ─────────────────────────────────────────────────────
        charmGradeColor(grade) {
            return { normal: '#94a3b8', epic: '#a855f7', legendary: '#f59e0b' }[grade] ?? '#94a3b8';
        },
        charmGradeLabel(grade) {
            return { normal: 'Normal', epic: 'Epic', legendary: 'Legendary' }[grade] ?? grade;
        },
        charmCatLabel(cat) {
            return {
                construction: 'Bauzeitbonus', research: 'Forschungsbonus',
                troops_hp: 'Truppen HP', troops_attack: 'Truppenangriff',
                troops_defense: 'Truppenschutz', carry: 'Traglast',
                march_speed: 'Marschgeschwindigkeit', gathering: 'Sammelgeschwindigkeit',
            }[cat] ?? cat;
        },
        charmTimeLeft(expiresAt, _tick) {
            const timeLeft = Math.max(0, Math.round((new Date(expiresAt.replace(' ', 'T') + 'Z') - Date.now()) / 1000));
            const mins = Math.floor(timeLeft / 60);
            const secs = timeLeft % 60;
            return `${mins}m ${secs}s`;
        },

        async loadCharmTroops() {
            this.charmTroopsLoading = true;
            this.charmTroops = [];
            try {
                const r = await fetch('/api/troops/list');
                const j = await r.json();
                if (j.ok) {
                    this.charmTroops = j.data.definitions
                        .filter(d => (j.data.troops[d.code] ?? 0) > 0)
                        .map(d => ({
                            code:   d.code,
                            name:   d.name,
                            count:  j.data.troops[d.code] ?? 0,
                            toSend: 1,
                        }));
                }
            } catch {}
            this.charmTroopsLoading = false;
        },

        async collectCharm(charmId, tx, ty) {
            const troops = {};
            let total = 0;
            for (const t of this.charmTroops) {
                if ((t.toSend || 0) > 0) { troops[t.code] = t.toSend; total += t.toSend; }
            }
            if (total === 0) { alert('Mindestens 1 Truppe muss ausgewählt werden.'); return; }

            this.charmCollecting = true;
            try {
                const r = await fetch('/api/march/dispatch-charm', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                    body:    JSON.stringify({ charm_id: charmId, target_x: tx, target_y: ty, troops }),
                });
                const j = await r.json();
                if (j.ok) {
                    this.showToast('✨ Charm-Marsch gestartet! (ID ' + j.data.march_id + ')', 'ok');
                    ConquerMap.refreshEntities?.();
                    this.pollMarches();
                } else {
                    this.showToast(j.message ?? j.error ?? 'Fehler beim Einsammeln', 'err');
                }
            } catch {
                this.showToast('Netzwerkfehler', 'err');
            }
            this.charmCollecting = false;
        },

        // ── Toast helper ──────────────────────────────────────────────────────
        showToast(msg, type = 'ok') {
            const el = document.getElementById('map-toast');
            el.textContent  = msg;
            el.className    = type;
            el.style.display = 'block';
            setTimeout(() => { el.style.display = 'none'; }, 4000);
        },
    };
}
</script>
</body>
</html>
