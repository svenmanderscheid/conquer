# Schatzkammer

Die Schatzkammer ist über **Menü → Schatzkammer**, **Inventar → Relikte** und direkt durch Antippen des Schatzkammer-Gebäudes erreichbar. Direkter Einstieg: `/city#treasures`.

## Bedienung

Im Tab **Relikte** stehen links alle Schätze zum vertikalen Scrollen. Rechts bleiben fünf Presets, sechs Ausrüstungsplätze und die aktiven Boni sichtbar. Der zusätzliche Tab **Schatztruhe** bietet zehn kostenlose blaue Öffnungen täglich mit zehn Minuten Abstand und eine goldene Öffnung alle 24 Stunden. Die Stufen-Schaltfläche öffnet den Gebäuderausbau. Details: [Kostenlose Schatztruhen](DAILY_CHESTS.md).

- **Ausrüstung:** sechs sichtbare Plätze. Die ersten beiden öffnen ab Gebäudestufe 1, weitere ab 5, 10, 20 und 25.
- **Sammlung:** alle 82 Katalogschätze mit Seltenheit, Fragmentfortschritt, aktueller Stufe und Bonusvorschau. Erst einen Platz, dann einen freigeschalteten Schatz wählen. Belegte Plätze lassen sich ersetzen; angelegte Schätze lassen sich ablegen.
- **Aktive Boni:** Summe der tatsächlich angelegten Schätze dieser Welt. Marsch- und Hospitalplätze sind absolute Werte; die übrigen Boni sind Prozentwerte.
- **Fünf Presets:** Nummer wählen und **Speichern** drücken sichert die aktuell angelegten sechs Relikte in dieser Welt. **Anlegen** lädt die gewählte gespeicherte Vorlage und ersetzt die gesamte Ausrüstung. Ein ausdrücklich leer gespeichertes Preset legt alle Relikte ab; ein noch nie gespeicherter Platz kann nicht geladen werden.

Fragmente stammen aus Schatztruhen, Fragmentpaketen und Dracheneiern unter **Inventar → Sonstiges**. Alle 77 gelieferten Reliktbilder sind im [vollständigen Schatzkatalog](TREASURE_CATALOG.md) zugeordnet. Genügend Fragmente schalten einen Schatz automatisch frei und erhöhen seine Stufe. Die Sammlung gehört zum Account, die Ausrüstung zur jeweiligen Spielwelt. Neue Welten beginnen ohne angelegte Schätze.

## Wirkung und Speicherung

`TreasureService` validiert Besitz, Fragmentstufe und Gebäudestufe serverseitig. Ersetzen und Verschieben erfolgen atomar. `BuffEngine` berücksichtigt ausschließlich die freigeschalteten Ausrüstungsplätze der angefragten Welt. Produktionsressourcen werden vor Ausrüstungswechseln und vor dem Stufenanstieg eines angelegten Schatzes mit dem bisherigen Bonus abgerechnet.

Migration `0074_treasure_loadouts.sql` ergänzt weltbezogene Plätze und übernimmt die bisherige Ausrüstung einmalig für bereits vorhandene Spielerwelten. Die Fragmentbestände bleiben unverändert. Bestehende Auftrags- und Marschzeiten werden durch einen Ausrüstungswechsel nicht nachträglich neu berechnet.

Migration `0076_treasure_presets.sql` ergänzt dauerhaft gespeicherte Presets pro Spieler und Welt. Sie kann lokal gezielt mit `php tools/migrate-treasure-presets.php` angewendet werden. Gespeicherte Presets enthalten nur sechs Reliktcodes oder `null`, keine kopierten Bonuswerte; beim Laden gelten die aktuellen Fragmentstufen. Fehlender Besitz, gesperrte Ausrüstungsplätze oder ungültige Einträge brechen den gesamten Wechsel ab. Die bisherige Ausrüstung bleibt dann vollständig erhalten.

Die Kingdom-API liefert `state.treasures.presets` immer als fünf Einträge `{slot: 1…5, saved: boolean, items: [sechs Codes oder null]}`. Die Aktionen `treasure.preset_save` und `treasure.preset_apply` erwarten `preset: 1…5` und liefern den aktualisierten Zustand. Speichern liest die aktuelle Ausrüstung vom Server; vom Client gesendete Itemlisten werden nicht übernommen.

## Prüfung

- `tests/treasure_loadouts.php`: isolierte Datenbank- und HTTP-Prüfung von Ausrüstung, Spielwelten, Fragmentstufen und tatsächlichen Bonuseffekten.
- `tests/treasure_presets.php`: 53 isolierte Prüfungen von Presets, vollständigen Wechseln, Besitz, Freischaltungen, Produktionsabrechnung, Rückabwicklung und Trennung nach Spieler und Welt.
- `tests/treasure_panel.cjs`: isolierte Oberflächenprüfung mit Testdaten und simulierten Aktionen, einschließlich kleiner Handyfenster.
- `tests/window_panels.cjs`: Regression der bestehenden Inventar- und Popupansichten.
- `tests/research_effects.php`: gemeinsame Bonusberechnung.

Die Oberflächentests führen keine Aktionen mit echten Spielerkonten aus.
