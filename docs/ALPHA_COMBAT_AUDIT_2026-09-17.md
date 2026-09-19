# PvE/PvP und Alpha-Prüfung · 17. September 2026

## Urteil und Geltungsbereich

Der Kern ist für einen betreuten, geschlossenen Spieltest weit genug: Stadt-PvP, Monsterjagd, Sammeln, Rallys, Expeditionen, Dungeons, Berichte und Rückkehr/Heilung wurden mit echten Diensten und isolierten lokalen MySQL-Datenbanken geprüft. Daraus folgt weder Fehlerfreiheit des gesamten Spiels noch eine Freigabe für eine öffentliche Alpha. Die nachfolgende Prüfung ergänzt die parallel ausgeführte Menü-, Mobil- und Inventarprüfung.

Als organisatorischen Einstieg empfehlen wir **10–20 gleichzeitig aktive, eingeladene Tester**, auf zwei Allianzen verteilt und mit vereinbarten Testzeiten. Diese Zahl ist **keine gemessene Serverkapazität**. Vor dem ersten externen Termin müssen die Testinstallation, HTTPS, Sicherung/Wiederherstellung und die tatsächliche Ausführung der Hintergrundjobs stehen. Ohne diese Betriebsvoraussetzungen ist der jetzige Stand nur lokal freigegeben.

## Belegte Prüfungen

Alle Datenbanktests verwenden zufällig benannte, anschließend entfernte Testdatenbanken; vorhandene Spieler wurden nicht kopiert oder verändert. Externe Hosts wurden nicht belastet. Die verwendeten Fixtures wurden vor dem Start auf diese Eigenschaft geprüft. Die Ergebnisse liegen unter `artifacts/alpha-audit-2026-09-17/`.

| Suite | Bestandene Prüfungen | Schwerpunkt |
| --- | ---: | --- |
| `mvp_rules.php` | 13 | Produktion, Kapazität, Monstergrundregeln |
| `research_combat.php` | 23 | Arena, Truppentyp-/Forschungsboni, Feldzugkapazität |
| `dungeon_rules.php` | 274 | Sechs Dungeons, beide Schwierigkeiten, deterministische Kämpfe, Beratung |
| `march_composition.php` | 57 | Echte HTTP-Märsche, atomare Reservierung, Mengen-/Truppentypprüfung |
| `city_combat.php` | 75 | Solo-PvP, Stadt-Rallys, Schutz, Angreifer-/Verteidigerberichte, parallele Abrechnung |
| `combat_reports.php` | 15 | Historische Kampfdaten und Zugriffsgrenzen |
| `monster_reports.php` | 23 | Monsterkampfberichte, historische Armeen und Beute |
| `monster_rallies.php` | 73 | Gruppenangriffe, Verteilung, Abbruch und Rückkehr |
| `regional_bosses.php` | 156 | Regionale Bossdefinitionen und wirksame Regeln |
| `regional_rewards.php` | 18 | Regionale Beute |
| `expedition_lifecycle.php` | 56 | Zwei Allianzen, parallele Angriffe/Claims, Austritt, Ablauf, Offline-Abrechnung |
| `expedition_lifecycle.php --http` | 59 | Echter Frontcontroller, Sitzung, CSRF, reale 20-Sekunden-Marschzeiten |
| `dungeon_lifecycle.php` | 77 | Reservierung, Abstimmung, Sieg/Niederlage, Konkurrenz, persönliche Beute, HTTP |
| `defense_lifecycle.php` | 119 | Späher, Verstärkung, Rückruf, Weltbindung, Authentifizierung/CSRF |
| `hospital_healing.php` | 53 | Bezahlte Heilung, Bestände, Abschluss und Wiederholungen |
| `gathering_lifecycle.php` | 30 | Sammeldauer, Besetzung, Abrechnung und Rückkehr |
| `multiworld_integration.php` | 39 | Weltwahl, Besitz, Guthaben, Allianzen, Rückkehr und Itemwirkung |

Damit bestanden **1.160 Einzelprüfungen in 16 unterschiedlichen Suiten bzw. 17 Durchläufen** einschließlich des zusätzlichen Expedition-HTTP-Modus. Die PHP-Syntax der acht angepassten Test-/Benchmarkdateien und das Dungeon-JSON wurden ebenfalls geprüft. `git diff --check` meldete keine Whitespacefehler.

Die Arena ist hier durch die Berechnungsregeln abgedeckt; ein vollständiges freiwilliges Zwei-Spieler-Duell im Browser war nicht Teil dieses unabhängigen Teilauftrags. Die beiden historischen Arena-HTTP-Spieltests verwenden normale registrierte Konten und wurden deshalb nicht gegen vorhandene Spielerdaten gestartet.

## Behobene Abweichungen

Die Dungeonberatung enthielt nach dem Truppenumbau zwei ungünstige Formationen. Bei gleichem Gesamtbestand ergab der vorhandene Simulator für die umgekehrte Aufteilung mehr verbleibende Gruppen-LP. Korrigiert wurden ausschließlich Empfehlungen und ihre Erläuterung:

- Frosthöhle: 50 % Infanterie, 15 % Fernkämpfer, 35 % Kavallerie.
- Sturmspitze: 45 % Infanterie, 40 % Fernkämpfer, 15 % Kavallerie; Name „Sturmvorhut“.

Kampfwerte, Gegner, Drops und bestehende gespeicherte Läufe wurden nicht neu balanciert. Die Sicherheitsberechnung benutzt weiterhin die tatsächlichen Truppenwerte und fünf deterministische Referenzläufe. Alle 274 Regelprüfungen bestanden nach der Korrektur, einschließlich der Formationsvergleiche aller sechs Dungeons.

Mehrere historische Tests waren gegenüber dem Spielstand veraltet. Sie wurden angepasst, ohne reale Spielregeln abzuschwächen:

- Der Produktionsregeltest benötigt seit den zusätzlichen Bauplätzen eine Datenbank; er verwendet jetzt ebenfalls eine wegwerfbare Fixture.
- Expeditionen und Dungeon-Lebenszyklen erwarteten Siege mit alten T1-Werten (z. B. 45 Angriff statt heute 1 beim Schwertkämpfer). Die Testarmeen wurden vergrößert, nicht die Gegner oder Kampfergebnisse verändert. Schwache Armeen müssen weiterhin tatsächlich verlieren; Mengen, Reservierung und Rückkehr werden exakt geprüft.
- Verteidigungsprüfungen akzeptieren den konkreten aktuellen 422-Fehler der Bauabbruchroute und prüfen zusätzlich, dass keine der fremden Weltwarteschlangen verändert wurde.
- Welt-/Benchmarktests lesen die vollständige kanonische Gebäudeliste statt der historischen festen Zahl 13. Die Weltprüfung behandelt verbotene Kristallkäufe nach der geltenden Kristallökonomie und verwendet selbst angelegte Itembestände für die Weltbindung.

## Betrieb und Kapazitätsgrenzen

Die lokal gelesene Konfiguration ist `development`, die Datenbank lokal, PHP 8.2.12. Eine getrennte externe Alpha-/Staginginstallation wurde aus den vorliegenden Informationen nicht bestätigt. In den lesbaren Windows-Aufgaben wurde kein Auftrag für Conquer, `march_tick`, `world_spawn_tick`, `expedition_tick` oder `dungeon_tick` gefunden. Das ist kein Nachweis, dass ein externer Hoster keine Jobs betreibt; dessen Konfiguration wurde nicht angefasst.

Der vorhandene Gesamtworker `cron/march_tick.php` verarbeitet Märsche, Expeditionen, Dungeons, Rallys, Kongress, Gemeinschaft und Ereignisse. `cron/world_spawn_tick.php` ist zusätzlich nötig. Die Projektanleitung sieht beide minütlich vor. Browserabfragen gleichen ebenfalls fällige Vorgänge ab; das ersetzt für eine externe Alpha keinen nachgewiesenen Hintergrundbetrieb ohne offene Browser.

Die Haupt-App aktualisiert alle **5 Sekunden** mindestens `game/state`, `kingdom/state` und `expeditions/state`; hinzu kommen bereichsspezifische Anfragen. Die ältere README-Angabe 15/60 Sekunden beschreibt diesen aktuellen Pfad nicht. Dieselbe Sitzung aktualisiert pro authentifizierter Anfrage `last_active`. Es gibt keine klassische blockierende PHP-Dateisitzung, aber mehrere Datenbank-Sperren:

- Pro Spieler sperren kritische Vorgänge mit `conquer-player-ID`.
- Stadt-PvP und Stadt-Rallys benutzen zusätzlich den globalen Lock `conquer-city-combat`, auch über Weltgrenzen hinweg.
- Diese MySQL-Namen sind serverweit: gleichzeitige Testdatenbanken mit denselben Fixture-Spieler-IDs können einander blockieren. Nach beobachteten Kollisionen wurden die entsprechenden Tests deshalb zwischen den Agenten zeitlich abgestimmt. Am Ende des Benchmarks überschnitt sich ein kurzer UI-Test; die Hintergrundlast war somit nicht vollständig kontrolliert.

Der ältere Benchmark in `BENCHMARK.md` hat 200 angelegte Spieler, aber **Parallelität 1**. Er misst keine 200 gleichzeitig spielenden Menschen. Auch ein wiederholter lokaler Dienstetest kann Hosting, HTTP-Verkehr, fünfsekündige Aktualisierungen, lange Berichtsverläufe und große gleichzeitige Schlachten nicht ersetzen.

Der erneute Benchmark am 17. September 2026 um 13:04:19 UTC bestand mit 200 synthetischen Spielern in zwei Welten, 30 Aufwärmpaketen und 30 Messpaketen. Ein Paket umfasst Stadt, Königreich, Gemeinschaft und Ereignisse. Median **84,054 ms**, P95 **109,996 ms**, Maximum **124,075 ms**; der einmalige Spawnlauf erzeugte 50 Rohstofffelder und 50 Monster in **357,448 ms**. PHP 8.2.12/MariaDB 10.4.32 unter Windows, CLI-OPcache aus. Vollständiges Ergebnis: `artifacts/alpha-audit-2026-09-17/benchmark-current.json`. Es wurde kein HTTP-Lasttest behauptet oder gegen einen produktiven Host ausgeführt.

## Offene Freigabepunkte

1. **Externe Testinstallation und Betrieb:** konkrete URL/Host, HTTPS, regelmäßig tatsächlich laufende Worker, Fehlermeldungen sowie erfolgreich erprobte Wiederherstellung eines Backups. Nicht in diesem Auftrag bereitgestellt.
2. **Wiederholung nach verlorener Antwort:** vorhandene Kämpfe/Rallys werden nachweislich nur einmal abgerechnet. Monster-/Charm-Entsendungen besitzen Vorgangskennungen; die Stadt-PvP-/Rally-Startwege haben noch keinen durchgehenden Vertrag, der einen erneut gesendeten Entsendebefehl nach Verbindungsabbruch auf denselben Auftrag abbildet. Kein pauschales Versprechen für sämtliche schreibenden Spielaktionen.
3. **Lastmessung auf dem vorgesehenen Host:** realistische angemeldete Spieler mit der tatsächlichen Abfragefrequenz, Schreibvorgängen, gleichzeitigen Kämpfen und gefüllter Historie. Ein schrittweiser Test mit 5/10/20/40 aktiven Sitzungen wäre der nächste Nachweis. Antwortzeiten, Fehler-/Timeoutquote, Lock-Wartezeiten, Datenbank-/PHP-Auslastung und Rückstand der Worker müssen dabei protokolliert werden. Keine daraus abgeleitete Kapazität ohne Messwerte behaupten.
4. **Echte Handys und längere Spielrunde:** Layoutprüfungen allein messen weder flüssige 3D-Darstellung noch Speicherverbrauch, Erwärmung oder zuverlässige Wiederaufnahme nach App-Wechsel. Die parallel durchgeführte 3D-Prüfung des Hauptagenten liefert zusätzliche Beobachtungen, aber keinen Test echter iOS-/Android-Hardware.
5. **Langfristige Spielbalance:** Fortschritt, Ressourcenquellen/-senken, Truppenverluste, Gruppengrößen und Belohnungsrhythmus mit echten Testspielern beobachten. Die heutigen Lebenszyklen belegen technische Abrechnung, nicht eine dauerhaft ausgewogene Ökonomie.

Für einen betreuten ersten Pilottermin sind 10–20 gleichzeitige Tester ein vorsichtiger Organisationsvorschlag. Eine öffentliche Einladung oder ein Versprechen von 100, 500 oder mehr gleichzeitigen Spielern wäre mit dieser Evidenz nicht begründet.
