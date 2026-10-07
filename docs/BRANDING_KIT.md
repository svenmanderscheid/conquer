# Union of Kingdoms – Branding-Kit

## Verbindliche Designentscheidung

**Freigegeben vom Nutzer am 7. Oktober 2026: „Fantasy · Bree Serif + Klares Weiß“.** Diese Kombination gilt für die gesamte Spieloberfläche einschließlich Anmeldung, Stadt-/Welt-HUD, Menüs, Dialogen, Gebäudeaktionen, Forschung, Ausbildung, Inventar und Karten-Overlays. Neue öffentliche Markenmaterialien verwenden dieselbe Schrift- und Farbfamilie. Der öffentliche Name lautet stets **Union of Kingdoms**; „Conquer“ bleibt ein interner technischer Name.

Die jüngste Auswahl verbindet **klare weiße Flächen und Köpfe**, weiche graue Schatten, plastisch drückbare Knöpfe und grüne Hauptaktionen mit der Schriftwahl **Fantasy · Bree Serif**. Sie ersetzt die zuvor am selben Tag gewählte Kombination Apricotlicht mit kräftig violetten Köpfen sowie die älteren Beige-/Violettwerte und Almendra-/Lora-Schriften. Alte Screenshots, Entwürfe und Prüfberichte belegen den damaligen Stand, aber überschreiben diese Entscheidung nicht.

Die Verwaltungsoberfläche behält ihre gesonderte Freigabe vom 5. Oktober 2026: Systemschrift, neutrale helle Flächen und blaue Aktionen unter `body.admin-modern` mit `assets/css/admin-modern.css`. Regeln für Sprache, semantische Farben, Touch-Bedienung und unsichtbare Scrollleisten gelten auch dort.

Die Auswahl ist in diesen Projektentwürfen nachvollziehbar:

| Referenz | Gewählte Einstellung |
| --- | --- |
| [main-screen-clean-white-preview.html](../output/menu-depth/main-screen-clean-white-preview.html) | Klares Weiß für Hauptbildschirm und Gebäudeaktionen, graue Tiefe, dunkle Schrift |
| [font-variants.html](../output/menu-depth/font-variants.html) | `data-typeface="bree"` – Fantasy · Bree Serif |
| [pressable-menus-preview.html](../output/menu-depth/pressable-menus-preview.html) | Druckbewegung in Ausbildung, Forschung und Inventar; die dortigen Farbvarianten sind keine aktuelle Farbvorgabe |

Die folgenden Werte halten die Entscheidung dauerhaft fest, auch wenn die Vorschauen später archiviert werden. Die Vorschauen sind Gestaltungsmuster; ihre Demonstrationsdaten und externen Schriftlinks gehören nicht in die produktive App.

## Markenwirkung

Die Oberfläche wirkt hell, ruhig und aufgeräumt. Weiße Leseflächen lassen die bunte Stadt, Figuren, Werte und Aktionen klar hervortreten. Weiße Köpfe mit dunklen Überschriften gliedern die Fenster. Abgerundete Kanten, eine helle Oberkante und ein kurzer grauer Schatten geben Knöpfen und Karten weiche plastische Tiefe. Bree Serif und die gezeichneten Symbole bewahren den Fantasycharakter. Gold bleibt ein gezielter Akzent.

Größere Flächen verwenden nur eine dezente neutrale Schattierung, ohne Apricot- oder Fliederlicht. Bereits freigegebene Figuren, Gebäude, Landschaften und Symbolfamilien behalten ihre gezeichnete Identität und ihre Farben. Die Stilreferenz der Welt bleibt `assets/art/village2.png` mit den Regeln in [ART_DIRECTION.md](ART_DIRECTION.md).

## Farben

| Rolle | Gemeinsamer Wert | Farbe |
| --- | --- | --- |
| Weiße Grundfläche | `--ui-paper` | `#FFFFFF` |
| Weiße Karte und Eingabe | `--ui-card`, `--ui-card-light` | `#FFFFFF` |
| Eingelassene helle Fläche | `--ui-inset` | `#F0F3F6` |
| Deaktivierte Fläche | `--ui-disabled` | `#E5EAF0` |
| Haupttext und Kopftext | `--ui-ink`, `--ui-head-ink` | `#293441` |
| Ergänzender Text | `--ui-muted` | `#536173` |
| Neutraler Kartenrand | `--ui-line` | `#D7DDE4` |
| Äußerer Rahmen | `--ui-frame` | `#C5CDD5` |
| Lesbarer Textakzent | `--ui-primary` | `#637080` |
| Helle neutrale Akzente | `--ui-primary-light` | `#E5EAF0` |
| Dunkle Werte und Fokus | `--ui-primary-dark` | `#293441` |
| Dezente untere Schattierung | `--ui-primary-base` | `#F0F3F6` |
| Plastische Tiefenkante | `--ui-primary-edge` | `#AAB4C0` |
| Obere Lichtkante | `--ui-primary-highlight` | `#FFFFFF` |
| Helle Kartenakzente | `--ui-window-head` | `#F0F3F6` |
| Lichtkante heller Karten | `--ui-window-head-light` | `#FFFFFF` |
| Tiefenkante heller Karten | `--ui-window-head-shadow` | `#AAB4C0` |
| Heller Fensterrahmen | `--ui-window-frame` | `#FFFFFF` |
| Äußere Fensterkante | `--ui-window-frame-edge` | `#C5CDD5` |
| Grüne Aktionsoberkante | `--ui-action-green-light` | `#B6E084` |
| Helle grüne Aktionsfläche | `--ui-action-green` | `#82C34A` |
| Unterer Verlauf grüner Aktionen | `--ui-action-green-base` | `#72B641` |
| Text auf grünen Aktionen | `--ui-action-green-ink` | `#203B18` |
| Tiefe grüner Aktionen | `--ui-action-green-edge` | `#4C7A2D` |

Der weiße Fensterkopf `--ui-head-surface` entspricht der gewählten Vorlage:

```css
linear-gradient(#ffffff, #ffffff 23%, #f0f3f6)
```

Kopf und Schrift werden gemeinsam gesetzt: `background: var(--ui-head-surface); color: var(--ui-head-ink);`. Die Grundfläche `--ui-surface` verläuft von `#FFFFFF` nach `#F7F9FC`, `--ui-surface-inset` von `#F7F9FC` nach `#F0F3F6` und `--ui-surface-footer` von `#FFFFFF` nach `#F4F6F8`. Ausgewählte Reiter verwenden `--ui-selected-surface` mit `#F0F3F6` → `#E5EAF0`, dunkle Schrift und einen deutlich sichtbaren Rand. Die Auswahl muss außerdem durch einen zugänglichen Zustand wie `aria-pressed` erkennbar bleiben.

`--ui-primary` bleibt bewusst ein lesbares Schiefergrau, da bestehende Komponenten diesen Wert auch für Links, Text und Fokus verwenden. Der Token darf nicht pauschal auf Weiß gesetzt werden. Weiße Köpfe und neutrale Auswahlen haben eigene Flächentokens; helle Schrift auf diesen Flächen ist ausgeschlossen. Die Tiefenkante ist Dekoration, keine Textfläche.

Bestätigende Aktionen verlaufen von `#B6E084` über `#82C34A` bei 18 % bis `#72B641`, mit dunklem Text `#203B18`. Ihre Lichtkante verwendet `#D9F3AA`, die Tiefenkante `#4C7A2D`. Neutrale Knöpfe bleiben weiß mit grauem Rand; Goldaktionen behalten ihre erkennbare Kauf-/Belohnungsbedeutung und sichtbare Preise.

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

- Fenster haben weiche Ecken, einen hellen Rahmen, eine schmale graue Kante und einen ruhigen Schatten. Bestehende passende Geometrien bleiben erhalten; etwa 16–20 px Radius für Fenster, 12–14 px für Karten und 9–12 px für Knöpfe dienen als Orientierung.
- Tiefe entsteht aus einer hellen Oberkante und einer kurzen unteren Kante; kleine Bedienelemente benötigen weniger Tiefe als die Hauptaktion. Keine zusätzliche gläserne, metallische oder fotorealistische Materialhaut.
- Beim Drücken senkt sich der Knopf kurz ab und sein unterer Schatten wird kürzer; beim Loslassen kehrt er weich zurück. Maus, Touch und Tastatur erhalten denselben erkennbaren Zustand. Abgebrochene Gesten und deaktivierte Knöpfe lösen keine Aktion aus; die Darstellung darf keinen zusätzlichen Klick erzeugen. Bei `prefers-reduced-motion: reduce` entfällt das bewegte Nachfedern.
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

Symbole proportional skalieren, freigegebene Beschnitte und Maskierungsabstände bewahren, keine Schrift über das kleine App-Symbol legen. Der Export verwendet künftig `#FFFFFF` als einheitlichen Hintergrund für Apple-/maskierbare Symbole. Die freigegebenen Bildmaster werden durch die Farbentscheidung nicht neu gezeichnet; die Hintergrundkonstante greift beim nächsten bewussten Symbolexport. Textlich gesetzte Markennamen und neue Begleitmaterialien verwenden die obige Typografie. Die zentrale Gestaltungsschicht bleibt `assets/css/village-theme.css`; gemeinsame Fontdefinitionen bleiben in `assets/css/fantasy-fonts.css`.

Browser-Metadaten und das Web-App-Manifest verwenden `#FFFFFF` als `theme-color` / `theme_color` und `background_color`. Die mobile Konfiguration und Android-Startfläche übernehmen ebenfalls Weiß; dunkle Schrift und Symbole sichern den Kontrast auf heller Systemoberfläche. Änderungen an diesen Quelldateien ersetzen keinen nativen Build und keine Geräteprüfung.

## Geltung und Prüfung

[AGENTS.md](../AGENTS.md), dieses Kit und [UI_STYLE_GUIDE.md](UI_STYLE_GUIDE.md) beschreiben dieselbe verbindliche Auswahl. Bei Interfacefragen ersetzt diese Entscheidung vom 7. Oktober ältere Farbreferenzen. [ART_DIRECTION.md](ART_DIRECTION.md) bleibt für Illustrationen, [MOBILE_APP_STRATEGY.md](MOBILE_APP_STRATEGY.md) für die gemeinsame App maßgeblich.

Die Anpassung erhält Spielregeln, Werte, Inhalte, bestätigungspflichtige Aktionen und bestehenden Freigabestatus. Die ausdrücklich abgewählten Inventarfilter „Owned“ und „All items“ entfallen; Kategorien, Gegenstandsdetails, Mengenwahl, „Use All“ und Fundorte bleiben erreichbar. Englisch bleibt Standard und Rückfallebene; Deutsch und Französisch verwenden das gemeinsame Sprachsystem. Spielernamen, Welt-/Allianznamen und Nachrichten bleiben unverändert.

Die Abnahme erfolgt in der tatsächlichen Haupt-App unter `/city#city`, einschließlich Gebäudeaktion und den betroffenen Fenstern, mindestens bei 1280×800, 390×844 und 844×390; bei allgemeinen Menüänderungen zusätzlich bei 320 px Breite. Prüfen: geladene Bree-/Nunito-Schriften, Text- und Aktionskontrast, vollständige Bilder, korrekte semantische Farben, erreichbare Bedienflächen, Scrollen, fehlende Überlagerungen und Browserfehler. Browsernachweise ersetzen keine Prüfung auf einem physischen Mobilgerät. Aktuelle Umsetzungs- und Prüfergebnisse werden beim jeweiligen Rollout dokumentiert; diese Designentscheidung allein bescheinigt keinen abgeschlossenen Rollout.
