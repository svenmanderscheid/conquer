# Union of Kingdoms – Umsetzung der UI/UX-Prüfung

Stand: 30. September 2026. **Umsetzung und UI/UX-Nachprüfung abgeschlossen.** Die bestätigten Oberflächenbefunde sind korrigiert. Jeweils letzte Ergebnisse: **49/49 App-Suiten und 47/47 isolierte Suiten erfolgreich**, ergänzt um die vollständige Ansichtenmatrix und gezielte Sichtkontrollen. Das ist keine Freigabe für native Stores oder eine vollständige Barrierefreiheitszertifizierung.

Die ursprünglichen Berichte [Designprüfung](C:/xampp/htdocs/conquer/docs/qa/UI_UX_DESIGN_REVIEW_2026-09-30.md) und [Sichtprüfung](C:/xampp/htdocs/conquer/docs/qa/UI_UX_APP_VISUAL_REVIEW_2026-09-30.md) bleiben unverändert. Ihr finaler Auditlauf ist eine historische Momentaufnahme vor dieser Umsetzung. Der Ausgangsstand der Umsetzung ist im [Baseline-Manifest](C:/xampp/htdocs/conquer/artifacts/ui-ux-implementation-2026-09-30/baseline-manifest.json) festgehalten. Neue Belege liegen ausschließlich unter `artifacts/ui-ux-implementation-2026-09-30/`.

## Gestalterischer und sprachlicher Rahmen

Variante A bleibt bestehen: dunkelviolette Fensterköpfe, warme beigefarbene Flächen, dezentes Gold und weiche Formen mit gemeinsamen `--ui-*`-Variablen. Almendra für Text und Lora für Zahlen bleiben über die lokale Schriftfamilie `Conquer UI` eingebunden. Es gibt keinen Wechsel der Schriftfamilien oder der gesamten Farbpalette. Aktions-, Rollen- und Seltenheitsfarben behalten ihre Bedeutung.

Englisch bleibt Standard ohne Browser-Spracherkennung und zugleich Rückfallebene. Explizite EN-/DE-/FR-Auswahl bleibt möglich. Spielernamen, Allianznamen und Nachrichten bleiben unverändert. Sichtbarer Produktname ist **Union of Kingdoms**.

## Befund und Korrektur

Die Tabelle ordnet die umgesetzten Änderungen den bestätigten Befunden zu. Die genaue Testabdeckung und die unterschiedlichen Prüfstände stehen weiter unten.

| Auditbefund | Umsetzung und Nachweisstelle |
|---|---|
| Aktive HUD-Werte und Docktexte teilweise nur 7–8 px | `assets/css/village-theme.css` ordnet das Profil in zwei 44-px-Aktionszeilen an. Wesentliche Zahlen erhalten mindestens 13 px, Docktexte 11 px mit normalem Wortumbruch. `assets/js/game.js` verwendet kürzere gemeinsame Docklabels, etwa „Items“/„Objets“, und den gemeinsamen Markt-Schlüssel. Safe-Area-Abstände bleiben berücksichtigt. |
| Inventar-Übersichtssymbol nahezu weiß auf Beige | Die pauschale helle SVG-/Pfadfarbe in Fensterüberschriften wurde in `village-theme.css` entfernt; das Symbol kann seine dunkle Komponentenfarbe verwenden. |
| Langer Titel der Inventarübersicht kollidiert bei 320 px mit Melden | `inventory-reference.css` reserviert im Kopf 108 px für zwei getrennte Aktionen und lässt den vollständigen Titel umbrechen. Der Kopf wächst mit dem Text; Melden und Schließen erhalten jeweils 44 × 44 px. Die bisherige 42-px-Kopffixierung im kurzen Querformat entfällt. Der erweiterte `inventory_overview_app.cjs` prüft jede Titelzeile, Überschneidungen, Mittelpunkt-Treffbarkeit und DE/FR zusätzlich in 320 × 568 sowie 568 × 320. |
| Gebäudeidentität im kurzen Querformat durch Voraussetzungen überdeckt | Flexible äußere Spalten, eine eigenständig scrollbare Übersicht, kleinere Bildanordnung und umbruchfähige Stufen-/Maximumangaben in `village-theme.css`. Bei aktiver Gebäudeauswahl werden optionale Zielhilfe und geschlossene Chatvorschau ausgeblendet, damit sie die Aktion nicht überdecken. |
| Abgeholte Aufgaben unnötig abgeblendet, kleine Lesetexte | Aufgabeninhalt erhält wieder volle Deckkraft; Beschreibung 13 px und Aktionen mindestens 44 px. Der erledigte Zustand bleibt durch Gestaltung und Status erkennbar. |
| Kollisionen zwischen Weltkarte, Hinweisen, Suche und Aktionen | Optionale Zielhilfe wird bei aktiver Zielkarte bzw. Suche und im sehr kurzen Welt-Querformat verborgen. Suchknopf und Werkzeugbereich erhalten abgestimmte Abstände. |
| Kleine Rückrufaktion und sehr kleiner Marschstatus | Die direkte Rückrufaktion erhält 44 × 44 px; Marschstatus 11 px, Titel und Zeit 12 px. `world-march-hud.js` ersetzt den ausgeblendeten Truppenknopf an dessen Position, statt darunter zusätzlichen Platz zu reservieren. So steht der vergrößerten Liste oberhalb der Kartensteuerung mehr Raum zur Verfügung. |
| Rückkehrhinweis kollidiert bei 568 × 320 mit der Jobleiste | Der Hinweis wird im kurzen Stadt-Querformat links neben der geschlossenen Chatvorschau verankert. Die Rückkehraktion bleibt vorhanden. Der ursprüngliche Klickfehler in `game_comfort_app` und das Fehlerbild bleiben als Beleg erhalten. |
| Expeditionsbeschreibung läuft bei 320 px über Bossillustration | `village-theme.css` trennt bis 400 px Breite Text und Bild in eigene Bereiche; die Illustration liegt unter der Beschreibung auf einer ruhigen Fläche. |
| Lange französische Tabnamen werden mitten im Wort getrennt | Inventar, Hilfe und Händler verwenden auf schmalen Bildschirmen horizontal scrollbare Tabstreifen ohne erzwungene Wortzerstückelung. Inventartabs erhalten 13 px Schrift und mindestens 44 px Höhe; Hilfetabs bleiben bei 15 px und mindestens 44 px. `mvp-panels.js`, `beginner-guide.js` und `trading-panel.js` halten den gewählten Tab nach Neuzeichnen und Übersetzung im sichtbaren Streifen. |
| Transparente Ränder großer Kartengrafiken fangen Nachbarklicks ab | `world-painted.js` ermittelt je Bild die äußeren Grenzen nicht vollständig transparenter Pixel; `world-atlas.css` begrenzt die Trefffläche der überstehenden Monster-/Rohstoffbilder darauf. Die sichtbaren Animationsflächen werden nicht beschnitten. Dies verändert keine Weltkoordinaten oder serverseitigen Regeln. |
| Allianzliste und Verwaltungsaktionen passen nicht gemeinsam ins Fenster | Die betreffende `.window-list` in der Allianzansicht erhält einen eigenen vertikalen Scrollbereich; Zeilen schrumpfen nicht zusammen, Aktionen bleiben mindestens 44 px groß. Kopf und übrige Fensteraktionen bleiben außerhalb dieser Liste. |
| Aufgaben-, Markt-, Talent-, Armee-, Marsch- und Admintexte sprachlich gemischt | Betroffene Renderer und gemeinsame Kataloge verwenden passende Sprachschlüssel bzw. lokalisierte Werte. Dazu gehören Aufgabenstatus, zugängliche Aktionsnamen, Ressourcen, Talentränge und die Grumwald-Erklärung. `views/admin/lands.php` übersetzt Weltstart und Stufenanforderung ausdrücklich; Nutzerdaten in Tabellen bleiben geschützt. |
| Kalender zeigt „Kills“ als Wochentag; mehrdeutige Textübersetzungen | Bereits mit `Intl` formatierte Datumswerte werden vor nachträglicher Wortübersetzung geschützt. Mehrdeutige Katalogwerte, darunter das französische „Kills“ und „Gefallen“, wurden semantisch korrigiert. |
| Kleine Ausbildungsstatuszeilen liegen auf dem unteren Strich | Die erste Abschlussmatrix zeigt das bei 320 px: Drei gestapelte Reihen konkurrieren in 55 px Höhe mit einem absolut positionierten Strich. Die bestehende Hochformatregel in `training-panel.css` erhält mindestens 72 px, 12 px Innenabstand unten, nicht schrumpfende Zeilen und 11-px-Statusschrift. Die Querformatregel bleibt erhalten. Diese Änderung erfolgte nach den Bildern der ersten Abschlussmatrix. |
| Eingabe „Kurzer Titel“ in der Fehlermeldung zu flach | Das Feld besitzt kein explizites `type` und fiel aus der vorhandenen Größenregel für `input[type=text]` heraus. `bug-reports.css` setzt gezielt für `#bug-title` mindestens 44 px Höhe und passende Innenabstände. Die gemeinsamen Farben bleiben bestehen. |
| Weitere Restbeschriftungen in der ersten Abschlussmatrix | Sichtbar waren EN „Offen“, „RANG“, „Allianz öffnen“ und der deutsche Admin-Einladungssatz sowie FR „Train infantry“ und „Chests · Rare“. Diese konkreten Renderer-/Katalogstellen wurden zentral nachkorrigiert. Die zweite Matrix bestätigt diese Korrekturen. Drei dort zusätzlich gefundene Details wurden anschließend gezielt korrigiert: der französische Händlerreiter, das Burgstufenlabel der Weltauswahl und der französische Goldwert der Expedition. Ihre Nachweise werden getrennt der abschließenden Sprachprüfung zugeordnet. Nutzernamen, Chattexte und gespeicherte Berichtstitel sind davon getrennt. |
| Übersetzung verändert Nutzernamen oder lässt benachbarte Knöpfe unübersetzt | `localization.js` und Profil-, Gemeinschafts- und Berichtmodule markieren Sprachgrenzen gezielt. Redaktionelle Bedienelemente bleiben übersetzbar, Namen und Nachrichten unverändert. |
| Alter öffentlicher Name und fehlerhafte Beispieltexte | Fehlerbericht sowie kopierte/geteilte Spieler- und Monsterberichte nennen Union of Kingdoms. `index.php` verwendet den Namen auch auf Datenbankfehler- und Vorschauseiten. Betroffene Beispieltexte wurden im gemeinsamen Sprachsystem korrigiert. |
| Große Kataloge auf jeder HTML-Seite erneut eingebettet | Versionierte öffentliche Sprachassets ersetzen den vollständigen Inline-Katalog. Größen, Cachegrenzen und Sicherheitsgrenzen stehen im nächsten Abschnitt. |
| Dialognavigation und verzögerte Sprachbereitschaft | `game.js` und `admin-backoffice.js` warten vor dem Aufbau der Oberfläche auf `ConquerLocale.ready`. Scout-, Kampf-, Monster-, Belohnungs- und Inventarübersicht melden ihre eigene Historie über `historyManaged`; dadurch entfällt ein zusätzlicher generischer Mobile-Historieneintrag. Direkte Monsterberichte warten ebenfalls auf DOM und Sprache. `mobile-pages.js` selbst wurde dafür nicht geändert. |
| Marschfenster wird beim Wechsel aus einer Kartenaktion sofort wieder geschlossen | `world-map.js` beendet zuerst den eigenen Karteneintrag in der Browserhistorie. Die vorgemerkte Monster-/Sammelaktion startet erst nach allen `popstate`-Listenern in einem neuen Ereignisschritt. Vorher werden noch dieselbe Karteninstanz, sichtbare Szene, Welt und Auswahlgeneration geprüft; ein inzwischen erfolgter Wechsel verwirft die Aktion. `map_action_history.cjs` prüft den tatsächlichen Übergang mit beiden Listener-Reihenfolgen, einmaligem Aufruf, Zurück/Escape und abgebrochenen Wechseln ohne Browser oder Datenbank. |
| Inventarfilter: allgemeine Häkchenregel erfasst Text | `assets/css/inventory-reference.css` begrenzt die Häkchengestaltung auf das dekorative `aria-hidden`-Element; Beschriftungen erhalten eigene freie Breite. Der App-Test prüft auch inaktive Einheitentexte. |
| Admin-Kopf belegt im kurzen Querformat viel Höhe | Eng auf `.admin-village` und kurze Querformate begrenzte Regeln in `village-theme.css` reduzieren Abstände, Markenbild und äußere Sprachkartenfläche. Das Sprachlabel bleibt zugänglich; Sprachwahl, Weltwahl und Menü bleiben mindestens 44 px hoch. Die Weltwahl darf umbrechen, die Seite bleibt normal scrollbar. Die erste Abschlussmatrix zeigt den kompakten Kopf und den sichtbaren Inhaltsbeginn bei 568 × 320; das ist keine vollständige Prüfung der darunterliegenden Editoren. |
| Viele überlagerte CSS-Regeln | Der bearbeitete HUD-Bereich wurde zusammengeführt und veraltete Größenregeln wurden entfernt. Der neue Vertrag ist in `docs/UI_STYLE_GUIDE.md` dokumentiert. Eine vollständige Bereinigung aller historischen Stylesheets ist damit nicht erledigt. |

Der Marschabschluss erreichte bereits im letzten Audit 44 px Höhe; das wird nicht als neuer Erfolg dieser Umsetzung gezählt. 44 px ist hier das gemeinsame Bedienziel, keine pauschale Aussage über WCAG-Konformität.

## Sprachdateien: messbare Entlastung

[Messdaten mit Inhalts-Hashes](C:/xampp/htdocs/conquer/artifacts/ui-ux-implementation-2026-09-30/locale-delivery/source-bytes.json), **Messstand 16:07:00 UTC mit jeweils 9.107 Schlüsseln**: Der Vergleich verwendet dieselben Kataloginhalte im bisherigen und neuen Auslieferungsvertrag, einschließlich der letzten Ranglisten- und Verwaltungsbeschriftungen. Alle Zahlen sind **unkomprimierte Quellbytes**, keine gemessenen Übertragungszeiten und keine gesamte Seitengröße.

| Bestandteil | Bytes |
|---|---:|
| Bisheriger Inline-Block mit allen drei Katalogen | 1.663.295 |
| Neue Inline-Konfiguration am Webroot | 313 |
| Englischer ausführbarer Katalog | 519.755 |
| Deutscher ausführbarer Katalog | 551.207 |
| Französischer ausführbarer Katalog | 592.905 |
| Deutsche Quellzuordnung für vorhandene Oberflächentexte | 522.889 |
| Erster Aufruf ohne Cache: EN + Quellzuordnung | 1.042.644 |
| Erster Aufruf ohne Cache: EN + DE | 1.070.962 |
| Erster Aufruf ohne Cache: EN + FR + Quellzuordnung | 1.635.549 |

`src/Game/Locale.php`, `index.php` und die betreffenden Login-/Spiel-/Recovery-/Lokalisierungs-Views liefern vollständiges Englisch sowie die gewählte Sprache und nötige Quellzuordnung in definierter Reihenfolge. Weitere Sprachen werden gezielt nachgeladen; Nachladeversuche sind zeitlich begrenzt und verwenden bei Fehlern die englische Rückfallebene. Host und Unterordner werden nicht fest eingebaut, vorhandene CSP-Nonces bleiben berücksichtigt.

Die öffentliche Route akzeptiert nur erlaubte Sprachen, Formate und passende Inhaltsversionen. Sie läuft vor Datenbank- und Sitzungsinitialisierung, setzt passende Inhaltstypen und Validatoren und liefert die Assets mit `public, max-age=31536000, immutable`. Veraltete oder ungültige URLs werden nicht gecacht. Der Service Worker beschränkt die Behandlung auf die erlaubten öffentlichen Assets; authentifiziertes HTML und APIs gelangen dadurch nicht in den Cache. Die bisherigen Grenzen von **32 Einträgen und 512 KiB je Datei** bleiben bestehen. DE und FR überschreiten derzeit die einzelne CacheStorage-Grenze, bleiben aber über den HTTP-Cache wiederverwendbar.

## Prüfgrenzen

- Die früheren automatischen Kontrastkandidaten über Bildern, Schatten und Verläufen waren keine bestätigten Verstöße. Daraus wird weder ein offener globaler Farbfehler noch eine pauschale Kontrastfreigabe abgeleitet.
- Beide Abschlussmatrizen enthalten keine JavaScript-/HTTP-Fehler und keine defekten oder noch ausstehenden Bilder. Damit wurden frühere `BUSY`-Antworten in diesem Lauf nicht erneut beobachtet; ihre Ursache ist durch diesen einzelnen Lauf nicht allgemein behoben. Ebenso beweisen kleinere HTML-Blöcke keine Behebung der früheren lokalen Pause vor Beginn der HTTP-Anfrage.
- Echte iOS-/Android-Geräte, Bildschirmleser, Bildschirmtastaturen und systemweite Textvergrößerung wurden nicht physisch geprüft. Die frühere Reflow-Probe war nur ein Geometrie-Proxy. Admin-Routenaufrufe allein beweisen keine vollständige Editorbedienung.

## Erste Abschlussmatrix und Sichtprüfung

Die [erste Abschlussmatrix](C:/xampp/htdocs/conquer/artifacts/ui-ux-implementation-2026-09-30/app-matrix-final/report.json) vom 30. September, ca. 15:25–15:29 UTC, erfasst **302 Beobachtungen**. Sie meldet **0 JavaScript-Fehler, 0 HTTP-Fehler, 0 defekte und 0 noch ausstehende Bilder** sowie keine erkannten Routen- oder Usabilityfehler. Diese Zähler beschreiben die automatisierten Messregeln dieses Laufs; sie schließen die zusätzlich sichtbaren Restbefunde nicht aus.

Die [Sichtprüfung](C:/xampp/htdocs/conquer/artifacts/ui-ux-implementation-2026-09-30/app-matrix-final/visual-review.md) umfasst **56 tatsächlich angesehene PNGs**, darunter alle 25 Panelrouten auf Desktop sowie gezielte Hoch-/Querformat-, französische und Verwaltungsansichten. [Dateihashes und Aufnahmezeiten](C:/xampp/htdocs/conquer/artifacts/ui-ux-implementation-2026-09-30/app-matrix-final/visual-review-observed-files.json) kennzeichnen genau diesen Stand. Die französischen Inventar-/Hilfereiter und ihre letzte Auswahl waren lesbar; die Allianzverwaltung und der kompakte Admin-Kopf passten im kurzen Querformat.

Einige Händleraufnahmen zeigen noch den Ladezustand. Sie belegen deshalb keine Lesbarkeit fertig geladener Händlerangebote. Die Sichtprüfung fand außerdem die kleinen Ausbildungsstatuszeilen, das flache Kurztitelfeld und einzelne Restbeschriftungen. Weil diese Punkte anschließend nachbearbeitet wurden und der Karten-/Marschübergang separat abgesichert wird, wurde eine **zweite vollständige Abschlussmatrix ausgeführt**, mit eigenem Belegordner `app-matrix-release`. Der erste Lauf ist kein Nachweis für später geänderte Dateien.

## Zweite Abschlussmatrix und gezielte Nachprüfung

Die [Release-Matrix](C:/xampp/htdocs/conquer/artifacts/ui-ux-implementation-2026-09-30/app-matrix-release/report.json) enthält erneut **302 Beobachtungen**, davon 277 erfasste Ansichten und 16 erfolgreiche zusätzliche Bedienproben. **245 PNGs** wurden gespeichert; Ansichtenmessung und Bilddatei sind unterschiedliche Zähler. Enthalten sind alle **25 Panelrouten in fünf Größen** (1280 × 800, 390 × 844, 320 × 568, 844 × 390, 568 × 320) und **15 Verwaltungsrouten in drei Größen**, außerdem Stadt, Welt, Gebäude-/Marschfenster, Sprachwechsel, Schriftmessung, Reflow-Proxy und reduzierte Bewegung.

Ergebnis dieses Laufs: **0 JavaScript-Fehler, 0 erfasste HTTP-Fehler, 0 defekte oder ausstehende Bilder, keine fehlgeschlagenen Routen-/Bedienproben und kein gemessener horizontaler Seitenüberlauf.** Händleraufnahmen warten diesmal ausdrücklich auf alle acht Angebote. 43 Bilder, einschließlich aller 25 Desktopmenüs, wurden [tatsächlich visuell geprüft](C:/xampp/htdocs/conquer/artifacts/ui-ux-implementation-2026-09-30/app-matrix-release/visual-review.md).

Die **130 erfassten CSS-/JS-/MJS-/Locale-Dateien der Matrix-Fixture blieben unverändert**. Im Arbeitsverzeichnis änderten sich währenddessen `world-panel.js`, `en.json` und `fr.json`; anschließend folgten `trading-panel.js`, `trading-panel.css` und die einzelne Ressourcenbeschriftung in `mvp-panels.js`. Deshalb werden diese sechs Dateien durch getrennte Nachprüfungen abgedeckt, nicht rückwirkend durch die Matrix als geprüft ausgegeben.

Die [abschließende Sprachsuite](C:/xampp/htdocs/conquer/artifacts/ui-ux-implementation-2026-09-30/functional-labels/results.json) bestand am unveränderten Produktstand: **21 Spielbereiche in EN/FR, 80 responsive Ansichten und 40 Händler-Tabwechsel**, einschließlich vollständig geladener Angebote, 44-px-Reiter, kompletter aktiver Beschriftungen und sichtbarer Auswahl. Sie prüft außerdem Englisch als Standard im deutschen Browser, unveränderte Spielernamen und verfasste Nachrichten. Ihre 86 PNGs enthalten auch die Stadtansichten und zwei zusätzliche Kristallreiterbilder. Der [Sichtnachtrag zu sieben Bildern](C:/xampp/htdocs/conquer/artifacts/ui-ux-implementation-2026-09-30/functional-labels/visual-review.md) bestätigt die drei letzten Korrekturen: „Castle level“/„Niveau du château“, vollständiger französischer Shop-Reiter und „600 Or“.

Die Inventarübersicht bestand separat ihren vollständigen App-Test: Summen, fünf englische Formate, DE/FR-Hoch-/Querformat, vollständige Titel, beide 44-px-Kopfaktionen, drei Einheiten, laufende Aktualisierung, Touch, Escape, Zurück und keine Schreibaktionen. [Vier neue Bilder wurden zusätzlich betrachtet](C:/xampp/htdocs/conquer/artifacts/ui-ux-implementation-2026-09-30/functional-release/inventory-visual-review.md); die französische Minuteneinheit lautet korrekt „Minutes“.

## Tests und Abschluss

Die [aggregierten App-Ergebnisse](C:/xampp/htdocs/conquer/artifacts/ui-ux-implementation-2026-09-30/functional-latest-results.json) enthalten genau die **49 registrierten App-Suiten**, jede mit erfolgreichem letztem Ergebnis. Diese Zusammenstellung umfasst mehrere serielle Prüfrunden und Quellstände; sie ist kein einzelner Lauf auf einem identischen Build. Die ursprünglichen Fehlerprotokolle bleiben erhalten:

| Runde | Erfolgreich | Einordnung |
|---|---:|---|
| `functional-final` | 35/49 | Erster Gesamtlauf; Produkt- und veraltete Testverträge anschließend untersucht. |
| `functional-diagnostics` | 0/2 | Zusätzliche Diagnose des Karten-/Marschübergangs und der Szenenübergangszeit. |
| `functional-recheck` | 15/18 | Nachtest der korrigierten Abläufe und angrenzender Funktionen. |
| `functional-release` | 3/4 | Inventarübersicht, Kartensuche und Rohstoffbesetzung erfolgreich. Die neue Sprachassertion erwartete irrtümlich Burgstufe 30 statt der dokumentierten Fixture-Stufe 12. |
| `functional-labels` | 1/1 | Vollständiger erweiterter Sprachtest mit korrekten Fixture-Daten und den letzten drei sichtbaren Korrekturen. |

Die [isolierten Ergebnisse](C:/xampp/htdocs/conquer/artifacts/ui-ux-implementation-2026-09-30/isolated-latest-results.json) enthalten **45 registrierte STATIC-Suiten sowie `world_life` und `world_footprint`: 47/47 erfolgreich**. Die ursprüngliche Sammlung mit 43 STATIC-Suiten und zwei Zusatzprüfungen bleibt in `isolated-final` erhalten. Später hinzugekommene Tests für Sprite-Treffflächen und Kartenhistorie sowie gezielte Nachläufe werden separat eingerechnet; Wiederholungen erhöhen die Zahl eindeutiger Suiten nicht. `world_footprint` deckt Desktop/Tablet und Handy in getrennten Läufen ab; das wird nicht als gemeinsamer Snapshot ausgegeben.

Die letzte isolierte Serie bestätigte Fortschritts-/Weltfenster, Sprachbereitstellung, geschützte Nutzertexte, Sprite-Treffflächen und Kartenhistorie. Zwei isolierte Testumgebungen mussten danach korrigiert werden: Der Händlertest benötigt für Sprach-Cookies eine gültige abgefangene Testadresse; die reine Inventar-VM benötigt den echten gemeinsamen Übersetzer. Anschließend bestanden **55 Händlerprüfungen** sowie **231 Fensterprüfungen, 26 Renderingfälle und vier Nutzungstests**. [Nachlaufprotokolle](C:/xampp/htdocs/conquer/artifacts/ui-ux-implementation-2026-09-30/isolated-fixture-final/window_panels.log) enthalten keine Browser-/Requestfehler oder Schreibversuche. Dafür wurde kein weiterer Produktcode verändert.

Besonders relevante funktionale Nachweise:

- **Karte und Marsch:** 173 App-Prüfungen der Suche, Auswahl, Rückkehr, mobilen Marschöffnung und Fehlerpfade; zusätzlich echte Sammelaktionen und Besetzungsansichten in fünf Größen. Die isolierte Historienprüfung testet beide Listener-Reihenfolgen, genau einen Aktionsstart und verworfene veraltete Aktionen.
- **Lesbarkeit und Menüs:** 110 Menü-/Formatkombinationen der gemeinsamen Theme-Prüfung; echte Almendra-/Lora-Verwendung, konsistente Palette, mobile Navigation und Rückrufaktionen. Ausbildung zusätzlich mit 75 Layoutzuständen über drei Schulen und fünf Zustände.
- **Inventar und Belohnungen:** Kategorien, Details, Nutzung, Live-Aktualisierung und Scrollposition; Belohnungsablauf mit verlorener Antwort, erneutem Öffnen und wiederholter identischer Anfrage. Die Übersicht selbst bleibt lesend.
- **Gemeinschaft:** bestehende Community-Suite mit 49 responsiven Ansichten und überprüften gespeicherten Präferenzen, Zielen, Terminen, Umfragen, Hilfeanfrage sowie exakter Spende. Schreibaktionen betreffen ausschließlich wegwerfbare Testdaten.
- **Vorherige Spielerlebnis-Erweiterungen:** Fundortansicht, Grumwald-Vorschau/Kampfrechner und Einsteigerführung wurden durch ihre bestehenden App-Suiten mitgeprüft.
- **Szenenwechsel:** alle fünf Größen und reduzierte Bewegung; bestehende DOM-Ansicht wird wiederverwendet. Der Test wartet begrenzt auf die tatsächlichen Phasen statt pauschal 1.150 ms. Gemessene lokale Übergangszeiten sind keine Leistungsfreigabe für echte Mobilgeräte.

`localization.php` bestätigt **9.107 gemeinsame Schlüssel** mit Katalogparität, Platzhaltern, englischem Standard und Schutz von Nutzerdaten. `locale_delivery.php` besteht sowohl am Webroot als auch unter `/conquer`; die isolierten JavaScript-Prüfungen bestätigen öffentliche Assets, Zeitlimit/Fallback, Cachegrenzen und semantische Sprachgrenzen. [Syntaxprüfung](C:/xampp/htdocs/conquer/artifacts/ui-ux-implementation-2026-09-30/syntax-release.json): **106/106 geprüfte geänderte/neue JS-, PHP-, JSON- und Python-Dateien erfolgreich**. [Abschließende Quellhashes](C:/xampp/htdocs/conquer/artifacts/ui-ux-implementation-2026-09-30/final-source-hashes.json) dokumentieren den Arbeitsstand.

## Nachvollziehbarkeit der Testkorrekturen

Bestehende Tests mit deutschen Textassertionen wählen DE ausdrücklich; der Produktstandard bleibt EN. Die aktuelle Spieloberfläche verwendet fünf aktive Truppenstufen und neun Einstiegsziele; betroffene veraltete Erwartungen wurden im Testquelltext eng angepasst. Für horizontale Reiter werden vollständige Beschriftungen, Scrollbarkeit, erreichbare Auswahl und sichtbarer aktiver Zustand geprüft. Alle Reiter gleichzeitig in eine schmale Zeile zu pressen wäre kein passender Vertrag.

Die Gemeinschaftstests warten auf den tatsächlichen Social Hub und öffnen bei Bedarf die Allianzwerkzeuge. Sprachwechseltests warten auf das ausgelöste Neuladen und die geladene Hauptoberfläche; Chatprüfungen warten auf die echte Nachricht statt eine feste kurze Pause. Die Inventarübersicht prüft ihre serverseitige Live-Aktualisierung innerhalb eines 30-Sekunden-Fensters, das den bestehenden 15-Sekunden-Poll einschließt. Mobile Dialogtests schließen über den tatsächlich sichtbaren Zurückknopf und warten auf den Abschluss der Historienänderung. Die Belohnungsprüfung wartet nach dem nativen Schließen, bevor sie ein neues Fenster öffnet.

Die Launcher ändern in den Abschlussläufen keine Assertions dynamisch und verwenden keine `--current-rules`-Umschreibungen. Sie trennen Artefakte, protokollieren Quellhashes und setzen für App-Navigation eine dokumentierte 45-Sekunden-Obergrenze. Testkorrekturen sind keine Produktkorrekturen; ursprüngliche Fehlschläge werden nicht gelöscht oder als bestanden ausgegeben.

Der Testumfang ist eine umfassende UI/UX- und angrenzende Funktionsprüfung. Die gesamte unabhängige Backend-/Sicherheits-Testgruppe wurde dafür nicht erneut ausgeführt. Die Arbeit ist lokal im Projekt umgesetzt; ein Deployment oder eine native Store-Veröffentlichung gehört nicht zu diesem Nachweis.
