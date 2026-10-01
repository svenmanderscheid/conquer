# Mobile Layout Review – Union of Kingdoms

**Neuester Sprachnachweis, 13:44:12 UTC:** EN, DE und FR besitzen jeweils **9.050 identische Schlüssel**. Die dabei geprüften Dateihashes stimmen exakt mit der finalen App-Fixture überein. Damit ist auch deren Katalogparität belegt; sprachliche Qualität ist damit nicht pauschal bestätigt. Der Abschlussnachtrag unten enthält die Belege.

**Lesestand:** Die nachfolgenden statischen Befunde bleiben mit ihrem jeweiligen Zeitstempel erhalten. Spätere Belege schließen zwei Punkte: Im Sprachnachlauf um 13:23:54 UTC besitzen EN/DE/FR jeweils 9.026 identische Schlüssel; der Marschabschluss erreicht im finalen App-Fixture bei 320 × 568 und 568 × 320 jeweils 44 px. Die früheren 19 fehlenden FR-Schlüssel und 35-/38-px-Marschmessungen sind damit historische Befunde. Einzelheiten stehen im Nachtrag am Ende.

Datum: 30. September 2026. Teilprüfung: isolierte mobile Geometrie, Bedienflächen und Test-Fonts. Keine Datenbank, Anmeldung an einem Spielkonto oder schreibende Spiel-API verwendet. Die echte Haupt-App wird separat vom koordinierenden Agenten geprüft.

## Ergebnis

**11 von 11 freigegebenen statischen Suiten bestehen.** Die zusätzliche Schriftprobe hat drei unzuverlässige Fixture-Verträge aufgedeckt und korrigiert: Profil, Schatzkammer und Handelsposten luden zuvor Ersatzschriften. Mit tatsächlich geladenem `Conquer UI` bestehen die gleichen Layoutprüfungen weiterhin. Vier Testdateien wurden angepasst; keine Produktdatei wurde geändert.

Aus dieser Teilprüfung folgt noch kein bestätigter Fehler der Haupt-App. Zwei Beobachtungen sind für die dortige Prüfung relevant: kleine Marschaktionen und Vergrößerung. Die weiter unten genannten Abmessungen sind Messungen des korrigierten statischen Fixtures, keine behauptete Bestätigung in der Haupt-App.

## Priorisierte Beobachtungen

1. **P2 – Marschaktionen unter dem empfohlenen 44-Pixel-Maßstab; Haupt-App-Bestätigung ausstehend.** Im korrigierten mobilen Marschfixture ist `#march-confirm` bei 320 × 568 und 390 × 844 jeweils 38 px hoch, bei 568 × 320 nur 35 px. Im kurzen Querformat sind die Reiter und die Aktionen Leeren/Max 36 px hoch. Zurück hat 44 × 44 px; Kampfrechner-Schließen und Neuberechnen erreichen 44 px. Die Werte entsprechen den weiterhin vorhandenen Regeln in `assets/css/march-panel.css:88` und `:89`. Empfehlung für die Haupt-App: diese konkreten Aktionen mit geladener Schrift und ohne Vergrößerung messen; erst danach gegebenenfalls die Touchflächen erhöhen. 44 Pixel dienen hier als empfohlener Projektmaßstab für gut bedienbare Touchflächen. Der Nutzer bat um einen vollständigen UI/UX-Test, ohne ein numerisches Ziel vorzugeben. Die vorhandene Stilrichtlinie erlaubt teilweise kompakte Bedienelemente; die Unterschreitung dieses Maßstabs allein ist daher noch kein bestätigter Produktfehler.
2. **P2 – Vergrößerung ist noch nicht als bestandene Produkteigenschaft nachgewiesen.** Die ergänzende CSS-`zoom:2`-Probe lässt feste Marsch- und Rechnerfenster über die Höhe hinausragen; im Rechner entstehen in 320 px Breite 123 CSS-Pixel horizontaler Überlauf. **CSS-Zoom ist ein Diagnoseersatz, kein Beweis für natives Browser-Zoom, Textvergrößerung oder iOS-/Android-Pinch.** Die echte App behandelt `visualViewport.scale` ausdrücklich separat. Daher kein bestätigter Produktfehler aus dieser Probe; natives Zoom bzw. ein Gerätetest bleibt erforderlich. Eine pauschale Aussage „200 % geprüft und bestanden“ wäre derzeit falsch.
3. **QA-01 – Ersatzschriften kaschierten die tatsächliche Geometrie; behoben.** Vor der Korrektur meldete `document.fonts.check` in Profil, Schatzkammer und Handelsposten `false`. Profil-Fontfaces hatten den Status `error`; Schatzkammer/Handelsposten brachen je drei Requests nach `/fonts/*.woff2` ab. Die statischen Tests bestanden dennoch. Nun stimmen die Font-Assetpfade; ein expliziter Fontload plus Assertion bestätigt Buchstaben, Akzente und Ziffern. Die finalen Proben haben `check: true`, keine fehlerhaften Fontfaces und keine fehlgeschlagenen Fontrequests.
4. **QA-02 – Veralteter mobiler Dialogaufbau im Rechnerfixture; behoben.** Die Fixture-Version von `openDialog` rief anders als `game.js` nicht `mobile.syncDialog()` auf. Dadurch maß die erste Zusatzprobe einen veralteten 25/26-Pixel-Schließknopf. Der fehlende Aufruf wurde ergänzt und die Suite erneut bestanden. Diese erste Messung wurde ausdrücklich verworfen; sie ist kein Produktbefund. Die korrigierte Probe misst den tatsächlichen mobilen Zurückknopf mit 44 px.

Belege: [Zusatzmessungen](../../artifacts/ui-ux-audit-2026-09-30/mobile-static/supplemental-metrics.json), [Querformat-Marsch](../../artifacts/ui-ux-audit-2026-09-30/mobile-static/supplement-march-568x320-zoom1-no-preference.png), [Zoom-Diagnose](../../artifacts/ui-ux-audit-2026-09-30/mobile-static/supplement-preview-320x568-zoom2-no-preference.png). Die Produktkandidaten wurden dem koordinierenden Agenten mit Selektoren und Abmessungen für die Haupt-App-Prüfung übergeben.

## Exakte Suite-Ergebnisse

| Suite | Ergebnis | Tatsächlicher Umfang / Grenze |
|---|---|---|
| `profile_layout.cjs` | PASS | 5 Formate: 320 × 568, 390 × 844, 568 × 320, 1024 × 683, 1280 × 800; Fenstergrenzen, Überlauf und Profilbedienelemente. Mit korrigiertem Fontload wiederholt. |
| `march_windows.cjs` | PASS, 833 Checks | Marscharten und Formularverhalten, 5 Formate einschließlich 320-Hoch-/568-Querformat, Zielbild/Text, interne Scrolllisten, ETA und Reportlayout; reine Aktionsmocks. Die Tests prüfen keine generelle Mindestgröße von 44 px. |
| `battle_preview_ui.cjs` | PASS | 4 Rechnerformate: 1280 × 720, 390 × 844, 320 × 568, 844 × 390; erreichbares 44-px-Schließen, Back/Escape, DE/EN/FR, Monster- und PvP-Zweige, keine Marschausführung. Nach Dialog-Fixture-Korrektur erneut PASS. |
| `window_panels.cjs` | PASS, 231 Browserchecks + 26 Itemchecks + 4 Usechecks | 5 Formate einschließlich 320 × 568 und 568 × 320; Inventar, Aufgaben, Allianz, interne Scrollwege und Fokusbeibehaltung. `failures`, `browserErrors`, `requestErrors` leer; `mutationAttempts: 0`. |
| `treasure_panel.cjs` | PASS, 35 Checks | 4 Formate einschließlich 320 × 568 und 568 × 320; 77 Relikte, 5 Presets, 6 Plätze, Details, Ausrüstung und Truhen. Mit echten Fonts wiederholt. |
| `trading_panel.cjs` | PASS, 30 Checks | 4 Formate einschließlich 320 × 568 und 568 × 320; Händler, Karawane, persönlicher/gesamter VIP-Shop, letztes Angebot erreichbar. Mit echten Fonts wiederholt. |
| `toast_layout.cjs` | PASS | 3 Formate × 3 Ebenen (Spielfläche, Panel, Detaildialog): kompakt, obere Bildschirmhälfte, innerhalb seitlicher Grenzen. |
| `play_login_layout.cjs` | PASS, 30 Layouts | Anmeldung/Registrierung × DE/EN/FR × 5 Formate; kein horizontaler Überlauf, Zentrierung, Logo, Unterordner-Assets, CSP. Sichtbare Eingaben/Submit/Navigation erfüllen die bestehende Prüfschwelle von mindestens 43 px; dies ist keine strikte 44-px-Assertion. |
| `app_polling.cjs` | PASS | VM-Test: keine überlappenden Abrufe, Hidden/Offline/Online/Pageshow, begrenzter Wiederholungsablauf, Listenerabbau. |
| `graphics_quality.cjs` | PASS | VM-Test: konservative automatische Auswahl, Persistenz, manuelle Wahl, automatische Herabstufung. Kein GPU-/Geräte-Leistungstest. |
| `dungeon_panel.cjs` | PASS | Lokales DOM und reine Speichermocks: Übersicht/Gruppen/Berichte in 320 × 700, 390 × 844, 667 × 375, 1280 × 800; Planer, Rollen, Truppenvorgaben, veraltete Vorschauen, Draftbeibehaltung und Welttrennung. **Kein 568 × 320, kein echtes App-Fenster; die Fixture-CSS-Liste enthält keine `fantasy-fonts.css`.** |
| `training_mobile_layout.cjs` | NICHT GESTARTET | Startet zwingend `preview-feature-fixture.php` mit isolierter Datenbank und Anmeldung. Dieser Lauf gehört ausschließlich in den vom Root koordinierten App-Slot. |

Jede ausgeführte Suite hat einen eigenen `.log` und `.result.json` unter [mobile-static](../../artifacts/ui-ux-audit-2026-09-30/mobile-static/). Der Logkopf enthält SHA-256 des jeweiligen Testquelltexts. Es gab keine verlängerten Assertions/Timeouts und keine abgeschwächten Prüfkriterien.

## Ergänzende mobile Probe

`supplemental-audit.cjs` prüft den gespeicherten, korrigierten Rechner/Marsch-DOM mit den Produktionsmodulen in 320 × 568, 568 × 320 und 390 × 844; jeweils normal und CSS-Zoom 2 sowie `prefers-reduced-motion: reduce`/Standard: **24 Ansichten**.

- Bei Normalgröße: Fenster innerhalb des Bildschirms, kein horizontaler Überlauf und keine durch andere Elemente verdeckten vollständig sichtbaren Controls.
- Der Kampfrechner erfüllt für seine sichtbaren Aktionen die 44-Pixel-Größe in allen drei Formaten.
- Die gemessenen Tastaturstationen besitzen sichtbare 2–3-Pixel-Fokusumrandungen. Das ist eine Stichprobe von sechs Tab-Schritten pro Ansicht, keine vollständige Tastatur- oder Screenreaderabnahme.
- Bei reduzierter Bewegung meldet `document.getAnimations()` zum Messzeitpunkt keine laufende Animation. Das ist eine Momentaufnahme der getesteten Fenster, keine Abnahme der animierten Stadt/Welt.
- Die Schriftprüfung für die ergänzenden Ansichten besteht. Gemessene Farben stammen aus der produktiven gemeinsamen CSS-Kaskade; eine vollständige Kontrastabnahme erfolgt separat in der Haupt-App.
- Der teilweise außerhalb des Startausschnitts liegende Rechnerknopf gehört zu einem internen Scrollbereich. Seine Anfangsposition allein wird deshalb ausdrücklich nicht als unerreichbarer Knopf gewertet.

Einige Screenshots wurden zusätzlich visuell gelesen: Profil 320 px, Schatzkammer 568 × 320, Handel 320 px und Marsch 568 × 320. Schriften sind lesbar geladen, Fenster verwenden Beige/Violett, die betrachteten Inhalte überdecken sich nicht. Statische Profil-/Dungeonfixtures ersetzen dennoch keine Abnahme der echten mobilen Navigation.

## Eng begrenzte Testkorrekturen

- `tests/profile_layout.cjs`: lokaler HTTPS-Assetursprung plus ausschließliche Font-Route; explizit geladene gemeinsame Schrift vor der vorhandenen Geometrieprüfung.
- `tests/treasure_panel.cjs` und `tests/trading_panel.cjs`: `/fonts/` auf lokale `assets/fonts/` abbilden, WOFF2-MIME-Typ und Fontassertion. Kein externer Netzwerkzugriff.
- `tests/battle_preview_ui.cjs`: im vorhandenen Open-Dialog-Mock den realen `mobile.syncDialog()`-Schritt ergänzen.

Zwischenlauf: Die erste neue Handels-Fontassertion war noch negativ, obwohl das Mapping korrigiert war: `document.fonts.ready` wartet nur auf bereits angeforderte Zeichen/Schriftschnitte. Anschließend wurde derselbe Prüfsatz mit `document.fonts.load(...)` explizit angefordert, bevor die unveränderte `check`-Assertion ausgeführt wird. Der endgültige Lauf besteht mit geladenen Fonts; der Fehler wurde nicht durch Entfernen der Assertion verdeckt.

## Ausführung und Abgrenzung

Der Audit-Launcher `artifacts/ui-ux-audit-2026-09-30/mobile-static/run-static.cjs <suite>` belässt `__dirname` und die Testdatei-Zuordnung bei `tests/`, lenkt aber temporäre Fixture-Ausgaben in den zugewiesenen Artefaktordner. Beim Login-Test ersetzt er ausschließlich den fest codierten Screenshot-Ausgabepfad im Arbeitsspeicher. `run-group.cjs` protokolliert Exitstatus und Dauer. Die Prüfassertions bleiben unverändert, ausgenommen die dokumentierten zusätzlichen Fontassertions.

Alle Browser und der rein statische Login-Server wurden beendet. Es wurde keine DB/App-Fixture gestartet. Nicht behauptet: echte iOS-/Android-Geräteabnahme, native Tastatur/Safe-Area, Betriebssystem-Textskalierung, vollständige Screenreaderbedienung, sämtliche Touchziele ≥44 px oder bestandenes natives 200-%-Zoom.


## Ergänzung: Ladegewicht und erster Seitenaufruf

Diese Ergänzung verwendet ausschließlich die bereits gespeicherte Haupt-App-Matrix und Quellcode. Zusätzlich wurde die vorhandene eigenständige Login-View per CLI ohne `Bootstrap`, Server oder Datenbankverbindung in einen Speicherpuffer gerendert, um ihre Bytes aufzuschlüsseln. Es wurde kein neuer Browser- oder Datenbanklauf gestartet. Aktuelle Workspace-Bytes können wegen gleichzeitiger redaktioneller Änderungen geringfügig von den zuvor erfassten HTTP-Antworten abweichen.

### Zeitstand und beweglicher Arbeitsstand

Die folgende Bytebilanz wurde am **30.09.2026 um 12:31:07 UTC (14:31:07 Luxemburg)** aufgenommen. Das Artefakt enthält für alle berücksichtigten Quelldateien SHA-256, Bytezahl und Änderungszeit; zwischen Beginn und Ende dieser Erfassung änderte sich keine davon. Die Browsermatrix trägt den eigenen Zeitstempel 12:21:56 UTC. Die frühere funktionale Baseline stammt von 12:05:28 UTC. Diese Zeitstände sind getrennt zu betrachten; der Audit prüft einen gemeinsam weiterbearbeiteten Workspace.

Gegenüber der funktionalen Baseline haben in diesem Quellumfang genau sechs Dateien andere Hashes: `assets/css/village-theme.css`, `assets/js/admin-backoffice.js`, beide Reward-Admin-Views sowie die englischen und deutschen Sprachkataloge. `views/game.php`, `src/Game/Locale.php` und der französische Katalog stimmen mit der Baseline überein. Diese parallelen Produktänderungen stammen nicht aus dieser lesenden Audit-Ergänzung.

Der frühe Designbericht erfasst **1.087.902 CSS-Bytes**, die spätere Bilanz **1.091.133 Bytes**. Der gesamte Unterschied von **3.231 Bytes** entfällt auf `village-theme.css` (335.884 → 339.115 Bytes; aktueller SHA-256 `f82243bc40e385d4a2c5dceb9c5a5ac85c362c8fd7d17683897fd8055108a3cc`). Die aktuelle Endbereichsprüfung findet in Zeilen 3839–3960 einen ausschließlich mit `.admin-village` begrenzten Reward-Verwaltungsblock, einschließlich der Media Queries. Darauf folgt ab Zeile 3961 der separate bestehende gemeinsame Block für Anfängerziele, Bossregeln und Gegenstandsquellen. Das Admin-Skript und die beiden Admin-Views werden von `views/game.php` nicht referenziert. Die frühe Größenliste enthält keinen CSS-Quelltext: Eine vollständige Zuordnung jeder geänderten Deklaration zu diesen 3.231 Bytes wäre deshalb ohne weiteren historischen Quellstand nicht belegt.

### Beobachtete lokale Messwerte

| Bestehender Matrixeintrag | Bis zum ersten Byte | Ende der HTML-Antwort | HTML `transferSize` | Zusätzliche Ressourcen im Snapshot |
|---|---:|---:|---:|---:|
| `navigation-login`, `/` | 15.165,6 ms | 15.167,2 ms | 1.589.438 Bytes | 10 Einträge; 910.278 Transferbytes |
| `navigation-main-app`, `/city` | 179,8 ms | 195,0 ms | 1.619.742 Bytes | 163 Einträge; 6.884.893 Transferbytes, 7.149.106 dekodierte Bytes |

Der Login-Body wurde nach dem ersten Byte innerhalb von ungefähr **1,6 ms** empfangen, der App-Body innerhalb von **15,2 ms**. Die große Antwort allein erklärt deshalb den beobachteten Zeitraum vor dem ersten Byte nicht. Der Ressourcen-Snapshot der App stammt vom Zeitpunkt ungefähr 4,13 Sekunden nach Navigationsbeginn; er kann asynchron geladene Daten, Cachetreffer und noch nicht abgeschlossene Abrufe enthalten. Die 163 Ressourcen sind keine vollständige Aufschlüsselung des kritischen ersten Bildaufbaus.

Die als `ttfbMs` bezeichnete Messung im frühen Snapshot ist `navigation.responseStart - navigation.startTime`. Sie enthält damit den Zeitraum ab Navigationsbeginn; damals wurden **keine einzelnen DNS-, Verbindungs-, Request- oder Serverphasen** gespeichert. Aus dieser Zahl darf keine reine PHP-Ausführungsdauer abgeleitet werden. Der unten ergänzte spätere Lauf trennt diese Phasen.

### Ursache der HTML-Größe: belegt

`src/Game/Locale.php:63` lädt in `bootstrap()` jede unterstützte Sprache und serialisiert alle Kataloge gemeinsam als `window.CONQUER_I18N`. Sowohl `views/play_login.php:23` als auch `views/game.php:38` schreiben diesen Block direkt in das HTML.

Die reine Bytebilanz der aktuellen Login-View ergibt:

- HTML gesamt: **1.589.177 Bytes**.
- Inline-Locale-Bootstrap: **1.586.298 Bytes**, also **99,819 %** der View.
- Verbleibendes HTML: **2.879 Bytes**.
- Darin enthaltene serialisierte Sprachkataloge: Englisch 495.600 Bytes, Deutsch 525.101 Bytes, Französisch 565.530 Bytes, plus Wrapper/Metadaten.

Damit ist die ungefähr 1,6-MB-Antwort auf der Anmeldung konkret erklärt; auch die Haupt-App enthält denselben großen Block. Der Code sendet die Kataloge unabhängig von der gewählten Sprache. `assets/js/localization.js:8` übernimmt sie unmittelbar und baut ab Zeile 16 außerdem einen Erkennungsindex aus allen drei Sprachen auf. Ein einzelner reiner CLI-Viewdurchlauf benötigte lokal ungefähr 13–14 ms; das dient ausschließlich der Einordnung dieses isolierten Renderpfads, **nicht** als Aussage über HTTP, Datenbank oder Gerätegeschwindigkeit.

Die Haupt-App referenziert in `views/game.php` zusätzlich **50 CSS-Dateien mit zusammen 1.091.133 Bytes** und **59 JavaScript-Dateien mit zusammen 1.143.639 Bytes** im aktuellen Workspace. Das sind unkomprimierte Dateigrößen, keine gemessenen Transferbytes. Größter Stylesheet ist `village-theme.css` mit 339.115 Bytes; größtes Skript `world-map.js` mit 145.461 Bytes. Die Module werden in der gemeinsamen Seite referenziert, auch wenn zunächst nur die Stadt sichtbar ist. Die gespeicherte Resource-Timing-Matrix erlaubt keine sichere Zuweisung aller 6,88 MB zu einzelnen Bildern, Skripten oder APIs.

### Historischer Erstbefund zu den 15 Sekunden

Vor der späteren detaillierten Zeitmessung war folgende Eingrenzung aus dem Quellcode möglich:

- **Fixture-Erstellung ist außerhalb des gemessenen Intervalls.** Kopieren, Schema-/Datenerstellung und Seeding erfolgen in `tools/preview-feature-fixture.php` vor `proc_open` und der Meldung „Synthetic preview ready“. Erst nachdem die Matrix diese Meldung empfangen und einen neuen Browserkontext erzeugt hat, startet `page.goto` (`tests/ui_ux_matrix.cjs:55–60`). Die gesamte Fixture-Vorbereitung darf deshalb nicht nachträglich als Erklärung für die gemessenen 15 Sekunden angegeben werden.
- **Ein Kaltstart des HTTP-Prozesses ist nicht aufgelöst.** Die Fixture meldet Bereitschaft unmittelbar nach `proc_open`, ohne HTTP-Bereitschaftsprobe. Die neue PHP-Serverinstanz liegt somit im Umfeld des ersten Requests; es gibt aber keine gespeicherten Teilzeiten, die ihr die 15 Sekunden zuweisen.
- **Datenbankinitialisierung liegt auf dem Loginpfad.** `src/Bootstrap.php:78` öffnet über `Connection::init`/`new PDO` bereits vor dem Routing die Verbindung. Damit bleibt die erste Verbindung eine zu instrumentierende Stufe. Dass sie tatsächlich langsam war, ist nicht gemessen.
- **PHP-Sessions liegen ebenfalls auf diesem Pfad, aber Konkurrenz ist nicht belegt.** `index.php:92–109` startet `conquer_login`. Die Fixture erzeugt einen frischen Sessionordner und der Test einen frischen Browserkontext. Ohne Spielcookie kehrt `Session::current()` direkt zurück, bevor eine Session-Abfrage ausgeführt wird. Ein konkret blockierender paralleler Session-Request ist in den vorhandenen Daten nicht nachgewiesen.
- **Keine konkrete externe Resolver-/OAuth-Ursache gefunden.** Die Dokumentnavigation benutzt `127.0.0.1`; die Fixture übernimmt hier auch `127.0.0.1` als DB-Host. Der Login-GET ruft keine externe HTTP-/OAuth-Schnittstelle auf. Ein externer Namensauflöser ist auf diesem untersuchten Anwendungspfad daher nicht als Ursache belegt. Betriebssystem-/Browser-/Datenbank-internes Verhalten wurde nicht instrumentiert.

Der damalige Stand war: **Ein langsamer erster lokaler Antwortbeginn wurde beobachtet; der genaue Verzögerungspunkt war noch offen.** Getrennte Navigationsphasen sowie Serverzeitmarken vor/nach Bootstrap-DB-Verbindung, `session_start` und Viewrendering fehlten. Im Rahmen dieser lesenden Ergänzung wurde kein weiterer Browserlauf gestartet.

### Nachtrag: spätere Messung grenzt die Wartephase ein

Die vom koordinierenden Testlauf gespeicherte [Matrix vom 30.09.2026, 12:40:46 UTC](../../artifacts/ui-ux-audit-2026-09-30/app-matrix-remaining/report.json) enthält nun einzelne Navigationsphasen. Beim erneuten Loginaufruf liegt `fetchStart` bei **0,4 ms**, `domainLookupStart` und `domainLookupEnd` jeweils bei **15.079,2 ms**, `requestStart` bei **15.080,6 ms** und `responseStart` bei **15.101,9 ms**. Von der gesendeten Anfrage bis zum ersten Antwortbyte vergehen somit nur **21,3 ms**; auch dieser Wert enthält Transportzeit und ist keine reine PHP-CPU-Zeit.

Die lange Wartezeit dieses späteren Laufs liegt damit **vor dem Request und vor der erfassten DNS-Phase**. Die DNS-Phase selbst hat in diesen Daten keine messbare Dauer; eine „15 Sekunden dauernde DNS-Auflösung“ wäre ebenso wenig belegt wie eine „15 Sekunden dauernde PHP-Verarbeitung“. Die genaue Ursache innerhalb von Browser, Betriebssystem oder lokaler Verbindungsverwaltung bleibt offen. Die früheren Zahlen oben bleiben als eigener Snapshot erhalten; die neue Eingrenzung erklärt die Position der Wartephase im späteren Lauf, ohne nachträglich fehlende Phasenwerte des ersten Laufs zu erfinden.

### Priorität für eine spätere Optimierung

1. **Zuerst den belegten HTML-Ballast reduzieren.** Für Anmeldung/Registrierung genügt ein klar begrenzter öffentlicher Textumfang. Für die App lassen sich Sprachdaten versioniert und getrennt von sitzungsgebundenem HTML ausliefern. Dabei muss der bestehende sprachübergreifende Erkennungsindex erhalten bzw. gezielt ersetzt werden; bloßes Entfernen zweier Kataloge kann die aktuelle Übersetzungslogik beschädigen.
2. **Öffentliche Daten und privates HTML getrennt cachen.** Das HTML bleibt wegen Sitzungs-/CSRF-Daten `private, no-store`. Öffentliche Sprachpakete gehören in einen ausdrücklich öffentlichen Assetpfad, **nicht durch Freigabe des gesperrten `data/`-Verzeichnisses** (`.htaccess:36`). Der aktuelle Service Worker listet zwar `data/i18n/*.json`, begrenzt jedoch einzelne Cacheeinträge auf 512 KiB; die heutigen Quelldateien liegen darüber. Auslagern allein würde diese Dateien daher nicht automatisch korrekt erreichbar oder offline-cachebar machen.
3. **Erst mit genauerer Messung das Laden von Funktionsmodulen priorisieren.** Die 50 CSS-/59 JS-Referenzen sind ein nachvollziehbarer Ansatzpunkt für späteres bedarfsweises Laden. Auswirkungen auf Navigation, Sprachwechsel und mobile Wiederaufnahme müssten mitgeprüft werden.

Die Produktionskonfiguration besitzt Apache-`mod_deflate`-Regeln für HTML/CSS/JS/JSON (`.htaccess:115`). Die lokale Fixture verwendet dagegen PHPs eingebauten Server und einen eigenen Assetrouter; sie bildet diese Apache-Kompression nicht nach. Eine rein rechnerische Gzip-Stufe-6-Kompression des gesamten Inline-Katalogblocks ergibt 607.432 Bytes. Das ist ein mögliches Größenverhältnis, **kein gemessener Produktionsdownload**. Weder diese Bytebilanz noch die lokale Navigation liefern einen belastbaren Geschwindigkeitswert für iOS, Android oder langsame Mobilfunkverbindungen.

Belege: [bestehende Haupt-App-Matrix](../../artifacts/ui-ux-audit-2026-09-30/app-matrix/report.json), [reine Bytebilanz](../../artifacts/ui-ux-audit-2026-09-30/mobile-static/payload-accounting.json), [strukturierte Quellenanalyse](../../artifacts/ui-ux-audit-2026-09-30/mobile-static/cold-start-analysis.json).

## Historische statische Momentaufnahme, 13:15:01 UTC

Dieser Nachtrag vom **30.09.2026, 13:15:01 UTC (15:15:01 Luxemburg)** liest ausschließlich Quellcode und JSON. Er startet weder Browser noch Anwendung noch Datenbank. [latest-static-status.json](../../artifacts/ui-ux-audit-2026-09-30/mobile-static/latest-static-status.json) enthält die Inhalteinordnung sowie SHA-256, Dateigröße und Änderungszeit für **129 Quelldateien**. Alle ausgewerteten Inhalte wurden gepuffert; keine dieser Dateien änderte sich innerhalb des kurzen Erfassungsintervalls. Parallele Arbeiten außerhalb dieses Intervalls bleiben möglich. Frühere Screenshots, Laufzahlen und Bytebilanzen werden deshalb nicht als Abnahme dieses späteren Stands umgedeutet.

### Sprachkataloge: um 13:15:01 UTC noch keine Parität

| Katalog | Schlüssel | Änderung seit Bytebilanz 12:31:07 UTC | Fehlend gegenüber Englisch |
|---|---:|---:|---:|
| Englisch | 9.026 | +374 | 0 |
| Deutsch | 9.026 | +374 | 0 |
| Französisch | 9.007 | +363 | 19 |

Alle drei Dateien sind gültiges JSON, ihre vorhandenen Werte sind nichtleere Zeichenketten. EN und DE enthalten dieselbe Schlüsselmenge. FR fehlen weiterhin die acht Schlüssel `admin.drops.matrix_view`, `matrix_items`, `matrix_item`, `matrix_hint`, `matrix_no_drop`, `matrix_resource_hint`, `matrix_solo` und `matrix_rally` (jeweils mit Präfix `admin.drops.`). Zusätzlich fehlen elf neuere Verwaltungsbegriffe: `add_drop`, `add_drop_for`, `drop_added_note`, `drop_focus_note`, `drop_limit`, `edit_drop`, `reset_filters`, `search_monsters`, `search_monsters_hint`, `search_sources` und `search_sources_hint` unter demselben Präfix. Die vollständige Liste und EN-/DE-Werte der acht Matrixschlüssel stehen im Artefakt.

Die **330 Schlüssel** unter `social.`, `social_chat.` und `alliance_community.` sind dagegen in allen drei Katalogen vorhanden. Das bestätigt Vollständigkeit dieser Schlüsselmenge, nicht sprachliche Qualität sämtlicher Übersetzungen. `Locale::DEFAULT='en'` und die englische Rückfallebene bleiben erhalten; fehlende französische Einträge fallen daher auf Englisch zurück und dürfen nicht als vollständig französisch übersetzt gelten.

### Neue Module verändern die Prüfabdeckung

`views/game.php` bindet jetzt **61 JavaScript- und 52 CSS-Dateien** ein. Gegenüber der früheren Baseline hinzugekommen sind `social-hub.js`, `alliance-community.js` und die gleichnamigen Stylesheets. Neun bereits verknüpfte JavaScript-Dateien haben ebenfalls andere Hashes, darunter `game.js`, `mvp-panels.js`, `mobile-pages.js`, `world-chat.js` und `community-panel.js`.

Die Route `community` verwendet inzwischen `ConquerSocialHub`; die bisherige Gemeinschaftskomponente wird unter `alliance-tools` geöffnet, und `alliance-community` besitzt einen eigenen Renderer (`game.js:357`, `:572`, `:573`). Der frühere Nachweis für 23 Hauptrouten gilt somit für den damaligen Stand. Er beweist weder die neue Gemeinschaftsansicht noch diese neuen Routen. Alle **61 eingebundenen JavaScript-Dateien** wurden aus den erfassten Inhalten syntaktisch geparst; es gab keinen Syntaxfehler. Dabei wurde kein Modul ausgeführt, kein API-Ablauf getestet und keine mobile Geometrie neu gemessen.

### Frühere Befunde im aktuellen Quellstand

- **Inventarsymbol: Ursache weiterhin vorhanden.** `inventory-reference.css:167` zeichnet das Symbol über `stroke:currentColor`; der helle Knopfgrund bleibt in `village-theme.css:177` bestehen. Die späte Regel für SVGs und Pfade innerhalb der Fensterköpfe setzt weiterhin `--ui-card-light` (`village-theme.css:3446`). Damit ist keine Behebung des früheren Befunds im untersuchten Quellstand belegt. Der historische Screenshot und sein Kontrastwert bleiben historische Messungen, keine neue Browsermessung.
- **Kleine HUD-Texte: einschlägige Regeln bestehen weiter.** Docklabels verwenden unter 360 px weiterhin 8 px (`village-theme.css:3124`); die kurze Querformatregel setzt ebenfalls 8 px (`:3129`). Aktionsenergiewerte haben 8 px und im kurzen Querformat 7 px (`:2939`, `:2982`, `:3022`). Die früheren Größenbefunde sind daher nicht durch eine erkennbare Korrektur dieser Regeln erledigt; aktuelle berechnete Stile wurden nicht erneut erhoben.
- **Öffentlicher Spielname: konkrete Ausgabepfade enthalten weiterhin „Conquer“.** Fehlerformular, Bestätigung und fehlender Kontextname verwenden ihn in `bug-reports.js:12`, `:16` und `:54`; die zugehörigen Formulierungskataloge enthalten den Namen ebenfalls. Die Kopieraktion des Monsterberichts schreibt ihn in den Zwischenablagetext (`monster-report.js:154`). `index.php:140` und `:754` enthalten ihn in der Datenbankfehlerseite bzw. der nachgeordneten Coming-Soon-Vorlage; diese bedingten Ausgaben wurden hier nicht aufgerufen. Die PvP-Kopieraktion verwendet bereits „Union of Kingdoms“ (`combat-report.js:137`). Technische Namespaces, Speicherkennungen, `Conquer UI` und das englische Verb „conquer“ werden ausdrücklich nicht als Namensfehler gezählt. Weitere reine Katalogtreffer sind im Artefakt als Kandidaten ohne bewiesenen aktuellen Renderpfad getrennt erfasst.

Relevante SHA-256-Werte dieses Zeitstands:

| Datei | SHA-256 |
|---|---|
| `data/i18n/en.json` | `c148e09f765ec4fd65178eda76230eda92f0d5c7ee322abbf8832cd91c1bee13` |
| `data/i18n/de.json` | `da67bf1f2991999fde0a4bd670c2546ab57fc931611e6f38e038a5a66178ddf8` |
| `data/i18n/fr.json` | `2b7dfbe4d2fe50ee8c8c0ea3dbc5e712aed4a73acb86b52c344faad8304a2c67` |
| `assets/css/village-theme.css` | `e32bb4dd56ce326f7d448baaca79f559d7c9513fdd8dc877215dbfee86b4a39d` |
| `assets/js/social-hub.js` | `312b9e073a43dd461cd3574af11b9db93a415ad661e9505a494f11b4bacd6b07` |
| `assets/js/alliance-community.js` | `b56cef80c26f632d73034063a3623d1fe39d0580d9ad29487976cd9438b2bcf7` |

Die früher gemessenen 1,59 MB Login-HTML und 50 CSS-/59 JavaScript-Dateien bleiben bewusst beim ursprünglichen Zeitstand. Der aktuelle Nachtrag rendert keine Antwort neu und behauptet keine neue Ladezeit oder abgeschlossene Produktabnahme.

## Nachtrag aus späteren gespeicherten Prüfläufen

Der [Sprachnachlauf um 13:23:54 UTC](../../artifacts/ui-ux-audit-2026-09-30/flows/localization-followup-latest.json) besteht mit **9.026 identischen Schlüsseln in EN, DE und FR**. Die fehlenden Schlüsselmengen sind leer; die Dateien änderten sich während der unveränderten PHP-Sprachprüfung nicht. Damit sind die oben dokumentierten 19 französischen Kataloglücken für diesen späteren Stand geschlossen. Der Bericht behält den älteren Snapshot als Verlauf bei. Schlüsselparität bestätigt weiterhin nicht die semantische Richtigkeit aller Texte.

Diese Sprachprüfung bleibt ein eigener Zeitstand: Die Sprachdateihashes in der anschließend erstellten finalen Fixture unterscheiden sich bereits wieder von den Hashes dieses Nachlaufs. Der 9.026-Schlüssel-Nachweis wird deshalb nicht ohne weiteren Beleg auf jede nachfolgende Sprachdateifassung übertragen.

Die [abschließende Haupt-App-Matrix](../../artifacts/ui-ux-audit-2026-09-30/app-matrix-final/report.json) verwendet eine um **13:25:04 UTC** per Hash erfasste, während des Laufs unveränderte Fixture-Kopie. Dort misst `#march-confirm` bei 320 × 568 **123,5 × 44 px** (`results[124]`) und bei 568 × 320 **162,4 × 44 px** (`results[200]`); beide Knöpfe sind aktiv, im Bildschirm und am Mittelpunkt erreichbar. Der ursprüngliche kleine Marschabschluss gilt in diesem geprüften Stand somit als behoben. Das ist eine übernommene Messung des koordinierenden App-Laufs, kein neuer Browserlauf dieser Teilprüfung und kein Nachweis des tatsächlichen Entsendens.

Dieser Schlusslauf enthält 265 Beobachtungen, darunter 255 erfasste Zustände, sowie 224 Screenshots. Er meldet keine Browser-JavaScriptfehler, aber **drei GET-Antworten mit 409 BUSY**; die Verteidigungsansicht bei 390 × 844 wurde nicht erfolgreich geladen. Eine pauschale fehlerfreie Abnahme aller Ansichten wäre daher falsch. Während des Laufs änderten sich im Workspace `village-theme.css`, `alliance-community.js`, `march-panel.js` und `mvp-panels.js`; diese späteren Fassungen sind von den unveränderten Fixture-Messungen nicht abgedeckt.

Auch die HTML-Größe hat einen neueren Messstand: Der finale Login weist **1.658.257 Transferbytes** aus, die Haupt-App **1.688.865 Transferbytes**. Die weiter oben stehende Aufteilung von 1,59 MB und 99,819 % Kataloganteil gehört zur früheren reinen Bytebilanz; der finale Anteil wurde hier nicht erneut berechnet. Aus den neuen Transfergrößen folgt keine Aussage über reale Mobilgeräte.

### Abschließender Sprachnachweis um 13:44:12 UTC

Der koordinierende Agent führte `tests/localization.php` um **13:44:12 UTC** nochmals unverändert aus: **Exit 0, je 9.050 Schlüssel in EN/DE/FR, keine fehlenden Schlüssel**. Die Dateien blieben während der Prüfung unverändert. Ihre SHA-256-Werte stimmen exakt mit den Sprachdateien der finalen App-Fixture überein: EN `b1cb463208386b9cc11876509198a80a0b9180e442eff2b10d45e63a9070d565`, DE `d1828379901a58042df0172f901dcd3ee698092192aff403601a68d4d7283be8`, FR `60b5366f075ce3fc44ce6b7b62d02f7c6149b6dae347a424ec38fea110193aa6`. Damit ist die Schlüsselparität dieses finalen Prüfsnapshots nachgewiesen. Die 9.026-Schlüssel-Messung bleibt als früherer Zeitstand erhalten; falsche Bedeutungen und gemischte sichtbare Texte bleiben unabhängig von Schlüsselparität zu bewerten. Belege: [Abschlussmessung](../../artifacts/ui-ux-audit-2026-09-30/closing-checks/localization.json), [unverändertes Prüfprotokoll](../../artifacts/ui-ux-audit-2026-09-30/closing-checks/localization.log). Für diesen Dokumentnachtrag wurde keine weitere Prüfung gestartet.
