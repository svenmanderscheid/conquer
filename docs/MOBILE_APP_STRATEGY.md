# Mobile-App-Strategie für Conquer

## Ziel

Union of Kingdoms soll aus einer gemeinsamen Codebasis als Web-App, iOS-App und Android-App angeboten werden. Das vorhandene Frontend aus HTML, CSS und JavaScript wird dafür weiterverwendet und mit Capacitor in native App-Projekte eingebettet. Das PHP-/MySQL-System bleibt das zentrale Online-Backend für Anmeldung, Spielregeln und gespeicherte Spielstände.

## Zeitpunkt

**Ergänzung vom 1. Oktober 2026:** Der Nutzer hat ausdrücklich darum gebeten, jetzt mit der Google-Play-App anzufangen. Deshalb beginnt unter `mobile/` ein begrenzter Android-Prototyp mit Capacitor 8.5.2. Ein iOS-Projekt, endgültige Gerätefunktionen, Store-Metadaten, Signierung und Store-Einreichung bleiben der späteren stabilen App-Phase vorbehalten.

Der erste Android-Prototyp lädt die bestehende HTTPS-Spielseite und erhält dadurch die bisherige PHP-Anmeldung mit Sitzungscookie und API-Aufrufen auf demselben Ursprung. Das lokale Paket enthält nur eine Verbindungs-/Fehlerseite mit öffentlichen Assets. Capacitors `server.url` dient hier ausschließlich dem Prototyp; vor Veröffentlichung sind ein mitgeliefertes statisches Frontend sowie App-Start, Anmeldung und Server-API getrennt auszuarbeiten. Release-Builds bleiben gesperrt. Einrichtung und Grenzen stehen in [mobile/README.md](../mobile/README.md).

Am 1. Oktober wurden Android Studio, Java 21 und das Android SDK installiert. Der erste Debug-APK-Build mit Gradle 8.14.3 und die Signaturprüfung waren erfolgreich; die Testdatei liegt unter `artifacts/android/Union-of-Kingdoms-0.1.0-prototype.apk`. Installation und erster Start auf dem S23 Ultra mit Android 16 sind erfolgt. Die vollständige Geräteprüfung steht weiter aus; eine Startmeldung der SystemBars-Erweiterung zu Bildschirmrändern ist in `mobile/README.md` dokumentiert. Browser und PWA bleiben parallel ein mobiler Testweg. Die Android-Hülle gilt erst nach dem dokumentierten Gerätedurchstich als geprüft.

## Anforderungen während der laufenden Entwicklung

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
