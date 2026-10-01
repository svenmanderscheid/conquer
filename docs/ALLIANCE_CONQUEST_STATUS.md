# Union of Kingdoms – Eroberung: Umsetzung und Abnahme

Aktuelle Benennung (29. September 2026): **Commune → Shrine → Royal Castle**. Kantone bleiben die geografischen Regionen; ihr Eroberungsziel heißt Shrine. Frühere Bezeichnungen im dokumentierten Entwurfs- und Prüfverlauf sind historische Namen derselben Ziele. Verbindliche Zuordnung: [TERRITORY_NAMING.md](TERRITORY_NAMING.md).

Stand: 28. September 2026. Maßgeblich bleiben `ALLIANCE_OBJECTIVES.md` und die fünf Pakete in `ALLIANCE_CONQUEST_IMPLEMENTATION.md`. Alle fünf Pakete sind implementiert; Einführung und Betriebsregeln stehen in [ALLIANCE_CONQUEST_OPERATIONS.md](ALLIANCE_CONQUEST_OPERATIONS.md).

## Stand der Pakete

| Paket | Umsetzung | Unabhängige Abnahme |
|---|---|---|
| Luxemburg-Karte | Versioniertes Weltprofil, gemeinsame Geometrie/Gewässer, 113 Ziele, rechteckige Platzierung, Suche, Spawns und Hauptkarte | Sämtliche Gemeindezellen und 10.708 Browser-/Server-Geometrievergleiche bestanden; südliche Platzierung, Teleport und Haupt-App geprüft |
| Gemeinden | Echte Rally-Armeen, NPC-/PvP-Kampf, Garnisonen, Rückkehr, Berichte, Offline-Verarbeitung | Lebenszyklus, häufige/verspätete/parallele Verarbeitung und reale UI-Eroberung bestanden |
| Erträge/Ziele | Besitzintervalle, Kassenbelege, persönliche Ansprüche, Allianz-Ziele, Abtei und Runenwacht | Ertrag vor/nach Besitzwechsel, verlorene Antworten, gemeinsames Kontingent, Inventargegenstand und Teleport geprüft |
| Kantone | Strikte Mehrheit, reservierte Besitzplätze, Alpha-/Weltlimit, gespeicherte Angriffsberechtigung, regionaler Vorteil und Auftrag | Gerade/ungerade Mehrheiten, gleichzeitige Reservierung, Abbruch/Freigabe und gemeinsamer Auftrag bestanden |
| Krounbuerg | Drei Belagerungsziele, Kontrollzeit, Gleichstand, Herrschaft, Titel und vier Hofämter | Pflichtziele, letzter Angriff gegenüber Kontrollzeit, verspäteter Abschluss und reale Amtswirkungen bestanden |

## Bereits vom Hauptagenten ausgeführte Prüfungen

- `tests/multiworld_integration.php`: bestanden; Weltwechsel, Besitz, Ausgaben und bestehende Spielwege bleiben getrennt.
- `tests/kingdom_regressions.php`: bestanden; bestehende Macht-, Aufgaben- und Inventarregeln.
- `tests/luxembourg_app_integration.php`: bestanden; tatsächliche Admin-Welterstellung, wiederholsichere Erstellung, 113 Ziele, trockene OAuth-Städte, südlicher Teleport, einmaliger Gegenstandsverbrauch und Garnisons-Macht/Teleportsperre.
- `tests/territory_http.php`: bestanden; tatsächlicher Frontcontroller mit Anmeldung, CSRF, Rollen, Weltzuordnung, Zielabfrage, Befehlsbelegen und pausierter Welt. Dabei gefundene Tabellen-Kollationsabweichungen wurden vor der erfolgreichen Wiederholung behoben.
- `tests/territory_lifecycle.php`: bestanden; vollständiger Gemeinde-/Kantons-/Kronenweg, tatsächliche Verwundete, Garnisonen, persönliche Ansprüche, Abtei, Runenwacht, Kantonsauftrag und vier wirksame Ämter.
- `tests/territory_ordering.php`: bestanden; unabhängig vom implementierenden Backend-Agenten erstellt und zusätzlich vom Hauptagenten ausgeführt. Häufige, verspätete, parallele und auf ein Ereignis begrenzte Verarbeitung ergeben dieselben Besitzer, Armeen, Verwundeten, Berichte, Kronenpunkte und Erträge. Über 1.000 andere Garnisonen verdrängen keine fällige Zielverteidigung. Ablaufende Talismane, spätere Forschung und Mitgliedschaftswechsel sind geprüft.
- `tests/luxembourg_world.php` und `tests/luxembourg_geometry_parity.mjs`: bestanden; vollständige Geografie, trockene Grundflächen, südlicher Kartenbereich, echte Spawns und schreibgeschützte Umstellungsvorschau.
- `tests/territory_pve.php`: abschließende unabhängige Wiederholung bestanden; echter Monsterzug mit 250 Nahrung aus dem garantierten Paketwert für die Allianzkasse, persönlicher Beute erst bei Heimkehr und echter Sammelzug mit genau 500 Nahrung. Wiederholte Verarbeitung vervielfacht keine Truppen, Beute oder Belege.
- `tests/territory_income_transition.php`: bestanden; Profiländerung während einer anreisenden Rally rechnet laufenden Besitz zum neuen Ertragssatz ab, während die persönliche Kampfbelohnung beim ursprünglichen Regelstand bleibt.
- `tests/monster_rallies.php`: abschließende unabhängige Wiederholung bestanden; alle 75 bestehenden Rally-/Talismanprüfungen. Auch `territory_ordering.php` wurde nach der letzten Ertragskorrektur erneut erfolgreich ausgeführt.
- PHP-Syntaxprüfung der vom Hauptagenten integrierten Routen, Handler, Admin-Ansichten, Stadtgründung, Inventarwege und Hintergrundverarbeitung: bestanden.

Alle diese Laufzeitprüfungen verwenden getrennte, synthetische Datenbanken. Vorhandene Spieler und Welten wurden nicht konvertiert.

## Abnahme der Oberfläche

Die echte Haupt-App wird mit `tools/preview-feature-fixture.php --territory` gegen eine synthetische Luxemburg-Welt gestartet. Hauptagent und Oberflächenagent verwenden getrennte Instanzen.

- Desktop-Stadt lädt mit Gebäuden und Navigation; Weltkarte zeigt den südlichen Stadtstandort und echte Gemeindeziele.
- Gemeindedialog zeigt tatsächlichen Besitzer, NPC-Verteidigung, Ortszuordnung und Kampfzeit.
- Der Hauptagent hat in der echten Haupt-App eine Gemeinde-Rally gestartet, den tatsächlichen Besitzwechsel beobachtet, die verdiente Belohnung abgeholt sowie Garnison und Rückruf ausgeführt.
- Ein frischer Teststand wurde anschließend vom Hauptagenten bei 390 × 844, 320 × 568 und 844 × 390 visuell geprüft. Hauptkarte, gemeinsame Ziele und Kronenansicht sind erreichbar; Browserfehler wurden nicht beobachtet.
- Der Oberflächenagent hat Haupt-App und Admin-Regelformulare bei 1280 × 800, 390 × 844, 320 × 568, 844 × 390 und 568 × 320 geprüft. Zusätzliche Prüfungen decken verlorene Antworten, unveränderte Vorgangskennung nach Neuladen und tatsächliche Gebiets-Kampfberichte ab.
- Screenshots der tatsächlichen Eroberung und Berichte liegen unter `artifacts/territory-main/`; der Hauptagent hat die Desktop-, Handy- und Querformatbelege geprüft.

Bei der Abnahme gefundene Fehler wurden korrigiert: dunkle Titel auf violettem Kopf, abgeschnittene Querformatsteuerung, englische Kampfzustände, alte Schrein-Kalendereinträge, fehlende Berichtszuordnung, falsche Reisezeitanzeige und weltfremde Allianzverknüpfungen der Kartenabfrage. Unbekannte NPC-Verluste werden nicht als erfundene Zahlen angezeigt. Die endgültige 320-Pixel-Übersicht wurde nach der Titelkorrektur zusätzlich anhand ihres Screenshots geprüft.

## Abschluss der fünf Pakete

Die Abnahmekriterien des Implementierungsplans sind durch die oben genannten Dienste-, HTTP-, Geometrie- und Oberflächenprüfungen erfüllt. Die abschließende Prüfung erfolgte nach den letzten Korrekturen an regionalen Rohstoffpaketen und Ertragsänderungen. Temporäre Vorschauprozesse wurden beendet und deren synthetische Datenbanken bereinigt. Keine Produktivmigration, Veröffentlichung oder Umstellung vorhandener Welten wurde ausgeführt.

## Einführung

Die neuen Migrationen sind additiv. Neue Luxemburg-Welten werden explizit mit dem Profil `luxembourg` erstellt; vorhandene Welten bleiben in ihrem bisherigen Profil. `tools/preview-luxembourg-migration.php` erstellt einen schreibgeschützten Umstellungsbericht für bestehende Welten. Offene Armeen, Garnisonen, Ansprüche und Landfortschritt dürfen nicht durch Zurücksetzen oder Umbenennen verschwinden.

Zusätzliche Schatten-Invasionen, Weltbosse und Turniere sind im Konzept als spätere Erweiterungen vorgesehen. Sie sind keine zusätzlichen Voraussetzungen für Gemeinde → Kanton → Krounbuerg.

Ein Test auf einem echten Mobilgerät und ein Lasttest mit 1.000 gleichzeitig aktiven Spielern wurden bisher nicht durchgeführt.
