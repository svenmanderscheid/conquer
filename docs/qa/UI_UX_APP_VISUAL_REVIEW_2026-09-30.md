# Union of Kingdoms – Sichtprüfung der echten App, 30. September 2026

**Lesestand:** Die abschließende Bewertung steht im Abschnitt „Finaler Prüfsnapshot“ am Ende. Frühere Messungen bleiben historisch erhalten. Insbesondere ist das frühere 35-/38-px-Marsch-Touchziel im finalen Fixture auf 44 px verbessert; während dieses letzten Laufs änderten sich nochmals vier Workspace-Dateien.

## Umfang und Stand

Die vorhandene violett/beige Gestaltung und die lokalen Schriften passen zusammen. Die wichtigsten bestätigten Verbesserungen betreffen sehr kleine mobile Beschriftungen, einen kaum sichtbaren Inventar-Knopf und unvollständige englische Texte. Eine neue Farbpalette oder Schrift ist daraus nicht erforderlich.

Grundlage ist der vom Hauptagenten ausgeführte echte App-Lauf in fünf CSS-Ansichten: 1280 × 800, 390 × 844, 320 × 568, 844 × 390 und 568 × 320. Der gesicherte [Baselinebericht](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix/baseline-report.json) enthält **199 Beobachtungen, darunter 190 erfasste Zustände**, 23 Haupt-App-Panels sowie Stadt, Welt, Login, Menü und echte Gebäude-/Marschdialoge. 29 konkrete Screenshotdateien wurden in dieser Teilprüfung manuell angesehen; die vollständige Liste mit Zeitstempeln und SHA-256 steht in [app-visual-evidence.json](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/design/app-visual-evidence.json). 190 Erfassungen bedeuten ausdrücklich nicht 190 manuell geprüfte Bilder.

Die Baseline endete durch eine falsche Sichtbarkeitsannahme des Testprogramms beim Sprachwechsel. FR-, Reflow- und Backoffice-Ergebnisse sowie der falsche Login wurden separat im ergänzenden `app-matrix-remaining` erhoben; die abgeschlossene Ergänzung folgt am Ende dieses Berichts. Das ist keine Prüfung auf einem physischen iOS-/Android-Gerät, mit Bildschirmleser oder echter Bildschirmtastatur.

Die Berichtdatei wurde am 2026-09-30 um 12:21:56 UTC gespeichert. Ihr gesicherter SHA-256 lautet `6f0e27202430dcec0c1b9910acceb64bfc4274c5704a7065ed6ab390435c8225`. Im Arbeitsverzeichnis liefen unabhängig Änderungen an Backoffice, Sprachkatalogen und CSS. Die zusätzlich protokollierten Quellhashes sind **nachträgliche Lesestände**, kein Nachweis einer gemeinsamen unveränderten Revision für den gesamten Testlauf. Screenshots und Baseline bleiben als historischer Beleg erhalten.

## Bestätigte Befunde

### P2 – Der Inventar-Übersichtsknopf ist visuell kaum erkennbar

Im [Inventar bei 320 × 568](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix/inventory-320x568.png) und [Desktop](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix/inventory-1280x800.png) ist rechts neben dem Titel ein fast weißes Diagrammsymbol auf heller Cremefläche zu sehen. Der Knopf ist aktiv und per Zeigertest erreichbar, wirkt aber ohne gut sichtbares Symbol wie eine leere Fläche. Sein zugänglicher Name ist bereits „Overview: Raw materials and accelerators“.

Die Kaskade erklärt die Darstellung: [inventory-reference.css:167](C:/xampp/htdocs/conquer/assets/css/inventory-reference.css:167) zeichnet das SVG mit `stroke:currentColor`. [village-theme.css:177](C:/xampp/htdocs/conquer/assets/css/village-theme.css:177) legt für den Knopf eine helle Karte und dunkle Schrift fest; die spätere pauschale Regel für `svg,path` innerhalb der Überschrift setzt aber `color:var(--ui-card-light)` ([village-theme.css:3423](C:/xampp/htdocs/conquer/assets/css/village-theme.css:3423)). Somit entsteht `#FFFCF6` auf `#FBF6EC`, rechnerisch **1,052:1**, durch den Screenshot bestätigt.

**Empfehlung:** Symbole heller Kopfknöpfe ausdrücklich in `--ui-ink` zeichnen. Der violette Fenstertitel kann hell bleiben. Bei ähnlichen Kopfknöpfen dieselbe Kaskade prüfen, ohne alle Überschrift-SVGs global umzufärben.

### P2 – Im schmalen HUD werden zentrale Informationen zu klein

Die echten Computed Styles bestätigen die statische Vorprüfung: In [Stadt 320 × 568](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix/city-320x568.png) sind sämtliche unteren Navigationsnamen **8 px groß mit 8 px Zeilenhöhe**; „Gems“ ist **7 px**. Im [Querformat 568 × 320](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix/city-568x320.png) sinkt die Aktionsenergie `200 / 200` auf **7 px mit 7 px Zeilenhöhe**, die Machtanzeige auf 8 px. Dies sind aktive sichtbare Informationen, keine deaktivierten Knöpfe. Das ist keine aus einer alten überschriebenen CSS-Regel abgeleitete Behauptung.

Die 8-px-Bestandszahlen in [Armee 320 × 568](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix/army-320x568.png) und 9-px-Statuszeilen in den unteren Schulknöpfen zeigen dasselbe Problem. Die fantasievolle Schrift erhöht bei diesen kleinen Größen den Leseaufwand.

**Empfehlung:** Zuerst Platz und Informationsmenge ordnen, dann die mobilen Mindestgrößen anheben. Für Navigation/Status zunächst 11–12 px erproben, wichtige Ressourcen/Zeitangaben größer lassen. Almendra und Lora dabei beibehalten; keine weitere Komprimierung langer Übersetzungen durch kleinere Schrift. Diese Größen sind Gestaltungsvorschläge, keine behaupteten gesetzlichen Mindestwerte.

### P2 – Der echte Marschabschluss unterschreitet das eigene Touchziel

Der aktive `#march-confirm` misst im echten Grumwald-Dialog **123,5 × 38 px** bei [320 × 568](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix/grumwald-march-320x568.png) und **162,4 × 35 px** bei [568 × 320](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix/grumwald-march-568x320.png). In beiden Fällen liegt der Knopf vollständig im sichtbaren Bereich, ist nicht deaktiviert und sein Mittelpunkt trifft tatsächlich das Bedienelement. Der Befund lautet daher „kleines bestätigtes Touchziel“, nicht „unbedienbar“.

**Empfehlung:** Die entscheidende Senden-Aktion auf das empfohlene 44-px-Touchziel bringen, besonders im niedrigen Querformat. Platz dafür aus Dekoration und doppelten Kennzahlen gewinnen. Unter 44 px allein beweist **keinen automatischen WCAG-AA-Verstoß**. Der 30-px-Marschrückruf aus der isolierten Designprüfung ist zusätzlich im echten Rückrufzustand zu bestätigen; die Baseline löst bewusst keinen Marsch aus.

### P2 – Die englische Oberfläche bleibt in wichtigen Flüssen gemischt

Die App startet trotz Browsergebietsschema `de-DE` korrekt mit `html lang="en"`. Die folgenden Texte stammen sichtbar aus der Oberfläche und nicht aus Spielernamen oder Chatnachrichten:

| Ansicht und Beleg | Sichtbarer Resttext | Auswirkung / Ansatz |
|---|---|---|
| [Aufgaben 320 × 568](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix/quests-320x568.png) | `Abholbereit (1)`, `Neue Aufgaben in` | Ein aktiver Filter und der Rücksetzzeitpunkt sind nicht durchgehend verständlich. Dynamischen Gesamttext über Locale erzeugen; Quelle [mvp-panels.js:282](C:/xampp/htdocs/conquer/assets/js/mvp-panels.js:282). |
| [Grumwald Desktop](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix/grumwald-march-1280x800.png) | `Losses (0 % Kampfglück)`; `Echter Kampf: −10 % to +10 % Glück; Verluste können abweichen.` | Gerade die Einordnung der Kampfprognose bleibt gemischt. Vorabhinweise im Marschdialog explizit lokalisieren; Quelle [march-panel.js:37](C:/xampp/htdocs/conquer/assets/js/march-panel.js:37). Der gesonderte neue Kampfrechner ist ein anderer Renderer. |
| [Gebäude 320 × 568](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix/building-upgrade-320x568.png) | Aktive Schaltfläche `Allianz öffnen →` | Die Folgeaktion aus dem Gebäudedialog ist unübersetzt. Quelle [game.js:412](C:/xampp/htdocs/conquer/assets/js/game.js:412); der gemeinsame Schlüssel `ui.open_alliance` existiert bereits. |
| [Markt 320 × 568](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix/market-320x568.png) | `+1,100 Nahrung`, `5,000 Edelsteine`, `100,000,000 Stein` | Ressourcen-/Preisbezeichnungen passen nicht zur gewählten Sprache. Zahlen unverändert formatieren, Ressourcenbezeichnungen über das gemeinsame System ausgeben. |
| [Meisterschaft 320 × 568](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix/mastery-320x568.png) | `+1% / Rang`, `+2% / Rang` | Die Einheit pro Talentstufe bleibt Deutsch. Quelle [lord-talents.js:61](C:/xampp/htdocs/conquer/assets/js/lord-talents.js:61). |

Deutsche Chatnachrichten in [Community 320 × 568](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix/community-320x568.png), Spieler- und Reichsnamen sind dagegen **keine Übersetzungsfehler**. Ein automatischer Texttreffer ohne diesen Kontext genügt nicht.

### P3 – Fehlerbericht verwendet noch den internen Spielnamen

Der englische [Fehlerbericht auf Desktop](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix/bugreport-1280x800.png) sagt „Briefly describe what happened or what would make **Conquer** better.“ Der Text ist im englischen Katalog hinterlegt ([en.json:1005](C:/xampp/htdocs/conquer/data/i18n/en.json:1005)). Der sichtbare Name muss **Union of Kingdoms** sein. Im Beispieltext steht außerdem `e.g. B.`, ein erkennbares Übersetzungsartefakt. Die Bestätigungsfassung im Katalog verwendet ebenfalls noch Conquer; diese spätere Ansicht wurde nicht durch Absenden geprüft.

## Kontrastkandidaten, die nicht als bestätigte Verletzung gezählt werden

Das vorhandene Kontrastwerkzeug berücksichtigt einfache Hintergründe/Verläufe, aber keine vollständige Pixelkomposition aller Geschwisterbilder, Schatten und lokalen Verlaufspositionen. Seine drei gemeldeten Fälle wurden deshalb mit CSS und Originalbildern gegengeprüft:

| Kandidat | Einordnung |
|---|---|
| `.hud-edge-label`, rechnerisch 2,8:1 | Weiße HUD-Schrift liegt über gezeichneter Szene und besitzt dunkle Textschatten ([village-theme.css:675](C:/xampp/htdocs/conquer/assets/css/village-theme.css:675)). Der allein aus Vorfahren hergeleitete Hintergrund bildet die Szene nicht ab. **Kein belastbarer genauer Kontrastnachweis**; die sehr kleine Schrift bleibt ein separater bestätigter Befund. |
| `Open chat` Desktop, 3,92:1 | [world-chat.css:8](C:/xampp/htdocs/conquer/assets/css/world-chat.css:8) verwendet einen diagonalen Verlauf. Der hellste Verlaufspunkt liegt nicht überall unter dem Text. Vier protokollierte benachbarte Screenshotpixel ergeben mit der tatsächlichen Schriftfarbe 6,23–7,50:1. Dies widerlegt nicht jede mögliche problematische Pixelposition, zeigt aber, dass der pauschale 3,92-Wert **kein bestätigter Textkontrastfehler** ist. |
| Dekoratives `March` im Desktopkopf, 4,0:1 | Das blaue Kopfetikett in [march-panel.css:20](C:/xampp/htdocs/conquer/assets/css/march-panel.css:20) hat ebenfalls einen Verlauf. Drei dokumentierte benachbarte Bildpunkte ergeben 4,52–6,66:1. Keine vollständige Prüfung aller Glyphen; daher **nicht als bestätigte Verletzung** zählen. Es handelt sich auch nicht um den aktiven Senden-Knopf. |

Die Bildpunkte, RGB-Werte, Rechnung und unveränderten Bildhashes sind in [app-visual-evidence.json](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/design/app-visual-evidence.json) nachvollziehbar. Das fast unsichtbare Inventarsymbol ist davon unabhängig mit konkreter Kaskade und Screenshot bestätigt.

## Bestätigte Stärken und Prüfgrenzen

- Der echte Chrome-Schriftbericht weist **Almendra-Bold** für den Titel „Quests“ nach. In `100M` stammen die drei Ziffern aus **Lora-Bold**, das M aus Almendra-Bold. Die Schriftwahl funktioniert tatsächlich und ist nicht nur ein berechneter CSS-Familienname.
- Die angesehenen dichten Ansichten ordnen sich im schmalen Hochformat grundsätzlich zu lesbaren Karten. Im 568-px-Querformat verwendet der Marschdialog sinnvoll zwei Spalten; Inventar und Armee bleiben als eigener Vollbildbereich erkennbar. Längere Listen dürfen vertikal scrollen; abgeschnittene Inhalte unterhalb des ersten Bildausschnitts allein sind kein Fehler.
- Im Baselinebericht stehen keine Browser-JavaScriptfehler und keine registrierten lokalen HTTP-Antworten ab 400. Das gilt nur für diesen erfassten Lauf und ist **keine globale Stabilitätsaussage**.
- Der Gebäudedialog wurde mit dem Trainingsfixture auf **Stufe 30 / Maximalstufe** geöffnet. Es wurde keine freigeschaltete Ausbauaktion oder Kaufbestätigung geprüft. Sein nachträglicher Probe-Hinweis im Harness macht diese Grenze ausdrücklich.
- Der erste fehlgeschlagene Gebäudeklick bei 568 × 320 ist **kein belegter Produktfehler**: Der Szenenwechsel besitzt eine berechtigte etwa 330-ms-Sperre während des Aufdeckens; der ursprüngliche Harness wartete nur auf das Ende der Abdeckung. Die spätere Probe wartet auf den vollständigen Übergang und speichert bei Problemen konkrete Trefferelemente. Im vorhandenen Stadtbild ist der Allianzsaal erreichbar.
- Der Abbruch beim FR-Wechsel ist ebenfalls ein Harnessfehler: `#hud-research` ist bei geöffnetem Hilfepanel absichtlich unsichtbar, aber vorhanden und mit Daten versorgt. Die Fortsetzung prüft deshalb `attached` statt `visible`.
- Navigationszeiten sind lokale Browserbeobachtungen. Sie werden um DNS-/Verbindungs-/Anfragephasen ergänzt. Einzelne 15-Sekunden-Timeouts anderer Tests beweisen noch keine langsame Serververarbeitung; weder Mobilfunk noch reale Gerätelast wurden simuliert.

## Empfohlene Reihenfolge

1. Das Inventarsymbol und die englischen Aktions-/Kampf-/Preistexte korrigieren, dann dieselben Screenshots in EN/DE/FR wiederholen.
2. Kleine HUD-Schriften und Marsch-Touchziele gezielt überarbeiten; Platzgewinn und Lesbarkeit gemeinsam prüfen. Violett, Beige, Almendra und Lora beibehalten.
3. Fehler-/Leerzustände, Tastaturöffnung, reale Ausbauaktion und Rückruf anschließend in isolierten funktionalen Flüssen ergänzen; physischen Handytest vor App-Verpackung vorsehen.

Diese Teilprüfung änderte ausschließlich Audit-Test, technische Dokumentation und Prüfarbeitsdateien. Sie startete keinen Datenbanklauf und veränderte keine Produktdateien.

## Ergänzung: abgeschlossener Restlauf um 12:40:46 UTC

Der [Remaining-Bericht](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-remaining/report.json) enthält **73 weitere Beobachtungen, davon 64 erfasste Zustände**. Enthalten sind 15 tatsächlich ermittelte Admin-Routen in drei Ansichten (1280 × 800, 390 × 844, 568 × 320), je fünf DE-/FR-Panels, vier Reflow-Probes, ein falscher Login sowie die gezielte Gebäude-Wiederholung. Alle Routen-/Erfassungsprüfungen schlossen ohne Fehler ab; registrierte Browserfehler und HTTP-Antworten ab 400: jeweils null. Der Admin-Routenbestand wird mit Namen und URLs gespeichert und darf nicht leer sein.

Zusätzlich wurden **25 Screenshotdateien manuell angesehen**, darunter jede der 15 Admin-Routen mindestens in einer Ansicht, der Loginfehler, das Gebäude im Querformat, vier französische Panels sowie zwei Reflow-Probes. Zusammen mit der Baseline sind es 54 manuell betrachtete Bilddateien. [app-remaining-visual-evidence.json](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/design/app-remaining-visual-evidence.json) dokumentiert Bild-/Berichtshashes, Zeitstempel und Messdaten. Die unterschiedlichen Quellstände und Laufzeiten werden nicht zu einer unveränderten gemeinsamen Revision erklärt.

### P2 – Bestätigte Überdeckung im Gebäude-Querformat

Der erneute Gebäudeklick ist erfolgreich. Im echten [Gebäudedialog bei 568 × 320](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-remaining/building-upgrade-568x320.png) beginnt die Requirements-Spalte ungefähr bei x = 231 und überdeckt den rechten Teil der Gebäudeidentität. Das Statusfeld „Maximum“ ist nur teilweise lesbar. Dies ist ein **sichtbarer Layoutfehler nach erfolgreichem Öffnen**, unabhängig vom zuvor behobenen Test-Wartefehler.

Die CSS-Kaskade liefert einen konkreten Ansatz: Bei dieser Breite gilt noch die mobile innere Aufteilung mit 100-px-Bildspalte plus 12-px-Abstand ([village-theme.css:543](C:/xampp/htdocs/conquer/assets/css/village-theme.css:543)); zugleich schaltet die kurze Querformatregel die äußere Ansicht auf mindestens 220 px und 330 px breite Spalten ([village-theme.css:568](C:/xampp/htdocs/conquer/assets/css/village-theme.css:568)). Die Gebäudeidentität hat darin zu wenig Platz. `overflow:0` am Dialog bzw. Dokument hat diesen **inneren** Überdeckungsfehler nicht erkannt.

**Empfehlung:** Für schmale Querformate auch die innere Gebäudeidentität anpassen – beispielsweise Bild über die Identität setzen oder deren Aufteilung mit der verfügbaren Spaltenbreite ändern. Anschließend lange EN-/DE-/FR-Statuslabels und einen echten ausbaubaren Zustand prüfen. Hier bleibt die aktive Probe ausdrücklich Maximalstufe 30. Die aktiven Folge-/Schließen-Knöpfe sind erreichbar; aus dem überdeckten Text folgt keine gesamte Bedienblockade.

### Weitere bestätigte Sprachreste

Die Restprüfung bestätigt dieselbe Lokalisierungslücke auch außerhalb EN: [FR-Aufgaben](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-remaining/quests-fr-320x568.png) zeigen weiter `Abholbereit (1)` und `Neue Aufgaben in`; der Titel von [FR-Armee](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-remaining/army-fr-320x568.png) lautet `Train infantry`, obwohl Einheiten und übrige Aktionen Französisch sind. Im französischen Inventar und Hilfemenü werden lange Tabnamen mitten im Wort umbrochen (`Accélérate / urs`, `Bâtiment / s`). Dies ist lesbar, aber ein zusätzlicher Komfortpunkt für die mobile Tabaufteilung.

Die englische Verwaltung hat dieselbe Klasse bestätigter Resttexte:

| Beleg | Sichtbarer UI-Text |
|---|---|
| [Übersicht 390](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-remaining/admin-dashboard-390x844.png) | `0 Interessenten warten aktuell auf eine Einladung.` |
| [Belohnungen 390](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-remaining/admin-rewards-390x844.png) | `104 Quellen` |
| [Gegenstände 390](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-remaining/admin-items-390x844.png) | Aktiver Link `Drops bearbeiten →` |
| [Alpha-Schlüssel 390](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-remaining/admin-alpha-keys-390x844.png) | `Bezeichnung`, `Ablaufdatum (UTC, optional)`, Platzhalter `e.g. B. First test group` |
| [Warteliste 390](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-remaining/admin-alpha-waitlist-390x844.png) | Aktiver Knopf `CSV exportieren`, `Name oder E-Mail`, `0 Anmeldungen gefunden.` |
| [Landentwicklung 390](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-remaining/admin-lands-390x844.png) | `9216 Landteile · jeweils 8 × 8 fields`, zahlreiche `Länder`-Bestände |

Deutsche Nachrichten im Chatprotokoll und Allianz-/Spielernamen bleiben unverändert korrekt. Die native Datumseingabe kann sich nach dem Browsergebietsschema formatieren; ihr deutsches Muster allein wird nicht als Fehler der App-Übersetzung gezählt.

### Positive Befunde und verbleibende Bediengrenzen

- Der [Loginfehler 320 × 568](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-remaining/login-invalid-320x568.png) ist klar Englisch, zeigt eine 16-px-Fehlermeldung mit 23,2-px-Zeilenhöhe, behält den Namen und leert das Passwort. Beide 16-px-Eingabefelder haben zugeordnete Labels. Der erneute Login mit dem synthetischen Konto gelingt. Registrierung, Passwortwiederherstellung, echte Bildschirmtastatur und Fokusansage durch einen Bildschirmleser wurden nicht geprüft.
- Die Adminkarten übernehmen Palette und Schrift konsistent. Der [Belohnungsvergleich auf Desktop](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-remaining/admin-rewards-1280x800.png) stellt Mengen/Chancen klar tabellarisch gegenüber. Daten-/Formularänderungen wurden in dieser Matrix nicht abgesendet.
- Der Kopf der Verwaltung belegt bei 568 × 320 etwa **190–200 px Höhe**; im [Layouteditor](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-remaining/admin-layout-568x320.png) ist der eigentliche Arbeitsbereich zunächst unter dem ersten Bildausschnitt. **P3-Komfortempfehlung:** Identität, Sprachwahl und Weltwahl im kurzen Querformat kompakter anordnen. Die Seite darf normal scrollen; daraus wird keine Bedienblockade abgeleitet.
- Die mobile Spieler-/Allianzliste zeigt rechts nur Teile der breiten Tabelle. Dokumentüberlauf ist in allen Admin-Messungen null; das beweist jedoch weder vollständige Zellensichtbarkeit noch Touch-Bedienbarkeit jeder horizontalen Tabelle. Diese inneren Scrollwege sowie geöffnete mobile Navigation wurden nicht durch die bloßen Routenaufrufe vollständig geprüft.
- Alle vier Reflow-Probes (640 × 400 CSS px, DPR 2) zeigen keinen gemessenen horizontalen Dialog-/Dokumentüberlauf; Inventar und Hilfe wurden zusätzlich angesehen. Das bleibt ein **Geometrie-Proxy**, kein Test echten Browserzooms oder systemweiter Textvergrößerung.
- Die Matrix öffnet die Layouteditor-Seite, wartet aber nicht auf die vollständige Bedienbereitschaft des Editor-Iframes. Dafür bleibt der separate funktionale `ui_layout_app`-Test maßgeblich; dessen Ergebnis gehört in den Gesamtbericht.

### Beobachtete Navigationsverzögerung

Der erste lokale Loginaufruf dauerte bis zum ersten Byte rund 15.102 ms. Die detaillierte Messung zeigt `fetchStart = 0,4 ms`, `domainLookupStart = 15.079,2 ms`, `requestStart = 15.080,6 ms`, `responseStart = 15.101,9 ms`: **nur 21,3 ms liegen zwischen Anfragebeginn und erstem Byte**. Fast die gesamte Pause liegt davor. Der falsche Login benötigt in derselben Kennzahl 80,1 ms, der folgende Haupt-App-Aufruf 127,6 ms. Die Ursache der Pause vor der Anfrage ist damit noch nicht identifiziert; aus diesem Lauf folgt aber kein Beleg für 15 Sekunden PHP-Verarbeitung. Diese Werte sind lokale Beobachtungen einschließlich Transport, keine Aussage über Mobilfunk oder Produktionsleistung.

## Finaler Prüfsnapshot: 13:25:04 bis 13:30:40 UTC

Die folgende Bewertung ersetzt ältere Aussagen zum Zustand der erneut geprüften Oberflächen. Sie bezieht sich auf das **unverändert ausgeführte Fixture**, nicht auf nachträgliche Änderungen im Arbeitsverzeichnis. Rohdaten: [finaler Matrixbericht](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-final/report.json); konsolidierte Belege: [final-visual-evidence.json](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/design/final-visual-evidence.json).

### Abdeckung und unveränderter Teststand

- **265 Beobachtungen:** 255 erfasste Zustände, eine fehlgeschlagene Route und neun weitere Sprach-, Schrift-, Navigations-, Fokus-, Bewegungs- oder Routenbestandsmessungen.
- Alle **25 Haupt-App-Panelrouten** wurden in fünf Ansichten angefahren, darunter die neue Gemeinschaft, Allianzplanung und Allianzwerkzeuge. Dazu kommen Login einschließlich Fehlerzustand, Stadt/Welt, echte Maximalstufen-Gebäudedialoge, Grumwald-Marschdialoge, DE/FR-Auswahl, Reflow-Probes sowie 15 Admin-Routen in drei Ansichten.
- **47 finale Screenshotdateien wurden manuell angesehen**, jede der 25 Panelrouten mindestens einmal. Gemeinschaft, Allianzplanung und Allianzwerkzeuge wurden jeweils bei Desktop, 320 × 568 und 568 × 320 betrachtet. Dies ist eine Stichprobe der fertigen Ansichten, kein Test sämtlicher Unterseiten und schreibender Aktionen.
- **0 Browser-JavaScriptfehler**, aber **3 HTTP-409-Konflikte** und eine dadurch nicht geladene Verteidigungsansicht. Prozessende mit Exit 0 bedeutet hier nur abgeschlossenen Auditlauf; die erfassten Fehler bleiben ausdrücklich offen.
- Anfang und Ende des Fixtures enthalten dieselben **130 CSS-/JS-/MJS-/Locale-JSON-Dateien** mit denselben Inhalts-Hashes. Beim Start stimmen diese auch mit dem Workspace überein. Die Hashdateien selbst unterscheiden sich wegen Aufnahmezeitpunkten; `fixtureChanges=[]` vergleicht die darin enthaltenen Datei-Inhalte.

Der Workspace änderte währenddessen `assets/css/village-theme.css`, `assets/js/alliance-community.js`, `assets/js/march-panel.js` und `assets/js/mvp-panels.js`. Diese vier späteren Änderungen sind **nicht Bestandteil der hier geprüften Momentaufnahme**. Der Kopiermechanismus hat verhindert, dass sie den laufenden Fixture-Stand vermischen. Die Hashbehauptung umfasst die ausdrücklich aufgeführten 130 Dateien, nicht sämtliche Grafiken oder Backenddateien. Der SHA-256 des finalen Berichts lautet `c7e36eafd49c2cbd0518076ff6897c6e372d59b815bf7f3fb7de5b0dbe450989`.

### Gegenprüfung der fünf Hauptbefunde

| Thema | Ergebnis im finalen Fixture | Neuer Beleg |
|---|---|---|
| Inventar-Übersichtssymbol | **Weiter bestätigt.** Das fast weiße Diagrammsymbol bleibt auf cremefarbener Fläche schwer erkennbar. | [Desktop](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-final/inventory-1280x800.png), [320](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-final/inventory-320x568.png) |
| Kleine aktive HUD-Texte | **Weiter bestätigt.** Untere Navigationslabels messen in beiden kleinen Ansichten 8 px Schrift/8 px Zeilenhöhe; weitere aktive Informationen erreichen 7–8 px. | [Stadt 320](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-final/city-320x568.png), [Stadt 568](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-final/city-568x320.png), konkrete Computed Styles im Beleg-JSON |
| Marschabschluss unter dem 44-px-Stilziel | **In beiden geprüften Handyansichten behoben.** 320: 123,5 × **44 px**; 568: 162,4 × **44 px**. Aktiv, vollständig im Bildschirm und am Mittelpunkt erreichbar. Der alte 38-/35-px-Befund gilt hier nicht mehr. | [Marsch 320](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-final/grumwald-march-320x568.png), [Marsch 568](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-final/grumwald-march-568x320.png) |
| Gemischte Oberflächensprache | **Weiter bestätigt.** Aufgabenfilter und Resettext, Marktressourcen, „/ Rang“, Kampfglückhinweis und „Allianz öffnen“ bleiben teilweise Deutsch. FR-Aufgaben sind weiterhin gemischt; FR-Armee zeigt „Train infantry“. | [Aufgaben EN](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-final/quests-1280x800.png), [Markt EN](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-final/market-320x568.png), [Marsch EN](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-final/grumwald-march-1280x800.png), [Aufgaben FR](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-final/quests-fr-320x568.png), [Armee FR](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-final/army-fr-320x568.png) |
| Überdeckung im Gebäude-Querformat | **Weiter bestätigt.** Requirements verdeckt weiterhin den rechten Teil der „Maximum“-Pille. Die Ansicht wurde erfolgreich geöffnet; kein Klick-Harnessfehler. | [Gebäude 568](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-final/building-upgrade-568x320.png) |

Der Gebäudezustand bleibt Maximalstufe 30. Eine freigegebene Ausbauaktion wurde damit weiterhin nicht geprüft. Andere kleine Bedienelemente werden durch die Verbesserung des Marschabschlusses nicht automatisch mitvalidiert.

### Neue und aktualisierte Sichtbefunde

**P2 – Falscher Wochentag im Ereigniskalender:** Die Spalte für Dienstag, 6. Oktober, heißt im [finalen Ereignisbild](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-final/events-1280x800.png) **„Kills“** statt „Tue“. Das ist ein bestätigter sichtbarer Textfehler. Die Erzeugung verwendet `Intl`-Wochentage; eine nachgelagerte Wortübersetzung ist als Ursache plausibel, aber die vollständige Ersetzungskette wurde in dieser Sichtprüfung nicht nachgewiesen. Kontextbezogene Sprachschlüssel bzw. Schutz bereits formatierter Datumswerte sind der passende Ansatz.

**P3 – Bild und Lesetext im Expeditionskopf:** Bei [320 × 568](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-final/expeditions-320x568.png) läuft der erklärende Absatz direkt über die Bossillustration. Die Bildkonturen machen die Textfläche unruhig. Empfehlung: Auf schmalen Ansichten das Bild unter/neben den Absatz setzen oder hinter der Schrift eine ruhige Fläche herstellen. Das ist eine visuelle Lesbarkeitsempfehlung, kein rechnerisch bestätigter Kontrastverstoß.

Der [Fehlerbericht](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-final/bugreport-1280x800.png) nennt weiterhin „Conquer“ und enthält den Platzhalterrest „e.g. B.“. Die erneute Verwaltungsstichprobe zeigt dagegen eine konkrete Verbesserung: [Belohnungen 390](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-final/admin-rewards-390x844.png) sagt jetzt **„104 sources found“**; der ältere „104 Quellen“-Befund ist dort behoben. Die [Alpha-Schlüssel-Seite](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-final/admin-alpha-keys-390x844.png) behält deutsche Feldbezeichnungen und „e.g. B.“. Die große [Admin-Kopffläche im Querformat](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-final/admin-layout-568x320.png) bleibt eine Komfortempfehlung, kein nachgewiesener Scrollblocker.

### Neue Gemeinschaftsbereiche

Die drei Ansichten sind visuell voneinander unterscheidbar und verwenden das bestehende Design. Desktop verteilt Übersichten sinnvoll auf Spalten, 320 px stapelt sie, 568 px hält die Navigation lesbar. In diesen neun Bildern wurde keine zusätzliche eindeutige Überdeckung festgestellt:

- Gemeinschaft: [Desktop](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-final/community-1280x800.png), [320](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-final/community-320x568.png), [568](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-final/community-568x320.png).
- Allianzplanung: [Desktop](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-final/alliance-community-1280x800.png), [320](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-final/alliance-community-320x568.png), [568](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-final/alliance-community-568x320.png).
- Allianzwerkzeuge: [Desktop](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-final/alliance-tools-1280x800.png), [320](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-final/alliance-tools-320x568.png), [568](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-final/alliance-tools-568x320.png).

Die Aussage betrifft die Startansichten. Freundschaftsanfragen, Kalendererstellung, Abstimmungen, Rekrutierung und Senden einer Nachricht wurden hier nicht ausgeführt. Deutsche Chatnachrichten und Allianznamen bleiben Nutzerinhalt und werden nicht als Sprachfehler gewertet.

### Unvollständige Zustände und Konflikte

| Aufnahme | Beleg und Grenze |
|---|---|
| Verteidigung 390 × 844 | GET `/api/defense/state` → **409 BUSY**. [Fehlerbild](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-final/defense-390x844-failure.png) zeigt „Please try again immediately.“ und einen sichtbaren Retry-Knopf. Die Inhaltsansicht wurde in dieser Größe nicht geladen; automatische Erholung und Betätigung von Retry sind nicht getestet. Bei 320 wird Verteidigung dagegen normal erfasst. |
| Während Grumwald 844 × 390 | Erneutes GET `/api/defense/state` → **409 BUSY**, während der Grumwald-Dialog selbst erfolgreich erfasst wird. Der aktive Tag bezeichnet den Beobachtungszeitpunkt, nicht den verursachenden Endpunkt. Kein zweiter fehlgeschlagener Marschdialog wird daraus abgeleitet. |
| Admin-Layout 568 × 320 | GET `/api/game/state` → **409 BUSY**. Die äußere Adminseite wurde erfasst; der Zustand des eingebetteten Editors ist damit weiterhin nicht vollständig geprüft. |
| Stadt 568 × 320 | Ein sichtbares Weltkartenicon war nach dem begrenzten Bild-Wartefenster noch `pending`; kein endgültiger Ladefehler nachgewiesen. |
| Schatzkammer 568 × 320 | Zwölf Reliktbilder waren beim Erfassen noch `pending`. Das [Bild](C:/xampp/htdocs/conquer/artifacts/ui-ux-audit-2026-09-30/app-matrix-final/treasures-568x320.png) zeigt entsprechend leere Reliktflächen. Dieser Zustand darf nicht als vollständig geladen gewertet werden; Dauerfehler oder fehlende Dateien sind damit nicht belegt. |

Alle drei Konfliktantworten enthalten den Code `BUSY`; die begrenzten Antworttexte stehen im Rohbericht. Die Häufigkeit bei realer Nutzung ist aus diesem schnellen synthetischen Navigationslauf nicht ableitbar. Unter den als abgeschlossen gemessenen Bildern meldet der Lauf keine endgültig defekten Bilddateien, aber die 13 genannten ausstehenden Bilder bleiben eine klare Prüfgrenze.

Der letzte Navigationstest zeigt erneut etwa 15,1 Sekunden **vor** der ersten eigentlichen HTTP-Anfrage. Anfragebeginn bis erstes Byte: 33,8 ms beim initialen Login, 87,0 ms beim falschen Login, 107,4 ms bei der Haupt-App. Die Ursache der vorgelagerten Pause bleibt offen. Lokale Ladezeiten sind keine Zusicherung für Mobilgeräte oder Produktion.

### Konsequenz für den nächsten Verbesserungsdurchgang

Im geprüften Fixture haben Gebäudeüberdeckung, Inventarsymbol, kleine aktive Schrift und kontextgerechte Übersetzungen Vorrang. Den bereits verbesserten Marschabschluss und den korrigierten Admin-Quellentext nicht erneut als offene Fehler führen. Vor einem Eingriff zuerst die vier später geänderten Workspace-Dateien mit den Belegen abgleichen, damit bereits parallel erledigte Korrekturen erhalten bleiben. Vollständige Editorbedienung, Retry-Erholung, reale mobile Geräte und sämtliche Unteraktionen bleiben außerhalb dieser Sichtprüfung.
