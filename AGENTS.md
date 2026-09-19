# Conquer – Regeln für neue Inhalte

## Verbindlicher Stil für Menüs und Weltkarte

Lies vor jeder sichtbaren Änderung an Menüs, Dialogen, HUD, Karten-Overlays oder Backoffice `docs/UI_STYLE_GUIDE.md`. Die zentrale Oberfläche liegt in `assets/css/village-theme.css` und wird zuletzt geladen. Verbindlich ist die vom Nutzer gewählte Variante A: dunkelviolette Fensterköpfe, warme beigefarbene Flächen, dezente Goldakzente und weiche Formen. Verwende die gemeinsamen `--ui-*`-Variablen, Almendra für Texte und Lora für Zahlen. Die lokal geladene Schriftfamilie `Conquer UI` kombiniert beide automatisch. Neue Komponenten dürfen keine eigene Farbpalette oder kantige Metallhaut einführen. Bewahre die Farbbedeutung von Aktionen, Zuständen und Seltenheiten; das violette Menüdesign ersetzt keine blauen Verteidigungs- oder sonstigen Rollenfarben. Prüfe neue Oberflächen im Desktop-, Handy- und Querformat.

## Verbindliche 3D-Gestaltung

Lies vor jeder sichtbaren Änderung an Stadt, Welt, Gebäuden, Figuren oder Dekoration `docs/ART_DIRECTION.md`. Die aktive Stilvorlage ist `assets/art/village2.png`. Neue Inhalte müssen wie Bestandteile derselben gezeichneten Fantasywelt aussehen.

- Verwende große, weiche und leicht überzeichnete Formen. Silhouetten sollen aus der normalen Spielkamera sofort erkennbar sein.
- Nutze die gemeinsame Palette und die Toonmaterialien aus `assets/city3d/storybook-style.js`. Große Formen erhalten gezielte dunkelbraune Konturen. Vermeide realistische, glänzende PBR-Oberflächen.
- Natürliche Oberflächen dürfen echte Texturen verwenden. Diese müssen handgemalt, nahtlos, farblich reduziert und aus der Spielentfernung ruhig lesbar sein. Keine Foto-, Pixelart- oder stark wiederholbaren Kacheltexturen.
- Wege bestehen aus warmer Erde mit unregelmäßigen Farbflächen, eingelassenem Kies und wenigen lockeren Randsteinen. Das Referenzverfahren ist `paintedRoadTexture()` in `assets/city3d/full-city.js`. Vermeide regelmäßige Rechteckreihen und Schienenmuster.
- Bäume brauchen krumme Stämme, sichtbare Wurzeln und asymmetrische Kronen. Variiere Silhouette, Neigung und Farbe; vermeide perfekte Kegel oder exakt gestapelte Kugeln.
- Menschen verwenden Chibi-Proportionen: großer Kopf, kurzer Körper, breite Schuhe, klare Gesichtszüge und eine überzeichnete Kleidung oder Ausrüstung, die ihre Rolle zeigt.
- Bewegung respektiert die Szene. Fußgänger bleiben auf ihren Straßen, Arbeiter in ihren Arbeitsbereichen und Wachen auf dem mittleren Wehrgang. Niemand darf durch Gebäude, Dekoration oder Mauern laufen.
- Instanziere wiederholte Kleinteile und halte Texturen klein. Neue Details dürfen die Bedienbarkeit, Gebäudeauswahl und Lesbarkeit der Beschriftungen nicht verschlechtern.

Prüfe neue sichtbare Inhalte in der echten 3D-Spielansicht mindestens einmal als Gesamtansicht und einmal aus der Nähe. Prüfe zusätzlich die eingebettete Haupt-App unter `/city#city`; dort dürfen weder das 3D-HUD noch die 3D-Navigation doppelt über dem App-HUD erscheinen. Kontrolliere beide Baumtypen, Figuren in Bewegung, freie Laufwege, Browserfehler sowie die Anzeige von Gebäudenamen und Bedienelementen.

## Verbindliches Ziel: gemeinsame App für iOS und Android

Conquer soll nach Stabilisierung des Spiels mit **einer gemeinsamen Web-Codebasis** als iOS- und Android-App veröffentlicht werden. Die vorgesehene Technik ist Capacitor mit dem bestehenden HTML-, CSS-, JavaScript- und Three.js-Frontend; PHP, MySQL, Spielregeln und Spielstände bleiben auf dem Server. Native Erweiterungen in Swift oder Kotlin sollen nur eingesetzt werden, wenn eine benötigte Gerätefunktion nicht zuverlässig über Capacitor oder ein gepflegtes Plugin verfügbar ist.

Die nativen iOS- und Android-Projekte, Store-Pakete und die endgültige Einbindung von Gerätefunktionen werden erst gegen Ende erstellt, wenn Spielabläufe, Benutzeroberfläche und Schnittstellen weitgehend stabil sind. Dieses späte Verpacken darf jedoch nicht dazu führen, dass mobile Anforderungen bis dahin ignoriert werden. Lies bei Änderungen mit Einfluss auf Frontend, Navigation, Anmeldung, Sitzungen, APIs, 3D-Leistung oder Dateipfade zusätzlich `docs/MOBILE_APP_STRATEGY.md`.

Berücksichtige bei jeder passenden Entwicklung bereits jetzt:

- Alle Spielbereiche müssen per Touch ohne Maus, Hover oder Tastatur vollständig bedienbar sein.
- Sichtbare Oberflächen müssen auf kleinen Hochformatbildschirmen sowie im Querformat funktionieren und sichere Bildschirmränder berücksichtigen.
- Browsernavigation, Zurück-Verhalten, App-Wechsel und erneutes Öffnen dürfen keine Aktionen doppelt auslösen oder den Spielzustand verlieren.
- Neue Spielfunktionen und schreibende Aktionen brauchen klar getrennte, authentifizierte Serverendpunkte; Spielregeln und vertrauenswürdige Werte bleiben serverseitig.
- URLs, Weiterleitungen, Assets, Cookies, CSRF-Schutz und Anmeldesitzungen dürfen nicht unnötig an einen bestimmten Host, Unterordner oder normalen Browser-Tab gebunden werden.
- Große Bilder, Texturen, JavaScript-Module und 3D-Inhalte müssen für mobile Speicher- und Leistungsgrenzen geeignet bleiben.
- Relevante Änderungen werden mindestens in einem schmalen Handyformat geprüft; 3D-Änderungen zusätzlich auf einem echten Mobilgerät, sobald ein geeigneter Teststand verfügbar ist.
- Plattformabhängiger Code wird hinter einer kleinen Schnittstelle gekapselt, damit Web, iOS und Android dieselbe Spiellogik und Oberfläche teilen.
