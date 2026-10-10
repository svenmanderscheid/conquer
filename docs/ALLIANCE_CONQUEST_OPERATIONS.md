# Union of Kingdoms – Gebietseroberung betreiben

Die Implementierung verwendet eine eigene Kartenvorlage je Welt. Es gibt keinen automatischen Umzug vorhandener Städte. Verbindliche Spielrichtung: [ALLIANCE_OBJECTIVES.md](ALLIANCE_OBJECTIVES.md). Abnahme: [ALLIANCE_CONQUEST_STATUS.md](ALLIANCE_CONQUEST_STATUS.md).

## Einführung einer neuen Luxemburg-Welt

1. Datenbank sichern und den auszuliefernden Stand mit allen benötigten Assets vorbereiten. Die Geografie- und Gewässerdateien unter `assets/world-lux-preview/` gehören zur Anwendung; der Server benötigt dieselben Dateien wie der Browser.
2. Ausstehende Migrationen prüfen. `0119_world_map_profiles.sql` und `0120_territory_conquest.sql` sind die neuen additiven Änderungen. Der vorhandene CLI-Lauf `php migrations/run.php` führt **alle** noch nicht registrierten SQL-Dateien in Reihenfolge aus; er ist kein auf diese beiden Dateien begrenzter Schalter. Zuerst auf einer gesicherten Kopie prüfen. Während dieser Entwicklung wurden nur synthetische Datenbanken migriert.
3. Im Backoffice eine neue Welt mit Kartenvorlage **Luxemburg** erstellen. Initialisierung legt 100 Communes, zwölf Shrines, das Royal Castle und die getrennten Landentwicklungsfelder an. Für eine bereits bewohnte Welt wird der direkte Profilwechsel abgewiesen.
4. Im Weltformular Eroberungsregeln einstellen. Vollständige Welt: alle Kantone, maximal zwei je Allianz. Alpha: zwei oder drei ganze Kantone auswählen; maximal ein Kanton je Allianz. In beiden Fällen bleibt die gesamte trockene Karte besiedelbar.
5. Den bestehenden Hintergrundlauf `php cron/march_tick.php` regelmäßig ausführen, beispielsweise jede Minute. Er verarbeitet Gebietsfeldzüge, Rückwege, Garnisonen, Kronenabschluss und Erträge zusätzlich zu den bestehenden Märschen. Verarbeitung ist in Pakete begrenzt; bei größerem Rückstand folgen weitere Läufe. Meldungen und Laufzeit überwachen. Eine geöffnete Spielseite ist für den Ablauf nicht erforderlich.
6. Mit zwei Testallianzen zuerst eine neutrale Gemeinde, Garnison und gegnerische Übernahme prüfen. Erst danach die Welt für die vorgesehene Spielergruppe freigeben.

Auf der neuen Kartenvorlage wird das alte Schrein-/Kongresssystem weder angelegt noch angeboten; die Shrines gehören zur neuen Gebietseroberung. Vorhandene andere Weltprofile behalten ihre bisherigen Systeme. Ein Rollback der Oberfläche darf gebundene Armeen und verdiente Ansprüche nicht löschen; betroffene Welten zuerst für neue Aktionen pausieren und den passenden Serverlauf bis zur geordneten Abwicklung beibehalten.

## Regelprofil und Ausgangswerte

Die Werte sind konfigurierbare Ausgangswerte für die Erprobung, keine endgültige Balance-Zusage aus dem Quellchat. Verwaltungszeiten sind ausdrücklich UTC; Spieler sehen lokale Termine.

| Bereich | Ausgangswert |
|---|---|
| Gemeinde-/Kantons-PvP | Täglich 17:00–21:00 UTC |
| Kronenkrieg | Alle 14 Tage, vier Stunden; Kalenderanker 28.09.2026 17:00 UTC |
| Neutrale Besatzung | Commune 1.400.000, Shrine 2.100.000, Kronenziel 2.800.000 T1-Wachen |
| Rohstoffgemeinde | 1.200 Einheiten ihres Typs je Besitzstunde in die Allianzkasse |
| Persönlicher Kampfsieg | 500 Gold je tatsächlichem Teilnahmebeleg |
| Unterstützung | 1.000 Nahrung; ein Beitrag je Spieler, Auftragstyp und UTC-Tag |
| Abtei | Fünf Forschungsbeschleuniger zu je fünf Minuten pro Allianz und Tag |
| Runenwacht | Drei gemeinsame Teleports pro Allianz und Tag, Radius 16 Felder |
| Regionale PvE-Versorgung | 5 % der garantierten regionalen Rohstoffbeute einschließlich des Grundwerts garantierter Rohstoffpakete, insgesamt höchstens 2.000 Einheiten pro Allianz/Tag |
| Shrine-Auftrag | Drei verschiedene Mitglieder liefern; einmal täglich je Allianz 500 von jedem Grundrohstoff |
| Hofämter | Eine Nutzung je Amt und Allianz/Tag; Schatzmeister 1.000 je Grundrohstoff, übrige Ämter fünf Minuten Beschleunigung |

Mehrere Abteien, Runenwachten oder Amtswechsel vervielfachen die gemeinsamen Tageskontingente nicht. Regionale Versorgung wird einmal pro besiegtem Monster gutgeschrieben; auch bei einer Rally vervielfacht die Teilnehmerzahl sie nicht. Zufällige Beute und persönliche Beuteverstärkungen erhöhen diesen Grundwert nicht. Die eigenen Rohstoffpakete bleiben dem Spieler erhalten.

Marschall beschleunigt laufende Heilung, Baumeister einen laufenden Bauauftrag und Hofmagier einen laufenden Forschungsauftrag des Amtsträgers. Der Schatzmeister zahlt in die Allianzkasse ein. Alle vier Fähigkeiten verändern echte bestehende Aufträge beziehungsweise Ressourcen.

Regeln werden mit einer Versionsnummer gespeichert. Veraltete Admin-Formulare werden abgewiesen. Bereits gestartete Feldzüge behalten ihr Regelprofil und ihre beim Start geprüfte Berechtigung; Kantonsplätze bleiben bis Abschluss oder Abbruch reserviert. Kampfboni und Geschwindigkeit werden beim Reservieren jeder Armee gespeichert. Abgelaufene Talismane und später abgeschlossene Forschung verändern entsandte Armeen nicht nachträglich.

Laufender Gebietsertrag wird bei einer Profiländerung zunächst bis zum Änderungszeitpunkt nach dem bisherigen Satz abgerechnet. Anschließend gilt der neue Satz, auch wenn eine schon vorher gestartete gegnerische Rally das Gebiet später übernimmt. Deren gespeicherte Kampfregeln und persönliche Siegesbelohnung verändern den Ertrag des bisherigen Besitzers nicht.

Die Krone verlangt Tor, Arsenal und Thron. Qualifiziert ist, wer alle drei während des laufenden Krieges mindestens einmal übernommen hat. Es zählen die aufsummierten Kontrollsekunden, dann die früheste erste Kontrolle, zuletzt die stabile Allianzkennung. Ohne qualifizierten Sieger bleibt die bisherige Herrschaft bestehen. Ein verlorener Kanton beendet keine bereits berechtigten Kronenangriffe; jeder neue Angriff prüft den Zugang erneut.

## Bestehende Welten

`php tools/preview-luxembourg-migration.php WORLD_ID` erzeugt einen ausdrücklich schreibgeschützten Bericht mit vorgeschlagenen Stadtpositionen, Prüfsummen und noch zuzuordnenden Spielständen. Er führt keinen Umzug aus. Berichtdateien enthalten Spielstände und gehören außerhalb des öffentlichen Webverzeichnisses.

Ein vorhandener Spielstand benötigt vor einer Umstellung eine vollständig geprüfte Zuordnung von Städten, laufenden Armeen, Garnisonen, Landfortschritt und Belohnungsansprüchen. Dieser gesonderte Umzug wurde hier nicht ausgeführt. Details stehen in [WORLD_MAP_PROFILES.md](WORLD_MAP_PROFILES.md).

## Wiederholbare Abnahme

Serverprüfungen verwenden `tests/Support/FeatureDatabase.php` und erstellen eigene synthetische Datenbanken. Die bisherige Anwendung nutzt serverweit benannte Spielersperren; daher mehrere vollständige Laufzeitsuiten auf demselben lokalen MySQL-Server nacheinander ausführen. Der gezielte Parallelitätstest startet seine konkurrierenden Prozesse selbst.

- `php tests/luxembourg_world.php`
- `node tests/luxembourg_geometry_parity.mjs`
- `php tests/territory_lifecycle.php`
- `php tests/territory_npc_balance.php`
- `php tests/territory_ordering.php`
- `php tests/territory_http.php`
- `php tests/luxembourg_app_integration.php`
- `php tests/territory_pve.php`
- `php tests/territory_income_transition.php`
- `php tests/multiworld_integration.php`
- `php tests/kingdom_regressions.php`

Für die echte Oberfläche startet `php tools/preview-feature-fixture.php --territory --port=18946` eine lokale synthetische Welt mit den aktuellen NPC-Stärken. Enter beendet den Server und räumt seine Datenbank auf. `tests/territory_main_app.cjs` prüft darauf Haupt-App und Backoffice in fünf Bildschirmgrößen.

Für `tests/territory_main_report.cjs` stattdessen eine frische Vorschau mit `php tools/preview-feature-fixture.php --territory --territory-report --port=18946` starten. Nur dieser ausdrückliche Berichtstest verwendet kleine eigene Besatzungen von 120/800/1.600 Wachen, damit die echte Rally mit 1.000 Truppen einen Siegesbericht erzeugt. Die gesetzte NPC-Balancerevision bewahrt diese Testwerte; die gewöhnliche Vorschau und Spielwelten behalten ihre reguläre Stärke. Ohne diesen Schalter bricht der Berichtstest vor dem ersten Angriff mit einem Hinweis ab.

`tests/territory_frontend.cjs` prüft zusätzlich verlorene Antworten und Berichte. Diese Testprogramme benötigen die vorhandene Playwright-Laufzeit. Zugangsdaten der Vorschau stehen nur im Fixture-Quelltext und gelten ausschließlich für diese synthetische Umgebung.

Ein echter Mobilgerätetest und der Lastnachweis für 1.000 gleichzeitige Spieler sind gesonderte Freigabeschritte und wurden nicht durch Desktop-Emulation ersetzt.
