# Union of Kingdoms – Branding-Kit

## Verbindliche Designentscheidung

**Freigegeben vom Nutzer am 7. Oktober 2026: „Fantasy · Bree Serif + Apricotlicht“.** Diese Kombination gilt für die gesamte Spieloberfläche einschließlich Anmeldung, Stadt-/Welt-HUD, Menüs, Dialogen, Gebäudeaktionen, Forschung, Ausbildung, Inventar und Karten-Overlays. Neue öffentliche Markenmaterialien verwenden dieselbe Schrift- und Farbfamilie. Der öffentliche Name lautet stets **Union of Kingdoms**; „Conquer“ bleibt ein interner technischer Name.

Die Auswahl verbindet den hellen Hintergrund **Apricotlicht**, die kräftigen violetten Köpfe der Variante **Knallig** und die Schriftwahl **Fantasy · Bree Serif**. Sie ersetzt die früher verbindlichen dunkleren Beige-/Violettwerte und Almendra-/Lora-Schriften. Alte Screenshots und Entwürfe belegen den damaligen Stand, aber überschreiben diese Entscheidung nicht.

Die Verwaltungsoberfläche behält ihre gesonderte Freigabe vom 5. Oktober 2026: Systemschrift, neutrale helle Flächen und blaue Aktionen unter `body.admin-modern` mit `assets/css/admin-modern.css`. Regeln für Sprache, semantische Farben, Touch-Bedienung und unsichtbare Scrollleisten gelten auch dort.

Die Auswahl ist in diesen Projektentwürfen nachvollziehbar:

| Referenz | Gewählte Einstellung |
| --- | --- |
| [light-backgrounds.html](../output/menu-depth/light-backgrounds.html) | `data-surface="apricot"`, helle grüne Aktionen mit `.is-vivid` |
| [font-variants.html](../output/menu-depth/font-variants.html) | `data-typeface="bree"` – Fantasy · Bree Serif |
| [research-color-additions.html](../output/menu-depth/research-color-additions.html) | `.is-vivid` – kräftige violette Köpfe und plastische Akzente |

Die folgenden Werte halten die Entscheidung dauerhaft fest, auch wenn die Vorschauen später archiviert werden. Die Vorschauen sind Gestaltungsmuster; ihre Demonstrationsdaten und externen Schriftlinks gehören nicht in die produktive App.

## Markenwirkung

Die Oberfläche wirkt hell, warm und gezeichnet. Ruhige elfenbeinfarbene Leseflächen lassen Figuren, Werte und Aktionen klar hervortreten. Violette Köpfe geben den Fenstern eine erkennbare Form. Abgerundete Kanten, eine helle Oberkante und ein kurzer unterer Schatten geben Knöpfen und Karten weiche plastische Tiefe. Gold bleibt ein gezielter Akzent.

Apricot- und Fliederlicht sitzt dezent am Rand größerer Flächen; es darf weder Textkontrast noch die Farbe von Gegenständen verändern. Bereits freigegebene Figuren, Gebäude, Landschaften und Symbolfamilien behalten ihre gezeichnete Identität. Die Stilreferenz der Welt bleibt `assets/art/village2.png` mit den Regeln in [ART_DIRECTION.md](ART_DIRECTION.md).

## Farben

| Rolle | Gemeinsamer Wert | Farbe |
| --- | --- | --- |
| Elfenbeinfläche | `--ui-paper` | `#FFFAF0` |
| Helle Karte und Eingabe | `--ui-card`, `--ui-card-light` | `#FFFDF8` |
| Eingelassene helle Fläche | `--ui-inset` | `#FFF0E2` |
| Deaktivierte Fläche | `--ui-disabled` | `#EEE7D9` |
| Haupttext | `--ui-ink` | `#432D4B` |
| Ergänzender Text | `--ui-muted` | `#635368` |
| Warmer Kartenrand | `--ui-line` | `#DFBDA0` |
| Äußerer Rahmen | `--ui-frame` | `#BCA37C` |
| Violetter Hauptakzent | `--ui-primary` | `#8538BC` |
| Violette obere Aufhellung | `--ui-primary-light` | `#A24DD0` |
| Violette Tiefe / Fokus | `--ui-primary-dark` | `#532375` |
| Grüne Aktionsoberkante | `--ui-action-green-light` | `#B6E084` |
| Helle grüne Aktionsfläche | `--ui-action-green` | `#82C34A` |
| Unterer Verlauf grüner Aktionen | `--ui-action-green-base` | `#72B641` |
| Text auf grünen Aktionen | `--ui-action-green-ink` | `#203B18` |
| Tiefe grüner Aktionen | `--ui-action-green-edge` | `#4C7A2D` |

Der Apricotlicht-Flächenverlauf `--ui-surface` entspricht der gewählten Vorlage:

```css
radial-gradient(ellipse at 0% 32%, #ffdbbb9e, transparent 52%),
radial-gradient(ellipse at 100% 66%, #f2d9e959, transparent 46%),
linear-gradient(#fffdf6, #fff7ec)
```

Bei Verwendung als Kurzschreibweise steht die Grundfarbe nach allen Verlaufsebenen: `background: var(--ui-surface) var(--ui-paper);`. `--ui-primary-light` ist für ausreichenden Kontrast heller Tab-Schrift auf `#A24DD0` abgestimmt; die übrigen Lichtfarben der Kopfvorlage bleiben erhalten.

Der violette Kopf verläuft von `#A24DD0` über `#8538BC` bei 23 % und `#702BA1` bei 84 % bis `#532375`. Die schmale obere Lichtkante ist `#DA93F4`, die untere Tiefenkante `#421760`. Die Schrift bleibt hell. Die Lichtkante ist Dekoration, keine eigene Textfläche.

Bestätigende Aktionen verlaufen von `#B6E084` über `#82C34A` bei 18 % bis `#72B641`, mit dunklem Text `#203B18`. Ihre Lichtkante verwendet `#D9F3AA`, die Tiefenkante `#4C7A2D`. Neutrale Knöpfe bleiben hell mit warmem Rand; Goldaktionen behalten ihre erkennbare Kauf-/Belohnungsbedeutung und sichtbare Preise.

**Semantische Farben bleiben eigenständig.** Die neuen Aktionsfarben ersetzen weder `--ui-green` (`#586D50`) und `--ui-green-dark` (`#3F523A`) für Status-/Textkontrast noch Blau für Verteidigung, Rot für Angriff/Fehler, Kristallviolett, Freund-/Feindfarben oder Seltenheits- und Truppenstufenfarben. Insbesondere darf das helle Aktionsgrün nicht als Fließtextfarbe auf hellem Grund verwendet werden. Die vollständige gemeinsame Palette steht in [UI_STYLE_GUIDE.md](UI_STYLE_GUIDE.md).

## Typografie

| Einsatz | Schrift | Gewicht / Darstellung |
| --- | --- | --- |
| Fenster- und Abschnittsüberschriften | Bree Serif | 400, normale Schreibweise, etwa 1,15 Zeilenhöhe |
| Beschriftung der Hauptaktionen | Bree Serif | 400, etwa 1,15 Zeilenhöhe |
| Fließtext und erklärende Texte | Nunito | 400–600, mindestens etwa 1,3 Zeilenhöhe |
| Reiter, Nebenschaltflächen und wichtige Kurztexte | Nunito | 600–700 |
| Zahlen, Preise, Bestände, Zeitangaben, Eingaben | Nunito | 700, `font-variant-numeric: tabular-nums` |
| Einzelne hervorgehobene Kennzahlen | Nunito | bis 800, ohne Information nur über Schriftgewicht zu vermitteln |

`--ui-font-heading` verwendet `"Bree Serif", "Nunito", "Conquer UI", Georgia, serif`; Nunito ergänzt dabei insbesondere das fehlende große `ẞ`. `--ui-font` und `--ui-font-number` verwenden `"Nunito", "Conquer UI", "Segoe UI", sans-serif`. Die historische Familie `Conquer UI` darf als technischer Fallback erhalten bleiben. Sie ist keine Vorgabe, weiterhin Almendra-/Lora-Glyphen im aktiven Design zu zeigen. Zahlen innerhalb einer Hauptaktion, etwa Preis und Restzeit, bleiben Nunito.

Die Schriften werden mit ihren Lizenzdateien lokal unter `assets/fonts/` ausgeliefert und in `assets/css/fantasy-fonts.css` eingebunden. Die produktive Oberfläche benötigt keinen externen Schriftdienst. Umlaute, französische Akzente, Sonderzeichen und Zahlen müssen in den ausgelieferten Schnitten vorhanden sein. Bree Serif wird nicht künstlich fett gesetzt. Überschriften dürfen umbrechen; Schriftgrößen dürfen Bedienflächen und feste Aktionen nicht verdrängen.

## Form, Tiefe und Bedienung

- Fenster haben weiche Ecken, einen hellen Rahmen, eine schmale warme Kante und einen ruhigen Schatten. Bestehende passende Geometrien bleiben erhalten; etwa 16–20 px Radius für Fenster, 12–14 px für Karten und 9–12 px für Knöpfe dienen als Orientierung.
- Tiefe entsteht aus einer hellen Oberkante und einer kurzen unteren Kante; kleine Bedienelemente benötigen weniger Tiefe als die Hauptaktion. Keine zusätzliche gläserne, metallische oder fotorealistische Materialhaut.
- Eingelassene Bereiche bleiben hell. Die Lichtwirkung darf weder eine ganze Figur verfärben noch Statusfarben überdecken. Schatten bleiben so klein, dass benachbarte Ziele und Beschriftungen nicht überlagert werden.
- Aktiv, ausgewählt, gesperrt, fehlend und erfüllt bleiben durch Text, Symbol oder Form erkennbar. Normale Textkontraste erreichen mindestens 4,5:1. Vorder- und Hintergrund werden gemeinsam festgelegt; deaktivierte Texte werden nicht durch pauschale Deckkraft unlesbar gemacht.
- Hauptaktionen und wichtige Touch-Ziele sind mindestens 44 px hoch. Schließen, Zurück, Navigation und feste Aktionen bleiben auch bei kurzen Ansichten erreichbar. Eine größere Schrift darf keine zweite Navigation oder verdeckte Bedienelemente erzeugen.
- Seiten und innere Bereiche lassen sich per Touch, Mausrad und Tastatur scrollen. Browser-Scrollleisten bleiben in allen bestehenden und zukünftigen Welten unsichtbar; benötigtes Scrollen darf nicht mit `overflow:hidden` entfernt werden.
- Desktop, schmales Hochformat und kurzes Querformat teilen dieselbe Weboberfläche. Sichere Bildschirmränder, eingeblendete Bildschirmtastatur und reduzierte Bewegung werden berücksichtigt. [MOBILE_APP_STRATEGY.md](MOBILE_APP_STRATEGY.md) bleibt verbindlich.

## Logo und vorhandene Markenassets

Das freigegebene gezeichnete App-Symbol bleibt erhalten. Die Farb- und Schriftentscheidung ist keine Freigabe, das Motiv oder die Weltillustrationen neu zu erfinden.

| Aufgabe | Quelle / Ausgabe |
| --- | --- |
| Freigegebener Symbolmaster | `assets/icons/union-of-kingdoms-painted-master.png` |
| Maskierbarer Symbolmaster | `assets/icons/union-of-kingdoms-painted-maskable-master.png` |
| Browser-/App-Symbole | `favicon.ico`, `apple-touch-icon.png`, `assets/icons/conquer-*.png` |
| Bestehender Symbolexport | `tools/build-brand-icons.cjs` |
| Gemeinsame öffentliche Einbindung | `views/partials/brand-head.php`, `manifest.php` |

Symbole proportional skalieren, freigegebene Beschnitte und Maskierungsabstände bewahren, keine Schrift über das kleine App-Symbol legen. Der Export verwendet künftig `#8538BC` als einheitlichen violetten Hintergrund für Apple-/maskierbare Symbole. Für diese Designumstellung wurden die freigegebenen Bildmaster und bereits exportierten Icondateien nicht neu gezeichnet oder neu exportiert; die neue Hintergrundkonstante greift beim nächsten bewussten Symbolexport. Textlich gesetzte Markennamen und neue Begleitmaterialien verwenden die obige Typografie. Die zentrale Gestaltungsschicht bleibt `assets/css/village-theme.css`; gemeinsame Fontdefinitionen bleiben in `assets/css/fantasy-fonts.css`.

Browser-Metadaten und das Web-App-Manifest verwenden `#8538BC` als `theme-color` / `theme_color` und `#FFFAF0` als `background_color`. Die mobile Konfiguration und Android-Startfläche übernehmen ebenfalls `#FFFAF0`; Androids Primär- und Tiefenfarbe sind `#8538BC` und `#532375`. Änderungen an diesen Quelldateien ersetzen keinen nativen Build und keine Geräteprüfung.

## Geltung und Prüfung

[AGENTS.md](../AGENTS.md), dieses Kit und [UI_STYLE_GUIDE.md](UI_STYLE_GUIDE.md) beschreiben dieselbe verbindliche Auswahl. Bei Interfacefragen ersetzt diese Entscheidung vom 7. Oktober ältere Farbreferenzen. [ART_DIRECTION.md](ART_DIRECTION.md) bleibt für Illustrationen, [MOBILE_APP_STRATEGY.md](MOBILE_APP_STRATEGY.md) für die gemeinsame App maßgeblich.

Die Anpassung erhält Spielregeln, Werte, Inhalte, bestätigungspflichtige Aktionen und bestehenden Freigabestatus. Die ausdrücklich abgewählten Inventarfilter „Owned“ und „All items“ entfallen; Kategorien, Gegenstandsdetails, Mengenwahl, „Use All“ und Fundorte bleiben erreichbar. Englisch bleibt Standard und Rückfallebene; Deutsch und Französisch verwenden das gemeinsame Sprachsystem. Spielernamen, Welt-/Allianznamen und Nachrichten bleiben unverändert.

Die Abnahme erfolgt in der tatsächlichen Haupt-App unter `/city#city`, einschließlich Gebäudeaktion und den betroffenen Fenstern, mindestens bei 1280×800, 390×844 und 844×390; bei allgemeinen Menüänderungen zusätzlich bei 320 px Breite. Prüfen: geladene Bree-/Nunito-Schriften, Text- und Aktionskontrast, vollständige Bilder, korrekte semantische Farben, erreichbare Bedienflächen, Scrollen, fehlende Überlagerungen und Browserfehler. Browsernachweise ersetzen keine Prüfung auf einem physischen Mobilgerät. Aktuelle Umsetzungs- und Prüfergebnisse werden beim jeweiligen Rollout dokumentiert; diese Designentscheidung allein bescheinigt keinen abgeschlossenen Rollout.
