# Conquer – Regeln für neue Inhalte

## Öffentlicher Spielname

Das Spiel heißt **Union of Kingdoms**. Verwende diesen Namen in sichtbaren Oberflächen, Seitentiteln, Vorschauen und Nutzertexten. „Conquer“ ist nur der interne Projektname; bestehende technische Pfade, Namespaces und die Schriftfamilie `Conquer UI` dürfen so bleiben.

## Hauptsprache

**Englisch ist die Haupt- und Standardsprache von Union of Kingdoms.** Neue sichtbare Texte benötigen eine vollständige englische Fassung im gemeinsamen Sprachsystem (`data/i18n/`, `Locale`, `ConquerLocale`). Ohne ausdrückliche Sprachwahl gilt `en`, unabhängig von der Browsersprache. Englisch ist auch die Rückfallebene. Spielernamen, Allianz-/Weltnamen und Spielernachrichten bleiben unverändert.

## Verbindlicher Stil für Menüs und Weltkarte

Lies vor jeder sichtbaren Änderung an Menüs, Dialogen, HUD, Karten-Overlays oder Backoffice `docs/UI_STYLE_GUIDE.md` und das verbindliche `docs/BRANDING_KIT.md`. Die zentrale Oberfläche liegt in `assets/css/village-theme.css` und wird nach den Funktions-Stylesheets geladen. Seit der jüngsten Nutzerentscheidung vom **7. Oktober 2026** gilt **„Fantasy · Bree Serif + Warmes Creme & Königsgold“**: cremeweiße Grundflächen `#FFF7E7`, helle Karten `#FFFCF3`, dezente Ockerschattierung `#FAF2E1`, dunkle Schrift `#3A3529`, warme Ränder `#D9C7A4` und weiche plastische Tiefe. Überschriften und Hauptaktionen verwenden **Bree Serif**, Texte und Zahlen **Nunito**, jeweils lokal geladen. Verwende die gemeinsamen `--ui-*`-Variablen; Kopf-/Auswahlflächen haben eigene Tokens, während `--ui-primary` lesbare Textakzente `#756A55` liefert. Dekoratives Königsgold `#D6A64D`, helle Auswahl `#FFF5DE`, Auswahltext `#704600` und Fokus-/Auswahlkante `#8B611B` verwenden eigene `--ui-accent-gold*`-Tokens, getrennt vom semantischen `--ui-gold`. Helle grüne Aktionsflächen (`#82C34A` → `#72B641`, Text `#203B18`) nutzen eigene `--ui-action-green-*`-Variablen; semantische Status-, Rollen-, Kristall- und Seltenheitsfarben bleiben erhalten. Knöpfe behalten ihre kurze Druckbewegung mit verkürztem Schatten; reduzierte Bewegung wird berücksichtigt. Diese Entscheidung ersetzt Klares Weiß, Apricotlicht mit violetten Köpfen sowie die früheren Almendra-/Lora- und Beige-Vorgaben. Neue Komponenten dürfen keine eigene Farbpalette oder kantige Metallhaut einführen. Prüfe neue Oberflächen im Desktop-, Handy- und Querformat.

Browser-Scrollleisten bleiben in allen bestehenden und zukünftigen Welten unsichtbar. Die gemeinsame Regel in `assets/css/village-theme.css` gilt unabhängig von Welt-ID, Region und Kartenart für Seiten und innere Scrollbereiche. Scrollen per Mausrad, Touch und Tastatur muss erhalten bleiben; neue Ansichten dürfen die Scrollleisten nicht wieder einblenden oder zum Ausblenden benötigtes Scrollen mit `overflow:hidden` sperren.

**Vom Nutzer freigegebene Ausnahme für die Verwaltung (5. Oktober 2026):** Das Admin-Backoffice verwendet die moderne, eigenständige Gestaltung in `assets/css/admin-modern.css`: Systemschrift, neutrale helle Flächen und blaue Aktionen. Dieses nur auf `body.admin-modern` begrenzte Stylesheet wird nach `village-theme.css` geladen. Die sieben Hauptbereiche und die direkt bearbeitbaren Drop-Tabellen sind in `docs/ADMIN_BACKOFFICE.md` beschrieben. Die Spieloberfläche verwendet „Fantasy · Bree Serif + Warmes Creme & Königsgold“. Sprache, semantische Farben, unsichtbare Scrollleisten und mobile Bedienbarkeit gelten weiterhin.

## Verbindliche Gestaltung der gezeichneten Welt

Lies vor sichtbaren Änderungen an Stadt, Welt, Gebäuden, Figuren oder Dekoration `docs/ART_DIRECTION.md`. Die Stilvorlage bleibt `assets/art/village2.png`; die aktive Stadt verwendet `assets/js/city-painted.js` und die dort referenzierten freigegebenen Bilder.

- Große, weiche, leicht überzeichnete Formen und klare Silhouetten; dunkelbraune Konturen, reduzierte Farben und ruhige Flächen.
- Chibi-Proportionen, unregelmäßige Naturformen und warme Erdwege; keine realistischen, glänzenden Materialien.
- Wiederverwende vorhandene freigegebene PNG/WebP/SVG-Bilder. Animationen müssen Beschriftungen, Gebäudewahl und Touch-Bedienung respektieren und reduzierte Bewegung unterstützen.
- Gebäudenamen und Stufen erscheinen in der Stadt nur bei Auswahl in der bestehenden Plakette direkt am Gebäude. Keine neue Karte am unteren Bildschirmrand und kein zusätzlicher Auswahlring; laufende Aufträge und ihre Statusanzeigen bleiben sichtbar.
- Prüfe die echte Haupt-App unter `/city#city` als Gesamtansicht und bei Gebäudeaktionen, in Desktop-, Handy- und Querformat. Keine doppelte Navigation, überdeckten Bedienelemente, fehlenden Bilder oder Browserfehler.
- Die eigenständige 3D-/2,5D-Szene wurde auf Nutzerwunsch entfernt. Keine Three.js-/WebGL-Szene wieder einführen. Dekorative Tiefe durch Schatten, Rahmen und Schichtung bleibt erwünscht.

## Verbindliches Ziel: gemeinsame App für iOS und Android

Conquer soll nach Stabilisierung des Spiels mit **einer gemeinsamen Web-Codebasis** als iOS- und Android-App veröffentlicht werden. Die vorgesehene Technik ist Capacitor mit dem bestehenden HTML-, CSS- und JavaScript-Frontend; PHP, MySQL, Spielregeln und Spielstände bleiben auf dem Server. Native Erweiterungen in Swift oder Kotlin sollen nur eingesetzt werden, wenn eine benötigte Gerätefunktion nicht zuverlässig über Capacitor oder ein gepflegtes Plugin verfügbar ist.

Die nativen iOS- und Android-Projekte, Store-Pakete und die endgültige Einbindung von Gerätefunktionen werden erst gegen Ende erstellt, wenn Spielabläufe, Benutzeroberfläche und Schnittstellen weitgehend stabil sind. Dieses späte Verpacken darf jedoch nicht dazu führen, dass mobile Anforderungen bis dahin ignoriert werden. Lies bei Änderungen mit Einfluss auf Frontend, Navigation, Anmeldung, Sitzungen, APIs, Grafikleistung oder Dateipfade zusätzlich `docs/MOBILE_APP_STRATEGY.md`.

Berücksichtige bei jeder passenden Entwicklung bereits jetzt:

- Alle Spielbereiche müssen per Touch ohne Maus, Hover oder Tastatur vollständig bedienbar sein.
- Sichtbare Oberflächen müssen auf kleinen Hochformatbildschirmen sowie im Querformat funktionieren und sichere Bildschirmränder berücksichtigen.
- Browsernavigation, Zurück-Verhalten, App-Wechsel und erneutes Öffnen dürfen keine Aktionen doppelt auslösen oder den Spielzustand verlieren.
- Neue Spielfunktionen und schreibende Aktionen brauchen klar getrennte, authentifizierte Serverendpunkte; Spielregeln und vertrauenswürdige Werte bleiben serverseitig.
- URLs, Weiterleitungen, Assets, Cookies, CSRF-Schutz und Anmeldesitzungen dürfen nicht unnötig an einen bestimmten Host, Unterordner oder normalen Browser-Tab gebunden werden.
- Große Bilder, Texturen, JavaScript-Module und Animationen müssen für mobile Speicher- und Leistungsgrenzen geeignet bleiben.
- Relevante Änderungen werden mindestens in einem schmalen Handyformat geprüft; grafikintensive Änderungen zusätzlich auf einem echten Mobilgerät, sobald ein geeigneter Teststand verfügbar ist.
- Plattformabhängiger Code wird hinter einer kleinen Schnittstelle gekapselt, damit Web, iOS und Android dieselbe Spiellogik und Oberfläche teilen.
