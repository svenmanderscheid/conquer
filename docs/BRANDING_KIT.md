# Union of Kingdoms – Branding-Kit

## Verbindliche Designentscheidung

**Freigegeben vom Nutzer am 7. Oktober 2026: „Fantasy · Bree Serif + Warmes Creme & Königsgold“.** Diese Kombination gilt für die gesamte Spieloberfläche einschließlich Anmeldung, Stadt-/Welt-HUD, Menüs, Dialogen, Gebäudeaktionen, Forschung, Ausbildung, Inventar und Karten-Overlays. Neue öffentliche Markenmaterialien verwenden dieselbe Schrift- und Farbfamilie. Der öffentliche Name lautet stets **Union of Kingdoms**; „Conquer“ bleibt ein interner technischer Name.

**Veröffentlichte Spieloberfläche:** Seit dem 7. Oktober 2026 ist die Cremeweiß-/Gold-Umstellung mit Commit [`4655c7ed`](https://github.com/svenmanderscheid/conquer/commit/4655c7ed7544f9f2d110cd789870a23c32a048af) live. Die bestätigte Abnahme steht unter „Veröffentlichter Stand und Nachweise“. Separate Logo- und Symbolfreigaben sind damit nicht automatisch als veröffentlicht bestätigt.

Die jüngste Auswahl verbindet **warme cremeweiße Flächen und Köpfe**, sehr helle Ockerschattierung, gezieltes Königsgold, plastisch drückbare Knöpfe und grüne Hauptaktionen mit der Schriftwahl **Fantasy · Bree Serif**. Sie ersetzt das zuvor am selben Tag gewählte Klare Weiß und Apricotlicht mit kräftig violetten Köpfen sowie die älteren Beige-/Violettwerte und Almendra-/Lora-Schriften. Cremeweiß bleibt die dominante Fläche; Gold gliedert Auswahl und kleine Schmuckdetails. Alte Screenshots, Entwürfe und Prüfberichte belegen den damaligen Stand, aber überschreiben diese Entscheidung nicht.

Die Verwaltungsoberfläche behält ihre gesonderte Freigabe vom 5. Oktober 2026: Systemschrift, neutrale helle Flächen und blaue Aktionen unter `body.admin-modern` mit `assets/css/admin-modern.css`. Regeln für Sprache, semantische Farben, Touch-Bedienung und unsichtbare Scrollleisten gelten auch dort.

Die Auswahl ist in diesen Projektentwürfen nachvollziehbar:

| Referenz | Gewählte Einstellung |
| --- | --- |
| Jüngste Auswahl vom 7. Oktober 2026 | Warmes Cremeweiß mit Königsgold; verbindliche Werte in diesem Kit |
| [main-screen-clean-white-preview.html](../output/menu-depth/main-screen-clean-white-preview.html) | Frühere weiße Basis für Hauptbildschirm und Gebäudeaktionen; die Farbwahl ist durch Warmes Creme & Königsgold ersetzt |
| [font-variants.html](../output/menu-depth/font-variants.html) | `data-typeface="bree"` – Fantasy · Bree Serif |
| [pressable-menus-preview.html](../output/menu-depth/pressable-menus-preview.html) | Druckbewegung in Ausbildung, Forschung und Inventar; die dortigen Farbvarianten sind keine aktuelle Farbvorgabe |

Die folgenden Werte halten die Entscheidung dauerhaft fest, auch wenn die Vorschauen später archiviert werden. Die Vorschauen sind Gestaltungsmuster; ihre Demonstrationsdaten und externen Schriftlinks gehören nicht in die produktive App.

## Markenwirkung

Die Oberfläche wirkt hell, warm und aufgeräumt. Cremeweiße Leseflächen lassen die bunte Stadt, Figuren, Werte und Aktionen klar hervortreten. Helle Köpfe mit dunklen Überschriften gliedern die Fenster. Abgerundete Kanten, eine helle Oberkante und ein kurzer warmer Schatten geben Knöpfen und Karten weiche plastische Tiefe. Bree Serif und die gezeichneten Symbole bewahren den Fantasycharakter. Königsgold bleibt ein gezielter Akzent an ausgewählten Reitern, feinen Kanten und kleinen Schmuckdetails.

Größere Flächen verwenden nur eine sehr helle Ockerschattierung, ohne Apricot-, Rosa- oder Fliederlicht. Bereits freigegebene Figuren, Gebäude, Landschaften und Symbolfamilien behalten ihre gezeichnete Identität und ihre Farben. Die Stilreferenz der Welt bleibt `assets/art/village2.png` mit den Regeln in [ART_DIRECTION.md](ART_DIRECTION.md).

## Farben

| Rolle | Gemeinsamer Wert | Farbe |
| --- | --- | --- |
| Cremeweiße Grundfläche | `--ui-paper` | `#FFF7E7` |
| Helle Karte und Eingabe | `--ui-card`, `--ui-card-light` | `#FFFCF3` |
| Eingelassene helle Fläche | `--ui-inset` | `#F0E5CF` |
| Deaktivierte Fläche | `--ui-disabled` | `#E3D3B5` |
| Haupttext und Kopftext | `--ui-ink`, `--ui-head-ink` | `#3A3529` |
| Ergänzender Text | `--ui-muted` | `#68604F` |
| Warmer Kartenrand | `--ui-line` | `#D9C7A4` |
| Äußerer Rahmen | `--ui-frame` | `#C9B48E` |
| Lesbarer Textakzent | `--ui-primary` | `#756A55` |
| Helle neutrale Akzente | `--ui-primary-light` | `#E3D3B5` |
| Dunkle Werte | `--ui-primary-dark` | `#3A3529` |
| Dezente untere Schattierung | `--ui-primary-base` | `#F0E5CF` |
| Plastische Tiefenkante | `--ui-primary-edge` | `#B39B76` |
| Obere Lichtkante | `--ui-primary-highlight` | `#FFFCF3` |
| Helle Kartenakzente | `--ui-window-head` | `#FAF2E1` |
| Lichtkante heller Karten | `--ui-window-head-light` | `#FFFCF3` |
| Tiefenkante heller Karten | `--ui-window-head-shadow` | `#B39B76` |
| Heller Fensterrahmen | `--ui-window-frame` | `#FFF7E7` |
| Äußere Fensterkante | `--ui-window-frame-edge` | `#C9B48E` |
| Dekoratives Königsgold | `--ui-accent-gold` | `#D6A64D` |
| Helle goldene Auswahlfläche | `--ui-accent-gold-soft` | `#FFF5DE` |
| Text auf heller goldener Auswahl | `--ui-accent-gold-ink` | `#704600` |
| Fokus und Auswahlkante | `--ui-accent-gold-edge` | `#8B611B` |
| Grüne Aktionsoberkante | `--ui-action-green-light` | `#B6E084` |
| Helle grüne Aktionsfläche | `--ui-action-green` | `#82C34A` |
| Unterer Verlauf grüner Aktionen | `--ui-action-green-base` | `#72B641` |
| Text auf grünen Aktionen | `--ui-action-green-ink` | `#203B18` |
| Tiefe grüner Aktionen | `--ui-action-green-edge` | `#4C7A2D` |

Der cremeweiße Fensterkopf `--ui-head-surface` entspricht der gewählten Vorlage:

```css
linear-gradient(#fffcf3, #fffcf3 23%, #faf2e1)
```

Kopf und Schrift werden gemeinsam gesetzt: `background: var(--ui-head-surface); color: var(--ui-head-ink);`. Die Grundfläche `--ui-surface` verläuft von `#FFFCF3` nach `#FAF2E1`, `--ui-surface-inset` von `#FAF2E1` nach `#F0E5CF` und `--ui-surface-footer` von `#FFFCF3` nach `#F8EDDA`. Ausgewählte Reiter verwenden `--ui-selected-surface` mit `#FFFCF3` → `#FFF5DE`, Auswahltext `#704600` und einer deutlich sichtbaren Kante `#8B611B`. Die Auswahl muss außerdem durch einen zugänglichen Zustand wie `aria-pressed` erkennbar bleiben.

`--ui-primary` bleibt mit `#756A55` bewusst ein lesbarer dunkler Textakzent, da bestehende Komponenten diesen Wert auch für Links und Text verwenden. Der Token darf nicht auf eine helle Flächenfarbe oder das dekorative Königsgold gesetzt werden. Köpfe und Auswahlen haben eigene Flächentokens; helle Schrift auf diesen Flächen ist ausgeschlossen. Die Tiefenkante und `#D6A64D` sind Dekoration, keine Textfarben auf Cremeweiß. Text auf kräftigem Gold verwendet `--ui-ink`; `--ui-accent-gold-ink` ist für die helle Auswahlfläche vorgesehen. Die neuen `--ui-accent-gold*`-Tokens verändern nicht `--ui-gold` und `--ui-gold-soft` für Währungen, Käufe, Belohnungen und erfüllte Talente.

Bestätigende Aktionen verlaufen von `#B6E084` über `#82C34A` bei 18 % bis `#72B641`, mit dunklem Text `#203B18`. Ihre Lichtkante verwendet `#D9F3AA`, die Tiefenkante `#4C7A2D`. Neutrale Knöpfe bleiben cremeweiß mit warmem Rand; Goldaktionen behalten ihre erkennbare Kauf-/Belohnungsbedeutung und sichtbare Preise.

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
- Beim Drücken senkt sich der Knopf um **3 px** ab und sein unterer Schatten wird kürzer; beim Loslassen kehrt er weich zurück. Die gemeinsame Darstellung verwendet **65 ms** beim Drücken und **170 ms** bei der Rückkehr. Maus, Touch und Tastatur erhalten denselben erkennbaren Zustand. Abgebrochene Gesten und deaktivierte Knöpfe lösen keine Aktion aus; die Darstellung darf keinen zusätzlichen Klick erzeugen. Bei `prefers-reduced-motion: reduce` oder der Spieleinstellung für reduzierte Bewegung entfällt die zeitliche Animation; der gedrückte Zustand bleibt unmittelbar erkennbar.
- Eingelassene Bereiche bleiben hell. Die Lichtwirkung darf weder eine ganze Figur verfärben noch Statusfarben überdecken. Schatten bleiben so klein, dass benachbarte Ziele und Beschriftungen nicht überlagert werden.
- Gebäudenamen und Stufen erscheinen in der Stadt nur bei Auswahl in **genau einer bestehenden kompakten Plakette über dem Gebäude** (Nutzerwunsch vom 8. Oktober 2026, horizontal zentriert mit 8 px Abstand). Schrift, Innenabstände und Form der Plakette bleiben erhalten; das frühere zusätzliche Namensbanner entfällt. Es entsteht keine neue untere Infokarte und kein zusätzlicher Auswahlring. Die drei bestehenden Aktionsknöpfe stehen mit Abstand unter dem Gebäude, bei knapper Höhe über der Plakette, und verdecken weder Name noch Stufe. Laufende Aufträge und ihre Statusanzeigen bleiben unabhängig von der Namensanzeige sichtbar. Ein Tipp auf freie Fläche, Schließen, Escape oder das Verschieben der Stadt hebt die Auswahl auf.
- Aktiv, ausgewählt, gesperrt, fehlend und erfüllt bleiben durch Text, Symbol oder Form erkennbar. Normale Textkontraste erreichen mindestens 4,5:1. Vorder- und Hintergrund werden gemeinsam festgelegt; deaktivierte Texte werden nicht durch pauschale Deckkraft unlesbar gemacht.
- Auf der dunkleren deaktivierten Fläche `--ui-disabled` wird `--ui-ink` statt `--ui-muted` verwendet. Ergänzende Texte auf erfüllten grünen Voraussetzungskarten verwenden `--ui-muted` statt `--ui-primary`; die semantische grüne Fläche bleibt erhalten.
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

Browser-Metadaten und das Web-App-Manifest verwenden `#FFF7E7` als `theme-color` / `theme_color` und `background_color`. Die mobile Konfiguration und Android-Startfläche übernehmen ebenfalls dieses Cremeweiß; dunkle Schrift und Symbole sichern den Kontrast auf heller Systemoberfläche. Änderungen an diesen Quelldateien ersetzen keinen nativen Build und keine Geräteprüfung; für diese Farbumstellung wird kein neuer nativer Build erstellt.

## Geltung und Prüfung

[AGENTS.md](../AGENTS.md), dieses Kit und [UI_STYLE_GUIDE.md](UI_STYLE_GUIDE.md) beschreiben dieselbe verbindliche Auswahl. Bei Interfacefragen ersetzt diese Entscheidung vom 7. Oktober ältere Farbreferenzen. [ART_DIRECTION.md](ART_DIRECTION.md) bleibt für Illustrationen, [MOBILE_APP_STRATEGY.md](MOBILE_APP_STRATEGY.md) für die gemeinsame App maßgeblich.

Die Anpassung erhält Spielregeln, Werte, Inhalte, bestätigungspflichtige Aktionen und bestehenden Freigabestatus. Die ausdrücklich abgewählten Inventarfilter „Owned“ und „All items“ entfallen; Kategorien, Gegenstandsdetails, Mengenwahl, „Use All“ und Fundorte bleiben erreichbar. Englisch bleibt Standard und Rückfallebene; Deutsch und Französisch verwenden das gemeinsame Sprachsystem. Spielernamen, Welt-/Allianznamen und Nachrichten bleiben unverändert.

Die Abnahme erfolgt in der tatsächlichen Haupt-App unter `/city#city`, einschließlich Gebäudeaktion und den betroffenen Fenstern, mindestens bei 1280×800, 390×844 und 844×390; bei allgemeinen Menüänderungen zusätzlich bei 320 px Breite. Prüfen: geladene Bree-/Nunito-Schriften, Text- und Aktionskontrast, vollständige Bilder, korrekte semantische Farben, erreichbare Bedienflächen, Scrollen, fehlende Überlagerungen und Browserfehler. Browsernachweise ersetzen keine Prüfung auf einem physischen Mobilgerät. Aktuelle Umsetzungs- und Prüfergebnisse werden beim jeweiligen Rollout dokumentiert; diese Designentscheidung allein bescheinigt keinen abgeschlossenen Rollout.

## Veröffentlichter Stand und Nachweise

Die Spieloberfläche aus Commit `4655c7ed7544f9f2d110cd789870a23c32a048af` wurde am **7. Oktober 2026** auf GitHub und dem Live-Server bestätigt. Die Kontrolle auf **unionofkingdoms.com** und **play.unionofkingdoms.com** bestand **16 von 16 Prüfungen**: ausgelieferte Stylesheets, Stadtrenderer, Druckanimation, Service Worker und Offline-Seite stimmen mit dem Commit überein; Manifest und Einstiegsseiten verwenden `#FFF7E7`. Die Offline-Cache-Version dieses Releases lautet `union-of-kingdoms-public-v12-cream-gold`.

Vor der Veröffentlichung wurden **22 Menüs in fünf Bildschirmformaten (110 Ansichten)** geprüft: 1280×800, 390×844, 320×568, 844×390 und 568×320. Die Prüfung umfasst tatsächliche Bree-Serif-/Nunito-Schriften, Kontrast, erreichbare Aktionen sowie Welt, Anmeldung und die eigenständige Verwaltung. Die Gebäudeauswahl bestand zusätzlich die Prüfung von **16 Gebäuden in vier Formaten** und der echten Haupt-App in **sechs Formaten**, einschließlich der einmaligen Namensplakette, sichtbarer Aufträge und nicht überlagernder Aktionen. Der Chat samt Druckzustand und reduzierter Bewegung wurde in fünf Formaten geprüft. Diese Angaben belegen Browserprüfungen, keinen neuen nativen Build oder Test auf einem physischen Mobilgerät.

Lokale Prüfbelege dieses Releases liegen unter `output/cream-gold-release/`: `live-verification.json`, `menus/report.json` und `city-validation.json` mit den geprüften CSS-/JavaScript-Hashes. Die zusätzliche Chatprüfung liegt unter `output/playwright/warm-cream-chat/`. Diese lokalen Prüfarbeitsdateien werden nicht automatisch mit dem Repository veröffentlicht; der freigegebene Commit und die Regeln dieses Kits bleiben die dauerhafte Referenz.
