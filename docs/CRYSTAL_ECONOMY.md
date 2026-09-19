# Kristalle und Heilungsbalance

Stand: 18. September 2026. Aktuelle Entscheidung des Spieleigentümers.

## Kristalle

Kristalle können für den vollständigen, serverseitigen VIP-Shop, für Skins sowie für ausdrücklich definierte Angebote im Rohstofftab „Händler“ ausgegeben werden. Dort sind sie eine gelegentliche Tauschwährung; Preis und Belohnung kommen aus dem festen Serverkatalog. Es gibt keinen allgemeinen Kristallpreis für Minuten. Direkte Sofortabschlüsse von Bau, Forschung und Heilung sowie Crystal-Käufe über Inventarshop, Karawane oder alte Kaufendpunkte bleiben gesperrt. Der VIP-Shop enthält zusätzlich die 52 Angebote aus den gelieferten Referenzbildern.

`src/Game/CrystalEconomy.php` begrenzt allgemeine Gegenstandskäufe weiterhin auf die Kategorie `vip_point`, erlaubt aber definierte Angebote aus dem serverseitigen VIP-Katalog. Bestehende Rohstoffangebote bleiben verfügbar. Skin-Käufe verwenden weiterhin ihren eigenen serverseitigen Katalog. Alte Kaufendpunkte lehnen verbotene Aktionen ab, auch mit ausreichendem Guthaben. Bereits vorhandene Gegenstände und kostenlose Belohnungen bleiben nutzbar.

Thematische Echtgeldpakete können Edelsteine und Rohstoffe als feste Paketbelohnung enthalten. Das ist keine zusätzliche Edelstein-Ausgabe: Preise und Inhalte kommen aus `data/theme_bundles.json`, und eine Gutschrift erfolgt ausschließlich nach bestätigter Provider-Zahlung. Einzelheiten stehen in `THEME_BUNDLES.md`.

## Hospital

Die Basisheilzeiten gelten für alle drei Truppengattungen gleichermaßen:

| Tier | Sekunden pro Truppe | 1.000 Verwundete ohne Boni |
|---|---:|---:|
| T1 | 1 | 00:16:40 |
| T2 | 2 | 00:33:20 |
| T3 | 3 | 00:50:00 |
| T4 | 4 | 01:06:40 |
| T5 | 5 | 01:23:20 |
| T6 | 7 | 01:56:40 |
| T7 | 9 | 02:30:00 |
| T8 | 11 | 03:03:20 |
| T9 | 13 | 03:36:40 |
| T10 | 15 | 04:10:00 |

Die Staffel ist ein Ausgangspunkt für Spieltests. T10 orientiert sich am Durchschnitt des gelieferten Screenshots (3.006 Verwundete, 12:31:30 ursprüngliche Heilzeit); die niedrigeren Stufen sind eigene Balancewerte. Die Tabelle liegt in `data/troops.json`.

Die Zeiten aller ausgewählten Truppen werden summiert. Zeitreduktionsboni und Heilgeschwindigkeit wirken vor der einmaligen Aufrundung des gesamten Auftrags auf Sekunden:

`Dauer = ceil(Summe(Anzahl × Basiszeit) × max(0.05, 1 − Zeitreduktion) / max(1, 1 + Heilgeschwindigkeit))`

Beispiel: 1.000 T10 benötigen bei +100 % Heilgeschwindigkeit 01:06:40. Heilung kostet je nach Tier 10 bis 45 % der Ausbildungskosten, je Ressource und Truppe aufgerundet. Der Start bezahlt Ressourcen genau einmal. Nur Heilungs- und allgemeine Speedups verkürzen einen laufenden Auftrag. Neue Verwundete warten separat. Bereits laufende Behandlungen behalten ihre gespeicherte Endzeit.

Prüfungen: `tests/hospital_healing.php`, `tests/hospital_app.cjs`, `tests/crystal_economy.php`, `tests/trading_shop.php`, `tests/daily_chests.php` und `tests/march_skins.php`.
