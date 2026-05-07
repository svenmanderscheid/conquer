/**
 * Conquer — Map Renderer (Sprint 2)
 *
 * Two-canvas setup:
 *   #map-canvas   — left large detail view (pan + zoom)
 *   #minimap-canvas — right small overview (full world, click to jump)
 *
 * Public API:
 *   ConquerMap.init({ canvas, minimap, seed, cityCoords, onTileInfo, onHover })
 *   ConquerMap.jumpToCity()
 *   ConquerMap.zoomIn() / zoomOut()
 *   ConquerMap.currentZoom() → number
 */
const ConquerMap = (() => {
    'use strict';

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    const BASE_TILE    = 32;
    const ZOOM_LEVELS  = [1, 2, 4, 8];
    const FETCH_DELAY  = 200;   // ms debounce for entity API calls
    const DRAG_THRESH  = 6;     // px before a mousedown is treated as a drag

    let MAP_SIZE = 256; // overwritten by init() from the API response

    // Terrain type IDs
    const T = { WATER: 0, DESERT: 1, PLAINS: 2, FOREST: 3, MOUNTAINS: 4, SNOW: 5 };

    // Fallback solid colours (minimap + terrain types without a sprite)
    // Index order matches T: WATER, DESERT, PLAINS, FOREST, MOUNTAINS, (unused)
    const FILL   = ['#1e6896', '#c8a050', '#5cb83c', '#2a6a2a', '#7a7060', '#7a7060'];
    const BORDER = ['#155276', '#a07830', '#3a9820', '#1a4a1a', '#5a5040', '#5a5040'];

    // Pre-parsed RGB values for minimap ImageData painting
    const FILL_RGB = FILL.map(hex => ({
        r: parseInt(hex.slice(1, 3), 16),
        g: parseInt(hex.slice(3, 5), 16),
        b: parseInt(hex.slice(5, 7), 16),
    }));

    // -------------------------------------------------------------------------
    // Kenney Tiny Town atlas
    //   tilemap_packed.png — 12 cols × 11 rows, 16×16 tiles, 1px gap → 17px stride
    // -------------------------------------------------------------------------

    const ATLAS_SRC  = '/assets/sprites/kenney-tiny-town/Tilemap/tilemap_packed.png';
    const ATLAS_COLS = 12;
    const ATLAS_TILE = 16;
    const ATLAS_STEP = 17;   // 16px tile + 1px gap

    // Which atlas tile indices to use per terrain type (null = colour fill)
    const TERRAIN_TILES = {
        [T.WATER]:     null,
        [T.DESERT]:    [12, 13],
        [T.PLAINS]:    [0, 1, 2],
        [T.FOREST]:    [0, 1, 2],   // grass base; tree drawn on top
        [T.MOUNTAINS]: null,
        [T.SNOW]:      null,
    };
    const TREE_TILES = [4, 5, 6];   // pine, round green, dark green

    // -------------------------------------------------------------------------
    // Terrain — seeded value noise (no external libraries)
    // -------------------------------------------------------------------------

    function gridHash(seed, gx, gy) {
        let h = (seed ^ (Math.imul(gx, 374761393) + Math.imul(gy, 668265263))) >>> 0;
        h = Math.imul(h ^ (h >>> 13), 1274126177) >>> 0;
        return (h ^ (h >>> 16)) / 0xFFFFFFFF;
    }

    function valueNoise(seed, x, y, scale) {
        const gx = Math.floor(x / scale), gy = Math.floor(y / scale);
        const fx = x / scale - gx,         fy = y / scale - gy;
        const ux = fx * fx * (3 - 2 * fx), uy = fy * fy * (3 - 2 * fy);
        const v00 = gridHash(seed, gx,     gy);
        const v10 = gridHash(seed, gx + 1, gy);
        const v01 = gridHash(seed, gx,     gy + 1);
        const v11 = gridHash(seed, gx + 1, gy + 1);
        return v00*(1-ux)*(1-uy) + v10*ux*(1-uy) + v01*(1-ux)*uy + v11*ux*uy;
    }

    /**
     * Two-axis noise biome system.
     *
     * height   (large scale) → water / mountain boundaries
     * moisture (large scale) → desert / forest boundaries
     *
     * Target distribution: Plains 50%, Forest 25%, Mountain 15%, Water 5%, Desert 5%
     * Biomes form natural clusters because both noise fields use large base scales.
     *
     * Octave weights 0.55 / 0.30 / 0.15 give a range of roughly [0, 1] that clusters
     * around 0.5 (bell-shaped). Thresholds below are tuned to hit the % targets.
     */
    function terrain(seed, tx, ty) {
        const s = MAP_SIZE;

        // Height — controls elevation (water in valleys, mountains on peaks)
        const h = valueNoise(seed,      tx, ty, s * 0.38) * 0.55
                + valueNoise(seed + 1,  tx, ty, s * 0.16) * 0.30
                + valueNoise(seed + 2,  tx, ty, s * 0.06) * 0.15;

        // Moisture — independent axis (arid ↔ lush)
        const m = valueNoise(seed + 50, tx, ty, s * 0.28) * 0.55
                + valueNoise(seed + 51, tx, ty, s * 0.11) * 0.30
                + valueNoise(seed + 52, tx, ty, s * 0.04) * 0.15;

        if (h < 0.22)              return T.WATER;      // ~5%  deep water / lakes
        if (h > 0.75)              return T.MOUNTAINS;  // ~15% high elevation
        if (m < 0.20 && h < 0.62) return T.DESERT;     // ~5%  arid lowlands
        if (m > 0.65)              return T.FOREST;     // ~25% humid / lush areas
        return T.PLAINS;                                // ~50% default
    }

    // Alias so the rest of the code stays readable
    function getTerrain(seed, tx, ty) { return terrain(seed, tx, ty); }

    // -------------------------------------------------------------------------
    // State
    // -------------------------------------------------------------------------

    // Main canvas
    let canvas, ctx;
    // Minimap canvas
    let minimap, mmCtx, mmImgData;
    // Atlas
    let atlasImg = null, atlasReady = false;

    let seed, myCity;
    let onTileInfo, onHover;

    // Camera (world-pixel coords of top-left corner of canvas)
    let camX = 0, camY = 0, zoomIdx = 2; // default 4×

    // Drag state
    let dragging = false, dragSX = 0, dragSY = 0, dragCX = 0, dragCY = 0, didDrag = false;

    // Entity cache
    let entities = {}, fetchTimer = null, lastVP = '';

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    const tileSize = () => BASE_TILE * ZOOM_LEVELS[zoomIdx];

    function clampCamera() {
        const s = tileSize();
        camX = Math.max(0, Math.min(MAP_SIZE * s - canvas.width,  camX));
        camY = Math.max(0, Math.min(MAP_SIZE * s - canvas.height, camY));
    }

    function screenToTile(sx, sy) {
        const s = tileSize();
        return { x: Math.floor((camX + sx) / s), y: Math.floor((camY + sy) / s) };
    }

    // -------------------------------------------------------------------------
    // Init
    // -------------------------------------------------------------------------

    function init(opts) {
        canvas     = opts.canvas;
        minimap    = opts.minimap;
        seed       = opts.seed;
        MAP_SIZE   = opts.mapSize ?? 256;
        myCity     = opts.cityCoords;
        onTileInfo = opts.onTileInfo ?? (() => {});
        onHover    = opts.onHover   ?? (() => {});

        ctx   = canvas.getContext('2d');
        mmCtx = minimap.getContext('2d');

        loadAtlas();
        resizeMain();
        window.addEventListener('resize', resizeMain);

        if (myCity) {
            const s = tileSize();
            camX = myCity.x * s - canvas.width  / 2 + s / 2;
            camY = myCity.y * s - canvas.height / 2 + s / 2;
        }
        clampCamera();

        buildMinimapTerrain(); // one-time pre-render of minimap terrain
        bindEvents();
        fetchEntities();
        requestAnimationFrame(loop);
    }

    function loadAtlas() {
        atlasImg = new Image();
        atlasImg.onload = () => { atlasReady = true; };
        atlasImg.src = ATLAS_SRC;
    }

    function drawAtlasTile(tileIdx, destX, destY, destSize) {
        const col = tileIdx % ATLAS_COLS;
        const row = Math.floor(tileIdx / ATLAS_COLS);
        ctx.drawImage(
            atlasImg,
            col * ATLAS_STEP, row * ATLAS_STEP, ATLAS_TILE, ATLAS_TILE,
            Math.round(destX), Math.round(destY), destSize, destSize
        );
    }

    function resizeMain() {
        canvas.width  = canvas.offsetWidth;
        canvas.height = canvas.offsetHeight;
    }

    // -------------------------------------------------------------------------
    // Minimap terrain — pre-rendered once into an ImageData
    // -------------------------------------------------------------------------

    function buildMinimapTerrain() {
        const size   = minimap.offsetWidth  || 256;
        minimap.width  = size;
        minimap.height = size;

        const scale  = MAP_SIZE / size; // world tiles per minimap pixel
        const imgData = mmCtx.createImageData(size, size);
        const data    = imgData.data;

        for (let py = 0; py < size; py++) {
            for (let px = 0; px < size; px++) {
                const tx  = Math.floor(px * scale);
                const ty  = Math.floor(py * scale);
                const t   = getTerrain(seed, tx, ty);
                const rgb = FILL_RGB[t];
                const i   = (py * size + px) * 4;
                data[i]     = rgb.r;
                data[i + 1] = rgb.g;
                data[i + 2] = rgb.b;
                data[i + 3] = 255;
            }
        }
        mmImgData = imgData;
    }

    // -------------------------------------------------------------------------
    // Render loop
    // -------------------------------------------------------------------------

    function loop() {
        renderMain();
        renderMinimap();
        requestAnimationFrame(loop);
    }

    // ---- Detail map ----

    function renderMain() {
        const s  = tileSize();
        const x0 = Math.floor(camX / s);
        const y0 = Math.floor(camY / s);
        const x1 = Math.ceil((camX + canvas.width)  / s);
        const y1 = Math.ceil((camY + canvas.height) / s);

        ctx.clearRect(0, 0, canvas.width, canvas.height);

        // Terrain tiles
        ctx.imageSmoothingEnabled = false;
        for (let ty = y0; ty <= y1; ty++) {
            for (let tx = x0; tx <= x1; tx++) {
                if (tx < 0 || ty < 0 || tx >= MAP_SIZE || ty >= MAP_SIZE) continue;
                const t   = getTerrain(seed, tx, ty);
                const px  = tx * s - camX;
                const py  = ty * s - camY;
                const ids = TERRAIN_TILES[t];

                if (ids && atlasReady) {
                    // Pick a stable variant per tile using position hash
                    const v = ids[Math.floor(gridHash(seed + 77, tx, ty) * ids.length)];
                    drawAtlasTile(v, px, py, s);

                    // Forest: overlay a tree on ~60 % of tiles
                    if (t === T.FOREST && gridHash(seed + 78, tx, ty) > 0.40) {
                        const tree = TREE_TILES[Math.floor(gridHash(seed + 79, tx, ty) * TREE_TILES.length)];
                        drawAtlasTile(tree, px, py, s);
                    }
                } else {
                    // Colour fill for water / snow / mountains (no sprite in pack)
                    ctx.fillStyle = FILL[t];
                    ctx.fillRect(px, py, s, s);
                }
            }
        }

        // Entities
        for (const e of Object.values(entities)) {
            const px = e.x * s - camX;
            const py = e.y * s - camY;
            if (px < -s || py < -s || px > canvas.width + s || py > canvas.height + s) continue;
            drawEntity(e, px, py, s);
        }
    }

    function drawEntity(e, px, py, s) {
        const pad = Math.max(2, s * 0.1);
        ctx.save();

        if (e.type === 'city') {
            ctx.fillStyle   = '#f59e0b';
            ctx.strokeStyle = '#92400e';
            ctx.lineWidth   = 1;
            ctx.fillRect(  px + pad, py + pad, s - pad*2, s - pad*2);
            ctx.strokeRect(px + pad + 0.5, py + pad + 0.5, s - pad*2 - 1, s - pad*2 - 1);
            if (s >= 16) {
                ctx.fillStyle    = '#1c1917';
                ctx.font         = `bold ${Math.max(7, Math.floor(s * 0.38))}px monospace`;
                ctx.textAlign    = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(String(e.level), px + s / 2, py + s / 2);
            }
        } else if (e.type === 'monster') {
            ctx.beginPath();
            ctx.arc(px + s/2, py + s/2, s/2 - pad, 0, Math.PI * 2);
            ctx.fillStyle   = '#ef4444';
            ctx.strokeStyle = '#991b1b';
            ctx.lineWidth   = 1;
            ctx.fill();
            ctx.stroke();
        } else if (e.type === 'resource') {
            const cx = px + s/2, cy = py + s/2, r = s/2 - pad;
            ctx.beginPath();
            ctx.moveTo(cx, cy - r); ctx.lineTo(cx + r, cy);
            ctx.lineTo(cx, cy + r); ctx.lineTo(cx - r, cy);
            ctx.closePath();
            ctx.fillStyle   = '#22c55e';
            ctx.strokeStyle = '#15803d';
            ctx.lineWidth   = 1;
            ctx.fill();
            ctx.stroke();
        } else if (e.type === 'shrine') {
            const TIER_COLOR = { S: '#f59e0b', A: '#a78bfa', B: '#60a5fa', C: '#94a3b8' };
            const cx = px + s/2, cy = py + s/2, r = s/2 - pad;
            // Pentagon shape for shrines
            ctx.beginPath();
            for (let i = 0; i < 5; i++) {
                const a = (i * 2 * Math.PI / 5) - Math.PI / 2;
                i === 0 ? ctx.moveTo(cx + r * Math.cos(a), cy + r * Math.sin(a))
                        : ctx.lineTo(cx + r * Math.cos(a), cy + r * Math.sin(a));
            }
            ctx.closePath();
            ctx.fillStyle   = TIER_COLOR[e.tier] ?? '#94a3b8';
            ctx.strokeStyle = '#1e293b';
            ctx.lineWidth   = 1;
            ctx.fill();
            ctx.stroke();
            if (s >= 24) {
                ctx.fillStyle    = '#1e293b';
                ctx.font         = `bold ${Math.max(7, Math.floor(s * 0.32))}px monospace`;
                ctx.textAlign    = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(e.tier, cx, cy);
            }
        }

        ctx.restore();
    }

    // ---- Minimap ----

    function renderMinimap() {
        if (!mmImgData) return;
        const mmSize = minimap.width;
        const scale  = mmSize / MAP_SIZE; // minimap px per world tile

        // Draw cached terrain
        mmCtx.putImageData(mmImgData, 0, 0);

        // Draw entity dots (2px each)
        for (const e of Object.values(entities)) {
            const mx = Math.round(e.x * scale);
            const my = Math.round(e.y * scale);
            if      (e.type === 'city')     mmCtx.fillStyle = '#f59e0b';
            else if (e.type === 'monster')  mmCtx.fillStyle = '#ef4444';
            else if (e.type === 'resource') mmCtx.fillStyle = '#22c55e';
            else if (e.type === 'shrine')   mmCtx.fillStyle = '#a78bfa';
            else continue;
            mmCtx.fillRect(mx - 1, my - 1, 3, 3);
        }

        // My city marker
        if (myCity) {
            const mx = Math.round(myCity.x * scale);
            const my = Math.round(myCity.y * scale);
            mmCtx.fillStyle   = '#ffffff';
            mmCtx.fillRect(mx - 2, my - 2, 5, 5);
        }

        // Viewport rectangle
        const s   = tileSize();
        const vx0 = Math.max(0, camX / s);
        const vy0 = Math.max(0, camY / s);
        const vx1 = Math.min(MAP_SIZE, (camX + canvas.width)  / s);
        const vy1 = Math.min(MAP_SIZE, (camY + canvas.height) / s);

        const rx = Math.round(vx0 * scale);
        const ry = Math.round(vy0 * scale);
        const rw = Math.max(2, Math.round((vx1 - vx0) * scale));
        const rh = Math.max(2, Math.round((vy1 - vy0) * scale));

        mmCtx.strokeStyle = '#ffffff';
        mmCtx.lineWidth   = 1.5;
        mmCtx.strokeRect(rx + 0.5, ry + 0.5, rw - 1, rh - 1);
    }

    // -------------------------------------------------------------------------
    // Entity fetching
    // -------------------------------------------------------------------------

    function scheduleFetch() {
        clearTimeout(fetchTimer);
        fetchTimer = setTimeout(fetchEntities, FETCH_DELAY);
    }

    async function fetchEntities() {
        const s  = tileSize();
        const x1 = Math.max(0,           Math.floor(camX / s) - 2);
        const y1 = Math.max(0,           Math.floor(camY / s) - 2);
        const x2 = Math.min(MAP_SIZE - 1, Math.ceil((camX + canvas.width)  / s) + 2);
        const y2 = Math.min(MAP_SIZE - 1, Math.ceil((camY + canvas.height) / s) + 2);
        const vp = `${x1},${y1},${x2},${y2}`;
        if (vp === lastVP) return;
        lastVP = vp;

        try {
            const r = await fetch(`/api/map/tiles?x_min=${x1}&y_min=${y1}&x_max=${x2}&y_max=${y2}`);
            const j = await r.json();
            if (!j.ok) return;
            entities = {};
            for (const e of j.data.entities) entities[`${e.x},${e.y}`] = e;
        } catch { /* ignore network errors */ }
    }

    // -------------------------------------------------------------------------
    // Events — main canvas
    // -------------------------------------------------------------------------

    function bindEvents() {
        // Main canvas
        canvas.addEventListener('mousedown',  onMouseDown);
        canvas.addEventListener('mousemove',  onMouseMove);
        canvas.addEventListener('mouseup',    onMouseUp);
        canvas.addEventListener('mouseleave', onMouseLeave);
        canvas.addEventListener('wheel',      onWheel, { passive: false });
        canvas.addEventListener('click',      onCanvasClick);
        canvas.addEventListener('touchstart', onTouchStart, { passive: false });
        canvas.addEventListener('touchmove',  onTouchMove,  { passive: false });
        canvas.addEventListener('touchend',   onTouchEnd);

        // Minimap
        minimap.addEventListener('click', onMinimapClick);
    }

    function onMouseDown(e) {
        dragging = true; didDrag = false;
        dragSX = e.clientX; dragSY = e.clientY;
        dragCX = camX;      dragCY = camY;
        canvas.style.cursor = 'grabbing';
    }

    function onMouseMove(e) {
        // Update hover coords for status bar
        const rect = canvas.getBoundingClientRect();
        const { x, y } = screenToTile(e.clientX - rect.left, e.clientY - rect.top);
        if (x >= 0 && y >= 0 && x < MAP_SIZE && y < MAP_SIZE) onHover(x, y);
        else onHover(null, null);

        if (!dragging) return;
        const dx = e.clientX - dragSX, dy = e.clientY - dragSY;
        if (Math.abs(dx) > DRAG_THRESH || Math.abs(dy) > DRAG_THRESH) didDrag = true;
        if (didDrag) {
            camX = dragCX - dx; camY = dragCY - dy;
            clampCamera(); scheduleFetch();
        }
    }

    function onMouseUp() {
        dragging = false;
        canvas.style.cursor = 'grab';
    }

    function onMouseLeave() {
        dragging = false;
        canvas.style.cursor = 'grab';
        onHover(null, null);
    }

    function onWheel(e) {
        e.preventDefault();
        const rect  = canvas.getBoundingClientRect();
        const mx    = e.clientX - rect.left, my = e.clientY - rect.top;
        applyZoom(zoomIdx + (e.deltaY < 0 ? 1 : -1), mx, my);
    }

    async function onCanvasClick(e) {
        // Ignore if this was actually a drag
        if (didDrag) { didDrag = false; return; }
        didDrag = false;

        const rect = canvas.getBoundingClientRect();
        const sx   = e.clientX - rect.left, sy = e.clientY - rect.top;
        const { x, y } = screenToTile(sx, sy);
        if (x < 0 || y < 0 || x >= MAP_SIZE || y >= MAP_SIZE) return;

        // Optimistically show coords while loading
        onTileInfo({ x, y, occupant: null });

        try {
            const r = await fetch(`/api/map/tile/${x}/${y}`);
            const j = await r.json();
            if (j.ok) onTileInfo(j.data);
        } catch { onTileInfo(null); }
    }

    // ---- Minimap click → jump ----

    function onMinimapClick(e) {
        const rect  = minimap.getBoundingClientRect();
        const mx    = e.clientX - rect.left, my = e.clientY - rect.top;
        const scale = MAP_SIZE / minimap.width;  // world tiles per minimap px
        const tx    = Math.floor(mx * scale);
        const ty    = Math.floor(my * scale);
        const s     = tileSize();
        camX = tx * s - canvas.width  / 2;
        camY = ty * s - canvas.height / 2;
        clampCamera(); lastVP = ''; scheduleFetch();
    }

    // ---- Touch ----

    let tLastDist = 0;

    function onTouchStart(e) {
        e.preventDefault();
        if (e.touches.length === 1) {
            dragging = true; didDrag = false;
            dragSX = e.touches[0].clientX; dragSY = e.touches[0].clientY;
            dragCX = camX; dragCY = camY;
        } else if (e.touches.length === 2) {
            dragging = false;
            tLastDist = touchDist(e.touches[0], e.touches[1]);
        }
    }

    function onTouchMove(e) {
        e.preventDefault();
        if (e.touches.length === 1 && dragging) {
            const dx = e.touches[0].clientX - dragSX;
            const dy = e.touches[0].clientY - dragSY;
            if (Math.abs(dx) > DRAG_THRESH || Math.abs(dy) > DRAG_THRESH) didDrag = true;
            if (didDrag) {
                camX = dragCX - dx; camY = dragCY - dy;
                clampCamera(); scheduleFetch();
            }
        } else if (e.touches.length === 2) {
            const d    = touchDist(e.touches[0], e.touches[1]);
            const rect = canvas.getBoundingClientRect();
            const mx   = (e.touches[0].clientX + e.touches[1].clientX) / 2 - rect.left;
            const my   = (e.touches[0].clientY + e.touches[1].clientY) / 2 - rect.top;
            if (d > tLastDist * 1.2) { applyZoom(zoomIdx + 1, mx, my); tLastDist = d; }
            else if (d < tLastDist * 0.8) { applyZoom(zoomIdx - 1, mx, my); tLastDist = d; }
        }
    }

    function onTouchEnd(e) {
        if (e.touches.length === 0) dragging = false;
    }

    function touchDist(t1, t2) {
        const dx = t1.clientX - t2.clientX, dy = t1.clientY - t2.clientY;
        return Math.sqrt(dx*dx + dy*dy);
    }

    // -------------------------------------------------------------------------
    // Zoom
    // -------------------------------------------------------------------------

    function applyZoom(newIdx, pivotX, pivotY) {
        newIdx = Math.max(0, Math.min(ZOOM_LEVELS.length - 1, newIdx));
        if (newIdx === zoomIdx) return;
        const oldS = tileSize();
        zoomIdx    = newIdx;
        const newS = tileSize();
        camX = Math.round((camX + pivotX) * (newS / oldS) - pivotX);
        camY = Math.round((camY + pivotY) * (newS / oldS) - pivotY);
        clampCamera(); lastVP = ''; scheduleFetch();
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    function jumpToCity() {
        if (!myCity) return;
        const s = tileSize();
        camX = myCity.x * s - canvas.width  / 2 + s / 2;
        camY = myCity.y * s - canvas.height / 2 + s / 2;
        clampCamera(); lastVP = ''; scheduleFetch();
    }

    function zoomIn()  { applyZoom(zoomIdx + 1, canvas.width / 2, canvas.height / 2); }
    function zoomOut() { applyZoom(zoomIdx - 1, canvas.width / 2, canvas.height / 2); }
    function currentZoom() { return ZOOM_LEVELS[zoomIdx]; }

    return { init, jumpToCity, zoomIn, zoomOut, currentZoom };
})();
