# Eroberungsmotive für die Luxemburg-Welt

## Aktive Reihe vom 5. Oktober 2026

Auf Nutzerwunsch erhalten Shrines, Communes und Congress deutlich unterscheidbare Motive ohne Burg. Die aktive Zuordnung liegt in `assets/js/territory-art.js` und verwendet `assets/art/territory-v3/`:

- `commune-food`, `commune-lumber`, `commune-stone`, `commune-gold`, `commune-abbey`, `commune-rune`: sechs zivile Markt-, Handwerks- und Versammlungsgebäude ohne Wehrtürme oder Zinnen; die bisherigen Vorteilsmerkmale bleiben erkennbar.
- `canton-shrine`: wiederverwendeter freigegebener Waldschrein aus `map/painted-v2/shrine-forest.png`, mit heiligem Baum und Steinkreis.
- `congress-forum`: neuer offener Ratsplatz mit Rundtisch, Sitzen, Lorbeer und Kristall. Auch Congress in klassischen Welten, sein Dialog und seine Marschvorschau verwenden dieses Motiv.

Neue Illustrationen wurden mit dem eingebauten Imagegen-Werkzeug erzeugt; Originale und Herkunft liegen neben den WebP-Dateien. Alle Laufzeitbilder sind transparente 512 × 512 px WebP-Dateien. Die Aufbereitung verkleinert und komprimiert nur. Weltkarte, Gebietsfenster und Allianz-Zielvorschauen verwenden dieselben Zuordnungen. Dunkelviolette Namensschilder mit Goldkante machen Sonderorte zusätzlich erkennbar; Spielnamen, Besitzer, Grundflächen und Regeln bleiben unverändert. Die vorherigen Reihen werden aufbewahrt.

Prüfung: `tests/territory_frontend.cjs` besteht in fünf Bildschirmgrößen, `tests/world_shrines.cjs` in vier Größen einschließlich System-/Spiel-Einstellung für reduzierte Bewegung. Die echte Haupt-App wurde mit einer isolierten Luxemburg-Testwelt bei 1280 × 800, 390 × 844 und 844 × 390 geprüft: alle acht Motive in Karte und Dialog, Stadtansicht und Gebäudeaktionen, keine fehlenden Bilder oder JavaScript-Fehler. Aufnahmen und Prüfprotokoll: `output/playwright/landmarks/`. Spielernamen wurden mit `tests/map_alliance_labels_app.cjs` in fünf Größen und bei wechselnden Mitgliedschaften geprüft. Physische Mobilgeräte wurden in dieser Änderung nicht getestet.

## Vorherige Reihe vom 30. September 2026

Stand: 30. September 2026. Acht zusammengehörige Bilder für **Union of Kingdoms**, überarbeitet mit dem eingebauten Imagegen-Werkzeug. Die neue Reihe `territory-v2` verwendet jeweils das bisherige Motiv aus `territory-v1` als Bearbeitungsvorlage und `assets/art/village2.png` als verbindliche Stilreferenz. Kein CLI-Fallback.

Auf Nutzerwunsch wirken die Gebäude mächtiger: breitere Hauptgebäude, stärkere Sockel, schwere Tore, Stützpfeiler und kräftigere Wehrmauern. Die regionale Festung besitzt größere Bastionen; das Royal Castle eine gestaffelte Burganlage. Dachfarben, Rohstoffsymbole, weiche Formen und dunkelbraune Konturen bewahren die Erkennbarkeit. Die bisherige Reihe bleibt für den direkten Vergleich erhalten.

| Ziel | Vorteil | Spieldatei in `assets/art/territory-v2/` |
|---|---|---|
| Kornlande | Nahrung | `commune-food.webp` |
| Waldvogtei | Holz | `commune-lumber.webp` |
| Steinbruch | Stein | `commune-stone.webp` |
| Handelsgemeinde | Gold | `commune-gold.webp` |
| Abteigemeinde | Forschung | `commune-abbey.webp` |
| Runenwacht | Teleport | `commune-rune.webp` |
| Regionale Festung | Kanton | `canton-fortress.webp` |
| Royal Castle | Kontinentziel | `crown-krounbuerg.webp` |

Die verlustfrei aufbewahrten PNG-Originale liegen im Unterverzeichnis `source/`. Die Laufzeit verwendet ausschließlich die acht WebP-Dateien mit 512 × 512 Pixeln und echtem Alphakanal. Zusammen: 550.994 Bytes (rund 538 KiB). Für die WebP-Ausgabe wurden die Originale nur verkleinert und komprimiert (Qualität 88), ohne nachträgliche Motivänderungen. `sizes.json` dokumentiert die Einzelgrößen; `prompts.json` enthält sämtliche Erstellungs- und Korrekturprompts. Zwei unerwünschte Banner am Handelsgebäude wurden mit Imagegen entfernt, damit sie keine feste Allianz-Zugehörigkeit suggerieren.

`assets/js/territory-art.js` ordnet die Bilder anhand von `kind` und `benefit_type` zu. Weltkarte und Gebietsfenster verwenden dieselbe Zuordnung. Bestehende Forschungs- und Runen-Aliasse sind berücksichtigt; unbekannte Gemeindetypen erhalten das Kornlande-Motiv. Keine Gemeinderegeln, Besitzdaten oder Weltprofile werden geändert.

Die Kartengröße folgt der vom Server gelieferten Grundfläche. Das Bild nimmt 92 % ihrer Breite ein; damit bleiben Gemeinde, Kanton und Krone gestaffelt. Besitzer und Auswahl bleiben eigenständige Beschriftungen. Die neuen Dateien ersetzen keine Spielerburgen oder Allianzgebäude der bisherigen Welt.

## Vorschau und Prüfung

- Bildübersicht mit Umschaltung Vorher/Nachher: `assets/art/territory-v2/index.html`; Screenshots `artifacts/territory-art-v2.png` und `artifacts/territory-art-v2-mobile.png`.
- `tests/territory_frontend.cjs` prüft 113 Ziele, acht verschiedene geladene Bilder und die Oberfläche bei 1280 × 800, 390 × 844, 320 × 568, 844 × 390 und 568 × 320.
- `tests/territory_main_app.cjs` verwendet die echte Haupt-App mit `tools/preview-feature-fixture.php --territory`: identische Bilder in Karte und Gebietsfenster, alle acht Kategorien, passende Bildgröße zur Grundfläche, Gebietsaktionen und Stadtansicht mit Gebäudeauswahl in fünf Bildschirmgrößen.
- Die bei der ersten Bildreihe korrigierte Position der Stadt-Gebäudeaktionen bleibt Bestandteil der Haupt-App-Prüfung.
- PNG- und WebP-Transparenz wurden geprüft; alle vier Ecken sind transparent. Die Abnahme erfolgt mit synthetischen Spielständen, ohne bestehende Welten umzustellen. Ein Test auf einem echten Mobilgerät steht weiterhin aus.

Abnahme vom 30. September bestanden: Beide Browserprüfungen in allen fünf Bildschirmgrößen, alle acht Bildkategorien in Karte und Gebietsfenstern, der Vorher/Nachher-Wechsel der Galerie sowie zusätzliche Ansichten von Festung und Royal Castle bei 320 × 568, 390 × 844 und 844 × 390. Die Galerie wurde bei Desktop- und Handybreite geprüft. Keine JavaScript-Fehler; alle acht Spieldateien haben 512 × 512 Pixel, einen echten Alphakanal und transparente Ecken. Die Galerieansicht und die zusätzlichen Karten-/Dialogbilder wurden visuell geprüft.
