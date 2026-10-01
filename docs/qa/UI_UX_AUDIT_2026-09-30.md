# Union of Kingdoms – UI/UX-Audit

30. September 2026 · Abschlussbericht

Die Sichtbefunde beziehen sich auf die dokumentierten Prüfsnapshots, zuletzt die unveränderte Dateikopie von 13:25:04 UTC. Die funktionalen Nachläufe endeten später. Parallel bearbeitete Dateien sind abgegrenzt; dies ist keine Freigabe eines unveränderlichen Releases.

## Urteil

**Die gestalterische Grundlage funktioniert. Der größte Gewinn entsteht durch bessere mobile Lesbarkeit, deutlichere Bedienelemente und verlässlichere Sprache.** Dunkelviolette Fensterköpfe, warme beigefarbene Flächen, weiche Formen und dezente Goldakzente passen zusammen und zur gezeichneten Welt. Eine neue Farbwelt oder eine dritte Schrift ist durch die Prüfung nicht begründet.

Almendra für Texte und Lora für Zahlen werden in der echten App tatsächlich geladen. Auf kleinen Bildschirmen verkleinert die Oberfläche jedoch wichtige Angaben so stark, dass die passende Schrift allein keine gute Lesbarkeit mehr sicherstellt. Die Verbesserung sollte deshalb an Größe, Abstand und Informationsdichte ansetzen.

Es wurden keine Produktdateien für dieses Audit verändert. Vier bestehende Testfixtures wurden korrigiert, damit sie die echten Schriften bzw. den heutigen mobilen Dialogaufbau verwenden. Zusätzlich entstanden reproduzierbare Auditprüfungen und Belege. Spielaktionen der Funktionstests laufen ausschließlich mit synthetischen Konten in temporären Testdatenbanken.

## Zuerst verbessern

| Priorität | Befund | Auswirkung und empfohlene Änderung |
|---|---|---|
| Hoch | **Zu kleine mobile Texte** | In der echten 320-px-Stadt: Dockbeschriftungen 8 px, Aktionspunkte 8 px, „Gems“ 7 px. Im Gebäudedialog sind Stufenlabel und Wert 9 px. Häufig gelesene Texte vergrößern; dafür weniger Details gleichzeitig zeigen und mehrzeilige Beschriftungen zulassen. |
| Hoch | **Kleine wichtige Touchflächen im HUD** | Im schmalen HUD sind mehrere Ressourcen-/Profilaktionen nur 24–25 px hoch. Wichtige Aktionen möglichst auf etwa 44 px bringen, ohne benachbarte Aktionen zu überlagern. Die frühere kleine Marschaktion wurde zwischenzeitlich verbessert: Im Schlusslauf ist sie bei 320 × 568 und 568 × 320 jeweils 44 px hoch und erreichbar. |
| Hoch | **Einsteigerziel verdeckt Kartenaktionen** | Im abschließenden Kartensuchtest bei 844 × 390 liegt die Einsteigerziel-Karte über der Aktionsreihe des Getreidehofs und verdeckt die Tippmitte von „Weiter suchen“. Desktop und beide Hochformatgrößen bestehen. Zielhinweis und offene Kontextkarte brauchen abgestimmte Positionen; ein schmaler freier Knopfrand genügt nicht für komfortable Bedienung. |
| Hoch | **Fast unsichtbare Inventarübersicht** | Das weiße Übersichtssymbol liegt auf einem hellen Knopf. Die Funktion ist technisch erreichbar, aber visuell schwer zu entdecken. Das Symbol sollte die dunkle gemeinsame Textfarbe erhalten; die vorhandene zugängliche Beschriftung bleibt bestehen. |
| Hoch | **Überdeckung im Gebäudefenster** | Bei 568 × 320 verdeckt die rechte Voraussetzungsspalte einen Teil der Anzeige „Maximum“. Die Spalten müssen sich die verfügbare Breite teilen, ohne Gebäudeinformationen abzuschneiden. Der Fehler ist im echten Maximalstufen-Dialog sichtbar. |
| Hoch | **Sprach- und Identitätsfehler** | Ein Spielername „Forschung“ wird im Profil zu „Research“. Der englische Kampfbericht nennt gefallene Truppen „Liked“; im aktuellen Kalender steht „Kills“ als Wochentag. Redaktionelle Chataktionen und einzelne Gebäude-/Markt-/Marschtexte bleiben deutsch. Spielertexte gezielt schützen und Bedienbeschriftungen mit eindeutigen Sprachschlüsseln ausgeben. |
| Mittel | **Große Anmeldeseite** | Die frühe lokale Bytebilanz maß rund 1,59 MB HTML, davon rund 1,586 MB eingebettete Sprachkataloge. Der spätere Schlusslauf registrierte 1.658.257 Transferbytes; der Kataloganteil wurde dort nicht erneut isoliert. Benötigte Sprachdaten gezielt laden und wiederverwendbar zwischenspeichern. Eine bestimmte Ladezeit auf echten Mobilgeräten ist daraus nicht abgeleitet. |
| Mittel | **Uneinheitliche Navigation** | „Market“ und „Shop“ führen zum gleichen Bereich. Allianzfunktionen verteilen sich auf mehrere Einstiege. Bezeichnungen vereinheitlichen und die häufigsten Aufgaben mit neuen Spielern prüfen. Dies ist eine Gestaltungsempfehlung, kein Beleg für unerreichbare Funktionen. |
| Nachgelagert | **Abgeschlossene Aufgaben und CSS-Pflege** | Abgeholte Aufgaben verlieren durch Gruppen-Transparenz Lesbarkeit. Status besser über Häkchen, Badge und Fläche zeigen. Die umfangreichen Stilüberschreibungen schrittweise pro Baustein zusammenführen. |

Konkrete Ansichten aus dem Schlusslauf: [Stadt 320 px](../../artifacts/ui-ux-audit-2026-09-30/app-matrix-final/city-320x568.png), [Inventar 320 px](../../artifacts/ui-ux-audit-2026-09-30/app-matrix-final/inventory-320x568.png), [Marsch mit verbesserter Hauptaktion im kurzen Querformat](../../artifacts/ui-ux-audit-2026-09-30/app-matrix-final/grumwald-march-568x320.png), [Gebäude 320 px](../../artifacts/ui-ux-audit-2026-09-30/app-matrix-final/building-upgrade-320x568.png). Der Gebäudebeleg zeigt die bereits erreichte Maximalstufe, keine freigeschaltete Ausbaubestätigung.

Die [Überdeckung im Gebäudefenster](../../artifacts/ui-ux-audit-2026-09-30/app-matrix-final/building-upgrade-568x320.png) wurde in der gezielten Nachprüfung erkannt und im Schlusslauf bestätigt. Sie liegt innerhalb eines ansonsten korrekt begrenzten Fensters; reine Außenmaße entdecken diesen Fehler nicht. Das Inventarsymbol erreicht mit den tatsächlich verwendeten Farben nur etwa 1,05:1 Kontrast.

Der spätere [Kartensuchbeleg bei 844 × 390](../../artifacts/ui-ux-audit-2026-09-30/rechecks-final/map_search_app/844x390-result.png) zeigt das Einsteigerziel über der Aktionsreihe. Der Test scheitert am tatsächlichen Trefferbereich der Schaltflächenmitte, ohne API-Fehlerantwort oder JavaScriptfehler. Daraus folgt keine Behauptung, dass die komplette Schaltfläche in jedem Kartenzustand unbedienbar wäre.

## Schrift und Menüfarben

**Die bestehende Kombination beibehalten.** Als nächste, im Layout zu prüfende Größen eignen sich etwa 11–12 px für kurze Navigationslabels, 13–14 px für wichtige Werte und 14–16 px für erklärende Texte. Das sind Designziele und keine vorgeschriebenen WCAG-Schriftgrößen. Ein globales Vergrößern aller Texte würde dichte Ausbildungs- und Marschfenster gefährden; die Anordnung muss mitwachsen.

Die deckenden gemeinsamen Farbpaare sind gut gewählt: Haupttext auf Papier erreicht 8,60:1, heller Text auf dem primären Violett 8,31:1 und ergänzender Text auf Papier 5,55:1. Diese Tokenwerte sind keine pauschale Abnahme jedes Textes über einem Bild, Verlauf oder transparenten Element. Gold sollte Akzent bleiben; wichtige Texte auf hellem Grund sollten dunkel sein. Blau für Verteidigung sowie die bestehenden Aktions- und Seltenheitsfarben behalten ihre Bedeutung.

Automatisch gemeldete Kontrastwerte an HUD-Labels, Chat und dem dekorativen „March“-Kopfetikett wurden gesondert überprüft: Gezeichnete Hintergründe, Schatten und die Lage des Textes im Verlauf können den rechnerischen Befund verfälschen. Diese Kandidaten werden deshalb nicht als bestätigte Kontrastverstöße gezählt; das Kopfetikett ist nicht die Senden-Aktion.

Englisch bleibt bei allen Verbesserungen Standard und Rückfallsprache, unabhängig von der Browsersprache. Spieler- und Weltnamen sowie Spielernachrichten bleiben unverändert. Auch der sichtbare interne Name „Conquer“ im Fehlerbericht sollte durch **Union of Kingdoms** ersetzt werden.

Als Referenz dienen die W3C-Erläuterungen zu [Textkontrast](https://www.w3.org/WAI/WCAG21/Understanding/contrast-minimum), [Mindestgröße von Bedienflächen](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum) und [Reflow](https://www.w3.org/WAI/WCAG21/Understanding/reflow). Die hier empfohlenen 44 px sind ein Komfortziel für wichtige Touchaktionen. Ein kleinerer Knopf ist nicht automatisch ein Verstoß gegen WCAG 2.2 AA; dort gelten andere Mindestwerte und Ausnahmen.

## Prüfung und Grenzen

Die Haupt-App wurde mit 1280 × 800, 390 × 844, 320 × 568, 844 × 390 und 568 × 320 CSS-Pixeln geprüft. Die abschließende Matrix steuerte Stadt, Weltkarte, Hauptmenü, **25 Panelrouten in allen fünf Formaten** sowie Gebäude- und Grumwald-Dialoge an. Von den 125 Panel-Inhaltsansichten wurden 124 erfolgreich erfasst; die Verteidigungsansicht bei 390 × 844 blieb unterbrochen. Ausgewählte lange Ansichten wurden zusätzlich am unteren Scrollende erfasst. Der englische Erststart wurde ausdrücklich mit deutschem Browserprofil geprüft. Hinzu kommen **15 Verwaltungsseiten in drei Formaten**, fünf ausgewählte Hauptrouten jeweils in DE/FR, ein fehlgeschlagener Login und vier Reflow-Ersatzmessungen.

Der Schlusslauf enthält **255 erfasste Zustände und 224 Screenshots**, teilweise Wiederholungen derselben Ansicht. **47 dieser Screenshots wurden manuell angesehen**, damit alle 25 Panelrouten mindestens einmal zusätzlich visuell geprüft. Es gab **keinen Browser-JavaScriptfehler**, aber drei HTTP-409-Antworten mit dem bestätigten Fehlercode `BUSY`. Eine davon verhinderte die erfolgreiche Prüfung der Verteidigungsansicht bei 390 × 844; stattdessen wurde ihre verständliche Wiederholen-Ansicht erfasst. Die anderen beiden betrafen Hintergrundabfragen bei Grumwald und im Verwaltungseditor. Diese Unterbrechungen sind keine bestandenen Abläufe.

In zwei Aufnahmen waren sichtbare Bilder noch nicht fertig geladen: ein Kartenicon in der Stadt sowie zwölf Reliktbilder in der Schatzkammer, jeweils bei 568 × 320. Die leeren Reliktkacheln sind im Screenshot sichtbar. Das belegt einen unvollständigen Ladezustand in dieser lokalen Testumgebung, keine dauerhaft defekten Dateien oder gemessene Leistung auf einem echten Smartphone.

Der Schlusslauf verwendete eine passende, eingefrorene Dateikopie vom **30.09.2026, 13:25:04 UTC**. Alle 130 erfassten CSS-/JS-/Sprachdateien stimmten zu Beginn mit dem Workspace überein und blieben im Fixture unverändert. Im Workspace wurden währenddessen vier Dateien weiterbearbeitet (`village-theme.css`, `alliance-community.js`, `march-panel.js`, `mvp-panels.js`); diese späteren Änderungen sind durch die Schlussmatrix nicht mitgeprüft.

Die zwei früheren Matrixläufe bleiben separat erhalten: zusammen 254 erfasste Zustände, 222 Screenshots und 54 manuell betrachtete Bilder. Dort wurden keine JS-Fehler, lokalen HTTP-Antworten ab 400 oder fehlenden sichtbaren Bilder registriert. Zwei Fehler im ursprünglichen Testablauf wurden im Ergänzungslauf nachgeholt. Frühere und abschließende Zähler dürfen nicht als Anzahl verschiedener App-Seiten addiert werden.

Getrennte Prüfungen behandeln EN/DE/FR, Anmeldung, Backoffice, Training, Forschung, Inventar, Heilung, Berichte, Kartenaktionen, Einstiegsziele und Quellenfinder. Die funktionale Prüfserie ist abgeschlossen:

| Runde | Ausgeführte Suiten | Bestanden | Abgebrochen/fehlgeschlagen |
|---|---:|---:|---:|
| Originale Ausgangsläufe | 48 | 8 | 40 |
| Gezielte erste Nachprüfung | 39 | 26 | 13 |
| Abschließende Nachprüfung der übrigen Fälle | 13 | 9 | 4 |

Nach dem jeweils letzten Ergebnis stehen **43 von 48 Suiten auf bestanden**. Diese Zahl führt mehrere dokumentierte Dateistände zusammen und ist kein Ergebnis eines einzigen Builds. Vier Suiten sind nicht vollständig abgeschlossen; `scene_transition_app` ist zusätzlich veraltet, weil es den auf Nutzerwunsch entfernten Stadt-Iframe voraussetzt. Heutige Stadt-/Weltwechsel wurden separat geprüft. Die [Zusammenfassung mit allen Einzelergebnissen](../../artifacts/ui-ux-audit-2026-09-30/closing-checks/functional-summary.json) bewahrt die Zuordnung zu den drei Runden.

| Offener Test | Letzter belegter Stand und Grenze |
|---|---|
| Inventarübersicht | Abbruch bereits beim initialen Inventar-Dockzugang. Keine API-Fehlerantwort und kein JS-Fehler erfasst; Ursache ungeklärt. Die eigentlichen Übersichtsschritte wurden nicht erreicht. Das normale Inventar und sein schlecht sichtbares Übersichtssymbol wurden separat erfasst. |
| Kartensuche | Desktop, 390 × 844 und 320 × 568 einschließlich Suche, Ergebnis und Zurück bestehen. Bei 844 × 390 verdeckt das Einsteigerziel die Tippmitte von „Weiter suchen“. Nachfolgende Suchkategorien sowie Fehler-/Abbruchfälle wurden in dieser Suite nicht erreicht. |
| Monsterbericht | Alle fünf Größen, Details, Zwischenablage, Kartenrückkehr, simuliertes Teilen, Scroll-/Polling-Erhalt sowie Rally-, Niederlage- und Altberichtvarianten erreicht. Nach Escape am Altbericht bleibt der Historienmarker bestehen. Ob auch das Fenster offen bleibt, ist nicht belegt. Direktlink und Löschabläufe wurden danach nicht mehr ausgeführt. |
| Sammelbelegung | Bei 1280 × 800 wurden eigene, verbündete und feindliche Belegung mit Aktionsmenüs geprüft. Bei einem freien Feld öffnet der aktuelle Code direkt den Sammeldialog; der ältere Test erwartet noch ein Aktionsmenü. Diese veraltete Erwartung verhindert den vollständigen Pass. Der direkte Dialog und die kleineren Formate sind in dieser Suite noch ungeprüft. |

Im vorherigen Sammellauf blockierte eine Orkfigur die Tippmitte eines Bauernhofs. Der letzte Lauf erreichte dessen eigene, verbündete und feindliche Aktionsmenüs erfolgreich und scheiterte erst an der geänderten Behandlung freier Felder. Das frühere Hindernis bleibt als begrenzter historischer Beleg erhalten; daraus wird kein weiterhin generell unbedienbarer Bauernhof abgeleitet.

**Bereits abgeschlossen:** 11/11 isolierte mobile bzw. statische Suiten bestehen. Dazu gehören 833 Marschprüfungen, 231 Fensterprüfungen, 30 Anmelde-/Registrierungslayouts und Prüfungen für Profil, Schatzkammer, Handel, Meldungen, Kampfrechner, Polling und Grafikqualität. Die Zahlen sind die jeweiligen Suitezähler und keine Anzahl unterschiedlicher Benutzerabläufe. [Exakte Abgrenzung und Ergebnisse](UI_UX_MOBILE_REVIEW_2026-09-30.md).

Die zusätzlichen sicheren Sprach-/Ablaufprüfungen lieferten 7/9 bestandene Originalprüfungen. Die zwei Abbrüche betreffen veraltete Testannahmen; ein gesonderter PWA-Nachlauf mit korrektem Schlüsselmengenvergleich bestand. Drei Sprachfehler sind separat reproduziert. [Ablauf- und Sprachbericht](UI_UX_FLOW_REVIEW_2026-09-30.md).

Die Ergebnisse sind **keine vollständige Geräte- oder Barrierefreiheitsabnahme**. Nicht durchgeführt wurden echte iOS-/Android-Gerätetests, vollständige Screenreaderbedienung, native Systemschriftvergrößerung, verlässlicher nativer 200-%-Zoom, längere Leistungs-/Akkumessungen oder ein Aufgabentest mit neuen Spielern. Die Reflow-/CSS-Zoom-Proben sind als Ersatzmessungen gekennzeichnet. Nicht jeder Unterzustand, jede Fehlermeldung und jede Spielregel ist mit dem Öffnen einer Hauptroute abgedeckt.

## Testpflege gehört zur Verbesserung

Viele ältere Funktionstests erwarten weiterhin Deutsch, obwohl Englisch jetzt korrekt die Standardsprache ist. Andere erwarten zehn statt fünf Truppenstufen, ältere Heilkosten oder einen früheren Einstiegsablauf. Ein Abbruch an solchen Erwartungen ist kein Produktfehler; die dahinterliegenden Schritte gelten ohne erfolgreichen Nachlauf aber auch nicht als geprüft.

Die Originalfehlläufe bleiben erhalten. Gesonderte Nachprüfungen protokollieren ausdrücklich gewählte Sprache, neue Ausgabeordner, Navigationstimeouts und – nur in drei ausgewählten Fällen – exakt begründete Erwartungen an die heutigen Spielregeln. Prüfungen werden nicht durch das Entfernen von Assertions grün gemacht. Die neuen Kernabläufe sollten anschließend in das reguläre Release-Testregister aufgenommen werden.

Der Arbeitsstand wurde während des Audits außerhalb dieser Auditänderungen weiterbearbeitet. Betroffen sind zentrale Spielmodule, Gemeinschaft und Allianz, Backoffice, CSS, Sprachdateien und einzelne Originaltests. Die Gemeinschaft wurde währenddessen in drei Einstiege aufgeteilt; dadurch stieg die Zahl der Panelrouten von 23 auf 25. Dateihashes und Zeitpunkte dokumentieren diese Bewegung; die Ergebnisse sind keine Freigabe eines unveränderlichen Git-Commits.

Die statische Momentaufnahme von 13:15:01 UTC enthielt 9.026 englische und deutsche, aber 9.007 französische Sprachschlüssel. Diese Lücke wurde während der parallelen Arbeiten geschlossen. Die unveränderte Sprachprüfung bestand zuletzt um **13:44:12 UTC mit je 9.050 identischen Schlüsseln in EN/DE/FR**, unveränderten Parametern und nichtleeren Werten. Alle drei Kataloghashes stimmen mit der Schlussmatrix überein und blieben während dieser Sprachprüfung stabil. Die historischen 19 fehlenden Verwaltungstexte sind damit geschlossen. Die Vollständigkeit einer Schlüsselmenge ersetzt weiterhin keine inhaltliche Übersetzungsprüfung. [Abschließender Sprachnachweis](../../artifacts/ui-ux-audit-2026-09-30/closing-checks/localization.json).

## Empfohlene Reihenfolge

1. Zuerst die Überdeckung von Kartenaktionen und Gebäudeanzeige sowie das helle Inventarsymbol korrigieren. Mobile Texte und wichtige Touchflächen mit passender Anordnung vergrößern. Jede Änderung erneut bei 320 × 568, 844 × 390 und 568 × 320 sowie am Desktop prüfen.
2. Danach Spielernamen vor automatischer Übersetzung schützen, falsche Bedeutungen und deutsche Resttexte beheben und den öffentlichen Namen in allen Ausgabewegen vereinheitlichen.
3. Die neuen Gemeinschaftseinstiege mit konkreten Aufgaben prüfen: Nachricht finden, Allianz beitreten, helfen, spenden und zum vorherigen Ort zurückkehren. Erst daraus weitere Navigationseingriffe ableiten.
4. Sprachdaten und globale Ressourcen gezielter ausliefern. Ladegröße, Start und Wiederaufnahme anschließend auf echten iOS-/Android-Geräten messen.

Die gewählten Schriftfamilien, Menüfarben, gezeichnete Stadt und semantischen Rollenfarben bilden dabei weiterhin die Grundlage.

## Belege

- [Gestaltung, Schriftmessungen und Farbpaare](UI_UX_DESIGN_REVIEW_2026-09-30.md)
- [Sichtprüfung der echten App](UI_UX_APP_VISUAL_REVIEW_2026-09-30.md)
- [Mobile Layouts, Schriften und Prüfgrenzen](UI_UX_MOBILE_REVIEW_2026-09-30.md)
- [Navigation, Sprache und Testklassifikation](UI_UX_FLOW_REVIEW_2026-09-30.md)
- [Haupt-App-Messdaten](../../artifacts/ui-ux-audit-2026-09-30/app-matrix/report.json)
- [Ergänzende App- und Backoffice-Messdaten](../../artifacts/ui-ux-audit-2026-09-30/app-matrix-remaining/report.json)
- [Schlussmatrix mit 25 Panelrouten und Dateihashes](../../artifacts/ui-ux-audit-2026-09-30/app-matrix-final/report.json)
- [Unveränderte funktionale Ausgangsläufe](../../artifacts/ui-ux-audit-2026-09-30/functional/results.json)
- [Abschließende funktionale Nachläufe](../../artifacts/ui-ux-audit-2026-09-30/functional-final-rechecks/results.json)
- [Manuelle Sichtbelege der Schlussmatrix](../../artifacts/ui-ux-audit-2026-09-30/design/final-visual-evidence.json)
