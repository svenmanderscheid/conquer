# Kostenlose Schatztruhen

Die Schatzkammer bietet neben den Relikten einen Tab **Schatztruhe**. Kostenlose Öffnungen setzen eine Schatzkammer ab Stufe 1 in der aktiven Welt voraus. Truhen im Inventar bleiben unabhängig davon verwendbar.

Die blaue Schatztruhe ist derselbe Truhentyp wie die früher als Silbertruhe bezeichnete Monsterbelohnung (Gegenstand `10105001`, interner Typ `silver`). Schatzkammer, Inventar, Monsterbeute, Belohnungsbelege und Verwaltung verwenden einheitlich „Blaue Schatztruhe“ bzw. die gemeinsame Übersetzung und das freigegebene Bild `assets/art/items/daily-chest-blue-v1.png`. Bestehende Kennungen, Bestände, Beutetabellen und kostenlose Öffnungen bleiben erhalten.

- **Blaue Schatztruhe:** zehn kostenlose Öffnungen je UTC-Kalendertag. Zwischen zwei Öffnungen müssen mindestens 600 Sekunden liegen, auch über Mitternacht hinweg. Die erste Öffnung ist sofort verfügbar.
- **Goldene Schatztruhe:** eine kostenlose Öffnung alle 86.400 Sekunden, gerechnet ab der letzten Öffnung. Die erste Öffnung ist sofort verfügbar.
- Limits und Zeitstempel gelten für das gesamte Spielerkonto. Ein Weltwechsel erzeugt keine zusätzlichen kostenlosen Truhen.
- Kostenlos öffnen verbraucht weder vorhandene Truhen noch Edelsteine. Gesperrte Öffnungen vergeben keine Beute und verändern keinen Bestand.

## Beute

`data/chest_drops.json` enthält die gewichteten Beutetabellen. Eine blaue Öffnung zieht drei Einträge, eine goldene vier. Jeder Eintrag wird unabhängig mit Zurücklegen gezogen; seine Chance pro Ziehung beträgt `weight / Summe aller Gewichte`. Mehrere gleiche Ziehungen werden vollständig gutgeschrieben. Die Goldtabelle bevorzugt wertvollere Beute. Sämtliche Gegenstände aus dem VIP-Shop sind in beiden Tabellen mit positiver Wahrscheinlichkeit enthalten.

Gegenstände landen im tatsächlichen `player_inventory`, Reliktfragmente in `player_treasures`. Gewonnene Truhengegenstände werden eingelagert und nicht automatisch weiter geöffnet. Ein Öffnen zählt einmal für die tägliche Truhenaufgabe.

## Schnittstellen und Persistenz

- `ChestService::getChestStatus($playerId)` liefert UTC-Zeitstempel als ISO 8601: `server_time`, `free_silver_next_at`, `free_silver_resets_at`, `free_gold_next_at`. Dazu kommen `free_silver_limit`, `free_silver_remaining`, Verfügbarkeitsflags und bestehende Truhenzähler.
- `ChestService::openFreeChest($playerId, 'silver'|'gold')` liefert die tatsächlich vergebenen Beuteeinträge. Aufruf innerhalb einer vorhandenen Kingdom-Transaktion ist möglich.
- Der ältere Endpunkt `/api/treasure/open-chest` unterstützt ausdrücklich `free: true`. Ohne diese Angabe verwendet Silber wie bisher zuerst eine verfügbare kostenlose Öffnung und sonst einen vorhandenen Truhenzähler. Gold und Platin verwenden vorhandene Zähler. `buy_with_gems: true` wird abgelehnt: Crystal-Käufe von Truhen sind ausschließlich über definierte VIP-Shop-Angebote erlaubt (siehe `CRYSTAL_ECONOMY.md`).
- Spielerlock und Zeilensperre schützen vor parallelen Öffnungen. Zahlung, Timer und Beute werden gemeinsam zurückgerollt, falls die Vergabe fehlschlägt.
- `migrations/0077_daily_chests.sql` ergänzt ausschließlich zwei nullable Zeitstempel. `tools/migrate-daily-chests.php` wendet nur diese Migration wiederholbar an und vergibt keine Gegenstände.

## Prüfung

`php tests/daily_chests.php` prüft echte Vergaben in einer temporären Datenbank, Quoten, UTC-Mitternacht, sekundengenaue Freigabe, rollende Goldzeit, Weltwechsel, Hausvoraussetzung, vorhandene Truhen, Transaktionsrollback, bezahlte Öffnungen, zwei konkurrierende Prozesse und den authentifizierten HTTP-Endpunkt einschließlich CSRF. Der Test prüft außerdem alle VIP-Angebote auf positive Beutegewichte in Silber und Gold.

`node tests/treasure_panel.cjs` prüft die vorhandene Reliktausrüstung und beide Tabs auf Desktop, kleinen Smartphones und im Querformat sowie Timer, explizite kostenlose Aktionen, Beuteanzeige, Tageswechsel und die Gebäudesperre.

Die Vereinheitlichung der blauen Truhe wurde am 10. Oktober 2026 in der echten Haupt-App mit einem temporären Testspielstand geprüft: Stadt, Inventar, Schatzkammer und Monsterbeute bei 1280×800, 390×844, 320×568 und 844×390. Alle 16 Ansichten bestanden ohne Browserfehler oder fehlende Bilder. Nachweise: `output/playwright/blue-chest-consistency/`. Katalog- und Belohnungsprüfungen (`tests/reward_catalog.cjs`, `tests/reward_presentation.php`) sowie die englischen, deutschen und französischen Namen einschließlich historischer Belege bestanden ebenfalls. Dies sind lokale Browserprüfungen, keine Veröffentlichung oder Geräteabnahme.
