# Monster-Kampfberichte

Monsterangriffe öffnen in **Post → Berichte** einen langen, durchgehend scrollbaren Bericht nach der gelieferten Referenz: schmale Kopfzeile mit Ort und Zeit, Kampfergebnis über beiden Seiten, gemeinsame Verlusttabelle, Beute, Truppenaufstellung, Kampfstärke, Erfahrung, Hunter-Meisterschaft, Relikte und aktive Kampfboni. Die Anzeige verwendet direkt die gemeinsamen `.cr-*`-Bausteine aus `combat-report.css` sowie die `--ui-*`-Farben aus `village-theme.css`.

Hunter-Meisterschaft, Relikte und Werteboni sind beim Öffnen sichtbar, bleiben aber als zugängliche Bereiche einzeln einklappbar. Die neun wirksamen Boni stehen als kompakte Liste untereinander; Monster erhalten keine erfundenen Truppentyp-Boni. „Kampfdetails“ in der festen Fußleiste öffnet die genaue Truppenbilanz in einem eigenen Dialog. Zurück und Escape schließen zunächst die Details und dann den Bericht. „Kopieren“ kopiert die Zusammenfassung einschließlich Monster-HP in die Zwischenablage. Blättern, Rückkehr zur Post und Löschen bleiben am Ende der Übersicht verfügbar. Direkte Links `/reports/:id` verwenden denselben Renderer und dieselben Aktionen.

## Verbleibendes Monsterleben auf der Weltkarte

Überlebt ein Monster mit Schaden, zeigt sein Kartenmarker einen proportional gefüllten Lebensbalken und **aktuelle HP / maximale HP**. Unverletzte Monster erhalten keinen zusätzlichen Balken. Bei weiteren Angriffen aktualisiert die reguläre Kartenabfrage die Werte am vorhandenen Marker; besiegte Ziele verschwinden. Solo-Monster und Rally-Gegner verwenden dieselbe Anzeige, ohne die Touch-Fläche oder Kartenbewegung zu blockieren.

`MonsterData::mapData` liefert `hp_max` und die gemeinsame Definition für Spielstand und Weltkartensuche. Der vollständige HP-Pool entspricht wie beim Spawn `stats.hp × amount`, mit Absicherung für ältere überhöhte HP-Werte. Das Zielmenü nutzt denselben Maximalwert. Im historischen Kampfbericht bezeichnet „nach / vor Kampf“ dagegen die HP dieses konkreten Gefechts. Es ist keine Migration nötig.

## Gespeicherte Kampfdaten

`BattleEngine` schreibt mit `report_version: 3` die tatsächlichen Angriffs-, Verteidigungs-, HP- und Machtwerte sowie die effektiven Truppenboni in `combat_snapshot`. `army_power`, `required_power` und `power_ratio` halten die für den Ausgang maßgebliche Siegesschwelle fest. Talent-, Zusammensetzungs- und Monsterboni sind bereits angewendet. `monster_snapshot` enthält die damaligen Monstergrundwerte, Stufe, Darstellung und benötigte Macht. Monster sind eine neutrale Gruppe; ihnen werden keine erfundenen Infanterie-, Kavallerie- oder Bogenschützenwerte zugewiesen.

`MonsterReport::capture` ergänzt beim Kampfabschluss Name, Porträt, Allianz, Startkoordinaten, ausgerüstete Relikte und Hunter-Talente. Die Aufnahme erfolgt vor der Vergabe der Kampf-XP. Bei Rallys enthält jeder Bericht seine eigenen Truppen und Boni; `rally_combat_snapshot` zeigt zusätzlich die gesamte beteiligte Armee. PvP- und Spähberichte behalten ihre eigenen Ansichten.

Alte Berichte bleiben lesbar. Nicht gespeicherte Boni und Ausrüstung werden ausdrücklich als fehlend angezeigt. Aktuelle Spielerwerte ersetzen keine historischen Kampfwerte. Es gibt keine zusätzliche Spielmechanik für leicht Verletzte oder Tödlichkeit.

## Belohnungen und Postaktionen

Ressourcen und Gegenstände werden weiterhin genau einmal beim Rückmarsch ausgezahlt. Hunter-XP werden beim Kampfabschluss vergeben. `BattleReportService` liest den tatsächlichen Marsch-/Rallystatus für `reward_delivery`; ein unbekannter Zustand wird nicht als ausgezahlt dargestellt. Öffnen und Statusabfragen lösen keine Auszahlung aus. Ein öffentlicher Charm wird als separat einzusammelnder Fund bezeichnet.

`POST /api/battle/report/:id/delete` verlangt Sitzung und CSRF-Token und prüft Spieler, Welt und Monsterziel. Es blendet den Bericht über `hidden_by_attacker` aus. Kampfdatensatz und Rückmarsch bleiben erhalten. Die Post übernimmt diesen Zustand sowie den Gelesenstatus; erneut gesendetes Löschen verändert keine Belohnung. Die Berichtserweiterung benötigt keine eigene Schemamigration.

## Prüfung

- `php tests/monster_reports.php`: historische Werte, Rallyanteile, echte Solo-Auflösung, Rechte, Weltgrenzen, genau einmal ausgezahlte Rückmärsche, Löschen vor/nach der Rückkehr sowie zwei erfolglose Angriffe mit sinkenden Rest-HP und unverändertem Maximalwert.
- `php tests/monster_rallies.php` und `php tests/march_composition.php`: vorhandene Rally- und Marschabläufe.
- `php tools/preview-feature-fixture.php --port=18976 --monster-reports --monster-health`, dann `node tests/monster_report_app.cjs`: echte Haupt-App mit isoliertem Vorschaukonto; 1280×800, 390×844, 320×568, 844×390 und 568×320, gemeinsames Spielerbericht-Layout, feste Aktionen, Detaildialog, Zurück/Escape, Kopieren, Erhalt der Scrollposition bei Updates, Postintegration, Altberichte, Niederlage, Rally, Direktlink, CSRF und Löschen. Der Test verändert nur die Vorschau. `PLAYWRIGHT_MODULE` und `MONSTER_REPORT_URL` können überschrieben werden.
- `node tests/monster_health_app.cjs` auf derselben Vorschau: gesunde und verletzte Solo-/Rally-Monster inklusive 1 HP, korrekte X/Y-Werte und Füllung, erreichbare Touch-Ziele in fünf Formaten, übereinstimmende Maximalwerte in Suche und Zielmenü sowie Aktualisierung und Entfernen beim nächsten Spielstandabruf.
- `php tests/map_search.php`: bestehende Such-, Filter-, Welt- und Authentifizierungsregeln.

Bildschirmaufnahmen liegen in `artifacts/monster-report-review` und `artifacts/monster-health-review`. Ein Test auf einem physischen Mobilgerät steht aus.
