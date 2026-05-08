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
    // Fallback colours while custom tiles load
    // plains_01/02/03 → green, plains_04 → sand
    const TILE_FILL = ['#5cb83c', '#5cb83c', '#5cb83c', '#c8a050'];

    // Minimap RGB for each tile index
    const TILE_RGB = TILE_FILL.map(hex => ({
        r: parseInt(hex.slice(1, 3), 16),
        g: parseInt(hex.slice(3, 5), 16),
        b: parseInt(hex.slice(5, 7), 16),
    }));

    // -------------------------------------------------------------------------
    // Kenney Tiny Town atlas
    //   tilemap_packed.png — 12 cols × 11 rows, 16×16 tiles, 1px gap → 17px stride
    // -------------------------------------------------------------------------

    // -------------------------------------------------------------------------
    // Custom terrain tiles (user-created 32×32 PNGs)
    //   plains_01–03 → 90%   plains_04 → 10%
    // -------------------------------------------------------------------------

    const TERRAIN_IMG_SRCS = [
        '/assets/sprites/terrain/plains_01.png',
        '/assets/sprites/terrain/plains_02.png',
        '/assets/sprites/terrain/plains_03.png',
        '/assets/sprites/terrain/plains_04.png',  // desert / sand
    ];

    let terrainImgs = [];
    // Per-image loaded flags — no single point of failure
    let terrainLoaded = [];

    // -------------------------------------------------------------------------
    // Tile selection — position-stable hash, no biomes, no water
    //   plains_01/02/03 (idx 0-2) → 90%
    //   plains_04       (idx 3)   → 10%
    // -------------------------------------------------------------------------

    function tileHash(seed, gx, gy) {
        let h = (seed ^ (Math.imul(gx, 374761393) + Math.imul(gy, 668265263))) >>> 0;
        h = Math.imul(h ^ (h >>> 13), 1274126177) >>> 0;
        // Divide by 2^32 so result is always in [0, 0.9999…] — never exactly 1.0
        return ((h ^ (h >>> 16)) >>> 0) / 0x100000000;
    }

    function getTileIdx(seed, tx, ty) {
        const r = tileHash(seed, tx, ty);
        if (r < 0.02) return 3;          // plains_04 —  2%
        const r2 = tileHash(seed + 1, tx, ty);
        if (r2 < 0.61) return 0;         // plains_01 — ~60%
        if (r2 < 0.81) return 1;         // plains_02 — ~20%
        return 2;                        // plains_03 — ~18%
    }

    // -------------------------------------------------------------------------
    // State
    // -------------------------------------------------------------------------

    // Main canvas
    let canvas, ctx;
    // Minimap canvas
    let minimap, mmCtx, mmImgData, mmFullCanvas;

    const MM_ZOOM = 2; // show half the world at a time — adjust for more/less zoom

    let seed, myCity;
    let onTileInfo, onHover;

    // Camera (world-pixel coords of top-left corner of canvas)
    let camX = 0, camY = 0, zoomIdx = 2; // default 4×

    // Drag state
    let dragging = false, dragSX = 0, dragSY = 0, dragCX = 0, dragCY = 0, didDrag = false;

    // Entity cache
    let entities = {}, fetchTimer = null, lastVP = '';

    // Selected tile (shows border + keeps info panel open)
    let selectedTile = null;

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

        loadTerrainTiles();
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

    function loadTerrainTiles() {
        terrainLoaded = new Array(TERRAIN_IMG_SRCS.length).fill(false);
        terrainImgs   = TERRAIN_IMG_SRCS.map((src, i) => {
            const img = new Image();
            img.onload  = () => { terrainLoaded[i] = true; };
            img.onerror = () => { console.warn('[terrain] failed to load: ' + src); };
            img.src = src;
            return img;
        });
    }

    // Draw custom terrain tile, or fall back to a solid colour while loading
    function drawTerrainTile(imgIdx, destX, destY, destSize) {
        const dx = Math.round(destX), dy = Math.round(destY);
        if (terrainLoaded[imgIdx]) {
            ctx.drawImage(terrainImgs[imgIdx], dx, dy, destSize, destSize);
        } else {
            ctx.fillStyle = imgIdx === 3 ? '#c8a050' : '#5cb83c';
            ctx.fillRect(dx, dy, destSize, destSize);
        }
    }

    function resizeMain() {
        canvas.width  = canvas.offsetWidth;
        canvas.height = canvas.offsetHeight;
    }

    // -------------------------------------------------------------------------
    // Minimap terrain — pre-rendered once into an ImageData
    // -------------------------------------------------------------------------

    function buildMinimapTerrain() {
        const size = minimap.offsetWidth || 256;
        minimap.width  = size;
        minimap.height = size;
        mmCtx = minimap.getContext('2d');

        // Render full 1:1 world map into an off-screen canvas
        mmFullCanvas = document.createElement('canvas');
        mmFullCanvas.width  = size;
        mmFullCanvas.height = size;
        const fCtx  = mmFullCanvas.getContext('2d');
        const scale = MAP_SIZE / size;
        const img   = fCtx.createImageData(size, size);
        const data  = img.data;

        for (let py = 0; py < size; py++) {
            for (let px = 0; px < size; px++) {
                const tx  = Math.floor(px * scale);
                const ty  = Math.floor(py * scale);
                const idx = getTileIdx(seed, tx, ty);
                const rgb = TILE_RGB[idx] ?? TILE_RGB[0];
                const i   = (py * size + px) * 4;
                data[i]     = rgb.r;
                data[i + 1] = rgb.g;
                data[i + 2] = rgb.b;
                data[i + 3] = 255;
            }
        }
        fCtx.putImageData(img, 0, 0);
        mmImgData = img; // truthy flag used by renderMinimap guard
    }

    // Returns the current zoomed view region of the full minimap canvas
    function mmViewRegion() {
        const mmSize   = minimap.width || 256;
        const s        = tileSize();
        const cx       = (camX + canvas.width  / 2) / s;
        const cy       = (camY + canvas.height / 2) / s;
        const viewTiles = MAP_SIZE / MM_ZOOM;
        const imgScale  = mmSize / MAP_SIZE;        // full-canvas px per world tile
        const viewPx    = viewTiles * imgScale;     // source rect size in full-canvas px
        let   sx = (cx - viewTiles / 2) * imgScale;
        let   sy = (cy - viewTiles / 2) * imgScale;
        sx = Math.max(0, Math.min(mmSize - viewPx, sx));
        sy = Math.max(0, Math.min(mmSize - viewPx, sy));
        const worldOffX = sx / imgScale;            // world tile at minimap left edge
        const worldOffY = sy / imgScale;
        const pxPerTile = mmSize / viewTiles;       // minimap px per world tile (zoomed)
        return { sx, sy, viewPx, worldOffX, worldOffY, pxPerTile, mmSize };
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
                const px = tx * s - camX;
                const py = ty * s - camY;
                drawTerrainTile(getTileIdx(seed, tx, ty), px, py, s);
            }
        }

        // Entities
        for (const e of Object.values(entities)) {
            const px = e.x * s - camX;
            const py = e.y * s - camY;
            if (px < -s || py < -s || px > canvas.width + s || py > canvas.height + s) continue;
            drawEntity(e, px, py, s);
        }

        // Selected tile border
        if (selectedTile !== null) {
            const px = Math.round(selectedTile.x * s - camX);
            const py = Math.round(selectedTile.y * s - camY);
            ctx.save();
            ctx.strokeStyle = '#ffffff';
            ctx.lineWidth   = Math.max(2, s * 0.07);
            ctx.shadowColor = '#ffffff';
            ctx.shadowBlur  = 6;
            ctx.strokeRect(px + 1, py + 1, s - 2, s - 2);
            ctx.restore();
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
            const level = e.monster_code % 100;
            ctx.beginPath();
            ctx.arc(px + s/2, py + s/2, s/2 - pad, 0, Math.PI * 2);
            ctx.fillStyle   = '#ef4444';
            ctx.strokeStyle = '#991b1b';
            ctx.lineWidth   = 1;
            ctx.fill();
            ctx.stroke();
            if (s >= 16) {
                ctx.fillStyle    = '#ffffff';
                ctx.font         = `bold ${Math.max(7, Math.floor(s * 0.38))}px monospace`;
                ctx.textAlign    = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(String(level), px + s / 2, py + s / 2);
            }
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
        if (!mmFullCanvas) buildMinimapTerrain();
        if (!mmFullCanvas) return;

        const { sx, sy, viewPx, worldOffX, worldOffY, pxPerTile, mmSize } = mmViewRegion();

        // Draw zoomed terrain slice
        mmCtx.imageSmoothingEnabled = false;
        mmCtx.drawImage(mmFullCanvas, sx, sy, viewPx, viewPx, 0, 0, mmSize, mmSize);

        // Entity dots
        for (const e of Object.values(entities)) {
            const mx = Math.round((e.x - worldOffX) * pxPerTile);
            const my = Math.round((e.y - worldOffY) * pxPerTile);
            if (mx < -2 || my < -2 || mx > mmSize + 2 || my > mmSize + 2) continue;
            if      (e.type === 'city')     mmCtx.fillStyle = '#f59e0b';
            else if (e.type === 'monster')  mmCtx.fillStyle = '#ef4444';
            else if (e.type === 'resource') mmCtx.fillStyle = '#22c55e';
            else if (e.type === 'shrine')   mmCtx.fillStyle = '#a78bfa';
            else continue;
            mmCtx.fillRect(mx - 1, my - 1, 3, 3);
        }

        // My city marker
        if (myCity) {
            const mx = Math.round((myCity.x - worldOffX) * pxPerTile);
            const my = Math.round((myCity.y - worldOffY) * pxPerTile);
            mmCtx.fillStyle = '#ffffff';
            mmCtx.fillRect(mx - 2, my - 2, 5, 5);
        }

        // Viewport rectangle
        const s   = tileSize();
        const rx  = Math.round((camX / s - worldOffX) * pxPerTile);
        const ry  = Math.round((camY / s - worldOffY) * pxPerTile);
        const rw  = Math.max(2, Math.round((canvas.width  / s) * pxPerTile));
        const rh  = Math.max(2, Math.round((canvas.height / s) * pxPerTile));

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

        // Minimap — drag to scroll
        minimap.addEventListener('mousedown',  onMinimapDown);
        minimap.addEventListener('mousemove',  onMinimapMove);
        minimap.addEventListener('mouseup',    onMinimapUp);
        minimap.addEventListener('mouseleave', onMinimapUp);
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

        // Mark tile as selected immediately (shows border while loading)
        selectedTile = { x, y };

        // Optimistically show coords while loading
        onTileInfo({ x, y, occupant: null });

        try {
            const r = await fetch(`/api/map/tile/${x}/${y}`);
            const j = await r.json();
            if (j.ok) onTileInfo(j.data);
            // j.ok = false leaves the optimistic {x, y, occupant:null} in place
        } catch {
            // Network/parse error: keep whatever is shown, don't blank the panel
        }
    }

    // ---- Minimap drag → scroll ----

    let mmDragging = false;

    function mmEventToWorld(e) {
        const rect = minimap.getBoundingClientRect();
        const mx   = (e.clientX - rect.left) * (minimap.width  / rect.width);
        const my   = (e.clientY - rect.top)  * (minimap.height / rect.height);
        const { worldOffX, worldOffY, pxPerTile } = mmViewRegion();
        return { tx: worldOffX + mx / pxPerTile, ty: worldOffY + my / pxPerTile };
    }

    function onMinimapDown(e) {
        mmDragging = true;
        minimap.style.cursor = 'grabbing';
        const { tx, ty } = mmEventToWorld(e);
        const s = tileSize();
        camX = tx * s - canvas.width  / 2;
        camY = ty * s - canvas.height / 2;
        clampCamera(); lastVP = ''; scheduleFetch();
    }

    function onMinimapMove(e) {
        if (!mmDragging) return;
        const { tx, ty } = mmEventToWorld(e);
        const s = tileSize();
        camX = tx * s - canvas.width  / 2;
        camY = ty * s - canvas.height / 2;
        clampCamera(); lastVP = ''; scheduleFetch();
    }

    function onMinimapUp() {
        mmDragging = false;
        minimap.style.cursor = 'crosshair';
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
