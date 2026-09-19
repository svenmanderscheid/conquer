**Conquer – Vergleich mit dem League-of-Kingdoms-Guide**

**Umsetzungsstand vom 12. September 2026:** Die folgende Tabelle dokumentiert den ursprünglichen Vergleich. Inzwischen sind Monster-Rallies und die acht Kartenkristalle angeschlossen; Burg-/Kasernenkapazität, Lager-Grundschutz und zeitbasiertes Sammeln sind umgesetzt. Neue Regeln, Tests und weiterhin offene Punkte stehen in [GUIDE_SYSTEMS.md](GUIDE_SYSTEMS.md).

Stand: 11. September 2026. Grundlage ist der lokale Arbeitsstand einschließlich noch nicht eingecheckter Dateien.

Referenz: [League of Kingdoms INSANE GUIDE, OQTACORE, 5. März 2023](https://gtmdevelopment.medium.com/league-of-kingdoms-insane-guide-ceeead399d5d). Der vollständige Artikel wurde im Browser gelesen. Er beschreibt Grundlagen und Spieltipps, keine vollständige technische Spezifikation und keinen aktuellen Stand von League of Kingdoms.

**Ergebnis:** Conquer besitzt bereits einen großen Teil der beschriebenen Grundsysteme. Wesentliche Lücken bestehen bei strategischen Bauplätzen, Wachturm-Erkundung, dem Allianz-Münzenkreislauf und Monster-Rallies. Einige vorhandene Systeme unterscheiden sich zudem in ihren tatsächlichen Berechnungen deutlich von der Referenz. Zwei konkrete Verbindungsfehler betreffen Monster-Rallies und eingesammelte Buff-Kristalle.

Die Bewertung beruht auf Quellcode, Routen, Datenmodell und aktuellen Projektdokumenten. Kleine PHP-Auswertungen ohne Datenbankzugriff bestätigen Gebäudekatalog, Truppenwerte, Basislimits und den Kristallfilter. „Vorhanden“ bedeutet hier im Code umgesetzt; es ist keine erneute vollständige Spiel-, Browser- oder Produktionsabnahme. Historische Testmeldungen wurden nicht als frisch ausgeführte Prüfungen übernommen. Spielcode und Spielerdaten wurden bei diesem Vergleich nicht geändert.

**Vergleich der Systeme**

| Bereich der Referenz | Stand in Conquer | Bewertung |
|---|---|---|
| Einstieg und Konten | Registrierung mit Passwort und gespeicherter Fortschritt; Google-/Discord-OAuth im Code. Apple, Facebook und Gastzugang fehlen. Ob OAuth auf einer Installation eingerichtet ist, wurde nicht geprüft. | Kern vorhanden, Anbieter abweichend |
| Browser und Mobilgeräte | Browseroberfläche und PWA vorhanden. Keine eigenen iOS-/Android-Store-Apps im Projekt. | Eigener Plattformansatz |
| Einstiegsaufgaben | Tägliche Aufgaben funktionieren als eigenes System. Ein älterer Tutorial-Service mit zwölf Schritten existiert; die Haupt-App bindet ihn nicht als durchgehende Einführung ein. `compactGuide()` ist definiert, aber ohne Aufruf. Eine Einführung bis zum Allianzbeitritt fehlt. | Teilweise / angelegt |
| Stadt und Produktion | 13 funktionale Gebäudetypen, Ausbau bis Stufe 30, Bauzeiten, Kosten und Rohstoffproduktion. Der sichtbare Wachturm ist zusätzlich vorhanden, aber kein funktionaler 14. Gebäudetyp. | Grundsystem vorhanden |
| Frei belegbare Außenbauplätze | Das Datenmodell speichert genau einen Datensatz je Stadt und Gebäudetyp. Mehrere unabhängig ausbaubare Kasernen, Holzfällerlager oder Farmen und die Wahl ihrer Anzahl sind nicht umgesetzt. | Fehlt |
| Burg | Begrenzt andere Gebäudestufen und besitzt Ausbauvoraussetzungen. Marschplätze, Marschgröße und Ausbildungsmenge wachsen jedoch nicht unmittelbar mit der Burgstufe. | Teilweise |
| Truppen und Kaserne | Infanterie, Fernkämpfer und Kavallerie mit jeweils T1–T5, Ausbildung, Forschungsvoraussetzungen und Beförderungen vorhanden. Nur ein Ausbildungsplatz; kein Ausbau mehrerer Kasernen. | Grundsystem vorhanden, Ausbauwirkung begrenzt |
| Akademie | Drei Forschungskataloge mit insgesamt 129 Einträgen, Voraussetzungen und angebundenen Wirtschafts-/Armeeboni. | Vorhanden |
| Schatzkammer und Ausrüstung | 82 Relikte, Fragmente, Stufen, sechs freischaltbare Ausrüstungsplätze und fünf Presets. Slots öffnen bei Gebäudestufe 1/1/5/10/20/25. | Vorhanden |
| Kostenlose Truhen | Zehn blaue Öffnungen pro Tag mit zehn Minuten Abstand und eine goldene alle 24 Stunden. Die Anzahl bleibt unabhängig von weiteren Schatzkammerstufen gleich. | Vorhanden, Stufenskalierung fehlt |
| Handelsposten | Karawane mit achtstündiger Rotation, mehr Angebote durch Gebäudestufe, 52 VIP-Angebote und Käufe mit Spielwährung. Dazu Edelstein-Itemshop und Ressourcentransporte. | Vorhanden |
| Mauer | Haltbarkeit, Verteidigungswirkung, Schaden, Reparatur und Regeneration umgesetzt. | Vorhanden |
| Hospital | Verwundete, Heilzeiten und Beschleunigung/Heilaktionen vorhanden. Die Basiskapazität beträgt derzeit fest 1.000 plus Boni; eine Gebäudestufenskalierung ist nicht angeschlossen. Diese zusätzliche Ausbaufrage wird im Guide nicht konkret spezifiziert. | Heilfunktion vorhanden |
| Lagerhaus | Erhöht die Produktions-Lagergrenze für Nahrung, Holz und Stein; die Goldgrenze hängt an der Schatzkammer. Plünderschutz entsteht aus prozentualen Boni, nicht aus einer Lagerhaus-Schutzkapazität. | Abweichende Funktion |
| Wachturm und Erkundung | Modell und Weltkarten-Verknüpfung vorhanden. Keine ausbaubare Wachturmstufe, kein davon abhängiger Entdeckungsradius und kein gespeicherter Erkundungsfortschritt. Das Ausspähen fremder Städte existiert separat. | Optisch angelegt, Mechanik fehlt |
| Rohstofffelder und Kristallminen | Nahrung, Holz, Stein, Gold und Edelsteine; begrenzte Vorräte, Stufen, Ablauf und konfigurierbares Nachspawnen. | Vorhanden |
| Sammeln und Truppenrollen | Echte Truppen werden reserviert und kehren mit Beute zurück. Der Abbau geschieht sofort bei Ankunft. Basistraglast ist für alle Einheiten gleich; beim Sammelweg wird ihre unterschiedliche Grundgeschwindigkeit nicht benutzt. | Deutlich vereinfacht |
| Regionale Landentwicklung | Kein regionaler Entwicklungsstand, der durch Spielaktivität wächst und lokale Rohstoff-/Monsterqualität verbessert. Globale Spawnkonfiguration, Gelände und Weltkapitel sind andere Systeme. | Fehlt |
| Monsterjagd | Arten, Stufen, Kampfwerte, Soloangriffe, Verluste, Ressourcenbeute, Lord-XP und Berichte vorhanden. Der Kampf verwendet allgemeine Werte; eigenständige Monsterfähigkeiten sind damit nicht nachgewiesen. | Grundsystem vorhanden |
| Lord und Meisterschaft | Jagd-XP, Level 1–60 und vier Talentbereiche mit jeweils neun Talenten. Keine gesonderte Belohnungstabelle für zusätzliche Ressourcen/Items bei jedem Lord-Levelaufstieg gefunden. | Weitgehend vorhanden, eigene Talentstruktur |
| Buff-Kristalle nach Monsterkills | Spawn und Einsammeln sind implementiert, bei Orks, Skeletten und Golems. Sechs von acht Kategoriebezeichnungen werden aber vom aktuellen Bonusfilter ausgeschlossen. | Teilweise, konkreter Fehler |
| Allianz und Zusammenarbeit | Gründen, Beitreten, Mitglieder, Rollen, Chat, Post, Spenden, Diplomatie und Truppenverstärkung vorhanden. | Vorhanden |
| Allianz-Hilfe und Forschung | Bau-/Forschungshilfe sowie gemeinsame Wirtschafts-/Kampfforschung vorhanden. | Vorhanden |
| Allianz-Münzen und Allianzshop | Keine persönliche Allianz-Währung, keine Münzvergütung für Hilfe/Spenden und kein damit bezahlter Allianzshop gefunden. Bündniskasse und VIP-Shop ersetzen diesen Kreislauf nicht. | Fehlt |
| Gemeinsame Monster-Rally | Ein Dialog ist vorbereitet, aber der dazu aufgerufene Server-Endpunkt fehlt. Bestehende klassische Rallies prüfen Stadtziele. Kooperative Boss-Feldzüge sind als separates System vorhanden. | Oberfläche angelegt, Ablauf unvollständig |
| VIP | 20 Stufen und wirksame Boni. Zweiter Bauplatz bereits ab VIP 4. Kein zusätzlicher Marschplatz durch VIP 6; auch ein eigener VIP-Sammelbonus fehlt in der Bonustabelle. | Vorhanden, andere Freischaltungen |
| Wallet, Ressourcen-NFTs und Blockchain-Handel | Nicht implementiert und in den bisherigen Projektregeln ausdrücklich ausgeschlossen. | Bewusste Abgrenzung, keine technische Restarbeit |

**Die wichtigsten Befunde mit Codebelegen**

**1. Monster-Rally: Angebot in der Oberfläche ohne Serverfunktion.**

Der Marschdialog erkennt Monster mit Rally-Typ, zeigt eine Sammelzeit und sendet an `/api/rally/start-monster`. Der Router registriert nur den bestehenden Stadt-Rally-Ablauf; `RallyService::start()` verlangt ein angreifbares Stadtziel. Für eine vollständige Monster-Rally fehlen damit unter anderem Zielvalidierung, gemeinsamer Monsterkampf und dessen Abschluss/Belohnung im Rally-Service. Die vorhandenen Feldzüge erfüllen einen anderen kooperativen Ablauf.

Belege: [Marschdialog](C:/xampp/htdocs/conquer/assets/js/march-panel.js:132), [registrierte Routen](C:/xampp/htdocs/conquer/index.php:298), [Stadtzielprüfung](C:/xampp/htdocs/conquer/src/Game/Rally/RallyService.php:19).

**2. Buff-Kristalle: sechs Kategorien erreichen die Bonusberechnung nicht.**

Der Spawner verwendet `construction`, `research`, `troops_hp`, `troops_attack`, `troops_defense`, `carry`, `march_speed` und `gathering`. Beim Einsammeln bleiben diese Schlüssel unverändert. `BuffEngine` liest jedoch nur die Kategorien aus `KingdomInventory::DIRECT_BOOSTS`; davon passen lediglich `troops_hp` und `march_speed`. Die übrigen sechs werden bereits in der SQL-Abfrage herausgefiltert. Ein erzeugter oder eingesammelter Kristall ist deshalb noch kein Nachweis eines wirksamen Bonus.

Belege: [Spawner](C:/xampp/htdocs/conquer/src/Game/Charm/CharmSpawner.php:19), [Übernahme beim Einsammeln](C:/xampp/htdocs/conquer/src/Game/March/MarchTick.php:416), [Filter](C:/xampp/htdocs/conquer/src/Game/Research/BuffEngine.php:114), [zugelassene Kategorien](C:/xampp/htdocs/conquer/src/Game/Kingdom/KingdomInventory.php:19).

**3. Burg- und Kasernenausbau tragen die Kapazitätsprogression noch nicht.**

Ohne Boni liefert die aktuelle Berechnung drei Marschplätze, 50.000 Einheiten je Marsch und 500 Einheiten je Ausbildungsauftrag. Forschung, Relikte und Talente können Grenzen verändern. Die Burgstufe wird für diese Basiswerte nicht eingelesen. Die Kaserne hat genau einen Ausbildungsplatz; ihre Stufe wird für die allgemeine Verfügbarkeit geprüft, ohne die Ausbildungsmenge daraus abzuleiten. Der Ausbau besitzt dadurch weniger unmittelbaren strategischen Nutzen als in der Referenz.

Belege: [Kapazitätsformeln](C:/xampp/htdocs/conquer/src/Game/Research/ResearchEffects.php:31), [Ausbildungsplatz](C:/xampp/htdocs/conquer/src/Game/City/TroopTrainer.php:46), [Truppenfreischaltung](C:/xampp/htdocs/conquer/src/Game/City/TroopData.php:93).

**4. Truppenwahl beim Sammeln nutzt die vorhandenen Unterschiede nicht vollständig.**

Schon die T1-Daten unterscheiden Geschwindigkeit und Traglast: Fighter 65/2, Hunter 75/1,5 und Stableman 95/1. Tatsächlich berechnet `carryPerTroop()` ohne Boni für alle zehn Einheiten Traglast. Beim Sammelmarsch wird nur der Geschwindigkeits-Bonusfaktor verwendet; die Grundgeschwindigkeit aus den Truppendaten fehlt. Beim Monsterangriff wird sie dagegen berücksichtigt. Zusätzlich fehlt eine Aufenthaltsphase zum Abbauen: Ankunft löst Ernte und Rückreise aus. Sammeltempo wirkt gegenwärtig auf die Reisezeit.

Belege: [Truppenkatalog](C:/xampp/htdocs/conquer/data/troops.json), [Traglast](C:/xampp/htdocs/conquer/src/Game/Research/ResearchEffects.php:52), [Sammelreise](C:/xampp/htdocs/conquer/src/Game/March/GatherService.php:105), [Ernte bei Ankunft](C:/xampp/htdocs/conquer/src/Game/March/GatherService.php:254), [Monster-Marschtempo](C:/xampp/htdocs/conquer/src/Game/March/MarchDispatcher.php:129).

**5. Sichtbare Gebäude und funktionale Gebäude sind nicht dasselbe.**

Der Gebäudekatalog enthält 13 Typen. Die 3D-Stadt ergänzt einen Wachturm mit dem ausdrücklichen Hinweis, dass die Spielfunktion noch nicht angebunden ist. Mehrere unabhängig ausbaubare Gebäude desselben Typs sind durch den Primärschlüssel `(city_id, building_code)` nicht abgebildet. Zusätzliche Modelle oder Dekorationen würden diese Bauplatzstrategie allein nicht herstellen.

Belege: [funktionaler Gebäudekatalog](C:/xampp/htdocs/conquer/src/Game/City/CityState.php:26), [Wachturm-Vorschau](C:/xampp/htdocs/conquer/assets/city3d/play.js:43), [Datenmodell](C:/xampp/htdocs/conquer/migrations/0007_create_city_buildings_table.sql:4).

**6. Lager, VIP und Schatztruhen brauchen eine bewusste Regelentscheidung.**

Beim Lager ist die aktuelle Kapazitätsfunktion von der Plünderschutzfunktion getrennt. VIP 4 statt VIP 5 für den zweiten Bauplatz, kein VIP-6-Marschplatz und feste kostenlose Truhenquoten sind zunächst Unterschiede zur Referenz. Sie sind nicht automatisch Programmfehler. Wenn die Mechaniken des Guides verbindlich werden sollen, müssen diese Regeln gezielt abgestimmt und verbunden werden.

Belege: [Lagergrenzen](C:/xampp/htdocs/conquer/src/Game/City/BuildingData.php:128), [Plünderschutz](C:/xampp/htdocs/conquer/src/Game/Defense/DefenseService.php:84), [zweiter Bauplatz](C:/xampp/htdocs/conquer/src/Game/City/BuildingUpgrader.php:69), [VIP-Boni](C:/xampp/htdocs/conquer/src/Game/Vip/VipService.php:62), [Truhenquoten](C:/xampp/htdocs/conquer/src/Game/Treasure/ChestService.php:13).

**7. Allianz-Mitwirkung hat noch keinen persönlichen Shop-Kreislauf.**

Hilfe reduziert Zeiten und Spenden erhöhen die gemeinsame Kasse. Die geprüften Aktionswege schreiben keine persönlichen Allianz-Münzen gut. Im Datenmodell und in den Shop-Routen fehlt der dazugehörige Shop. Das ist ein eigenständiges fehlendes System, obwohl Allianz und Handel bereits existieren.

Belege: [Hilfe und Spenden](C:/xampp/htdocs/conquer/src/Game/Community/CommunityService.php:145), [Bündniskasse](C:/xampp/htdocs/conquer/src/Game/Kingdom/KingdomService.php:368), [vorhandener Handelsmarkt](C:/xampp/htdocs/conquer/src/Game/Trading/TradingShopService.php:35).

**Zusätzlicher Umfang unseres Projekts**

Über die im Artikel behandelten Grundlagen hinaus sind unter anderem Boss-Feldzüge mit mehreren Schwierigkeiten, Kongress und Schreine, Weltereignisse/Invasionen, Weltenverwaltung, Backoffice, Arena, Reliktpresets und Lord-Talentplanung angelegt beziehungsweise umgesetzt. Diese Funktionen gleichen fehlende Grundmechaniken nicht automatisch aus.

Vertiefende Projektbelege: [Spielsysteme](C:/xampp/htdocs/conquer/docs/FEATURE_PACK_STATUS.md), [Lord-Talente](C:/xampp/htdocs/conquer/docs/LORD_TALENTS.md), [Relikte](C:/xampp/htdocs/conquer/docs/TREASURES.md), [Handel](C:/xampp/htdocs/conquer/docs/TRADING_SHOP.md), [kostenlose Truhen](C:/xampp/htdocs/conquer/docs/DAILY_CHESTS.md).

**Vorgeschlagene Reihenfolge**

1. Vorhandene Spielwege vervollständigen: Monster-Rally und Buff-Kristalle reparieren.
2. Grundprogression klären und anschließen: Burg-/Kasernenwirkung, Sammeltraglast und Tempo, Lager-Schutzkapazität; anschließend gegebenenfalls Hospital-Ausbauwirkung.
3. Fehlende Kernsysteme ergänzen: Allianz-Münzen/Shop und ausbaubarer Wachturm mit Erkundung.
4. Größere strategische Erweiterungen planen: echte Außenbauplätze und regionale Landentwicklung ohne Blockchain.
5. Einführung und Feinschliff: Spieler bis zur ersten Allianzinteraktion führen; VIP-Meilensteine, Truhenskalierung und Lord-Levelbelohnungen festlegen.

Diese Priorisierung stammt aus dem ursprünglichen Vergleich. Der anschließende Umsetzungsstand ist oben verlinkt.
