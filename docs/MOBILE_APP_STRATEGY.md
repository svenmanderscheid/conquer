# Mobile-App-Strategie für Conquer

## Ziel

Union of Kingdoms soll aus einer gemeinsamen Codebasis als Web-App, iOS-App und Android-App angeboten werden. Das vorhandene Frontend aus HTML, CSS und JavaScript wird dafür weiterverwendet und mit Capacitor in native App-Projekte eingebettet. Das PHP-/MySQL-System bleibt das zentrale Online-Backend für Anmeldung, Spielregeln und gespeicherte Spielstände.

## Zeitpunkt

**Ergänzung vom 1. Oktober 2026:** Der Nutzer hat ausdrücklich darum gebeten, jetzt mit der Google-Play-App anzufangen. Deshalb beginnt unter `mobile/` ein begrenzter Android-Prototyp mit Capacitor 8.5.2. Ein iOS-Projekt, endgültige Gerätefunktionen, Store-Metadaten, Signierung und Store-Einreichung bleiben der späteren stabilen App-Phase vorbehalten.

Der erste Android-Prototyp lädt die bestehende HTTPS-Spielseite und erhält dadurch die bisherige PHP-Anmeldung mit Sitzungscookie und API-Aufrufen auf demselben Ursprung. Das lokale Paket enthält nur eine Verbindungs-/Fehlerseite mit öffentlichen Assets. Capacitors `server.url` dient hier ausschließlich dem Prototyp; vor Veröffentlichung sind ein mitgeliefertes statisches Frontend sowie App-Start, Anmeldung und Server-API getrennt auszuarbeiten. Release-Builds bleiben gesperrt. Einrichtung und Grenzen stehen in [mobile/README.md](../mobile/README.md).

Am 1. Oktober wurden Android Studio, Java 21 und das Android SDK installiert. Der erste Debug-APK-Build mit Gradle 8.14.3 und die Signaturprüfung waren erfolgreich; die Testdatei liegt unter `artifacts/android/Union-of-Kingdoms-0.1.0-prototype.apk`. Installation und erster Start auf dem S23 Ultra mit Android 16 sind erfolgt. Die vollständige Geräteprüfung steht weiter aus; eine Startmeldung der SystemBars-Erweiterung zu Bildschirmrändern ist in `mobile/README.md` dokumentiert. Browser und PWA bleiben parallel ein mobiler Testweg. Die Android-Hülle gilt erst nach dem dokumentierten Gerätedurchstich als geprüft.

## Anforderungen während der laufenden Entwicklung

### Lokales Komfortpaket vom 9. Oktober 2026

`assets/js/app-polling.js` ergänzt die vorhandenen Browserereignisse um `appStateChange` des bereits eingebundenen Capacitor-App-Moduls. Ein nativer Hintergrundzustand pausiert Spielstandsabrufe auch bei weiterhin sichtbarer WebView; die Rückkehr liest den Zustand sofort neu. Gleichzeitige Rückkehrereignisse erzeugen keine parallelen Spielstandsabrufe. Bei fehlender nativer Schnittstelle bleiben die Browserereignisse wirksam; nur der eigene native Listener wird beim Stoppen entfernt. Der gemeinsame Sekundentimer pausiert ebenfalls. Die Rückkehrübersicht sichert beim nativen Pausieren ihren bisherigen Besuchszeitpunkt.

Der bestehende Verbindungshinweis unterscheidet Offlinezustand, erneuten Abruf, Serverfehler und unvollständig geladene Teilbereiche. „Refresh game state“ im Spielmenü liest den Zustand ausdrücklich neu; diese Aktion führt keine ausstehenden Spielaufträge erneut aus. Die vorhandenen Auftragsbelege und ihre ausdrückliche Wiederholung bleiben der Weg für eine fehlende Aktionsantwort. Aktualisierungen erhalten fokussierte Eingabefelder auch in `#panel-content`.

Die letzte Beraterszene bietet den aktuellen nächsten Einsteigerschritt als ausdrücklichen Einstieg in die bestehende Aktionsansicht an; siehe [BEGINNER_GUIDE.md](BEGINNER_GUIDE.md). Neue Texte sind im gemeinsamen Sprachsystem vollständig in EN/DE/FR vorhanden. Das Paket benötigt kein neues Plugin, keine Servermigration und keinen App-Build. Am 9. Oktober hat der Nutzer Commit und Push für die Handyprüfung beauftragt; die vorhandene App lädt die veröffentlichten Web-Dateien vom Spielserver.

Prüfungen: `tests/app_polling.cjs` (einschließlich nativer Hintergrundzustände, konkurrierender Rückkehrereignisse und Listener-Cleanup), `tests/kingdom_entry.cjs`, `tests/beginner_journey_selection.cjs` und `tests/mobile_comfort_app.cjs`. Die Haupt-App-Prüfung verwendet eine Wegwerf-Datenbank, synthetische gelesene Spielstände und einen simulierten Capacitor-Listener. Sie prüft fünf Bildschirmformate, echte Einsteiger-Navigation, erhaltene Eingaben bei geändertem Serverzustand, Offline-/Serverausfall und manuelle Wiederverbindung ohne schreibende API-Anfragen. Aufnahmen und Bericht: `output/playwright/mobile-comfort-20261009/`. Die bestehende Rückkehr-/Auftragskomfortprüfung `tests/game_comfort_app.cjs` bestand ebenfalls in fünf Bildschirmformaten. Eine echte Android-/iOS-Geräteabnahme wird durch diese Browserprüfungen nicht bestätigt.

Der allgemeine Prüflauf `tests/localization.php` scheitert bereits im unveränderten Arbeitsstand an Katalogparität: FR fehlen gegenüber DE 240 vorhandene Talent-Schlüssel; EN enthält 45 zusätzliche vorhandene Rechtstext-Schlüssel. Die zehn neuen Schlüssel wurden separat auf vollständige EN/DE/FR-Texte, identische Platzhalter und unveränderte bestehende Übersetzungen geprüft. Diese bestehenden Unterschiede wurden nicht durch Kopieren deutscher Texte in andere Sprachen kaschiert.

Die abschließende Haupt-App-Prüfung bestand 15 Darstellungsfälle: Berater, Spielmenü und Verbindungshinweis jeweils bei 1280 × 800, 390 × 844, 320 × 568, 844 × 390 und 568 × 320. Der Verbindungshinweis hält die tatsächlich sichtbaren HUD-Aktionen frei, einschließlich der zweispaltigen Werkzeuge auf schmalen Handys. Die Aufnahmen wurden visuell geprüft; es gab keine JavaScript-Seitenfehler oder schreibenden Spielanfragen im neuen Prüflauf. Die bestehende `tests/beginner_guide_app.cjs` bestand anschließend ihre fünf Formate einschließlich aller Gebäude, Kapitel, Wiederaufnahme und Zurück-Navigation.

### Oberfläche und Eingabe

- Vollständige Touch-Bedienung ohne Abhängigkeit von Hover, Rechtsklick oder Tastatur.
- Ausreichend große Schaltflächen und lesbare Texte auf kleinen Bildschirmen.
- Unterstützung von Hoch- und Querformat sowie sicheren Bildschirmrändern für Kameraaussparungen und Systemleisten.
- Keine doppelte Navigation oder doppeltes HUD in eingebetteten Ansichten.
- Eingabefelder dürfen beim Öffnen der Bildschirmtastatur nicht unerreichbar werden.

### Navigation und Lebenszyklus

- Das Zurück-Verhalten muss Dialoge und Ansichten in nachvollziehbarer Reihenfolge schließen.
- Pausieren, App-Wechsel, Verbindungsverlust und erneutes Öffnen müssen sicher behandelt werden.
- Wiederholte oder verspätete Anfragen dürfen Käufe, Ausbauten oder andere Aktionen nicht doppelt ausführen.
- Externe Links, Downloads und neue Fenster müssen auch in einer App-WebView einen definierten Ablauf haben.

### Backend und Sicherheit

- PHP und MySQL laufen ausschließlich auf dem Server; die installierte App enthält keine geheimen Zugangsdaten oder vertrauenswürdige Spiellogik.
- Schreibende Aktionen werden serverseitig authentifiziert, validiert und gegen Wiederholung geschützt.
- API-Antworten sollen klar strukturiert sein und keine vollständige HTML-Seite voraussetzen, wenn dieselbe Funktion später von der App direkt benötigt wird.
- Sitzung, Cookies, CSRF-Schutz und CORS werden vor dem App-Prototyp gezielt geprüft. Fest codierte Ursprünge und absolute Pfade sind zu vermeiden, sofern sie nicht zentral konfiguriert werden.

Vorgangskennungen müssen auch beim lokalen Handytest über eine HTTP-LAN-Adresse funktionieren. `assets/js/browser-compat.js` wird vor den Spielmodulen geladen und ergänzt fehlendes `crypto.randomUUID()` durch UUID v4 aus `crypto.getRandomValues()`. Eine vorhandene native Implementierung bleibt unverändert. `tests/training_http_app.cjs` prüft Ausbildung, Beschleuniger, Heilung und die Wiederholung nach einer verlorenen Antwort auf einem tatsächlich unsicheren HTTP-Test-Origin; reine Localhost-Tests decken diesen Fall nicht ab.

### Leistung

- Gezeichnete Spielansichten, Bilder und Animationen werden regelmäßig in typischen Handyformaten geprüft.
- Bildauflösung, Effekte und Animationsdichte müssen bei Bedarf abhängig von der Geräteleistung reduziert werden können.
- Große Assets werden komprimiert, nur bei Bedarf geladen und nach Möglichkeit wiederverwendet .
- Lange Aufgaben dürfen die Bedienung nicht blockieren; Speicherverbrauch und Wiederaufnahme nach App-Wechsel sind bei größeren Funktionen mitzudenken.

## Späte App-Phase

Der jetzt begonnene Android-Prototyp prüft zuerst Anmeldung, gezeichnete Stadt, Weltkarte, Sitzungswiederaufnahme und einen kompletten schreibenden Spielablauf auf einem echten Gerät. Eine erste Hochformatprüfung auf dem S23 Ultra bestätigte das angemeldete Spiel, Stadt/Welt, Inventar → Android Zurück → Stadt sowie den Erhalt der Anmeldung nach App-Wechsel. Weitere Zurück-Abläufe, Prozessende, Querformat, Tastatur, Verbindungsverlust, PWA-Verhalten und externe Links sind noch offen; schreibende Spielaktionen wurden nicht geprüft. Das Geräteprotokoll steht in `mobile/README.md`. Das iOS-Projekt und die endgültige App-/API-Trennung folgen auf Grundlage dieser Ergebnisse, sobald die Kernabläufe stabil genug sind. Erst nach dem jeweiligen Gerätedurchstich folgen Push-Nachrichten und weitere native Funktionen.

Swift für iOS und Kotlin für Android werden nur für begrenzte native Anpassungen verwendet. Die gemeinsame Spiellogik und Oberfläche bleiben in JavaScript, damit Fehlerbehebungen und neue Funktionen nicht dreimal umgesetzt werden müssen.
