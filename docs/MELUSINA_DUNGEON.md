# Melusina: der versiegelte Brunnen

`melusina_well` ergänzt Union of Kingdoms um den ersten dauerhaft verfügbaren Kantons-Dungeon. Er ist ausschließlich in Welten mit dem Kartentyp `luxembourg` verfügbar. Die sechs bisherigen Dungeons behalten ihre unveränderte wöchentliche Dreierrotation; Melusina wird zusätzlich angezeigt. Besitz eines Kantons oder Mitgliedschaft in einer bestimmten Allianz sind keine Zugangsvoraussetzungen.

## Sage und Spielgeschichte

Die luxemburgische Melusina-Sage ist das Vorbild. Die staatliche Darstellung der [Ursprünge Luxemburgs](https://luxembourg.public.lu/de/gesellschaft-und-kultur/geschichte/urspruenge-luxemburgs.html) und die im staatlichen [Unterrichtsmaterial zum Trinkwasser](https://eau.gouvernement.lu/dam-assets/publications/2023/lehrerhandreichung/2023-eist-drnkwaasser-guide-pour-lenseignant-de-lr-m.pdf) wiedergegebene Variante mit dem goldenen Schlüssel dienen als Quellen für den erzählerischen Rahmen.

Der zuerst zusammengesetzte Brunnenschlüssel ist ein eigener, erfundener Zugangsschlüssel. Er ist nicht Melusinas goldener Schlüssel aus der Sage. Überflutete Gänge, Schlüsselfragmente, Brunnenwächter, Gruppenprüfung und die erfolgreiche Befreiung sind unsere Spieladaption; die Oberfläche kennzeichnet das Abenteuer entsprechend. Melusina ist keine bekämpfte Bossgegnerin: Der erfundene Brunnenwächter steht am Ende der Kampfstrecke, bevor Melusinas goldener Schlüssel zum erzählerischen Abschluss gehört.

## Spielablauf

1. Der Spieler öffnet den Eingang auf der Weltkarte oder den Dungeon-Bereich und nimmt die Suche an.
2. Erfolgreiche Solo-Monsterkämpfe und vollständig abgebaute Rohstoffstellen im Kanton Luxembourg (`03`) können ein Brunnenschlüsselfragment liefern. Die Oberfläche zeigt aktuelle geeignete Quellen mit Kartenkoordinaten.
3. Drei austauschbare Fragmente werden zu einem Brunnenschlüssel zusammengesetzt. Fragmente und Schlüssel gehören einem Spieler in genau einer Welt.
4. Eine Gruppe aus zwei bis vier Spielern bereitet den Lauf im vorhandenen Dungeon-Planer vor. Der Gruppenleiter stellt beim Start genau einen Schlüssel für die ganze Gruppe. Die anderen Mitglieder benötigen weder einen eigenen Schlüssel noch eine angenommene Schlüsselsuche.
5. Die Gruppe durchquert die vorhandene kooperative Kampf- und Abstimmungsstruktur: zwei Begegnungen, die optionale Kammer der Alzette und der Brunnenwächter. Die gespeicherten Kampfwerte, Schwierigkeiten und Rollenregeln gelten wie bei den anderen Dungeons.
6. Erfolg verbraucht den reservierten Schlüssel und erzeugt persönliche Beuteansprüche. Niederlage oder vom Gruppenleiter ausgelöster Abbruch geben denselben Schlüssel zurück. Während eines unvollständigen Laufs wird keine Dungeon-Beute ausgezahlt.

Der erste erfolgreiche Abschluss wird je Mitglied und Welt als `first_clear_at` gespeichert. Spätere Erfolge erhöhen `completed_runs` und werden als Echo der Sage erzählt. Der beim Beitritt gespeicherte Erzählmodus sorgt dafür, dass der erste Bericht nach dem Abschluss seine ursprüngliche Geschichte behält. Ein Browserwechsel oder Verbindungsabbruch bricht keinen Lauf ab; Polling und Cron setzen den gespeicherten Ablauf fort.

## Fragmente und vorläufige Balance

Die Werte liegen serverseitig in `data/melusina.json` und sind ein erster, noch nicht durch Spieltelemetrie bestätigter Balanceentwurf:

| Regel | Aktueller Wert |
| --- | --- |
| Fragmente für einen Schlüssel | 3 |
| Zufallschance pro geeigneter Aktion | 35 % |
| Garantierter Fund spätestens | bei der fünften geeigneten Aktion seit dem letzten Fund |
| Fundmenge | 1 Fragment |
| Angezeigte Quellen | bis zu 3 Monster und 3 Rohstoffstellen |

Jeder Fund setzt den Zähler zurück. Ohne Fund steigt er um eins. Die Garantie zählt ausschließlich geeignete Ereignisse nach Annahme der Suche; die eigentliche Ankunft beziehungsweise Sammelfertigstellung entscheidet, nicht der spätere Zeitpunkt eines Offline-Ticks.

Ein Fragment wird zunächst der Rückkehrbeute des Marsches hinzugefügt. Es wird erst bei der Heimkehr im Inventar verfügbar. Der Dungeon-Bereich unterscheidet vorhandene und noch zurückkehrende Fragmente. Monsterbericht, Rückkehrbeute und gespeicherter Monster-Belohnungsbeleg enthalten denselben tatsächlich vergebenen Gegenstand.

## Genaue Berechtigung einer Aktion

- Die Welt muss das freigegebene Luxembourg-Kartenprofil verwenden, und die tatsächlichen Zielkoordinaten müssen laut `LuxembourgGeography::at()` zum Kanton `03` gehören. Clientangaben, Allianzbesitz und die Position der Heimatstadt ändern diese Prüfung nicht.
- Bei Monstern zählen ausschließlich Siege über aktive Solo-Monster. Das Monster muss vor dem Kampf noch leben und bei der gespeicherten Marschankunft noch nicht abgelaufen sein; `expires_at = NULL` bleibt zulässig. Niederlagen, beschädigte aber überlebende Monster, Rallys und bereits anderweitig erledigte Ziele erzeugen keinen Versuch.
- Bei Rohstoffstellen muss ein normal abgeschlossener Sammelmarsch die gesamte verbliebene Ressourcenmenge entnehmen. Eine volle Traglast bei noch vorhandenem Feldbestand zählt nicht. Teilrückruf, Verdrängung und vorzeitiger Rückruf zählen nicht. Bereits begonnene Sammlung folgt weiterhin dem bestehenden Belegungsmodell und darf nach Ablauf des ursprünglichen Feldtimers fertig werden.
- Kartenhinweise zeigen nur lebende, aktive, nicht abgelaufene Solo-Monster sowie freie, nicht abgelaufene und nicht leere Rohstoffstellen. Die Liste ist ein aktueller Hinweis, keine Zielreservierung; die normale Marschprüfung bleibt maßgeblich.

## Speicherung und Wiederholschutz

`MelusinaProgress` führt Fortschritt, Herstellung und Schlüsselverbrauch zusammen:

- `melusina_progress`: Annahmezeitpunkt, Zähler ohne Fund, Erstabschluss und Abschlussanzahl, jeweils für Spieler und Welt.
- `melusina_drop_events`: genau ein Beleg je Welt und Monster-/Rohstoffstellen-ID. Beleg, Zufallswurf, Zähler, Kampf-/Sammelergebnis und Marschbeute gehören zu derselben Transaktion.
- `melusina_operations`: Annahme und Herstellung mit einer vom Client erzeugten `request_id`. Wiederholte Antworten führen die Herstellung nicht erneut aus; eine Kennung darf nicht für eine andere Aktion wiederverwendet werden.
- `melusina_key_reservations`: ein Schlüssel je Lauf, mit den Zuständen `reserved`, `consumed` und `returned`. Die gespeicherte Welt bestimmt den Rückgabeort auch dann, wenn ein Hintergrundtick gerade in einer anderen Welt läuft.
- `player_inventory`: Gegenstände `10309001` und `10309002` verwenden die Kategorie `dungeon_quest` und damit die jeweilige Welt als Inventarbereich.
- `world_dungeon_entrances`: dauerhaft gespeicherter, kollisionsfrei zugewiesener Eingang je Welt. `DungeonEntrance` und `WorldPlacement` reservieren ihn im selben Platzierungsmodell wie andere Kartenobjekte.

Die Fortschrittszeile wird direkt exklusiv gesperrt. Dadurch benötigen parallele Herstellungsanfragen keine problematische Hochstufung einer gemeinsamen Lesesperre. Fehler rollen Belege, Zähler und Inventaränderungen gemeinsam zurück. Reguläre Marsch-Heimkehr zahlt den gespeicherten Fund höchstens einmal aus.

## Schnittstellen

Der vorhandene authentifizierte Dungeon-Zustand ergänzt `melusina`. Darin stehen unter anderem `available`, `accepted`, `fragments`, `fragments_required`, `in_transit_fragments`, `keys`, `reserved_keys`, `can_craft`, `pity_count`, `pity_limit`, `actions_until_guaranteed`, `drop_chance`, `first_clear_at`, `completed_runs`, `entrance` und `sources`.

`POST /api/dungeons/action` erhält zwei zusätzliche Aktionen:

```json
{"action":"accept_melusina","expected_world_id":1,"request_id":"client-generated-uuid"}
```

```json
{"action":"craft_melusina_key","expected_world_id":1,"request_id":"another-client-generated-uuid"}
```

Beide verwenden die bestehende Sitzung, CSRF-Prüfung und Weltprüfung. Herstellung setzt exakt einen Schlüssel zusammen. Erstellen, Beitreten, Vorschau, Start, Abstimmung, Abbruch und Beuteabholung verwenden die bestehenden Dungeon-Aktionen mit `dungeon_code: "melusina_well"`.

## Migration und Prüfung

Die additiven Migrationen sind `0131_melusina_dungeon.sql` und `0132_dungeon_entrances.sql`. Sie werden gemeinsam durch `C:\xampp\php\php.exe tools\migrate-melusina.php` angewendet. Der vorhandene Dungeon-Grundbestand aus Migration `0082` wird vorausgesetzt. Am 5. Oktober 2026 wurden beide Migrationen auf der lokalen Entwicklungsdatenbank `conquer_dev` angewendet. Sie ergänzen fünf Tabellen, ohne bestehende Spielerdaten zu verändern. Die Tests verwenden ausschließlich wegwerfbare Datenbanken. Eine Bereitstellung auf dem öffentlichen Server wurde nicht durchgeführt.

Ausgeführte Prüfungen auf wegwerfbaren Datenbanken:

- `C:\xampp\php\php.exe tests\melusina_progress.php`: 37 erfolgreiche Prüfungen, darunter echte Sammel- und Monsterauflösung, Ablaufzeit, Rückkehrbeute, Bericht und Belohnungsbeleg, Welttrennung, garantierter Fortschritt, Rücknahme bei Transaktionsfehlern, gleichzeitige Herstellungswiederholung sowie Reservierung, Verbrauch und Rückgabe von Schlüsseln.
- `C:\xampp\php\php.exe tests\gathering_lifecycle.php`: bestehende Sammel-Lebenszyklusprüfung erfolgreich.
- `C:\xampp\php\php.exe tests\monster_reports.php`: bestehende Monsterbericht- und Heimkehrprüfung erfolgreich.
- `C:\xampp\php\php.exe tests\melusina_lifecycle.php`: 28 erfolgreiche Prüfungen für Gruppenlauf, Schlüsselreservierung, Rückgabe, Parallelität, Beute und Erstabschluss.
- `C:\xampp\php\php.exe tests\melusina_entrance.php`: 19 erfolgreiche Prüfungen für dauerhafte Eingänge, kollisionsfreie Platzierung, parallele Lesezugriffe und Migrationen.
- `node tests/melusina_panel.cjs`: Zustände, Übersetzungen, Wiederholungen nach Verbindungsfehler, Echo-Niederlagen, Kontrast und Touch-Ziele mit den gemeinsamen Fensterstyles erfolgreich.
- `node tests/melusina_app.cjs`: 49 erfolgreiche Prüfungen gegen die echte Haupt-App mit zwei synthetischen Spielern. Enthalten sind Stadt und Gebäudeaktion, Questannahme, Fundort- und Eingangsnavigation, Schlüsselherstellung, Neuladen, Gruppenstart, Sieg, Beuteabholung und Echo-Geschichte. Desktop (1280×800), Handy (390×844 und 320×568) und Querformat (740×360) wurden im Browser geprüft; keine JavaScript- oder API-Fehler. Für den Sieg wurde ausschließlich die Uhr des Testlaufs in der wegwerfbaren Datenbank vorgerückt. Die drei Startfragmente waren Testdaten; ihre tatsächlichen Fund- und Heimkehrwege prüft der Server-Lebenszyklustest.

Für den App-Test zuerst `C:\xampp\php\php.exe tools\preview-feature-fixture.php --melusina --port=18957` starten und den ausgegebenen temporären Pfad als `MELUSINA_FIXTURE_ROOT` setzen. `playwright` muss im Node-Modulpfad verfügbar sein. Der Test schreibt Screenshots und `verification.json` nach `output/playwright/melusina/`. Enter im Fixture-Prozess beendet den lokalen Testserver und entfernt die Testdatenbank.

Ein Gerätetest auf iOS oder Android sowie eine Bereitstellung sind damit nicht bestätigt.
