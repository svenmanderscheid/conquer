# Handelsmarkt und VIP-Handel

Der Handelsmarkt zeigt eine Karawane mit echten Inventargegenständen und gezielten Reliktfragmenten. Angebote werden um 00:00, 08:00 und 16:00 UTC erneuert. Handelsmarktstufe 1 zeigt vier, Stufe 30 zeigt bis zu 25 zulässige Angebote (derzeit 20) (dazwischen stufenweise). Ein Ausbau legt zusätzliche Angebote derselben Rotation frei und würfelt gekaufte Slots nicht neu aus. Bestand und Preise bleiben bei Neuladen erhalten. Der normale Händler ist separat verfügbar: Er zeigt acht persönliche Angebote, ersetzt einen gekauften Platz sofort aus seinem Tageskatalog und erneuert den gesamten Bestand täglich um 00:00 UTC. Rohstoffe werden 1:1 getauscht, damit keine verlustreichen Kurse oder Gewinnschleifen durch Rücktausch entstehen. Festgelegte Sonderangebote dürfen Kristalle kosten und zahlen dafür leicht mehr Rohstoff aus. Die Karten zeigen groß das erhaltene Material; der Aktionsknopf zeigt ausschließlich die Kosten mit Währungssymbol.

Der Referenzkatalog umfasst 52 Karten aus den gelieferten Shopbildern. Alle 52 Angebote werden im VIP-Shop angezeigt; die in den Bildern erkennbaren Kristallpreise gelten dort als ausdrücklich freigegebene VIP-Käufe. Die allgemeine Sperre für Crystal-Sofortkäufe außerhalb des VIP-Shops bleibt bestehen. Höhere VIP-Stufen behalten alle früheren Freischaltungen. Wöchentliche Bestände werden montags um 00:00 UTC erneuert und gelten über alle Welten eines Kontos. Die Screenshotpreise sind Gesamtpreise für „Alle kaufen“; Einzelpreise sind Gesamtpreis geteilt durch Wochenmenge. Eine höhere Marktstufe beeinflusst den VIP-Bestand nicht.

Die Oberfläche startet in „Für dich“ mit den bereits freigeschalteten Angeboten. „Alle Stufen“ zeigt den vollständigen Katalog. Kategorien für Rohstoffe, Beschleuniger, Boni, VIP & Energie sowie Truhen & Relikte verkürzen die Liste zusätzlich; Angebote bleiben nach VIP-Stufe gruppiert. Auswahl und Scrollposition bleiben bei Käufen und Serveraktualisierungen erhalten.

## Spielwirkungen

Vier zusätzliche Verbrauchsitems ergänzen den bestehenden Katalog: Energieflasche 10 AP, ein zufälliges legendäres Fragment, ein gezieltes Portalsphäre-Fragment und das Große Kriegshorn. Das Horn nutzt das vorhandene Originalbild und erhöht die Kapazität neu gestarteter Märsche für eine Stunde um 50 %. Die kleinen/großen roten Banner entsprechen +10/+20 % Marschkapazität; blaue/epische Marschhelme +25/+50 % Marschgeschwindigkeit. Diese Zahlen sind Conquer-Balancewerte: die Fotos zeigen die Symbole, aber keine vollständigen Effektbeschreibungen. Rohstoff-, Beschleunigungs-, AP-, VIP- und Produktionswerte entsprechen den erkennbaren Karten.

## Schatztruhen

Alle 52 VIP-Angebote sind als Gegenstände mit positiver Chance sowohl in blauen/Silber- als auch Goldtruhen erhältlich. Gold- und Platintruhen werden dabei als ungeöffnete Inventaritems vergeben; kein automatisches rekursives Öffnen. Gezielte Portalsphäre-Fragmente liegen ebenfalls zunächst im Inventar und werden erst beim Benutzen gutgeschrieben.

Silbertruhen geben drei, Goldtruhen vier und Platintruhen fünf unabhängige Beuteziehungen. Pro Eintrag und Seltenheit gelten diese Gewichte; die tatsächliche Wahrscheinlichkeit ist Gewicht geteilt durch die Summe der jeweiligen Tabelle.

| Truhe | Gewöhnlich | Selten | Episch | Legendär | Mythisch | Truhe als Item |
|---|---:|---:|---:|---:|---:|---:|
| Silber | 1800 | 300 | 15 | 1 | 1 | 1 |
| Gold | 100 | 180 | 70 | 5 | 2 | 3 |
| Platin | 15 | 50 | 160 | 35 | 8 | 8 |

Nicht jede Seltenheit ist in jeder Tabelle vertreten. Aktuell sind die Silberziehungen zu etwa 81,01 % gewöhnlich, 18,65 % selten und 0,34 % episch. Legendäre Langzeitbeschleuniger bleiben mit etwa 0,0011 % je Eintrag und Ziehung selten. Alle Einträge bleiben serverseitig; es gibt keine VIP-Schranke für Truhenbeute.

## Sicherheit und Prüfung

Käufe lesen Preis, Bestand, VIP-Stufe, Marktstufe und Welt serverseitig. Sie sperren die Spielerzeile und belasten echte Edelsteine beziehungsweise Rohstoffe der aktiven Stadt, vergeben die Gegenstände und schreiben den Verbrauch in einer einzigen Transaktion. Alte Rotationskennungen, falsche Welten, zu niedrige VIP-Stufen und fehlendes Guthaben werden abgelehnt. Gegenstände/Bestände werden bei Fehlern nicht verändert. Ein pausierter oder geschlossener Server erlaubt keine Käufe.

Migration: nur 0078_trading_shop.sql, additive Tabelle trading_shop_purchases. Kein Spieler erhält Testguthaben oder Testgegenstände.

Tests: tests/trading_shop.php — 531 isolierte DB-/HTTP-Prüfungen inklusive echter KingdomService-Aktion, malformed Payloads, aller 52 Kaufpreise, Bestand, Wochen-/8h-Grenzen, Welten, Rückabwicklung und Truhenverfügbarkeit. Der reale TradingHandler wird zusätzlich über einen isolierten HTTP-Server auf Anmeldung, CSRF, falsche Welt, alte slot_idx-Requests, Kauf und Rückgabe des aktualisierten Zustands geprüft. tests/item_catalog.php — 1210 Prüfungen der tatsächlichen Effekte aller 166 Items.

## VIP-Referenzkatalog

Vollständiger, kaufbarer Angebotskatalog aus den gelieferten Shopbildern.

| ID | VIP | Gegenstand | Wochenlimit | Einzelpreis | Screenshot-Gesamtpreis | Rabatt |
|---|---:|---|---:|---|---:|---:|
| vip_01 | 1 | Prestigemedaille · 100 Punkte | 10 | 50 gems | 500 | 50 % |
| vip_02 | 1 | Universell · 5 Minuten | 50 | 3000 food | 150000 | 40 % |
| vip_03 | 2 | 100.000 Nahrung | 20 | 14 gems | 280 | 80 % |
| vip_04 | 2 | 100.000 Holz | 20 | 14 gems | 280 | 80 % |
| vip_05 | 2 | 100.000 Stein | 20 | 14 gems | 280 | 80 % |
| vip_06 | 2 | 100.000 Gold | 20 | 14 gems | 280 | 80 % |
| vip_07 | 3 | Energieflasche · 10 AP | 20 | 10000 food | 200000 | 50 % |
| vip_08 | 3 | Truppen-Lebenspunkte +10 % · 1 Stunde | 5 | 100 gems | 500 | 50 % |
| vip_09 | 3 | Truppenverteidigung +10 % · 1 Stunde | 5 | 100 gems | 500 | 50 % |
| vip_10 | 3 | Truppenangriff +10 % · 1 Stunde | 5 | 100 gems | 500 | 50 % |
| vip_11 | 3 | Marschgeschwindigkeit +25 % · 1 Stunde | 5 | 100 gems | 500 | 50 % |
| vip_12 | 4 | Prestigemedaille · 10 Punkte | 30 | 5000 gold | 150000 | 50 % |
| vip_13 | 4 | Energieflasche · 50 AP | 20 | 50 gems | 1000 | 80 % |
| vip_14 | 4 | Sammelgeschwindigkeit +50 % · 8 Stunden | 5 | 40 gems | 200 | 80 % |
| vip_15 | 4 | Universell · 30 Minuten | 20 | 15000 gold | 300000 | 40 % |
| vip_16 | 5 | Universell · 1 Stunde | 50 | 20 gems | 1000 | 50 % |
| vip_17 | 5 | Universell · 8 Stunden | 20 | 100 gems | 2000 | 50 % |
| vip_18 | 6 | Nahrungsproduktion +25 % · 8 Stunden | 5 | 20 gems | 100 | 80 % |
| vip_19 | 6 | Holzproduktion +25 % · 8 Stunden | 5 | 20 gems | 100 | 80 % |
| vip_20 | 6 | Steinproduktion +25 % · 8 Stunden | 5 | 20 gems | 100 | 80 % |
| vip_21 | 6 | Goldproduktion +25 % · 8 Stunden | 5 | 20 gems | 100 | 80 % |
| vip_22 | 6 | Portalsphäre · Fragment | 10 | 100 gems | 1000 | 80 % |
| vip_23 | 7 | Nahrungsproduktion +25 % · 1 Tag | 5 | 60 gems | 300 | 80 % |
| vip_24 | 7 | Holzproduktion +25 % · 1 Tag | 5 | 60 gems | 300 | 80 % |
| vip_25 | 7 | Steinproduktion +25 % · 1 Tag | 5 | 60 gems | 300 | 80 % |
| vip_26 | 7 | Goldproduktion +25 % · 1 Tag | 5 | 60 gems | 300 | 80 % |
| vip_27 | 8 | 1.000.000 Nahrung | 20 | 100 gems | 2000 | 80 % |
| vip_28 | 8 | 1.000.000 Holz | 20 | 100 gems | 2000 | 80 % |
| vip_29 | 8 | 1.000.000 Stein | 20 | 100 gems | 2000 | 80 % |
| vip_30 | 8 | 1.000.000 Gold | 20 | 100 gems | 2000 | 80 % |
| vip_31 | 8 | Sammelgeschwindigkeit +50 % · 1 Tag | 5 | 120 gems | 600 | 80 % |
| vip_32 | 9 | Prestigemedaille · 500 Punkte | 10 | 250 gems | 2500 | 50 % |
| vip_33 | 9 | Marschkapazität +10 % · 1 Stunde | 5 | 200 gems | 1000 | 80 % |
| vip_34 | 9 | Truppen-Lebenspunkte +20 % · 1 Stunde | 5 | 200 gems | 1000 | 60 % |
| vip_35 | 9 | Truppenverteidigung +20 % · 1 Stunde | 5 | 200 gems | 1000 | 60 % |
| vip_36 | 9 | Truppenangriff +20 % · 1 Stunde | 5 | 200 gems | 1000 | 60 % |
| vip_37 | 10 | Legendäres Reliktfragment | 5 | 1000 gems | 5000 | 50 % |
| vip_38 | 10 | Goldtruhe | 20 | 100 gems | 2000 | 80 % |
| vip_39 | 11 | Marschkapazität +20 % · 1 Stunde | 5 | 500 gems | 2500 | 80 % |
| vip_40 | 11 | Marschgeschwindigkeit +50 % · 1 Stunde | 5 | 200 gems | 1000 | 60 % |
| vip_41 | 12 | Forschung · 1 Tag | 10 | 250 gems | 2500 | 50 % |
| vip_42 | 12 | Ausbildung · 1 Tag | 10 | 250 gems | 2500 | 50 % |
| vip_43 | 12 | Heilung · 1 Tag | 10 | 250 gems | 2500 | 50 % |
| vip_44 | 13 | Forschung · 3 Tage | 5 | 725 gems | 3625 | 50 % |
| vip_45 | 13 | Ausbildung · 3 Tage | 5 | 725 gems | 3625 | 50 % |
| vip_46 | 13 | Heilung · 3 Tage | 5 | 725 gems | 3625 | 50 % |
| vip_47 | 13 | Großes Kriegshorn · 1 Stunde | 5 | 3000 gems | 15000 | 50 % |
| vip_48 | 14 | Forschung · 7 Tage | 3 | 1650 gems | 4950 | 50 % |
| vip_49 | 14 | Ausbildung · 7 Tage | 3 | 1650 gems | 4950 | 50 % |
| vip_50 | 14 | Heilung · 7 Tage | 3 | 1650 gems | 4950 | 50 % |
| vip_51 | 15 | Universell · 30 Tage | 1 | 6500 gems | 6500 | 50 % |
| vip_52 | 16 | Platintruhe | 3 | 2000 gems | 6000 | 50 % |
