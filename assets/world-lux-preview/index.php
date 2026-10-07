<?php
declare(strict_types=1);
// Isolated, read-only world concept: no account, session or game API is loaded.
function worldPreviewVersion(string $path): string { return (string) filemtime(__DIR__ . '/' . $path); }
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#fff7e7">
  <title>Luxemburg als Spielwelt · Union of Kingdoms</title>
  <link rel="icon" href="../art/items/compass.svg" type="image/svg+xml">
  <link rel="stylesheet" href="../css/fantasy-fonts.css?v=<?= worldPreviewVersion('../css/fantasy-fonts.css') ?>">
  <link rel="stylesheet" href="world.css?v=<?= worldPreviewVersion('world.css') ?>">
  <link rel="stylesheet" href="../css/village-theme.css?v=<?= worldPreviewVersion('../css/village-theme.css') ?>">
  <script type="module" src="world.js?v=<?= worldPreviewVersion('world.js') ?>"></script>
</head>
<body class="lux-world-preview">
  <header class="world-header">
    <div class="brand"><span>Union of Kingdoms</span><small>A new Era begins</small></div>
    <div class="world-heading"><h1>Luxemburg als Spielwelt</h1><span>Interaktiver Entwurf</span></div>
    <a class="play-view-link" href="play.php">Spielansicht ↗</a>
    <button type="button" id="about-open" aria-label="Über diesen Entwurf">?</button>
  </header>
  <main class="world-layout">
    <aside class="world-rail">
      <p class="eyebrow">Die Welt der zwölf Kantone</p>
      <h2>Kleine Heimat.<br>Großes Königreich.</h2>
      <p>Deine Stadt liegt mitten in einer lebendigen Landschaft. Wege verbinden Gemeinden, Wälder verbergen Dungeons und über allem wacht die Krounbuerg.</p>
      <div class="rail-art"><img src="art/crown.webp" alt="Illustration der Kronfestung" width="180" height="180"><span>Ein Thron. Viele Verbündete.</span></div>
      <div class="rail-note"><strong>Von der Welt bis zur Gemeinde</strong><p>Zoome hinein, um Spielerstädte und Rohstoffe zu entdecken. Blende Grenzen ein und wähle ein Gebiet.</p></div>
      <div class="world-legend" aria-label="Kartenlegende"><span><i class="own"></i>Verbündete Stadt</span><span><i class="danger"></i>PvE-Bedrohung</span><span><i class="dungeon"></i>Kantonsdungeon</span><span><i class="crown"></i>Kronfestung</span></div>
      <a class="atlas-link" href="../dungeon-preview/">Zum Dungeonatlas <span aria-hidden="true">↗</span></a>
    </aside>
    <section class="world-frame" aria-label="Interaktive Weltkarte von Luxemburg">
      <div class="world-toolbar">
        <div class="layer-buttons" role="group" aria-label="Kartenebenen"><button type="button" data-layer="landscape" aria-pressed="true">Landschaft</button><button type="button" data-layer="cantons" aria-pressed="false">Kantone</button><button type="button" data-layer="communes" aria-pressed="false">Gemeinden</button></div>
        <label class="place-search"><span class="sr-only">Gemeinde auf der Karte suchen</span><select id="place-select"><option value="">Gemeinde entdecken …</option></select></label>
      </div>
      <div class="world-stage" id="world-stage" tabindex="0" aria-label="Weltkarte. Mit Pfeiltasten verschieben, mit Plus oder Minus zoomen. Orte auch über die Gemeindeauswahl erreichbar.">
        <canvas id="world-canvas" aria-hidden="true"></canvas>
        <div id="map-labels" aria-hidden="true"></div>
        <div id="map-markers"></div>
        <div class="world-watermark" aria-hidden="true"><span>Das Großherzogtum</span><strong>Luxemburg</strong><small>Land der Burgen & Legenden</small></div>
        <div class="world-compass" aria-hidden="true"><span>N</span><img src="../art/items/compass.svg" alt="" width="56" height="56"></div>
        <div class="world-controls" role="group" aria-label="Karte zoomen"><button type="button" id="zoom-in" aria-label="Vergrößern">+</button><output id="zoom-level" aria-live="off">1×</output><button type="button" id="zoom-out" aria-label="Verkleinern">−</button><button type="button" id="overview" aria-label="Gesamtes Luxemburg anzeigen">⌂</button></div>
        <div class="world-quick"><button type="button" id="my-city">⚑ Meine Stadt</button><button type="button" id="crown-jump">♛ Krounbuerg</button></div>
        <p class="map-hint" id="map-hint">Ziehen zum Erkunden · Zoomen für mehr Details</p>
        <div class="map-loading" id="map-loading" role="status">Die Welt wird entfaltet …</div>
      </div>
      <footer class="world-footer"><span id="map-status" role="status">Luxemburg · 12 Kantone · 100 Gemeinden</span><span>Beispielwelt · keine Spielaktionen</span></footer>
    </section>
  </main>
  <dialog id="place-dialog" aria-labelledby="place-title" class="world-dialog">
    <header><div><p id="place-kind"></p><h2 id="place-title"></h2></div><button type="button" data-close aria-label="Fenster schließen" autofocus>×</button></header>
    <div id="place-content" class="dialog-content"></div>
    <footer><button type="button" data-close>Zur Karte</button><a id="dungeon-link" href="../dungeon-preview/" hidden>Dungeon ansehen ↗</a></footer>
  </dialog>
  <dialog id="about-dialog" aria-labelledby="about-title" class="world-dialog">
    <header><div><p>Union of Kingdoms</p><h2 id="about-title">Eine Heimat für dein Königreich</h2></div><button type="button" data-close aria-label="Hinweis schließen" autofocus>×</button></header>
    <div class="dialog-content"><p>Die Landesform und Verwaltungsgrenzen stammen aus Luxemburg. Landschaft, Wege, Gewässer, Städte, Bündnisse und Besitzstände sind eine freie Fantasygestaltung für diesen Entwurf.</p><p><strong>Gemeinden sind Gebiete, keine einzelnen Bauplätze.</strong> In jeder Gemeinde können mehrere Spielerstädte, Rohstoffe, Monster und Allianzgebäude liegen.</p><p>Als Spielidee: Gemeinden gemeinsam befreien, angekündigte PvE-Angriffe abwehren und Kantonsprüfungen bestehen. Beispielsweise sechs errungene Siegel öffnen den Weg zur Kronfestung. Der Bund bestimmt gemeinsam seinen Kandidaten für den Titel Grand-Duc oder Grande-Duchesse.</p><p>Die gezeigten Städte und Siegel sind Beispiele. Diese Vorschau verändert keine Spielstände.</p><p class="source-note">Geodaten: ACT Luxembourg / SIG-GR 2026, <a href="https://data.public.lu/en/datasets/cantons-in-luxembourg-2026/" target="_blank" rel="noopener">Kantone</a> und <a href="https://data.public.lu/en/datasets/municipalities-in-the-greater-region-2026/" target="_blank" rel="noopener">Gemeinden</a>, <a href="https://creativecommons.org/licenses/by/4.0/" target="_blank" rel="noopener">CC BY 4.0</a>. Für die Spielkarte projiziert. Illustrationen aus Union of Kingdoms.</p></div>
    <footer><button type="button" data-close>Die Welt erkunden</button></footer>
  </dialog>
  <noscript><p>Bitte JavaScript aktivieren, um die Weltkarte zu erkunden.</p></noscript>
</body>
</html>
