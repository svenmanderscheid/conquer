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
            height: 100%;
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
    </style>
</head>
<body x-data="mapApp()" x-init="boot()">
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

    </aside>
</div><!-- #app -->

<script src="/assets/js/map.js?v=<?= filemtime(ROOT_DIR . '/assets/js/map.js') ?>"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
    function mapApp() {
        return {
            zoom:      4,
            myCity:    null,
            tileInfo:  null,
            hoverTile: '',

            // Monster type lookup: floor(code / 100) → name
            MONSTER_TYPES: {
                202001: 'Orc',
                202002: 'Skeleton',
                202003: 'Golem',
                202004: 'Treasure Goblin',
                202005: 'Deathkar',
                202006: 'Green Dragon',
                202007: 'Red Dragon',
                202008: 'Gold Dragon',
                202009: 'Magdar',
            },

            monsterLabel(code) {
                const type  = Math.floor(code / 100);
                const level = code % 100;
                const name  = this.MONSTER_TYPES[type] ?? 'Unknown';
                return `${name} Lv ${level}`;
            },

            shrineColor(tier) {
                return { S: '#f59e0b', A: '#a78bfa', B: '#60a5fa', C: '#94a3b8' }[tier] ?? '#94a3b8';
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
                    onTileInfo: (info) => { this.tileInfo = info; },
                    onHover:    (x, y) => { this.hoverTile = x !== null ? `${x}, ${y}` : ''; },
                });
                this.zoom = ConquerMap.currentZoom();
            },

            doZoomIn()   { ConquerMap.zoomIn();     this.zoom = ConquerMap.currentZoom(); },
            doZoomOut()  { ConquerMap.zoomOut();    this.zoom = ConquerMap.currentZoom(); },
            jumpToCity() { ConquerMap.jumpToCity(); },
        };
    }
</script>
</body>
</html>
