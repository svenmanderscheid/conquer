<?php
declare(strict_types=1);
// A read-only visual concept. It does not load accounts, sessions or game APIs.
function atlasVersion(string $path): string { return (string) filemtime(__DIR__ . '/' . $path); }
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#ffffff">
  <title>Luxemburg · Union of Kingdoms Dungeonatlas</title>
  <link rel="icon" href="../art/items/compass.svg" type="image/svg+xml">
  <link rel="stylesheet" href="../css/fantasy-fonts.css?v=<?= atlasVersion('../css/fantasy-fonts.css') ?>">
  <link rel="stylesheet" href="atlas.css?v=<?= atlasVersion('atlas.css') ?>">
  <link rel="stylesheet" href="../css/village-theme.css?v=<?= atlasVersion('../css/village-theme.css') ?>">
  <script type="module" src="atlas.js?v=<?= atlasVersion('atlas.js') ?>"></script>
</head>
<body class="dungeon-atlas-preview">
  <div class="atlas-page">
    <header class="atlas-brand"><a href="../../city#city" aria-label="Zurück zu Union of Kingdoms">Union of Kingdoms<span>A new Era begins</span></a><span class="concept-pill">Gestaltungsvorschau</span></header>
    <main class="atlas-window">
      <header class="atlas-heading"><img src="../art/items/compass.svg" alt="" width="48" height="48"><div><p>Der Dungeonatlas</p><h1>Die zwölf Kantone</h1></div><span class="heading-country">Luxemburg<br><small>Ein Land voller Legenden</small></span></header>
      <div class="atlas-toolbar"><div class="view-tabs" role="group" aria-label="Ansicht"><button type="button" data-view="map" aria-pressed="true">Karte</button><button type="button" data-view="list" aria-pressed="false">Kantonsliste</button></div><label class="progress-toggle"><input type="checkbox" id="progress-toggle"> Freischaltungen zeigen</label></div>
      <div class="atlas-layout">
        <section class="atlas-explore" aria-label="Dungeons entdecken">
          <div class="map-intro"><p class="eyebrow">Deine nächste Expedition</p><h2>Kleine Heimat. Große Abenteuer.</h2><p>Zwölf Kantone, zwölf Dungeons.<br>Welche Legende entdeckst du zuerst?</p></div>
          <div id="map-view" class="map-view">
            <div class="map-stage" id="map-stage"><p class="map-loading" role="status">Die Karte wird entfaltet …</p></div>
            <div class="map-compass" aria-hidden="true"><span>N</span><img src="../art/items/compass.svg" alt="" width="62" height="62"></div>
            <div class="map-note"><span aria-hidden="true">✧</span><p>Wähle einen Kanton<br><small>und entdecke seine Beute.</small></p></div>
          </div>
          <div id="list-view" class="canton-list" hidden aria-label="Alle zwölf Kantone"></div>
          <div class="map-legend" id="map-legend"><span><i class="legend-selected"></i>Ausgewählt</span><span><i class="legend-open"></i>Dungeon</span><span class="progress-legend" hidden><i class="legend-done"></i>Gemeistert</span><span class="progress-legend" hidden><i class="legend-locked"></i>Gesperrt</span></div>
          <p class="map-credit">Kantonsgrenzen: <a href="https://data.public.lu/en/datasets/cantons-in-luxembourg-2026/" target="_blank" rel="noopener">ACT / SIG-GR 2026</a> · <a href="https://creativecommons.org/licenses/by/4.0/" target="_blank" rel="noopener">CC BY 4.0</a> · für die Spielkarte aufbereitet</p>
        </section>
      </div>
      <footer class="atlas-footer"><span><span aria-hidden="true">✦</span> Jeder Kanton birgt seinen eigenen Schatz.</span><small>Entwurf · Dungeon-Namen, Beute und Freischaltungen sind Beispiele.</small></footer>
    </main>
    <p class="preview-note">Interaktive Gestaltungsvorschau · Expeditionen und Belohnungen sind noch nicht spielbar.</p>
  </div>
  <dialog id="dungeon-dialog" class="atlas-dialog" aria-labelledby="dungeon-modal-title">
    <header class="dungeon-dialog-heading"><div><p id="dungeon-modal-canton"></p><h2 id="dungeon-modal-title"></h2></div><button type="button" class="dialog-close" data-close-dialog aria-label="Dungeon schließen" autofocus>×</button></header>
    <div id="dungeon-detail" class="dungeon-detail" aria-busy="true"></div>
    <footer class="dungeon-dialog-footer"><button type="button" class="dialog-return" data-close-dialog>Zur Karte</button><button class="atlas-action" type="button" data-next>Nächster Kanton <span aria-hidden="true">→</span></button><p class="detail-footnote">Vorschau · Noch keine Expedition starten</p><p id="selection-announcement" class="visually-hidden" role="status" aria-live="polite"></p></footer>
  </dialog>
  <noscript><p>Bitte JavaScript aktivieren, um die Kantone und ihre Dungeons auszuwählen.</p></noscript>
</body>
</html>
