**Conquer – Funktionsvergleich und Entscheidungsgrundlage für Monsterbeute**

**Fortschreibung nach deiner Durchsicht:** A01/A02 und die Erweiterungen E01–E42 sind als Gesamtumfang angenommen. Der verbindliche weitere Plan steht in [IMPLEMENTATION_PLAN.md](planning/IMPLEMENTATION_PLAN.md). E16 umfasst jetzt Landteile auf Stufe 1–9 und eine Kartenfreigabe in drei Phasen. Die Entfernung zum Zentrum bestimmt nur die Startstufe; **jedes Landteil kann Stufe 9 erreichen**. Deathkar, Magdar sowie grüne, goldene und rote Drachen gelten nach deiner Klarstellung als noch nicht aktive Inhalte. Ihre JSON-Einträge sind kein Nachweis, dass sie bereits im Spiel verfügbar sind.

**Technischer Zwischenstand am selben Tag:** Inzwischen sind `RewardCatalog`, `/admin/rewards` („Beute & Drops“), `reward_overrides` und eine allgemeine Item-/Edelsteinziehung im Solo-Kampf hinzugekommen. Die folgende Recherche dokumentiert den früher geprüften Stand und ist deshalb für diese Punkte historisch. Der Umsetzungsplan und die erneute Prüfung des jeweils aktuellen Codes haben Vorrang; die bestehende Dropverwaltung wird ausgebaut. Ihre vollständige Laufzeitabnahme und der Charm-Weg für alle Monster sind damit noch nicht nachgewiesen.

Bei der erneuten Planungsprüfung umfasst `monsters.json` inzwischen 99 Definitionen unter 14 Namen statt 89; hinzugekommen sind zehn Grumwald-Stufen. Der aktuelle Katalogabgleich steht im verlinkten Umsetzungsplan und in dessen Drop-/Charm-Detailplan. Frühere Zählungen weiter unten bleiben als Recherchehistorie erhalten.

Stand: 12. September 2026. Recherche am lokalen Arbeitsstand einschließlich nicht eingecheckter Dateien. Spielcode und Spielerdaten wurden für diese Recherche nicht verändert. „Vorhanden“ bezeichnet einen im aktuellen Code angebundenen Ablauf; es ist keine erneute vollständige Browser-, Mehrspieler- oder Produktionsabnahme. Ältere Testmeldungen wurden nicht als neue Testläufe übernommen.

**Ergebnis**

Conquer besitzt bereits einen großen Teil der Grundsysteme eines Aufbaustrategiespiels mit Weltkarte, Allianzen und zeitgesteuerten Märschen. Der kooperative Bereich ist mit Monster-Rallies, Feldzügen und Dungeons breit angelegt. Die wichtigsten nächsten Schritte sind ein verlässlicher Beutekreislauf, der vollständige Charm-Sammelweg, eine durchgängige Einführung und gezielte Erweiterungen der Allianz-Zusammenarbeit.

Eine belastbare Prozentzahl wie „80 % eines fertigen Spiels“ wäre irreführend: Die Anzahl vorhandener Systeme sagt wenig über ihre Spieltiefe, Balance, Verknüpfung und Betriebsreife aus.

**Vergleichsmaßstab**

Verglichen wurden die aktuellen offiziellen Beschreibungen von Rise of Kingdoms, Call of Dragons, Whiteout Survival, Kingshot und Tribal Wars. Der vorhandene LoK-Vergleich wurde als historische Projektgrundlage berücksichtigt. Nicht jede Funktion dieser Spiele ist ein Genrestandard oder für Conquer sinnvoll.

| Referenz | Nachgewiesene Vergleichsfunktionen | Bedeutung für Conquer |
|---|---|---|
| Rise of Kingdoms | Erkundungsnebel und Späher, Kommandanten mit Talententwicklung, Allianzgebiete, Kartenmarkierungen, Pässe und Gruppenfortschritt. | Erkundung und Allianzplanung sind sinnvolle Vergleichspunkte; frei steuerbare Echtzeitkämpfe wären ein großer Architekturwechsel. [Offizielle Spielbeschreibung](https://play.google.com/store/apps/details?id=com.lilithgame.roc.gp&hl=en). |
| Call of Dragons | Kooperative Behemoth-Kämpfe, Heldenfähigkeiten, Begleiter sowie Gebäude, Forschung und Ressourcenwirtschaft. | Gemeinsame Bosse passen zum vorhandenen PvE-Schwerpunkt; Helden und Begleiter wären eigenständige zusätzliche Entwicklungssysteme. [Offizielle Spielbeschreibung](https://play.google.com/store/apps/details?id=com.farlightgames.samo.gp&hl=en). |
| Whiteout Survival | Organisierte Allianzjagd mit Teilnahmebedingungen und Regeln für wiederholte Teilnahme. | Terminplanung, Teilnahmeberechtigung und nachvollziehbare Belohnung gehören zum Gruppeninhalt. [Offizielle Hilfe zur Beast Hunting](https://centurygames.helpshift.com/hc/en/64-whiteout-survival/section/1260-beast-hunting/). |
| Kingshot | Allianz-Hauptquartier, Banner, territoriale Ressourcen und Forschungsvoraussetzungen; Allianzaufgaben und angemeldete Wettkämpfe. | Allianzgebiete, gemeinsame Aufträge und freiwillige Turniere sind mögliche Erweiterungen. [Allianzsystem](https://centurygames.helpshift.com/hc/en/140-kingshot/section/1417-alliance/), [Allianzaufgaben](https://centurygames.helpshift.com/hc/en/140-kingshot/section/1381-alliance-mobilization/), [Tri-Alliance Clash](https://centurygames.helpshift.com/hc/en/140-kingshot/faq/8521-what-is-tri-alliance-clash/). |
| Tribal Wars | Truppenentsendung zum Ressourcenerwerb mit Traglast, Vorschau auf Ertrag und Dauer sowie Rückkehrbericht. | Reise, Sammlung und Rückkehr sind auch im Browser gut tragende Grundmechaniken. [Offizielle Erklärung zum Sammeln](https://support.innogames.com/kb/TribalWars/en_DK/3002). |

Die nachfolgenden Prioritäten sind meine Bewertung für Conquer, keine Aussage der Hersteller.

**Kingshot – ausführlicher direkter Vergleich**

Ergänzt auf deinen Wunsch am 12. September 2026. Grundlage sind die offizielle Spielbeschreibung und die unten je Funktion verlinkten Hilfeseiten von Century Games. Der Conquer-Stand bezieht sich auf die lokale Codeprüfung dieser Recherche. Die Auswahl umfasst die für unseren Aufbau-, Allianz- und PvE-Schwerpunkt relevanten Systeme; sie ist keine vollständige Liste sämtlicher zeitlich begrenzter Kingshot-Aktionen. Freischaltungen können bei Kingshot zusätzlich vom Serverfortschritt abhängen.

| Funktion in Kingshot | Was Conquer davon bereits besitzt | Unterschied / mögliche Ergänzung |
|---|---|---|
| Stadtentwicklung, Rohstoffversorgung, Soldaten und Abwehr von Angriffen. [Spielbeschreibung](https://www.centurygames.com/games/kingshot/) | Gebäude, Ressourcenproduktion, Ausbildung, Mauer, Stadtverteidigung und Weltinvasionen. | Der gemeinsame Grundkreislauf ist vorhanden. |
| Zivilisten retten und mit Nahrung und Unterkunft versorgen. [Spielbeschreibung](https://www.centurygames.com/games/kingshot/) | Sichtbare Dorfbewohner und Rohstoffproduktion. | Keine entsprechende spielbare Bevölkerungssimulation mit Versorgung und Unterkunft nachgewiesen. **E36** wäre ein größerer Ausbau des Städtebaus. |
| Rekrutierbare Helden mit unterschiedlichen Fähigkeiten. [Spielbeschreibung](https://www.centurygames.com/games/kingshot/) | Lord-Talente und unterschiedliche Truppentypen. | Ein Lord-Talentbaum ersetzt keine Sammlung individuell entwickelter Helden. Option **E26**. |
| Heldenausrüstung mit Verstärkung, Aufstieg und weiteren Verbesserungen. [Heldenausrüstung](https://centurygames.helpshift.com/hc/en/140-kingshot/section/1407-hero-gear-ascension-and-imbuement/) | Relikte, Fragmente, Ausrüstungsplätze und Presets. | Keine separate Ausrüstung je Held. Erst bei Einführung von Helden sinnvoll; **E42**. |
| Governor Gear und Governor Charms als ausbaubare, nicht ablegbare Ausrüstungselemente. [Governor Gear](https://centurygames.helpshift.com/hc/en/140-kingshot/section/1737-governor-gear/) | Relikte sowie temporäre Charm-Buffs. | Eigenständiges System: Kingshots Governor Charms entsprechen nicht unseren einsammelbaren Karten-Charms. Eine zusätzliche dauerhafte Ausrüstung wäre **E42**. |
| Begleiter fangen, füttern und verbessern. [Pet System](https://centurygames.helpshift.com/hc/en/140-kingshot/section/1402-pet-system/) | Kein vergleichbarer eigener Begleiter-Fortschritt nachgewiesen. | Option **E27**, zusätzlicher Entwicklungs- und Balanceaufwand. |
| Bear Hunt: durch die Allianzleitung gestartete Jagd mit gemeinsamer Falle, Beiträgen und Rally-Kämpfen. [Bear Hunt](https://centurygames.helpshift.com/hc/en/140-kingshot/section/1740-bear-hunt/) | Monster-Rallies und organisierte Feldzüge mit Versorgung und Beiträgen. | Gemeinsame Bossjagd ist bereits vorhanden. Wiederkehrende Allianztermine und eine ausbaubare Vorbereitungseinrichtung wären Erweiterungen von **E12/E19**. |
| Viking Vengeance: von Offizieren gestartete Angriffe auf Allianzmitglieder mit gewerteter Verteidigung. [Viking Vengeance](https://centurygames.helpshift.com/hc/en/140-kingshot/section/1386-viking-vengeance/) | Verstärkungen, Stadtverteidigung und Weltinvasionen. | Unsere Invasion ist ein gemeinsames Beitragsziel; ein eigenes Allianzereignis mit Abwehrwellen fehlt. **E37**. |
| Allianz-Hauptquartier, Bannerverbindungen, territoriale Rohstoffe und Zugangsvoraussetzungen für Regionen. [Allianzsystem](https://centurygames.helpshift.com/hc/en/140-kingshot/section/1417-alliance/) | Allianzen, Diplomatie, Kasse, Forschung und kontrollierbare Schreine. | Ein zusammenhängendes, ausbaubares Allianzgebiet fehlt. **E15/E16**. |
| Alliance Mobilization mit annehmbaren Aufgaben und begrenzten Versuchen. [Allianzaufgaben](https://centurygames.helpshift.com/hc/en/140-kingshot/section/1381-alliance-mobilization/) | Persönliche Tagesaufgaben und Beiträge in Feldzügen. | Gemeinsames Allianz-Auftragsbrett mit eigener Fortschrittswertung fehlt. **E11**. |
| Allgemeine Allianzabstimmungen mit laufenden und abgeschlossenen Ergebnissen. [Alliance Vote](https://centurygames.helpshift.com/hc/en/140-kingshot/section/1403-alliance-vote/) | Dungeonabstimmungen über die Seitenkammer. | Diese Abstimmung ist an den Dungeonlauf gebunden. Allgemeine Umfragen zu Terminen, Zielen oder Strategie fehlen; **E39**. |
| Allianzshop, beispielsweise als Bezugsquelle für Teleporter. [Teleporter und Fundorte](https://centurygames.helpshift.com/hc/en/140-kingshot/faq/9015-what-are-the-teleporters-available-in-the-game-what-is-the-difference-between-them-and-how-to-obtain-them/) | Karawane, VIP-Handel und Ressourcentransporte. | Ein eigener Allianzshop ist **E05**. Die Vergütung von Hilfe/Spenden mit Münzen ist unser Gestaltungsvorschlag. |
| Swordland Showdown mit Terminabstimmung, Anmeldung und Teilnehmer-/Ersatzspielerregeln. [Swordland Showdown](https://centurygames.helpshift.com/hc/en/140-kingshot/section/1389-swordland-showdown/) | Freiwillige Einzelduelle und klassische Allianz-Rallies. | Ein angemeldetes Allianz-Schlachtfeldturnier fehlt; **E12/E23**. |
| Alliance Championship mit registrierten Mitgliedern und eigener Kampf-/Belohnungswertung. [Alliance Championship](https://centurygames.helpshift.com/hc/en/140-kingshot/section/1387-alliance-championship/) | Ranglisten und Arena. | Ein organisierter Mannschaftswettbewerb wäre **E23**, keine weitere Variante des Stadtangriffs. |
| Tri-Alliance Clash mit mehreren Allianzparteien, Zeitfenstern und eigenen Schlachtfeldregeln. [Tri-Alliance Clash](https://centurygames.helpshift.com/hc/en/140-kingshot/section/1547-tri-alliance-clash/) | Feldzüge verbinden zwei Allianzen kooperativ. | Unser Koalitions-PvE erfüllt einen anderen Zweck. Mehrparteien-PvP wäre eine spätere Variante von **E23**. |
| Mystic Trial mit Stufen, einmaligen Erstabschluss-/Meilensteinbelohnungen und Raid-Funktion nach Freischaltung. [Mystic Trial](https://centurygames.helpshift.com/hc/en/140-kingshot/section/1554-mystic-trial/) | Wiederholbare Dungeons, Schwierigkeitsgrade und persönliche Beute. | Ein eigener Stufenpfad mit gespeicherten Erstabschlüssen und späterer verkürzter Wiederholung fehlt; **E38**. |
| Trial Crystals und ein wöchentlich erneuerter Trial Shop. [Mystic Trial](https://centurygames.helpshift.com/hc/en/140-kingshot/section/1554-mystic-trial/) | Direktbeute und verschiedene allgemeine Shops. | Verdiente Ereignismarken könnten gezielte Belohnungswahl ermöglichen; **E41**, ergänzend zu **E21**. |
| Auto Hunting mit eingestelltem Stufenbereich; laut Hilfe nur online verfügbar. [Auto Hunting](https://centurygames.helpshift.com/hc/en/140-kingshot/section/1396-auto-hunting/) | Einzelne Jagdaufträge und serverseitige Abwicklung bereits entsandter Märsche. | Es werden nicht automatisch neue Jagdaufträge gestartet. Eine ausdrücklich begrenzte Jagdserie wäre **E40**. |
| Kingdom of Power: weltübergreifende Vorbereitung, Kämpfe und Belohnungen. [Kingdom of Power](https://centurygames.helpshift.com/hc/en/140-kingshot/section/1532-kingdom-of-power/) | Mehrere getrennte Welten und Weltereignisse. | Weltenauswahl ist kein Welt-gegen-Welt-Wettbewerb. Ausbau **E35**, sobald genügend aktive Welten vorhanden sind. |

**Was wir aus Kingshot für Conquer übernehmen könnten**

Am besten passen aus meiner Sicht gemeinsame Abwehrwellen (**E37**), ein PvE-Prüfungspfad mit Erstabschlussbelohnungen (**E38**), allgemeine Allianzabstimmungen (**E39**) sowie die bereits vorgeschlagenen Allianzaufträge, Kalender und Gebiete (**E11/E12/E15**). Diese Vorschläge nutzen vorhandene Armee-, Gruppen- und Fortschrittssysteme. Bevölkerung, Heldenausrüstung und Begleiter sind größere eigenständige Ausbauten.

Für unsere Dropplanung ist besonders die Kombination aus direkter Beute, einmaligen Meilensteinbelohnungen und gezieltem Einkauf mit erspielten Ereignismarken interessant. Kingshots Mystic Trial belegt diese Belohnungswege; daraus lässt sich für Conquer **E38/E41** ableiten. Konkrete Dropchancen oder Mengen für unsere Monster lassen sich daraus nicht übernehmen. [Offizielle Regeln](https://centurygames.helpshift.com/hc/en/140-kingshot/section/1554-mystic-trial/).

**Abgrenzung der Charms:** Kingshots Governor Charms werden an der Ausrüstung entwickelt und sind nicht ablegbar. Unser beauftragtes System bleibt: jedes getötete Monster hinterlässt genau einen temporär wirksamen Charm am gespeicherten Spawnort, den Truppen einsammeln. Die Kingshot-Recherche ändert diesen Auftrag nicht. [Governor-Charms](https://centurygames.helpshift.com/hc/en/140-kingshot/section/1737-governor-gear/).

**Bestandsaufnahme: Einstieg, Stadt und Wirtschaft**

| Funktion | Aktueller Stand |
|---|---|
| Registrierung, Passwortanmeldung und Abmeldung | Vorhanden. |
| Persistenter Fortschritt | Vorhanden; Speicherung in MySQL. |
| Google-/Discord-Anmeldung | OAuth-Code vorhanden; Konfiguration auf einer konkreten Installation hier nicht geprüft. |
| Passwort ändern | Vorhanden. |
| Kontowiederherstellung | Einmalig sichtbarer Wiederherstellungscode, gespeicherter Hash und Verbrauch bei Wiederherstellung. |
| Andere Sitzungen abmelden | Vorhanden. |
| Öffentliche Spielerprofile | Anzeigename, Porträt, Beschreibung, Statistiken und Erfolge. |
| Einstellungen | Unter anderem reduzierte Bewegung, Zahlenformat und Aktionsbestätigungen. |
| Mehrere Welten | Beitreten und Wechseln; Stadtfortschritt und Allianzen je Welt. Inventar und Edelsteine teilweise accountweit. |
| Installierbare Web-App | PWA und öffentliche Offline-Hilfeseite; kein Offline-Spielbetrieb. |
| Sprachen | DE/FR/LU-Menüs; ausführliche Inhalte noch nicht vollständig übersetzt. |
| Interaktive 3D-Stadt | Gebäudeauswahl, Kamerasteuerung, Gebäudenamen und Einbettung in die Haupt-App. |
| Burg | Ausbau, Voraussetzungen und stufenabhängige Marschkapazität. |
| Mauer | Ausbau, Haltbarkeit, Verteidigung, Reparatur und Regeneration. |
| Bauernhof | Nahrungsproduktion und Ausbau. |
| Holzfällerlager | Holzproduktion und Ausbau. |
| Steinbruch | Steinproduktion und Ausbau. |
| Goldmine | Goldproduktion und Ausbau. |
| Lager | Produktionskapazität und stufenabhängiger Grundschutz vor Plünderung. |
| Schatzkammer | Reliktverwaltung, stufenabhängige Ausrüstungsplätze, kostenlose Truhen. |
| Kaserne | Ausbildung und stufenabhängige Ausbildungsmenge. |
| Hospital | Verwundete, automatische Heilung und Beschleunigung; Kapazitätsmechanik unvollständig, siehe Lücken. |
| Akademie | Forschung mit Voraussetzungen und Wartezeit. |
| Handelsmarkt | Karawane und VIP-Handel; Gebäudestufe erweitert das Karawanenangebot. |
| Allianzhalle | Allianzbezug und stufenabhängige Monster-Rally-Kapazität. |
| Gebäudeentwicklung | 13 funktionale Grundgebäudetypen, Entwicklung bis Stufe 30, Kosten und Voraussetzungen. |
| Bauaufträge | Zeitgesteuerter Ausbau, Abbruch, Beschleuniger und Sofortfertigstellung. |
| Zwei Baumeister | Zweiter Bauplatz ab VIP 4. |
| Zusätzliche Außenbauplätze | Im aktuellen Dienst ausdrücklich deaktiviert. Altbestände werden weiter abgewickelt. Die Beschreibung in BUILDING_PLOTS.md entspricht nicht mehr dem aktiven Zustand. |
| Wachturm | Sichtbares Modell; kein vollständiges ausbaubares Erkundungssystem. |
| Laufende Produktion | Nahrung, Holz, Stein und Gold, Zeitabrechnung bei Abwesenheit und Lagergrenzen. |
| Ressourcenpakete | Ungeöffnete Pakete im Inventar und Verbrauch zur Gutschrift. |
| Edelsteine | Accountwährung mit verschiedenen Spielverwendungen. |
| Rohstofftausch | Feste Angebote serverseitig vorhanden; aktuelle Handelsoberfläche konzentriert sich auf Karawane/VIP. |
| Ressourcentransporte | Reale Abbuchung, Reise, Zustellung oder Erstattung und Verlauf. |

Belege: [Stadtzustand](C:/xampp/htdocs/conquer/src/Game/City/CityState.php), [Gebäuderegeln](C:/xampp/htdocs/conquer/src/Game/City/BuildingProgression.php), [deaktivierte Außenbauplätze](C:/xampp/htdocs/conquer/src/Game/City/BuildingPlotService.php), [aktueller Funktionsstand](C:/xampp/htdocs/conquer/docs/FEATURE_PACK_STATUS.md), [Handel](C:/xampp/htdocs/conquer/docs/TRADING_SHOP.md).

**Bestandsaufnahme: Armee, Karte und Kämpfe**

| Funktion | Aktueller Stand |
|---|---|
| Truppentypen | Infanterie, Fernkämpfer und Kavallerie, jeweils T1–T5: 15 Definitionen. |
| Truppenfreischaltung | Gebäude- und Forschungsvoraussetzungen. |
| Ausbildung | Mengenwahl, Kosten, Warteschlange, Beschleunigung und Abbruch. |
| Beförderung | Eigene Aufträge mit Reservierung, Kosten und Rückabwicklung. |
| Formationen | Vier benannte Armeevorlagen. |
| Marschauswahl | Mengen je Einheit, verfügbare Bestände und Kapazitätsprüfung. |
| Marschplätze | Drei reguläre Plätze; zusätzliche Boni und ein ausschließlich zum Sammeln nutzbarer Talentplatz. |
| Reisen und Rückkehr | Truppenreservierung, sichtbare Märsche, Rückkehr und Beutegutschrift. |
| Rückruf | Für unterstützte Marscharten, einschließlich laufender Sammler. |
| Weltkarte | Verschieben, Zoomen, Minikarte, Koordinatensprung, Städte, Rohstofffelder, Monster und Ereignisziele. |
| Kartensuche | Suche und Filter innerhalb der geladenen Ziele; noch kein umfassender weltweiter Monsterfinder. |
| Regionen | Wald, Frostlande, Sonnendünen und Aschenlande; regionale Bosszuordnung. |
| Rohstofffelder | Nahrung, Holz, Stein, Gold und Edelsteine mit Stufen, Vorräten und Nachspawns. |
| Sammelreise | Tempo der langsamsten gewählten Truppenart. |
| Sammeltraglast | Summe der unterschiedlichen Truppenwerte mit Boni. |
| Sammelarbeit | Aufenthaltsdauer am Feld; Rückruf bringt den bis dahin gewonnenen Ertrag zurück. |
| Besetzte Rohstofffelder | Belegung und Angriffsweg vorhanden. |
| Solo-Monsterkampf | Schaden, Rest-LP, Verwundungen, Grundressourcen, Jagd-XP und Berichte. Itembeute unvollständig. |
| Aktionspunkte | Verbrauch, Regeneration und AP-Gegenstände. |
| Monster-Rallies | Erstellen, beitreten, Sammelzeit, früher Start, Abbruch, gemeinsamer Kampf und Rückkehr. |
| Persönliche Rally-Berichte | Eigene Truppen, Verwundete, Ressourcen, Items und XP. |
| Rally-Beute | Gemeinsamer Pool wird nach entsandter Truppenanzahl aufgeteilt. |
| Stadtangriffe | Soloangriffe und Allianz-Rallies gegen zulässige fremde Städte. |
| Stadtplünderung | Ressourcenverlust und geschützte Bestände. |
| Spähmärsche | Spionageberichte und Spähschutz. |
| Anfängerschutz und Schilde | Zeitlich begrenzter Schutz mit serverseitiger Prüfung. |
| Verstärkungen | Echte Truppen in fremden Städten und Rückreise. |
| Kampfauswertung | Truppeneigenschaften, Forschungs-, Talent-, Relikt- und Buffwerte. |
| Kampfhistorie | Ergebnis, Truppen und Beute; Darstellung unterscheidet Angriff/Verteidigung. |
| Arena | Ausdrücklich angenommene Duelle ohne Verbrauch der echten Armeen. |
| Charms auf der Karte | Backend teilweise vorhanden; nur drei Standardfamilien, aktueller UI-Sammelweg fehlt. |

Belege: [Marschverarbeitung](C:/xampp/htdocs/conquer/src/Game/March/MarchTick.php), [Monster-Rallies](C:/xampp/htdocs/conquer/src/Game/Rally/MonsterRally.php), [Sammeln](C:/xampp/htdocs/conquer/src/Game/March/GatherService.php), [Weltkarte](C:/xampp/htdocs/conquer/assets/js/world-map.js), [angeschlossene Grundsysteme](C:/xampp/htdocs/conquer/docs/GUIDE_SYSTEMS.md).

**Bestandsaufnahme: Fortschritt, Beute und Gruppeninhalte**

| Funktion | Aktueller Stand |
|---|---|
| Forschungskatalog | 129 Technologien: 55 Militär, 34 Produktion und 40 fortgeschrittene Einträge. |
| Forschungsbaum | Voraussetzungen, Freischaltungen, Ausbau, Warteschlange und Beschleunigung. |
| Lord-/Hunter-Level | Stufe 1–60 mit Jagd-XP. |
| Lord-Talente | 36 Talente in Angriff, Verteidigung, Sammler und Jäger. |
| Talentplanung | Punkte vor dem Speichern planen, Voraussetzungen prüfen, Boni anzeigen. |
| Talentwechsel | Kostenlose Neuverteilung mit Zeitregel und Sperren während relevanter Armeeaktivität. |
| Ältere Meisterschaftszweige | Archivdaten; kein zusätzlich aktives zweites System neben dem neuen Talentbaum. |
| VIP | 20 Stufen, Punkte, tägliche Punkte und wirksame Boni. |
| VIP-Handel | 52 Angebote mit Freischaltung und wöchentlichen Kontolimits. |
| Karawane | Achtstündige Rotation, Rohstoff-/Edelsteinpreise, Bestände und gezielte Reliktfragmente. |
| Inventarkatalog | 166 Gegenstände. |
| Inventarfunktionen | Mengen, Kategorien, Verwendung und echte Effekte. |
| Ressourcenpacks/-kisten | 48 Ressourcenpakete und fünf Ressourcenkisten. |
| Beschleuniger | 52 Einträge für verschiedene Auftragsarten und Dauern. |
| Buffgegenstände | 35 Einträge; außerdem vier AP-, sieben VIP- und zwei Teleport-Einträge. |
| Truhen und Fragmentpakete | Drei Truhentypen und zehn Fragmentpakete. |
| Reliktsammlung | 82 Relikte, Fragmente, automatische Freischaltung/Stufen und Bonusvorschau. |
| Ausrüstung | Sechs stufenabhängige Plätze, weltbezogene Ausrüstung und tatsächliche Boni. |
| Ausrüstungsvorlagen | Fünf gespeicherte Reliktpresets pro Welt. |
| Kostenlose Truhen | Zehn blaue Öffnungen pro UTC-Tag mit Abstand; eine goldene alle 24 Stunden. |
| Truheninhalte | Serverseitige gewichtete Ziehungen; Truhen können als ungeöffnete Items vergeben werden. |
| Tägliche Aufgaben | Login, Bau, Ausbildung, Forschung, Sammeln, Monsterjagd und Truhen. |
| Erfolge und Ranglisten | Profilfortschritt und Vergleich öffentlicher Spielstatistiken. |
| Stadt-Skins | Auswahl und Darstellung vorhanden. |
| Allianzverwaltung | Gründen, suchen, beitreten, verlassen, Mitglieder verwalten und Leitung übertragen. |
| Rollen | Mitglieder, Veteranen, Offiziere, Stellvertretung und Leitung mit Berechtigungen. |
| Kommunikation | Weltchat, Allianzchat und private Post mit Antworten/Lesestatus. |
| Allianz-Hilfe | Bau- und Forschungszeiten gemeinsam verkürzen. |
| Allianzforschung | Gemeinsame Verbesserungen und Forschungsaufträge. |
| Bündniskasse | Spenden und geregelte Entnahme durch die Leitung. |
| Diplomatie | Beidseitig angenommene Bündnisse und Nichtangriffspakte. |
| Allianzgeschenke aus Jagd | Alter Trigger-/Claim-Service vorhanden; kein vollständiger Claim-Weg in der aktuellen Haupt-App gefunden. Admin-Geschenke sind ein anderes, angebundenes System. |
| Koalitionsfeldzüge | Zwei Allianzen, Einladungen, Versorgung, Schutzanlagen, Passverteidigung und gemeinsamer Boss. |
| Feldzugskatalog | Aschenfürst, Frostwächter und Dornenkönigin; normal, heroisch und legendär. |
| Feldzugsfortschritt | Beiträge, persönliche Ansprüche, Verlauf, Ablauf/Abbruch und Truppenrückgabe. |
| Dungeons | Sechs Themen, davon drei wöchentlich aktiv; Gruppen aus zwei bis vier Spielern. |
| Dungeonrollen | Angriff, Verteidigung, Sammler und Jäger mit echten Talent-/Truppenvoraussetzungen. |
| Dungeonentscheidungen | Zwei Schwierigkeiten, Haltung, optionale Seitenkammer und Abstimmung mit Frist. |
| Dungeonbelohnung | Persönliche Reliktfragmente und Itemchance, beanspruchbar nach Erfolg. |
| Weltereignisse | Konfigurierbare Conquest-Zyklen, Phasen, Haltepunkte, Rangliste und Abschlussbeute. |
| Weltinvasionen | Versorgung oder Armeen beitragen, gemeinsamer Fortschritt und Kapitelaufstieg. |
| Schreine und Kongress | Angriff, Garnison, Kontrolle und separate Ereignisregeln; vier regionale Schreine. |

Belege: [Lord-Talente](C:/xampp/htdocs/conquer/src/Game/Player/MasteryService.php), [Relikte](C:/xampp/htdocs/conquer/docs/TREASURES.md), [Inventar](C:/xampp/htdocs/conquer/data/items.json), [Gemeinschaft](C:/xampp/htdocs/conquer/src/Game/Community/CommunityService.php), [Feldzüge](C:/xampp/htdocs/conquer/src/Game/Expedition/EncounterCatalog.php), [Dungeons](C:/xampp/htdocs/conquer/docs/DUNGEONS.md).

**Bestandsaufnahme: Verwaltung und Betrieb**

| Funktion | Aktueller Stand |
|---|---|
| Admin-Anmeldung und Rollen | Separate Anmeldung; Moderatoren lesen, Superadmins ändern. |
| Weltenverwaltung | Anlegen, öffnen, pausieren, schließen und Spieltempo konfigurieren. |
| Spawnsteuerung | Getrennte Monster-/Rohstoffdichte, Wahrscheinlichkeiten, Typen, Stufen, Zeitfenster, Intervalle, Lebensdauer und Grenzen. |
| Spawnprotokolle | Letzter/nächster Lauf, Mengen und Platzierungsfehler. |
| Spielersuche | Name, Mail/ID, Welt und Filter. |
| Kontosperren | Sperren/Entsperren und Sitzungen widerrufen. |
| Spielstandkorrekturen | Ressourcen, Gebäude, Forschung, Garnison und Schutz korrigieren. |
| Geschenke der Spielleitung | Ressourcen, Edelsteine oder ein Itemtyp an Spieler oder Welt, mit Nachricht und Zustellbeleg. |
| Allianz-/Chatübersicht | Verwaltungsansichten vorhanden. |
| Änderungsprotokoll | Begründung, Berechtigungsprüfung und wiederholungssichere Vorgänge. |
| Hintergrundarbeiter | Spawns, Märsche, Expeditionen und Dungeons serverseitig abwickeln. Einrichtung auf dem Zielhost hier nicht geprüft. |
| Monster-Dropverwaltung | Noch keine Admin-Seite und keine gemeinsame wirksame Konfigurationsschicht. |
| Balanceanalyse | Lokale Tests/Benchmark-Werkzeuge vorhanden; keine vollständige Beuteökonomie-Auswertung im Backoffice gefunden. |

Belege: [Admin-Controller](C:/xampp/htdocs/conquer/src/Admin/AdminController.php), [Backoffice](C:/xampp/htdocs/conquer/docs/BACKOFFICE.md), [API-Routen](C:/xampp/htdocs/conquer/index.php).

**Konkrete Lücken und Widersprüche**

1. **Solo-Itemdrops werden nicht aus der allgemeinen Tabelle ausgewürfelt.** `MarchTick` zahlt `resource_reward` aus, erzeugt gegebenenfalls einen Charm und ruft für Goblins einen Sonderweg auf. Die allgemeinen `drops` und `gems_drop` normaler Solo-Monster werden dort nicht ausgewertet. Die Vorschau kann trotzdem diese Angaben zeigen.
2. **Rally-Drops kommen aus einer anderen Datei.** `MonsterRally::drops()` verwendet `data/rally_rewards.json`: Deathkar-, Drachen- und Magdar-Pools. Die drei Regionalbosse verwenden den Deathkar-Pool. Eine bloße Änderung an `monsters.json` würde deshalb nicht die erwartete Rally-Itembeute ändern.
3. **Alte Itemcodes passen nicht zum aktuellen Inventar.** Von 537 konfigurierten Monster-Dropslots verweisen 447 auf insgesamt 37 im aktuellen Itemkatalog fehlende Codes. Das ist eine Katalogprüfung, keine Aussage über 447 tatsächlich ausbezahlte falsche Drops.
4. **Goblins besitzen fest programmierte Belohnungen.** Der Code vergibt zufällig Item 10103011 oder 10103023 plus eine gesonderte Edelsteinchance. Laut aktuellem Inventarkatalog sind das eine Stunde Bauen oder acht Stunden Forschung; der Codekommentar spricht noch von 30 Minuten. Kommentare und Balanceabsicht sind hier veraltet.
5. **Charm-Regeln widersprechen sich.** Die alte Spezifikation und `charms.json` nennen 30 % sowie Aktivierung aus dem Inventar. Der aktive Spawner erzeugt bei drei Standardfamilien immer einen Karten-Charm; beim Einsammeln wird er sofort aktiviert. Deine neue Vorgabe lautet: jedes Monster hinterlässt einen Charm am eigenen Spawnort. Diese aktuelle Vorgabe soll für die Umsetzung gelten.
6. **Die neue Haupt-App hat keinen vollständigen Charm-Sammelweg.** `GameHandler` liefert keine Karten-Charms und `world-map.js` baut keine Charm-Ziele. Die alte Ansicht `views/map.php` enthält einen Sammeldialog, `/map` leitet aber vorher auf `/city#world` um. Der alte Kartenhandler enthält außerdem noch eine feste Welt-1-Abfrage für Charms.
7. **Rally-Siege erzeugen keinen Karten-Charm.** Im geprüften Rally-Abschluss fehlt der entsprechende Aufruf. Auch der Einsteiger-Ork mit Code 20209901 fällt durch die bisherige Familienprüfung.
8. **Monster-IDs haben historische Aliasregeln.** Bei zehn Ork-Spawncodes ersetzt `MonsterData` die direkte JSON-Zuordnung durch die passende Namens-/Stufendefinition. Die Verwaltung muss die im Spiel wirksame Stufe anzeigen und diese Zuordnung vereinheitlichen.
9. **Hospital-Kapazität ist keine fertige Spielregel.** Angezeigt wird 1.000 plus Boni; `addWounded()` begrenzt die Aufnahme nicht anhand dieser Kapazität. Eine stufenabhängige Kapazitäts-/Überlaufregel braucht eine bewusste Entscheidung, insbesondere beim PvE-Schwerpunkt.
10. **Einige Statusdokumente sind überholt.** Außenbauplätze sind deaktiviert, obwohl ihr Dokument einen aktiven Ausbau beschreibt. Die sechs älteren Meisterschaftszweige wurden vom vierteiligen Lord-Talentbaum abgelöst. Die älteren Hinweise auf fehlende Monster-Rallies und kaputte Charm-Bonusschlüssel sind dagegen bereits überholt.

Quellbelege: [Solo-Abrechnung](C:/xampp/htdocs/conquer/src/Game/March/MarchTick.php:260), [Goblin-Sonderweg](C:/xampp/htdocs/conquer/src/Game/March/MarchTick.php:612), [Rally-Beute](C:/xampp/htdocs/conquer/src/Game/Rally/MonsterRally.php:38), [Charm-Spawner](C:/xampp/htdocs/conquer/src/Game/Charm/CharmSpawner.php), [Charm-Sammlung](C:/xampp/htdocs/conquer/src/Game/March/MarchTick.php:384), [Karten-Daten](C:/xampp/htdocs/conquer/src/Api/Handlers/GameHandler.php), [Monster-Zuordnung](C:/xampp/htdocs/conquer/src/Game/Map/MonsterData.php), [Hospital](C:/xampp/htdocs/conquer/src/Game/Hospital/HospitalService.php).

**Erweiterungen zur Auswahl**

Die IDs dienen zur Rückmeldung, zum Beispiel „E03, E05 und E12 zuerst“. P1 = zuerst sinnvoll, P2 = danach, P3 = größerer optionaler Ausbau. Aufwand S/M/L ist relativ und keine Terminzusage. Die beiden A-Punkte sind bereits von dir beauftragt; die E-Punkte sind Vorschläge.

| ID | Funktion | Konkreter Nutzen | Priorität / Aufwand |
|---|---|---|---|
| A01 | Monster-Dropverwaltung | Alle Monster, gültige Items, Mengen, Chancen, Vorschau und tatsächlich gleiche Auszahlung. | Beauftragt / M–L |
| A02 | Charm bei jedem Monster | Genau ein Charm nach jedem Sieg am gespeicherten Spawnort; Truppen zum Einsammeln schicken. | Beauftragt / M–L |
| E01 | Bestiarium mit Fundorten | Monster, Stufe, Region, Beute und passende Jagdziele nachschlagen. | P1 / M |
| E02 | Itemsuche „Wo bekomme ich das?“ | Vom gewünschten Gegenstand zu Monstern, Truhen, Dungeons und Shops wechseln. | P1 / M |
| E03 | Geführte Einführung | Bau → Ausbildung → erster Kampf → Charm einsammeln → Allianzhilfe. | P1 / M |
| E04 | Hospital-Ausbauwirkung | Kapazität und Heilwirkung verständlich mit der Gebäudestufe entwickeln; Überlaufregel festlegen. | P1 / M |
| E05 | Allianz-Münzen und Allianzshop | Hilfe, Spenden und Gruppenaufgaben belohnen persönliche Mitwirkung. Shop als Genrebeispiel: [offizielle Kingshot-Teleporterhilfe](https://centurygames.helpshift.com/hc/en/140-kingshot/faq/9015-what-are-the-teleporters-available-in-the-game-what-is-the-difference-between-them-and-how-to-obtain-them/). | P1 / M |
| E06 | Allianzgeschenke fertig anbinden | Vorhandene Jagdgeschenke sichtbar machen und sicher einmalig abholen. | P1 / S–M |
| E07 | Weltweite Monstersuche | Art, Stufe, Biom und gewünschten Drop statt nur geladene Kartenziele suchen. | P1 / M |
| E08 | Wochenaufgaben | Längere Fortschrittsziele ergänzen tägliche Aufgaben. | P2 / M |
| E09 | Lord-Levelbelohnungen | Bei Meilensteinen zusätzlich zu Talentpunkten sichtbare Items oder Kosmetik. | P2 / S–M |
| E10 | VIP-/Truhen-Meilensteine | Freischaltungen und kostenlose Truhen bewusst an Gebäude/VIP koppeln. | P2 / M |
| E11 | Allianz-Auftragsbrett | Gemeinsame Wochenaufgaben für Versorgung, Jagd und Dungeons. | P1 / M |
| E12 | Allianzkalender und Anmeldung | Feldzüge planen, Rollen/Teilnehmer anmelden, mehrere geeignete Zeitfenster. | P1 / M |
| E13 | Kartenmarkierungen und Favoriten | Persönliche Fundorte und gemeinsame Allianz-Ziele mit Notizen. | P1 / M |
| E14 | Wachturm und Erkundung | Ausbaubarer Turm, gespeicherte Entdeckungen, Ruinen und Erkundungsbelohnungen. | P2 / L |
| E15 | Allianzgebiet und Außenposten | Hauptquartier, zusammenhängende Gebiete, gemeinsamer Ausbau und lokale Vorteile. | P2 / L |
| E16 | Regionale Entwicklung | PvE-Aktivität verbessert Infrastruktur und öffnet schwierigere regionale Inhalte. | P2 / L |
| E17 | Zusätzliche Bauplätze reaktivieren | Mehrere spezialisierte Produktionsgebäude/Kasernen; aktuell bewusst deaktiviert, nur bei erneutem Wunsch. | P3 / M–L |
| E18 | Einzelspieler-Abenteuer | Kurze Missionen, Erkundungsorte und kleine Bosse für geringe Serverbevölkerung. | P2 / L |
| E19 | Mehr Bossmechaniken | Verständliche Schwächen, Nebenaufgaben und wechselnde Phasen statt nur höherer Werte. | P2 / L |
| E20 | Dungeon-Gruppensuche | Offene Gruppen nach Schwierigkeit und Rolle finden; bestehende Gruppenfunktionen erweitern. | P2 / M |
| E21 | Gezielte Fragmentwahl | Seltene Beute ergänzend durch planbare Auswahlmarken oder gezielte Belohnungen. | P2 / M |
| E22 | Handwerk und Rezepte | Monster-Materialien zu ausgewählten Items verarbeiten. Neues System mit zusätzlicher Balancearbeit. | P3 / L |
| E23 | Freiwillige Allianzturniere | Angemeldete Wettbewerbe mit eigener Wertung und klaren Verlustrisiken. | P2 / L |
| E24 | PvE-Wettbewerbe | Boss-/Dungeonwertungen nach Schwierigkeit, Effizienz und begrenzten Versuchen. | P2 / M–L |
| E25 | Saisonale Geschichten und Kosmetik | Wiederkehrende Themen, Sammlungen und Trophäen ohne Stadtreset. | P2 / L |
| E26 | Helden oder Kommandanten | Individuelle Armeeführung und Fähigkeiten; großer zusätzlicher Fortschrittszweig. | P3 / L |
| E27 | Begleiter | Sammelbare Fantasytiere mit überschaubaren Rollen oder rein kosmetischer Wirkung. | P3 / L |
| E28 | Spielerhandel/Auktionshaus | Gezielter Austausch freigegebener Items statt nur Ressourcentransporten. | P3 / L |
| E29 | Opt-in-Benachrichtigungen | Hinweise auf fertige Aufträge, Gruppenstarts und Angriffe bei geschlossener App. | P2 / M |
| E30 | Vollständige Übersetzung | Auch Regeln, Berichte, Items und Hilfetexte in DE/FR/LU. | P2 / M–L |
| E31 | Rückkehrer-/Aufholaufträge | Nach Pausen wieder sinnvoll teilnehmen, ohne den vorhandenen Fortschritt zu entwerten. | P2 / M |
| E32 | Kontaktliste, Blockieren und Melden | Einfachere Zusammenarbeit und Moderation direkt aus Chat/Profil. | P2 / M |
| E33 | Beute- und Wirtschaftsstatistik | Tatsächliche Auszahlungen, Itemquellen und Verbrauch im Admin-Dashboard sehen. | P1 / M |
| E34 | Event- und Inhaltseditor | Nach den Drops auch Termine, Aufgaben und Belohnungen verwalten. | P2 / L |
| E35 | Weltübergreifende Wettbewerbe | Späterer Endgame-Ausbau, wenn mehrere aktive Welten tragen. | P3 / L |
| E36 | Bevölkerung und Versorgung | Gerettete Bewohner, Unterkünfte und Versorgung als spielbare Stadtmechanik, inspiriert vom Kingshot-Aufbau. | P3 / L |
| E37 | Allianz-Abwehrwellen | Ein angemeldetes PvE-Ereignis greift Mitgliedsstädte an; Verstärkung und Verteidigung bringen Beiträge und persönliche Beute. Vergleich: Viking Vengeance. | P2 / M–L |
| E38 | PvE-Prüfungspfad | Stufen mit einmaliger Erstabschlussbeute, Meilensteinen und später freischaltbarer verkürzter Wiederholung. Vergleich: Mystic Trial. | P2 / M–L |
| E39 | Allgemeine Allianzabstimmungen | Leitung/Offiziere erstellen Umfragen zu Terminen, Ausbau und Zielen; Mitglieder stimmen ab und sehen das Ergebnis. | P1 / S–M |
| E40 | Begrenzte Jagdserien | Spieler legt Zielstufen, maximale Angriffe und AP-Budget fest; Folgeaufträge stoppen bei erreichten Grenzen. Kein automatisches Charm-Einsammeln. | P3 / M–L |
| E41 | Ereignismarken und Ereignisshop | Verdiente Teilnahme-/Erfolgsmarken gegen ausgewählte Items, Fragmente oder Kosmetik tauschen; Limits und Verfall sichtbar festlegen. | P2 / M |
| E42 | Zusätzliche Herrscher-/Heldenausrüstung | Optionaler dauerhafter Ausrüstungszweig mit Verbesserungsmaterialien; Heldenausrüstung setzt E26 voraus. Abgrenzung zu vorhandenen Relikten nötig. | P3 / L |

E11–E15 und E23 orientieren sich an nachgewiesenen Allianz-/Erkundungsfunktionen der oben verlinkten Referenzen. Die genaue Ausgestaltung aller E-Punkte ist ein Conquer-Vorschlag. Helden, Begleiter, Auktionshaus und weltübergreifende Kämpfe sind für den nächsten Schritt nicht notwendig.

Die Auswahl umfasst nach dem ausführlichen Kingshot-Vergleich **42 Erweiterungsvorschläge (E01–E42)** plus die bereits beauftragten Pakete **A01/A02**. E36–E42 ergänzen die bestehende Liste; ihre Quellen und Abgrenzungen stehen im Kingshot-Vergleich oben. Auch die Reihenfolge und Begrenzungen bei E40 sind ein eigener Conquer-Vorschlag, keine behauptete vollständige Nachbildung von Kingshots Auto Hunting.

**Umfang der Monsterverwaltung**

Im Weltmonsterkatalog stehen **89 Definitionen in 13 Familien**. Das ist die Zahl der Definitionen einschließlich historischer Varianten, nicht die Zahl aktuell gespawnter Gegner. Der separate [Kataloganhang](C:/xampp/htdocs/conquer/docs/MONSTER_DROP_AUDIT_2026-09-12.md) führt jeden Eintrag mit ID, Stufe und heutigem Belohnungsweg auf.

| Familie | Definitionen / Stufen | Heutiger Charm-Spawn |
|---|---|---|
| Ork-Späher | 1 / Stufe 1 | Nein |
| Orc | 11 / Stufen 0–10; historische Spawn-Aliase beachten | Ja |
| Skeleton | 10 / Stufen 1–10 | Ja |
| Golem | 10 / Stufen 1–10 | Ja |
| Treasure Goblin | 5 / Stufen 1–5 | Nein |
| Deathkar | 10 / Stufen 1–10 | Nein |
| Green Dragon | 3 / Stufen 1–3 | Nein |
| Red Dragon | 3 / Stufen 1–3 | Nein |
| Gold Dragon | 3 / Stufen 1–3 | Nein |
| Magdar | 3 / Stufen 1–3 | Nein |
| Frostgrimm | 10 / Stufen 1–10 | Nein |
| Sandmaul | 10 / Stufen 1–10 | Nein |
| Glutramm | 10 / Stufen 1–10 | Nein |

Außerdem existieren drei Feldzugsbosse sowie sechs Dungeons mit je zwei regulären Begegnungen, einer optionalen Begegnung und einem Boss. Diese verwenden eigene Belohnungssysteme. Einige optionale Begegnungen sind Räume, keine benannten Monster. Die Admin-Oberfläche soll diese Kontexte sichtbar unterscheiden und nicht behaupten, der Weltmonsterkatalog decke sie bereits ab.

**Vorschlag für die Seite „Monster & Drops“**

Geplanter Einstieg: Admin-Menü → **Monster & Drops**. Folgende Funktionen gehören zum vorgesehenen Umsetzungspaket:

1. Vollständige Liste einschließlich nicht aktuell gespawnter Monster; Suche und Filter nach Familie, Stufe, Solo/Rally, Region und Begegnungskontext.
2. Detailansicht mit Bild, eindeutiger ID, tatsächlicher Spielstufe, Spawnbezug und wirksamer Regelversion.
3. Itemauswahl aus dem aktuellen Katalog; je Zeile Item, feste Menge oder Mengenbereich und Dropchance. Keine frei eingetippten unbekannten Itemcodes.
4. Gewöhnliche Ressourcen, Edelsteine, Inventaritems und Reliktfragmente als klar benannte Belohnungsarten.
5. Garantierte Beute und unabhängige Chancen getrennt von gewichteten „eine Auswahl aus diesem Pool“-Ziehungen. Unabhängige Chancen dürfen zusammen über 100 % ergeben; die Oberfläche muss die Regel erklären.
6. **Genau ein Karten-Charm ist bei jedem getöteten Monster verpflichtend.** Die Anzahl und der garantierte Spawn sind keine versehentlich abschaltbare Zufallszeile. Einstellbar sind Kategorie, Seltenheitsverteilung und gegebenenfalls deren Vererbung aus Familienregeln.
7. Regeln pro Monsterstufe; Familienvorlagen und Mehrfachbearbeitung mit Vorschau auf betroffene Monster.
8. Gemeinsame Grundregeln mit ausdrücklich sichtbaren Weltabweichungen, passend zur vorhandenen Weltenauswahl. Laufende Kämpfe behalten die beim Start gespeicherte Regelversion.
9. Beutevorschau für Spieler und Auszahlung lesen dieselben wirksamen Daten. Solojagd, Goblin-Sonderweg und Rally-Pools werden daran angeschlossen.
10. Probeauswertung ohne Gutschrift: garantierte Beute, durchschnittlicher Ertrag und simulierte Verteilung. Reale Spawns und Spielerguthaben werden dabei nicht verändert.
11. Speichern mit bestehender Superadmin-Prüfung, Änderungsgrund, Änderungsprotokoll und Schutz vor mehrfacher Ausführung/veralteten Formularen. Moderatoren behalten Lesezugriff.
12. Ungültige Altzuordnungen als konkrete Migrationsliste bearbeiten; keine stillen Ersatzitems. Später kann E02 daraus die Item-Fundortansicht aufbauen.

**Charm-Ablauf nach deiner Vorgabe**

Monster wird besiegt → genau ein Charm entsteht an den gespeicherten Monsterkoordinaten derselben Welt → der Spieler öffnet den Charm auf der Karte → wählt Truppen → schickt einen Sammelmarsch → die zuerst berechtigte ankommende Armee sammelt ihn einmalig ein → die Truppen kehren zurück.

Für normale Weltmonster und Rally-Bosse ist der Ort bereits vorhanden. Ein Boss erzeugt insgesamt einen Charm, nicht einen pro Rally-Teilnehmer. Gleichzeitige Siege, wiederholte Cron-Aufrufe und mehrere Sammelmärsche dürfen keine Kopien erzeugen. Ein inzwischen vergebener oder abgelaufener Charm muss die entsandten Truppen zuverlässig zurückschicken.

Die acht vorhandenen Kategorien können erhalten bleiben: Bau, Forschung, Truppen-LP, Angriff, Verteidigung, Traglast, Marschtempo und Sammeltempo. Aktuell gibt es normal (+3 %, 30 Minuten), episch (+5 %, zwei Stunden) und legendär (+10 %, vier Stunden). Die Kartenlebensdauer beträgt im bisherigen Spawner eine Stunde. Diese Werte sind Bestandswerte, noch keine neue Balanceentscheidung für sämtliche Bosse.

Für Feldzugs- und Dungeongegner fehlt derzeit ein individueller persistenter Weltkarten-Spawnort. Soll „jedes Monster“ auch diese Gegner umfassen, müssen sie einen gespeicherten Spawnpunkt auf der Weltkarte oder auf einer eigenen dauerhaft adressierbaren Begegnungskarte erhalten. Erst dort kann ein Charm wirklich am selben Ort entstehen und von Truppen erreicht werden. Ein beliebiger Ersatzort bei der Stadt würde deine Regel nicht erfüllen.

**Noch zu entscheidende Charm-Details**

| Entscheidung | Bestandsverhalten | Mein Vorschlag zur Durchsicht |
|---|---|---|
| Wer darf einsammeln? | Jeder, der einen gültigen Sammelmarsch zuerst ankommen lässt; kein Besitzer im Spawn gespeichert. | Kurze Reservierung für den Sieger bzw. die siegreiche Allianz, danach öffentlich. Die genaue Dauer festlegen. |
| Wann wirkt der Charm? | Sofort beim Einsammeln am Ziel. | Bestehenden Ablauf zunächst beibehalten; falls taktisches Aufsparen gewünscht ist, stattdessen Inventar und manuelle Aktivierung wählen. |
| Was passiert bei gleichem Bufftyp? | Neuer Charm überschreibt den alten, auch wenn er schwächer ist. | Schwächere Funde sollen einen stärkeren laufenden Effekt nicht unbemerkt verschlechtern. Verlängern oder speichern als klare Regel wählen. |
| Verfall während der Anreise? | Charm muss bei Ankunft noch gültig sein. | Restzeit und Ankunftszeit vor Abmarsch anzeigen; aussichtslose neue Entsendungen verhindern. |
| Geteilte Bossbeute? | Rally-Items proportional, Feldzugs-/Dungeonbeute persönlich. | Diese Unterschiede beibehalten; der eine physische Charm erhält eine separate Besitzregel. |
| Instanzgegner? | Kein individueller Weltkartenpunkt. | Persistente Spawnpunkte definieren, bevor Charms dieser Gegner an Truppen-Sammelmärsche angeschlossen werden. |

**Vorschlag für Item-Fundorte – noch keine beschlossene Droptabelle**

| Gegner/Quelle | Sinnvolle Spezialisierung |
|---|---|
| Einsteiger-Ork | Kleine Ressourcenpakete und sicherer erster Charm als erklärter Einstieg. |
| Orks | Nahrung/Holz sowie Ausbildungsbeschleuniger. |
| Skelette | Gold, Heilbeschleuniger und ausgewählte militärische Fragmente. |
| Golems | Stein sowie ausgewählte Produktions-/Verteidigungsfragmente. |
| Schatzgoblins | Bau-/Forschungsbeschleuniger, Edelsteine und zusätzlich der garantierte Charm. |
| Deathkar | Mittlere militärische Beute und ausgewählte epische Fragmente. |
| Frostgrimm | Verteidigungs-/Heilungsbeute. |
| Sandmaul | Sammel-, Traglast- und Marschbeute. |
| Glutramm | Angriffs- und Ausbildungsbeute. |
| Grüner Drache | Wirtschafts-/Produktionsrelikte. |
| Roter Drache | Angriffsrelikte. |
| Goldener Drache | Forschungs-/Entwicklungsrelikte. |
| Magdar | Seltene Endgamefragmente und hochwertige Gruppenbeute. |
| Feldzüge | Persönliche Erfolgsbeute passend zum Boss und Schwierigkeitsgrad. |
| Dungeons | Die bereits gezielt zugeordneten Reliktfragmente als klare Farmziele erhalten. |
| Truhen, Karawane und VIP-Handel | Ergänzende Zugangswege; sie bleiben Teil der gesamten Itemökonomie. |

Die konkrete Itemauswahl muss aus den 166 vorhandenen Items und 82 Relikten erfolgen. Chance und Menge sollten anhand von AP-Kosten, durchschnittlicher Kampfdauer, Truppenrisiko und gewünschtem Fortschritt abgestimmt werden. Eine pauschale Aktivierung der alten Monster-Dropslots wäre wegen ungültiger IDs und veralteter Balancewerte ungeeignet.

**Empfohlene nächste Arbeitspakete**

Zuerst A01/A02 mit gültigen Katalogzuordnungen, gemeinsamer Beuteauswertung und tatsächlichem Karten-Sammelweg. Danach bevorzugt E01/E02/E03/E05/E06/E12/E13: Fundorte verstehen, Einstieg abschließen und die vorhandene Zusammenarbeit erleichtern. Größere neue Entwicklungszweige erst nach diesen verbindenden Funktionen.

Für die spätere, von dir gewünschte Umsetzung mit Agenten bietet sich folgende Aufteilung an: ein Agent für Beutedaten und Abrechnung, ein Agent für die Admin-Seite und ein Agent für Karten-Charms und Sammeldialog; der Hauptagent integriert Datenverträge, Migrationen und Prüfungen. Gemeinsame Dateien werden gezielt zugewiesen. Die Agentenarbeit wurde in dieser Recherchephase noch nicht gestartet.

Zur Abnahme gehören insbesondere: sämtliche Monsterfamilien, identische Vorschau/Auszahlung, garantierter Einzel-Charm am exakten Ort, gleichzeitige Kills/Sammler, Verfall, Abbruch/Rückkehr, Welttrennung, Rally-Verteilung, Berechtigungen und Admin-Änderungen während laufender Aufträge. Sichtbare Änderungen müssen den bestehenden UI-/3D-Regeln entsprechen und in Desktop-, Handy- und Querformat sowie in `/city#city` geprüft werden.
