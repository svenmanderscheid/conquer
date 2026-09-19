<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Game/City/City3dImportMap.php';
$base = htmlspecialchars(APP_BASE, ENT_QUOTES);
$embedded = isset($_GET['embed']) && $_GET['embed'] === '1';
$city3dImportMap = \Conquer\Game\City\City3dImportMap::build(dirname(__DIR__), APP_BASE);
$city3dImportMapJson = \Conquer\Game\City\City3dImportMap::json(['imports' => $city3dImportMap['imports']]);
header('Cache-Control: no-store');
?>
<!doctype html>
<html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#173c39"><title>Conquer · Deine 3D-Stadt</title><link rel="icon" href="data:,"><link rel="stylesheet" href="<?= $base ?>/assets/css/fantasy-fonts.css?v=<?= filemtime(__DIR__ . '/../assets/css/fantasy-fonts.css') ?>"><link rel="stylesheet" href="<?= $base ?>/assets/city3d/style.css"><link rel="stylesheet" href="<?= $base ?>/assets/city3d/play.css?v=commands2"><link rel="stylesheet" href="<?= $base ?>/assets/city3d/hud.css?v=<?= filemtime(__DIR__ . '/../assets/city3d/hud.css') ?>">
<?php if ($embedded): ?><link rel="stylesheet" href="<?= $base ?>/assets/css/embedded-city-hud.css?v=<?= filemtime(__DIR__ . '/../assets/css/embedded-city-hud.css') ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= $base ?>/assets/css/village-theme.css?v=<?= filemtime(__DIR__ . '/../assets/css/village-theme.css') ?>">
</head>
<body class="play-city<?= $embedded ? ' embedded' : '' ?>">
<main id="world" aria-label="Deine Stadt in 3D. Tippe auf die Festung, um sie auszubauen."></main>
<header class="realm-hud"><button id="profile-open" aria-label="Spielerprofil öffnen"><span class="portrait" aria-hidden="true">♜</span><span class="profile-caption"><strong><?= htmlspecialchars($session['username'], ENT_QUOTES) ?></strong><small id="hud-power">Deine Stadt wird geladen …</small></span></button><span class="realm-marker">CONQUER<small>DEINE STADT</small></span></header>
<div id="resource-bar" aria-label="Deine Ressourcen"><span>Ressourcen werden geladen …</span></div>
<div id="loading" role="status">Deine Stadt entsteht …</div>
<aside class="controls" aria-label="Ansicht steuern"><button id="zoomIn" aria-label="Vergrößern">+</button><button id="zoomOut" aria-label="Verkleinern">−</button><button id="reset" aria-label="Ansicht zurücksetzen">⌂</button><button id="pause" aria-label="Animation pausieren" aria-pressed="false">Ⅱ</button></aside>
<section class="card" hidden><div class="eyebrow" id="castle-level">DEINE FESTUNG</div><h2 id="name">Deine Stadt wächst.</h2><p id="description">Tippe auf die Festung und starte ihren ersten Ausbau.</p>
<p id="build-status" role="status">Spielstand wird geladen …</p><progress id="build-progress" max="1" value="0" hidden aria-label="Baufortschritt"></progress>
<div class="choices"><button data-building="keep">Festung ausbauen</button><button id="detail">Nahansicht</button><button id="cityMode" hidden>Kleine Szene</button></div>
</section>
<div id="building-labels" aria-label="Gebäude und Aufträge">
<?php foreach (['castle'=>'Festung','academy'=>'Akademie','barrack'=>'Kaserne','archery_range'=>'Schützenlager','stable'=>'Reiterhof','hospital'=>'Krankenhaus','storage'=>'Lagerhaus','treasure_house'=>'Schatzkammer','hall_of_alliance'=>'Allianzhalle','trading_post'=>'Handelsposten','farm'=>'Bauernhof','lumber_camp'=>'Holzfällerlager','quarry'=>'Steinbruch','gold_mine'=>'Goldmine','wall'=>'Stadtmauer','watch_tower'=>'Wachturm'] as $code=>$label): ?>
<article class="building-label" id="label-<?= $code ?>" data-anchor="<?= $code ?>"><button class="building-name" data-building-code="<?= $code ?>" aria-expanded="false"><?= $label ?> <span class="building-level">…</span></button><div class="activity-list"></div></article>
<?php endforeach ?>
</div>
<footer class="city-footer"><span id="connection" role="status">Verbinde mit deiner Stadt …</span><button id="retry" hidden>Erneut verbinden</button><button id="logout">Abmelden</button><button id="metricsToggle" aria-expanded="false">Messwerte</button><div id="metrics" hidden></div></footer>
<nav class="realm-nav" aria-label="Hauptnavigation">
<?php
$menu = [
 'tasks'=>['Aufgaben','M5 3h14v18H5z M8 8h8 M8 12h8 M8 16h5'],
 'inventory'=>['Inventar','M3 9h18v11H3z M3 9a9 9 0 0 1 18 0 M10 11h4v5h-4z'],
 'reports'=>['Nachrichten','M3 5h18v14H3z M3 5l9 8 9-8'],
 'alliance'=>['Allianz','M12 2l9 4v7c0 5-9 9-9 9s-9-4-9-9V6z M12 6v11 M7 10h10'],
 'world'=>['Welt','M2 5l6-2 8 2 6-2v16l-6 2-8-2-6 2z M8 3v16 M16 5v16'],
];
foreach ($menu as $key=>[$label,$path]): ?>
<?php if (in_array($key,['reports','world'],true)): ?><a href="<?= $base ?>/city#<?= $key ?>"><?php else: ?><button data-hud-panel="<?= $key ?>"><?php endif ?>
<span class="nav-emblem"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="<?= $path ?>"/></svg></span><span><?= $label ?></span>
<?php if (in_array($key,['reports','world'],true)): ?></a><?php else: ?></button><?php endif ?>
<?php endforeach ?>
</nav>
<dialog id="realm-panel" aria-labelledby="realm-panel-title"><div class="realm-panel-head"><h2 id="realm-panel-title"></h2><button id="realm-panel-close" aria-label="Menü schließen">×</button></div><div id="realm-panel-content"></div><div id="profile-tools" hidden></div></dialog>
<div id="building-command" hidden><div class="building-command-shell"><div class="building-command-head"><span><strong id="building-command-name">Gebäude</strong><small id="building-command-level"></small></span><button id="close-building-command" aria-label="Gebäudemenü schließen">×</button></div><div class="quick-actions"><button id="building-info" title="Details" aria-label="Details"><span class="action-disc">i</span><span>Details</span></button><button id="building-upgrade" title="Ausbau prüfen" aria-label="Ausbau prüfen"><span class="action-disc">↑</span><span>Ausbau</span></button><button id="building-function" title="Stadtübersicht" aria-label="Stadtübersicht"><span class="action-disc">♜</span><span>Stadtübersicht</span></button></div></div></div>
<section id="upgrade-dialog" class="building-actions" aria-labelledby="upgrade-title" hidden><div class="dialog-head"><div><h2 id="upgrade-title">Festung</h2></div><button id="close-upgrade" aria-label="Gebäudeinfos schließen">×</button></div>
<p id="upgrade-benefit"></p><p id="upgrade-duration"></p><div id="upgrade-costs" aria-label="Ausbaukosten"></div><div id="upgrade-requirements"></div><p id="upgrade-reason"></p><p id="action-message" role="status"></p><button id="start-upgrade" class="primary" disabled>Spielstand laden …</button><div id="prerequisite-actions"></div></section>
<script>window.CONQUER_PLAY = {world: <?= \Conquer\Game\World\WorldContext::id() ?>, base: <?= json_encode(APP_BASE, JSON_HEX_TAG | JSON_HEX_AMP) ?>, embedded: <?= $embedded ? 'true' : 'false' ?>, assetVersion: <?= json_encode($city3dImportMap['version'], JSON_HEX_TAG | JSON_HEX_AMP) ?>};window.CONQUER_EMBED=<?= $embedded ? 'true' : 'false' ?>;</script>
<script type="importmap"><?= $city3dImportMapJson ?></script>
<script type="module" src="<?= $base ?>/assets/city3d/scene.js?v=city3d-<?= $city3dImportMap['version'] ?>"></script><script type="module" src="<?= $base ?>/assets/city3d/play.js?v=city3d-<?= $city3dImportMap['version'] ?>"></script>
<script type="module" src="<?= $base ?>/assets/city3d/hud.js?v=city3d-<?= $city3dImportMap['version'] ?>"></script>
</body></html>


