# Union of Kingdoms – Umsetzung der Allianz-Eroberung

Aktuelle Benennung (29. September 2026): **Commune → Shrine → Royal Castle**. Kantone bleiben die geografischen Regionen; ihr Eroberungsziel heißt Shrine. Frühere Bezeichnungen im dokumentierten Entwurfs- und Prüfverlauf sind historische Namen derselben Ziele. Verbindliche Zuordnung: [TERRITORY_NAMING.md](TERRITORY_NAMING.md).

Stand: 28. September 2026. Umsetzungsplan auf Grundlage von [ALLIANCE_OBJECTIVES.md](ALLIANCE_OBJECTIVES.md). Alle fünf Pakete wurden auf ausdrücklichen Auftrag umgesetzt. Der folgende Plan bewahrt die fachlichen Abnahmekriterien; Implementierungsstand, Prüfungen und Einführung stehen in [ALLIANCE_CONQUEST_STATUS.md](ALLIANCE_CONQUEST_STATUS.md). Vorhandene Spielstände wurden nicht umgestellt.

## Ziel und erster spielbarer Stand

Die Eroberung wird als zusammenhängendes System für **100 Gemeinden, zwölf Kantonsfestungen und eine Krounbuerg** gebaut. Die drei Zielarten teilen sich Märsche, Teilnahme, Verteidigung, Besitzwechsel und Belohnungsbelege. Ihre Zugangsvoraussetzungen und Siege unterscheiden sich.

Der erste spielbare Stand ist eine echte Gemeinde-Eroberung in der Haupt-App: Allianzmitglieder öffnen das Gemeindeziel, bilden eine Rally, besiegen NPCs, übernehmen die Gemeinde, stationieren eine Garnison und erhalten den ersten belegten Gebietsertrag. Alles bleibt nach Abmelden und erneutem Öffnen gespeichert. Danach übernimmt eine zweite Testallianz das Gebiet im angekündigten Kampfzeitfenster.

## Was vorhanden ist und was ergänzt wird

| Grundlage im Projekt | Verwendung |
|---|---|
| `assets/world-lux-preview/game-geography.json` und `game-hydrology.json` | Freigegebene Gemeinde-/Kantonsgrenzen und Spielgewässer als gemeinsame Datengrundlage. |
| `assets/world-lux-preview/play-world.mjs`, `play.js` | Darstellung und Platzierungsverfahren der neuen Karte; Beispielstädte durch echte Daten ersetzen. |
| `src/Game/World/WorldContext.php` | Authentifizierte Weltzuordnung und Prüfung des Weltstatus. |
| `src/Game/Community/CommunityService.php` | Bestehende aktive Allianzverwaltung, Rollen und gemeinschaftliche Aktionen; aktive Einbindung vor Erweiterung nachverfolgen. |
| `src/Game/Rally/RallyService.php` | Sammlung, Anreise, Truppenreservierung und Rückkehr; neuer Gebietszieltyp nötig. |
| `src/Game/March/MarchArmy.php`, `BattleEngine.php`, Hospital- und Berichtssystem | Vorhandene Armee- und Kampfbausteine weiterverwenden. |
| `src/Game/Shrine/CongressService.php` | Technische Erfahrung mit Besatzung, Verstärkung und Rückruf; geeignete gemeinsame Teile übernehmen, ohne Schrein-IDs als neue Gemeinde-IDs umzudeuten. |
| `src/Game/Conquest/EventService.php` | Vorhandene Muster für Termine, zeitbezogene Wertung und Abschlussbelege; Schrein-Tiers sind keine neue Gebietsregel. |
| `src/Game/Alliance/TreasuryService.php` | Zielkonto für Gebietserträge; neue, eindeutig belegte Gutschriften ergänzen. |

Der aktive Gemeinschaftsweg läuft über `/api/community/*`. Alte Allianz-Mutationsrouten sind im Frontcontroller stillgelegt. Die Erweiterung darf diese alten Zugänge nicht wieder aktivieren.

Zu Planungsbeginn erlaubte die Server-Welterstellung nur **256 × 256 Felder**. Die neue Kartenvorlage verwendet **768 × 1.100 Felder**. Diese Trennung wurde durch versionierte Weltprofile mit unabhängiger Breite und Höhe umgesetzt.

## Paket 1 – Neue Karte mit echten Spielständen verbinden

1. Eine versionierte Kartendefinition mit Breite, Höhe, bebaubarer Landesfläche, Gewässern und stabilen Gemeinde-/Kantonskennungen einführen. Zunächst genügt ein Kontinent pro Welt; Kontinent und Welt bleiben ausdrücklich zugeordnet.
2. Für Server und Browser dieselbe Feldzuordnung exportieren. Jede gültige Landkoordinate gehört eindeutig zu einer Gemeinde und deren Kanton. Wassersperren und vollständige Gebäudegrundflächen verwenden dieselben Regeln.
3. Stadtgründung, Teleport, Spawn, Kartensuche, Marschziele, Kamera und Minikarte auf die Kartendefinition umstellen. Annahmen wie 256, 255 oder Mittelpunkt 128 an den betroffenen Stellen beseitigen. Die größere Welt erfordert auch passende Reisezeiten und eine angemessene Spawnverteilung.
4. Alle 113 Eroberungsziele mit gültigem, trockenem Standort und ausreichend Abstand importieren. Zielgeometrie und die veränderlichen Besitzer werden getrennt gespeichert. Die bisherigen 8×8-Landentwicklungsfelder sind keine Gemeinden und dürfen nicht als Besitzgebiete umgedeutet werden.
5. Die vorhandene Spielansicht unter `/city#world` an die echte Welt anbinden. Die Vorschau bleibt als Referenz erhalten. Beispielstädte und deren künstliche Besitzer werden nicht als echte Spieler importiert.

**Abnahme:** Eine Teststadt lässt sich auf der neuen Karte anzeigen und regelkonform versetzen; ein echter Sammel- und Monsterzug läuft einschließlich Rückkehr. Server und Karte stimmen bei Grenzen und Wasser überein. Alle 100 Gemeinden sind eindeutig zugeordnet.

Für diesen Schritt wird eine separate Testwelt beziehungsweise Datenbankkopie mit synthetischen Spielern verwendet. Die spätere Übernahme vorhandener Städte erhält einen eigenen Vorschauplan: Positionen, laufende Märsche, Garnisonen und Landfortschritt müssen vollständig zugeordnet sein. Ein Zurücksetzen echter Spielstände ist kein Migrationsverfahren.

## Paket 2 – Eine Gemeinde vollständig erobern

Ein neuer Bereich `src/Game/Territory/` bündelt die Gebietsregeln. Vorgesehene Aufgaben sind Geografie, Voraussetzungen, Belagerungsablauf, Garnisonen und Erträge. Das bestehende `AllianceTerritoryService` behandelt Allianzgebäude und Radiusboni; daraus entsteht nicht automatisch Gemeindebesitz.

Jedes Ziel erhält einen gespeicherten Besitzer, Verteidigung, einen laufenden Kampf und dessen Termine. Ein gemeinsamer Ablauf führt von Vorbereitung über Kampf und Sicherung zum Ergebnis. Ein neutraler Start verwendet NPC-Verteidiger; nach Übernahme gehört die Besatzung zur Allianz.

- Führung und Offiziere setzen das gemeinsame Ziel und organisieren den Angriff. Mitglieder treten mit eigenen Truppen bei oder übernehmen vorgesehene Unterstützungsaufgaben.
- Der Gebietszieltyp wird durchgängig in Rallys, Marschübersicht, Karte, Kampfauflösung und Berichten unterstützt. Die aktuellen Stadt-/Monsterverzweigungen werden entsprechend erweitert.
- Die serverseitige Verarbeitung entscheidet über Verluste, Verwundete, Besitz und Heimkehr. Ein geöffnetes Browserfenster ist dafür nicht erforderlich.
- Nach Übernahme sind Verstärkung und eigener Rückruf möglich. Auch bei Austritt oder Besitzerwechsel benötigen sämtliche stationierten und anreisenden Truppen einen eindeutigen weiteren Weg.
- Besitzer, nächstes Kampfzeitfenster, Verteidigung, Belohnung und eigene Beteiligung sind im Gemeindefenster sichtbar.

**Abnahme:** Zwei Testallianzen durchspielen NPC-Eroberung, Garnison, erlaubte gegnerische Übernahme und Rückkehr. Gleichzeitige Angriffe führen zu genau einem gültigen Besitzer. Dieselbe Armee und Belohnung werden nur einmal verrechnet. Persönliche Städte bleiben bei Gebietsverlust erhalten.

## Paket 3 – Gebietsertrag und Allianz-Zielübersicht

Zuerst die vier bestehenden Rohstoffe anschließen. Jede Rohstoffgemeinde erzeugt den konfigurierten Ertrag für die Allianzkasse. Persönliche Eroberungsbelohnungen haben getrennte Teilnahmebelege.

Ertrag wird nach dem tatsächlich gültigen Besitzzeitraum abgerechnet. Vor einem Besitzerwechsel wird der alte Zeitraum abgeschlossen. Die neue Allianz erhält keinen Ertrag aus der Zeit ihres Vorgängers. Wiederholte Verarbeitung oder ein verspäteter Serverlauf dürfen weder doppelt auszahlen noch die Online-Anwesenheit belohnen.

Die Allianzansicht erhält **Gebiete**, **Aktuelles Ziel** und **Eroberungsverlauf**. Angezeigt werden gehaltene Gemeinden, Fortschritt zur Kantonsmehrheit, nutzbare Gebietsvorteile, Termine und anstehende eigene Aktionen. Ein Ziel lässt sich direkt auf der Karte öffnen.

Anschließend folgen Abteiaufträge und Runenwacht. Deren begrenzte Belohnungen beziehungsweise Teleportladungen verwenden eigene Belege. Die Zuordnung der Typen zu den 100 Gemeinden wird als prüfbarer Katalog gepflegt; Erträge werden nicht zufällig bei jedem Aufruf neu vergeben.

**Abnahme:** Ertrag vor und nach Besitzerwechsel stimmt; Spielerzahl vervielfacht die gemeinsame Auszahlung nicht. Persönliche Ansprüche bleiben nach Allianzwechsel erhalten. Gleiche Sonderboni stapeln sich nicht unbegrenzt.

## Paket 4 – Kantonsfestungen und Besitzlimit

Die Voraussetzung ist `floor(Gemeindezahl / 2) + 1` aktuell kontrollierte Gemeinden. Der Nenner ist die vollständige Gemeindezahl des Kantons. Für die Alpha werden ganze Kantone als Eroberungsbereiche aktiviert, damit die Mehrheit nicht durch ausgeblendete Einzelgemeinden verfälscht wird.

Die Kantonsfestung verwendet denselben Belagerungsablauf, aber eine größere Verteidigung und die zusätzliche Mehrheitsprüfung. Ihr Besitzer wechselt erst nach der gewonnenen Festungsschlacht. Der bloße Verlust einer Gemeindemehrheit überträgt keinen Kanton.

Das Besitzlimit beträgt regulär zwei, im vorgeschlagenen Alpha-Profil einen Kanton. Parallel laufende Herausforderungen müssen berücksichtigt werden: Eine Allianz darf das Limit nicht durch zwei gleichzeitig abgeschlossene Festungskämpfe überschreiten.

**Vorgeschlagene Ausführungsregel:** Beim verbindlichen Start eines Kantonsangriffs werden Mehrheit und freie Besitzkapazität geprüft; für den Angriff wird ein Platz reserviert. Bei Abbruch oder Niederlage wird er freigegeben. Der gestartete Angriff erhält seine Berechtigung als gespeicherten Stand, damit ein späterer einzelner Gemeindeverlust den laufenden Feldzug nicht kommentarlos aufhebt. Allianzaustritte und ungültige Truppenbefehle werden weiterhin separat geprüft. Diese zeitliche Präzisierung ist ein Umsetzungsvorschlag, keine bereits im Quellchat festgelegte Zusatzregel.

**Abnahme:** Gerade und ungerade Gemeindezahlen, Mehrheitsverlust, konkurrierende Kantonsangriffe, Alpha-/Weltlimit und unveränderter Kantonsbesitz bei bloßem Gemeindewechsel sind geprüft.

## Paket 5 – Krounbuerg und Herrschaft

Mindestens ein Kanton eröffnet den Kronenzugang. Die Burg ergänzt den gemeinsamen Belagerungsablauf um mehrere Ziele und eine gespeicherte Wertung der gehaltenen Kontrolle. Gemeinsame Vorbereitungen und Belagerungsziele sind sichtbar; ein letzter Treffer allein entscheidet nicht über den Sieg.

Vor Aktivierung eines Kronenkriegs werden im Regelprofil Teilnahmezeitpunkt, Pflichtziele, Kontrollwertung, Ende und Gleichstandsregel festgelegt und angezeigt. Ebenso wird ausdrücklich festgelegt, wie sich ein Kantonsverlust während eines bereits begonnenen Kronenkriegs auswirkt. Die bekannten Grundregeln werden dabei nicht erneut zur Wahl gestellt.

Nach Abschluss werden Gewinner, Herrschaft und persönliche Ansprüche gespeichert. Die berechtigte Allianzführung benennt den Grand-Duc beziehungsweise die Grande-Duchesse. Zunächst genügt die sichtbare Herrschaft mit Chronik und Titel; die begrenzten Fähigkeiten der vier Hofämter folgen als eigener Ausbau. Eine noch nicht gebaute Amtsfähigkeit darf in der Oberfläche keine Wirkung versprechen.

**Abnahme:** Nur berechtigte Allianzen nehmen teil; gleichzeitige Aktionen und verspätete Verarbeitung ergeben denselben Sieger. Die Krone bleibt bis zur nächsten erfolgreichen Eroberung erhalten. Persönliche Städte und Forschungsstände bleiben bestehen.

## Gemeinsame technische Regeln

Die gespeicherten Daten unterscheiden Geografie, aktuellen Besitz, Kampfteilnahme, Garnison, Besitzerverlauf und ausgezahlte Belohnungen. Welt-/Kontinentzuordnung und eindeutige Ereigniskennungen gehören in jeden relevanten Datensatz. Geometrieänderungen während laufender Angriffe sind ausgeschlossen.

Neue Lesezugriffe liefern Gebietsübersicht und Zieldetails; getrennte Schreibzugriffe starten Angriffe, treten ihnen bei, verstärken, rufen zurück und wählen Allianz-Ziele. Sie verwenden Anmeldung, Rollenprüfung, CSRF-Schutz, erwartete Welt und wiederholsichere Vorgangskennungen. Spieler können Besitzer, Truppenwerte oder Ertrag nicht selbst vorgeben.

Ein regelmäßiger Serverlauf verarbeitet fällige Ankünfte, Kampfabschlüsse, Rückreisen und Erträge in begrenzten Paketen. Er verwendet die vorgesehenen Ereigniszeitpunkte, nicht die Uhrzeit des nächsten Logins. Wiederanlauf nach Unterbrechung und parallele Verarbeitung sind Teil der fachlichen Tests.

Kampfzeiten und Balancewerte werden pro Welt als versioniertes Profil gespeichert. Laufende Kämpfe behalten ihren gültigen Regelstand. Die alte Schrein-Haltezeit von einer Stunde wird nicht automatisch auf Gemeinden, Kantone oder die Burg übertragen. Kalenderdaten werden intern eindeutig gespeichert und den Spielern in ihrer lokalen Zeit angezeigt.

## Einführung und Abnahme

Die erste technische Probe nutzt eine vollständige Testregion auf der echten neuen Karte. Danach folgt der komplette Weg bis zur Burg mit verkürzten Testzeiten. Die Alpha kann anschließend zwei oder drei ganze Kantone für Eroberung aktivieren, während die komplette Karte besiedelbar bleibt.

Die neue Kartenvariante wird je Welt eingeführt. Alte Schrein-/Kongressziele werden in der neuen Variante weder angelegt noch angeboten. Alte laufende Armeen und Ansprüche müssen vor einer bestehenden Weltumstellung regulär abgeschlossen oder über eine geprüfte Zuordnung übernommen werden; ihre Daten werden nicht durch Umbenennen gelöscht.

Notwendige Prüfungen betreffen Grenzfälle der Geometrie, gleichzeitige Eroberungen, Besitzlimits, Eintritt/Austritt, doppelte Anfragen, verlorene Antworten, Offline-Abschluss und vollständig erhaltene Armeen und Ansprüche. Bestehende Stadt-, Monster-, Rally- und Weltwechselwege erhalten gezielte Regressionstests.

Die Haupt-App wird unter `/city#city` und `/city#world` auf Desktop, bei 320/390 px Breite und im Querformat geprüft. Gestaltung und Grafiken folgen `UI_STYLE_GUIDE.md` und `ART_DIRECTION.md`. Kartenabfragen liefern nur benötigte Ausschnitte; die große Karte wird nicht als riesiges Gesamtbild oder komplette Objektliste ständig übertragen. Ein echter Mobilgerätetest und ein gesonderter Lasttest sind vor einer Aussage über 1.000 gleichzeitig aktive Spieler erforderlich.

Erst nach dem vollständigen Kontinent-Ablauf folgen zusätzliche NPC-Invasionen, Weltbosse und Allianzturniere. Sie verwenden dann das bereits erprobte Gebiets- und Ereignissystem.

## Umgesetzte Ausführungsregeln

Die Umsetzung umfasst alle fünf Pakete einschließlich Abtei, Runenwacht, regionaler Versorgung, Kantonsauftrag und wirksamer Hofämter. Zusätzliche Invasionen, Weltbosse und Turniere bleiben spätere Erweiterungen.

Mehrheit und freier Kantonsplatz werden beim verbindlichen Rally-Start gespeichert. Ein laufender Kantonsangriff reserviert einen Platz. Der Kronenzugang wird ebenso bei jedem Angriff geprüft und gespeichert; ein späterer Kantonsverlust bricht einen bereits berechtigten Angriff nicht ab. Die tatsächliche Allianzmitgliedschaft bleibt für Teilnahme und Verteidigung erforderlich.

Kampfboni und Marschgeschwindigkeit einer Gebietsarmee werden beim Reservieren ihrer Truppen gebunden: für den Anführer beim Start, für Teilnehmer beim Beitritt und für Garnisonen beim Entsenden. Spätere Forschung oder ablaufende Talismane verändern diese Armee nicht. Neue Befehle verwenden die dann aktuellen Werte. Verspätete Teilnehmer und unterwegs abgebrochene Armeen behalten ihre reale Rückreise und belegen bis zur Heimkehr einen Marschplatz.

Die Krounbuerg hat Tor, Arsenal und Thron als Pflichtziele. Eine Allianz muss im laufenden Krieg alle drei mindestens einmal erobert haben. Unter den qualifizierten Allianzen entscheidet die Summe der Kontrollsekunden aller Ziele; bei Gleichstand die erste Kontrollübernahme, zuletzt die stabile Allianzkennung. Der letzte Angriff allein vergibt die Krone nicht.

Regelprofile sind versioniert. Laufende Feldzüge und Kronenkriege behalten ihren gespeicherten Regelstand. Vor Änderungen werden fällige Ereignisse und Erträge nach den bisherigen Regeln verarbeitet. Laufender Gebietsertrag verwendet ab der Änderung den neuen Satz; ein älterer Angriffsstand ändert diese kontinuierliche Abrechnung nicht. Vorhandene Welten behalten ihr bisheriges Profil; die neuen Migrationen wurden ausschließlich in synthetischen Testdatenbanken ausgeführt.
