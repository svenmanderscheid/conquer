# Conquer: Arbeitsplan für Android und iOS

Stand: 1. Oktober 2026. Ergänzt `MOBILE_APP_STRATEGY.md`; die erste Bestandsaufnahme unten stammt vom 24. September 2026.

## Ziel und Testgeräte

Eine gemeinsame Oberfläche für Browser, Android und iOS. PHP, Datenbank und verbindliche Spielregeln bleiben auf dem Server. Auf ausdrücklichen Nutzerwunsch beginnt jetzt ein begrenzter Android-Prototyp mit Capacitor; die endgültige App-Veröffentlichung setzt weiterhin stabile Kernabläufe voraus.

Vom Nutzer bestätigte Geräte: Samsung Galaxy S23 Ultra und iPhone 13. Das S23 Ultra verwendet Android 16 / API 36; die iOS-Version und Zugang zu einem Mac sind noch offen. Das S23 Ultra ersetzt keinen Leistungstest auf einem schwächeren Android-Gerät. Browser und installierte PWA dienen weiterhin als mobiler Testweg; dieselben Abläufe müssen zusätzlich in der tatsächlich installierten App geprüft werden.

## Android-Start am 1. Oktober 2026

Unter `mobile/` beginnt ein Android-Projekt mit Capacitor 8.5.2 und gemeinsamer JavaScript-Oberfläche für die spätere iOS-App. Der Prototyp lädt die vorhandene HTTPS-Spielseite; PHP-Anmeldung, Cookies und APIs bleiben auf demselben Ursprung. Das lokale Webpaket ist eine Verbindungs-/Fehlerseite mit öffentlichen Assets. Release-Builds sind gesperrt; es gibt noch keine Freigabe für Google Play.

**Nächster messbarer Meilenstein:** Auf dem S23 Ultra Anmeldung → Stadt/Welt → Gebäudeausbau → App-Wechsel → korrekten Spielstand ohne doppelte Aktion dokumentieren. Android Studio, Java 21 und Android SDK wurden am 1. Oktober installiert. `assembleDebug` und die APK-Signaturprüfung bestanden; die Datei liegt unter `artifacts/android/Union-of-Kingdoms-0.1.0-prototype.apk`. Installation und erster Start auf dem S23 Ultra mit Android 16 waren erfolgreich, der Prozess blieb aktiv. Eine Startmeldung zu Bildschirmrändern ist dokumentiert; die vollständige Geräteprüfung steht weiterhin aus. Vorbereitung und Befehle: [mobile/README.md](../mobile/README.md).

Die erste Prüfung im Hochformat bestätigte nach Anmeldung durch den Nutzer Stadt/Welt, Inventar → Android Zurück → Stadt und die erhaltene Anmeldung nach kurzem App-Wechsel. Beim Weltkartenknopf reagierte erst das Tippen auf die Beschriftung; seine gesamte Touchfläche bleibt zu prüfen. Es wurden keine Spielaktionen ausgeführt. Die ursprüngliche Bildschirm-Wachhaltung wurde wiederhergestellt. Weitere Zurück-Abläufe, Querformat, Tastatur, Verbindungsverlust, Prozessende, PWA-Installationshinweise/Service-Worker und externe Links sind noch zu prüfen. Details und Aufnahmen sind in `mobile/README.md` verzeichnet. Das offizielle `@capacitor/app`-Modul ist mit seinem Standardhandler für Androids WebView-Verlauf eingebunden; eine eigene Anbindung seiner Lebenszyklusereignisse an das Spiel steht noch aus. Für die spätere Store-Fassung bleiben ein mitgeliefertes statisches Frontend und die getrennte App-Anmeldung/API-Anbindung erforderlich. Die Befunde der früheren Browserprüfung wurden durch das Anlegen des Android-Projekts nicht erneut geprüft oder erledigt.

## Erste Bestandsaufnahme

Am 24. September lokal auf einer isolierten Vorschau mit synthetischem Konto und eigener temporärer Datenbank geprüft. Keine Tests am echten Spielkonto.

- `tests/fantasy_theme_app.cjs`: 22 Spielfenster in 1280 × 800, 390 × 844, 320 × 568, 844 × 390 und 568 × 320 CSS-Pixeln; alle 110 Kombinationen wurden erreicht.
- In diesen 110 Kombinationen keine vom Test gemeldeten seitlichen Fensterüberläufe, außerhalb des Bildschirms liegenden Fenster oder unerreichbaren Schließen-Knöpfe.
- Anmeldung und Wiederherstellung wurden zusätzlich in drei Handyformaten auf erreichbare Hauptaktionen geprüft.
- Bis zum Testabbruch keine erfassten JavaScript-Seitenfehler, fehlenden Assets oder fehlerhaften API-Antworten.
- `tests/app_polling.cjs`: bestanden. Prüft das Aktualisierungsmodul bei unsichtbarer Seite, Verbindungsverlust, Wiederaufnahme, gleichzeitigen Anfragen und Fehlern. Dies ist keine vollständige Prüfung nativer App-Wechsel.
- Stichproben der erzeugten Aufnahmen: Stadt bei 320 × 568, Ausbildung und Profil bei 390 × 844, Forschung bei 844 × 390.

**Der Gesamttest ist nicht bestanden.** Er brach nach den 110 Menüprüfungen bei einer Farbprüfung des Backoffice ab. Erwartet wurde das frühere Violett `#5c4270`, vorhanden war `#805f9c`. Weitere Farbabweichungen wurden bereits gesammelt. Außerdem meldete die automatische Kontrastprüfung 217 Fundstellen über die fünf Formate, einschließlich Wiederholungen derselben Elemente. Das sind keine 217 bestätigten, unterschiedlichen UI-Fehler: insbesondere Texte auf Farbverläufen müssen mit der Messmethode und den Bildern abgeglichen werden.

Die Testzugriffe auf die Anmeldung wurden an das aktuelle Formular mit `identifier` angepasst. Abweichende Installationsfarben werden nun gesammelt, damit sie die Layoutprüfung nicht vorzeitig beenden. Auch bei Abbruch bleiben die bis dahin gesammelten Darstellungsfehler im Bericht erhalten. Die Erwartungen wurden nicht gelockert.

Messdaten: `artifacts/fantasy-theme/report.json`. Aufnahmen liegen im selben Ordner. Diese Dateien werden beim nächsten Lauf überschrieben.

## Priorisierte Arbeitspakete

| Reihenfolge | Aufgabe | Fertig, wenn … |
| --- | --- | --- |
| Jetzt | Begrenzten Android-Durchstich durchführen | Debug-Build auf dem S23 Ultra installiert; der oben beschriebene Kernablauf ist mit OS-Version und Build dokumentiert. |
| 1 | Verlässliche mobile Abnahme herstellen | Aktuelle Gestaltung, Stilreferenz und Farberwartungen sind abgeglichen; Kontrastmessung berücksichtigt ihre Grenzen; bestätigte Probleme sind behoben und erneut geprüft. |
| 2 | Bedienbarkeit aller Kernabläufe prüfen | Nicht nur Fensterrahmen, sondern Reiter, Hauptaktionen, Unterdialoge, Scrollbereiche, Touchflächen und Zurück-Verhalten funktionieren in kleinen Hoch- und Querformaten. |
| 3 | Auf echten Geräten abnehmen | Die unten stehenden Abläufe funktionieren auf S23 Ultra und iPhone 13 mit dokumentierter OS-Version und Bildschirmaufnahmen. |
| 4 | App-Einstieg und Anmeldung vorbereiten | Statische Weboberfläche, Serverkonfiguration, API-Verbindung, Sitzungen, CSRF und externe Links sind für einen anderen App-Ursprung ausgearbeitet und getestet. |
| 5 | Capacitor-Prototyp erstellen | Android- und iOS-Testbuild schaffen Anmeldung → Stadt/Welt → Gebäudeausbau → App-Wechsel → korrekten Spielstand ohne doppelte Aktion. |
| 6 | Veröffentlichung vorbereiten | Gerätematrix und Regressionstests bestanden; Signierung, Store-Einträge und benötigte Gerätefunktionen fertig. |

### Konkrete Prüfpunkte aus der Bestandsaufnahme

- Ausbildung: Truppenstufen und Aktionsknöpfe wirken in der 390-px-Aufnahme gedrängt. Überdeckung und tatsächliche Antippbarkeit gezielt messen, bevor das Layout geändert wird.
- Kontraste: gemeldete Profilaktionen, Ausbildungswerte und weitere Hauptaktionen anhand ihrer tatsächlich sichtbaren Hintergründe nachprüfen.
- Farbvertrag: Arbeitsstand von `assets/css/village-theme.css`, `docs/UI_STYLE_GUIDE.md` und Test enthält unterschiedliche Farberwartungen. Nicht allein zum Bestehen des Tests die Oberfläche umfärben.
- App-Anbindung: `assets/js/game.js` verwendet aktuell `credentials: 'same-origin'`. Für eine lokal eingebettete Oberfläche mit entferntem Server reicht es nicht, lediglich eine neue Server-URL einzutragen. Anmeldung, Cookies/Token, CORS und CSRF müssen zusammen geplant werden.
- PHP-Einstieg: `views/game.php` erzeugt HTML, Sprach-/Weltinitialisierung, Layoutdaten und Assetversionen serverseitig. Für ein mitgeliefertes Webpaket braucht es einen geeigneten Build-/Startvorgang und eine klar getrennte Initialisierung vom Server.

## Abnahme auf S23 Ultra und iPhone 13

Für beide Geräte OS-Version, Browser beziehungsweise App-Build, Ausrichtung und Ergebnis festhalten. Browserprüfung und installierte App getrennt protokollieren.

1. Anmelden, Passwortwiederherstellung öffnen, Bildschirmtastatur ein-/ausblenden; Eingaben und Absenden bleiben erreichbar.
2. Stadt und Weltkarte öffnen, verschieben und zoomen; Navigation, Chat und HUD dürfen sich nicht unbedienbar überlagern.
3. Profil, Aufgaben, Ausbildung/Hospital, Forschung, Inventar, Schatzkammer, Shop und Allianz öffnen; Inhalte bis zum Ende scrollen und Hauptaktionen erreichen.
4. Gebäudeausbau und eine Truppenausbildung mit einem Testkonto durchführen; auch nach erneutem Öffnen darf die Aktion nur einmal ausgeführt sein.
5. Chat mit geöffneter Tastatur bedienen und Gerät drehen. Eingabefeld, Senden und Schließen bleiben sichtbar.
6. Dialog öffnen, Zurück-Verhalten prüfen; auf Android ausdrücklich den System-Zurück-Vorgang der App testen.
7. App/Browser wechseln, Bildschirm sperren und zurückkehren. Spielstand und Timer werden korrekt aktualisiert.
8. Verbindung kurz trennen und wiederherstellen. Fehler sind verständlich, offene Aktionen bleiben nachvollziehbar, Wiederholen erzeugt keine Doppelaktion.
9. Gezeichnete Stadt und Welt länger benutzen; Erwärmung, Ruckeln, Ladezeit und Stabilität in Gesamtansicht und bei Gebäudeaktionen beobachten. Die entfernte 3D-/2,5D-Szene gehört nicht mehr zum Testumfang.

## Grenzen dieser ersten Prüfung

In der Bestandsaufnahme vom 24. September gab es keine echte Handyprüfung, keine iOS-WebView-Prüfung, keine simulierte Bildschirmtastatur oder Kameraaussparung, keine umfassende Prüfung aller Unterdialoge und Spielzustände und keine gemessene Leistung der gezeichneten Ansichten auf Mobilgeräten. Die 110 Kombinationen belegen die geprüfte Fenstergeometrie, nicht eine vollständige mobile Freigabe. Damals wurden keine nativen App-Projekte oder Store-Pakete erstellt; der Android-Start vom 1. Oktober ersetzt diese ausstehenden Prüfungen nicht.
