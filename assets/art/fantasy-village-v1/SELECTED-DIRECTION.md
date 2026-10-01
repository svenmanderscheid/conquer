# Ausgewählte Dorfvorlage

Der Nutzer hat ausdrücklich die angehängte großzügige Dorfansicht mit kleineren Gebäuden gewählt, nicht die nachfolgende Variante mit vergrößerten Gebäuden.

Verbindliche Konzeptreferenz: `village-selected-spacious.png` (unveränderte Kopie des Nutzeranhangs `codex-clipboard-61e99195-d46b-4502-8645-8ef4b3f071e6.png`, entspricht visuell der v8-Richtung).

Beibehalten: großzügige Freiflächen, bestehende Gebäudeproportionen und Platzierung, einfacher matter Cartoon-Zeichenstil, innere Trennmauer mit Treppe, erkennbare Goldmine und offener Steinbruch.

`village-overview-v9-balanced-buildings.png` ist nur eine nicht ausgewählte Alternative. Frühere Bilder bleiben erhalten.

Status: Das Gesamtbild ist jetzt die Stilvorlage für `../village-layered-v2/`: neu abgeleiteter leerer Untergrund, 15 separate Gebäudesprites und eine Baustellengrafik. Die alten prunkvollen Einzelbilder bleiben verworfen und werden nicht geladen. Laufende, vom Server gelieferte Bauaufträge ersetzen das Gebäudebild durch die Baustelle mit dezentem Staubeffekt und Restzeit. Erst nach serverseitiger Entfernung des Bauauftrags wird das fertige Gebäude angezeigt. Stufe 0 ohne Auftrag zeigt einen freien Bauplatz. Mauern bleiben Teil des Untergrunds; ihr Ausbau wird am Tor markiert. Die Grafiken unterscheiden noch keine individuellen Stufenmodelle.

Die mobilen WebP-Dateien in `runtime/` werden mit `php tools/build-painted-village.php` aus den unveränderten PNG-Originalen erzeugt. Positionen und Größen: `assets/js/city-painted.js`. Prüfung: `tests/painted_city.cjs`.
