# Conquer: kooperativer PvE-MVP

Aktuelle Erweiterung vom 11. September 2026: [Spielsysteme und Backoffice](FEATURE_PACK_STATUS.md). Die nachfolgenden Angaben beschreiben den früheren MVP.

Stand: 10. September 2026. Dieser Stand ersetzt die ältere Beschreibung des reinen Einsteiger-MVP in `MVP_STATUS.md`. Conquer bleibt der Arbeitstitel.

## Spielumfang

Die gemeinsame Spieloberfläche verbindet die vorhandene 3D-Stadt mit Weltkarte, Truppen, Forschung und den neuen kooperativen Funktionen. Der Fortschritt liegt weiterhin in MySQL; Spielregeln und Auszahlungen werden serverseitig ausgeführt.

- **Königreich:** dreidimensionale Stadt, alle 14 bestehenden Gebäudetypen, Kosten, Voraussetzungen, Ausbau und laufende Aufträge.
- **Weltkarte:** verschiebbare Karte, Monster, Rohstofffelder, Märsche und öffentliche Spielerprofile. Städte belegen 3 × 3 Felder. Solo-Angriffe und Allianz-Rallies sind gegen ungeschützte fremde Städte möglich; eigene Allianz, Schutzschilde und Anfängerschutz werden serverseitig geprüft.
- **Feldzüge:** wiederholbare Aschenfürst-Expedition mit zwei Allianzen, Einladungen, Vorräten, Schutzanlagen, Passverteidigung, gemeinsamem Boss, Beiträgen, Ereignisverlauf und persönlicher Beute.
- **Allianz:** gründen, finden und beitreten, Mitglieder und Rollen, Beschreibung, Spenden, Führungswechsel und Austritt mit Berechtigungsprüfung. Die aktuelle Allianzführung kann Vorräte aus der Bündniskasse in die eigene Stadt entnehmen und anschließend beispielsweise für Feldzüge verwenden. Dauerhafte Vorgangsbelege verhindern doppelte Entnahmen bei wiederholten Anfragen.
- **Truppen und Hospital:** Ausbildung mit Voraussetzungen und Kosten, Warteschlange sowie automatisch heilende Verwundete. Normale PvE-Verwundungen gehen ins Hospital; der erste Koalitionsboss verursacht keine dauerhaften Truppenverluste.
- **Forschung:** Voraussetzungen, dauerhafte Verbesserungen und echte Forschungsaufträge.
- **Inventar und Relikte:** Ressourcenpakete, Beschleuniger, Truhen mit tatsächlichem Inhalt sowie anlegbare Relikte. Seltenheitsabhängige Fragmentanforderungen gelten gleichermaßen für Darstellung und Ausrüstung. In diesem MVP nicht verwendete Nebenwerte werden als solche erläutert.
- **Aufgaben:** täglicher Fortschritt aus tatsächlich abgeschlossenen Bau-, Ausbildungs-, Forschungs- und Sammelaufträgen, Monsterkämpfen und verwendeten Truhen; Belohnungen nur einmal.
- **Profil:** separates Anzeigename-Feld, vordefinierte Porträts, Beschreibung, Spielstatistiken, Erfolge und öffentliche Ansicht anderer Spieler. Der Anmeldename bleibt unverändert.
- **Rangliste und Arena:** Vergleich öffentlicher Spielstatistiken und ausdrücklich angenommene Duelle. Arenakämpfe verbrauchen und vernichten keine realen Armeen.
- **Markt:** acht feste Ressourcentausch-Angebote mit sichtbaren Preisen, echter Abbuchung, Lieferung und Verlauf.
- **Berichte, Optionen und Spielhilfe:** Kampfergebnisse, gespeicherte Einstellungen und Erklärung der kooperativen Spielabläufe.

## Einstieg und gemeinsamer Bosskampf

1. Unter `http://localhost/conquer/` anmelden oder ein Königreich gründen. Truppen ausbilden, Gebäude entwickeln und die täglichen Aufgaben kennenlernen.
2. Zwei Spieler gründen unterschiedliche Allianzen; weitere Teilnehmer können einer dieser Allianzen beitreten.
3. Eine Allianzleitung erstellt unter **Feldzüge** eine Expedition und lädt die andere Allianz ein. Deren Leitung nimmt an.
4. Vorräte liefern und die für die eigene Allianz vorgesehenen Aufgaben mit realen Truppen erfüllen. Die Schutzanlagen und der Pass müssen beide gesichert werden.
5. Anschließend den gemeinsamen Boss angreifen. Erledigte Marschaufträge, verbleibende Lebenspunkte und Beiträge werden gespeichert. Nach dem Sieg erhält jeder berechtigte Teilnehmer seine eigene Beute.

Der erste Boss nutzt kurze, echte Marschzeiten von jeweils 20 Sekunden für Hin- und Rückweg. Eine Expedition hat ein Zeitfenster von 24 Stunden. Belohnungen pro Spieler sind zusätzlich zeitlich begrenzt, damit ein Allianzwechsel keine zusätzlichen Auszahlungen erzeugt. Die konkreten Regeln liefert die Expedition-Schnittstelle an die Oberfläche.

Ein Ausschluss oder Austritt aus einer Allianz löscht weder bereits entsandte Truppen noch verdiente persönliche Belohnungen. Neue Befehle sind nur bei aktueller Teilnahmeberechtigung möglich. Abbruch, Ablauf und wiederholtes Polling geben Truppen höchstens einmal zurück.

## Lokaler Betrieb

Apache und MySQL in XAMPP müssen laufen. Vorhandene Konfigurationsdateien bleiben maßgeblich.

```text
C:\xampp\php\php.exe migrations\run.php
```

Die Migrationen `0055` bis `0058` ergänzen Expeditionen, Profile/Arena, Marktverlauf und Belege für Kassenentnahmen. Der Runner kann wiederholt aufgerufen werden.

Browseraufrufe gleichen fällige Aufträge ab. Für eine unabhängige Serververarbeitung steht derselbe Ablauf als CLI-Worker bereit:

```text
C:\xampp\php\php.exe cron\march_tick.php
C:\xampp\php\php.exe cron\expedition_tick.php
```

Auf einem gehosteten Server sollte `cron/march_tick.php` regelmäßig ausgeführt werden. Es wurde im Rahmen dieser Arbeit keine Produktionsbereitstellung vorgenommen.

## Prüfungen

Erfolgreich ausgeführte Serverprüfungen:

```text
C:\xampp\php\php.exe tests\mvp_rules.php
C:\xampp\php\php.exe tests\mvp_smoke.php http://localhost/conquer
C:\xampp\php\php.exe tests\kingdom_smoke.php http://localhost/conquer
C:\xampp\php\php.exe tests\kingdom_regressions.php
C:\xampp\php\php.exe tests\kingdom_regressions.php --balances-only
C:\xampp\php\php.exe tests\kingdom_treasury.php http://localhost/conquer
C:\xampp\php\php.exe tests\expedition_lifecycle.php
C:\xampp\php\php.exe tests\expedition_lifecycle.php --http
C:\xampp\php\php.exe tests\pve_integration.php http://localhost/conquer
```

Der klassische MVP-Test prüft Bauen, Forschung, Ausbildung, Kämpfe, Sammeln und erneutes Anmelden mit echten Wartezeiten. Die Expeditionstests verwenden automatisch bereinigte, separate Testdatenbanken; der HTTP-Durchlauf nutzt den tatsächlichen Frontcontroller und echte Marschzeiten. Die Prüfungen enthalten konkurrierende Angriffe, Auszahlungen, Truppenreservierungen und Allianzaustritte während laufender Einsätze.

`pve_integration.php` hat 33 Integrationsprüfungen bestanden. Es verändert nur ein neu registriertes Prüfkönigreich. Zwei dessen Marschzeiten werden gezielt in die Vergangenheit gesetzt, um die Rückkehr nach längerer Abwesenheit zu prüfen. Dieser Fall ergänzt die Prüfungen mit echten Wartezeiten. HTTP-Tests mit der normalen lokalen Installation hinterlassen klar benannte Testkonten. Der gemeinsame Anmelde-Limiter bleibt auch während Tests aktiv.

**Oberflächenprüfung abgeschlossen:** Desktop mit 1440 × 960 Pixeln, Tablet mit 768 × 1024 Pixeln, Smartphone mit 390 × 844 Pixeln und zusätzliche Prüfung bei 320 × 740 Pixeln. Alle 15 Hauptbereiche wurden geöffnet. Nach Korrektur der schmalen Kopfzeile besteht kein horizontaler Seitenüberlauf; Weltkarte und untere Menüleiste besitzen bewusst eigene Scrollbereiche. Die finale Oberfläche verwendet die Cacheversion `pve10`. Das Browserprotokoll enthielt im abschließenden Durchlauf keine Fehler oder Warnungen.

Im Browser tatsächlich ausgeführt und kontrolliert:

- Anzeigename, Porträt und Profiltext gespeichert; nach Neuladen weiter vorhanden. Eigene Guthaben werden gezeigt, fremde Profile enthalten diese privaten Guthaben nicht.
- Bewegungsoption gespeichert; die eingebettete Stadt übernimmt sie. Profilfenster mit Escape geschlossen und Fokus auf den Auslöser zurückgegeben. Eine ungespeicherte Allianzbeschreibung blieb über Hintergrundaktualisierungen erhalten.
- Nahrung-/Holzpakete verbraucht, 100 Schwertkämpfer ausgebildet und mit einem vorhandenen Beschleuniger fertiggestellt, Truhe geöffnet sowie ein Relikt in einen freien Platz ausgerüstet.
- Marktpreise bestätigt, Steine gekauft, Burg direkt im 3D-Modell ausgewählt und mit echter Bauzeit auf Stufe 2 ausgebaut. Forschung abgeschlossen; der Produktionsbonus erschien anschließend im Ressourcenfenster.
- Normales Monster besiegt, einen Sammelzug beendet, den Kampfbericht geöffnet und die resultierenden Bestände kontrolliert.
- Feldzug #3 mit zwei eigenen QA-Allianzen vollständig absolviert: Gastgeber über die Oberfläche, Gast über dieselben lokalen HTTP-Schnittstellen. Alle Truppen wurden regulär ausgebildet; für diesen Durchlauf gab es weder Truppen-Seeds noch veränderte Timer. Vorräte, Schutzanlagen, Pass, Boss und beide persönlichen Auszahlungen funktionierten. Auch der ausschließlich unterstützende Gast erhielt seine Beute.
- Freiwilliges Arenaduell #7 von der mobilen Oberfläche angefragt und durch das zweite QA-Konto angenommen. Ergebnis und Bericht sichtbar, Garnison unverändert.
- 100 Nahrung in die eigene QA-Bündniskasse gespendet; anschließend 60 mit Bestätigung entnommen. 40 Nahrung blieben in der Kasse, 60 wurden der Stadt gutgeschrieben.

Die ergänzenden Kassenprüfungen kontrollieren insbesondere Mitgliedsrechte, Führungswechsel, gefälschte Ziel-IDs, unzureichende Bestände und wiederholte Vorgangskennungen. PHP-/JavaScript-Syntaxprüfungen und `git diff --check` bestanden ebenfalls.

## Wichtige Integrationsentscheidungen

- Die bestehende Architektur aus PHP, PDO, MySQL und direktem JavaScript bleibt erhalten.
- Die neue Hauptoberfläche liegt in `views/game.php`. Die 3D-Stadt wird unter `/city/3d?embed=1` eingebettet und spricht mit dem Hauptfenster ausschließlich über geprüfte Nachrichten desselben Ursprungs.
- Die neuen Schnittstellen heißen `/api/kingdom/*`, `/api/expeditions/*` und `/api/market/*`; bestehende Kernaktionen für Bauen, Forschen, Ausbildung und Märsche werden weiterverwendet.
- Ältere Ansichten führen zur gemeinsamen Oberfläche. Veraltete Mutationswege für ungetestete Zusatzmodule sind stillgelegt, damit sie Berechtigungen und Belohnungsregeln nicht umgehen können.
- Verdiente Ressourcen oberhalb der Lagergrenze bleiben erhalten; normale Produktion erhöht einen bereits vollen Vorrat nicht weiter.
- Bereits abgelaufene Hin- und Rückreisen werden beim nächsten Aufruf vollständig abgeglichen, ohne eine zusätzliche Online-Wartezeit zu verlangen.
- Die Referenzen und die neue Bossillustration sind in `PVE_ART_PROVENANCE.md` dokumentiert.

Der MVP enthält einen vollständig spielbaren Koalitionsboss und freiwillige Arenaduelle. Weitere Bossfamilien, dynamische Weltinvasionen und große Serververanstaltungen sind Erweiterungen dieses Fundaments. Die lokalen Funktionstests sind kein Lasttest einer mit Tausenden Spielern bevölkerten Welt.

## Frühere Überarbeitung von Märschen und Inventar · 10. September 2026

Hinweis: Der damalige Ausschnitt von 15 Forschungen und vereinfachte Entwicklungsvoraussetzungen wurden durch den vollständigen Katalog ersetzt; siehe aktuellen Stand unten.

- Sammeln und Monsterangriffe öffnen eine gemeinsame Armeeauswahl mit Zielvorschau, Regler und Mengenfeld je Truppentyp, Max je Typ, gemeinsamem Max und Leeren. Der gemeinsame Max verteilt bis zu 50.000 verfügbare Einheiten proportional; bereits entsandte Truppen fehlen im verfügbaren Bestand. Die Anzeige aktualisiert sich während eines offenen Fensters, ohne Eingaben zu überschreiben.
- Beide Serverrouten übernehmen die angegebene Komposition exakt. Unbekannte Einheiten, Dezimalzahlen, negative Mengen, überhöhte Bestände und mehr als 50.000 Truppen werden abgewiesen; Reservierungen sind atomar. Der bisherige Sammel-Payload mit `troop_count` bleibt kompatibel.
- Das Inventar verwendet kompakte, illustrierte Kacheln mit Menge und Dauer, getrennte Kategorien, Reliktqualität und Ausrüstungsplätze. 37 lokale Itemillustrationen sind in `assets/art/items/README.md` dokumentiert; die vom Nutzer gelieferten PNGs werden unverändert verwendet.
- Der Forschungsbaum enthält 15 Technologien in Wirtschaft, Militär und Entwicklung, echte Verbindungslinien, Voraussetzungen, Fortschritt sowie laufende und abgeschlossene Zustände. Alle drei militärischen Pfade sind vollständig sichtbar: Lebenspunkte → Verteidigung → Angriff.
- Der MVP-Entwicklungspfad führt nun von Goldproduktion Stufe 2 zu Wissensdurst (Akademie 3–5) und von Wissensdurst Stufe 2 zu Flotten Baumeistern (Akademie 5–7). Damit hängt der sichtbare Baum nicht mehr von ausgeblendeter Kristall- und Traglastforschung ab. Kosten, Dauer und Boni dieser Technologien bleiben erhalten. Der Server prüft die genauen Voraussetzungen der angeforderten Stufe.
- Inventar, Forschung und Weltkarte bekommen die volle Inhaltsbreite. Auf Mobilgeräten ersetzt eine Navigation mit Königreich, Weltkarte, Feldzügen, Allianz und Mehr die lange Leiste; alle 15 Spielbereiche bleiben erreichbar.
- Die eingebundenen Oberflächenassets tragen den Cache-Stand `pve11`; Inventarscript und -stylesheet stehen auf `pve12`. Beschleuniger zeigen die Dauer groß direkt im Icon, mit typabhängigen Farben: Universell gold, Bauen orange, Forschung violett, Ausbildung blau, Heilung grün. Zeitangaben im Raster und Detailfenster wurden bis 320 Pixel Breite ohne Textüberlauf geprüft.

Geprüft: `tests/march_composition.php` (isolierte Datenbank, echte HTTP-Routen, gemischte T1/T2-Armeen, Rückkehr, 50.000-Grenze und Legacy-Payload) und `tests/research_requirements.php` (20 HTTP-/Datenprüfungen einschließlich vollständigem Entwicklungspfad). Temporäre Testdaten werden bereinigt. PHP-/JavaScript-Syntax und `git diff --check` bestanden.

Browserprüfung mit dem eigenen QA-Königreich bei 1440 × 960, 390 × 844 und 320 × 740: Max/Leeren, einzelner Max, Überschreitung des Bestands, gemischter Sammelzug (2/3/4 Einheiten), gemischter Monsterangriff (10/1/1) mit passendem Kampfbericht, Relikt ab-/ausrüsten, Forschung starten und durch einen tatsächlich verbrauchten Inventargegenstand abschließen. Menü, Itemdetails und Bestätigung im schmalen Marschfenster sind erreichbar; kein horizontaler Seitenüberlauf und keine Browserfehler in den geprüften Ansichten.

## Vollständige Forschung und erste Spieloberfläche · 10. September 2026

- Der Client lädt alle 129 Technologien mit 963 Stufen aus den drei Forschungsdateien: Wirtschaft 34, Militär 55, Fortgeschritten 40. Suche über alle Bereiche, Teilzweige mit ihren Voraussetzungen, echte Abhängigkeitslinien, deutsche Namen und ein Sprung zur gesuchten Technologie sind enthalten.
- Die ursprünglichen Akademie- und Forschungsvoraussetzungen von Wissensdurst und Flotten Baumeistern sind wiederhergestellt. Beide `resource_protect`-Einträge bleiben erhalten: Der Produktionsknoten heißt nun `production_resource_protect`, der bisher gespeicherte fortgeschrittene Schlüssel behält seine Bedeutung.
- Die Kaserne zeigt alle 15 Einheiten über fünf Stufen. Höhere Stufen erfordern ihre Forschung; Kosten, Dauer und Ausbildungslimit verwenden die Forschungsboni. Normale Märsche berücksichtigen erforschte Kapazität, zusätzliche Plätze, Geschwindigkeit und Traglast. Lagerforschung erhöht die tatsächliche Produktionsgrenze.
- Wirtschaftliche Basis-/Fortgeschrittenenboni werden auf dieselben Spielwerte angewandt. Zusammensetzungsboni wirken in Kämpfen, Konterboni in der einvernehmlichen Arena und Sammelangriffsboni bei Feldzügen. Forschungsabhängige Feldzugkapazität und Reisezeiten stimmen zwischen Anzeige und gespeicherter Mission überein. Stadtverteidigungs- und Vorratsschutzdetails erklären den derzeitigen Schutz der Spielerstädte; der Einführungsfeldzug bleibt verlustfrei.
- Holz-HUD, Schieferflächen, Bronzerahmen, goldene Aktionen und gezeichnete Navigationsembleme geben Menüs, Profilen, Aufgaben und Fenstern einen gemeinsamen Spielstil. Das Inventar behält seine klaren Dauerlabels und Typfarben. Doppelte Überschriften in Forschung und Inventar entfallen.

Neue/erweiterte Prüfungen: `research_catalog.js`, `research_effects.php`, `research_combat.php`, `research_live_services.php`, `research_requirements.php`, `march_composition.php` und `mvp_rules.php`. Der Feldzug-Lebenszyklus einschließlich konkurrierender Aktionen ist ebenfalls geprüft. Die Dienst- und Marschtests verwenden eigene anschließend gelöschte Testdatenbanken; die Voraussetzungstests bereinigen ihren eigenen Testspieler.

Weltkarte: Der Atlas zeichnet Gelände mit Wald, Hügeln, Wasser und Wegen sowie eigene SVG-Illustrationen für Städte und Rohstoffplätze. Echte Ziele sind über Filter, Suche, Zielkarte und eine Liste nahegelegener Orte erreichbar. Zoom, Ziehen, Tastatur, Koordinatensprung, Heimkehr und Mini-Karte erhalten den Kamerazustand bei Spielaktualisierungen. Ausgewählte Armeen erscheinen mit Hin-/Rückweg; Ressourcenvorräte, Belegung und Marschplätze folgen dem aktuellen Spielstand. Die Serverabfrage unterstützt einen Sichtbereich von 12–60 Feldern, bis zu 180 Monster und 180 Ressourcenfelder sowie 120 Städte. Besiegte Späher blockieren die regelmäßige Auffüllung nicht mehr.

Browser-QA: Desktop 1440×960, Forschung/Inventar 390×844 und 320×740, Weltkarte zusätzlich 320×740. Geprüft wurden vollständige Forschungsbereiche, Suche und Fokus auf Freischaltungen direkt aus der Kaserne, Icon-Voraussetzungen, Menü-/Itemdarstellung, Kartenfilter und -suche, Zoom, Koordinaten/Tastatur, bleibende Kamera nach Aktualisierung und ein echter Sammelzug mit 3 Schwertkämpfern, 2 Bogenschützen und 1 Reiter im eigenen Testkönigreich (60 Vorräte). Eigene Atlas-UI-Fixtures prüfen zusätzlich Ziehen, Terrain-Stabilität, mobile Zielkarten und Marsch-/Rückkehrdarstellung. Änderungen an Tests betreffen automatisch bereinigte Testdaten oder das eigene QA-Königreich.

Abschluss: Asset-Cache `pve19`. Die mobile Zielliste bleibt auch nach vorheriger Zielauswahl sichtbar. Kartenmarker besitzen separate, auf Kartenfelder begrenzte Trefferflächen, damit große Burgillustrationen keine benachbarten Ziele abfangen. Bei 320×740 sind Koordinaten, Ziellistenknopf, Mini-Karte, Footer und Navigation erreichbar; Marschbestätigung ist per Tastatur und Fensterscroll erreichbar. Finale Browserprüfung ohne Warnungen, Fehler, fehlende Bilder oder horizontalen Seitenüberlauf; temporäre Größenänderung zurückgesetzt.

## Mobile Oberfläche nach den Kartenreferenzen · 10. September 2026

Die nachfolgende Anordnung ersetzt die zuvor beschriebene Holz-Kopfleiste und permanente Kartenbereiche. Maßgeblich waren die gelieferten Bilder `map overlay.png`, `mapoverlay.png`, `Overlay_TOP.png` und `overlay_villageview.png` sowie die markierte Bildschirmaufnahme des Nutzers.

- Stadt und Weltkarte füllen das Fenster. Logo, linke Navigation, große Kartenüberschrift und rechte Informationsspalte sind entfernt. Das Profil sitzt links oben; vier kleine Rohstoffanzeigen liegen direkt über der Spielwelt. Auf schmalen Geräten stehen die Rohstoffe in einer eigenen Zeile unter dem Profil.
- Unten bleiben genau fünf illustrierte Tasten: Aufgaben, Inventar, Post, Allianz und Stadt/Welt. Sie verwenden eigene SVG-Illustrationen auf blauen, goldgerahmten Schaltflächen. Feldzüge und Spielmenü haben zwei kleine zusätzliche Tasten. Das Profil öffnet sich direkt über den Avatar.
- Das Zusatzmenü enthält sieben weitere Ziele: Truppen, Forschung, Markt, Rangliste, Arena, Optionen und Spielhilfe. Alle 15 Spielbereiche bleiben erreichbar. Zurück und Schließen führen zur zuletzt genutzten Spielwelt.
- Suche und Filter erscheinen erst über die Schriftrolle; Koordinatensprung, Mini-Karte und Zoom über die Koordinatenanzeige. Ein angetipptes Ziel öffnet eine kleine Karte mit echter Sammel-, Angriffs- oder Profilaktion. Schließen und Escape geben die Spielfläche wieder frei. In flachen Querformaten erhalten Kartenfenster die verfügbare Bildschirmhöhe und verdecken das HUD vorübergehend.
- Die vorhandene Max-Auswahl und frei wählbare Truppenkomposition bleiben angebunden. Große Gebäudeillustrationen haben weiterhin kleine, getrennte Trefferflächen. Ressourcen im Produktionsgebäudefenster verwenden den richtigen Ressourcenschlüssel; die Synchronisationsanzeige erzeugt keinen zusätzlichen Seitenrand.

Browserprüfung mit dem eigenen QA-Königreich: Desktop, 390×844, 320×740 und 844×390. Geprüft wurden fünf Haupttasten, Zusatzmenü, Profil und Rückkehr, Stadt-Iframe, Forschungseinstieg, Rohstofffilter, Zielauswahl, Max und individuelle Truppenzahlen, Koordinatensprung, Zoom und Heimkehr. Kein horizontaler Seitenüberlauf; Stadt und Welt haben die genaue Bildschirmhöhe. Die geprüften Ansichten zeigten keine fehlenden Bilder oder Browserwarnungen/-fehler. Separate Atlas-UI-Fixtures prüfen zusätzlich Ziehen, benachbarte Ziele, Fokus und erhaltene Kamera bei Aktualisierungen.

Asset-Cache: `overlay5`, eingebettete Stadt `overlay1`. PHP-/JavaScript-Syntax sowie `git diff --check` geprüft. Die Geschäftsregeln und der vollständige Forschungskatalog wurden bei dieser Layoutkorrektur nicht verändert.

## Spielbereiche als Popups · 10. September 2026

- Alle 13 Bereiche außerhalb von Stadt und Weltkarte öffnen sich im eigenen nativen Bereichsfenster. Die Spielwelt bleibt darunter eingebunden, einschließlich Kartenposition und Stadt-Iframe. Ein sichtbarer Rand, abgedunkelter Hintergrund, feste Titelleiste und innen scrollbarer Inhalt ersetzen die bisherigen Seitenansichten.
- Item-, Forschungs- und andere Detailfenster liegen über dem jeweiligen Bereich. Schließen und Escape kehren schrittweise zurück; ein Bereichswechsel über Browser-Zurück schließt auch den zugehörigen Detaildialog. Fokus kehrt zur auslösenden Taste zurück. Forschungsfreischaltungen springen auch innerhalb eines schmalen Popups sichtbar zum passenden Knoten.
- Aktionsmeldungen werden in den obersten offenen Dialog versetzt, damit Erfolgs- und Fehlerrückmeldungen nicht hinter dessen Browser-Ebene verschwinden.
- Angriff und Sammeln orientieren sich an `MAP/Attack_monster_screen.png`, `MAP/Attack_DK_screen.png` und `PVE_SOlO_ATTACK_VIEW.png`: links Ziel, Lebenspunkte/Vorrat und vorhandene Ressourcenbelohnungen, mittig kompakte Regler und Mengenfelder, rechts ausgewählte Portraitkarten, Kapazität und Marschplätze. Leeren, Max und die rote Angriffs- beziehungsweise grüne Sammeltaste bleiben erreichbar. Auf Mobilgeräten scrollt der Inhalt innerhalb des Fensters bei fester Fußleiste.
- Die vorhandene Truppenverteilung, individuelle Zusammensetzung, Forschungsboni, Bestands- und Kapazitätsprüfungen sowie Server-Payloads bleiben erhalten.

Geprüft: alle 13 Bereichsfenster im Browser, verschachtelte Item-/Forschungsdetails, Browser-Zurück, Forschungssprung aus gesperrten Truppen, Stadt als Hintergrund und unverändertes Speichern der eigenen QA-Einstellungen mit sichtbarer Meldung im Popup. Layoutprüfung bei 1280×800, 390×844 und 320×740. Separate vollständige CSS-/DOM-Fixtures prüfen die Marschfenster zusätzlich bei 1440×900, 320×740 und 568×320, einschließlich gemischtem Payload, Max, Einzel-Max, Reglern, fehlenden Zielen, belegten Feldern, Bestandsänderungen und ungültigen Mengen. Keine Spielregeländerungen. Asset-Cache `popup4`; PHP-/JavaScript-Syntax und `git diff --check` bestanden.

## Früherer Stand: Kompakte Ansichten und Forschungskapitel · 10. September 2026

- Forschung ersetzt den großen scrollbaren Baum durch 19 feste Kapitel: sieben Wirtschaft, acht Militär und vier Fortgeschritten. Alle 129 Technologien mit 963 Stufen bleiben enthalten. Infanterie, Schützen und Reiterei behalten feste Reihen; Lebenspunkte, Verteidigung und Angriff sind aufeinanderfolgende Spalten. Echte Voraussetzungen bestimmen Linien, Sperren und Icon-Karten im Detailfenster.
- Je nach verfügbarer Fenstergröße erscheinen zwei oder drei Spalten und höchstens drei Reihen. Kapitelwahl und Vor-/Zurück-Tasten erschließen den gesamten Baum ohne Scrollfläche. Die globale Suche verwendet dieselben Seiten und führt direkt zum richtigen Kapitel. Sucheingaben bleiben bei Größenänderungen erhalten; Tastaturfokus folgt Kapitel- und Seitenwechseln.
- Angriff und Sammeln zeigen kompakte Zielinformationen, drei editierbare Truppenreihen und eine kurze Armeeübersicht. Weitere Truppenstufen haben eigene Seiten; Max und der Versand berücksichtigen weiterhin alle 15 Einheiten. Im kurzen Querformat wechseln echte Truppen-/Ziel-Reiter zwischen den Ansichten.
- Inventar und Relikte verwenden einen Kategoriewähler sowie sechs beziehungsweise drei Einträge pro Seite. Dauerlabels und Typfarben der Beschleuniger bleiben erhalten. Profil trennt Reich, Fortschritt, Erfolge und Konto; Aufgaben und Erfolge sind paginiert. Sehr niedrige Bildschirme erhalten zusätzliche Profilteilseiten. Die reguläre Truppenansicht zeigt drei kompakte Einheiten pro Stufe.

Prüfungen: alle 13 Checks in `tests/research_catalog.js` bestanden, einschließlich eindeutiger Erreichbarkeit sämtlicher Technologien, originaler Voraussetzungen und vollständiger Suchergebnisse. Isolierte Browser-Fixtures prüfen 216 Forschungsseiten auf vier Fenstergrößen, 72 Inventar-/Profil-/Aufgabenfälle sowie die Marschkomposition mit allen 15 Einheiten und gemeinsamem Max. Gemessen wurden Inhaltsüberlauf und die vollständige Sichtbarkeit von Kartenicons und Stufenbalken.

Zusätzliche Liveprüfung im eigenen QA-Königreich bei 1280×800, 390×844, 320×740, 320×568 und 568×320: Forschungskapitel, Suche und Fokus, vier Icon-Voraussetzungen, Max und individuell geänderte Truppenmengen, Inventarseiten, Ausbildung, Profil und Aufgaben. Auch alle acht tatsächlichen Tagesaufgaben passen im kurzen Querformat, einschließlich zweizeiliger Belohnungen. Keine Spielaktionen ausgelöst und keine Browserfehler/-warnungen in dieser Prüfung. Keine Änderungen an Forschungsdaten oder Spielregeln. Asset-Cache `compact6`.

### Einheitlicher Popupstil nach den Originalbildern

`popup-surfaces.css` und `popup-skin.css` ersetzen innerhalb der Fenster die alten olivgrünen Flächen, Pergamentkarten, Materialtexturen und Serifenschriften. Die Fenster haben einen hellen Rahmen/Kopf, eine abgeschrägte Navy-Titelkappe, flache Schieferflächen, dunkelblaue Einsätze und kräftige blaue Schaltflächen. Indigo markiert aktive Tabs; Aktions- und Itemfarben behalten ihre Bedeutung. Die Gestaltung gilt auch für Profil, Aufgaben, Allianz und die Kartenpopups. Detaildialoge erhalten über `openDialog()` denselben Kopf; Inhalte, Formfelder und Fokus bleiben erhalten. Marschfenster behalten ihre integrierte Kopfzeile und Komposition.

Prüfung: 72 vorhandene Inventar-/Profil-/Aufgaben-Layoutfälle mit den neuen Styles bestanden; Marschfixtures bei 320×740, 390×844 und 568×320 einschließlich aller 15 Truppentypen, Max, freien Mengen und Versandpayload bestanden. Live geprüft wurden Forschungsfenster/-details, Profil, Inventar und Itemdetails samt Fokus-Rückkehr sowie Karten-/Sammelfenster. Asset-Cache `style5`.


## Mobile Überarbeitung: feste Fenster, Dorf und Weltkarte

- Alle großen Panel-, Detail- und Marschfenster verwenden dieselbe responsive Breite und Höhe (`window-layout.css`). Untermenüs ändern die Fenstergröße nicht. Listen werden nach Bildschirmhöhe in Seiten aufgeteilt; Forschungen sind horizontal wischbar.
- Forschung: 129 Technologien, 963 Stufen, 59 neue thematische SVG-Illustrationen. Voraussetzungen zeigen passende Icons. Kurze Querformate ordnen Kosten und Voraussetzungen nebeneinander an.
- Inventar: sichtbare Kategorien, Dauer direkt im Icon, silberne/blaue/violette/orange Rahmen nach Wert statt Truppentyp. Bau/Forschung/Ausbildung/Heilung haben eigene Zusatzsymbole.
- Aufgaben trennen offene, abholbereite und abgeholte Einträge. Post zeigt kompakte Listen und separate Details. Allianz trennt Überblick, Mitglieder, Kasse und Verwaltung; die Kasse hat Einzahlen/Entnehmen als Untermenüs.
- Dorf: umliegende Waldgruppen und Hügel, Felsufer, Wassergraben, Bogenbrücke, fünf Wohnhöfe und zusätzliche Gebäudeteile. Touch-Pan folgt der Kamera-Bodenprojektion und funktioniert auch ab Gebäudelabels; ein Swipe löst keinen Gebäudeklick aus.
- Eigene Stadt öffnet in Dorf und Weltkarte dasselbe Menü: Profil, Skins, Koordinaten teilen. Die drei kostenlosen kosmetischen Stile werden über `skin.save` im Konto gespeichert (Migration 0060). Der 3D-Client klont nur Gebäudematerialien.
- Fremde Stadt: Profil, Solo-Angriff, Rally, Koordinaten teilen. Beide Angriffsarten verwenden die vorhandene Truppenauswahl inklusive Max und Mengen pro Typ. Allianz-Rallies sind über die Allianzansicht erreichbar; Beitreten, vorzeitig starten und abbrechen berücksichtigen Berechtigungen.
- Die Karte hat ein sichtbares quadratisches Raster und auswählbare Leerfelder. Monster/Rohstoffe sind 1 × 1, Dörfer 3 × 3. Märsche zeigen Gruppen der tatsächlich entsandten Infanterie, Bogenschützen und Kavallerie.
- Koordinaten teilen verwendet die native Teilenfunktion oder die Zwischenablage. Bei unverschlüsseltem LAN-Zugriff ohne Clipboard-API wird ein auswählbares Textfeld angeboten.

Prüfung: Forschung auf sechs Bildschirmgrößen einschließlich 320 × 568 und 568 × 320; Panel-Fixtures mit vollen Listen, langen Namen, leeren Zuständen und Itemfarben; echte Touch-Ereignisse für Dorf/Forschung; serverseitige Persistenz- und Validierungsprüfungen für Skins. Stadt-/Rally-Kampftests verwenden eine separate Wegwerf-Datenbank.


### Nachweise dieser Überarbeitung

- `tests/window_panels.cjs`: 325 Ansichten und 16 Itemtests; keine abgeschnittenen Controls, fehlenden Bilder oder Layoutüberläufe.
- `tests/research_catalog.js`: 16 Prüfungen, einschließlich vollständiger Katalogabdeckung und Swipe-/Klick-Abgrenzung.
- `tests/city3d_landscape.js --browser`: 18 Prüfungen einschließlich echter Touch-/Pinch-/Label-Gesten und pausierter verdeckter WebGL-Ansicht.
- `tests/march_windows.cjs`: 118 bestandene isolierte Fenster- und Payloadprüfungen mit allen 15 Truppentypen, Max, Seitenwechseln und ungültigen Mengen.
- `tests/village_menus.js`: 12 UI-Vertragsprüfungen für eigene/fremde Dörfer, Skins, LAN-Teilen sowie Rally-Führung/Teilnahme.
- `tests/city_combat.php`: 63 Service-/HTTP-/Konkurrenzprüfungen in separater Wegwerf-Datenbank.
- `tests/kingdom_regressions.php`: Skinspeicherung/Validierung, öffentliche/private Profilwerte und bestehende Quest-/Truppenabrechnung bestanden.

Bestehende prozedurale Ziele innerhalb einer Dorfgrundfläche werden nur ohne laufende Reise oder Sammlung in ein freies Nachbarfeld versetzt. ID, Vorrat und HP bleiben erhalten. Ein Ziel mit aktiver Reise bleibt bis deren Ende an seiner Position; danach wird die Fläche bereinigt. Neu erzeugte Ziele berücksichtigen die 3 × 3-Grundfläche sofort.

Nach PvP sind Ressourcenstände parallel veränderlich. Produktion liest unter Stadtsperre einen frischen Datenbankstand; Bau, Ausbildung und Forschung buchen nur ab, wenn die aktuellen Bestände noch reichen. Wiederholte Rally-Ticks und Abbrüche führen zu höchstens einer Abrechnung/Rückgabe.


## Vollständige Forschungsbäume ohne Seiten · 12. September 2026

Die Haupt-App erhielt durch eine alte Auswahl in `GameHandler` nur zehn Forschungsdefinitionen (sechs Wirtschaft, vier Militär, keine fortgeschrittenen). Sie liefert jetzt alle 129 Forschungen und 963 Stufen: Wirtschaft 34/245, Militär 55/318 und Fortgeschritten 40/400. Die Forschungsqueue wird wie die erforschten Stufen nach der aktiven Welt gefiltert.

Die Seitenaufteilung vom 10. September entfällt. Jeder Tab enthält einen durchgehenden horizontal scrollbaren Baum mit Abschnittsüberschriften und echten Voraussetzungslinien. Die drei Reihen bleiben in allen Bildschirmgrößen vorhanden; kurze Bildschirme können auch vertikal scrollen. Die globale Suche zeigt alle Treffer und springt direkt zur Forschung im passenden Tab. Polling bewahrt beide Scrollpositionen und den Tastaturfokus. Forschungsassets verwenden `filemtime` zur Cache-Aktualisierung.

Prüfungen: `tests/research_snapshot.php` kontrolliert den vollständigen echten HTTP-Spielstand bereits bei Akademie Stufe 1, alle 963 erreichbaren Upgrades und die Weltenzuordnung in einer separaten Testdatenbank. `tests/research_catalog.js` sichert Katalog, Voraussetzungen, Illustrationen, Zustände, alle Tabs und vollständige Suchergebnisse ab. `tests/research_app.cjs` prüft die echte Haupt-App bei 1280×800, 390×844, 320×568, 844×390 und 568×320 einschließlich aller Knoten, Wischgesten, Details und Serveraktualisierung.
