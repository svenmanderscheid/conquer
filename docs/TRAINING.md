# Truppenausbildung

Stand: 20. September 2026.

## Gebäude und Ablauf

| Gebäude | Interner Schlüssel | Truppenart | Ausbildungsplatz |
|---|---|---|---|
| Kaserne | `barrack` | Infanterie | 1 |
| Schützenlager | `archery_range` | Bogenschützen | 2 |
| Reiterhof | `stable` | Kavallerie | 3 |

Jedes Gebäude bildet unabhängig aus. Eine Beförderung belegt nur das Gebäude ihrer Truppenart. Die Gebäudeauswahl, Timer und Abschlussmeldungen öffnen denselben Ausbildungsbereich der Haupt-App unter `/city#army`.

Das Menü bietet zehn Stufen je Truppenart, eine drehbare animierte Figur, sieben Grundwerte, Bestandszahlen, Rohstoffbedarf, Anzahl mit Schieberegler und Zahleneingabe, Ausbildungsdauer und passende Inventar-Beschleuniger. Beim Öffnen und beim Einheitenwechsel wird die maximal leistbare Ausbildungsmenge vorausgewählt. Figur, Stufenwahl und Hauptaktion passen ohne Scrollen ins Fenster. Die zehn Stufen sind in zwei feste Fünfergruppen mit Vor-/Zurück-Knöpfen aufgeteilt. Auf Handys steht die Figur oben, im Querformat neben den Bedienelementen. Eine laufende Ausbildung ersetzt das Eingabeformular durch Restzeit und Beschleuniger. Große Rohstoffzahlen werden kompakt angezeigt; Antippen öffnet die genauen Werte. Gesperrte Stufen zeigen die fehlenden Gebäudeanforderungen. T11 ist ausdrücklich noch nicht enthalten. Ein Sofortkauf mit Edelsteinen ist nicht Teil dieses Ausbaus.

## Werte und Freischaltung

`data/troops.json` enthält 30 Einheiten. Stärke, Angriff, Verteidigung, Lebenspunkte, Tödlichkeit, Tempo und Traglast wurden aus den bereitgestellten Kingshot-Screenshots übertragen. `tools/update-training-catalog.py` dokumentiert die Transkription und erzeugt den Katalog erneut.

Die Freischaltung erfolgt für alle drei Truppenarten ausschließlich über das eigene Ausbildungsgebäude und das Stadtzentrum. Die Akademie und Truppenforschungen sind keine Voraussetzung mehr.

| Truppenstufe | Ausbildungsgebäude | Stadtzentrum |
|---|---:|---:|
| T1 | 1 | 1 |
| T2 | 4 | 4 |
| T3 | 7 | 7 |
| T4 | 11 | 11 |
| T5 | 13 | 13 |
| T6 | 16 | 16 |
| T7 | 19 | 19 |
| T8 | 22 | 22 |
| T9 | 26 | 26 |
| T10 | 30 | 30 |

Seit dem Import der Gebäudetabellen benötigt der Ausbau eines Ausbildungsgebäudes auf Stufe 4 Stadtzentrum 4 und Bauernhof 4; alle weiteren Ausbaustufen folgen den Voraussetzungen der gelieferten Kaserne (`docs/BALANCE_IMPORT.md`). Beförderungen stehen ab Ausbildungsgebäude und Stadtzentrum 13 zur Verfügung; zusätzlich muss die Zielstufe freigeschaltet sein. Bereits vorhandene Truppen und laufende Ausbildungen bleiben erhalten.

Die zwölf bisherigen Truppenfreischaltungen sind aus dem Militärforschungsbaum entfernt. Ihre Nachfolger hängen direkt an den weiterhin vorhandenen Vorstufen. Militär bietet damit 43 Forschungen; insgesamt bleiben 117 Technologien mit 951 Forschungsstufen. Abgeschlossene alte Forschungen bleiben als historische Einträge gespeichert. Noch offene Truppenfreischaltungen werden beim nächsten Forschungs-/Spielstandabruf einmalig mit voller Rohstofferstattung beendet und geben den Forschungsplatz frei; die archivierten Kosten stehen in `data/retired-troop-research.json`. Andere Forschungen und ihre Boni bleiben bestehen.

### Rohstoffkosten ab 20. September 2026

Die neue Conquer-Kostenkurve ersetzt die starken Sprünge bei T2–T5 und die frühere exponentielle Fortschreibung. T1 bleibt unverändert. Die folgenden Kosten gelten je Einheit ohne Forschungsrabatte; Stein wird für die Ausbildung weiterhin nicht benötigt.

| Stufe | Faktor gegenüber T1 | Infanterie: Nahrung / Holz | Bogenschützen: Nahrung / Holz / Gold | Kavallerie: Nahrung / Gold |
|---|---:|---:|---:|---:|
| T1 | 1 | 50 / 30 | 40 / 20 / 10 | 60 / 20 |
| T2 | 1,6 | 80 / 48 | 64 / 32 / 16 | 96 / 32 |
| T3 | 2,5 | 125 / 75 | 100 / 50 / 25 | 150 / 50 |
| T4 | 4 | 200 / 120 | 160 / 80 / 40 | 240 / 80 |
| T5 | 6 | 300 / 180 | 240 / 120 / 60 | 360 / 120 |
| T6 | 8,5 | 425 / 255 | 340 / 170 / 85 | 510 / 170 |
| T7 | 11,5 | 575 / 345 | 460 / 230 / 115 | 690 / 230 |
| T8 | 15 | 750 / 450 | 600 / 300 / 150 | 900 / 300 |
| T9 | 19 | 950 / 570 | 760 / 380 / 190 | 1.140 / 380 |
| T10 | 24 | 1.200 / 720 | 960 / 480 / 240 | 1.440 / 480 |

Beispiel: 2.000 T4-Infanteristen kosten 400.000 Nahrung und 240.000 Holz statt 3,6 Mio. Nahrung und 2 Mio. Holz. Die drei Rollen behalten ihre bisherigen Rohstoffprofile. Forschungsrabatte wirken weiterhin auf den Gesamtauftrag; erst dessen Endbetrag wird aufgerundet.

Neue Beförderungen kosten weiterhin 70 % der Zieltruppen-Ausbildung, neue Heilungen den bestehenden tierabhängigen Anteil. Beide nutzen dadurch ebenfalls die günstigeren Grundkosten. Bereits bezahlte Aufträge werden nicht erneut berechnet oder rückwirkend erstattet. Ein Abbruch verwendet weiterhin den gespeicherten tatsächlichen Kostenbeleg. Der Kataloggenerator verwendet feste T1-Ausgangskosten und dieselben Stufenfaktoren; wiederholtes Generieren senkt die Kosten nicht erneut.

### Ausbildungszeiten ab 20. September 2026

Die neue Conquer-Staffel gilt gleichermaßen für Infanterie, Bogenschützen und Kavallerie. Die frühere Kurve (T4: 120 Sekunden, T10: 1.114 Sekunden je Einheit) blockierte schon mittlere Aufträge mehrere Tage. Die neue Kurve hält 2.000 T4 ohne Boni unter acht Stunden und lässt höhere Stufen gleichmäßig ansteigen.

| Stufe | Sekunden je Einheit | 2.000 Einheiten ohne Boni |
|---|---:|---:|
| T1 | 3 | 1 Std. 40 Min. |
| T2 | 5 | 2 Std. 46 Min. 40 Sek. |
| T3 | 9 | 5 Std. |
| T4 | 14 | 7 Std. 46 Min. 40 Sek. |
| T5 | 20 | 11 Std. 6 Min. 40 Sek. |
| T6 | 27 | 15 Std. |
| T7 | 35 | 19 Std. 26 Min. 40 Sek. |
| T8 | 44 | 24 Std. 26 Min. 40 Sek. |
| T9 | 54 | 30 Std. |
| T10 | 65 | 36 Std. 6 Min. 40 Sek. |

Anzeige und Server verwenden dieselben Sekunden aus `data/troops.json`: `ceil(Sekunden × Anzahl / Ausbildungstempo)`. Forschungs-, VIP-, Relikt-, aktive Ausbildungs- und zusätzliche Kasernenboni wirken weiterhin. Beförderungen verwenden weiterhin die halbe Ausbildungszeit der Zielstufe. Gebäudegrenzen, Kapazitäten und Kampfwerte ändern sich nicht.

Die Staffel gilt für neu gestartete Ausbildungen und Beförderungen. Bereits gespeicherte Aufträge behalten ihre Endzeit und bereits angewendete Beschleuniger. Es gibt keine nachträgliche Neuberechnung beim Laden oder Neustarten. `tools/update-training-catalog.py` enthält dieselbe neue Staffel, damit ein erneuter Katalogimport die alten Zeiten nicht wiederherstellt.

Angriff, Verteidigung, Lebenspunkte, Stärke und Traglast fließen in die vorhandenen Spielberechnungen ein. **Tödlichkeit ist zunächst ein angezeigter Katalogwert; die bestehende Kampfformel wurde nicht um einen neuen Tödlichkeitsfaktor erweitert.** Das angezeigte Tempo 11 ist vom Feld `march_speed` getrennt: Die etablierte Weltkarten-Geschwindigkeit von T1–T5 bleibt erhalten, T6–T10 führen diese mit acht zusätzlichen Geschwindigkeitspunkten je Stufe fort. Forschung und aktive Boni werden weiterhin serverseitig angewendet. Die veränderten Kampf- und Traglastwerte sind keine vollständige Neubalancierung der Monster und Weltkarte.

## Server und bestehende Spielstände

`TroopData::forCity()` liefert gemeinsam genutzte Definitionen, Freischaltungen und Kosten. Der Server leitet das Ausbildungsgebäude aus dem Truppencode ab und lehnt fremde Ausbildungsplätze ab. Authentifizierung, aktive Welt, CSRF, Ressourcen, Kapazität und Belegung werden serverseitig geprüft. Stadt-Sperre, Ressourcenabzug und Queue-Eintrag verhindern parallele Doppelbuchungen.

Die Haupt-App sendet einen `operation_key` für Ausbildung und Ausbildungs-Beschleuniger. Unbestätigte Aktionen werden mit demselben Schlüssel in `sessionStorage` je Stadt/Welt aufbewahrt. Nach einem Netzwerkfehler oder Neuladen kann der Benutzer genau diesen Auftrag erneut prüfen. Ein bereits abgeschlossener Auftrag startet dadurch keine neue Ausbildung und verbraucht keinen weiteren Gegenstand. Abgeschlossene Queues werden nur einmal gutgeschrieben. Ein Abbruch erstattet anteilig die am Auftrag gespeicherten Kosten; alte Aufträge ohne Kostenbeleg verwenden den bisherigen Katalog-Fallback.

Migration `0090_training_buildings.sql` ergänzt Schützenlager und Reiterhof für bestehende Städte mit deren bisheriger Kasernenstufe, ordnet laufende Aufträge ihrer Truppenart zu und ergänzt den Kostenbeleg. Truppenbestände und gespeicherte Endzeiten bleiben bestehen. Neue Städte beginnen mit drei Gebäuden auf Stufe 1. Die Migration ist lokal bereits angewendet; weitere Installationen können den Status mit `php tools/migrate-training.php` prüfen und mit `--apply` aktualisieren.

## Darstellung und Mobilbetrieb

`assets/js/training-panel.js` und `assets/css/training-panel.css` verwenden die gemeinsame Oberfläche aus `village-theme.css`. `training-unit.js` erzeugt eigene Chibi-Modelle; es werden keine Figuren aus den Referenzbildern ausgeschnitten. Die 30 kleinen WebP-Porträts stammen aus diesen Modellen und benötigen zusammen etwa 158 KiB. Neugenerierung: `node tools/render-training-icons.cjs`, danach `python tools/encode-training-icons.py` mit Playwright beziehungsweise Pillow.

Die Figur rendert höchstens 30 Bilder pro Sekunde mit begrenzter Pixeldichte; unsichtbare Ansichten pausieren und geschlossene Ansichten geben ihre WebGL-Ressourcen frei. Reduzierte Bewegung wird berücksichtigt. Die Haupt-App und Stadt verwenden dieselbe versionierte Import-Map für die 3D-Module. Wiederholte Zaun- und Tierdetails der neuen Stadtgebäude sind instanziert. Die Höfe besitzen freie Zugänge zu den Stadtwegen.

## Prüfung

- `php tests/training_costs.php`: alle 30 Kostenprofile, tatsächlicher T4-Großauftrag und Rabatt, Ablehnung bei Rohstoffmangel, Beförderung/Heilung sowie Abbruch mit altem Kostenbeleg in einer isolierten Datenbank.
- `php tests/training_durations.php`: Staffel aller 30 Einheiten, T4-Großauftrag, Anzeige/Server mit Boni, Beförderungsquote, alte Endzeiten, Beschleuniger und einmaliger Abschluss in einer isolierten Datenbank.
- `php tests/training_unlocks.php`: alle 30 Stufen an beiden Gebäudegrenzen, echte Ausbildung ohne Forschung, Beförderung ab Stufe 13, Gebäudeausbau und einmalige Erstattung alter Forschungsaufträge.
- `php tests/training_buildings.php`: drei parallele Queues, Freischaltungen, T10-Werte, ungültige Plätze/T11, wiederholte Aufträge, genau eine Gutschrift, Beschleuniger, Beförderung und Abbrucherstattung in einer isolierten Datenbank.
- `php tests/research_effects.php`, `php tests/research_combat.php`, `php tests/defense_lifecycle.php`: Forschungswirkung, Kampfwerte, Beförderung und bestehende Welt-/Verteidigungsabläufe mit dem erweiterten Katalog.
- `node tests/training_layout.cjs`: kein Scrollen oder verdeckte Bedienelemente in fünf Bildschirmformaten, beiden Ansichten sowie bei gesperrten, laufenden, unbezahlbaren und unbestätigten Aufträgen.
- `php tests/city3d_importmap.php`: konsistenter Modulgraph.
- `php tools/preview-feature-fixture.php --port=19321 --training`, danach `node tests/training_app.cjs`: echte Browserabläufe mit einem wegwerfbaren Testkonto. Der Vorschauprozess wird mit Enter beendet.

Browserprüfung: 1280×800, 390×844, 320×568, 844×390 und 568×320; zusätzlich Stadtübersicht, Nahansichten, Gebäudenamen und eingebettete Haupt-App ohne doppeltes HUD. Bilder liegen unter `artifacts/training/`. Tests auf physischen iOS-/Android-Geräten stehen noch aus.

Zusätzlicher Befund außerhalb der Ausbildung: `tests/hospital_healing.php` besteht seine Prüfungen für Heilungsstart, Wiederholung und Inventar-Beschleuniger, scheitert aber anschließend an der Sofortheilung mit Kristallen. `CrystalEconomy` sperrt diesen Kauf derzeit; diese separate Regel wurde hier nicht geändert.
