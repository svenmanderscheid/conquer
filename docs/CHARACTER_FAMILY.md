# Gemeinsame Figurenfamilie

Stand: 14. September 2026. Erste spielbare Umsetzung, kein fertig ausgearbeitetes Helden- oder Kampfsystem.

`assets/city3d/character-workshop.html` zeigt Infanterist, Stadtwache, Arbeiter und Bewohner. Figuren lassen sich per horizontaler Touchgeste, Maus oder Pfeiltasten drehen. Bereitstehen, Gehen, Angriff und Arbeiten sind auswählbar. Pausieren und die Betriebssystempräferenz für reduzierte Bewegung werden berücksichtigt. Das vorhandene `assets/art/knight.png` ist die 2D-Referenz; es wurde kein neues Porträt erzeugt.

## Architektur

- `character-family.js`: gemeinsames Skelett mit 16 benannten Knochen, vier AnimationClips, SkinnedMesh und passende ebenfalls animierte Kontur. Rolle, Kleidungsfarbe und Werkzeug werden beim Aufbau gewählt. Augen und Gesicht erhalten keine zusätzlichen dicken Konturen.
- Pro Figur zwei Zeichenaufrufe im Hauptdurchlauf, zuzüglich Schatten. Geometrie und Materialien werden pro Variante gemeinsam verwendet; Skelett und AnimationMixer gehören der einzelnen Figur. Identische Vertices werden einschließlich Normalen, UVs und Knochenzuordnung zusammengeführt.
- `city-life.js`: die bestehenden zwölf Fußgänger, sieben Arbeiter und drei Mauerwachen verwenden die Familie. Straßen, Arbeitsplätze und Patrouillen bleiben ihre Bewegungsgrundlage. Hammer und Rechen bewegen sich mit der Hand; Träger verwenden die ruhige Bereitschaftsanimation.
- `character-export.js`: GLB-Export mit Vertexfarben, UVs, Skelett, Bindematrizen und vier Rotationsanimationen. `assets/art/characters/` enthält die vier exportierten Grundmodelle. Der Standard-glTF-Werkstoff ist matt; Toonlicht und Kontur sind Conquer-spezifisch und müssen in einem anderen Renderer ergänzt werden. Die Dateien sind indexiert, aber nicht Draco-/Meshopt-komprimiert.
- Die Stadt baut Modelle derzeit aus der gemeinsamen Quelle auf. Sie lädt die GLB-Dateien noch nicht. Diese dienen als transportable Ausgangsassets für die weitere Bearbeitung in einem 3D-Werkzeug.

## Produktionsgrenzen

### Überarbeitung des Infanteristen

`infantry-sculpt.js` ersetzt beim Infanteristen den ersten Baukastenentwurf: selbst definierte Querschnitte für Kiefer, Jacke und Stiefel, entlang von Kurven geformte und zugespitzte Haarsträhnen, dünne räumliche Ohren, geteilte Tunika mit heller Einfassung, gefalteter Schal und diagonal geführtes Schwert. Glatte Nahtnormalen und ruhigere Toonflächen ersetzen die fleckig wirkende Oberflächenstruktur. Eine eigene Bereitschaftshaltung ergänzt das gemeinsame Skelett. Der Stand umfasst 11.432 Dreiecke und eine GLB-Datei von rund 640 KiB.

Die Änderung betrifft zunächst ausschließlich den Infanteristen in der Figurenwerkstatt und seinen Export. Wachen, Arbeiter und Bewohner behalten ihren bisherigen Entwurf. Vorder-, Seiten- und Rückansicht wurden visuell geprüft; die Modell-/Animationstests und die responsiven Ansichten werden weiterhin mit denselben Prüfprogrammen ausgeführt.

Dies sind original programmgenerierte, modular aufgebaute Modelle mit starrer Zuordnung der einzelnen Körperteile zu Knochen. Sie sind keine handmodellierten, weich gewichteten Studiofiguren. Künstlerisches Sculpting, natürliche Gelenkverformung, weitere Frisuren/Kleidungsvarianten, eigene Arbeiterporträts und Distanzstufen können darauf aufbauen. Bogenschützen und Reiter sowie die vorhandenen Ausbildungsmodelle sind noch nicht auf diese Familie umgestellt. Die exportierten Assets ändern keine Einheitenwerte oder Spielregeln.

Die Bibliothek hält ihre begrenzten Geometrievarianten über die Lebensdauer der Seite im Cache. `dispose()` einer Figur gibt nur deren Animation/Skelett frei, keine gemeinsam genutzten Assets. Bei später frei kombinierbaren oder sehr vielen Varianten benötigt der Cache eine begrenzte Lebensdauer.

## Prüfung und Wiederherstellung der Exporte

1. Projekt als statische HTTP-Vorschau bereitstellen, zum Beispiel `php -S 127.0.0.1:19347 -t .`.
2. `node tests/character_family.cjs --export` prüft vier Modelle, Gewichte, Bewegungen, Pause, GLB-Struktur, schmale Ansichten und die 22 Stadtfiguren. `CHARACTER_BASE` überschreibt den Vorschau-Origin. Ohne `--export` schreibt die Prüfung nur unter `artifacts/character-family/`.
3. `tests/village_viewport.cjs` prüft die echte PHP-Stadtansicht mit Haupt-App-Styles, Ansichten, Touchzoom und fehlenden doppelten HUDs.
4. `php tools/preview-feature-fixture.php --port=19348` startet eine isolierte App mit Testkonto. `node tests/character_city_app.cjs` prüft dort `/city#city` mit Desktop, Hoch- und Querformat; nach der Prüfung die Vorschau per Enter schließen.

Ein echter Mobilgerätetest wurde in dieser Umsetzung nicht durchgeführt. Desktop-Browseransichten mit mobilen Abmessungen ersetzen keine GPU-/Speichermessung auf iOS oder Android.
