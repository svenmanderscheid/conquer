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
        .tile-info-row { margin-bottom: 4px; }
        .tile-info-row strong { color: #94a3b8; }
        .tile-info-empty { color: #334155; font-style: italic; }
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
                <template x-if="!tileInfo">
                    <div class="tile-info-empty">Click a tile to inspect it</div>
                </template>
                <template x-if="tileInfo">
                    <div>
                        <div class="tile-info-row">
                            Coords: <strong x-text="tileInfo.x + ', ' + tileInfo.y"></strong>
                        </div>
                        <template x-if="!tileInfo.occupant">
                            <div class="tile-info-row" style="color:#334155;font-style:italic">Empty</div>
                        </template>
                        <template x-if="tileInfo.occupant?.type === 'city'">
                            <div>
                                <div class="tile-info-row">
                                    Type: <strong style="color:#f59e0b">City</strong>
                                </div>
                                <div class="tile-info-row">
                                    Name: <strong x-text="tileInfo.occupant.name"></strong>
                                </div>
                                <div class="tile-info-row">
                                    Player: <strong x-text="tileInfo.occupant.player"></strong>
                                </div>
                                <div class="tile-info-row">
                                    Castle: <strong x-text="'Lv ' + tileInfo.occupant.level"></strong>
                                </div>
                                <div class="tile-info-row">
                                    Power: <strong x-text="(tileInfo.occupant.power ?? 0).toLocaleString()"></strong>
                                </div>
                            </div>
                        </template>
                        <template x-if="tileInfo.occupant?.type === 'monster'">
                            <div>
                                <div class="tile-info-row">
                                    Type: <strong style="color:#ef4444">Monster</strong>
                                </div>
                                <div class="tile-info-row">
                                    Name: <strong x-text="monsterLabel(tileInfo.occupant.monster_code)"></strong>
                                </div>
                                <div class="tile-info-row">
                                    HP: <strong x-text="tileInfo.occupant.hp_current.toLocaleString()"></strong>
                                </div>
                            </div>
                        </template>
                        <template x-if="tileInfo.occupant?.type === 'resource'">
                            <div>
                                <div class="tile-info-row">
                                    Type: <strong style="color:#22c55e">Resource Node</strong>
                                </div>
                                <div class="tile-info-row">
                                    Code: <strong x-text="tileInfo.occupant.object_code"></strong>
                                </div>
                                <div class="tile-info-row">
                                    Remaining: <strong x-text="tileInfo.occupant.remaining"></strong>
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
