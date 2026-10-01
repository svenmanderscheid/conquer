# Eroberungsmotive für die Luxemburg-Welt

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
