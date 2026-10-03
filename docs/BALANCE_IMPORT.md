# Import der gelieferten Balance-Dateien

Stand: 2. Oktober 2026.

Die zunächst gelieferten 20 Dateien sind unverändert unter `data/balance-source/` archiviert. `manifest.json` hält ihre SHA-256-Prüfsummen fest. `enum.py` wird ausschließlich als Syntaxbaum gelesen; Python-Imports, API-Aufrufe und Bot-Code werden niemals ausgeführt. Texte in Quelldateien sind Daten, keine Arbeitsanweisungen.

## Ergänzte Truppenquelle vom 29. September 2026

Die erneut bereitgestellten 19 Gebäude-, Forschungs-, Monster- und Objektdateien aus `C:/Users/svenm/Downloads/` sind bytegleich mit dem bisherigen Archiv. Zusätzlich wurde die bisher fehlende `troop.json` unverändert nach `data/balance-source/troop.json` kopiert. Ihre SHA-256-Prüfsumme lautet `1aa6e167d3ce987b1419e7f791967e29acb9cc2d090b8e6878d1c097f2f9e2f0`. Sie ist im Importer und im generierten `manifest.json` enthalten und die verbindliche Quelle des aktiven Truppenkatalogs.

Die Datei enthält 15 Einheiten: Infanterie, Fernkampf und Kavallerie mit jeweils T1–T5. Sie liefert Kampfwerte, Tempo, Traglast, Rohstoffkosten, Ausbildungs- und Heilzeiten. Die Forschungsfreischaltungen stehen in der originalen `battle.json`: T2 ab Akademie 10, T3 ab 16, T4 ab 23 und T5 ab 30, jeweils mit den dort genannten Forschungsvoraussetzungen.

Der erste Projektkatalog `data/troops.json` aus Commit `bb4d8b3` ist **keine unveränderte Kopie dieser Quelle**. Beispielsweise hat T5-Infanterie in der Quelle Angriff 126, Verteidigung 105 und HP 300; im damaligen Projektkatalog waren es 230, 179 und 853. Für eine Rückkehr zu den Originalwerten ist die neu archivierte Quelle maßgeblich.

Der Generator bildet Quellcodes ausdrücklich auf bestehende interne Truppencodes ab: Quellcode `50100201` bezeichnet den T1-Fernkämpfer, intern aber T2-Infanterie. Auch Bruchwerte wie `carry: 1.5` und `heal_time: 0.5` müssen erhalten bleiben. Aktiv sind wieder T1–T5 mit originalen Werten und Forschungsfreischaltungen. Nur die Ausbildungszeiten sind bewusst auf 3/5/9/16/27 Sekunden angepasst (2.000 T5: 15 Stunden). `docs/TRAINING.md` beschreibt die aktuelle Regel und den gesicherten Übergang bestehender Spielstände.

## Wiederholbarer Import

```powershell
python tools/import_balance.py
python tools/update-training-catalog.py
# Quellmengen in eine spielbare, an Truppen und Marschgrenzen gekoppelte Kurve überführen:
php tools/rebalance-monsters.php --apply
# Bereits lebende Monster proportional auf die neue HP-Kurve umstellen:
php tools/migrate-monster-balance.php --apply
# Schema installieren und gespeicherte Gebäudemacht bestehender Städte abgleichen:
php tools/migrate-balance.php --apply
# Eine neue vollständige Lieferung übernehmen:
python tools/import_balance.py C:/Pfad/zur/Lieferung
# Nur Forschung und ihre originalen Abhängigkeiten aktualisieren:
python tools/import_balance.py --research-only
# Ausschließlich die freigegebenen Forschungsgrundzeiten aktualisieren:
python tools/import_balance.py --research-times-only
# Nur die aktive Rohstoffkostenkurve aus dem vorhandenen Archiv neu berechnen:
python tools/import_balance.py --costs-only
# T6–T10-Bestände und bestehende Monster einmalig gesichert umstellen:
php tools/migrate-five-tiers.php --apply
```

Der normale Import braucht alle 21 Quelldateien einschließlich enum.py und troop.json. Ohne Verzeichnis verwendet er das Projektarchiv. Er verändert keine Datenbank und keine Spielstände. Migration `0094_building_cost_snapshot.sql` ergänzt die tatsächlich bezahlten Kosten an Bauaufträgen und legt den vorhandenen Wachturm für bestehende Städte auf Stufe 1 an. `tools/migrate-balance.php --apply` installiert gezielt diese Migration und gleicht die gespeicherte Gebäudemacht ab, damit auch Profile, Ranglisten und Weltkarte die neuen Werte anzeigen. Ohne `--apply` zeigt das Werkzeug nur den Bedarf an. Beide Schritte sind wiederholbar; Ausbaustufen, bezahlte Aufträge, Inventar und Rohstoffe bleiben erhalten.

## Aktive Regeln

Seit dem 30. September 2026 dienen die Quellpreise als Ausgangswerte. Für neue Gebäude- und Forschungsaufträge gilt die unten beschriebene, reduzierte Rohstoffkostenkurve. Die Originaldateien werden nicht geändert.

- **Gebäude:** Alle 14 Tabellen und 420 Stufen behalten zusätzliche Inventarmaterialien, Voraussetzungen und Macht der Quelle; die vier Rohstoffkosten folgen der reduzierten Kurve. `power` wird als kumulative Macht je Stufe verwendet; der Machtzuwachs ist die Differenz zur Vorstufe. Die Quellzeiten gelten unverändert bis Stufe 20. Für Stufe 21–30 setzt der Import die Conquer-Endspielkurven aus `tools/import_balance.py`: Burg endet bei 35 Tagen auf Stufe 30 (Stufen 21–30 rückwärts mit Faktor 1,2), Akademie weiterhin bei 30 Tagen, militärische und Rohstoffgebäude bei 10 Tagen, Lager/Aussichtsturm sowie Infrastruktur bei 26 Tagen. Startgebäude bleiben kostenlos vorhanden. `valid: false` betrifft die bereits vorhandenen festen Startgebäude auf Stufe 1.

| Gruppe | Stufe 21 | 22 | 23 | 24 | 25 | 26 | 27 | 28 | 29 | 30 |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| Burg | 6 T 18 Std 48 Min | 8 T 3 Std 21 Min | 9 T 18 Std 26 Min | 11 T 17 Std 19 Min | 14 T 1 Std 35 Min | 16 T 21 Std 6 Min | 20 T 6 Std 7 Min | 24 T 7 Std 20 Min | 29 T 4 Std | **35 T** |
| Akademie | 4 T | 5 T | 6 T | 8 T | 10 T | 13 T | 17 T | 21 T | 25 T | **30 T** |
| Allianz, Krankenhaus, Handel, Schatzhaus, Mauer | 4 T | 5 T | 6 T | 8 T | 10 T | 12 T | 15 T | 18 T | 22 T | 26 T |
| Lager, Aussichtsturm | 2 T | 3 T | 4 T | 5 T | 7 T | 9 T | 12 T | 16 T | 21 T | 26 T |
| Kaserne, Schützenlager, Reiterhof | 18 h | 1 T | 32 h | 42 h | 54 h | 3 T | 4 T | 5 T | 7 T | 10 T |
| Farm, Holzfäller, Steinbruch, Goldmine | 16 h | 22 h | 30 h | 42 h | 54 h | 3 T | 4 T | 5 T | 7 T | 10 T |
- **Conquer-Ausbildungsgebäude:** Schützenlager und Reiterhof verwenden dieselbe Ausbaukurve wie die gelieferte Kaserne. Es gibt für diese beiden Conquer-Gebäude keine eigenen Quelldateien.
- **Zusatzmaterialien:** `alliance_badge` → Allianzabzeichen (119000002) bleiben Ausbaukosten der Allianzhalle. `golden_pillar` wird seit der Entscheidung vom 30. September 2026 beim Import ausdrücklich ignoriert: Union of Kingdoms verwendet keine Säulen und keinen zugehörigen Kristallkauf. Die Originaldateien bleiben unverändert. Die historische Gegenstandsdefinition bleibt nur für vorhandene Bestände und alte Kostenbelege erhalten.
- **Allianzabzeichen-Beute ab 1. Oktober 2026:** Rally-Monster haben je siegreichem Teilnehmer einschließlich Anführer unabhängig 50 % Dropchance. Ein erfolgreicher Wurf gibt 2 Abzeichen auf Stufe 1–3, 3 auf Stufe 4–6, 4 auf Stufe 7–9 und 5 auf Stufe 10; ein erfolgloser Wurf gibt keine. Die zentrale Laufzeitregel ergänzt den gewählten Standard-Beutepool und bleibt dadurch auch nach einem Import aktiv. Globale und weltspezifische Beuteanpassungen behalten Vorrang. Die Gutschrift erfolgt einmalig bei Rückkehr; bestehende Rallys behalten ihre gespeicherte Beute. Solo-Monster und Stufen über 10 erhalten durch diese Regel keine Abzeichen. Hallenkosten und Bauzeiten ändern sich dabei nicht.
- **Bauabbruch:** Neue Aufträge erstatten ausschließlich ihren gespeicherten Preis, einschließlich Materialien. Ältere Aufträge ohne Kostensnapshot erstatten die vorherige Formel. Abgeschlossene oder bereits abgebrochene Aufträge können nicht erneut erstattet werden. Die Haupt-App sendet die erwartete Gebäudestufe, damit eine verzögerte Wiederholung nicht die nächste Stufe kauft.
- **Forschung:** 129 aktive Definitionen mit 963 Stufen behalten originale Macht, Wirkungen und Voraussetzungen; Rohstoffpreise und Forschungszeiten folgen den unten beschriebenen freigegebenen Kurven. Die zwölf Truppenfreischaltungen T2–T5 und ihre originalen Voraussetzungen sind wieder aktiv. Bestehende Forschungs-IDs und der getrennte Wirtschaftsknoten `production_resource_protect` bleiben erhalten.
- **Normale Weltmonster:** Orks, Skelette, Golems, Schatzgoblins sowie die vorhandenen inaktiven Deathkar-/Drachen-/Magdar-Vorlagen erhalten Quellwerte für HP, Angriff, Verteidigung, Aktionspunkte, Macht, XP und alle elf möglichen Dropplätze. Die importierte Einheitenmenge bleibt als `source_amount` erhalten; `tools/rebalance-monsters.php` berechnet daraus die spielbare `amount` gegen die echten Truppen- und Marschgrenzen. Schatzgoblins sind vollständig bis Stufe 10 hinterlegt. Der Quellschlüssel besteht aus **Familiencode und Stufe**; er wird niemals mit einer internen Spawn-ID verwechselt.
- **Belohnungen:** Dropwahrscheinlichkeiten und Mengen bleiben exakt. Rohstoff- und Kristallpakete ersetzen bei Quellmonstern die früheren zusätzlichen pauschalen Rohstoff-/Edelsteingutschriften. Bestehende globale und weltspezifische Backoffice-Einstellungen haben weiter Vorrang. Allianzgeschenke aus der Quelle werden innerhalb des einmaligen Kill-Belegs erzeugt und über die bestehende Geschenk-/Postmechanik abgeholt.
- **Sammelfelder:** Nahrung, Holz, Stein, Gold und Kristalle unterstützen Stufe 1–10. `production` ist die Gesamtmenge eines neuen Feldes; `gathering` wird als Menge pro Stunde verwendet. Bestehende Sammel- und Weltboni wirken darauf. Neue Spawns und Starterfelder verwenden diese Mengen; bestehende Vorräte und laufende Marschraten werden nicht rückwirkend ersetzt.
- **Sammelbeute:** Jeder nichtleere `drop_*`-Platz wird mit seiner `rate_*` als eigene Wahrscheinlichkeit ausgewürfelt, sobald ein Feld vollständig erschöpft ist. Teilweiser Rückruf erzeugt keine zusätzlichen Würfe. Beute reist im Marsch mit und wird genau einmal bei der Rückkehr gutgeschrieben. Die Quelle enthält keine Gruppen- oder Auslösebeschreibung; unabhängige Würfe bei vollständiger Erschöpfung sind die explizite Conquer-Integrationsregel.

Die Gebäudedateien enthalten keine Produktionsraten, Lagergrenzen oder sonstigen Gebäude-Effekte. Deren bestehende Conquer-Kurven bleiben deshalb erhalten. Regionale Conquer-Bosse behalten ihre eigenen Werte. Historische Marsch- und Rally-Snapshots sowie gespeicherte Belohnungen werden nicht umgeschrieben.

## Reduzierte Rohstoffkosten vom 30. September 2026

Die Regelparameter stehen in `data/economy_balance.json`; `tools/economy_balance.py` berechnet daraus die vier Rohstoffpreise. Vollimport, `--research-only` und `--costs-only` benutzen dieselbe Regel. `--costs-only` schreibt ausschließlich `resources` in den vorhandenen Gebäude- und Forschungskatalogen neu. Es rechnet immer von den archivierten Originalen, sodass wiederholtes Ausführen keine weiteren Rabatte erzeugt. Dezimalrechnung und Aufrunden auf ganze Rohstoffe vermeiden Gleitkommafehler; kostenlose Rohstoffe bleiben kostenlos.

- **Gebäude:** Nahrung, Holz und Stein kosten bis Stufe 5 jeweils 80 % der Quelle, Gold 48 %. Ab Stufe 6 wächst die Preisobergrenze von der ursprünglichen Stufe 5 aus um 25 % pro Stufe, anschließend gelten dieselben Faktoren. Ein günstigerer Originalpreis hat Vorrang. Alle 420 Stufen steigen weiterhin monoton; die beiden zusätzlichen Ausbildungsgebäude verwenden wie bisher die Kasernenkurve.
- **Forschung:** Nahrung, Holz und Stein kosten zunächst 70 %, Gold 35 % der Quelle. Für jede erforderliche Akademiestufe oberhalb von 5 sinkt dieser Faktor zusätzlich um 10 % (Multiplikation mit `0,9`). Maßgeblich ist die Voraussetzung der konkreten Forschungsstufe, nicht die aktuelle Akademie des Spielers. Die ursprüngliche Kostensteigerung überwiegt diesen Rabatt: Höhere Stufen derselben Forschung werden weiterhin teurer.
- **Frühe Militärforschung, Ergänzung vom 2. Oktober 2026:** Alle Stufen im Militärbaum mit Akademievoraussetzung 1–10 erhalten zusätzlich 30 % Rabatt auf die vier Rohstoffe, einschließlich der drei T2-Freischaltungen. Bei Akademievoraussetzung 11/12/13/14/15 sinkt dieser Zusatzrabatt auf 25/20/15/10/5 %. Ab Voraussetzung 16 gelten unverändert die bisherigen Preise. Die Regel steht unter `research.early_military`; beide Faktoren werden auf die Originalpreise angewendet und erst anschließend auf ganze Rohstoffe aufgerundet. Wirtschafts- und fortgeschrittene Forschung bleiben unverändert. Es zählt die Voraussetzung der einzelnen Forschungsstufe, auch wenn die eigene Akademie bereits höher ausgebaut ist.
- **Zusätzliche Goldentlastung vom 2. Oktober 2026:** Militärforschung mit Akademievoraussetzung 1–5 benötigt nochmals 30 % weniger Gold als nach der ersten Senkung. Dieser zusätzliche Goldrabatt beträgt bei Voraussetzung 6/7/8/9 noch 24/18/12/6 % und entfällt ab Voraussetzung 10. Damit kosten die ersten Stufen rund 51 % weniger Gold als vor der Einstiegsanpassung; die T2-Freischaltungen bleiben bei je 52.082 Gold. Die Parameter stehen unter `research.early_military.gold`. Auch hier wird ausschließlich vom Originalpreis und erst am Ende gerundet.
- **Erhalten durch die Kostenanpassung:** Truppenausbildungs- und Heilkosten, Sammeln, Monsterbeute, Kampfwerte, Macht und sämtliche Zeiten. Die separat angehobene Stadtproduktion und die zusätzlichen Aufgabenbelohnungen stehen unten. 2.000 T5 benötigen weiterhin 15 Stunden ohne Boni. Allianzabzeichen bleiben ausschließlich das bestehende Zusatzmaterial der Allianzhalle; ihre Gegenstandszuordnung wird durch diese Kostenanpassung nicht verändert. Säulen bleiben ausgeschlossen.

Summen aus Nahrung, Holz, Stein und Gold, jeweils für genau einen Ausbau bzw. eine Freischaltung ohne Vorbedingungen:

| Vorhaben | Original | Neue Kosten |
|---|---:|---:|
| Burg 10 | 422.532 | 166.765 |
| Burg 16 | 3.464.101 | 636.149 |
| Burg 23 | 40.328.218 | 3.033.388 |
| Burg 30 | 469.491.186 | 14.464.319 |
| Akademie 30 | 250.562.824 | 13.823.749 |
| Farm 30 | 15.689.133 | 2.957.206 |
| T2-Freischaltung pro Truppenart | 900.000 | 208.328 |
| T3-Freischaltung pro Truppenart | 7.200.000 | 1.265.288 |
| T4-Freischaltung pro Truppenart | 29.325.000 | 2.464.856 |
| T5-Freischaltung pro Truppenart | 103.500.000 | 4.160.940 |

Der Weg zur ersten T2-Infanterie umfasst einschließlich aller geteilten Vorforschungen 28 einzelne Stufen. Die erste Senkung reduzierte ihn von 1.380.140 auf 966.125 Rohstoffe; mit der zusätzlichen Goldentlastung sind es 926.461, davon 241.579 statt ursprünglich 401.765 Gold. Die einzelne T2-Freischaltung kostet je 52.082 Nahrung, Holz, Stein und Gold statt zuvor je 74.402. Frühe Beispiele: Infanterie-Verteidigung Stufe 1 kostet insgesamt 7.145 statt ursprünglich 11.340; Infanterie-Angriff Stufe 1 kostet 20.839 statt 33.075. Die Kosten höherer Stufen steigen weiterhin monoton.

Die gesamte erforderliche Forschung bis T5-Infanterie umfasst 104 einzelne Stufen einschließlich geteilter Vorforschungen, jede nur einmal gezählt: aktuell 52.611.876 Rohstoffe, davon 13.986.111 Gold. Vor dem zusätzlichen Einstiegsrabatt waren es 54.081.545 beziehungsweise 14.461.385. Die einzelne T3-, T4- und T5-Freischaltung behält ihren bisherigen Preis. Gebäude, Truppen und optionale Forschung kommen hinzu. Die einzelne T5-Freischaltung dauert seit der Zeitabstimmung 45 Tage ohne Boni. Der vollständige erforderliche Forschungsweg zur ersten T5-Infanterie sinkt von rund 351,64 auf 183,26 Tage ohne Boni, Bauzeiten oder Wartepausen; das ist eine Summe der Forschungstimer und keine Kalenderprognose für einen aktiven Spieler.

Als Vergleich auf einer normalen Welt ohne Boni liefern vier Produktionsgebäude nach der Produktionserhöhung vom 2. Oktober auf Stufe 30 zusammen rund 1,778 Millionen Rohstoffe pro Tag, davon 269.453 Gold. Der Preis von Burg 30 entspricht etwa 10 Tagen dieser Endstufenproduktion; vor dem Ausbau mit Produktionsgebäuden auf Stufe 29 sind es rund 11,5 Tage. Begrenzend ist Stein, bei gleichzeitig ausreichend Lagerplatz. Akademie 30 benötigt wegen des Goldanteils rund 17 Tage. Drei Märsche auf Stufe-10-Feldern könnten bei je acht tatsächlichen Sammelstunden zusätzlich insgesamt 2,04 Millionen pro Tag holen. Das ist ein Rechenbeispiel: Reisen, Traglast, Konkurrenz, Monsterjagden und erneutes Aussenden senken diese Ausbeute. Monsterbeute und Boni ergänzen die Einnahmen, sind aber keine Voraussetzung dieser passiven Vergleichsrechnung.

Die Kostenkurve ist eine erste spielbare Abstimmung, kein Nachweis eines bestimmten Fortschrittstempos. Insbesondere parallele Ausbildung aller drei Truppentypen bleibt teuer: je 2.000 T5 kosten zusammen weiterhin 7,56 Millionen Rohstoffe. Regelmäßige Spieltests müssen zeigen, wie viel Ausbildung nach Ausbau, Forschung und Heilung tatsächlich finanzierbar bleibt.

Neue Aufträge verwenden sofort die neuen Katalogpreise. Laufende Bauaufträge behalten ihren gespeicherten Kostenbeleg für eine spätere Erstattung; laufende Bau-, Forschungs- und Ausbildungszeiten bleiben erhalten. Forschung erstattet beim Abbruch gemäß bestehender Regel keine Rohstoffe. Es erfolgt keine rückwirkende Erstattung bereits abgeschlossener Käufe und keine Änderung an Spielständen.

## Stadtproduktion und frühe Aufgaben ab 2. Oktober 2026

Alle vier städtischen Rohstoffgebäude produzieren auf jeder Stufe 30 % mehr. `BuildingData::getHourlyRate()` ist die gemeinsame Quelle für tatsächliche Gutschriften, Offlineproduktion und Anzeige. Auf Stufe 1 gelten ohne Boni pro Stunde: Farm 390 Nahrung (zuvor 300), Holzfäller 390 Holz (300), Steinbruch 312 Stein (240), Goldmine 195 Gold (150). Die Steigerung je Gebäudestufe bleibt 15 %; Welt-, VIP-, Forschungs-, Allianz- und aktive Produktionsboni gelten weiterhin genau einmal. Lagergrenzen, Sammelraten auf der Weltkarte und Kristalle ändern sich nicht. Beim nächsten Ressourcenabgleich wird noch nicht verbuchte Offlinezeit nach der aktiven Produktionsregel berechnet; bereits gespeicherte Vorräte werden nicht pauschal erhöht.

Die drei früh erreichbaren Tagesaufgaben behalten ihre bisherigen Belohnungen und erhalten garantierte Rohstoffpakete:

| Aufgabe | Nahrung | Holz | Stein | Gold |
|---|---:|---:|---:|---:|
| Ein Gebäude ausbauen | – | 10.000 | 10.000 | 10.000 |
| 100 Truppen ausbilden | 20.000 | – | – | 10.000 |
| Eine Forschung abschließen | – | 10.000 | 10.000 | 10.000 |
| Gesamt je Tag bei allen drei Aufgaben | 20.000 | 20.000 | 20.000 | 30.000 |

Die Pakete werden beim Abholen ins Inventar gelegt und dort bei Bedarf geöffnet. Die bestehenden Katalogeinträge liefern Bilder und Übersetzungen. Die Aufgaben gelten weiterhin für alle Spieler und werden einmal je Konto und UTC-Tag belohnt. Bereits abgeholte Aufgaben erhalten keine nachträgliche Gutschrift. Abholstatus und sämtliche Belohnungen werden gemeinsam in einer Transaktion gespeichert; Wiederholungen buchen nicht doppelt. Prüfungen: `tests/resource_production.php`, `tests/starter_quest_rewards.php`, `tests/economy_balance.py`, `tests/economy_http.php` und die Forschungs-/Aufgabenprüfungen der Haupt-App.

## Geschwindigkeitsboni für Bau und Forschung

Seit dem 30. September 2026 verkürzen Geschwindigkeitsboni neue Aufträge über `Grundzeit / (1 + Bonus)`: +20 % ergeben den Divisor 1,2, +100 % den Divisor 2. Auch über 100 % bleibt eine reguläre Wartezeit bestehen. Talente bleiben ein zusätzlicher Divisor (maximal 1,1). Auf ganze Sekunden wird erst am Ende aufgerundet.

Die vier zeitlich begrenzten +25-%-Items für Bau/Forschung wurden auf Nutzerwunsch entfernt: 10102021, 10102031, 10202010, 10202011. Katalog, Shop, Inventaransicht, Neuvergabe, Nutzung und Truhen schließen diese Codes aus. Alte Inventarzeilen bleiben unverändert gespeichert, wirken aber nicht mehr. Bereits aktivierte Bau- und Forschungsitemeffekte werden nicht mehr angewendet oder angezeigt; Forschungs-Debuffs bleiben wirksam. Historische Beuteüberschreibungen filtern entfernte Gegenstände, ein ausschließlich daraus bestehender Truhenpool verwendet die aktuelle Standardbeute. Der Kataloggenerator reserviert die vier IDs dauerhaft und erzeugt diese Items nicht erneut. Produktions-, Sammel-, Ausbildungs- und andere Items sowie normale Zeitbeschleuniger bleiben erhalten. Bau- und Forschungsrunen von der Karte bleiben ebenfalls aktiv.

Mit VIP 10, vollständig ausgebauter aktueller Ausrüstung, Forschung, Allianzforschung und legendären Kartenrunen ergeben sich Bau +155 % plus Talent 10 % sowie Forschung +162 % plus Talent 10 %. Die Gesamtdivisoren sind 2,805 und 2,882. Zehn Tage Grundbauzeit ergeben damit 3 Tage 13 Stunden 33 Minuten 42 Sekunden; die T5-Freischaltung mit 45 Tagen Grundzeit dauert 15 Tage 14 Stunden 44 Minuten 24 Sekunden.

Burg 29→30 hat nach Freigabe 35 Tage Grundzeit: mit VIP 10 allein genau 28 Tage. Vor Burg 30 ist die letzte Bauforschungsstufe noch nicht erreichbar. Das dort maximal mögliche aktuelle Setup erreicht +150 % plus Talent 10 %, also den Divisor 2,75 und 12 Tage 17 Stunden 27 Minuten 17 Sekunden. Der Rückwärtsfaktor 1,2 gilt für Burgstufen 21–30; Stufen 1–20 sowie die Grundzeiten aller anderen Gebäude bleiben unverändert. Die Änderung der Burgkurve verändert keine Ressourcenpreise; die Forschungsgrundzeiten folgen der separat freigegebenen Regel unten.

Haupt-App, eigenständige Forschungsseite und Server verwenden dieselbe Berechnung. Boni gelten zum Startzeitpunkt; bestehende Aufträge behalten ihre gespeicherten Endzeiten auch bei Bonusablauf und bei dieser Umstellung.

`tests/speed_bonus_http.php` prüft sechs tatsächlich ausgerüstete Relikte, einen gültigen Talentplan, entfernte Altbestände und Effekte, historische Truhenüberschreibungen sowie die echten Bau-/Forschungsendpunkte einschließlich Burg 30. `--speed-bonuses` ergänzt die isolierte Browservorschau um VIP 10 und einen weiterhin erlaubten Produktionsbonus. Die Gebäude- und Forschungs-Browserprüfungen vergleichen die angezeigten Zeiten in Desktop-, Handy- und Querformat mit dem Server.

## Forschungsgrundzeiten ab 30. September 2026

Die freigegebene Regel steht in `data/research_time_balance.json`. `tools/research_time_balance.py` berechnet Zeiten aus dem unveränderten Quellenarchiv, damit wiederholte Importe keine weiteren Kürzungen auf bereits gekürzte Werte anwenden.

| Einzelne Truppenfreischaltung, je Truppenart | Grundzeit | Mit VIP 10 allein (+25 %) |
|---|---:|---:|
| T2 | 20 Stunden | 16 Stunden |
| T3 | 4 Tage | 3 Tage 4 Stunden 48 Minuten |
| T4 | 21 Tage | 16 Tage 19 Stunden 12 Minuten |
| T5 | 45 Tage | 36 Tage |

Alle übrigen Forschungsstufen verwenden `min(Originalzeit, 14 Tage / 1,2^(30 − erforderliche Akademiestufe))`, auf ganze Sekunden aufgerundet. Die erforderliche Akademiestufe der konkreten Forschungsstufe ist maßgeblich, nicht der aktuelle Ausbau des Spielers. Kürzere Originalzeiten bleiben erhalten. Der zusätzliche Marschplatz ist keine Truppenfreischaltung und folgt daher dieser normalen Zeitobergrenze. Mehrere Stufen mit derselben Akademievoraussetzung dürfen dieselbe Obergrenze erreichen.

Beispiele der Obergrenze: Akademie 23 rund 3 Tage 21 Stunden 46 Minuten; Akademie 28 genau 9 Tage 17 Stunden 20 Minuten; Akademie 29 genau 11 Tage 16 Stunden; Akademie 30 genau 14 Tage. Die letzte Stufe Bauforschung II braucht damit 14 Tage, die letzte Stufe Forschungstempo II (Akademie 29) 11 Tage 16 Stunden ohne Boni. 434 der 963 Forschungsstufen werden kürzer; die übrigen behalten ihre bereits kürzeren Zeiten.

`--research-times-only` ändert ausschließlich die Zeitfelder in den drei aktiven Forschungsdateien. Vollimport und `--research-only` wenden dieselbe Zeitregel an. `--costs-only` bleibt auf Ressourcen beschränkt. Kosten, Macht, Wirkungen, Freischaltvoraussetzungen und IDs bleiben bei der Zeitumstellung gleich. Es gibt keine Datenbankmigration; bereits gestartete Aufträge behalten ihre gespeicherten Endzeiten.

`python tests/research_durations.py` prüft alle 963 Stufen, die zwölf Truppenfreischaltungen, steigende bzw. gleichbleibende Zeiten, die 14-Tage-Grenze, Importwiederholbarkeit und unveränderte Quelltabellen. `tests/economy_http.php` prüft sowohl einen historischen T5-Auftrag mit 131,25 Tagen als auch den neuen 45-Tage-Auftrag. `tests/speed_bonus_http.php` prüft die tatsächliche neue Dauer mit dem maximalen VIP-10-Setup. `tests/research_app.cjs` vergleicht alle ausgelieferten Zeiten mit dem Katalog und die T4-/T5-Dialoge in fünf Bildschirmformaten.

## Fehlende Definitionen und andere Spielmodi

`data/source_item_map.json` dokumentiert 59 tatsächlich verwendete Quell-Itemcodes und ihre internen Gegenstücke. Bestehende Inventarcodes werden nicht umgedeutet: beispielsweise ist Quelle `10103001` ein 1-Minuten-Beschleuniger und wird auf den vorhandenen internen Code `10203001` abgebildet; interner Code `10103001` bleibt weiterhin 5 Minuten. Quelle `10101001` wird als Paket mit 10 Kristallen zugeordnet.

Für **24 verwendete Quellcodes** fehlen Name oder Wirkung. Sie werden unter konfliktfreien internen IDs als nicht direkt verwendbare Gegenstände gespeichert und weiterhin mit den Originalmengen und -chancen vergeben. Zwei davon sind namentlich bekannte Beschleunigerkisten, deren Inhalt fehlt. Es werden keine Relikte, Mengen, Wirkungen oder Seltenheiten aus den Ziffern geraten.

| Quellcodes | Fehlende Information |
|---|---|
| 10101007, 10104022, 10104023, 10104026, 10104027 | Itemdefinition |
| 10104103, 10104104 | Inhalt von Beschleunigerkiste Stufe 3 bzw. 4 |
| 10104107, 10104136 | Itemdefinition |
| 10105012, 10105013, 10105014 | Allianzgeschenk-Itemdefinition |
| 10601001, 10601002, 10601003 | Itemdefinition |
| 10602002, 10602003, 10602004 | Itemdefinition |
| 10603023, 10603024, 10603025, 10603026 | Itemdefinition |
| 10604001, 10604002 | Itemdefinition |

Alle **167 Monsterzeilen** und **131 Objektzeilen** sind als typisierte Kataloge abrufbar (`MonsterData::source(code, level)`, `FieldObjectData::source(code, level)`). Die `bf_*`-Familien, versiegelte Mine, fremde Tore/Festungen und andere Modusobjekte haben keine entsprechenden aktiven Conquer-Spielmodi. Ihre Werte bleiben vollständig erhalten; sie werden nicht als neue Modi oder unpassende Weltspawns aktiviert. Ebenso sind `asset`, `rare`, `klay` und die Quell-Charm-Grenzen Metadaten. Es wird keine Blockchain-Währung eingeführt. Die bestehende garantierte Conquer-Charmmechanik bleibt aktiv; die Quelle liefert keine vollständigen Charmwirkungen oder eine passende Verteilung für deren andere Seltenheitsstufen.

## Prüfung

`tests/balance_import.php` vergleicht sämtliche Gebäude-, Monster- und Objektzeilen mit den Originalen, prüft zusätzlich jede Conquer-Zeitkurve einschließlich Burg 30 mit 35 Tagen und Akademie 30 mit 30 Tagen, Itemzuordnung und den tatsächlichen Monsterzugriff. `tests/balance_lifecycle.php` verwendet eine wegwerfbare Datenbank für Kostenabzug, Materialmangel, Rückerstattung, wiederholte Anfragen, Wachturmausbau, Sammelbeute, Allianzgeschenke sowie den Machtabgleich bestehender und neuer Städte. `tests/balance_app.cjs` verwendet die vollständige Vorschau-App (`tools/preview-feature-fixture.php --balance-import --port=18974`) und prüft 1280×800, 390×844, 320×568, 844×390 und 568×320 in der Haupt-App mit ihrem gemeinsamen Gebäudedialog. Die eigenständige 3D-Szene ist entfernt.

Die aktuellen Truppen-, Forschungs- und Monsterprüfungen sind in `docs/TRAINING.md` aufgeführt. Alte Testannahmen zur zehnstufigen Truppenkurve gelten seit dem 29. September 2026 nicht mehr.

Zusätzlich prüft `python tests/economy_balance.py` die 420 Gebäude- und 963 Forschungsstufen auf steigende, höchstens originale Kosten, feste Meilensteinpreise und die vollständigen T5-Vorforschungen. Es führt Kosten-, Forschungs- und Vollimport in einer temporären Kopie aus und kontrolliert Wiederholbarkeit sowie unveränderte Originaldateien. `php tests/economy_http.php` startet T5 über den echten Serverendpunkt: ein Gold zu wenig wird abgewiesen, exakt ausreichende Ressourcen werden korrekt abgezogen, Wiederholungen buchen nicht doppelt und die Forschungszeit bleibt erhalten. Der Gebäudeverlauf prüft zusätzlich die Erstattung eines teureren historischen Bauauftrags. Beide Browserprüfungen vergleichen die sichtbaren Preise mit dem Katalog in Desktop-, Handy- und Querformat.
