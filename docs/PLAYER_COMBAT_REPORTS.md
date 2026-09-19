# Berichte für Spielerangriffe

Spielerangriffe öffnen in der Haupt-App unter **Post → Krieg** die Kampfübersicht. Direkte Links `/reports/:id` führen für Stadt- und PvP-Rallyberichte ebenfalls in diese Ansicht. „Kampfdetails“ öffnet ein eigenes Dialogfenster mit aufklappbaren Armeen und Truppenverlusten. Zurück und Escape führen zunächst zur Übersicht und dann zum Postfach. „Kopieren“ kopiert eine Textzusammenfassung und versendet keine Nachricht.

Die kompakte Übersicht zeigt zuerst Ergebnis, beide Seiten, Verluste und Beute, dann den Truppenvergleich. Relikte, Hunter-Talente und Werteboni sind zunächst eingeklappt. Teilnehmerzahlen stehen bei den Rollen; die einzelnen Armeen bleiben in den Vergleichen und Kampfdetails sichtbar. Der Kopf enthält Ort und Zeitpunkt als schmale Zeile. Truppenzeilen zeigen im Detailfenster auf breiten Bildschirmen Name und Verlustwerte nebeneinander, auf Handys in zwei kurzen Zeilen.

## Gespeicherte Werte

`CityCombat` speichert in `data_json.combat` (Version 1) einen unveränderlichen Stand zum Gefechtszeitpunkt. `CombatReport` erfasst die tatsächlichen Teilnehmer einschließlich Rallyarmeen und Verstärkungen, Namen, Koordinaten, ausgerüstete Relikte, gelernte Hunter-Talente und den Hunter-Level. Die Kampfboni stammen aus demselben auf Angriff bzw. Stadtverteidigung abgestimmten Buffsatz wie die Kampfstärke.

Die beiden Seiten enthalten ihre Armeen, Truppentypen, Kampfstärke und Summen für entsandte, gefallene, verwundete sowie einsatzfähige Truppen. Truppenmacht verloren bezeichnet die Grundmacht der gefallenen und verwundeten Einheiten; sie ist keine Veränderung der Gebäudemacht. Truppentyp-Boni werden bei mehreren Teilnehmern anhand der jeweiligen Truppenzahlen gewichtet. Mauerbonus und Verteidigervorteil sind in der defensiven Kampfstärke enthalten und in den Kampfdetails erläutert.

Leicht Verwundete, Fähigkeiten-Auslösungen und Abschüsse je Truppe werden von der aktuellen Kampfabrechnung nicht separat erfasst. Die Oberfläche erfindet diese Werte nicht. Ältere Berichte zeigen nur ihre gespeicherten eigenen Werte; fehlende Gegnerwerte und frühere Boni bleiben unbekannt. Es werden niemals heutige Spielerwerte zur Rekonstruktion eines alten Berichts verwendet.

## Perspektive und Zugriff

Stadtgefechte erzeugen persönliche Berichte. In diesen Datensätzen ist `attacker_id` aus historischen Gründen der **Empfänger**, auch beim Verteidigerbericht. `data_json.perspective` nennt die Rolle; das äußere `outcome` ist relativ zum Empfänger (`attacker_wins` bedeutet dessen Sieg). `combat.outcome` nennt dagegen den tatsächlichen Sieger zwischen Angreifer und Verteidiger. Alle persönlichen Kopien enthalten denselben `combat`-Stand, aber eigene Beute bzw. verlorene Ressourcen.

Das Postfach importiert Stadt-/Rallyberichte nur für ihren Empfänger und beschriftet Verteidigung entsprechend. `BattleReportService` prüft Eigentümer und aktive Welt. Es sind keine Datenbankmigrationen für die zusätzlichen Kampfdaten nötig.

## Prüfungen

- `php tests/city_combat.php`: bestehende Solo-/Rallyabrechnung, konsistente Summen, historische Daten, Zugriffsrechte und einmalige Abrechnung.
- `php tests/combat_reports.php`: gespeicherte Ausrüstung und Talente, Verstärkungen, Hospitalabgleich, spätere Änderungen und Postfachperspektive in einer Wegwerfdatenbank.
- `php tools/preview-feature-fixture.php --port=18978 --combat-reports`, anschließend `node tests/combat_report_app.cjs`: Haupt-App, Desktop, 390 px, 320 px, kurzes Querformat, Dialognavigation, Kopieren, Altberichte und Direktlinks. `PLAYWRIGHT_MODULE` und `COMBAT_FIXTURE_URL` können bei Bedarf gesetzt werden. Screenshots: `artifacts/combat-reports-compact/`.
