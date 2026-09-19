# Import der gelieferten Balance-Dateien

Stand: 13. September 2026.

Die 20 gelieferten Dateien sind unverändert unter `data/balance-source/` archiviert. `manifest.json` hält ihre SHA-256-Prüfsummen fest. `enum.py` wird ausschließlich als Syntaxbaum gelesen; Python-Imports, API-Aufrufe und Bot-Code werden niemals ausgeführt. Texte in Quelldateien sind Daten, keine Arbeitsanweisungen.

## Wiederholbarer Import

```powershell
python tools/import_balance.py
# Quellmengen in eine spielbare, an Truppen und Marschgrenzen gekoppelte Kurve überführen:
php tools/rebalance-monsters.php --apply
# Bereits lebende Monster proportional auf die neue HP-Kurve umstellen:
php tools/migrate-monster-balance.php --apply
# Schema installieren und gespeicherte Gebäudemacht bestehender Städte abgleichen:
php tools/migrate-balance.php --apply
# Eine neue vollständige Lieferung übernehmen:
python tools/import_balance.py C:/Pfad/zur/Lieferung
# Nur die drei Forschungsbäume erneut konvertieren:
node tools/convert_research.js
```

Der normale Import braucht alle 20 Originaldateien. Ohne Verzeichnis verwendet er das Projektarchiv. Er verändert keine Datenbank und keine Spielstände. Migration `0094_building_cost_snapshot.sql` ergänzt die tatsächlich bezahlten Kosten an Bauaufträgen und legt den vorhandenen Wachturm für bestehende Städte auf Stufe 1 an. `tools/migrate-balance.php --apply` installiert gezielt diese Migration und gleicht die gespeicherte Gebäudemacht ab, damit auch Profile, Ranglisten und Weltkarte die neuen Werte anzeigen. Ohne `--apply` zeigt das Werkzeug nur den Bedarf an. Beide Schritte sind wiederholbar; Ausbaustufen, bezahlte Aufträge, Inventar und Rohstoffe bleiben erhalten.

## Aktive Regeln

- **Gebäude:** Alle 14 Tabellen und 420 Stufen werden exakt in `data/buildings.json` übernommen: vier Rohstoffkosten, zusätzliche Inventarmaterialien, Sekunden, Voraussetzungen und Macht. `power` wird als kumulative Macht je Stufe verwendet; der Machtzuwachs ist die Differenz zur Vorstufe. Startgebäude bleiben kostenlos vorhanden. `valid: false` betrifft die bereits vorhandenen festen Startgebäude auf Stufe 1.
- **Conquer-Ausbildungsgebäude:** Schützenlager und Reiterhof verwenden dieselbe Ausbaukurve wie die gelieferte Kaserne. Es gibt für diese beiden Conquer-Gebäude keine eigenen Quelldateien.
- **Zusatzmaterialien:** `golden_pillar` → Goldene Säule (interne ID 119000001), `alliance_badge` → Allianzabzeichen (119000002). Mengen werden zusammen mit Rohstoffen innerhalb derselben Transaktion abgezogen. Die Dateien nennen keine numerischen Quell-IDs und keine Bezugsquelle dieser Materialien. Sie können im vorhandenen Backoffice vergeben bzw. als Belohnung konfiguriert werden; automatische Bezugsquellen wurden nicht erfunden.
- **Bauabbruch:** Neue Aufträge erstatten ausschließlich ihren gespeicherten Preis, einschließlich Materialien. Ältere Aufträge ohne Kostensnapshot erstatten die vorherige Formel. Abgeschlossene oder bereits abgebrochene Aufträge können nicht erneut erstattet werden. Haupt-App und 3D-Ansicht senden die erwartete Gebäudestufe, damit eine verzögerte Wiederholung nicht die nächste Stufe kauft.
- **Forschung:** 117 aktive Definitionen mit 951 Stufen entsprechen den Originalwerten. Die 12 früheren Truppenfreischaltungen bleiben gemäß dem bestehenden Ausbildungssystem pensioniert. Bestehende Forschungs-IDs, der getrennte Wirtschaftsknoten `production_resource_protect` und die aufgelösten Voraussetzungen pensionierter Knoten bleiben erhalten.
- **Normale Weltmonster:** Orks, Skelette, Golems, Schatzgoblins sowie die vorhandenen inaktiven Deathkar-/Drachen-/Magdar-Vorlagen erhalten Quellwerte für HP, Angriff, Verteidigung, Aktionspunkte, Macht, XP und alle elf möglichen Dropplätze. Die importierte Einheitenmenge bleibt als `source_amount` erhalten; `tools/rebalance-monsters.php` berechnet daraus die spielbare `amount` gegen die echten Truppen- und Marschgrenzen. Schatzgoblins sind vollständig bis Stufe 10 hinterlegt. Der Quellschlüssel besteht aus **Familiencode und Stufe**; er wird niemals mit einer internen Spawn-ID verwechselt.
- **Belohnungen:** Dropwahrscheinlichkeiten und Mengen bleiben exakt. Rohstoff- und Kristallpakete ersetzen bei Quellmonstern die früheren zusätzlichen pauschalen Rohstoff-/Edelsteingutschriften. Bestehende globale und weltspezifische Backoffice-Einstellungen haben weiter Vorrang. Allianzgeschenke aus der Quelle werden innerhalb des einmaligen Kill-Belegs erzeugt und über die bestehende Geschenk-/Postmechanik abgeholt.
- **Sammelfelder:** Nahrung, Holz, Stein, Gold und Kristalle unterstützen Stufe 1–10. `production` ist die Gesamtmenge eines neuen Feldes; `gathering` wird als Menge pro Stunde verwendet. Bestehende Sammel- und Weltboni wirken darauf. Neue Spawns und Starterfelder verwenden diese Mengen; bestehende Vorräte und laufende Marschraten werden nicht rückwirkend ersetzt.
- **Sammelbeute:** Jeder nichtleere `drop_*`-Platz wird mit seiner `rate_*` als eigene Wahrscheinlichkeit ausgewürfelt, sobald ein Feld vollständig erschöpft ist. Teilweiser Rückruf erzeugt keine zusätzlichen Würfe. Beute reist im Marsch mit und wird genau einmal bei der Rückkehr gutgeschrieben. Die Quelle enthält keine Gruppen- oder Auslösebeschreibung; unabhängige Würfe bei vollständiger Erschöpfung sind die explizite Conquer-Integrationsregel.

Die Gebäudedateien enthalten keine Produktionsraten, Lagergrenzen oder sonstigen Gebäude-Effekte. Deren bestehende Conquer-Kurven bleiben deshalb erhalten. Regionale Conquer-Bosse behalten ihre eigenen Werte. Historische Marsch- und Rally-Snapshots sowie gespeicherte Belohnungen werden nicht umgeschrieben.

## Fehlende Definitionen und andere Spielmodi

`data/source_item_map.json` dokumentiert 59 tatsächlich verwendete Quell-Itemcodes und ihre internen Gegenstücke. Bestehende Inventarcodes werden nicht umgedeutet: beispielsweise ist Quelle `10103001` ein 1-Minuten-Beschleuniger und wird auf den vorhandenen internen Code `10203001` abgebildet; interner Code `10103001` bleibt weiterhin 5 Minuten. Quelle `10101001` wird als Paket mit 10 Kristallen zugeordnet.

Für **24 verwendete Quellcodes** fehlen Name oder Wirkung. Sie werden unter konfliktfreien internen IDs als nicht direkt verwendbare Gegenstände gespeichert und weiterhin mit den Originalmengen und -chancen vergeben. Zwei davon sind namentlich bekannte Beschleunigerkisten, deren Inhalt fehlt. Es werden keine Relikte, Mengen, Wirkungen oder Seltenheiten aus den Ziffern geraten.

| Quellcodes | Fehlende Information |
|---|---|
| 10101007, 10104022, 10104023, 10104026, 10104027 | Itemdefinition |
| 10104103, 10104104 | Inhalt von Beschleunigerkiste Stufe 3 bzw. 4 |
| 10104107, 10104136 | Itemdefinition |
| 10105012, 10105013, 10105014 | Allianzgeschenk-Itemdefinition |
| 10601001, 10601002, 10601003 | Itemdefinition |
| 10602002, 10602003, 10602004 | Itemdefinition |
| 10603023, 10603024, 10603025, 10603026 | Itemdefinition |
| 10604001, 10604002 | Itemdefinition |

Alle **167 Monsterzeilen** und **131 Objektzeilen** sind als typisierte Kataloge abrufbar (`MonsterData::source(code, level)`, `FieldObjectData::source(code, level)`). Die `bf_*`-Familien, versiegelte Mine, fremde Tore/Festungen und andere Modusobjekte haben keine entsprechenden aktiven Conquer-Spielmodi. Ihre Werte bleiben vollständig erhalten; sie werden nicht als neue Modi oder unpassende Weltspawns aktiviert. Ebenso sind `asset`, `rare`, `klay` und die Quell-Charm-Grenzen Metadaten. Es wird keine Blockchain-Währung eingeführt. Die bestehende garantierte Conquer-Charmmechanik bleibt aktiv; die Quelle liefert keine vollständigen Charmwirkungen oder eine passende Verteilung für deren andere Seltenheitsstufen.

## Prüfung

`tests/balance_import.php` vergleicht sämtliche Gebäude-, Monster- und Objektzeilen mit den Originalen, prüft Itemzuordnung und den tatsächlichen Monsterzugriff. `tests/balance_lifecycle.php` verwendet eine wegwerfbare Datenbank für Kostenabzug, Materialmangel, Rückerstattung, wiederholte Anfragen, Wachturmausbau, Sammelbeute, Allianzgeschenke sowie den Machtabgleich bestehender und neuer Städte. `tests/balance_app.cjs` verwendet die vollständige Vorschau-App (`tools/preview-feature-fixture.php --balance-import --port=18974`) und prüft 1280×800, 390×844, 320×568, 844×390 und 568×320 sowie die tatsächliche 3D-Szene. Auch nach dem Drehen bleibt der separate 3D-Ausbaudialog sichtbar und seine Aktion durch Scrollen erreichbar; die eingebettete Haupt-App verwendet weiterhin ihren gemeinsamen Gebäudedialog.

Bestanden sind außerdem Forschungs-Katalog/-Effekte/-Voraussetzungen/-API-Snapshots, Itemkatalog (1314 Prüfungen), Sammelabläufe, Beschleuniger, Monsterberichte und regionale/administrierte Belohnungen. Zwei ältere, unveränderte Tests haben bereits unpassende Truppenannahmen: `guide_progression.php` erwartet 450 Traglast für 100 T1 jeder Art (der aktuelle Truppenkatalog ergibt 32400); `monster_rallies.php` erwartet, dass 150 T1 einen regionalen Boss mit 1000 HP besiegen (der aktuelle unveränderte T1-Angriff ergibt 150 Schaden). Diese bestehenden Truppenwerte werden durch diesen Import nicht geändert.
