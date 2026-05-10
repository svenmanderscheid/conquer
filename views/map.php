<?php
declare(strict_types=1);
/**
 * Map View — Sprint 2+
 *
 * Layout: Left = large detail map canvas | Right = minimap + info panel.
 * index.php guarantees the player is logged in before including this file.
 */
?>
<!DOCTYPE html>
<html lang="de">
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
            height: calc(100% - 52px);
            margin-top: 52px;
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
            background: #2b7fb8;
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
        .hp-bar-wrap { margin-bottom: 8px; }
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

        /* ── Modal base ── */
        .modal-overlay {
            position: fixed; inset: 0; z-index: 200;
            background: rgba(0,0,0,0.7);
            display: flex; align-items: center; justify-content: center;
        }
        .modal-box {
            background: #1e293b; border: 1px solid #334155;
            border-radius: 12px; padding: 1.5rem;
            width: 380px; max-width: 95vw; max-height: 90vh; overflow-y: auto;
        }
        .modal-title { font-size: 1rem; font-weight: 700; margin-bottom: 1rem; }
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
        .troop-pick-controls { display: flex; align-items: center; gap: 0.4rem; }
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
        .modal-btn-cancel:hover { background: #3d4f66; }
        .modal-btn-red     { background: #ef4444; color: #fff; }
        .modal-btn-red:hover { opacity: 0.9; }
        .modal-btn-orange  { background: #f59e0b; color: #1c1917; }
        .modal-btn-orange:hover { opacity: 0.9; }
        .modal-btn-purple  { background: #8b5cf6; color: #fff; }
        .modal-btn-purple:hover { opacity: 0.9; }
        .modal-btn:disabled { opacity: 0.45; cursor: not-allowed; }

        /* ── LoK Attack Overlay ── */
        .atk-overlay {
            position: fixed; inset: 0; z-index: 300;
            background: rgba(0,0,0,0.75);
            display: flex; align-items: center; justify-content: center;
        }
        .atk-dialog {
            display: flex; width: 90vw; max-width: 900px;
            height: 80vh; max-height: 620px;
            border-radius: 12px; overflow: hidden;
            box-shadow: 0 24px 64px rgba(0,0,0,0.7);
            position: relative;
        }
        .atk-back {
            position: absolute; top: 10px; left: 10px; z-index: 10;
            width: 36px; height: 28px;
            background: #1d4ed8; border: 2px solid #3b82f6; border-radius: 6px;
            color: #fff; font-size: 1rem; font-weight: 900; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
        }
        .atk-back:hover { background: #2563eb; }
        /* Left target panel */
        .atk-left {
            flex: 0 0 30%; display: flex; align-items: center; justify-content: center;
            padding: 48px 14px 14px;
            background: #0a1220;
        }
        .atk-target-panel {
            display: flex; flex-direction: column; align-items: center;
            width: 100%; gap: 10px;
        }
        .atk-banner {
            width: 100%; padding: 8px 16px; border-radius: 4px;
            background: linear-gradient(180deg,#c8730a,#7a3d00);
            border: 2px solid #f59e0b;
            font-size: 1rem; font-weight: 900; text-transform: uppercase;
            color: #fff; text-shadow: 0 1px 2px rgba(0,0,0,.5); text-align: center;
        }
        .atk-banner-red {
            background: linear-gradient(180deg,#7f1d1d,#450a0a);
            border-color: #ef4444;
        }
        .atk-coords { font-size: 0.75rem; color: #94a3b8; }
        .atk-city-img { font-size: 4.5rem; line-height: 1; filter: drop-shadow(0 4px 8px rgba(0,0,0,.5)); }
        .atk-name-row {
            display: flex; align-items: center; gap: 10px;
            font-size: 0.85rem; color: #e2e8f0; font-weight: 700;
        }
        .atk-lvl-hex {
            background: #0f172a; border: 2px solid #ef4444; border-radius: 6px;
            padding: 2px 8px; font-weight: 900; color: #ef4444; font-size: 1rem;
        }
        .atk-monster-lv {
            font-size: 1.4rem; font-weight: 900; color: #e2e8f0;
            display: flex; align-items: center; gap: 8px;
        }
        .atk-skull { font-size: 1.8rem; color: #ef4444; }
        .atk-hp-track {
            width: 100%; height: 12px; background: #1e293b;
            border: 1px solid #334155; border-radius: 6px; overflow: hidden;
        }
        .atk-hp-fill { height: 100%; background: #ef4444; border-radius: 6px; }
        .atk-reward {
            width: 100%; background: #0f172a; border: 2px solid #0ea5e9; border-radius: 8px;
            padding: 8px;
        }
        .atk-reward-hdr {
            font-size: 0.68rem; font-weight: 900; color: #38bdf8;
            text-transform: uppercase; text-align: center; letter-spacing: .07em;
            margin-bottom: 6px;
        }
        .atk-reward-body { font-size: 0.72rem; color: #94a3b8; text-align: center; }
        /* Right panel */
        .atk-right {
            flex: 1; display: flex; flex-direction: column;
            background: #0e1a2e; border-left: 1px solid #1e3a5f;
            overflow: hidden;
        }
        /* Tabs */
        .atk-tabs {
            display: flex; align-items: center; gap: 10px;
            background: #0c1628; border-bottom: 2px solid #1e3a5f;
            flex-shrink: 0; padding: 0 14px;
        }
        .atk-tab-title {
            flex: 1; padding: 11px 0;
            font-size: 0.9rem; font-weight: 900; color: #fff;
            text-transform: uppercase; letter-spacing: .05em;
        }
        .atk-tab-march {
            padding: 4px 12px; border-radius: 4px;
            background: rgba(59,130,246,.2); border: 1px solid #3b82f6;
            font-size: 0.72rem; font-weight: 700; color: #93c5fd;
            text-transform: uppercase; letter-spacing: .06em;
        }
        /* Body */
        .atk-body { flex: 1; display: flex; overflow: hidden; min-height: 0; }
        /* Troop list */
        .atk-list {
            flex: 0 0 56%; border-right: 1px solid #1e3a5f;
            overflow-y: auto; padding: 6px 8px;
            display: flex; flex-direction: column; gap: 2px;
        }
        .atk-loading { color: #475569; text-align: center; padding: 2rem; font-size: 0.85rem; }
        .atk-row { display: flex; align-items: center; gap: 7px; padding: 5px 2px; }
        .atk-icon {
            flex: 0 0 46px; height: 46px; border-radius: 6px; position: relative;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; font-weight: 900; color: #fff;
        }
        .atk-t1 { background: #334155; border: 2px solid #475569; }
        .atk-t2 { background: #1d4ed8; border: 2px solid #3b82f6; }
        .atk-t3 { background: #92400e; border: 2px solid #f59e0b; }
        .atk-t4 { background: #5b21b6; border: 2px solid #8b5cf6; }
        .atk-t5 { background: #991b1b; border: 2px solid #ef4444; }
        .atk-tbadge {
            position: absolute; top: -5px; right: -5px;
            font-size: 0.5rem; font-weight: 900; padding: 1px 3px; border-radius: 3px;
            background: #7f1d1d; border: 1px solid #ef4444; color: #fca5a5; line-height: 1.2;
        }
        .atk-t2 .atk-tbadge { background: #1e3a8a; border-color: #3b82f6; color: #93c5fd; }
        .atk-t3 .atk-tbadge { background: #78350f; border-color: #f59e0b; color: #fde68a; }
        .atk-t4 .atk-tbadge { background: #3b0764; border-color: #8b5cf6; color: #c4b5fd; }
        .atk-t5 .atk-tbadge { background: #7f1d1d; border-color: #ef4444; color: #fca5a5; }
        .atk-mid { flex: 1; display: flex; flex-direction: column; gap: 3px; min-width: 0; }
        .atk-cnt { font-size: 0.88rem; font-weight: 700; color: #e2e8f0; }
        .atk-srow { display: flex; align-items: center; gap: 4px; }
        .atk-sl { flex: 1; accent-color: #38bdf8; height: 6px; cursor: pointer; }
        .atk-sl:disabled { opacity: .3; cursor: not-allowed; }
        .atk-arr {
            flex: 0 0 30px; height: 22px; font-size: 0.62rem;
            background: linear-gradient(180deg,#16a34a,#15803d); border: 1px solid #166534;
            border-radius: 4px; color: #fff; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
        }
        .atk-arr:hover { background: linear-gradient(180deg,#22c55e,#16a34a); }
        .atk-arr:disabled { opacity: .3; cursor: not-allowed; }
        .atk-num {
            flex: 0 0 60px; padding: 3px 5px;
            background: #0f172a; border: 1px solid #334155; border-radius: 4px;
            color: #e2e8f0; font-size: 0.82rem; text-align: right;
        }
        /* Formation panel */
        .atk-form {
            flex: 1; overflow-y: auto; padding: 8px 6px;
            display: flex; flex-direction: column; gap: 6px; min-width: 0;
        }
        .atk-presets { display: flex; gap: 5px; }
        .atk-preset {
            flex: 1; height: 27px; border-radius: 14px;
            background: #1e293b; border: 1px solid #334155;
            color: #94a3b8; font-size: 0.82rem; font-weight: 700; cursor: pointer;
            transition: all .15s;
        }
        .atk-preset:hover { background: #273548; color: #e2e8f0; }
        .atk-preset.is-active { background: #3b82f6; border-color: #60a5fa; color: #fff; }
        .atk-cap-row {
            display: flex; align-items: center; gap: 6px; font-size: 0.75rem;
            color: #94a3b8; font-weight: 600;
            background: #0f172a; border: 1px solid #1e293b; border-radius: 5px;
            padding: 4px 8px;
        }
        .atk-grid {
            display: grid; grid-template-columns: repeat(3,1fr); gap: 4px; flex: 1; overflow-y: auto;
        }
        .atk-card {
            display: flex; flex-direction: column; align-items: center;
            background: #1e293b; border: 2px solid #334155; border-radius: 6px;
            padding: 3px; gap: 2px; transition: border-color .15s;
        }
        .atk-card.is-sel { border-color: #ef4444; }
        .atk-card-icon { width: 100%; aspect-ratio: 1; }
        .atk-card-cnt { font-size: 0.68rem; font-weight: 700; color: #e2e8f0; text-align: center; }
        /* Info bar */
        .atk-info {
            display: flex; padding: 5px 12px; gap: 16px; flex-shrink: 0;
            background: #0c1628; border-top: 1px solid #1e3a5f;
        }
        .atk-info-item { flex: 1; }
        .atk-info-lbl { display: block; font-size: 0.62rem; color: #64748b; text-transform: uppercase; letter-spacing: .05em; }
        .atk-info-val { display: block; font-size: 0.82rem; font-weight: 700; color: #e2e8f0; }
        /* Buttons row */
        .atk-foot { display: flex; height: 52px; flex-shrink: 0; }
        .atk-empty {
            flex: 0 0 28%; background: #f59e0b; border: none; border-radius: 0;
            color: #1c1917; font-size: 0.95rem; font-weight: 900;
            text-transform: uppercase; letter-spacing: .05em; cursor: pointer;
        }
        .atk-empty:hover { background: #d97706; }
        .atk-go {
            flex: 1; border: none; cursor: pointer;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: 0; border-radius: 0 0 12px 0; transition: opacity .15s;
        }
        .atk-go:hover:not(:disabled) { opacity: .88; }
        .atk-go:disabled { opacity: .4; cursor: not-allowed; }
        .atk-go-blue { background: #1d4ed8; }
        .atk-go-red  { background: #b91c1c; border-radius: 0; }
        .atk-go-time { font-size: 0.72rem; color: rgba(255,255,255,.7); line-height: 1; }
        .atk-go-lbl  { font-size: 0.95rem; font-weight: 900; color: #fff; text-transform: uppercase; line-height: 1.3; }

        /* ── Hex Popup ── */
        #hex-popup {
            position: fixed;
            z-index: 160;
            pointer-events: auto;
            transform: translateX(-50%);
        }
        [x-cloak] { display: none !important; }
        .hex-btn-grid {
            display: flex; flex-wrap: nowrap; gap: 6px; justify-content: center;
            align-items: flex-end;
        }
        .hex-btn {
            width: 52px; height: 52px;
            border-radius: 50%;
            border: 2px solid #334155;
            background: #1e293b;
            cursor: pointer;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            font-size: 0.52rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.04em;
            color: #94a3b8;
            transition: background 0.15s, border-color 0.15s, color 0.15s;
            gap: 2px;
        }
        .hex-btn:hover:not(:disabled) { background: #273548; color: #e2e8f0; }
        .hex-btn:disabled { opacity: 0.4; cursor: not-allowed; }
        .hex-btn .hex-btn-icon { font-size: 1.3rem; line-height: 1; }
        .hex-btn.hex-red   { border-color: rgba(239,68,68,0.5); color: #f87171; }
        .hex-btn.hex-red:hover:not(:disabled) { border-color: #ef4444; background: rgba(239,68,68,0.15); }
        .hex-btn.hex-orange { border-color: rgba(245,158,11,0.5); color: #fbbf24; }
        .hex-btn.hex-orange:hover:not(:disabled) { border-color: #f59e0b; background: rgba(245,158,11,0.12); }
        .hex-btn.hex-purple { border-color: rgba(139,92,246,0.5); color: #a78bfa; }
        .hex-btn.hex-purple:hover:not(:disabled) { border-color: #8b5cf6; background: rgba(139,92,246,0.12); }
        .hex-btn.hex-green  { border-color: rgba(34,197,94,0.5); color: #86efac; }
        .hex-btn.hex-green:hover:not(:disabled) { border-color: #22c55e; background: rgba(34,197,94,0.12); }

        /* Formation presets */
        .formation-bar {
            display: flex; gap: 4px; margin-bottom: 0.5rem;
        }
        .formation-slot {
            flex: 1; padding: 4px; border-radius: 4px;
            border: 1px solid #334155; background: #0f172a;
            font-size: 0.65rem; color: #64748b; text-align: center;
            cursor: pointer; transition: border-color 0.15s, color 0.15s;
        }
        .formation-slot:hover { border-color: #475569; color: #94a3b8; }
        .formation-slot.active { border-color: #f59e0b; color: #f59e0b; }
        .formation-save-btn {
            font-size: 0.65rem; color: #475569; background: none; border: none;
            cursor: pointer; padding: 2px 6px;
        }
        .formation-save-btn:hover { color: #22c55e; }

        /* Profile modal */
        .profile-header {
            display: flex; gap: 12px; align-items: flex-start; margin-bottom: 1rem;
        }
        .profile-avatar {
            width: 52px; height: 52px; border-radius: 10px;
            background: linear-gradient(135deg, #7c1e0e, #4a0d05);
            border: 2px solid #d4a017;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; flex-shrink: 0;
        }
        .profile-info { flex: 1; }
        .profile-name { font-size: 1.05rem; font-weight: 800; color: #f0d080; }
        .profile-tag  { font-size: 0.72rem; color: #64748b; margin-top: 2px; }
        .profile-stats-grid {
            display: grid; grid-template-columns: repeat(3,1fr); gap: 6px; margin-top: 0.75rem;
        }
        .pstat { background: #0f172a; border: 1px solid #1e293b; border-radius: 6px; padding: 6px; text-align: center; }
        .pstat-val { font-size: 0.88rem; font-weight: 700; color: #e2e8f0; }
        .pstat-lbl { font-size: 0.6rem; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; margin-top: 2px; }

        /* Profile tabs */
        .profile-tabs {
            display: flex; border-bottom: 1px solid #1e293b; margin: 0.75rem -1.5rem; padding: 0 1.5rem;
            gap: 0; overflow-x: auto;
        }
        .profile-tab-btn {
            padding: 7px 14px; font-size: 0.7rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.05em;
            color: #475569; background: none; border: none;
            border-bottom: 2px solid transparent;
            cursor: pointer; white-space: nowrap; margin-bottom: -1px;
        }
        .profile-tab-btn.active { color: #f0d080; border-bottom-color: #d4a017; }
        .profile-tab-placeholder {
            padding: 2rem; text-align: center; color: #334155; font-style: italic;
        }

        /* Rally time picker */
        .time-option-grid {
            display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; margin: 1rem 0;
        }
        .time-option {
            padding: 10px 16px; border-radius: 8px;
            border: 2px solid #334155; background: #0f172a;
            color: #94a3b8; font-size: 0.85rem; font-weight: 700;
            cursor: pointer; transition: border-color 0.15s, color 0.15s, background 0.15s;
        }
        .time-option:hover { border-color: #f59e0b; color: #fbbf24; }
        .time-option.selected { border-color: #f59e0b; color: #f59e0b; background: rgba(245,158,11,0.1); }

        /* Emoji picker */
        .emoji-grid {
            display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; margin: 1rem 0;
        }
        .emoji-option {
            width: 44px; height: 44px; border-radius: 8px;
            border: 2px solid #334155; background: #0f172a;
            font-size: 1.4rem; cursor: pointer; display: flex;
            align-items: center; justify-content: center;
            transition: border-color 0.15s, background 0.15s;
        }
        .emoji-option:hover { border-color: #8b5cf6; background: rgba(139,92,246,0.12); }
        .emoji-option.selected { border-color: #8b5cf6; background: rgba(139,92,246,0.2); }

        /* Skin changer */
        .skin-grid {
            display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; margin: 1rem 0;
        }
        .skin-option {
            width: 80px; border-radius: 8px;
            border: 2px solid #334155; background: #0f172a;
            padding: 8px 4px 6px; text-align: center;
            cursor: pointer; transition: border-color 0.15s;
        }
        .skin-option:hover { border-color: #f59e0b; }
        .skin-option.equipped { border-color: #22c55e; }
        .skin-option-icon { font-size: 1.8rem; }
        .skin-option-name { font-size: 0.6rem; color: #64748b; margin-top: 4px; }
        .skin-option.equipped .skin-option-name { color: #86efac; }

        /* ── Attack button ── */
        .btn-attack {
            display: block; width: 100%; margin-top: 10px;
            padding: 0.45rem; border-radius: 6px; border: none;
            background: #ef4444; color: #fff; font-size: 0.82rem; font-weight: 700;
            cursor: pointer;
        }
        .btn-attack:hover { opacity: 0.85; }

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
<?php $hudCurrentView = 'map'; require __DIR__ . '/partials/hud.php'; ?>
<style>#hud-bottom { right: 282px; }</style>
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

        <!-- Selected Tile Info — only shown for non-city/monster tiles -->
        <div class="panel" x-show="tileInfo && tileInfo.occupant?.type !== 'city' && tileInfo.occupant?.type !== 'monster'">
            <div class="panel-title">Tile Info</div>
            <div id="tile-info">

                <template x-if="!tileInfo">
                    <div class="tile-info-empty">Klicke auf ein Tile um Details zu sehen</div>
                </template>

                <template x-if="tileInfo">
                    <div>
                        <div class="tile-info-row" style="margin-bottom:8px">
                            <span class="lbl">Koordinaten</span>
                            <span class="val" x-text="tileInfo.x + ', ' + tileInfo.y"></span>
                        </div>

                        <template x-if="!tileInfo.occupant">
                            <div class="tile-info-empty">Leeres Tile</div>
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

        <!-- Field object (gather) panel -->
        <div class="panel" x-show="tileInfo && tileInfo.type === 'field_object'" x-cloak>
            <div class="panel-title">Ressourcenfeld</div>
            <div style="font-size:0.85rem;font-weight:800;color:#f0d080;margin-bottom:6px">
                🌾 <span x-text="fieldObjectName(tileInfo?.object_name)"></span>
                <span x-show="tileInfo?.level"> Lv.<span x-text="tileInfo?.level"></span></span>
            </div>
            <div style="font-size:0.75rem;color:#94a3b8;margin-bottom:6px">
                Ressourcen:
                <span style="color:#ffd700;font-weight:700" x-text="(tileInfo?.resource_amount ?? 0).toLocaleString()"></span>
                /
                <span x-text="(tileInfo?.resource_max ?? 0).toLocaleString()"></span>
            </div>
            <div x-show="tileInfo?.is_occupied" style="font-size:0.72rem;color:#f87171;margin-bottom:6px">
                Wird bereits gesammelt
            </div>
            <!-- Resource progress bar -->
            <div style="height:6px;background:#1e293b;border-radius:3px;overflow:hidden;margin-bottom:12px">
                <div style="height:100%;background:#22c55e;border-radius:3px;transition:width 0.3s"
                     :style="`width:${tileInfo && tileInfo.resource_max > 0 ? Math.round((tileInfo.resource_amount/tileInfo.resource_max)*100) : 0}%`"></div>
            </div>
            <button style="width:100%;padding:7px;border:none;border-radius:6px;background:linear-gradient(180deg,#16a34a,#15803d);color:#fff;font-size:0.82rem;font-weight:700;cursor:pointer"
                    @click="dispatchGather()"
                    :disabled="tileInfo?.is_occupied">
                🚶 Sammeln
            </button>
        </div>

        <!-- Default "click on map" hint when no special tile selected -->
        <div class="panel" x-show="!tileInfo || ['city','monster','field_object'].includes(tileInfo.occupant?.type ?? tileInfo?.type)">
            <div class="tile-info-empty" style="padding:4px 0">Klicke auf ein Tile um Details zu sehen</div>
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
                <div class="leg-row"><div class="leg-swatch" style="background:#22c55e"></div>Eigene Stadt</div>
                <div class="leg-row"><div class="leg-swatch" style="background:#f59e0b"></div>Fremde Stadt</div>
                <div class="leg-row"><div class="leg-swatch" style="background:#ef4444;border-radius:50%"></div>Monster</div>
                <div class="leg-row"><div class="leg-swatch" style="background:#22c55e;transform:rotate(45deg)"></div>Resource</div>
            </div>
        </div>

        <!-- Active Marches -->
        <div class="panel" x-show="marches.length > 0">
            <div class="panel-title">Aktive Märsche</div>
            <template x-for="m in marches" :key="m.id">
                <div class="march-badge"
                     :class="m.state === 'returning' ? 'return' : ([5,6].includes(+m.march_type) ? 'monster' : 'pvp')"
                     style="cursor:pointer"
                     @click="jumpToMarch(m)"
                     title="Auf Karte springen">
                    <span class="march-badge-label">
                        <template x-if="m.state === 'marching' && +m.march_type === 5">
                            <span>⚔ Monster (<span x-text="m.target_x + ',' + m.target_y"></span>)</span>
                        </template>
                        <template x-if="m.state === 'marching' && +m.march_type === 6">
                            <span>✨ Charm (<span x-text="m.target_x + ',' + m.target_y"></span>)</span>
                        </template>
                        <template x-if="m.state === 'marching' && +m.march_type === 7">
                            <span>⚔ Angriff (<span x-text="m.target_x + ',' + m.target_y"></span>)</span>
                        </template>
                        <template x-if="m.state === 'marching' && +m.march_type === 8">
                            <span>🔭 Scout (<span x-text="m.target_x + ',' + m.target_y"></span>)</span>
                        </template>
                        <template x-if="m.state === 'marching' && ![5,6,7,8].includes(+m.march_type)">
                            <span>⚔ (<span x-text="m.target_x + ',' + m.target_y"></span>)</span>
                        </template>
                        <template x-if="m.state === 'returning'">
                            <span>↩ Rückkehr</span>
                        </template>
                    </span>
                    <span class="march-badge-state" x-text="marchEta(m, tick)"></span>
                </div>
            </template>
        </div>

    </aside>
</div><!-- #app -->

<!-- ── Hex Popup Overlay ── -->
<div id="hex-popup" x-show="hexPopup" x-cloak
     :style="`left:${hexPopupX}px;top:${hexPopupY}px`"
     @mousedown.stop>
    <div class="hex-btn-grid">
        <template x-if="hexPopup === 'own'">
            <div style="display:contents">
                <button class="hex-btn hex-purple" style="transform:translateY(0px)" @click="openEmojiPicker(); hexPopup=null">
                    <span class="hex-btn-icon">😀</span>Emoji
                </button>
                <button class="hex-btn hex-orange" style="transform:translateY(16px)" @click="openOwnProfile(); hexPopup=null">
                    <span class="hex-btn-icon">👤</span>Profil
                </button>
                <button class="hex-btn hex-green" style="transform:translateY(16px)" @click="hexPopup=null; window.location.href='/city'">
                    <span class="hex-btn-icon">🏰</span>Stadt
                </button>
                <button class="hex-btn" style="transform:translateY(0px)" @click="openSkinModal(); hexPopup=null">
                    <span class="hex-btn-icon">👕</span>Skin
                </button>
            </div>
        </template>
        <template x-if="hexPopup === 'enemy'">
            <div style="display:contents">
                <button class="hex-btn hex-orange" style="transform:translateY(0px)" @click="openEnemyProfile(hexPopupEntity); hexPopup=null">
                    <span class="hex-btn-icon">ℹ️</span>Info
                </button>
                <button class="hex-btn hex-red" style="transform:translateY(13.5px)" @click="openPlayerAttack(hexPopupEntity); hexPopup=null">
                    <span class="hex-btn-icon">⚔️</span>Angriff
                </button>
                <button class="hex-btn hex-orange" style="transform:translateY(18px)" @click="openRally(hexPopupEntity); hexPopup=null">
                    <span class="hex-btn-icon">🏳️</span>Rally
                </button>
                <button class="hex-btn hex-purple" style="transform:translateY(13.5px)" @click="sendScout(hexPopupEntity); hexPopup=null">
                    <span class="hex-btn-icon">🔭</span>Scout
                </button>
                <button class="hex-btn" style="transform:translateY(0px)" disabled title="Kommt bald">
                    <span class="hex-btn-icon">💊</span>Debuff
                </button>
            </div>
        </template>
    </div>
</div>

<!-- ── LoK-style Attack Modal (Monster + Player combined) ── -->
<div class="atk-overlay" x-show="attackModal || playerAttackModal" x-cloak
     style="display:none" @click.self="attackModal=false; playerAttackModal=false">
    <div class="atk-dialog">
    <button class="atk-back" @click="attackModal=false; playerAttackModal=false">&#8592;</button>

    <!-- Left: target info -->
    <div class="atk-left">
        <!-- Player target -->
        <div x-show="playerAttackModal" class="atk-target-panel">
            <div class="atk-banner"
                 x-text="playerAttackTarget?.player_name ?? playerAttackTarget?.player ?? '?'"></div>
            <div class="atk-coords"
                 x-text="playerAttackTarget ? `X:${playerAttackTarget.x} Y:${playerAttackTarget.y}` : ''"></div>
            <div class="atk-city-img">🏰</div>
            <div class="atk-name-row">
                <span class="atk-lvl-hex" x-text="playerAttackTarget?.level ?? 1"></span>
                <span x-text="playerAttackTarget?.player_name ?? playerAttackTarget?.player ?? '?'"></span>
            </div>
        </div>
        <!-- Monster target -->
        <div x-show="attackModal" class="atk-target-panel">
            <div class="atk-banner atk-banner-red" x-text="attackTarget?.name ?? ''"></div>
            <div class="atk-coords"
                 x-text="attackTarget ? `X:${attackTarget.x} Y:${attackTarget.y}` : ''"></div>
            <div class="atk-city-img" style="font-size:3.5rem">👾</div>
            <div class="atk-monster-lv">
                <span class="atk-skull">☠</span>
                Lv.&nbsp;<strong x-text="attackTarget?.name?.match(/\d+/)?.[0] ?? '?'"></strong>
            </div>
            <div class="atk-hp-track"><div class="atk-hp-fill" style="width:100%"></div></div>
            <div class="atk-reward">
                <div class="atk-reward-hdr">POTENTIAL REWARD</div>
                <div class="atk-reward-body">EXP: —</div>
            </div>
        </div>
    </div>

    <!-- Right: troop selection -->
    <div class="atk-right">
        <!-- Header -->
        <div class="atk-tabs">
            <div class="atk-tab-title" x-text="attackModal ? 'MONSTER ATTACK' : 'ATTACK'"></div>
            <div class="atk-tab-march">MARCH</div>
        </div>

        <!-- Body: troop list + formation -->
        <div class="atk-body">
            <!-- Troop list -->
            <div class="atk-list">
                <div x-show="(attackModal && attackLoading)||(playerAttackModal && playerAttackLoading)"
                     class="atk-loading">Truppen laden…</div>
                <template x-for="t in (attackModal ? attackTroops : playerAttackTroops)" :key="t.code">
                    <div class="atk-row">
                        <div class="atk-icon" :class="'atk-t'+(t.tier??1)">
                            <span x-text="(t.name??'?').charAt(0)"></span>
                            <div class="atk-tbadge"
                                 x-text="['','I','II','III','IV','V'][t.tier??1]??''"></div>
                        </div>
                        <div class="atk-mid">
                            <div class="atk-cnt" x-text="(t.toSend||0).toLocaleString()"></div>
                            <div class="atk-srow">
                                <input type="range" class="atk-sl" min="0"
                                       :max="t.available" step="1"
                                       x-model.number="t.toSend"
                                       :disabled="t.available===0">
                                <button class="atk-arr"
                                        @click="t.toSend = t.toSend===t.available ? 0 : t.available"
                                        :disabled="t.available===0">&#9668;&#9658;</button>
                            </div>
                        </div>
                        <input type="number" class="atk-num" min="0"
                               :max="t.available" x-model.number="t.toSend"
                               :disabled="t.available===0">
                    </div>
                </template>
            </div>

            <!-- Formation grid -->
            <div class="atk-form">
                <div class="atk-presets">
                    <template x-for="i in [0,1,2,3]" :key="i">
                        <button class="atk-preset"
                                :class="selectedFormation===i ? 'is-active' : ''"
                                @click="applyFormation(i)"
                                x-text="i+1"></button>
                    </template>
                </div>
                <div class="atk-cap-row">
                    <span>⚔</span>
                    <span x-text="
                        (attackModal?attackTroops:playerAttackTroops)
                            .reduce((s,t)=>s+(t.toSend||0),0).toLocaleString()
                        +' / '+
                        (attackModal?attackTroops:playerAttackTroops)
                            .reduce((s,t)=>s+t.available,0).toLocaleString()
                    "></span>
                </div>
                <div class="atk-grid">
                    <template x-for="t in (attackModal?attackTroops:playerAttackTroops).filter(t=>t.available>0)"
                              :key="t.code">
                        <div class="atk-card" :class="(t.toSend||0)>0 ? 'is-sel' : ''">
                            <div class="atk-card-icon atk-icon" :class="'atk-t'+(t.tier??1)">
                                <span x-text="(t.name??'?').charAt(0)"></span>
                                <div class="atk-tbadge"
                                     x-text="['','I','II','III','IV','V'][t.tier??1]??''"></div>
                            </div>
                            <div class="atk-card-cnt" x-text="(t.toSend||0).toLocaleString()"></div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Info bar -->
        <div class="atk-info">
            <div class="atk-info-item">
                <span class="atk-info-lbl">Troop Dispatch Queue</span>
                <span class="atk-info-val">1 / 8</span>
            </div>
            <div class="atk-info-item" x-show="playerAttackModal">
                <span class="atk-info-lbl">Troops Load</span>
                <span class="atk-info-val"
                      x-text="playerAttackTroops.reduce((s,t)=>s+(t.toSend||0),0).toLocaleString()"></span>
            </div>
            <div class="atk-info-item" x-show="attackModal">
                <span class="atk-info-lbl">Action Point</span>
                <span class="atk-info-val">10 / 4,422</span>
            </div>
        </div>

        <!-- Action buttons -->
        <div class="atk-foot">
            <button class="atk-empty"
                    @click="(attackModal?attackTroops:playerAttackTroops).forEach(t=>t.toSend=0)">
                EMPTY
            </button>
            <button class="atk-go"
                    :class="attackModal ? 'atk-go-red' : 'atk-go-blue'"
                    :disabled="(attackModal?attackLoading:playerAttackLoading)
                        || (attackModal?attackTroops:playerAttackTroops)
                               .reduce((s,t)=>s+(t.toSend||0),0)===0"
                    @click="attackModal ? sendMarch() : sendPlayerMarch()">
                <div class="atk-go-time">⏱ 00:00:00</div>
                <div class="atk-go-lbl" x-text="attackModal ? 'ATTACK' : 'MARCH'"></div>
            </button>
        </div>
    </div><!-- atk-right -->
    </div><!-- atk-dialog -->
</div>

<!-- ── Rally Modal ── -->
<div class="modal-overlay" x-show="rallyModal" @click.self="rallyModal=false" style="display:none">
    <div class="modal-box">
        <div class="modal-title" style="color:#f59e0b">🏳 Rally starten</div>
        <div style="font-size:0.82rem;color:#94a3b8;margin-bottom:0.75rem"
             x-text="rallyTarget ? 'Ziel: ' + (rallyTarget.player_name ?? rallyTarget.player) + ' (' + rallyTarget.x + ', ' + rallyTarget.y + ')' : ''"></div>

        <div class="modal-sub">Rally-Timer</div>
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:0.75rem">
            <div style="flex:1;background:#0f172a;border:1px solid #334155;border-radius:6px;padding:8px 12px;font-size:0.85rem;font-weight:700;color:#fbbf24;text-align:center"
                 x-text="rallyMinutes + ' Minuten'"></div>
            <button class="modal-btn modal-btn-orange" style="flex:0 0 auto;padding:8px 14px"
                    @click="rallyTimePicker=true">Ändern</button>
        </div>

        <div class="modal-sub">Truppen auswählen</div>
        <template x-if="rallyLoading"><div style="text-align:center;color:#475569;padding:1rem">Truppen laden…</div></template>
        <template x-if="!rallyLoading">
            <div>
                <template x-for="t in rallyTroops" :key="t.code">
                    <div class="troop-pick">
                        <div class="troop-pick-header">
                            <span class="troop-pick-name" x-text="t.name"></span>
                            <span class="troop-pick-avail" x-text="(t.toSend||0).toLocaleString() + ' / ' + t.available.toLocaleString()"></span>
                        </div>
                        <div class="troop-pick-controls">
                            <input type="range" class="troop-pick-slider" style="accent-color:#f59e0b" min="0" :max="t.available" step="1" x-model.number="t.toSend" :disabled="t.available===0">
                            <input type="number" class="troop-pick-input" min="0" :max="t.available" x-model.number="t.toSend" :disabled="t.available===0">
                            <button class="troop-pick-max" :disabled="t.available===0" @click="t.toSend=t.available">Max</button>
                        </div>
                    </div>
                </template>
                <div style="font-size:0.72rem;color:#64748b;margin-top:0.5rem"
                     x-text="'Gesamt: ' + rallyTroops.reduce((s,t)=>s+(t.toSend||0),0).toLocaleString() + ' Truppen'"></div>
            </div>
        </template>

        <div class="modal-actions">
            <button class="modal-btn modal-btn-cancel" @click="rallyModal=false">Abbrechen</button>
            <button class="modal-btn modal-btn-orange"
                    :disabled="rallyLoading || rallyTroops.reduce((s,t)=>s+(t.toSend||0),0)===0"
                    @click="startRally()">🏳 Rally starten</button>
        </div>
    </div>
</div>

<!-- ── Rally Time Picker ── -->
<div class="modal-overlay" x-show="rallyTimePicker" @click.self="rallyTimePicker=false" style="display:none">
    <div class="modal-box" style="max-width:320px;text-align:center">
        <div class="modal-title" style="color:#f59e0b">⏱ Rally-Timer wählen</div>
        <div class="time-option-grid">
            <template x-for="min in [5,10,15,30,60]" :key="min">
                <button class="time-option" :class="rallyMinutes===min ? 'selected' : ''"
                        @click="rallyMinutes=min; rallyTimePicker=false"
                        x-text="min + ' Min'"></button>
            </template>
        </div>
        <button class="modal-btn modal-btn-cancel" @click="rallyTimePicker=false">Schließen</button>
    </div>
</div>

<!-- ── Enemy Profile Modal ── -->
<div class="modal-overlay" x-show="enemyProfileModal" @click.self="enemyProfileModal=false" style="display:none">
    <div class="modal-box">
        <div class="modal-title" style="color:#f59e0b">👤 Spielerprofil</div>

        <template x-if="enemyProfileLoading">
            <div style="text-align:center;color:#475569;padding:2rem">Profil laden…</div>
        </template>

        <template x-if="!enemyProfileLoading && enemyProfile">
            <div>
                <div class="profile-header">
                    <div class="profile-avatar">👤</div>
                    <div class="profile-info">
                        <div class="profile-name" x-text="enemyProfile.username"></div>
                        <div class="profile-tag" x-text="enemyProfile.alliance_tag ? '[' + enemyProfile.alliance_tag + '] ' + (enemyProfile.alliance_name ?? '') : 'Keine Allianz'"></div>
                        <div class="profile-tag" style="margin-top:4px" x-text="'Lord Lv ' + (enemyProfile.lord_level ?? 0)"></div>
                    </div>
                </div>
                <div class="profile-stats-grid">
                    <div class="pstat">
                        <div class="pstat-val" x-text="(enemyProfile.power ?? 0).toLocaleString()"></div>
                        <div class="pstat-lbl">Macht</div>
                    </div>
                    <div class="pstat">
                        <div class="pstat-val" x-text="enemyProfile.castle_level ?? '?'"></div>
                        <div class="pstat-lbl">Castle Lv</div>
                    </div>
                    <div class="pstat">
                        <div class="pstat-val" x-text="(enemyProfile.kill_count ?? 0).toLocaleString()"></div>
                        <div class="pstat-lbl">Kills</div>
                    </div>
                </div>
            </div>
        </template>

        <div class="modal-actions" style="margin-top:1rem">
            <button class="modal-btn modal-btn-cancel" @click="enemyProfileModal=false">Schließen</button>
        </div>
    </div>
</div>

<!-- ── Own Profile Modal ── -->
<div class="modal-overlay" x-show="ownProfileModal" @click.self="ownProfileModal=false" style="display:none">
    <div class="modal-box" style="width:380px;padding:0;overflow:hidden">

        <!-- Header mit Avatar + Basis-Info -->
        <div style="background:linear-gradient(135deg,#1a0500,#3d0a00);padding:16px;display:flex;gap:14px;align-items:center;border-bottom:1px solid #5c1d0a">
            <div style="width:64px;height:64px;border-radius:8px;background:linear-gradient(135deg,#7c1e0e,#4a0d05);border:2px solid #d4a017;display:flex;align-items:center;justify-content:center;font-size:2rem;flex-shrink:0">⚔</div>
            <div style="flex:1;min-width:0">
                <div style="font-size:1rem;font-weight:800;color:#f0d080;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" x-text="ownProfile?.username ?? ''"></div>
                <div style="font-size:0.72rem;color:#94a3b8;margin-top:2px" x-text="ownProfile?.alliance_tag ? '[' + ownProfile.alliance_tag + '] ' + (ownProfile.alliance_name ?? '') : 'Keine Allianz'"></div>
            </div>
        </div>

        <!-- Stats -->
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1px;background:#1e293b">
            <div style="background:#0f172a;padding:10px;text-align:center">
                <div style="font-size:0.9rem;font-weight:700;color:#e2e8f0" x-text="(ownProfile?.power ?? 0).toLocaleString()"></div>
                <div style="font-size:0.6rem;color:#475569;text-transform:uppercase;letter-spacing:0.05em;margin-top:2px">Macht</div>
            </div>
            <div style="background:#0f172a;padding:10px;text-align:center">
                <div style="font-size:0.9rem;font-weight:700;color:#e2e8f0" x-text="ownProfile?.castle_level ?? '?'"></div>
                <div style="font-size:0.6rem;color:#475569;text-transform:uppercase;letter-spacing:0.05em;margin-top:2px">Castle Lv</div>
            </div>
            <div style="background:#0f172a;padding:10px;text-align:center">
                <div style="font-size:0.9rem;font-weight:700;color:#e2e8f0" x-text="(ownProfile?.kill_count ?? 0).toLocaleString()"></div>
                <div style="font-size:0.6rem;color:#475569;text-transform:uppercase;letter-spacing:0.05em;margin-top:2px">Kills</div>
            </div>
        </div>

        <!-- Lord Level + AP -->
        <div style="padding:12px 16px;border-bottom:1px solid #1e293b">
            <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:5px">
                <span style="font-size:0.78rem;font-weight:700;color:#f0d080">Lord Lv <span x-text="ownProfile?.lord_level ?? 0"></span></span>
                <span style="font-size:0.65rem;color:#64748b">XP: <span x-text="(ownProfile?.lord_xp ?? 0).toLocaleString()"></span></span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
                <span style="font-size:0.7rem;color:#64748b;white-space:nowrap">⚡ AP</span>
                <div style="flex:1;height:8px;background:#1e293b;border-radius:4px;overflow:hidden">
                    <div style="height:100%;background:#f59e0b;border-radius:4px;transition:width 0.3s"
                         :style="'width:' + Math.min(100, Math.round((ownProfile?.action_points ?? 0) / 200 * 100)) + '%'"></div>
                </div>
                <span style="font-size:0.7rem;color:#fbbf24;white-space:nowrap" x-text="(ownProfile?.action_points ?? 0) + ' / 200'"></span>
            </div>
        </div>

        <!-- Nav Buttons -->
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1px;background:#1e293b">
            <a href="#" style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;padding:10px 6px;background:#0f172a;color:#94a3b8;text-decoration:none;font-size:0.6rem;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;transition:background 0.15s" onmouseover="this.style.background='#1a2535'" onmouseout="this.style.background='#0f172a'">
                <span style="font-size:1.2rem">📜</span>VERLAUF
            </a>
            <a href="/alliance" style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;padding:10px 6px;background:#0f172a;color:#94a3b8;text-decoration:none;font-size:0.6rem;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;transition:background 0.15s" onmouseover="this.style.background='#1a2535'" onmouseout="this.style.background='#0f172a'">
                <span style="font-size:1.2rem">⚔</span>ALLIANZ
            </a>
            <a href="#" style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;padding:10px 6px;background:#0f172a;color:#94a3b8;text-decoration:none;font-size:0.6rem;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;transition:background 0.15s" onmouseover="this.style.background='#1a2535'" onmouseout="this.style.background='#0f172a'">
                <span style="font-size:1.2rem">📖</span>MEISTER
            </a>
            <a href="#" style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;padding:10px 6px;background:#0f172a;color:#94a3b8;text-decoration:none;font-size:0.6rem;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;transition:background 0.15s" onmouseover="this.style.background='#1a2535'" onmouseout="this.style.background='#0f172a'">
                <span style="font-size:1.2rem">💎</span>SCHATZ
            </a>
        </div>

        <!-- Schließen Button -->
        <div style="padding:10px 16px">
            <button class="modal-btn modal-btn-cancel" style="width:100%" @click="ownProfileModal=false">Schließen</button>
        </div>
    </div>
</div>

<!-- ── Emoji Picker Modal ── -->
<div class="modal-overlay" x-show="emojiModal" @click.self="emojiModal=false" style="display:none">
    <div class="modal-box" style="max-width:320px;text-align:center">
        <div class="modal-title" style="color:#8b5cf6">😀 Emoji senden</div>
        <div style="font-size:0.75rem;color:#64748b;margin-bottom:0.5rem">5 Sekunden über deiner Stadt sichtbar</div>
        <div class="emoji-grid">
            <template x-for="e in emojiList" :key="e">
                <button class="emoji-option" :class="selectedEmoji===e ? 'selected' : ''"
                        @click="selectedEmoji=e" x-text="e"></button>
            </template>
        </div>
        <div class="modal-actions">
            <button class="modal-btn modal-btn-cancel" @click="emojiModal=false">Abbrechen</button>
            <button class="modal-btn modal-btn-purple" :disabled="!selectedEmoji" @click="sendEmoji()">Senden</button>
        </div>
    </div>
</div>

<!-- ── Skin Changer Modal ── -->
<div class="modal-overlay" x-show="skinModal" @click.self="skinModal=false" style="display:none">
    <div class="modal-box" style="text-align:center">
        <div class="modal-title">👕 City Skin</div>
        <div style="font-size:0.75rem;color:#64748b;margin-bottom:0.75rem">Skin für deine Stadt wählen</div>
        <div class="skin-grid">
            <!-- "NO SKIN" always shown -->
            <div class="skin-option" :class="!equippedSkin ? 'equipped' : ''"
                 @click="equipSkin('')">
                <div class="skin-option-icon">🏰</div>
                <div class="skin-option-name" x-text="!equippedSkin ? '✓ Standard' : 'Standard'"></div>
            </div>
            <!-- Unlocked skins -->
            <template x-for="s in skins" :key="s.skin_code">
                <div class="skin-option" :class="s.is_equipped ? 'equipped' : ''"
                     @click="equipSkin(s.skin_code)">
                    <div class="skin-option-icon">🏰</div>
                    <div class="skin-option-name" x-text="(s.is_equipped ? '✓ ' : '') + s.skin_code"></div>
                </div>
            </template>
        </div>
        <div class="modal-actions">
            <button class="modal-btn modal-btn-cancel" @click="skinModal=false">Schließen</button>
        </div>
    </div>
</div>

<div id="map-toast"></div>

<script src="/assets/js/map.js?v=<?= filemtime(ROOT_DIR . '/assets/js/map.js') ?>"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
const CSRF         = <?= json_encode($session['csrf_token']) ?>;
const MY_PLAYER_ID = <?= json_encode((int) $session['player_id']) ?>;

function mapApp() {
    return {
        zoom:         4,
        myCity:       null,
        myAllianceId: null,
        tileInfo:     null,
        hoverTile:    '',
        marches:      [],
        tick:         0,

        // Hex popup
        hexPopup:       null,   // 'own' | 'enemy' | null
        hexPopupX:      0,
        hexPopupY:      0,
        hexPopupTitle:  '',
        hexPopupEntity: null,

        // Monster attack modal
        attackModal:    false,
        attackTarget:   null,
        attackTroops:   [],
        attackLoading:  false,

        // Player attack modal (PvP)
        playerAttackModal:   false,
        playerAttackTarget:  null,
        playerAttackTroops:  [],
        playerAttackLoading: false,
        selectedFormation:   -1,
        formations:          [null, null, null, null],

        // Rally modal
        rallyModal:      false,
        rallyTarget:     null,
        rallyTroops:     [],
        rallyMinutes:    30,
        rallyLoading:    false,
        rallyTimePicker: false,

        // Enemy profile modal
        enemyProfileModal:   false,
        enemyProfile:        null,
        enemyProfileLoading: false,

        // Own profile modal
        ownProfileModal: false,
        ownProfile:      null,

        // Emoji picker
        emojiModal:   false,
        selectedEmoji: null,
        emojiList: ['😂','👍','❤️','😡','💀','⚔️','🏆','🔥','💎','👑','🎯','💪','😈','🐉','⭐','😎','🤝','😴','🤬','🛡️'],

        // Skin changer
        skinModal:    false,
        skins:        [],
        equippedSkin: null,

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
            return (this.MONSTER_TYPES[type] ?? 'Monster') + ' Lv ' + level;
        },

        shrineColor(tier) {
            return { S: '#f59e0b', A: '#a78bfa', B: '#60a5fa', C: '#94a3b8' }[tier] ?? '#94a3b8';
        },

        marchEta(m, _tick) {
            const target = m.state === 'marching' ? m.arrival_time : m.return_time;
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
            // Load player info (for allianceId)
            try {
                const r = await fetch('/api/player/me');
                const j = await r.json();
                if (j.ok) {
                    this.myAllianceId = j.data.alliance_id ?? null;
                }
            } catch {}

            // Load formations
            this.loadFormations();

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
                myPlayerId: MY_PLAYER_ID,
                myAllianceId: this.myAllianceId,

                onTileInfo: (info) => {
                    this.tileInfo = info;
                    if (info?.occupant?.type === 'charm') {
                        this.loadCharmTroops();
                    } else {
                        this.charmTroops = [];
                    }
                },
                onHover: (x, y) => { this.hoverTile = x !== null ? `${x}, ${y}` : ''; },

                onCityClick: (entity, screenX, screenY) => {
                    this.hexPopupEntity = entity;
                    const isOwn = entity.player_id == MY_PLAYER_ID;
                    this.hexPopup      = isOwn ? 'own' : 'enemy';
                    const pName = entity.player_name ?? entity.player ?? '';
                    const tag   = entity.alliance_tag ? ` [${entity.alliance_tag}]` : '';
                    this.hexPopupTitle = pName + tag + (entity.level ? ` · Lv ${entity.level}` : '');

                    // Popup appears below the city — clamp so it stays within viewport.
                    // With transform:translateX(-50%) the X is the center of the popup.
                    const pw = 300, ph = 80;
                    this.hexPopupX = Math.min(Math.max(screenX, pw / 2 + 4), window.innerWidth  - pw / 2 - 4);
                    this.hexPopupY = Math.min(screenY + 8, window.innerHeight - ph - 4);
                },

                onMonsterClick: (entity) => {
                    const level = entity.monster_code % 100;
                    const typeId = Math.floor(entity.monster_code / 100);
                    this.attackTarget = {
                        x:    entity.x,
                        y:    entity.y,
                        name: (this.MONSTER_TYPES[typeId] ?? 'Monster') + ' Lv ' + level,
                    };
                    this.attackModal  = true;
                    this.loadAttackTroops(this.attackTroops);
                },
            });

            ConquerMap.setMyPlayerId(MY_PLAYER_ID);
            if (this.myAllianceId) ConquerMap.setMyAllianceId(this.myAllianceId);

            this.zoom = ConquerMap.currentZoom();
            this.pollMarches();
            setInterval(() => this.tick++, 1000);

            // Close hex popup on outside canvas click
            document.getElementById('map-canvas').addEventListener('mousedown', () => {
                if (this.hexPopup) this.hexPopup = null;
            }, { capture: false });
        },

        doZoomIn()   { ConquerMap.zoomIn();     this.zoom = ConquerMap.currentZoom(); },
        doZoomOut()  { ConquerMap.zoomOut();    this.zoom = ConquerMap.currentZoom(); },
        jumpToCity() { ConquerMap.jumpToCity(); },

        jumpToMarch(m) {
            const tx = m.state === 'returning' ? (this.myCity?.x ?? m.target_x) : m.target_x;
            const ty = m.state === 'returning' ? (this.myCity?.y ?? m.target_y) : m.target_y;
            ConquerMap.jumpTo(tx, ty);
        },

        // ── March polling ─────────────────────────────────────────────────────
        async pollMarches() {
            try {
                // Own marches → sidebar list
                const r = await fetch('/api/march/list');
                const j = await r.json();
                if (j.ok) {
                    const prev = this.marches;
                    this.marches = j.data.marches;
                    if (j.data.marches.length < prev.length || j.data.marches.some((m, i) => m.state !== (prev[i]?.state))) {
                        ConquerMap.refreshEntities();
                    }
                }
            } catch {}

            try {
                // All public marches (types 5+7) → map overlay
                const r2 = await fetch('/api/map/marches');
                const j2 = await r2.json();
                if (j2.ok) ConquerMap.setMarches(j2.data.marches);
            } catch {}

            setTimeout(() => this.pollMarches(), 5000);
        },

        // ── Troop loading (shared) ────────────────────────────────────────────
        async loadAttackTroops(targetArray) {
            // targetArray is a reference — we'll populate this.attackTroops or this.playerAttackTroops
            const isMonster = targetArray === this.attackTroops;
            if (isMonster) this.attackLoading = true;
            else           this.playerAttackLoading = true;

            try {
                const r = await fetch('/api/troops/list');
                const j = await r.json();
                if (j.ok) {
                    const result = j.data.definitions
                        .filter(d => (j.data.troops[d.code] ?? 0) > 0)
                        .map(d => ({
                            code:      d.code,
                            name:      d.name,
                            tier:      d.tier ?? 1,
                            available: j.data.troops[d.code] ?? 0,
                            toSend:    0,
                        }));
                    if (isMonster) this.attackTroops = result;
                    else           this.playerAttackTroops = result;
                }
            } catch {}

            if (isMonster) this.attackLoading = false;
            else           this.playerAttackLoading = false;
        },

        // ── Monster attack ────────────────────────────────────────────────────
        async openAttackModal() {
            if (!this.tileInfo?.occupant) return;
            const occ = this.tileInfo.occupant;
            this.attackTarget  = { x: this.tileInfo.x, y: this.tileInfo.y, name: occ.name + ' Lv ' + occ.level };
            this.attackModal   = true;
            this.attackTroops  = [];
            this.loadAttackTroops(this.attackTroops);
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
                    this.showToast(j.message ?? j.error ?? 'Fehler', 'err');
                }
            } catch { this.showToast('Netzwerkfehler', 'err'); }
            this.attackLoading = false;
        },

        // ── Player attack (PvP) ───────────────────────────────────────────────
        async openPlayerAttack(entity) {
            this.playerAttackTarget = entity;
            this.playerAttackModal  = true;
            this.playerAttackTroops = [];
            this.selectedFormation  = -1;
            this.loadAttackTroops(this.playerAttackTroops);
        },

        async sendPlayerMarch() {
            const troops = {};
            let total = 0;
            for (const t of this.playerAttackTroops) {
                if (t.toSend > 0) { troops[t.code] = t.toSend; total += t.toSend; }
            }
            if (total === 0) return;
            this.playerAttackLoading = true;
            try {
                const r = await fetch('/api/march/dispatch-player', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                    body:    JSON.stringify({ target_x: this.playerAttackTarget.x, target_y: this.playerAttackTarget.y, troops }),
                });
                const j = await r.json();
                if (j.ok) {
                    this.playerAttackModal = false;
                    this.showToast('⚔ Angriff gestartet! (ID ' + j.data.march_id + ')', 'ok');
                    this.pollMarches();
                } else {
                    this.showToast(j.message ?? j.error ?? 'Fehler', 'err');
                }
            } catch { this.showToast('Netzwerkfehler', 'err'); }
            this.playerAttackLoading = false;
        },

        // ── Scout ─────────────────────────────────────────────────────────────
        async sendScout(entity) {
            try {
                const r = await fetch('/api/march/dispatch-scout', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                    body:    JSON.stringify({ target_x: entity.x, target_y: entity.y }),
                });
                const j = await r.json();
                if (j.ok) {
                    this.showToast('🔭 Scout gestartet!', 'ok');
                    this.pollMarches();
                } else {
                    this.showToast(j.message ?? j.error ?? 'Fehler', 'err');
                }
            } catch { this.showToast('Netzwerkfehler', 'err'); }
        },

        // ── Rally ─────────────────────────────────────────────────────────────
        async openRally(entity) {
            this.rallyTarget = entity;
            this.rallyModal  = true;
            this.rallyTroops = [];
            this.rallyLoading = true;
            try {
                const r = await fetch('/api/troops/list');
                const j = await r.json();
                if (j.ok) {
                    this.rallyTroops = j.data.definitions
                        .filter(d => (j.data.troops[d.code] ?? 0) > 0)
                        .map(d => ({ code: d.code, name: d.name, available: j.data.troops[d.code] ?? 0, toSend: 0 }));
                }
            } catch {}
            this.rallyLoading = false;
        },

        async startRally() {
            const troops = {};
            let total = 0;
            for (const t of this.rallyTroops) {
                if (t.toSend > 0) { troops[t.code] = t.toSend; total += t.toSend; }
            }
            if (total === 0) return;
            this.rallyLoading = true;
            try {
                const r = await fetch('/api/rally/start', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                    body:    JSON.stringify({
                        target_player_id: this.rallyTarget.player_id,
                        target_x: this.rallyTarget.x,
                        target_y: this.rallyTarget.y,
                        rally_minutes: this.rallyMinutes,
                        troops,
                    }),
                });
                const j = await r.json();
                if (j.ok) {
                    this.rallyModal = false;
                    this.showToast('🏳 Rally gestartet! (ID ' + j.data.rally_id + ')', 'ok');
                    ConquerMap.refreshEntities();
                } else {
                    this.showToast(j.message ?? j.error ?? 'Fehler', 'err');
                }
            } catch { this.showToast('Netzwerkfehler', 'err'); }
            this.rallyLoading = false;
        },

        // ── Formations ────────────────────────────────────────────────────────
        async loadFormations() {
            try {
                const r = await fetch('/api/player/formations');
                const j = await r.json();
                if (j.ok) {
                    this.formations = [null, null, null, null];
                    for (const f of (j.data.formations ?? [])) {
                        const slot = (f.slot ?? 1) - 1;
                        if (slot >= 0 && slot < 4) this.formations[slot] = f.troops ?? {};
                    }
                }
            } catch {}
        },

        async saveFormation(slotIdx, sourceTroops) {
            const troops = {};
            for (const t of sourceTroops) {
                if (t.toSend > 0) troops[t.code] = t.toSend;
            }
            try {
                const r = await fetch('/api/player/formations/' + (slotIdx + 1), {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                    body:    JSON.stringify({ troops }),
                });
                const j = await r.json();
                if (j.ok) {
                    this.formations[slotIdx] = troops;
                    this.showToast('✓ Formation ' + (slotIdx + 1) + ' gespeichert', 'ok');
                } else {
                    this.showToast(j.error ?? 'Fehler', 'err');
                }
            } catch { this.showToast('Netzwerkfehler', 'err'); }
        },

        applyFormation(slotIdx) {
            const f = this.formations[slotIdx];
            if (!f) return;
            this.selectedFormation = slotIdx;
            const troops = this.attackModal ? this.attackTroops : this.playerAttackTroops;
            for (const t of troops) {
                t.toSend = f[t.code] ?? 0;
                if (t.toSend > t.available) t.toSend = t.available;
            }
        },

        // ── Enemy profile ─────────────────────────────────────────────────────
        async openEnemyProfile(entity) {
            this.enemyProfileModal   = true;
            this.enemyProfile        = null;
            this.enemyProfileLoading = true;
            try {
                const r = await fetch('/api/player/profile/' + entity.player_id);
                const j = await r.json();
                if (j.ok) this.enemyProfile = j.data;
            } catch {}
            this.enemyProfileLoading = false;
        },

        // ── Own profile ───────────────────────────────────────────────────────
        async openOwnProfile() {
            this.ownProfileModal = true;
            try {
                const r = await fetch('/api/player/me');
                const j = await r.json();
                if (j.ok) this.ownProfile = j.data;
            } catch {}
        },

        // ── Emoji ─────────────────────────────────────────────────────────────
        openEmojiPicker() {
            this.selectedEmoji = null;
            this.emojiModal    = true;
        },

        async sendEmoji() {
            if (!this.selectedEmoji) return;
            try {
                const r = await fetch('/api/player/emoji', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                    body:    JSON.stringify({ emoji_code: this.selectedEmoji }),
                });
                const j = await r.json();
                if (j.ok) {
                    this.emojiModal = false;
                    this.showToast(this.selectedEmoji + ' gesendet!', 'ok');
                    ConquerMap.refreshEntities();
                } else {
                    this.showToast(j.error ?? 'Fehler', 'err');
                }
            } catch { this.showToast('Netzwerkfehler', 'err'); }
        },

        // ── Skin changer ──────────────────────────────────────────────────────
        async openSkinModal() {
            this.skinModal = true;
            try {
                const r = await fetch('/api/player/skins');
                const j = await r.json();
                if (j.ok) {
                    this.skins        = j.data.skins ?? [];
                    this.equippedSkin = this.skins.find(s => s.is_equipped)?.skin_code ?? null;
                }
            } catch {}
        },

        async equipSkin(code) {
            try {
                const r = await fetch('/api/player/skin/equip', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                    body:    JSON.stringify({ skin_code: code }),
                });
                const j = await r.json();
                if (j.ok) {
                    this.equippedSkin = code || null;
                    for (const s of this.skins) s.is_equipped = (s.skin_code === code);
                    this.showToast('✓ Skin geändert', 'ok');
                } else {
                    this.showToast(j.error ?? 'Fehler', 'err');
                }
            } catch { this.showToast('Netzwerkfehler', 'err'); }
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
                        .map(d => ({ code: d.code, name: d.name, count: j.data.troops[d.code] ?? 0, toSend: 1 }));
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
            } catch { this.showToast('Netzwerkfehler', 'err'); }
            this.charmCollecting = false;
        },

        // ── Field object (gather march) ───────────────────────────────────────
        fieldObjectName(name) {
            return { farm: 'Bauernhof', lumber: 'Holzfäller', quarry: 'Steinbruch', gold_mine: 'Goldmine', gem_node: 'Edelsteinader' }[name] || name || '—';
        },
        async dispatchGather() {
            if (!this.tileInfo) return;
            try {
                const r = await fetch('/api/march/dispatch-gather', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                    body:    JSON.stringify({
                        target_x:    this.tileInfo.x,
                        target_y:    this.tileInfo.y,
                        troop_count: 1000,
                    }),
                });
                const j = await r.json();
                if (j.ok) {
                    this.showToast('Marsch gestartet!', 'ok');
                    this.tileInfo = null;
                    this.pollMarches();
                } else {
                    this.showToast(j.message ?? j.error ?? 'Fehler', 'err');
                }
            } catch { this.showToast('Netzwerkfehler', 'err'); }
        },

        // ── Toast ─────────────────────────────────────────────────────────────
        showToast(msg, type = 'ok') {
            const el = document.getElementById('map-toast');
            el.textContent   = msg;
            el.className     = type;
            el.style.display = 'block';
            setTimeout(() => { el.style.display = 'none'; }, 4000);
        },
    };
}
</script>
</body>
</html>
