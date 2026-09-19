# Itemkatalog nach den Inventarreferenzen

166 Verbrauchsitems und 82 anlegbare Relikte sind verfügbar. Die Referenzrelikte stehen vollständig in [TREASURE_CATALOG.md](TREASURE_CATALOG.md).

Zusätzlich enthält der Katalog 26 Materialien aus dem Balanceimport: Goldene Säule und Allianzabzeichen werden bei passenden Gebäudeausbauten automatisch verbraucht. Für 24 weitere Quellitems fehlt eine vollständige Wirkungsdefinition, darunter die Inhalte der Beschleunigerkisten Stufe 3 und 4. Sie bleiben erhalten und werden als noch nicht verwendbar erklärt; es wurden keine Wirkungen oder Kisteninhalte erfunden. Die offenen Quellcodes stehen in [BALANCE_IMPORT.md](BALANCE_IMPORT.md).

## Im Spiel

**Inventar → Alle Items** zeigt den vollständigen Verbrauchskatalog; **Im Besitz** zeigt nur vorhandene Gegenstände. Nicht besessene Gegenstände bleiben sichtbar mit Bestand 0 und sind nicht verwendbar. Seltenheiten, Mengen und Zeitwerte erscheinen direkt auf den Kacheln.

Die fünf Reiter sind **Rohstoffe, Beschleuniger, Boni, Relikte und Sonstiges**. Die Sammlung scrollt durchgehend und zeigt die ausgewählten Gegenstandsdetails direkt im Inventar. Bis 539 px Breite stehen vier Kacheln nebeneinander und die Details darunter. Breitere Ansichten zeigen die Details rechts neben drei, ab 700 px vier und ab 1000 px fünf Kachelspalten. Ein Tipp markiert den Gegenstand und zeigt Bild, Bestand, vollständige Beschreibung und die bestehende Aktion ohne zusätzliches Detailfenster. Serveraktualisierungen bewahren Auswahl und Scrollposition. Relikte bleiben im Inventar erreichbar, einschließlich Ausrüstungsplätzen und aktiven Boni; die eigenständige Schatzkammer bleibt zusätzlich verfügbar.

Rohstofficons unterscheiden Bündel, Kiste und Wagen nach Paketgröße. Blaue Beschleuniger tragen für Bau, Forschung, Ausbildung oder Heilung ein zusätzliches Symbol. Die Original-SVGs liegen unter `assets/art/items/backpack/`; Werte, Bestände, Seltenheiten und Wirkungen stammen weiterhin vom Server. Die Screenshots vom 13. September 2026 dienen als Aufbau- und Motivvorlage.

Diese Icons werden auch mit den serverseitigen Itemdefinitionen, Monster-Droplisten und Kampfberichten ausgeliefert. Goldtruhen und Goldpakete werden anhand ihrer Itemcodes unterschieden. Alte Berichte erhalten fehlende Bildmetadaten beim Lesen; ihre Kampfergebnisse und Beutemengen bleiben unverändert.

Das Öffnen einer Inventar- oder kostenlosen Schatztruhe liefert die tatsächlich gutgeschriebenen Gegenstände und Reliktfragmente unter `result.drops`, einschließlich Code, Name, Icon, Seltenheit und Menge. Rohstoffkisten zeigen den gezogenen Rohstoff und Betrag; Fragmentpakete benennen das ausgewählte Relikt. Der Client sendet bei Inventarverwendungen und kostenlosen Truhen eine `operation_key`. Wiederholungen mit derselben Kennung liefern die ursprüngliche Beute zurück, auch nach Verbrauch der letzten Truhe oder während der Abklingzeit. Abweichende Nutzlasten werden abgelehnt; vorübergehende Sperrkonflikte liefern HTTP 503, damit der Client die Kennung zum Wiederholen behält.

Der Diagramm-Button rechts im Inventarkopf öffnet **Rohstoffe & Beschleuniger**. „In Items“ summiert feste Paketwerte mal Besitzmenge für Nahrung, Holz, Stein, Gold, Edelsteine, Energie und Prestige; „Im Vorrat“ zeigt die aktive Stadt bzw. den Kontostand. Zufalls- und Auswahltruhen zählen nicht mit. Der zweite Reiter summiert universelle, Ausbildungs-, Bau-, Forschungs- und Heilungsbeschleuniger getrennt. Tage, Stunden und Minuten ändern nur die Zeitdarstellung; universelle Zeit wird nicht zusätzlich in Spezialbereichen eingerechnet. Die Übersicht liest die vorhandenen Spielstände und aktualisiert offene Werte bei Serverabfragen. Schließen, Escape und Browser-Zurück führen zur bisherigen Inventarposition zurück. Prüfungen: `tests/inventory_overview.cjs` und `tests/inventory_overview_app.cjs`; isolierter Teststand: `tools/preview-feature-fixture.php --inventory-overview --port=18964`.

Der Katalog enthält 193 Items, darunter jetzt drei Teleporter. Alle neuen Varianten sind in den bestehenden Truhen-Beutetabellen erreichbar. Gegenstände werden nur gezielt für Tests an bestehende Konten verteilt.

Die 43 alten Itemcodes, Mengen, Dauern, Bonusstärken und bestehenden Preise bleiben erhalten. Neue Codes beginnen bei 10200000; Handelsvarianten ab 10300000. Die Originalfotos geben die grafische Richtung vor; neue Zahlen und Effekte sind für Conquer definierte Werte.

„Alle benutzen“ verbraucht den Bestand des ausgewählten Itemtyps über `inventory.use` mit `use_all: true` und einer `operation_key`. Der Server ermittelt den Bestand unter Sperre; vom Client kommt keine vertrauenswürdige Mengenangabe. Rohstoffe und VIP-Punkte werden mit dem gesamten Paketwert gutgeschrieben, Boni verlängern ihre Dauer. Bei Truhen, Rohstoffkisten und Fragmentpaketen wird jedes Item unabhängig ausgelost und die Beute anschließend je Gegenstand zusammengefasst. Neu gewonnene Items desselben Typs gehören nicht zum ursprünglichen Stapel. Der komplette Vorgang einschließlich Verbrauch, Belohnungen und Wiederholungsbeleg ist atomar. Aktionspunkte und Beschleuniger verbrauchen höchstens die bis zur vollen Leiste oder zum Auftragsabschluss benötigte Menge; überzählige Items bleiben erhalten. Teleporter behalten die Einzelverwendung. Prüfungen: `tests/inventory_bulk.php` und `tests/inventory_app.cjs`.

## Regeln

- Relikte werden angelegt; Verbrauchsitems werden nach erfolgreicher Anwendung genau einmal abgezogen.
- Rohstoffkisten geben eine serverseitig zufällige Ressource innerhalb der angegebenen Grenzen. Fragmentpakete und Dracheneier geben Fragmente der angegebenen Seltenheit. Eier sind Fragmentquellen.
- Temporäre Wirtschafts- und Kampfboni gelten accountweit. Schild und Spähschutz schützen nur die aktive Stadt. Gleiche Bonusstärke verlängert die Zeit; eine höhere Stärke ersetzt die schwächere mit ihrer eigenen Laufzeit. Ein schwächeres Item überschreibt keinen aktiven stärkeren Bonus.
- Teleporter versetzen die aktive Stadt nur auf ein vollständig freies, trockenes 4×4-Feld. Der Advanced-Teleporter erlaubt die freie Zielwahl in der aktuellen Welt. Der Allianz-Teleporter erlaubt Ziele höchstens 12 Felder von einer verbündeten Stadt entfernt; der Radius wird aus allen Allianzstädten der aktuellen Welt gebildet. Der Zufallsteleporter sucht automatisch einen Platz in der aktuellen Welt. Marsch-, Rally-, Verstärkungs- und Expeditionsbindungen blockieren den Verbrauch. Weltplatzierung und Kämpfe sind während der Transaktion gesperrt. Eine ungültige oder inzwischen belegte Auswahl verbraucht keinen Gegenstand.
- Beschleuniger verkürzen passende laufende Aufträge. Überschüssige Zeit verfällt. Laufzeiten werden nicht negativ.

## Prüfung

`tests/item_catalog.php` verwendet eine vollständig isolierte Datenbank und prüft alle Katalogitems über echte Kingdom-Aktionen. Dazu gehören Auszahlung, Warteschlangen, sämtliche Bonustypen, Fragmente, die drei Teleport-Modi, Truhenverfügbarkeit und Besitz-/Verbrauchsschutz. Oberflächentests: `tests/window_panels.cjs` und `tests/treasure_panel.cjs`.

`tests/inventory_rewards.php` prüft reale Beutegutschriften, Bilder und Metadaten, Wiederholungen nach Verbrauch der letzten Truhe, feste und zufällige Fragmente, Rohstoffkisten, kostenlosen Truhenabruf trotz laufender Abklingzeit, Rollback bei fehlerhafter Beute sowie die authentifizierten älteren HTTP-Endpunkte in einer isolierten Datenbank. `tests/daily_chests.php` deckt zusätzlich konkurrierende kostenlose Ansprüche ab.

## Vollständige Verbrauchsliste

| Code | Gegenstand | Seltenheit | Wirkung |
|---|---|---|---|
| 10101001 | 50.000 Nahrung | rare | Fügt sofort 50.000 Nahrung hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10101002 | 200.000 Nahrung | rare | Fügt sofort 200.000 Nahrung hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10101003 | 500.000 Nahrung | rare | Fügt sofort 500.000 Nahrung hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10101011 | 50.000 Holz | rare | Fügt sofort 50.000 Holz hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10101012 | 200.000 Holz | rare | Fügt sofort 200.000 Holz hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10101021 | 50.000 Stein | rare | Fügt sofort 50.000 Stein hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10101022 | 200.000 Stein | rare | Fügt sofort 200.000 Stein hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10101031 | 25.000 Gold | normal | Fügt sofort 25.000 Gold hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10101032 | 100.000 Gold | rare | Fügt sofort 100.000 Gold hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10101041 | 50 Edelsteine | normal | Fügt sofort 50 Edelsteine hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10101042 | 150 Edelsteine | rare | Fügt sofort 150 Edelsteine hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10101043 | 500 Edelsteine | rare | Fügt sofort 500 Edelsteine hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10102001 | Rohstoffproduktion +25 % · 8 Stunden | rare | Erhöht Rohstoffproduktion für 8 Stunden um 25 %. Gilt accountweit; laufende Auftrags- und Marschzeiten bleiben unverändert. Gleiche Stärke verlängert die Laufzeit; stärkere Boni ersetzen schwächere. |
| 10102002 | Rohstoffproduktion +25 % · 1 Tag | epic | Erhöht Rohstoffproduktion für 1 Tag um 25 %. Gilt accountweit; laufende Auftrags- und Marschzeiten bleiben unverändert. Gleiche Stärke verlängert die Laufzeit; stärkere Boni ersetzen schwächere. |
| 10102011 | Sammelgeschwindigkeit +50 % · 8 Stunden | rare | Erhöht Sammelgeschwindigkeit für 8 Stunden um 50 %. Gilt accountweit; laufende Auftrags- und Marschzeiten bleiben unverändert. Gleiche Stärke verlängert die Laufzeit; stärkere Boni ersetzen schwächere. |
| 10102021 | Baugeschwindigkeit +25 % · 8 Stunden | rare | Erhöht Baugeschwindigkeit für 8 Stunden um 25 %. Gilt accountweit; laufende Auftrags- und Marschzeiten bleiben unverändert. Gleiche Stärke verlängert die Laufzeit; stärkere Boni ersetzen schwächere. |
| 10102031 | Forschungsgeschwindigkeit +25 % · 8 Stunden | rare | Erhöht Forschungsgeschwindigkeit für 8 Stunden um 25 %. Gilt accountweit; laufende Auftrags- und Marschzeiten bleiben unverändert. Gleiche Stärke verlängert die Laufzeit; stärkere Boni ersetzen schwächere. |
| 10102041 | Ausbildungsgeschwindigkeit +25 % · 8 Stunden | rare | Erhöht Ausbildungsgeschwindigkeit für 8 Stunden um 25 %. Gilt accountweit; laufende Auftrags- und Marschzeiten bleiben unverändert. Gleiche Stärke verlängert die Laufzeit; stärkere Boni ersetzen schwächere. |
| 10102051 | Spähschutz · 8 Stunden | rare | Verbirgt Ressourcen, Truppen und Mauerwerte deiner aktiven Stadt 8 Stunden vor Spähern. |
| 10102061 | Königsschild · 8 Stunden | rare | Schützt deine aktive Stadt 8 Stunden vor feindlichen Angriffen. Nicht während eigener feindlicher Märsche nutzbar. |
| 10103001 | Universell · 5 Minuten | normal | Verkürzt einen Bau-, Forschungs-, Ausbildungs- oder Heilungsauftrag um 5 Minuten. Überschüssige Zeit verfällt. |
| 10103002 | Universell · 15 Minuten | normal | Verkürzt einen Bau-, Forschungs-, Ausbildungs- oder Heilungsauftrag um 15 Minuten. Überschüssige Zeit verfällt. |
| 10103003 | Universell · 1 Stunde | rare | Verkürzt einen Bau-, Forschungs-, Ausbildungs- oder Heilungsauftrag um 1 Stunde. Überschüssige Zeit verfällt. |
| 10103004 | Universell · 3 Stunden | rare | Verkürzt einen Bau-, Forschungs-, Ausbildungs- oder Heilungsauftrag um 3 Stunden. Überschüssige Zeit verfällt. |
| 10103005 | Universell · 8 Stunden | rare | Verkürzt einen Bau-, Forschungs-, Ausbildungs- oder Heilungsauftrag um 8 Stunden. Überschüssige Zeit verfällt. |
| 10103006 | Universell · 1 Tag | epic | Verkürzt einen Bau-, Forschungs-, Ausbildungs- oder Heilungsauftrag um 1 Tag. Überschüssige Zeit verfällt. |
| 10103011 | Bauen · 1 Stunde | rare | Verkürzt einen Bauauftrag um 1 Stunde. Überschüssige Zeit verfällt. |
| 10103012 | Bauen · 3 Stunden | rare | Verkürzt einen Bauauftrag um 3 Stunden. Überschüssige Zeit verfällt. |
| 10103013 | Bauen · 8 Stunden | rare | Verkürzt einen Bauauftrag um 8 Stunden. Überschüssige Zeit verfällt. |
| 10103021 | Forschung · 1 Stunde | rare | Verkürzt einen Forschungsauftrag um 1 Stunde. Überschüssige Zeit verfällt. |
| 10103022 | Forschung · 3 Stunden | rare | Verkürzt einen Forschungsauftrag um 3 Stunden. Überschüssige Zeit verfällt. |
| 10103023 | Forschung · 8 Stunden | rare | Verkürzt einen Forschungsauftrag um 8 Stunden. Überschüssige Zeit verfällt. |
| 10103031 | Ausbildung · 1 Stunde | rare | Verkürzt einen Ausbildungsauftrag um 1 Stunde. Überschüssige Zeit verfällt. |
| 10103032 | Ausbildung · 3 Stunden | rare | Verkürzt einen Ausbildungsauftrag um 3 Stunden. Überschüssige Zeit verfällt. |
| 10103041 | Heilung · 1 Stunde | rare | Verkürzt die laufende Heilung um 1 Stunde. Überschüssige Zeit verfällt. |
| 10104001 | Energieflasche · 50 AP | normal | Stellt bis zu 50 Aktionspunkte wieder her, höchstens bis zu deinem Maximum. Nur bei fehlenden Aktionspunkten verwendbar. |
| 10104002 | Energieflasche · 200 AP | epic | Stellt bis zu 200 Aktionspunkte wieder her, höchstens bis zu deinem Maximum. Nur bei fehlenden Aktionspunkten verwendbar. |
| 10105001 | Silbertruhe | rare | Enthält zufällige Gegenstände und möglicherweise Reliktfragmente.  |
| 10105002 | Goldtruhe | epic | Enthält zufällige Gegenstände und möglicherweise Reliktfragmente.  |
| 10105003 | Platintruhe | legendary | Enthält zufällige Gegenstände und möglicherweise Reliktfragmente. Auch legendäre und mythische Beute ist möglich. |
| 10106001 | Prestigemedaille · 100 Punkte | rare | Gewährt 100 Prestige-Punkte für deine Prestigestufe. |
| 10106002 | Prestigemedaille · 500 Punkte | rare | Gewährt 500 Prestige-Punkte für deine Prestigestufe. |
| 10106003 | Prestigemedaille · 2.000 Punkte | epic | Gewährt 2.000 Prestige-Punkte für deine Prestigestufe. |
| 10201001 | 1.000 Nahrung | normal | Fügt sofort 1.000 Nahrung hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10201002 | 5.000 Nahrung | normal | Fügt sofort 5.000 Nahrung hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10201003 | 10.000 Nahrung | normal | Fügt sofort 10.000 Nahrung hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10201004 | 100.000 Nahrung | rare | Fügt sofort 100.000 Nahrung hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10201005 | 1.000.000 Nahrung | epic | Fügt sofort 1.000.000 Nahrung hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10201006 | 5.000.000 Nahrung | epic | Fügt sofort 5.000.000 Nahrung hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10201007 | 10.000.000 Nahrung | legendary | Fügt sofort 10.000.000 Nahrung hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10201008 | 1.000 Holz | normal | Fügt sofort 1.000 Holz hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10201009 | 5.000 Holz | normal | Fügt sofort 5.000 Holz hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10201010 | 10.000 Holz | normal | Fügt sofort 10.000 Holz hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10201011 | 100.000 Holz | rare | Fügt sofort 100.000 Holz hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10201012 | 500.000 Holz | rare | Fügt sofort 500.000 Holz hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10201013 | 1.000.000 Holz | epic | Fügt sofort 1.000.000 Holz hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10201014 | 5.000.000 Holz | epic | Fügt sofort 5.000.000 Holz hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10201015 | 10.000.000 Holz | legendary | Fügt sofort 10.000.000 Holz hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10201016 | 1.000 Stein | normal | Fügt sofort 1.000 Stein hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10201017 | 5.000 Stein | normal | Fügt sofort 5.000 Stein hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10201018 | 10.000 Stein | normal | Fügt sofort 10.000 Stein hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10201019 | 100.000 Stein | rare | Fügt sofort 100.000 Stein hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10201020 | 500.000 Stein | rare | Fügt sofort 500.000 Stein hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10201021 | 1.000.000 Stein | epic | Fügt sofort 1.000.000 Stein hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10201022 | 5.000.000 Stein | epic | Fügt sofort 5.000.000 Stein hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10201023 | 10.000.000 Stein | legendary | Fügt sofort 10.000.000 Stein hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10201024 | 1.000 Gold | normal | Fügt sofort 1.000 Gold hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10201025 | 5.000 Gold | normal | Fügt sofort 5.000 Gold hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10201026 | 10.000 Gold | normal | Fügt sofort 10.000 Gold hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10201027 | 50.000 Gold | rare | Fügt sofort 50.000 Gold hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10201028 | 500.000 Gold | rare | Fügt sofort 500.000 Gold hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10201029 | 1.000.000 Gold | epic | Fügt sofort 1.000.000 Gold hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10201030 | 5.000.000 Gold | epic | Fügt sofort 5.000.000 Gold hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10201031 | 10.000.000 Gold | legendary | Fügt sofort 10.000.000 Gold hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10201032 | 10 Edelsteine | normal | Fügt sofort 10 Edelsteine hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10201033 | 100 Edelsteine | rare | Fügt sofort 100 Edelsteine hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10201034 | 1.000 Edelsteine | epic | Fügt sofort 1.000 Edelsteine hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10201035 | 5.000 Edelsteine | epic | Fügt sofort 5.000 Edelsteine hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10201036 | 10.000 Edelsteine | legendary | Fügt sofort 10.000 Edelsteine hinzu. Ungeöffnete Pakete bleiben im Inventar. |
| 10202001 | Nahrungsproduktion +25 % · 8 Stunden | rare | Erhöht Nahrungsproduktion für 8 Stunden um 25 %. Gilt accountweit; laufende Auftrags- und Marschzeiten bleiben unverändert. Gleiche Stärke verlängert die Laufzeit; stärkere Boni ersetzen schwächere. |
| 10202002 | Nahrungsproduktion +25 % · 1 Tag | epic | Erhöht Nahrungsproduktion für 1 Tag um 25 %. Gilt accountweit; laufende Auftrags- und Marschzeiten bleiben unverändert. Gleiche Stärke verlängert die Laufzeit; stärkere Boni ersetzen schwächere. |
| 10202003 | Holzproduktion +25 % · 8 Stunden | rare | Erhöht Holzproduktion für 8 Stunden um 25 %. Gilt accountweit; laufende Auftrags- und Marschzeiten bleiben unverändert. Gleiche Stärke verlängert die Laufzeit; stärkere Boni ersetzen schwächere. |
| 10202004 | Holzproduktion +25 % · 1 Tag | epic | Erhöht Holzproduktion für 1 Tag um 25 %. Gilt accountweit; laufende Auftrags- und Marschzeiten bleiben unverändert. Gleiche Stärke verlängert die Laufzeit; stärkere Boni ersetzen schwächere. |
| 10202005 | Steinproduktion +25 % · 8 Stunden | rare | Erhöht Steinproduktion für 8 Stunden um 25 %. Gilt accountweit; laufende Auftrags- und Marschzeiten bleiben unverändert. Gleiche Stärke verlängert die Laufzeit; stärkere Boni ersetzen schwächere. |
| 10202006 | Steinproduktion +25 % · 1 Tag | epic | Erhöht Steinproduktion für 1 Tag um 25 %. Gilt accountweit; laufende Auftrags- und Marschzeiten bleiben unverändert. Gleiche Stärke verlängert die Laufzeit; stärkere Boni ersetzen schwächere. |
| 10202007 | Goldproduktion +25 % · 8 Stunden | rare | Erhöht Goldproduktion für 8 Stunden um 25 %. Gilt accountweit; laufende Auftrags- und Marschzeiten bleiben unverändert. Gleiche Stärke verlängert die Laufzeit; stärkere Boni ersetzen schwächere. |
| 10202008 | Goldproduktion +25 % · 1 Tag | epic | Erhöht Goldproduktion für 1 Tag um 25 %. Gilt accountweit; laufende Auftrags- und Marschzeiten bleiben unverändert. Gleiche Stärke verlängert die Laufzeit; stärkere Boni ersetzen schwächere. |
| 10202009 | Sammelgeschwindigkeit +50 % · 1 Tag | epic | Erhöht Sammelgeschwindigkeit für 1 Tag um 50 %. Gilt accountweit; laufende Auftrags- und Marschzeiten bleiben unverändert. Gleiche Stärke verlängert die Laufzeit; stärkere Boni ersetzen schwächere. |
| 10202010 | Baugeschwindigkeit +25 % · 1 Tag | epic | Erhöht Baugeschwindigkeit für 1 Tag um 25 %. Gilt accountweit; laufende Auftrags- und Marschzeiten bleiben unverändert. Gleiche Stärke verlängert die Laufzeit; stärkere Boni ersetzen schwächere. |
| 10202011 | Forschungsgeschwindigkeit +25 % · 1 Tag | epic | Erhöht Forschungsgeschwindigkeit für 1 Tag um 25 %. Gilt accountweit; laufende Auftrags- und Marschzeiten bleiben unverändert. Gleiche Stärke verlängert die Laufzeit; stärkere Boni ersetzen schwächere. |
| 10202012 | Ausbildungsgeschwindigkeit +25 % · 1 Tag | epic | Erhöht Ausbildungsgeschwindigkeit für 1 Tag um 25 %. Gilt accountweit; laufende Auftrags- und Marschzeiten bleiben unverändert. Gleiche Stärke verlängert die Laufzeit; stärkere Boni ersetzen schwächere. |
| 10202013 | Truppenangriff +10 % · 1 Stunde | rare | Erhöht Truppenangriff für 1 Stunde um 10 %. Gilt accountweit; laufende Auftrags- und Marschzeiten bleiben unverändert. Gleiche Stärke verlängert die Laufzeit; stärkere Boni ersetzen schwächere. |
| 10202014 | Truppenangriff +20 % · 1 Stunde | epic | Erhöht Truppenangriff für 1 Stunde um 20 %. Gilt accountweit; laufende Auftrags- und Marschzeiten bleiben unverändert. Gleiche Stärke verlängert die Laufzeit; stärkere Boni ersetzen schwächere. |
| 10202015 | Truppenverteidigung +10 % · 1 Stunde | rare | Erhöht Truppenverteidigung für 1 Stunde um 10 %. Gilt accountweit; laufende Auftrags- und Marschzeiten bleiben unverändert. Gleiche Stärke verlängert die Laufzeit; stärkere Boni ersetzen schwächere. |
| 10202016 | Truppenverteidigung +20 % · 1 Stunde | epic | Erhöht Truppenverteidigung für 1 Stunde um 20 %. Gilt accountweit; laufende Auftrags- und Marschzeiten bleiben unverändert. Gleiche Stärke verlängert die Laufzeit; stärkere Boni ersetzen schwächere. |
| 10202017 | Truppen-Lebenspunkte +10 % · 1 Stunde | rare | Erhöht Truppen-Lebenspunkte für 1 Stunde um 10 %. Gilt accountweit; laufende Auftrags- und Marschzeiten bleiben unverändert. Gleiche Stärke verlängert die Laufzeit; stärkere Boni ersetzen schwächere. |
| 10202018 | Truppen-Lebenspunkte +20 % · 1 Stunde | epic | Erhöht Truppen-Lebenspunkte für 1 Stunde um 20 %. Gilt accountweit; laufende Auftrags- und Marschzeiten bleiben unverändert. Gleiche Stärke verlängert die Laufzeit; stärkere Boni ersetzen schwächere. |
| 10202019 | Marschkapazität +10 % · 1 Stunde | rare | Erhöht Marschkapazität für 1 Stunde um 10 %. Gilt accountweit; laufende Auftrags- und Marschzeiten bleiben unverändert. Gleiche Stärke verlängert die Laufzeit; stärkere Boni ersetzen schwächere. |
| 10202020 | Marschkapazität +20 % · 1 Stunde | epic | Erhöht Marschkapazität für 1 Stunde um 20 %. Gilt accountweit; laufende Auftrags- und Marschzeiten bleiben unverändert. Gleiche Stärke verlängert die Laufzeit; stärkere Boni ersetzen schwächere. |
| 10202021 | Angriff gegen Monster +10 % · 1 Stunde | rare | Erhöht Angriff gegen Monster für 1 Stunde um 10 %. Gilt accountweit; laufende Auftrags- und Marschzeiten bleiben unverändert. Gleiche Stärke verlängert die Laufzeit; stärkere Boni ersetzen schwächere. |
| 10202022 | Angriff gegen Monster +20 % · 1 Stunde | epic | Erhöht Angriff gegen Monster für 1 Stunde um 20 %. Gilt accountweit; laufende Auftrags- und Marschzeiten bleiben unverändert. Gleiche Stärke verlängert die Laufzeit; stärkere Boni ersetzen schwächere. |
| 10202023 | Marschgeschwindigkeit +25 % · 1 Stunde | rare | Erhöht Marschgeschwindigkeit für 1 Stunde um 25 %. Gilt accountweit; laufende Auftrags- und Marschzeiten bleiben unverändert. Gleiche Stärke verlängert die Laufzeit; stärkere Boni ersetzen schwächere. |
| 10202024 | Marschgeschwindigkeit +50 % · 1 Stunde | epic | Erhöht Marschgeschwindigkeit für 1 Stunde um 50 %. Gilt accountweit; laufende Auftrags- und Marschzeiten bleiben unverändert. Gleiche Stärke verlängert die Laufzeit; stärkere Boni ersetzen schwächere. |
| 10202025 | Spähschutz · 1 Tag | epic | Verbirgt Ressourcen, Truppen und Mauerwerte deiner aktiven Stadt 1 Tag vor Spähern. |
| 10202026 | Königsschild · 1 Tag | epic | Schützt deine aktive Stadt 1 Tag vor feindlichen Angriffen. Nicht während eigener feindlicher Märsche nutzbar. |
| 10203001 | Universell · 1 Minuten | normal | Verkürzt einen Bau-, Forschungs-, Ausbildungs- oder Heilungsauftrag um 1 Minuten. Überschüssige Zeit verfällt. |
| 10203002 | Universell · 10 Minuten | normal | Verkürzt einen Bau-, Forschungs-, Ausbildungs- oder Heilungsauftrag um 10 Minuten. Überschüssige Zeit verfällt. |
| 10203003 | Universell · 30 Minuten | normal | Verkürzt einen Bau-, Forschungs-, Ausbildungs- oder Heilungsauftrag um 30 Minuten. Überschüssige Zeit verfällt. |
| 10203004 | Universell · 3 Tage | epic | Verkürzt einen Bau-, Forschungs-, Ausbildungs- oder Heilungsauftrag um 3 Tage. Überschüssige Zeit verfällt. |
| 10203005 | Universell · 7 Tage | legendary | Verkürzt einen Bau-, Forschungs-, Ausbildungs- oder Heilungsauftrag um 7 Tage. Überschüssige Zeit verfällt. |
| 10203006 | Universell · 30 Tage | legendary | Verkürzt einen Bau-, Forschungs-, Ausbildungs- oder Heilungsauftrag um 30 Tage. Überschüssige Zeit verfällt. |
| 10203007 | Bauen · 1 Minuten | normal | Verkürzt einen Bauauftrag um 1 Minuten. Überschüssige Zeit verfällt. |
| 10203008 | Bauen · 5 Minuten | normal | Verkürzt einen Bauauftrag um 5 Minuten. Überschüssige Zeit verfällt. |
| 10203009 | Bauen · 10 Minuten | normal | Verkürzt einen Bauauftrag um 10 Minuten. Überschüssige Zeit verfällt. |
| 10203010 | Bauen · 30 Minuten | normal | Verkürzt einen Bauauftrag um 30 Minuten. Überschüssige Zeit verfällt. |
| 10203011 | Bauen · 1 Tag | epic | Verkürzt einen Bauauftrag um 1 Tag. Überschüssige Zeit verfällt. |
| 10203012 | Bauen · 3 Tage | epic | Verkürzt einen Bauauftrag um 3 Tage. Überschüssige Zeit verfällt. |
| 10203013 | Bauen · 7 Tage | legendary | Verkürzt einen Bauauftrag um 7 Tage. Überschüssige Zeit verfällt. |
| 10203014 | Forschung · 1 Minuten | normal | Verkürzt einen Forschungsauftrag um 1 Minuten. Überschüssige Zeit verfällt. |
| 10203015 | Forschung · 5 Minuten | normal | Verkürzt einen Forschungsauftrag um 5 Minuten. Überschüssige Zeit verfällt. |
| 10203016 | Forschung · 10 Minuten | normal | Verkürzt einen Forschungsauftrag um 10 Minuten. Überschüssige Zeit verfällt. |
| 10203017 | Forschung · 30 Minuten | normal | Verkürzt einen Forschungsauftrag um 30 Minuten. Überschüssige Zeit verfällt. |
| 10203018 | Forschung · 1 Tag | epic | Verkürzt einen Forschungsauftrag um 1 Tag. Überschüssige Zeit verfällt. |
| 10203019 | Forschung · 3 Tage | epic | Verkürzt einen Forschungsauftrag um 3 Tage. Überschüssige Zeit verfällt. |
| 10203020 | Forschung · 7 Tage | legendary | Verkürzt einen Forschungsauftrag um 7 Tage. Überschüssige Zeit verfällt. |
| 10203021 | Ausbildung · 1 Minuten | normal | Verkürzt einen Ausbildungsauftrag um 1 Minuten. Überschüssige Zeit verfällt. |
| 10203022 | Ausbildung · 5 Minuten | normal | Verkürzt einen Ausbildungsauftrag um 5 Minuten. Überschüssige Zeit verfällt. |
| 10203023 | Ausbildung · 10 Minuten | normal | Verkürzt einen Ausbildungsauftrag um 10 Minuten. Überschüssige Zeit verfällt. |
| 10203024 | Ausbildung · 30 Minuten | normal | Verkürzt einen Ausbildungsauftrag um 30 Minuten. Überschüssige Zeit verfällt. |
| 10203025 | Ausbildung · 8 Stunden | rare | Verkürzt einen Ausbildungsauftrag um 8 Stunden. Überschüssige Zeit verfällt. |
| 10203026 | Ausbildung · 1 Tag | epic | Verkürzt einen Ausbildungsauftrag um 1 Tag. Überschüssige Zeit verfällt. |
| 10203027 | Ausbildung · 3 Tage | epic | Verkürzt einen Ausbildungsauftrag um 3 Tage. Überschüssige Zeit verfällt. |
| 10203028 | Ausbildung · 7 Tage | legendary | Verkürzt einen Ausbildungsauftrag um 7 Tage. Überschüssige Zeit verfällt. |
| 10203029 | Heilung · 1 Minuten | normal | Verkürzt die laufende Heilung um 1 Minuten. Überschüssige Zeit verfällt. |
| 10203030 | Heilung · 5 Minuten | normal | Verkürzt die laufende Heilung um 5 Minuten. Überschüssige Zeit verfällt. |
| 10203031 | Heilung · 10 Minuten | normal | Verkürzt die laufende Heilung um 10 Minuten. Überschüssige Zeit verfällt. |
| 10203032 | Heilung · 30 Minuten | normal | Verkürzt die laufende Heilung um 30 Minuten. Überschüssige Zeit verfällt. |
| 10203033 | Heilung · 3 Stunden | rare | Verkürzt die laufende Heilung um 3 Stunden. Überschüssige Zeit verfällt. |
| 10203034 | Heilung · 8 Stunden | rare | Verkürzt die laufende Heilung um 8 Stunden. Überschüssige Zeit verfällt. |
| 10203035 | Heilung · 1 Tag | epic | Verkürzt die laufende Heilung um 1 Tag. Überschüssige Zeit verfällt. |
| 10203036 | Heilung · 3 Tage | epic | Verkürzt die laufende Heilung um 3 Tage. Überschüssige Zeit verfällt. |
| 10203037 | Heilung · 7 Tage | legendary | Verkürzt die laufende Heilung um 7 Tage. Überschüssige Zeit verfällt. |
| 10204001 | Energieflasche · 100 AP | rare | Stellt bis zu 100 Aktionspunkte wieder her, höchstens bis zu deinem Maximum. Nur bei fehlenden Aktionspunkten verwendbar. |
| 10205001 | Rohstoffkiste Stufe 1 | normal | Enthält 1.000 bis 50.000 Einheiten eines zufälligen Rohstoffs: Nahrung, Holz, Stein oder Gold. |
| 10205002 | Rohstoffkiste Stufe 2 | rare | Enthält 50.000 bis 100.000 Einheiten eines zufälligen Rohstoffs: Nahrung, Holz, Stein oder Gold. |
| 10205003 | Rohstoffkiste Stufe 3 | epic | Enthält 100.000 bis 500.000 Einheiten eines zufälligen Rohstoffs: Nahrung, Holz, Stein oder Gold. |
| 10205004 | Rohstoffkiste Stufe 4 | legendary | Enthält 500.000 bis 1.000.000 Einheiten eines zufälligen Rohstoffs: Nahrung, Holz, Stein oder Gold. |
| 10205005 | Rohstoffkiste Stufe 5 | legendary | Enthält 1.000.000 bis 5.000.000 Einheiten eines zufälligen Rohstoffs: Nahrung, Holz, Stein oder Gold. |
| 10206001 | Prestigemedaille · 10 Punkte | normal | Gewährt 10 Prestige-Punkte für deine Prestigestufe. |
| 10206002 | Prestigemedaille · 1.000 Punkte | epic | Gewährt 1.000 Prestige-Punkte für deine Prestigestufe. |
| 10206003 | Prestigemedaille · 5.000 Punkte | epic | Gewährt 5.000 Prestige-Punkte für deine Prestigestufe. |
| 10206004 | Prestigemedaille · 10.000 Punkte | legendary | Gewährt 10.000 Prestige-Punkte für deine Prestigestufe. |
| 10207001 | Reliktfragmente · Gewöhnlich | normal | Gewährt 10 Fragmente eines zufälligen Relikts der Seltenheit „Gewöhnlich“. Fragmente erhöhen automatisch dessen Stufe. |
| 10207002 | Reliktfragmente · Selten | rare | Gewährt 10 Fragmente eines zufälligen Relikts der Seltenheit „Selten“. Fragmente erhöhen automatisch dessen Stufe. |
| 10207003 | Reliktfragmente · Episch | epic | Gewährt 10 Fragmente eines zufälligen Relikts der Seltenheit „Episch“. Fragmente erhöhen automatisch dessen Stufe. |
| 10207004 | Reliktfragmente · Legendär | legendary | Gewährt 5 Fragmente eines zufälligen Relikts der Seltenheit „Legendär“. Fragmente erhöhen automatisch dessen Stufe. |
| 10207005 | Reliktfragmente · Mythisch | mythic | Gewährt 3 Fragmente eines zufälligen Relikts der Seltenheit „Mythisch“. Fragmente erhöhen automatisch dessen Stufe. |
| 10207021 | Smaragd-Drachenei | rare | Öffne das Drachenei. Gewährt 20 Fragmente eines zufälligen Relikts der Seltenheit „Selten“. Fragmente erhöhen automatisch dessen Stufe. |
| 10207022 | Glut-Drachenei | epic | Öffne das Drachenei. Gewährt 20 Fragmente eines zufälligen Relikts der Seltenheit „Episch“. Fragmente erhöhen automatisch dessen Stufe. |
| 10207023 | Goldenes Drachenei | legendary | Öffne das Drachenei. Gewährt 10 Fragmente eines zufälligen Relikts der Seltenheit „Legendär“. Fragmente erhöhen automatisch dessen Stufe. |
| 10208001 | Advanced-Teleporter | legendary | Lässt dich auf der Weltkarte einen freien, trockenen 4×4-Platz in der gesamten aktuellen Welt wählen. Nur nutzbar, wenn keine Märsche, Rallies oder Verstärkungen an deine Stadt gebunden sind. |
| 10208002 | Zufallsteleporter | rare | Versetzt deine Stadt auf einen freien, trockenen 4×4-Platz in deiner aktuellen Welt. Nur nutzbar, wenn keine Märsche, Rallies oder Verstärkungen an deine Stadt gebunden sind. |
| 10208003 | Allianz-Teleporter | epic | Lässt dich einen freien, trockenen 4×4-Platz höchstens 12 Felder von einer verbündeten Stadt entfernt wählen. Eine Allianzmitgliedschaft ist erforderlich. |
| 10300001 | Energieflasche · 10 AP | normal | Stellt bis zu 10 Aktionspunkte wieder her, höchstens bis zu deinem Maximum. |
| 10300002 | Legendäres Reliktfragment | legendary | Gewährt ein Fragment eines zufälligen legendären Relikts. Fragmente erhöhen automatisch dessen Stufe. |
| 10300003 | Portalsphäre · Fragment | epic | Gewährt genau ein Fragment der Portalsphäre. Fragmente erhöhen automatisch die Reliktstufe. |
| 10300004 | Großes Kriegshorn · 1 Stunde | legendary | Erhöht die Marschkapazität für eine Stunde um 50 %. Gilt für neu entsandte Armeen. Gleiche Stärke verlängert die Laufzeit; stärkere Boni ersetzen schwächere. |
