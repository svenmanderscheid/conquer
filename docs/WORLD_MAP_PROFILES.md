# Versionierte Weltkarten

Aktuelle Benennung (29. September 2026): **Commune → Shrine → Royal Castle**. Kantone bleiben die geografischen Regionen; ihr Eroberungsziel heißt Shrine. Frühere Bezeichnungen im dokumentierten Entwurfs- und Prüfverlauf sind historische Namen derselben Ziele. Verbindliche Zuordnung: [TERRITORY_NAMING.md](TERRITORY_NAMING.md).

Die neue Kartenvorlage `luxembourg` verwendet 768 × 1.100 Felder. `worlds.map_size` enthält aus Kompatibilitätsgründen die Breite. Neue Aufrufer verwenden immer `WorldMapProfile::forWorld($worldId)` mit getrennten `width` und `height`. Welten ohne Profildatensatz behalten die bisherige quadratische Karte vollständig unverändert.

`WorldMapProfile::configureEmptyWorld()` lässt sich ausschließlich auf eine leere Welt anwenden. Die additive Migration `0119_world_map_profiles.sql` stellt keine bestehende Welt um und verschiebt keine Spielstände. `WorldService::initializeWorld()` importiert auf Luxemburg 113 Gebietsziele; Schreine und Kongress werden weder erstellt noch über die alten Dienste angeboten. Die alte Ereignisverarbeitung überspringt Luxemburg.

## Gemeinsame Geometrie

Die bereits freigegebenen Dateien `assets/world-lux-preview/game-geography.json` und `game-hydrology.json` sind die gemeinsame Quelle für Browser und Server. Gemeinde- und Kantonskennungen werden als Zeichenketten einschließlich führender Nullen erhalten. Die eindeutige Feldzuordnung verwendet das enthaltene Lauflängenraster mit Schrittweite 4; die gezeichneten Polygone sind dessen Grenzen. Ein Arrayindex aus dem Raster ist eine Eins-basierte Referenz auf die Gemeindeliste und keine neue Gemeindekennung.

Alle gespeicherten Spielkoordinaten bezeichnen ganzzahlige Feldmittelpunkte. Eine Stadt bei `(x,y)` belegt 16 Felder von `(x−1,y−1)` bis `(x+2,y+2)`. Ihre physische Fläche reicht deshalb von `(x−1,5,y−1,5)` bis `(x+2,5,y+2,5)`. Jede geschnittene Rasterzelle muss Land im selben Kanton sein. Gemeindegrenzen innerhalb eines Kantons sperren keine Stadt. Flüsse und Seen werden durch vollständige Rechteck-Schnittprüfungen geprüft; ein trockener Mittelpunkt genügt nicht.

`LuxembourgHydrology` indexiert Gewässersegmente und Seen in 32-Felder-Zellen. Die Prüfungen entsprechen `hydrology.mjs`. `LuxembourgGeography::landmarks()` liefert den stabilen Katalog aus `data/luxembourg_landmarks.json`:

- `commune:<Gemeinde-ID>`: 100 Vogteien, jeweils 4 × 4 Felder.
- `canton:<Kanton-ID>`: zwölf Markfesten, jeweils 6 × 6 Felder.
- `crown:krounbuerg`: eine Burg, 7 × 7 Felder.

Der Generator `php tools/build-luxembourg-landmarks.php` erstellt ausschließlich diesen prüfbaren Katalog, keine Datenbankeinträge. Sämtliche vollständigen Grundflächen sind trocken, liegen in einem Kanton und halten fünf Felder Abstand zu anderen Zielen. Änderungen des Katalogs sind Geometrieänderungen. Ein gespeichertes Kartenprofil bindet sowohl Gelände und Gewässer als auch Zielpositionen über einen Hash. Neue Kartengeometrie darf bestehende Welten nicht stillschweigend verändern.

Die 8 × 8-Landentwicklung bleibt ein eigenes System und erhält rechteckige Ausmaße. Ihre 13.248 gespeicherten Teile sind keine Gemeinden und kein Allianzbesitz. Die erste Initialisierung benötigt lokal ungefähr zehn Sekunden; Kartenaktualisierungen müssen nur den eigenen Landteil beziehungsweise einen benötigten Ausschnitt laden.

## API und Spawn

`/api/map/info` liefert `map_profile`, `map_width` und `map_height`. Asset-URLs im Profil sind relativ zur App-Basis. `/api/map/tiles` begrenzt Ausschnitte weiterhin auf maximal 101 × 101 Feldmittelpunkte (Koordinatendifferenz höchstens 100), berücksichtigt die gesamte Kartenhöhe und liefert Gebietsziele mit `type: territory`. Die aktive Welt stammt aus der Sitzung.

Spawn, Platzierung und Suche verwenden das gespeicherte Weltprofil. Der alte direkte Ressourcen-Seed leitet Luxemburg an den begrenzten gemeinsamen Welt-Spawn weiter. Stadtpositionen werden nicht automatisch repariert oder verschoben, sobald eine Welt das neue Profil verwendet.

## Vorschau einer bestehenden Welt

`php tools/preview-luxembourg-migration.php WORLD_ID > private-preview.json` erstellt einen Bericht in einer ausdrücklich nur lesenden Datenbanktransaktion. Der Bericht enthält vollständige weltbezogene Datensätze, auch über Städte, Märsche, Rallys und Schreine verknüpfte Garnisonen und Fortschritte, Prüfsummen, vorgeschlagene trockene Positionen und unerledigte Zuordnungen. Die Datei enthält Spielstände und gehört nicht in ein öffentlich erreichbares Verzeichnis.

Der Bericht führt keine Migration aus. Er benennt laufende Armeen, alte Zielansprüche und Landfortschritt als erforderliche Prüfpunkte. Ein produktiver Umzug braucht zuvor eine gesicherte Kopie, eine vollständig geprüfte Zuordnung sowie die Abstimmung aller Truppen-, Ressourcen- und Belohnungsbelege. Bis dahin werden vorhandene Welten unverändert weiterbetrieben.

## Lokale Welt 1, umgestellt am 30. September 2026

Die lokale Welt 1 wurde nach vollständiger Sicherung und erfolgreichem Probelauf auf einer separaten Datenbank auf `luxembourg` v1 (768 × 1.100) umgestellt. Die beiden additiven Schemamigrationen 0119 und 0120 sind lokal angewendet und registriert. Die eigentliche Übertragung lief atomar bei kurz pausierter Welt; der ursprüngliche Status `open` wurde wiederhergestellt.

Alle 49 bestehenden Städte verteilen sich über zwölf Kantone. 223 bestehende Kartenobjekte einschließlich Allianzgebäude wurden mit vollständigen trockenen, kollisionsfreien Grundflächen übernommen. IDs, Stadtentwicklung, Inventare, Forschung und stationierte Allianztruppen bleiben erhalten. Zwei bereits fällige Rückmärsche wurden zuvor mit der normalen Marschabrechnung abgeschlossen; die zurückgegebenen Truppen und die Beute wurden genau abgeglichen.

Die 1.024 ursprünglichen Landteile behalten ihre IDs, Stufen, Fortschrittspunkte und Beitragsbelege. Jeder erhält den nächsten noch freien trockenen Landteil derselben Entwicklungszone nahe seiner skalierten Position; die restlichen Teile werden regulär ergänzt (insgesamt 13.248). Ressourcen und Monster verweisen danach auf ihren tatsächlichen neuen Landteil. Historische Berichte, abgeschlossene Marschkoordinaten sowie alle 13 unbesetzten alten Schrein-/Kongressdatensätze bleiben als Historie erhalten. Alte Schreine reservieren auf Luxemburg keine Kartenfläche mehr. Die 100 Gemeinden, zwölf Kantonsziele und das Royal Castle wurden aus dem freigegebenen Katalog initialisiert.

Private Sicherungen, Zuordnungen und Prüfbelege liegen außerhalb des Webroots unter `C:/Users/svenm/.codex/backups/union-of-kingdoms/world1-lux-20260930-091502/`. Der Abgleich erfasst alle 153 Tabellen; während der Kartenübertragung wurden nur die vorgesehenen Kartenfelder geändert. Dieser einmalige lokale Vorgang erweitert nicht die automatische Leerwelt-Konfiguration und stellt keine anderen Server um.

Nachprüfung: frische Datenbankverbindung bestätigt `open`, Profil `luxembourg`, 49 Städte und die vollständigen Ziele. `luxembourg_world.php`, `world_placement.php` und `luxembourg_app_integration.php` bestehen, einschließlich echter Teleport-Aktionen in isolierten Testdatenbanken. Die Browserabnahme der migrierten Welt auf Desktop und Mobilgeräten benötigt eine Spielanmeldung; der aktuelle Browser zeigt die Anmeldeseite. Die temporäre Datenbankkopie wurde nach dem Probelauf entfernt; Sicherung und Prüfbelege bleiben erhalten.

## Nachweise

- `php tests/luxembourg_world.php`: isolierte Datenbank; sämtliche Rasterzellen, 100 Gemeinden, 113 trockene Ziele, Platzierung und Suche im Süden, echte Ressourcen-/Monster-Spawns, vollständige Initialisierung ohne alte Ziele, unveränderte bestehende Welt, ausschließlich lesende Migrationsvorschau und Platzierung trotz archivierter Schrein-Historie.
- `node tests/luxembourg_geometry_parity.mjs`: 10.708 Browser-/Server-Vergleiche für Geografie, Gewässerpunkte und ganze Rechteckflächen einschließlich Flussrändern.
- `php tests/world_placement.php`, `php tests/land_progression.php` und `php tests/congress_lifecycle.php`: gezielte Regressionen der bisherigen Welt.

Diese Prüfungen ersetzen weder die Abnahme der Haupt-App auf verschiedenen Bildschirmgrößen noch einen echten Mobilgerätetest oder den Lasttest für 1.000 gleichzeitig aktive Spieler.
