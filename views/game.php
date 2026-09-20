<?php
declare(strict_types=1);
header('Cache-Control: private, no-store');
$base = htmlspecialchars(APP_BASE, ENT_QUOTES);
$trainingImportMap = \Conquer\Game\City\City3dImportMap::build(ROOT_DIR, APP_BASE);
?>
<!doctype html>
<html lang="<?= htmlspecialchars(\Conquer\Game\Locale::current(), ENT_QUOTES) ?>">
<head>
  <meta charset="utf-8">
  <script type="importmap"><?= \Conquer\Game\City\City3dImportMap::json(['imports' => $trainingImportMap['imports']]) ?></script>
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#5c4270">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/fantasy-fonts.css?v=<?= filemtime(ROOT_DIR.'/assets/css/fantasy-fonts.css') ?>">
  <title>Conquer · Gemeinsam gegen die Dunkelheit</title>
  <link rel="stylesheet" href="<?= $base ?>/assets/css/world-map.css?v=<?= filemtime(__DIR__ . '/../assets/css/world-map.css') ?>">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/game.css?v=style7">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/research-tree.css?v=<?= filemtime(__DIR__ . '/../assets/css/research-tree.css') ?>">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/march-panel.css?v=<?= filemtime(__DIR__ . '/../assets/css/march-panel.css') ?>">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/interface-polish.css?v=style7">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/inventory.css?v=style7">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/reward-dialog.css?v=<?= filemtime(__DIR__ . '/../assets/css/reward-dialog.css') ?>">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/game-theme.css?v=style7">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/world-atlas.css?v=style7">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/world-encounters.css?v=<?= filemtime(__DIR__ . '/../assets/css/world-encounters.css') ?>">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/mobile-shell.css?v=<?= filemtime(__DIR__ . '/../assets/css/mobile-shell.css') ?>">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/map-overlay.css?v=<?= filemtime(__DIR__ . '/../assets/css/map-overlay.css') ?>">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/game-popups.css?v=style7">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/popup-surfaces.css?v=style7">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/popup-skin.css?v=style7">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/window-layout.css?v=quest-rewards2">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/march-command.css?v=command2">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/castle-skins.css?v=collection4">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/march-skins.css?v=<?= filemtime(__DIR__ . '/../assets/css/march-skins.css') ?>">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/name-frames.css?v=<?= filemtime(__DIR__ . '/../assets/css/name-frames.css') ?>">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/theme-bundles.css?v=<?= filemtime(__DIR__ . '/../assets/css/theme-bundles.css') ?>">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/inventory-reference.css?v=<?= filemtime(__DIR__ . '/../assets/css/inventory-reference.css') ?>">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/world-zones.css?v=<?= filemtime(__DIR__ . '/../assets/css/world-zones.css') ?>">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/world-shrines.css?v=<?= filemtime(__DIR__ . '/../assets/css/world-shrines.css') ?>">
<link rel="stylesheet" href="<?= $base ?>/assets/css/mailbox-panel.css?v=<?= filemtime(ROOT_DIR.'/assets/css/mailbox-panel.css') ?>"><link rel="stylesheet" href="<?= $base ?>/assets/css/community-panel.css?v=features1"><link rel="stylesheet" href="<?= $base ?>/assets/css/defense-panel.css?v=features1"><link rel="stylesheet" href="<?= $base ?>/assets/css/progression-panel.css?v=features1"><link rel="stylesheet" href="<?= $base ?>/assets/css/lord-talents.css?v=<?= filemtime(ROOT_DIR.'/assets/css/lord-talents.css') ?>"><link rel="manifest" href="<?= htmlspecialchars(APP_BASE,ENT_QUOTES) ?>/manifest.php"><link rel="apple-touch-icon" href="<?= htmlspecialchars(APP_BASE,ENT_QUOTES) ?>/assets/icons/conquer-192.png"><link rel="stylesheet" href="<?= htmlspecialchars(APP_BASE,ENT_QUOTES) ?>/assets/css/localization.css?v=<?= filemtime(ROOT_DIR.'/assets/css/localization.css') ?>"><script><?= \Conquer\Game\Locale::bootstrap() ?></script><script src="<?= htmlspecialchars(APP_BASE,ENT_QUOTES) ?>/assets/js/localization.js?v=<?= filemtime(ROOT_DIR.'/assets/js/localization.js') ?>" defer></script>
  <link rel="stylesheet" href="<?= $base ?>/assets/css/world-chat.css?v=<?= filemtime(__DIR__ . '/../assets/css/world-chat.css') ?>">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/trading-panel.css?v=<?= filemtime(__DIR__ . '/../assets/css/trading-panel.css') ?>">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/treasure-panel.css?v=<?= filemtime(__DIR__ . '/../assets/css/treasure-panel.css') ?>">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/training-panel.css?v=<?= filemtime(ROOT_DIR.'/assets/css/training-panel.css') ?>">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/training-hud.css?v=<?= filemtime(__DIR__ . '/../assets/css/training-hud.css') ?>">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/vip-panel.css?v=<?= filemtime(__DIR__ . '/../assets/css/vip-panel.css') ?>">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/game-overlay.css?v=<?= filemtime(__DIR__ . '/../assets/css/game-overlay.css') ?>">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/profile-reference.css?v=<?= filemtime(__DIR__ . '/../assets/css/profile-reference.css') ?>">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/bug-reports.css?v=<?= filemtime(__DIR__ . '/../assets/css/bug-reports.css') ?>">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/mobile-refinements.css?v=<?= filemtime(__DIR__ . '/../assets/css/mobile-refinements.css') ?>">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/dungeon-panel.css?v=<?= filemtime(__DIR__ . '/../assets/css/dungeon-panel.css') ?>">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/dungeon-planner.css?v=<?= filemtime(__DIR__ . '/../assets/css/dungeon-planner.css') ?>">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/land-panel.css?v=<?= filemtime(__DIR__ . '/../assets/css/land-panel.css') ?>">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/hospital-panel.css?v=<?= filemtime(__DIR__ . '/../assets/css/hospital-panel.css') ?>">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/combat-report.css?v=<?= filemtime(__DIR__ . '/../assets/css/combat-report.css') ?>">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/scout-report.css?v=<?= filemtime(__DIR__ . '/../assets/css/scout-report.css') ?>">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/village-theme.css?v=<?= filemtime(__DIR__ . '/../assets/css/village-theme.css') ?>">
</head>
<body class="mobile-game">
<a class="skip-link" href="#main">Zum Spielinhalt</a>
<header class="topbar">
  <div class="hud-profile">
  <button class="player-button" id="account-button" aria-label="Mein Profil öffnen"><span class="avatar"><img src="<?= $base ?>/assets/art/knight.png" alt=""></span><span id="player-hud-name"><?= htmlspecialchars($session['username']) ?></span><span class="profile-chevron">⌄</span></button>
  <div class="hud-power" aria-label="Königreichsmacht"><img class="hud-power-icon" src="<?= $base ?>/assets/art/ui-hud/power.svg" alt=""><strong id="hud-power-value">…</strong></div>
  <div class="hud-ranks"><button id="hud-vip-button" class="hud-rank hud-vip" data-action="vip-open" aria-label="VIP öffnen"><img class="hud-status-icon" src="<?= $base ?>/assets/art/ui-hud/vip.svg" alt=""><span class="hud-vip-copy"><small>VIP</small><b data-vip-level>0</b></span><span data-vip-claim class="hud-notification" hidden>!</span></button><button type="button" id="lord-talent-button" class="hud-rank hud-hunter" aria-label="Hunter-Talente öffnen"><img class="hud-status-icon" src="<?= $base ?>/assets/art/ui-hud/hunter-level.svg" alt=""><b id="lord-hud-level">1</b><span id="hud-hunter-xp" class="hud-hunter-xp">… XP</span><span class="hud-hunter-track" role="progressbar" aria-label="Hunter-Erfahrung" aria-valuemin="0" aria-valuemax="1" aria-valuenow="0"><i id="hud-hunter-fill"></i></span></button></div>
  <button id="hud-energy" class="hud-energy" data-action="tab" data-id="profile" aria-label="Aktionspunkte ansehen"><img class="hud-status-icon" src="<?= $base ?>/assets/art/ui-hud/action-points.svg" alt=""><span class="hud-meter-body"><span class="hud-meter-copy"><strong>… / …</strong></span><span class="hud-meter-track" role="progressbar" aria-label="Aktionspunkte" aria-valuemin="0" aria-valuemax="200" aria-valuenow="0"><i id="hud-energy-fill"></i></span></span></button>
  <div class="hud-effects" aria-label="Aktive Effekte">
    <button type="button" id="hud-bonuses" class="hud-effect-button" aria-label="Aktive Boni anzeigen" aria-controls="active-effects-drawer" aria-expanded="false" hidden><svg viewBox="0 0 32 32" aria-hidden="true"><path d="M16 3 3 16h8v13h10V16h8Z"/><path class="effect-arrow-line" d="M13 20h6m-6 4h6"/></svg></button>
    <button type="button" id="hud-debuffs" class="hud-effect-button is-debuff" aria-label="Aktive Debuffs anzeigen" aria-controls="active-effects-drawer" aria-expanded="false" hidden><svg viewBox="0 0 32 32" aria-hidden="true"><path d="m16 29 13-13h-8V3H11v13H3Z"/><path class="effect-arrow-line" d="M13 8h6m-6 4h6"/></svg></button>
    <section id="active-effects-drawer" class="active-effects-drawer" aria-labelledby="active-effects-title" hidden>
      <header><h2 id="active-effects-title">Aktive Boni</h2><small>Zeitlich begrenzte Effekte</small></header>
      <ul class="active-effects-list" aria-label="Aktive Boni"></ul>
    </section>
  </div>
  </div>
  <div id="resources" class="resources" aria-label="Ressourcen"><span class="muted">Ressourcen werden geladen …</span></div>
  <button id="hud-gems" class="hud-gems" data-action="tab" data-id="inventory" aria-label="Edelsteine und Inventar öffnen"><img src="<?= $base ?>/assets/art/items/gems.svg" alt=""><strong>…</strong><span>Edelsteine</span></button>
</header>
<div class="game-layout">
  <main id="main" tabindex="-1">
    <section id="content" aria-label="Spielinhalt" aria-live="off"><div class="loading"><span class="loading-emblem">♜</span><h2>Dein Königreich erwacht</h2><p>Die Tore werden geöffnet …</p></div></section>
  </main>
</div>
<div id="scene-transition" class="scene-transition" aria-hidden="true">
  <span class="scene-transition-emblem"><img alt=""></span>
  <small class="scene-transition-label"></small>
</div>
<nav id="hud-left-tools" class="hud-edge-tools hud-left-tools" aria-label="Dorf und Truppen">
  <button id="hud-healing" class="hud-edge-button hud-job hud-healing" data-job-state="loading" data-action="army-hospital" aria-label="Hospital wird geladen" hidden><span class="hud-edge-art"><img src="<?= $base ?>/assets/art/items/healing.svg" alt=""><span class="hud-heal-now" aria-hidden="true">✚</span></span><span class="hud-job-copy"><span class="hud-edge-label">Heilung</span><small class="hud-job-state">Lädt …</small><strong class="hud-job-time">Bitte warten</strong></span><span class="hud-job-track" aria-hidden="true"><span class="hud-job-progress"></span></span></button>
  <button id="hud-build" class="hud-edge-button hud-job" data-job-state="loading" data-city-only data-action="buildings" aria-label="Erste Bauschleife öffnen"><span class="hud-edge-art"><img src="<?= $base ?>/assets/art/items/hammer.svg" alt=""></span><span class="hud-job-copy"><span class="hud-edge-label">Bauen I</span><small id="hud-build-status" class="hud-job-state">Lädt …</small><strong class="hud-job-time">Bitte warten</strong></span><span class="hud-job-track" aria-hidden="true"><span class="hud-job-progress"></span></span></button>
  <button id="hud-build-second" class="hud-edge-button hud-job hud-build-second is-locked" data-job-state="loading" data-city-only data-action="vip-open" aria-label="Zweite Bauschleife wird mit VIP 4 freigeschaltet"><span class="hud-edge-art"><img src="<?= $base ?>/assets/art/items/hammer.svg" alt=""></span><span class="hud-job-copy"><span class="hud-edge-label">Bauen II</span><small id="hud-build-second-status" class="hud-job-state">Lädt …</small><strong class="hud-job-time">Bitte warten</strong></span><span class="hud-job-track" aria-hidden="true"><span class="hud-job-progress"></span></span></button>
  <button id="hud-research" class="hud-edge-button hud-job" data-job-state="loading" data-city-only data-action="tab" data-id="research" aria-label="Forschung öffnen"><span class="hud-edge-art"><img src="<?= $base ?>/assets/art/items/research.svg" alt=""></span><span class="hud-job-copy"><span class="hud-edge-label">Forschung</span><small id="hud-research-status" class="hud-job-state">Lädt …</small><strong class="hud-job-time">Bitte warten</strong></span><span class="hud-job-track" aria-hidden="true"><span class="hud-job-progress"></span></span></button>
  <button id="hud-marches" class="hud-edge-button" data-world-only data-action="hud-marches" aria-label="Truppenmärsche öffnen"><span class="hud-edge-art"><img src="<?= $base ?>/assets/art/items/army.svg" alt=""></span><span class="hud-edge-label">Truppen</span><small id="hud-march-status" class="hud-edge-status">0 unterwegs</small></button>
</nav>
<nav class="hud-edge-tools hud-right-tools" aria-label="Spielmenü und Ereignisse">
  <button id="hud-report" class="hud-edge-button hud-report-button" data-action="bug-report-open" aria-label="Bug oder Idee melden" aria-haspopup="dialog"><span class="hud-edge-art" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 8h12v7a6 6 0 0 1-12 0V8Zm3 0V5h6v3M2 11h4m12 0h4M2 17h4m12 0h4M7 4 5 2m12 2 2-2m-7 8v10"/></svg></span><span class="hud-edge-label">Melden</span></button>
  <button id="hud-menu" class="hud-edge-button" data-action="menu-more" aria-label="Spielmenü öffnen" aria-haspopup="dialog"><span class="hud-edge-art"><img src="<?= $base ?>/assets/art/hud/menu.svg" alt=""></span><span class="hud-edge-label">Menü</span></button>
  <button class="hud-edge-button" data-action="tab" data-id="events" aria-label="Weltereignisse öffnen"><span class="hud-edge-art"><img src="<?= $base ?>/assets/art/hud/expeditions.svg" alt=""></span><span class="hud-edge-label">Events</span></button>
</nav>
<aside id="world-chat" class="world-chat" aria-label="Welt- und Allianzchat" hidden></aside>
<nav id="navigation" class="game-dock" aria-label="Spielbereiche"></nav>
<span class="save-state" id="save-state" role="status">Verbinde mit deinem Königreich …</span>
<dialog id="panel-dialog" class="game-panel" aria-labelledby="page-title">
  <div class="page-heading"><span class="panel-emblem" id="panel-emblem" aria-hidden="true"></span><h1 id="page-title" tabindex="-1">Dein Königreich</h1><button type="button" class="panel-report-button" data-action="bug-report-open" aria-label="Bug oder Idee in diesem Bereich melden" title="Bug oder Idee melden"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 8h12v7a6 6 0 0 1-12 0V8Zm3 0V5h6v3M2 11h4m12 0h4M2 17h4m12 0h4M7 4 5 2m12 2 2-2m-7 8v10"/></svg></button><button type="button" id="inventory-overview-button" aria-label="Übersicht: Rohstoffe und Beschleuniger" title="Inventarübersicht" aria-haspopup="dialog" aria-controls="game-dialog"><svg viewBox="0 0 32 32" aria-hidden="true"><path d="M6 27V19m7 8V15m7 12V20m7 7V10M5 13l8-7 7 6L28 3m-7 0h7v7"/></svg></button><button class="panel-back panel-close" data-action="return-playfield" aria-label="Bereich schließen">×</button></div>
  <section id="panel-content" class="panel-content" aria-label="Spielbereich"></section>
</dialog>
<dialog id="game-dialog" aria-label="Spielfenster"><button type="button" class="dialog-report-button" data-action="bug-report-open" aria-label="Bug oder Idee in diesem Fenster melden" title="Bug oder Idee melden"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 8h12v7a6 6 0 0 1-12 0V8Zm3 0V5h6v3M2 11h4m12 0h4M2 17h4m12 0h4M7 4 5 2m12 2 2-2m-7 8v10"/></svg></button><button class="dialog-close" aria-label="Fenster schließen">×</button><div id="dialog-content"></div></dialog>
<div id="toast" role="status" aria-live="polite"></div>
<script>window.CONQUER_ITEM_ART_VERSION = <?= max(filemtime(__DIR__ . '/../data/items.json'), ...array_map('filemtime', array_merge(glob(__DIR__ . '/../assets/art/items/*.svg'), glob(__DIR__ . '/../assets/art/items/backpack/*.svg'), glob(__DIR__ . '/../assets/art/items/reference/*.png')))) ?>;window.CONQUER_WORLD = <?= \Conquer\Game\World\WorldContext::id() ?>;window.CONQUER_BASE = <?= json_encode(APP_BASE, JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= $base ?>/assets/js/browser-compat.js?v=<?= filemtime(__DIR__ . '/../assets/js/browser-compat.js') ?>" defer></script>
<script src="<?= $base ?>/assets/js/reward-dialog.js?v=<?= filemtime(__DIR__ . '/../assets/js/reward-dialog.js') ?>" defer></script>
<script src="<?= $base ?>/assets/js/castle-skins.js?v=<?= filemtime(__DIR__ . '/../assets/js/castle-skins.js') ?>" defer></script>
<script src="<?= $base ?>/assets/js/march-skins.js?v=<?= filemtime(__DIR__ . '/../assets/js/march-skins.js') ?>" defer></script>
<script src="<?= $base ?>/assets/js/name-frames.js?v=<?= filemtime(__DIR__ . '/../assets/js/name-frames.js') ?>" defer></script>
<script src="<?= $base ?>/assets/js/theme-bundles.js?v=<?= filemtime(__DIR__ . '/../assets/js/theme-bundles.js') ?>" defer></script>
<script>window.ConquerTerrainData=<?= json_encode(\Conquer\Game\Map\WorldTerrain::definition(), JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;</script>
<script src="<?= $base ?>/assets/js/world-landscape.js?v=<?= filemtime(__DIR__ . '/../assets/js/world-landscape.js') ?>" defer></script>
<script>window.CONQUER_WORLD_LIFE_VERSION=<?= max([0,...array_map('filemtime',glob(__DIR__.'/../assets/art/map/life-*.{png,webp}',GLOB_BRACE))]) ?>;</script>
<script src="<?= $base ?>/assets/js/world-encounters.js?v=<?= filemtime(__DIR__ . '/../assets/js/world-encounters.js') ?>" defer></script>
<script src="<?= $base ?>/assets/js/world-march-hud.js?v=<?= filemtime(__DIR__ . '/../assets/js/world-march-hud.js') ?>" defer></script>
<script src="<?= $base ?>/assets/js/march-effects.js?v=<?= filemtime(__DIR__ . '/../assets/js/march-effects.js') ?>" defer></script>
<script src="<?= $base ?>/assets/js/world-map.js?v=<?= filemtime(__DIR__ . '/../assets/js/world-map.js') ?>" defer></script>
<script src="<?= $base ?>/assets/js/land-panel.js?v=<?= filemtime(__DIR__ . '/../assets/js/land-panel.js') ?>" defer></script>
<script src="<?= $base ?>/assets/js/hospital-panel.js?v=<?= filemtime(__DIR__ . '/../assets/js/hospital-panel.js') ?>" defer></script>
<script src="<?= $base ?>/assets/js/mvp-panels.js?v=<?= filemtime(__DIR__ . '/../assets/js/mvp-panels.js') ?>" defer></script>
<script src="<?= $base ?>/assets/js/inventory-overview.js?v=<?= filemtime(__DIR__ . '/../assets/js/inventory-overview.js') ?>" defer></script>
<script src="<?= $base ?>/assets/js/research-tree.js?v=<?= filemtime(__DIR__ . '/../assets/js/research-tree.js') ?>" defer></script>
<script src="<?= $base ?>/assets/js/battle-preview.js?v=<?= filemtime(__DIR__ . '/../assets/js/battle-preview.js') ?>" defer></script>
<script src="<?= $base ?>/assets/js/march-panel.js?v=<?= filemtime(__DIR__ . '/../assets/js/march-panel.js') ?>" defer></script>
<script src="<?= $base ?>/assets/js/report-share.js?v=<?= filemtime(__DIR__ . '/../assets/js/report-share.js') ?>" defer></script>
<script src="<?= $base ?>/assets/js/monster-report.js?v=<?= filemtime(__DIR__ . '/../assets/js/monster-report.js') ?>" defer></script>
<script src="<?= $base ?>/assets/js/rally-panel.js?v=style7" defer></script>
<script src="<?= $base ?>/assets/js/village-menu.js?v=<?= filemtime(__DIR__ . '/../assets/js/village-menu.js') ?>" defer></script>
<script src="<?= $base ?>/assets/js/congress-panel.js?v=shrines1" defer></script>
<script src="<?= $base ?>/assets/js/mailbox-panel.js?v=<?= filemtime(ROOT_DIR.'/assets/js/mailbox-panel.js') ?>" defer></script>
<script src="<?= $base ?>/assets/js/scout-report.js?v=<?= filemtime(ROOT_DIR.'/assets/js/scout-report.js') ?>" defer></script>
<script src="<?= $base ?>/assets/js/community-panel.js?v=<?= filemtime(ROOT_DIR.'/assets/js/community-panel.js') ?>" defer></script>
<script src="<?= $base ?>/assets/js/defense-panel.js?v=features1" defer></script>
<script src="<?= $base ?>/assets/js/lord-talents.js?v=<?= filemtime(ROOT_DIR.'/assets/js/lord-talents.js') ?>" defer></script>
<script src="<?= $base ?>/assets/js/progression-panel.js?v=<?= filemtime(ROOT_DIR.'/assets/js/progression-panel.js') ?>" defer></script>
<script src="<?= $base ?>/assets/js/world-panel.js?v=worlds1" defer></script>
<script src="<?= $base ?>/assets/js/world-chat.js?v=<?= filemtime(__DIR__ . '/../assets/js/world-chat.js') ?>" defer></script>
<script src="<?= $base ?>/assets/js/trading-panel.js?v=<?= filemtime(__DIR__ . '/../assets/js/trading-panel.js') ?>" defer></script>
<script src="<?= $base ?>/assets/js/treasure-panel.js?v=<?= filemtime(__DIR__ . '/../assets/js/treasure-panel.js') ?>" defer></script>
<script src="<?= $base ?>/assets/js/training-panel.js?v=<?= filemtime(ROOT_DIR.'/assets/js/training-panel.js') ?>" defer></script>
<script src="<?= $base ?>/assets/js/queue-speedups.js?v=<?= filemtime(ROOT_DIR.'/assets/js/queue-speedups.js') ?>" defer></script>
<script src="<?= $base ?>/assets/js/training-hud.js?v=<?= filemtime(__DIR__ . '/../assets/js/training-hud.js') ?>" defer></script>
<script src="<?= $base ?>/assets/js/vip-panel.js?v=<?= filemtime(__DIR__ . '/../assets/js/vip-panel.js') ?>" defer></script>
<script src="<?= $base ?>/assets/js/game-overlay.js?v=<?= filemtime(__DIR__ . '/../assets/js/game-overlay.js') ?>" defer></script>
<script src="<?= $base ?>/assets/js/active-effects.js?v=<?= filemtime(__DIR__ . '/../assets/js/active-effects.js') ?>" defer></script>
<script src="<?= $base ?>/assets/js/dungeon-panel.js?v=<?= filemtime(__DIR__ . '/../assets/js/dungeon-panel.js') ?>" defer></script>
<script src="<?= $base ?>/assets/js/combat-report.js?v=<?= filemtime(__DIR__ . '/../assets/js/combat-report.js') ?>" defer></script>
<script src="<?= $base ?>/assets/js/beginner-guide.js?v=<?= filemtime(__DIR__ . '/../assets/js/beginner-guide.js') ?>" defer></script>
<script src="<?= $base ?>/assets/js/bug-reports.js?v=<?= filemtime(__DIR__ . '/../assets/js/bug-reports.js') ?>" defer></script>
<script src="<?= $base ?>/assets/js/command-receipts.js?v=<?= filemtime(ROOT_DIR.'/assets/js/command-receipts.js') ?>" defer></script>
<script src="<?= $base ?>/assets/js/app-polling.js?v=<?= filemtime(__DIR__ . '/../assets/js/app-polling.js') ?>" defer></script>
<script src="<?= $base ?>/assets/js/game-comfort.js?v=<?= filemtime(__DIR__ . '/../assets/js/game-comfort.js') ?>" defer></script>
<script src="<?= $base ?>/assets/js/game-audio.js?v=<?= filemtime(__DIR__ . '/../assets/js/game-audio.js') ?>" defer></script>
<script src="<?= $base ?>/assets/js/game.js?v=<?= filemtime(__DIR__ . '/../assets/js/game.js') ?>" defer></script>
</body>
</html>

