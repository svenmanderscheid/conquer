# Mobile-App-Strategie für Conquer

## Ziel

Conquer soll später aus einer gemeinsamen Codebasis als Web-App, iOS-App und Android-App angeboten werden. Das vorhandene Frontend aus HTML, CSS, JavaScript und Three.js wird dafür weiterverwendet und voraussichtlich mit Capacitor in native App-Projekte eingebettet. Das PHP-/MySQL-System bleibt das zentrale Online-Backend für Anmeldung, Spielregeln und gespeicherte Spielstände.

## Zeitpunkt

Die eigentlichen Capacitor-, Xcode- und Android-Studio-Projekte werden erst angelegt, wenn die wichtigsten Spielabläufe, die Navigation, die Benutzeroberfläche und die Server-Schnittstellen weitgehend stabil sind. Auch App-Symbole, Startbildschirm, Push-Nachrichten, Store-Metadaten, Signierung und Store-Einreichung gehören in diese späte Phase.

Bis dahin bleibt die bestehende PWA der direkte mobile Testweg. Jede neue Funktion wird so gebaut, dass die spätere Verpackung keine grundlegende Neuentwicklung verlangt.

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

- 3D-Szenen, Texturen und Animationen werden regelmäßig in typischen Handyformaten geprüft.
- Auflösung, Schatten, Partikeleffekte und Animationsdichte müssen bei Bedarf abhängig von der Geräteleistung reduziert werden können.
- Große Assets werden komprimiert, nur bei Bedarf geladen und nach Möglichkeit wiederverwendet oder instanziert.
- Lange Aufgaben dürfen die Bedienung nicht blockieren; Speicherverbrauch und Wiederaufnahme nach App-Wechsel sind bei größeren Funktionen mitzudenken.

## Späte App-Phase

Wenn das Spiel stabil genug ist, folgt ein kleiner technischer Prototyp mit Capacitor für beide Plattformen. Dabei werden zuerst Anmeldung, Stadt, Weltkarte, 3D-Ansicht, Sitzungswiederaufnahme und ein kompletter schreibender Spielablauf auf echten Geräten geprüft. Erst nach diesem Durchstich folgen Push-Nachrichten und weitere native Funktionen.

Swift für iOS und Kotlin für Android werden nur für begrenzte native Anpassungen verwendet. Die gemeinsame Spiellogik und Oberfläche bleiben in JavaScript, damit Fehlerbehebungen und neue Funktionen nicht dreimal umgesetzt werden müssen.
