# Truppenausbildung

Stand: 29. September 2026.

## Aktiver Umfang und Originalwerte

Union of Kingdoms verwendet T1–T5 für Infanterie, Bogenschützen und Kavallerie: insgesamt 15 Einheiten. T6–T10 sind für spätere Inhalte archiviert und können weder neu ausgebildet noch durch Beförderung freigeschaltet werden.

`data/balance-source/troop.json` ist die unveränderte Quelle für Angriff, Verteidigung, Lebenspunkte, Stärke, Tempo, Traglast, Rohstoffkosten und Heilzeit. `tools/update-training-catalog.py` erzeugt daraus `data/troops.json`. Quellcodes werden ausdrücklich auf die bestehenden internen IDs abgebildet; Bruchwerte wie 1,5 Traglast und 0,5 Sekunden Heilzeit bleiben erhalten. Die Quelle definiert keinen separaten Tödlichkeitswert.

## Freischaltung und Ausbildung

Kaserne, Schützenlager und Reiterhof bilden unabhängig voneinander aus. T1 benötigt das jeweilige Gebäude und Stadtzentrum auf Stufe 1. T2–T5 benötigen zusätzlich die passende abgeschlossene Truppenforschung und Akademiestufe. Die Forschung hat die originalen Voraussetzungen aus `battle.json`.

| Tier | Akademie und Stadtzentrum | Sekunden je Einheit | 2.000 Einheiten ohne Boni |
|---|---:|---:|---|
| T1 | 1 (keine Akademie nötig) | 3 | 1 Std. 40 Min. |
| T2 | 10 | 5 | 2 Std. 46 Min. 40 Sek. |
| T3 | 16 | 9 | 5 Std. |
| T4 | 23 | 16 | 8 Std. 53 Min. 20 Sek. |
| T5 | 30 | 27 | **15 Std.** |

Die Ausbildungszeiten sind die bewusste Abweichung von der Originalquelle. Anzeige und Server verwenden `ceil(Sekunden × Anzahl / Ausbildungstempo)`. Forschungs-, VIP-, Relikt-, aktive Ausbildungs- und Kasernenboni wirken weiterhin. Beförderungen benötigen Ausbildungsgebäude und Stadtzentrum ab Stufe 13 sowie die freigeschaltete Zieltruppe; sie kosten 70 % der Zielausbildung und verwenden deren halbe Zeit.

Die Forschungsgrundzeiten pro Truppenart betragen T2: 20 Stunden, T3: 4 Tage, T4: 21 Tage und T5: 45 Tage. VIP 10 allein reduziert sie auf 16 Stunden, 3 Tage 4 Stunden 48 Minuten, 16 Tage 19 Stunden 12 Minuten und 36 Tage. Vorforschungen kommen hinzu. Die übrigen Forschungsstufen sind nach Akademiestufe auf maximal 14 Tage begrenzt; Details und Importregel stehen in `docs/BALANCE_IMPORT.md`.

Die zwölf Truppenforschungen sind wieder aktiv. Es gibt 129 Technologien mit 963 Stufen; Militär umfasst 55 Technologien mit 318 Stufen. Bestehende abgeschlossene Freischaltungen zählen in ihrer jeweiligen Welt, laufende Forschungsaufträge werden regulär abgeschlossen.

Ausbildungskosten entsprechen der Quelle einschließlich Stein. Beispiel: 2.000 T4-Infanteristen kosten ohne Boni 360.000 Nahrung, 720.000 Stein und 180.000 Gold. Forschungsrabatte werden vor der abschließenden Aufrundung des Gesamtauftrags angewendet.

Die originalen Heilzeiten betragen 0,5 / 1 / 2 / 3 / 4 Sekunden je Einheit. Bezahlt wird weiterhin der bestehende tierabhängige Anteil der Ausbildungskosten. Kristallheilung bleibt gemäß `CRYSTAL_ECONOMY.md` ausgeschlossen.

## Monster und Progression

`MonsterPower::profile()` verbindet Monsterstufe, Truppenstufe und Marschkapazität. Je zwei normale Monsterstufen gehören zu einer Truppenstufe; die zweite verlangt eine größere bzw. besser erforschte Armee. Die Mengen werden durch `php tools/rebalance-monsters.php --apply` angepasst, originale Monsterwerte und `source_amount` bleiben erhalten. Regionale Bosse verwenden Rallykapazitäten, Drachen und Magdar T5-Endspielrallys mit steigenden Anforderungen. Die Kurve ist eine Grundlage für weitere Spieltests, keine Garantie für ein bestimmtes Fortschrittstempo.

## Bestehende Spielstände

Vor einer Umstellung auf einer weiteren Installation zunächst `php tools/migrate-five-tiers.php` ausführen und die Vorschau prüfen. `--apply` sichert alle betroffenen Zeilen unter `data/balance-history/local-five-tiers-backup-*.json`, führt die Änderung in einer Transaktion aus und setzt einen einmaligen Marker.

Vorhandene T6–T10 werden mit gleicher Anzahl zu T5 derselben Truppenart. Das umfasst Armeen unterwegs, Garnisonen und gespeicherte Formationen. Laufende Ausbildung und Beförderung behalten bezahlte Kosten, Anzahl und Endzeit; ihr Ergebnis wird T5. Verwundete und laufende Heilaufträge behalten ihre Einträge und Timer, werden bei Heilungsabschluss als T5 gutgeschrieben. Historische Truppendefinitionen bleiben für alte Berichte verfügbar. Lebende Monster behalten ihren prozentualen Gesundheitszustand auf der neuen Kurve.

Alle neuen Aufträge prüfen Welt, Besitz, Forschung, Ressourcen und Kapazität serverseitig. Wiederholte Anfragen mit demselben `operation_key` buchen nicht doppelt. Bereits bezahlte Aufträge behalten ihren Kostenbeleg für Abbrucherstattungen und werden zeitlich nicht rückwirkend verändert.

## Oberfläche und Prüfung

Die Haupt-App unter `/city#city` verwendet vorhandene gezeichnete Truppenporträts, fünf Stufenkarten und sechs Grundwerte. Gesperrte Stufen führen zur Akademie bzw. zur benötigten Forschung. Die drei Ausbildungsgebäude bleiben unabhängig bedienbar. Desktop, schmale Handys und Querformat verwenden dieselben Spielregeln.

- `tests/five_tier_sources.php`: Originalwerte, ID-Abbildung und 15-Stunden-Ziel.
- `tests/five_tier_transition.php`: Bestände, Aufträge, Gesundheit, Sicherung und einmalige Gutschriften in einer isolierten Datenbank.
- `tests/training_unlocks.php`, `training_buildings.php`, `training_costs.php`, `training_durations.php`: echte Freischaltungen, Weltgrenzen, parallele Gebäude, Preise, Boni und alte Timer.
- `tests/research_catalog.js`, `research_snapshot.php`, `research_effects.php`, `research_combat.php`, `research_live_services.php`: Forschungsbaum und tatsächliche Wirkungen.
- `tests/monster_balance.php`, `hospital_healing.php`: Monsterprogression und Heilung.
- `tests/training_app.cjs`, `training_mobile_layout.cjs`, `training_layout.cjs`, `research_app.cjs`: Browserabläufe und responsive Darstellung mit wegwerfbaren Testkonten.

Browserformate: 1280×800, 390×844, 320×568, 844×390 und 568×320. Prüfungen auf physischen iOS-/Android-Geräten stehen noch aus.
