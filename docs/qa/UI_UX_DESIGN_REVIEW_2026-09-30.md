# Union of Kingdoms — Design-, Schrift- und Farbprüfung

Stand: 30. September 2026. Teilprüfung des gemeinsamen UI/UX-Audits, ausschließlich lesend gegenüber Produktcode und Spielständen.

## Ergebnis

Variante A mit Violett, warmem Beige, Almendra und Lora funktioniert als gemeinsame Identität. Die gemessenen Grundfarben benötigen keinen Austausch. Der größte konkrete Verbesserungsbedarf liegt in **zu kleinen Texten bei mobilen Informations- und Navigationsflächen** sowie **einzelnen kleinen Rückrufaktionen**. Abgeholte Aufgaben verlieren durch eine zusätzliche Transparenzstufe unnötig an Lesbarkeit.

Diese Teilprüfung enthält keine Freigabe der kompletten App und keine vollständige Aussage zur Barrierefreiheit. Sie ergänzt die echten App-Messungen des Hauptaudits.

## Prüfverfahren und Grenzen

- Gelesen: `docs/UI_STYLE_GUIDE.md`, `docs/MOBILE_APP_STRATEGY.md`, `assets/css/fantasy-fonts.css`, `assets/css/village-theme.css` sowie relevante Feature-Styles und Renderstellen.
- Ein lokales, dateibasiertes HTML-Prüfmuster lädt **alle 50 Stylesheets aus `views/game.php` in deren tatsächlicher Reihenfolge**. Keine PHP-Instanz, keine Anmeldung, keine API und keine Datenbank.
- Komponentenklassen und ihre entscheidenden Vorfahren entsprechen den Produktselektoren. Beispieldaten sind synthetisch. Nur Platzierung/Größe der äußeren Prüfrahmen wurden für die Übersicht verändert. Daraus werden **keine Aussagen über die tatsächliche App-Anordnung oder Überdeckungen** abgeleitet.
- Gemessen mit Chrome/Playwright: `1280×800`, `390×844`, `320×568`, `844×390`, `568×320` CSS-Pixel; `getComputedStyle`, Elementgrößen und Chrome DevTools Protocol für tatsächlich gerenderte Schriftdateien. Fünf Musterbilder wurden gespeichert, die Handyansicht zusätzlich visuell betrachtet.
- Kontrast: sRGB-Kanäle linearisiert, relative Leuchtdichte `0,2126 R + 0,7152 G + 0,0722 B`, Verhältnis `(Lhell+0,05)/(Ldunkel+0,05)`. Transparente Textgruppen werden gegen den deckenden Kartenhintergrund verrechnet. Bei den grünen Verläufen werden die beiden deckenden Endfarben separat gemessen. Keine Behauptungen aus einem vermeintlichen Kontrast gegen den durchscheinenden Vorfahren eines Verlaufs.
- Kein Sehtest mit Menschen, kein Screenreader-Durchlauf, kein echtes Mobilgerät und keine Messung bei Sonneneinstrahlung. Schriftgrößen sind CSS-Werte, keine physischen Millimeter. Für kleinen Text wird keine pauschale Normverletzung behauptet.

Messprogramm und Rohdaten: `artifacts/ui-ux-audit-2026-09-30/design/measure.cjs`, `measurements.json`, `run-output.json`; Bilder `specimens-<Breite>x<Höhe>.png`. Die Bilder sind ausdrücklich Komponenten-Prüfmuster, keine Screenshots einer laufenden Spielsitzung.

## Befunde nach Priorität

### D-01 · P2 · Mobile Navigation und relevante Werte werden zu stark verkleinert

Die kleine Darstellung ist mit der vollständigen Kaskade bestätigt; es handelt sich nicht um überholte CSS-Regeln. Gerade Almendras feine Buchstabenformen verlieren bei 8–10 px Übersichtlichkeit. Der Kontrast dieser Texte ist weitgehend gut und behebt die geringe Größe nicht.

| Gemessener Text | 1280×800 | 390×844 | 320×568 | 844×390 | 568×320 |
|---|---:|---:|---:|---:|---:|
| Navigation `.dock-label` | 11 px | 9 px | **8 px** | **8 px** | **8 px** |
| Ausbauwert/-bezeichnung `.levelup-stat` | 12 px | **9 px** | **9 px** | 12 px | **9 px** |
| Marschstatus `.world-march-row small` | **8 px** | **8 px** | **8 px** | **8 px** | **8 px** |
| Aufgabenbeschreibung `.quest-row p` | 11 px | **10 px** | **10 px** | 11 px | **10 px** |

Weitere Messwerte: Die 8-px-Marschstatuszeile hat rund **8,39 px Zeilenhöhe**; mobile Aufgabenbeschreibungen 10/13 px. Die Navigation hat bei 320 px zusätzlich `letter-spacing:-.35px`.

Belege:

- `assets/css/village-theme.css:3099`, `:3101`, `:3105`: mobile/Querformat-Navigation.
- `assets/css/village-theme.css:553`: mobile Ausbauwerte; echtes Markup in `assets/js/game.js:278` und `:407`.
- `assets/css/village-theme.css:2281`: 8-px-Marschstatus.
- `assets/css/village-theme.css:648` und `:663`: Aufgabenbeschreibung; echtes Markup in `assets/js/mvp-panels.js:284`.
- `measurements.json`: Proben `dock-label`, `upgrade-label`, `upgrade-value`, `march-state`, `quest-active-description`.

Empfehlung: Navigation möglichst 11–12 px, kurze Statusangaben mindestens etwa 11–12 px, entscheidende Ausbau-/Kostenwerte 13–14 px und erklärende Fließtexte 14–16 px als **zu prüfende Designziele** einführen. Zuerst die am häufigsten gelesenen Zahlen und Aktionsfolgen vergrößern. Dafür Raum durch Umbruch, weniger gleichzeitige Detailtexte oder eine abrufbare Detailzeile gewinnen. Primäre Aktionen und vorhandene feste Aktionsleisten müssen dabei erreichbar bleiben. Ein bloßes globales Hochskalieren würde die sorgfältig eingepassten Trainings-/Marschansichten gefährden.

### D-02 · P2 · Rückruf im Marsch-HUD ist als Touch-Ziel unnötig klein

Der Rückrufknopf misst **32×32 px** in Desktop/Hochformat und **30×30 px** in beiden kurzen Querformaten. Das ist kleiner als die im Stilhandbuch vorgesehenen etwa 40–44 px für wichtige Aktionen. Die Ausnahme für kompakte Rangknöpfe beschreibt diese Rückrufaktion nicht. Die benachbarte Marschzeile öffnet eine andere Ansicht; ein größerer, klar abgegrenzter Trefferbereich wäre auf dem Handy hilfreich.

Belege: `assets/css/village-theme.css:2285`, `:2335`; tatsächliche Aktion/ARIA-Beschriftung in `assets/js/world-march-hud.js:28`, `:64`, `:73`; Proben `march-recall` in `measurements.json`. Die größere Rückrufaktion im geöffneten Marschdetail existiert bereits (`world-march-hud.js:6`), ersetzt aber nicht die kleine direkte HUD-Aktion.

Empfehlung: Den sichtbaren Knopf oder seine eindeutig begrenzte Bedienfläche auf etwa **44×44 px** bringen. Keine unsichtbare Vergrößerung über die benachbarte Hauptaktion legen. Im Querformat lieber eine weniger dichte Marschzeile oder den Rückruf nur im bereits vorhandenen Detailfenster anbieten. Das ist eine Touch-/Fehlbedienungsempfehlung, keine pauschale Aussage über eine formale Mindestzielgröße.

Positiv: Der Knopf besitzt eine verständliche ARIA-Beschriftung und bei Tastaturfokus eine gemessene 3-px-Umrandung `#5C4270`. Die Ursache ist hier die geringe Trefferfläche, nicht fehlende Fokussichtbarkeit.

### D-03 · P3 · Abgeholte Aufgaben verlieren durch Gruppen-Transparenz Kontrast

`.quest-row.claimed .quest-copy` erhält `opacity:.65`. Dadurch wird Haupttext `#443549` auf Karte `#FBF6EC` effektiv ungefähr `rgb(132.05,120.55,130.05)` und erreicht nur noch **3,89:1**. Ohne Transparenz sind es **10,53:1**. Betroffen sind Titel, Zahlenfortschritt und Beschreibung; mobil ist die Beschreibung zugleich nur 10 px groß. Das gesonderte Statusbadge „Claimed“ bleibt mit **6,88:1** gut lesbar.

Beleg: `assets/css/village-theme.css:642`, Produktmarkup `assets/js/mvp-panels.js:284`, Proben `quest-claimed-*`.

Einordnung: Abgeholte Einträge sind abgeschlossene Inhalte, keine aktiven Aktionsknöpfe. Deshalb wird dies **getrennt von aktiven Text-/Bedienflächen** als Lesbarkeitsempfehlung geführt und nicht als pauschaler WCAG-Verstoß gewertet. Das eigene Stilhandbuch nennt 4,5:1 als Textziel und empfiehlt generell lesbare Materialien statt Textausblendung.

Empfehlung: Gruppen-Deckkraft auf 1 belassen und den abgeschlossenen Zustand über das vorhandene Häkchen/Badge sowie eine ruhigere Fläche kennzeichnen. Falls Text zurücktreten soll, eignet sich `--ui-muted` auf `--ui-card` mit **6,80:1** besser als Transparenz.

### D-04 · P3 · Die umfangreiche CSS-Kaskade erhöht das Risiko uneinheitlicher Folgeänderungen

Die Hauptansicht lädt 50 unterschiedliche CSS-Dateien mit zusammen **1.087.902 Rohbytes**. Darin stehen **1.257 `!important`-Deklarationen**; `village-theme.css` allein umfasst **335.884 Rohbytes** und **912 `!important`**. Ältere Grundregeln definieren beispielsweise noch dunkelblaue Oberflächen (`assets/css/game.css:1–2`), die am Ende von der gültigen Variante A überschrieben werden.

Das ist **kein gemessener Ladezeit- oder FPS-Befund**. Komprimierung, Cache und echte Renderingzeiten wurden hier nicht ermittelt. Es erklärt jedoch, weshalb eine isolierte Textsuche leicht falsche Schrift-/Farbprobleme meldet und neue Komponenten zusätzliche Reparaturregeln benötigen.

Empfehlung: Bei künftigen Feature-Arbeiten veraltete Regeln jeweils im betroffenen Baustein zusammenführen und Zustandsmaterialien/Typografiestufen als kleine gemeinsame Tokens verwenden. Kein großer CSS-Neubau während der laufenden Abnahme. Die Farbrollen von Angriff, Verteidigung, Belohnungen und Seltenheiten weiterhin gesondert halten.

## Was bereits gut funktioniert

### Palette: geeignete Hauptfarben beibehalten

Die folgenden deckenden Farbpaare erreichen das im Stilhandbuch genannte Textziel von 4,5:1. Sie belegen die Eignung der Tokens, nicht automatisch jedes beliebige Vorkommen in der App.

| Farbgebung | Gemessener Kontrast |
|---|---:|
| Haupttext `#443549` / Papier `#E9DFCF` | **8,60:1** |
| Ergänzender Text `#5F5261` / Papier `#E9DFCF` | **5,55:1** |
| Haupttext `#443549` / Karte `#FBF6EC` | **10,53:1** |
| Heller Text `#FFFCF6` / Violett `#5C4270` | **8,31:1** |
| Heller Text / helleres Violett `#80658F` | **4,91:1** |
| Heller Text / Grün `#586D50` | **5,52:1** |
| Heller Text / Rot `#B43C34` | **5,64:1** |
| Heller Text / Blau `#2A72C9` | **4,72:1** |
| Dunkelroter Hinweis `#85342B` / Karte | **7,73:1** |
| Haupttext / Gold `#C5A361` | **4,75:1** |

Die grünen Beispielaktionen verwenden tatsächlich einen Verlauf von `#586D50` nach `#3F523A`; die Endfarben erreichen **5,52:1** bzw. **8,27:1**. Der allgemeine deaktivierte Knopf hat volle Deckkraft, `#5F5261` auf `#DDD2C0`, **4,90:1**. Dieser inaktive Zustand wird separat betrachtet.

Gold `#C5A361` als Text auf Karte/Papier würde nur **2,22:1 / 1,81:1** erreichen. Daraus folgt **kein neuer konkreter Textfehler**: Gold wird überwiegend als Rand/Schmuck verwendet. Die Empfehlung ist, dieses Token auch künftig als Akzent zu behandeln und notwendige Textlabels dunkel zu halten. Ein globales Abdunkeln der Goldpalette ist nicht erforderlich.

### Schriften: die gewünschte Kombination wird wirklich verwendet

`fantasy-fonts.css:65–116` teilt `Conquer UI` per `unicode-range` auf. Der Chrome-Schriftbericht bestätigt:

- „Almendra Éclaireur Äußere Größe“: **Almendra-Regular**, lokal geladen; Akzente und Umlaute in derselben Schrift.
- „1234567890“: **Lora**, lokal geladen.
- „Troops 12345“: **Almendra** für Buchstaben/Leerzeichen und **Lora** für fünf Ziffern.

`font-display:swap` und lokale WOFF2-Dateien benötigen keinen externen Schriftdienst. Die sechs vorhandenen Font-Dateien haben zusammen 90.452 Bytes. Das ist keine Messung der tatsächlich übertragenen Menge; Browser laden die benötigten Teilmengen.

**Empfehlung zur Schriftwahl:** Almendra für die Spielidentität behalten, Lora für Zahlen weiterverwenden. Zuerst Größe, Zeilenhöhe und Gewicht vereinheitlichen. Eine optionale gut lesbare Textansicht mit Lora auch für längere Beschreibungen wäre als späterer, ausdrücklich abgestimmter Versuch möglich: vorhandene lokale Schrift, ruhigeres Schriftbild, aber weniger märchenhafte Wirkung und Abweichung von der bisherigen Almendra-für-Texte-Regel. Eine neue dritte Schrift ist aus diesen Messungen nicht begründet.

### Fokus, Zoom-Voraussetzung und reduzierte Bewegung

- Gemessener Aufgabenknopf: `:focus-visible`, **3 px `#5C4270`**, Außenabstand 2 px; Marschrückruf gleiche Farbe/Breite, Abstand −3 px. Dies sind zwei bestätigte Beispiele, keine Aussage über jeden interaktiven Knoten.
- Der Viewport in `views/game.php:9` enthält keine Zoomsperre wie `user-scalable=no`. Ob komplexe Ansichten bei Vergrößerung vollständig nutzbar bleiben, gehört in den echten App-Test.
- Bei emuliertem `prefers-reduced-motion:reduce` ist der Runenpartikel tatsächlich `animation-name:none` und `display:none`. Mehrere weitere Animationsgruppen besitzen passende CSS-Regeln; sämtliche JS-Animationen wurden hier nicht geprüft.
- Zustände werden an den geprüften Beispielen zusätzlich durch Texte, Häkchen, Zahlen und ARIA-Zustände dargestellt. Eine reine Farbblindheitsprüfung der gesamten Weltkarte ist damit nicht erledigt.

## Reihenfolge für eine spätere Verbesserung

1. 8-/9-px-Texte der Navigation, Ausbauwerte und Marschzustände in echten App-Flüssen vergrößern und in denselben fünf Ansichten erneut messen. EN/DE/FR berücksichtigen.
2. Rückruf-Touchziel vergrößern, Nachbaraktionen und Querformat dabei gezielt prüfen.
3. Abgeholte Aufgaben ohne Gruppen-Transparenz darstellen; bestehende Statuskennzeichnung behalten.
4. Gemeinsame Textgrößen und Zustandsflächen bei kommenden Änderungen konsolidieren. Erst nach Nutzerprüfung über eine optionale alternative Lesedarstellung entscheiden.

Keine Produktdatei wurde im Rahmen dieser Teilprüfung geändert. Keine Datenbank oder Spielwelt wurde geöffnet oder verändert.

## Abgleich mit dem finalen App-Prüfsnapshot

Die isolierten Werte oben bleiben historische CSS-Proben. Für den echten App-Zustand ist die abschließende Sektion in [UI_UX_APP_VISUAL_REVIEW_2026-09-30.md](C:/xampp/htdocs/conquer/docs/qa/UI_UX_APP_VISUAL_REVIEW_2026-09-30.md) maßgeblich; Rohbelege liegen in [final-visual-evidence.json](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/design/final-visual-evidence.json).

Der finale, unveränderte Fixture-Stand wurde von 13:25:04 bis 13:30:40 UTC geprüft. Seine 130 erfassten CSS-/JS-/MJS-/Locale-Dateien blieben identisch. Vier entsprechende Workspace-Dateien änderten sich währenddessen unabhängig; deshalb gelten die folgenden Aussagen für den eingefrorenen Prüfsnapshot und nicht pauschal für den späteren Arbeitsstand.

- Aktive Docklabels sind in den echten 320-/568-Ansichten weiterhin **8 px groß mit 8 px Zeilenhöhe**. Der Lesbarkeitsbefund ist damit bestätigt.
- Der echte Marschabschluss erreicht jetzt **44 px Höhe in beiden kleinen Ansichten**, ist vollständig sichtbar und per Mittelpunkt erreichbar. Frühere 38-/35-px-Werte dieses Knopfs sind dort überholt. Der separat isoliert gemessene Marschrückruf wurde nicht durch Auslösen eines Marsches nachgeprüft.
- Das Inventar-Übersichtssymbol bleibt visuell zu hell; der Gebäudestatus „Maximum“ wird im 568er Querformat teilweise überdeckt. Beide Fälle sind anhand neuer Bilder bestätigt.
- Der echte Schriftbericht bestätigt erneut Almendra-Bold für den Titel und Lora-Bold für die Ziffern. Ein Wechsel der Schriftfamilien oder der violett/beigen Palette ist aus der Nachprüfung nicht begründet.
- Die drei früheren automatischen Kontrastkandidaten über Bild-/Schatten-/Verlaufshintergründen werden weiterhin nicht zu gesicherten Kontrastverletzungen erklärt. Eine solche Schlussfolgerung benötigt tatsächliche Pixelkomposition am betreffenden Text.

Der finale Lauf enthält 255 erfasste Zustände aus 265 Beobachtungen; 47 Bilder wurden manuell angesehen. Eine Verteidigungsansicht blieb nach HTTP 409 BUSY im Fehlerzustand, zwei weitere BUSY-Antworten wurden protokolliert. In zwei Aufnahmen waren insgesamt 13 sichtbare Bilder noch ausstehend. Diese Grenzen schließen eine uneingeschränkte Aussage „alles vollständig geladen und fehlerfrei“ aus.
