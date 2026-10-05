# Kristalle und Heilungsbalance

Stand: 3. Oktober 2026. Aktuelle Entscheidung des Spieleigentümers.

## Kristalle

Der Kristall-Shop ist auf ausdrücklichen Nutzerwunsch unabhängig vom VIP-Shop. Sein eigener fester Katalog in `data/crystal_shop.json` verkauft Rohstoffpakete, Speedups, Teleports und Buffs zu regulären Kristallpreisen. Keine VIP-Stufe, kein Handelsposten und keine Wochenvorräte sind erforderlich. Pro Kauf sind 1–100 Gegenstände möglich; gekaufte Pakete bleiben bis zur Verwendung im Inventar. Preise bereits vorhandener VIP-Produkte entsprechen deren Preis vor dem angegebenen Rabatt, sind aber als feste Werte im separaten Katalog gespeichert. Teleports: Zufall 500, Allianz 1.000, Advanced 2.000 Kristalle. VIP-Rabatte und Wochenlimits gelten ausschließlich für den VIP-Shop.

`crystal.buy` im authentifizierten Kingdom-Endpunkt prüft Welt, serverseitigen Katalog, Preis und Guthaben unter der bestehenden Spielersperre. Abbuchung, Inventargutschrift und Kaufbeleg sind atomar; dieselbe Kaufkennung liefert bei Wiederholung den Beleg ohne weitere Abbuchung. Es gibt keine Echtgeldzahlung. Prüfungen: `tests/crystal_shop.php`, `tests/trading_panel.cjs`, `tests/trading_shop.php`, `tests/crystal_economy.php`.

Kristalle können für den eigenständigen Kristall-Shop, den vollständigen serverseitigen VIP-Shop, für Skins sowie für ausdrücklich definierte Angebote im Rohstofftab „Händler“ ausgegeben werden. Dort sind sie eine gelegentliche Tauschwährung; Preis und Belohnung kommen aus dem festen Serverkatalog. Es gibt keinen allgemeinen Kristallpreis für Minuten. Direkte Sofortabschlüsse von Bau, Forschung und Heilung sowie Crystal-Käufe über Inventarshop, Karawane oder alte Kaufendpunkte bleiben gesperrt. Der VIP-Shop enthält zusätzlich die 52 Angebote aus den gelieferten Referenzbildern.

`src/Game/CrystalEconomy.php` begrenzt alte allgemeine Gegenstandskäufe weiterhin auf die Kategorie `vip_point`, erlaubt aber definierte Angebote aus dem serverseitigen VIP-Katalog. Bestehende Rohstoffangebote bleiben verfügbar. Skin-Käufe verwenden weiterhin ihren eigenen serverseitigen Katalog. Alte Kaufendpunkte lehnen verbotene Aktionen ab, auch mit ausreichendem Guthaben. Bereits vorhandene Gegenstände und kostenlose Belohnungen bleiben nutzbar.

Thematische Echtgeldpakete können Edelsteine und Rohstoffe als feste Paketbelohnung enthalten. Das ist keine zusätzliche Edelstein-Ausgabe: Preise und Inhalte kommen aus `data/theme_bundles.json`, und eine Gutschrift erfolgt ausschließlich nach bestätigter Provider-Zahlung. Einzelheiten stehen in `THEME_BUNDLES.md`.

## Hospital

Die Basisheilzeiten gelten für alle drei Truppengattungen gleichermaßen:

| Tier | Sekunden pro Truppe | 1.000 Verwundete ohne Boni |
|---|---:|---:|
| T1 | 0,5 | 00:08:20 |
| T2 | 1 | 00:16:40 |
| T3 | 2 | 00:33:20 |
| T4 | 3 | 00:50:00 |
| T5 | 4 | 01:06:40 |

Seit dem 29. September 2026 gelten die Originalzeiten aus `data/balance-source/troop.json`. Aktiv sind T1–T5; frühere Aufträge behalten ihre gespeicherten Endzeiten.

Die Zeiten aller ausgewählten Truppen werden summiert. Zeitreduktionsboni und Heilgeschwindigkeit wirken vor der einmaligen Aufrundung des gesamten Auftrags auf Sekunden:

`Dauer = ceil(Summe(Anzahl × Basiszeit) × max(0.05, 1 − Zeitreduktion) / max(1, 1 + Heilgeschwindigkeit))`

Beispiel: 1.000 T5 benötigen bei +100 % Heilgeschwindigkeit 00:33:20. Heilung kostet je nach Tier 10 bis 22 % (historische höhere Stufen bis 45 %) der Ausbildungskosten, je Ressource und Truppe aufgerundet. Der Start bezahlt Ressourcen genau einmal. Nur Heilungs- und allgemeine Speedups verkürzen einen laufenden Auftrag. Neue Verwundete warten separat. Bereits laufende Behandlungen behalten ihre gespeicherte Endzeit.

Prüfungen: `tests/hospital_healing.php`, `tests/hospital_app.cjs`, `tests/crystal_economy.php`, `tests/trading_shop.php`, `tests/daily_chests.php` und `tests/march_skins.php`.
