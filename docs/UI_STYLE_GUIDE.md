# Conquer – verbindlicher Stil für Menüs und Weltkarte

Stand: 13. September 2026. Diese Referenz gilt für alle Spielmenüs, Dialoge, HUD-Elemente, Karten-Overlays, eingebetteten Gebäudeaktionen, Anmeldung und das Backoffice. Verbindlich ist die vom Nutzer gewählte Variante A „Violett & warmes Beige“ in `assets/art/ui-violet-beige-reference.png`, mit Almendra für Texte und Lora für Zahlen. Die Spielwelt folgt weiterhin den Formregeln in `docs/ART_DIRECTION.md`.

## Gemeinsame Wirkung

Eine glaubwürdige, gezeichnete Fantasywelt: dunkelviolette Fensterköpfe, warme beigefarbene Flächen, helle Karten, pflaumenfarbene Rahmen und dezente Goldkanten. Weiche Ecken und große, gut erkennbare Symbole halten die Oberfläche ruhig. Zwei breite Helligkeitsstufen und eine schmale Schattenkante reichen für Tiefe aus. Almendra gibt Texten ihren märchenhaften Charakter; Zahlen bleiben mit Lora klar lesbar.

Die Talentübersicht ist ein Anwendungsbeispiel. Die zentrale technische Referenz für alle Oberflächen ist **`assets/css/village-theme.css`**. Sie wird nach den Funktions-Stylesheets geladen. Layouts und Spielregeln bleiben bei den jeweiligen Modulen; Farben und gemeinsame Materialien kommen aus dieser Datei.

## Palette und CSS-Variablen

| Einsatz | Variable | Farbe |
|---|---|---|
| Fenster und Seiten | `--ui-paper` | `#E9DFCF` |
| Karten und neutrale Knöpfe | `--ui-card` | `#FBF6EC` |
| Eingabefelder | `--ui-card-light` | `#FFFCF6` |
| Eingelassene Bereiche und ausgewählte Reiter | `--ui-inset` | `#DFCDA9` |
| Gesperrte Flächen | `--ui-disabled` | `#DDD2C0` |
| Haupttext | `--ui-ink` | `#443549` |
| Ergänzende Texte | `--ui-muted` | `#5F5261` |
| Karten- und Feldränder | `--ui-line` | `#C7B692` |
| Äußerer Fensterrahmen | `--ui-frame` | `#756080` |
| Fensterkopf, Navigation, Hauptakzent | `--ui-primary` | `#5C4270` |
| Helle violette Akzente | `--ui-primary-light` | `#80658F` |
| Violette Schattenkante und Tastaturfokus | `--ui-primary-dark` | `#443052` |
| Verteidigung und sachlich blaue Zustände | `--ui-blue` | `#2A72C9` |
| Helle blaue Rollenakzente | `--ui-blue-light` | `#438FE4` |
| Dunkle blaue Rollenakzente | `--ui-blue-dark` | `#24559C` |
| Bestätigen, Speichern, Sammeln | `--ui-green` | `#586D50` |
| Positive Texte und grüne Ränder | `--ui-green-dark` | `#3F523A` |
| Erfolg und gelernte Inhalte | `--ui-green-soft` | `#E3EADC` |
| Goldkanten, Käufe, Belohnungen, vollständige Talente | `--ui-gold` | `#C5A361` |
| Goldene Flächen | `--ui-gold-soft` | `#F1E2BD` |
| Edelsteinkäufe und warme Akzente | `--ui-orange` | `#ED8A31` |
| Angriff, Fehler, gefährliche Aktion | `--ui-red` | `#B43C34` |
| Fehlende Rohstoffe und rote Hinweistexte | `--ui-red-dark` | `#85342B` |
| Fehlerhinweise | `--ui-red-soft` | `#FAE5D8` |
| Magie, Akademie, seltene Inhalte | `--ui-purple` | `#8C4AC4` |

## Bausteine

- **Fenster:** 2–3 px pflaumenfarbener Rahmen, 16 px Eckenradius, warmer Beigegrund. Dunkelvioletter Kopf mit hellem Titel und schmaler Goldkante. Schließen ist ein heller, abgerundeter Knopf.
- **Karten:** heller Grund, 1–2 px warmer Rand, 12 px Radius. Keine metallischen Einsätze oder schwarzen Innenflächen.
- **Knöpfe:** 9 px Radius, klare Beschriftung, dezente untere Schattenkante. Neutral cremefarben, ausgewählte Inhaltsreiter dunkler beige mit Goldkante und dunkler Schrift; kompakte Navigationszustände dürfen Violett verwenden. Bestätigende Aktionen bleiben grün. Rot ausschließlich mit passender Bedeutung. Kaufpreis und Währung bleiben sichtbar.
- **Reiter:** gleiche Form wie Knöpfe. Auswahl zeigt Farbe und einen klaren Zustand (`aria-pressed`, aktive Klasse). Keine schräg abgeschnittenen Registerkarten.
- **Texte:** `--ui-font` verwendet die lokale Verbundfamilie `Conquer UI`: Almendra für Buchstaben, Lora für Ziffern. Für reine Zahlenfelder und Zeit-/Wertetexte gibt es zusätzlich `--ui-font-number`. Die Aufteilung erfolgt über `@font-face` und `unicode-range`, ohne Textknoten umzuschreiben. Dunkle Pflaumenschrift auf hellen Flächen; helle Schrift auf violetten, grünen und roten Knöpfen. Überschriften in normaler Schreibweise. Keine schwarzen Konturschatten auf Menütexten. Schriftgewichte, Umlaute, Akzente und Zahlen bei schmalen Ansichten prüfen.
- **Kontrast:** Vordergrund und Hintergrund immer gemeinsam setzen. Normale Beschriftungen sollen mindestens 4,5:1 erreichen, auch bei fehlenden Rohstoffen und gesperrten Aktionen. Deaktivierte Knöpfe verwenden `--ui-disabled` und lesbare Schrift statt verringerter Deckkraft. Bei unbesessenen Relikten wird nur das Bild gedämpft; Bestand und Stufe bleiben deutlich. Zahlen auf Gegenstandsbildern erhalten eine deckende dunkle Unterlage. Gebäudeköpfe sind violett mit heller Schrift, die Zielstufe steht dunkel auf Goldbeige.
- **Eingaben:** sehr heller Grund, warmer Rand, sichtbare Beschriftung. Platzhalter ersetzen kein Label. Tastaturfokus ist eine 3 px violette Umrandung.
- **Fortschritt:** warmer heller Hintergrund, grüner Füllstand. Werte stehen zusätzlich als Text dabei.
- **Bedienung:** primäre Knöpfe ungefähr 40–44 px hoch; kompakte Rangknöpfe mindestens 32 px. Wichtige Aktionen bleiben erreichbar. Inhalte dürfen scrollen, ohne seitlich aus dem Fenster zu ragen.
- **Symbole:** große, weiche Silhouetten, dunkelbraune Konturen, dieselben Dorf-Farben. Vorhandene Seltenheitsfarben und Gegenstandsillustrationen bleiben als Information erhalten; sie bestimmen nicht die Farbe des ganzen Fensters.

Die vier Talentbereiche dürfen ihre Rollenfarben tragen: Angriff rot, Verteidigung blau, Sammler grün und Jäger orangebraun. Zustände müssen auch durch Rang, Text, Symbol oder gesperrte Bedienung verständlich sein. Violett als Menüakzent ersetzt keine Seltenheitsfarben, blauen Verteidigungswerte oder Freund/Feind-Markierungen. Die Schriftdateien liegen mit ihren Lizenzen unter `assets/fonts/`; ihre Einbindung in `assets/css/fantasy-fonts.css` benötigt keine externen Dienste.

### Aufgabenlisten

Aufgaben erscheinen als kompakte Zeilen in einer einzigen, nach unten scrollbaren Liste. Keine Seitennummern oder Blätterknöpfe. Oben bleiben Fensterkopf, die Filter „Aufgaben“, „Abholbereit“ und „Abgeholt“ sowie der Tagesstatus stehen. Jede Zeile zeigt Name, Ziel, Zahlenfortschritt, Belohnungen und rechts „Zeigen“ oder „Abholen“. Die Symbole bleiben klein; eine zusätzliche große Symbolspalte entfällt. Im Handyformat dürfen Texte und Belohnungen umbrechen, die Aktion bleibt rechts erreichbar. Abgeholte Aufgaben erhalten einen lesbaren Status statt eines Aktionsknopfes. Serveraktualisierungen bewahren Scrollposition und Tastaturfokus; ein Filterwechsel beginnt oben. Die Referenzprüfung ist `tests/window_panels.cjs`.

Der Aufgaben-Button steht in der gemeinsamen Dorf-/Welt-Navigation über „Allianz“. Sein goldener Zähler nennt die fertigen, noch nicht abgeholten Aufgaben und entfällt bei null. Der Filter „Aufgaben“ enthält alle nicht abgeholten Aufgaben, abholbereite stehen immer zuerst. Im kurzen Querformat stehen die fünf Navigationsknöpfe nebeneinander; Chat und Ereigniswerkzeuge bleiben frei. `tests/quests_app.cjs` prüft Navigation, Belohnungsabholung, Zähleraktualisierung und die Bildschirmgrößen in einer isolierten Haupt-App.

### Truppenausbildung

Die Ausbildung verwendet eine feste Bühne ohne horizontales oder vertikales Scrollen: oben die große Figur mit Einheitenname, Gebäudestufe und kompakten Werkzeugen; darunter fünf Rangporträts, Kosten, Mengenregler und Trainingsaktion. Vor-/Zurück-Knöpfe wechseln zwischen T1–T5 und T6–T10. Die drei Gebäudereiter stehen fest unten. Im Querformat liegt die Figur neben den Bedienelementen. Die Ansicht „Werte“ ersetzt den Eingabebereich durch sieben Balkenwerte. Beim Öffnen sowie beim Wechsel von Gebäude oder Truppenstufe ist automatisch die maximal leistbare Menge ausgewählt; eine danach manuell geänderte Menge bleibt während laufender Serveraktualisierungen erhalten. Während eines Auftrags stehen Restzeit und Beschleunigung anstelle des Eingabeformulars; Freischaltung und unbestätigte Aufträge haben jeweils einen eigenen kompakten Zustand. Ressourcen behalten ihre Mangelfarbe, Seltenheiten ihre Rangfarben. Gekürzte Rohstoffzahlen öffnen per Tipp die genauen Werte. Prüfungen: `tests/training_layout.cjs` und `tests/training_app.cjs`.

### Hospital

Das Hospital zeigt eine einzige Bettenübersicht, darunter eine scrollbare Liste der verwundeten Truppen. Jede Zeile enthält Porträt, Truppenname, Tier, Verwundetenanzahl, Mengenregler und Zahlenfeld. Doppelte Armeestatistiken und sachfremde Aktionen entfallen. Die feste Fußleiste zeigt ausgewählte Anzahl, Ressourcenkosten mit Vorrat, Heilzeit und „Heilen“. „Alle auswählen“ wechselt bei vollständiger Auswahl zu „Auswahl leeren“. Ressourcenmangel und leere Auswahl deaktivieren die Heilung.

Verwundete warten auf den bezahlten Start. „Heilen“ bezahlt je nach Tier 10, 12, 15, 18, 22, 27, 32, 37, 42 oder 45 % der Ausbildungskosten pro Truppe und startet einen gemeinsamen Auftrag mit den Heilzeiten des Truppenkatalogs und aktiven Boni. Während der Behandlung stehen Restzeit und „Beschleunigen“ unten. Heilungs- und allgemeine Speedups verkürzen diesen Auftrag; die Auswahl bietet Gegenstand, Anzahl und verbleibende Zeit. Kristallaktionen entfallen. Die Basisheilzeiten betragen für T1 bis T10 jeweils 1, 1, 1, 2, 2, 3, 4, 5, 6 und 8 Sekunden je Truppe; laufende Behandlungen behalten ihre Endzeit. Kristalle sind für den VIP-Shop und Skins vorgesehen; direkte Kristallheilung bleibt ausgeschlossen (siehe `CRYSTAL_ECONOMY.md`). Neue Verwundete verändern einen laufenden Auftrag nicht. Preis und Bestände werden serverseitig geprüft, wiederholte Vorgänge nur einmal verrechnet. Serverupdates bewahren Auswahl und Listenposition. Im kurzen Querformat stehen Bettenübersicht und Heilaktion links neben der scrollbaren Liste. Prüfungen: `tests/hospital_healing.php` und `tests/hospital_app.cjs` mit dem isolierten Vorschauparameter `--hospital`.

### Inventar

Fünf Reiter gliedern Rohstoffe, Beschleuniger, Boni, Relikte und Sonstiges. Das Raster scrollt senkrecht neben den Gegenstandsdetails; auf schmalen Handys stehen vier Kacheln nebeneinander und die Details darunter. Paketwert oder Dauer stehen oben, der Bestand unten rechts. Breite Seltenheitsflächen, weiche Ränder und große Icons machen die Gegenstände erkennbar. Nicht besessene Katalogitems zeigen 0 und eine gedämpfte Darstellung, behalten jedoch ihre Seltenheitsfarbe.

Ein Tipp wählt den Gegenstand aus und zeigt Bild, Bestand, Beschreibung und Aktion direkt im Inventar. Die Auswahl erhält einen violetten Rahmen und `aria-pressed`; es öffnet sich kein zusätzliches Detailfenster. Der erste Gegenstand eines Bereichs ist automatisch ausgewählt. Raster und Details scrollen unabhängig; Auswahl und Position bleiben bei Serverupdates erhalten. Im Desktop- und Querformat stehen die Details rechts, bis 539 px Breite unter dem Raster. Das gilt auch für die Reliktsammlung. Beschleuniger zeigen die passende Auftragsauswahl direkt im Detailbereich; erst die ausdrückliche Aktion „Beschleunigen“ führt zur gemeinsamen Mengenauswahl. Fenster und Reiter verwenden die gemeinsamen Beige-, Violett- und Goldtöne. Prüfungen: `tests/window_panels.cjs`, `tests/inventory_app.cjs` und `tests/queue_speedups_inventory_app.cjs`.

Der Diagramm-Button im Inventarkopf öffnet die Übersicht „Rohstoffe & Beschleuniger“. Zwei feste Reiter stehen über einer scrollbaren Tabelle mit Icons und abwechselnden Elfenbeinflächen. Rohstoffe zeigen Paketinhalt und aktuellen Vorrat getrennt; Beschleuniger zeigen die Gesamtdauer je Einsatzbereich. Die drei Zeitdarstellungen Tage, Stunden und Minuten sind gegenseitig ausschließende Schaltflächen mit sichtbarer Auswahl. Tabellenkopf und Fußleiste bleiben beim Scrollen erreichbar. Serverupdates bewahren Auswahl und Scrollposition; Schließen, Escape und Zurück schließen nur die Übersicht. Prüfungen: `tests/inventory_overview.cjs` und `tests/inventory_overview_app.cjs`.

Bei verwendbaren Stapeln steht „Alle benutzen“ neben der Einzelaktion. Die angezeigte Menge bezieht sich auf den ausgewählten Itemtyp. Rohstoffpakete, VIP-Punkte, Boni, Truhen sowie Ressourcen- und Fragmentpakete werden vollständig in einem serverseitigen Vorgang verbraucht. Bei Aktionspunkten und Beschleunigern erklärt ein sichtbarer Hinweis, dass nur bis zur vollen Leiste beziehungsweise zum Auftragsabschluss verbraucht wird. Teleporter bleiben Einzelaktionen; Materialien werden weiterhin durch ihren vorgesehenen Spielablauf verbraucht. Doppelklicks und Wiederholungen nach Verbindungsabbruch dürfen keinen zweiten Verbrauch auslösen. Prüfungen: `tests/inventory_bulk.php`, `tests/inventory_app.cjs` und `tests/queue_speedups_inventory_app.cjs`.

### VIP-Shop

Der VIP-Shop öffnet mit „Für dich“ und zeigt ausschließlich bereits freigeschaltete Angebote; ausverkaufte Wochenangebote bleiben darin sichtbar. „Alle Stufen“ macht den vollständigen Referenzkatalog zugänglich. Beide Ansichten gruppieren Karten nach VIP-Stufe. Eine zweite, horizontal wischbare Filterzeile gliedert Alle, Rohstoffe, Beschleuniger, Boni, VIP & Energie sowie Truhen & Relikte. Aktive Filter verwenden Violett und Gold und besitzen `aria-pressed`; Anzahl, aktueller VIP-Rang und Wochenablauf bleiben außerhalb der scrollbaren Kartenliste sichtbar. Filterwechsel beginnen oben, Käufe und Serveraktualisierungen bewahren dagegen den gewählten Filter und die Listenposition. Im Handyformat bleiben zwei Karten nebeneinander, im kurzen Querformat vier. Sämtliche 52 Angebote bleiben über „Alle Stufen“ erreichbar. Prüfung: `tests/trading_panel.cjs`.

### Schatzkammer und Beute

Die Reliktsammlung teilt sich das Fenster mit der Ausrüstung. Ihre Kachelgröße richtet sich deshalb nach der tatsächlich verfügbaren Spaltenbreite. Kleine Hochformate verwenden zwei Reliktspalten; die antippbaren Kacheln bleiben mindestens 44 × 44 px groß. Bild, Stufe und Fragmentanzahl dürfen einander nicht verdecken. Reiter und Vorlagenaktionen sind im Hochformat 40 px hoch, im sehr kurzen Querformat mindestens 32 px. Seltenheiten behalten ihre eigenen Farben, die aktive Auswahl verwendet den gemeinsamen violetten Fokus.

Nach einer bestätigten Truhenöffnung zeigt ein kompaktes Ergebnisfenster die tatsächlichen Gegenstände mit Katalogbild, Namen und Menge. Die Liste scrollt bei vielen Belohnungen; Titel, Schließen und „Weiter“ bleiben sichtbar. Keine zusätzliche Erfolgsmeldung darf den Kopf überdecken. Bei einer verlorenen Antwort wird derselbe Vorgang erneut geprüft, bevor ein weiterer Verbrauch erlaubt wird. Prüfungen: `tests/reward_dialog_app.cjs`, `tests/reward_catalog.cjs`, `tests/inventory_rewards.php` und `tests/treasure_panel.cjs`.

### Postfach

Die Post verwendet sechs Reiter: Krieg, Allianz, System, Berichte, Favoriten und Privat. Unter Privat stehen Posteingang und Gesendet zur Verfügung. Kompakte Nachrichtenzeilen zeigen ein kleines Dorfsymbol und genau drei Textzeilen: Titel, Nachrichtenart und Sendezeit. Inhaltsvorschauen entfallen. Ein goldener Punkt kennzeichnet ausschließlich noch abholbare Belohnungen; ungelesene Post behält den blauen Rand und Favoriten ihren Stern. Rote Zähler bedeuten ungelesen, goldene Favoritenzähler gespeicherte Nachrichten. Die Liste scrollt und lädt weitere Nachrichten nach; Reiter und Sammelaktionen bleiben stehen. Alle sechs Reiter stehen auch bei 320/390 px und im kurzen Querformat nebeneinander in einer Reihe. Unten stehen „Gelesene löschen“, „Alle lesen“ und „Alle einsammeln“. Lesen und Einsammeln sind getrennte Aktionen; Einsammeln erfasst auch gelesene Post mit offener Belohnung. Sammelaktionen gelten für den gewählten Bereich, Favoriten und offene Belohnungen bleiben beim Löschen erhalten. Details: `docs/MAILBOX.md`; Prüfungen: `tests/mailbox.php` und `tests/mailbox_app.cjs`.

### Kampfberichte

Spieler- und Monsterberichte verwenden dieselben `.cr-*`-Bausteine aus `combat-report.css`: kompakte violette Kopfzeile mit Ort und Zeit, Ergebnis über den zwei Parteien, gestreifte Vergleichstabelle, Beute und Truppenvergleich. Angreifer stehen links mit rotem, Verteidiger bzw. Monster rechts mit blauem Akzent. Monsterberichte folgen als langer, vertikal scrollbarer Ablauf der Referenz: Beute, Truppenaufstellung, Kampfstärke, Erfahrung, Hunter-Meisterschaft, Relikte und aktive Kampfboni. Die letzten drei Bereiche sind beim Öffnen sichtbar, bleiben aber einzeln einklappbar. Die feste Fußleiste bietet jederzeit erreichbar den neueren Bericht, „Kampfdetails“, „Teilen“, „Kopieren“ und den älteren Bericht. Teilen sendet eine kompakte Zusammenfassung wahlweise in Allianzchat, Weltchat oder privat an einen gewählten Spieler. Die Chatnachricht zeigt zusätzlich „Bericht ansehen“ und öffnet antippbar denselben vollständigen Bericht. Der Server prüft beim Öffnen erneut Welt, Allianz beziehungsweise private Gesprächspartner; eine erratene Berichtsnummer reicht nicht als Zugriff. Das Detailfenster enthält die einzelnen Truppenverluste. Monsterberichte ergänzen die historischen Rest-HP und den Lieferstatus ihrer Beute. Prüfungen: `tests/combat_report_app.cjs`, `tests/monster_report_app.cjs`, `tests/world_chat.php`, `tests/world_chat_app.cjs` und `tests/community_panel.cjs`.

### Forschungsbäume

Wirtschaft, Militär und Fortgeschritten zeigen jeweils den vollständigen Forschungsbaum von oben nach unten in einer ausschließlich vertikal scrollbaren Fläche. Bis zu drei Technologien stehen nebeneinander; gemeinsame Vorstufen stehen mittig, echte Voraussetzungen verbinden die Symbole mit verzweigten Linien. Große Illustrationen mit Stufenbalken und Namen bilden antippbare Knoten auf Elfenbein. Abschnittsüberschriften gliedern den durchgehenden Baum. Keine Seitennummern, Vor-/Zurück-Tasten oder Kapitelwahl. Alle drei Spalten bleiben auch auf 320 px Breite und im kurzen Querformat sichtbar, ohne seitliches Scrollen. Gesperrte und fortgeschrittene Forschungen bleiben sichtbar. Die Tabzahlen nennen den vollständigen Bestand: 34, 43 und 40 Forschungen.

Verbindungslinien verlaufen durchgehend mit weich gerundeten Abzweigungen und einem dezenten hellen Rand. Offene Voraussetzungen sind sandfarben, erfüllte grün. Kleine helle Anschlusspunkte führen an die Symbole; Linien beginnen unterhalb der Namen und überlagern keine Beschriftungen. Verzweigungen einer Reihe bleiben auch bei unterschiedlich langen Namen auf derselben Höhe.

Fensterkopf, Reiter und Suche bleiben erreichbar. Die Suche durchsucht alle drei Bereiche und zeigt sämtliche Treffer ohne Seitenaufteilung. Ein Treffer öffnet den richtigen Tab und scrollt die Forschung in den sichtbaren Bereich. Native vertikale Wischgesten und Tastaturnavigation bleiben erhalten; Serverupdates bewahren Scrollposition und Fokus. Die Haupt-App muss sämtliche 117 Definitionen mit 951 Stufen liefern. Prüfungen: `tests/research_catalog.js`, `tests/research_snapshot.php` und `tests/research_app.cjs`.

### Laufende Aufträge im Dorf-HUD

Bau, Forschung, Ausbildung und Heilung verwenden dieselbe Beschleunigeransicht. Sie zeigt nur passende besessene Gegenstände als auswählbare Kacheln. Eine passende Menge wird vorausgewählt: möglichst viel Zeitverkürzung ohne Überhang, sofern der Bestand dies erlaubt. „Nur 1“, „Passend“ und „Fertigstellen“ ändern ausschließlich die angezeigte Menge; erst „N Beschleuniger verwenden“ verbraucht sie in einem Vorgang. „QuickUse“ ermittelt über alle passenden Bestände eine Kombination, die den Auftrag mit möglichst wenig Überhang beendet, schont bei gleichwertigen Kombinationen universelle Beschleuniger und bestätigt die nötigen Verwendungen automatisch. Anzahl, Restzeit danach und gegebenenfalls verfallende Zeit stehen vor der manuellen Aktion sichtbar dabei. Wenn ausschließlich längere Gegenstände vorhanden sind, bleibt die verfallende Restdauer klar erkennbar.

Das Fenster bleibt nach der Verwendung offen und aktualisiert Bestand und Restzeit. Die Gegenstandsliste scrollt, während Mengenwahl und Aktion stehen bleiben; kurzes Querformat ordnet beide nebeneinander an. Der Zahlenblock erhält beim Öffnen keinen automatischen Fokus, damit auf Handys keine unnötige Tastatur erscheint. Bei einer schmalen Ansicht über der geöffneten Tastatur wird das gesamte Formular scrollbar, sodass Vorschau und Verbrauchsaktion erreichbar bleiben. Der Inventareinstieg führt bei einem passenden Auftrag direkt zur Auswahl; bei mehreren Aufträgen wird zunächst das Ziel gewählt. Leerer Bestand und abgeschlossene Aufträge werden ausdrücklich angezeigt. Unbestätigte Antworten bleiben als genau derselbe Vorgang über Neuladen prüfbar. Mengenverbrauch erfolgt atomar und serverseitig auf den ausgewählten Auftrag begrenzt. Prüfungen: `tests/queue_speedups.php`, `tests/queue_speedups_app.cjs`, `tests/queue_speedups_inventory_app.cjs`, `tests/hospital_app.cjs` und `tests/training_app.cjs` mit isolierten Vorschauen.

Bau I, Bau II, Forschung und Ausbildung verwenden die gemeinsame `.hud-job`-Karte. Auf großen Bildschirmen stehen Symbol, Bereichsname, Zustand und Restzeit zusammen. Bis 900 px Breite und im kurzen Querformat erscheinen nur das Symbol und die Restzeit auf einer 58 × 50 px großen Fläche. Titel, zusätzliche Statuszeile und Truppenanzahl entfallen dort. Der vollständige Bereichsname und Zustand bleiben als `aria-label` und Tooltip erhalten. Laufende Aufträge erhalten einen violetten Rahmen und einen schmalen grünen Fortschrittsbalken.

- **Läuft:** violettes Symbolfeld, Uhrzeichen, Restzeit und Fortschritt aus Start- und Endzeit des Servers.
- **Bereit:** gedämpfte Fläche und graues Symbol, weiterhin antippbar. Auf großen Bildschirmen zusätzlich grünes Häkchen und „Auftrag starten“.
- **Gesperrt:** gedämpfte Fläche, gestrichelter Rahmen und konkrete Freischaltung, z. B. „Ab VIP 4“.
- **Abschluss:** warmer goldener Hinweis „Wird bestätigt“, mobil nur „…“, bis der Server den Auftrag tatsächlich abgeschlossen hat.
- **Unbekannte Zeit:** „Läuft · Zeit offen“; keine erfundene Restzeit oder Erfolgsmeldung. Bewegte Anzeigen respektieren reduzierte Bewegung.

Im Querformat liegen die Karten nebeneinander. Kleine Hochformate behalten eine kompakte Spalte. Statuskarten dürfen Aufgabenhinweis, Chat und Navigation nicht verdecken. Auf breiten Dorfansichten steht der Aufgabenhinweis rechts neben den Auftragskarten über dem Chat. `tests/hud_activity.cjs` prüft die Zustände, Beschleunigungen, beide Bauplätze und die Anordnung in acht Bildschirmgrößen; `tests/training_hud.cjs` prüft die Abschlussbestätigung der Ausbildung.

### Zeitlich begrenzte Boni und Debuffs

Neben dem Profil erscheint ein grüner Aufwärtspfeil, solange zeitlich begrenzte Boni wirken. Negative Effekte verwenden einen roten Abwärtspfeil im festen Platz darunter, auch wenn kein Bonus aktiv ist. Die sichtbaren Schaltflächen sind dezente 28 × 28 px mit 18-px-Pfeilen, hellen Grün-/Rotflächen und feinem Rand ohne Schatten. Die transparente Antippfläche bleibt 44 × 44 px groß. Im schmalen Handyformat stehen VIP und Hunter unter dem Profilblock; im kurzen Querformat weichen die Auftragskarten nach unten aus.

Antippen öffnet eine kompakte Liste im gemeinsamen Spielfenster: Quelle bzw. Charm-Seltenheit, Wirkung, Prozentwert, Restzeit im Format `HH:MM:SS` und Fortschrittsbalken. Die Anzeige nutzt die Serverzeit, läuft auch bei geöffnetem Fenster weiter und entfernt abgelaufene Effekte. Gleichzeitige Boni und Debuffs werden getrennt angezeigt. Karten-Charms gelten für die jeweilige Welt, Inventarboni accountweit; gespiegelte Datenbankeinträge erscheinen nur einmal. Schließen, Escape und Zurück schließen das Fenster. Prüfungen: `tests/active_effects.php` und `tests/active_effects_app.cjs`.

### Chat im Spielfeld

Der Welt- und Allianzchat bietet ausgeklappt 220 px Höhe, auf kleinen Bildschirmen abhängig von der Höhe 164–230 px. Kurzes Querformat begrenzt ihn auf den verfügbaren Platz unter den HUD-Symbolen. Eingeklappt bleiben 32 px. Der Nachrichtenbereich scrollt; Kanalwahl und Eingabe bleiben sichtbar. Nachrichten verwenden dunkelbraune Schrift auf Elfenbein. Aufgabenhinweis und Kartensteuerung orientieren sich an derselben `--world-chat-height`, damit keine Bedienelemente überdeckt werden.

## Weltkarte

### Objektsuche über die Lupe

Die Lupe klappt direkt eine helle, horizontale Suchleiste aus. Sie bleibt an der Lupe verankert, legt keinen modalen Hintergrund über die Karte und lässt die untere Navigation erreichbar. Horizontal wischbare Bildkarten bieten Monster, Rally-Gegner, Nahrung, Holz, Stein, Gold und Kristalle an. Stufe, Minus/Plus und Schieberegler bleiben in einer kompakten zweiten Zeile zusammen; die Stufen kommen aus dem Serverkatalog. „Nächstes Ziel“ findet das nächste passende Ziel zur eigenen Stadt in der aktuellen Welt, auch außerhalb des geladenen Ausschnitts. Gesperrte Gebiete, besiegte oder abgelaufene Gegner sowie leere, abgelaufene oder besetzte Rohstofffelder werden ausgeschlossen. Die Karte springt zum Treffer und zeigt seine vorhandenen Aktionen. Die Suche selbst startet keinen Marsch.

Die Suche zeigt keine Ergebnisliste. Jeder weitere Klick auf „Weiter suchen“ führt direkt zum nächsten passenden Objekt, nach Entfernung zur eigenen Stadt sortiert. Bei gleicher Entfernung entscheidet die Objekt-ID; nach dem letzten Treffer beginnt die Runde von vorn. Ein Servercursor erhält die Reihenfolge auch dann, wenn vorherige Ziele inzwischen verschwunden sind. Wechsel von Typ, Stufe, Welt oder Stadtposition beginnen beim nächsten Ziel. „Weiter suchen“ ist auch direkt im Zielmenü erreichbar. Auswahl und Stufe bleiben beim erneuten Öffnen erhalten. Lupe, Einklapppfeil, Escape und Browser-Zurück klappen die Suche ein; späte Antworten dürfen die Karte danach nicht versetzen. Chat und rechte Ereignisleiste weichen der Suchleiste, die untere Navigation und das übrige HUD bleiben dagegen bedienbar. Prüfungen: `tests/map_search.php` und `tests/map_search_app.cjs` mit Desktop, 390 px, 320 px und kurzem Querformat; Vorschau mit `--regional-bosses --chat --map-search`.

Die Karte verwendet dieselben natürlichen Pigmente wie das Dorf. Die vier Regionen bleiben unterscheidbar. Geometrie, Koordinaten, Objektpositionen und Spielregeln werden bei einer Stiländerung nicht verändert.

| Region | Boden | Helle Akzente | Vegetation und Felsen |
|---|---|---|---|
| Smaragdwald | `#B9C985` | Sandwege `#EDD09A` | Blattgrün `#498047` / `#74A452`, warme graue Felsen |
| Frostlande | `#DCE9E5` | Schnee `#EDF2E7` | Gedämpftes Türkisgrün, keine grellblauen Schatten |
| Sonnendünen | `#E6CC96` | Sand `#F6E3B8` | Salbeigrün, warme Ockerfelsen |
| Aschenlande | `#AD9990` | Glut `#ED8A31` | Warmes Aschgrau und Braun, kleine Glutspalten |

Wasser ist überwiegend `#57B6D7`, mit `#3793BD` als Tiefe und `#C5EFF0` als Licht. Die regionale Abstimmung darf diese Farben leicht verändern. Ufer und Wege bleiben warm. Das Kachelraster ist eine zurückhaltende braune Linie (`#70472F16`), die Objekte nicht überlagert.

Die Palette steht in `assets/js/world-landscape.js`; Übergänge und Gelände sind deterministisch. Breite, schwache Farbflächen erzeugen Abwechslung. Keine Fototexturen, kein graugrüner Schmutzfilter und kein dichtes Rauschen. Baumkronen erhalten breite Farbstufen, Tannen weiche, leicht asymmetrische Silhouetten. Große Formen verwenden dunkelbraune Konturen.

## Regeln für neue Änderungen

1. Vor sichtbaren Änderungen diese Datei und bei Welt/Stadt zusätzlich `docs/ART_DIRECTION.md` lesen.
2. Für neue Oberflächen `var(--ui-...)` verwenden. Keine weitere private Farbpalette oder dunkle Fensterhaut hinzufügen.
3. Neue gemeinsame Bausteine in `village-theme.css` ergänzen. Bestehende `!important`-Regeln dort dienen ausschließlich der Ablösung älterer Stylesheets; neue Komponenten sollen direkt die Variablen nutzen.
4. Die Datei zuletzt und mit `filemtime`-Version laden. Das gilt auch für die separate 3D-Seite und das Backoffice.
5. Eine neue Oberfläche in der echten Haupt-App und bei 390 px sowie 320 px Breite prüfen. Zusätzlich eine kurze Querformatansicht testen. Schließen, Reiter, Formulare und primäre Aktion müssen erreichbar bleiben.
6. Bei Änderungen an Weltfarben alle vier Regionen, Weltübersicht, Suche und Objektaktionen prüfen. Bei Stadtänderungen Gesamt- und Nahansicht, Gebäudeauswahl, freie Laufwege und doppelte HUDs prüfen.

### Anfangsguide

Der Anfangsguide unter „Menü → Anfangsguide“ verwendet vier feste Reiter über einer senkrecht scrollbaren Lesefläche: Einstieg, Gebäude, Ziele und Wissen. Sechs Kapitel vermitteln den Start; alle 16 Gebäude zeigen Zweck und aktuelle Stufe. Ziele beziehen ihren Fortschritt aus dem Spielstand. Der schließbare Willkommenshinweis erscheint für neue Spieler einmal je Browser und Spieler; Lesestand und Kapitel werden auf diesem Gerät gemerkt. Aktualisierungen erhalten Fokus, Scrollposition und aufgeklappte Erklärungen. Prüfung: `tests/beginner_guide_app.cjs`; Details: `docs/BEGINNER_GUIDE.md`.

## Prüfwege

`tests/fantasy_theme_app.cjs` prüft Variante A mit einem automatisch entfernten Testkonto: 110 Kombinationen aus 22 Spielfenstern und fünf Bildschirmformaten, tatsächliche Almendra-/Lora-Glyphen, erreichbare Fensterköpfe, antippbare Relikte, erfolgreiche Menüabfragen, Anmeldung und Wiederherstellung bis 320 px, Weltkarte und Backoffice. Die Talentansicht muss vor der Prüfung vollständig geladen sein. Bildschirmaufnahmen und Ergebnisprotokoll entstehen unter `artifacts/fantasy-theme/`.

Die Menüprüfung misst zusätzlich den Kontrast sichtbarer Texte auf CSS-Farbflächen inklusive Transparenz. `tests/building_contrast_app.cjs` prüft die echten Gebäudeausbaudialoge mit ausreichenden und fehlenden Rohstoffen, Voraussetzungen und laufenden Aufträgen in vier Bildschirmformaten. Referenzaufnahmen liegen unter `artifacts/contrast-fixes/`. Text über Bildmaterial und Sondereffekten zusätzlich visuell prüfen.

Die vorhandenen UI-Prüfungen umfassen `window_panels.cjs`, `progression_panel.cjs`, `trading_panel.cjs`, `treasure_panel.cjs`, `community_panel.cjs`, `march_windows.cjs` und `building_actions.cjs`. `world_biomes.cjs` prüft Regionen, weiche Übergänge, Navigation, Menüs und mehrere Bildschirmgrößen. `world_terrain.cjs` vergleicht Wasserpositionen zwischen Server und Darstellung.

In der Haupt-App sind mindestens Profil, Aufgaben, Armee, Forschung, Inventar, Schatzkammer, Talente, Handel, Gemeinschaft, Verteidigung, Ereignisse, Feldzüge, Rangliste, Arena, Welten, Optionen, Konto, Hilfe und das Hauptmenü zu kontrollieren. Dazu kommen VIP, Gebäudeaktionen, Marschfenster sowie Suche und Navigation auf der Weltkarte. Das Backoffice benutzt dieselbe Palette mit seinem bestehenden Tabellenlayout.


### Karten-Ziele und direkter Monsterangriff

Rohstoffstellen zeigen eine zusammenhängende `.is-encounter`-Karte mit dunkelviolettem Kopf, Pflaumenrahmen, Goldkante und warmem Beigegrund. Bild, Stufe, Koordinaten und 1×1- bzw. 2×2-Belegung stehen über Vorrat und Fortschrittsbalken. Die Hauptaktion ist grün zum Sammeln; Details und Teilen stehen darunter. Besetzte Stellen zeigen ihren tatsächlichen Status und gegebenenfalls Rückruf. Die Karte wird anhand der sichtbaren Objektgrafik, HUD-Elemente und Chatbegrenzungen positioniert. Kleine Hochformate nutzen 188 px Breite, Querformat 210 px; sehr kurze Handy-Querformate nutzen eine 272 px breite, niedrige Karte mit nebeneinander liegenden Aktionen. Schließen, Escape und Browser-Zurück schließen die Karte, ohne einen Marsch auszulösen.

Ein bewusster Tipp auf ein Monster der Weltkarte öffnet dagegen unmittelbar das Angriffs- beziehungsweise Rallyfenster. Es gibt keine vorgeschaltete Monster-Infokarte. Monsterbild, Name, Stufe, Koordinaten, Lebenspunkte und mögliche Beute stehen im Zielbereich des Angriffsfensters; daneben folgen Truppenauswahl, Prognose und die rote Angriffsaktion. Rein programmgesteuertes Anzeigen eines Ziels, etwa aus Suche oder Bericht, darf weiterhin nur die Karte fokussieren und keinen Angriffsdialog auslösen. `tests/encounter_cards_app.cjs` prüft Rohstoffkarten und den direkten Monsterangriff mit dem echten App-HUD in fünf Bildschirmgrößen.
