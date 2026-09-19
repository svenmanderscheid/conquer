# Conquer – erster gespeicherter Ausbau in 3D

Stand: 9. September 2026. Lokale Umsetzung, nicht veröffentlicht.

Aktualisierung: Die nachfolgend beschriebene große Karte und das Ausbau-Fenster wurden inzwischen durch kompakte Anzeigen direkt an den Gebäuden ersetzt. Forschung und Training haben eigene Balken an Akademie und Kaserne. Details und Prüfgrenzen stehen in `Conquer-Gebaeudeanzeigen.md`.

## Ausprobieren

Im selben WLAN wie der PC: [Spieltest öffnen](http://192.168.178.96/conquer-3d-playtest/).
Auf dem PC alternativ: [lokaler Spieltest](http://localhost/conquer-3d-playtest/).

Ein eigenes Testkonto anlegen. Die Testkopie führt nach der Anmeldung direkt zur 3D-Stadt. Auf Handy und Computer denselben Spielernamen und dasselbe Passwort verwenden, um denselben Testspielstand zu öffnen. Festung antippen, Kosten und Voraussetzungen ansehen und „Auf Stufe 2 ausbauen“ betätigen. Der erste Ausbau dauert mit den vorhandenen Testwerten 42 Sekunden. Seite währenddessen neu laden; Bauzeit und Ressourcen bleiben erhalten. Nach Abschluss steigt die Festung auf Stufe 2, und das Gerüst verschwindet.

Die bisherige Vorschau auf Port 8766 bleibt eine reine Design-/Laststudie. Der neue Spieltest benötigt Apache und MySQL in XAMPP; der PC muss eingeschaltet bleiben. Die WLAN-Adresse kann sich ändern.

## Was umgesetzt ist

- Echte PHP-/MySQL-Anbindung der Cartoon-Festung unter `/city/3d`.
- Anmeldung, Stadtzuordnung, Ressourcen und Bauaufträge verwenden die vorhandenen Spielsysteme.
- Ausbau-Fenster mit Stufe, Kosten, Bauzeit, Machtzuwachs und Voraussetzungen.
- Serverseitige Prüfung und Ausgabe; ein erwarteter Ausgangslevel verhindert einen weiteren Kauf durch verspätete Wiederholungen.
- Gespeicherter Baufortschritt, Gerüst während des Ausbaus, aktualisierter Stufenhinweis nach Abschluss.
- Weitere Voraussetzungen können im Fenster angesehen werden, etwa die Stadtmauer für Festung Stufe 3.
- Verbindungsfehler sperren neue Ausgaben und bieten erneutes Verbinden an. Der dargestellte Countdown verwendet Serverzeit plus monotone verstrichene Zeit; die Fertigstellung erfolgt ausschließlich auf dem Server.
- „3D-Stadt öffnen“ ist in der bisherigen Spielübersicht verlinkt.

Dies ist der erste Gebäude-Durchstich. Die Mühle ist weiterhin ein Designmodell. Training, Monsterkämpfe und andere Spielfunktionen wurden nicht in die 3D-Ansicht integriert. Kosten, Bauzeiten und Machtwerte stammen aus dem vorhandenen MVP und sind keine neu bestätigte endgültige Balance. Das Gebäudemodell wird noch nicht pro Stufe neu gestaltet.

## Isolierte Testumgebung

Quellprojekt: `C:\xampp\htdocs\conquer`.
Testkopie: `C:\xampp\htdocs\conquer-3d-playtest`.
Testdatenbank: `conquer_3d_playtest_20260909`.

Vor dem Test wurde ein lokaler Datenbankdump erstellt und in eine neue Datenbank eingespielt. Die Zeilenzahlen aller 55 Tabellen wurden mit der Quelle verglichen. Übernommene Sitzungstokens wurden ausschließlich in der Testkopie entfernt; eigene Test-Cookienamen verhindern Verwechslungen mit Sitzungen des bestehenden Spiels. Es wurden getrennte Testkonten angelegt. Die ursprünglichen Spielstände und Datenbankkonfigurationen wurden nicht für die Tests verändert.

Das wiederhergestellte Schema enthält die bestehenden Fremdschlüssel aus dem Dump. Backup, private Konfigurationskopien und das einmalige Einrichtungsprogramm liegen im Arbeitsordner außerhalb des Webverzeichnisses; sie gehören nicht in GitHub. Ein automatischer Reset wurde bewusst nicht eingerichtet.

## Prüfung

- PHP-/JavaScript-Syntaxprüfungen bestanden; keine Whitespace-Fehler im Git-Diff.
- Vorhandene MVP-Regeltests bestanden.
- Neuer HTTP-Test `tests/city3d_smoke.php`: Anmeldung, zwei unabhängige Sitzungen derselben Stadt, korrekte Ressourcenabbuchung, echte Bauzeit, gespeicherter Auftrag und Fertigstellung ohne zwischenzeitliche Browseranfragen bestanden.
- Fehlende CSRF-Tokens, ungültige Level, fehlende Voraussetzungen und verspätete Wiederholungen werden abgewiesen. Vom Client mitgeschickte Kosten, Bauzeit oder Stadt-ID bestimmen nicht die Aktion.
- Zwei gleichzeitig gesendete Ausbauanfragen erzeugten genau einen erfolgreichen Start und eine Ablehnung; der gespeicherte Auftrag wurde nur einmal angelegt.
- Abmeldung und erneute Anmeldung erhalten Stufe 2. Ein anderes Konto sieht seine eigene Stadt und kann fremde CSRF-Tokens nicht verwenden.
- Konfiguration, PHP-Handler und Tests sind per HTTP gesperrt.
- Browserprüfung: Öffnen ohne Ausgabe, bewusster Start, bezahlte Kosten, Reload mit Restzeit/Gerüst, Stufe 2 nach Abschluss und fehlende Voraussetzungen für Stufe 3. 390 × 844 Hochformat und direkte Auswahl des 3D-Modells geprüft; keine erfassten Browserwarnungen/-fehler beim Laden.

Noch offen: erneuter echter Handytest dieses gespeicherten Ablaufs. Der frühere S23-Ultra-Test betraf die reine Grafikvorschau. Eine große belastbare Mehrspieler-Stadt oder ein vollständiger AP02-/Shrine-Test ist damit noch nicht abgenommen.

## Dateien und Git

Neue Schnittstelle: `src/Api/Handlers/City3dHandler.php`; Ansicht: `views/city3d.php`; Grafik und Bedienung: `assets/city3d/`; Test: `tests/city3d_smoke.php`. `index.php` erhielt drei Routen-/Ansichts-Ergänzungen; `views/game.php` einen Link zur 3D-Stadt. Die vorhandenen lokalen Änderungen im Repository wurden beibehalten.

Die Umsetzung liegt im vorhandenen Git-Arbeitsverzeichnis. Es wurde kein Commit und kein Push ausgeführt; dadurch wurde auch kein Hostinger-Autodeployment ausgelöst.
