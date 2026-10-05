# Union of Kingdoms – Android-Prototyp

Stand: 1. Oktober 2026. Auf ausdrücklichen Nutzerwunsch beginnt die Android-App jetzt mit einem kleinen Capacitor-Prototyp. Ziel des ersten Gerätebuilds ist Anmeldung → Stadt/Welt → Gebäudeausbau → App-Wechsel → korrekter Spielstand. Die gemeinsame Weboberfläche bleibt auch die Grundlage für eine spätere iOS-App.

**Status:** Die Android-Werkzeuge sind installiert. Am 1. Oktober 2026 wurde `assembleDebug` mit Gradle 8.14.3 und Java 21 erfolgreich ausgeführt; die APK-Signatur wurde geprüft. Die Testdatei liegt unter `artifacts/android/Union-of-Kingdoms-0.1.0-prototype.apk` (4.597.367 Bytes), Paket-ID `com.unionofkingdoms.app.prototype`, Version `0.1.0-prototype`, Mindest-API 24 und Ziel-API 36. SHA-256: `18e85af65f8954e59c58131ae01e8ec2374e8c008b206f6dafe31907a91df63d`. Anschließend wurde die APK per USB erfolgreich auf dem Samsung Galaxy S23 Ultra (SM-S918B, Android 16 / API 36) installiert und kalt gestartet; der App-Prozess blieb aktiv. Nach der Anmeldung durch den Nutzer bestanden erste Navigationsprüfungen im Hochformat: Stadt/Welt, Inventar mit Android Zurück und Wiederaufnahme nach App-Wechsel. Die vollständige native Geräteprüfung sowie die Freigabe für Google Play stehen noch aus.

Beim ersten Start meldete Capacitor 8.5.2 einmal `Error injecting safe area CSS: TypeError: Cannot read properties of null (reading 'style')` aus der eingebauten SystemBars-Erweiterung. Die App lief weiter. Das ist keine fehlerfreie Geräteabnahme: Bildschirmränder und Tastatur müssen im sichtbaren Spiel geprüft werden. Es wurden keine Kontodaten eingegeben oder Spielaktionen ausgeführt.

## Architektur und Grenzen

### Aktualisierter Android-Build (4. Oktober 2026)

Der aktuelle Projektstand wurde mit synchronisierter Fehlerseite und den aktuellen Android-Ressourcen einschließlich des freigegebenen Launcher-Icons als `0.1.2-prototype` (Versionscode 3) gebaut. Datei: `artifacts/android/Union-of-Kingdoms-0.1.2-prototype.apk`. `assembleDebug --offline` und die APK-Signaturprüfung bestanden. SHA-256: `d06c44f71c5d70e7fdb461435af8a085cca2abc1af071de95370c2b2015884d8`.

Beim Build war kein USB-Gerät verbunden; Installation und Geräteprüfung dieses Builds stehen aus. Die APK lädt weiterhin den HTTPS-Spielserver. Lokale Änderungen am eigentlichen Spiel benötigen zusätzlich ein Serverdeployment.

### Freigegebenes App-Icon (4. Oktober 2026)

Das freigegebene Motiv zeigt Magdar beim Angriff auf den Guardian, Feuerbogenschützin und Schattenreiter vor dem Congress, mit violetter Boss-Aura. Die Quelle liegt unter `assets/art/app-icon/union-of-kingdoms-boss-attack-v2-aura.png`. `tools/build-brand-icons.cjs` exportiert daraus Web-/PWA-, Apple-Touch- und Android-Launcher-Symbole. Die maskierbare Fassung setzt das vollständige Motiv mit Sicherheitsrand auf Violett. Android verwendet `drawable-nodpi/uok_launcher.png`; auf bereits installierten Geräten erscheint die Änderung erst nach einem neuen APK-Build und einer Aktualisierung der App.

### Ausblendbare Android-Navigation (3. Oktober 2026)

Der Debug-Build `0.1.1-prototype` (Versionscode 2) blendet die untere Android-Systemnavigation mit `WindowInsetsControllerCompat` aus. Ein Randwisch zeigt die Leiste vorübergehend als Overlay; Android Zurück bleibt unverändert verfügbar. Die Statusleiste und Capacitors bestehende Behandlung der sicheren Bildschirmränder bleiben erhalten. Start, Wiederaufnahme, Fensterfokus und Ausrichtungswechsel stellen den Modus wieder her. Während sichtbarer Bildschirmtastatur wird die Navigation nicht erneut verborgen; nach dem Schließen der Tastatur wird sie wieder ausgeblendet. Der Tastaturbeobachter ersetzt nicht Capacitors Insets-Listener.

`assembleDebug --offline` und die APK-Signaturprüfung bestanden. Die aktualisierte Testdatei liegt unter `artifacts/android/Union-of-Kingdoms-0.1.1-prototype.apk`; SHA-256: `513f5a01ffc1306720aabab44d3f4dab2bada30bc70ec88db00d491cc1153efc`.

Die Geräteprüfung steht aus: Beim Bau war kein USB-Gerät verbunden. Auf dem S23 Ultra Hoch-/Querformat, Stadt/Welt, Randwisch → Zurück/Home, App-Wechsel und Texteingabe → Tastatur schließen prüfen. Dabei insbesondere kontrollieren, dass die untere Spielnavigation den frei gewordenen Platz nutzt und Kameraaussparungen sowie Eingabefelder frei bleiben.

Capacitor 8.5.2 lädt für diesen Prototyp die vorhandene HTTPS-Spielseite, standardmäßig `https://play.unionofkingdoms.com/`. PHP-Anmeldung, Sitzungscookie, CSRF-Schutz und API-Aufrufe bleiben damit auf demselben Ursprung. PHP, Datenbank, Geheimnisse und verbindliche Spielregeln bleiben auf dem Server.

Das lokale Webpaket enthält eine eigenständige Verbindungs-/Fehlerseite: Schrift, gemeinsame Stile, Sprachsystem und Symbol sind vollständig in die HTML-Datei eingebettet; daneben liegen zwei Schriftlizenzen. Das ist erforderlich, weil Capacitor bei diesem entfernten Spielserver nur die genaue Fehlerseiten-URL lokal ausliefert. Eine mit Hashwerten begrenzte Inhaltsrichtlinie erlaubt die eingebetteten Stile und Skripte; die Fehlerseite registriert keinen Service-Worker und lädt keine Ressourcen nach. Sie enthält weder eine vollständige Kopie des Spiels noch einen Offline-Spielstand. Die Inhalte der entfernten Spielseite ändern sich mit deren Serverdeployment.

`server.url` ist laut [Capacitor-Konfigurationsdokumentation](https://capacitorjs.com/docs/config) für Entwicklung vorgesehen und keine Produktionsarchitektur. Vor einer Store-Veröffentlichung brauchen wir ein mitgeliefertes statisches Frontend sowie eine ausdrücklich entworfene und getestete Trennung von App-Start, Anmeldung, Sitzung und Server-API. Das bestehende `credentials: 'same-origin'` allein reicht dafür nicht.

Die Basis-ID lautet `com.unionofkingdoms.app`; Debug-Builds erhalten `.prototype` und sind damit separat installierbar. Release-Builds sind in diesem Prototyp gesperrt. Es gibt keine Store-Signierung, keinen Upload und keine Reservierung eines Google-Play-Eintrags. Das offizielle Modul `@capacitor/app` 8.1.1 ist für Androids Zurück-Taste eingebunden: Sein Standardhandler navigiert durch den WebView-Verlauf, den die bestehenden mobilen Ansichten nutzen. Die Abnahme von Dialogen, Gesten und der ersten Ansicht steht noch aus; weitere native Funktionen sind nicht eingebunden.

## Voraussetzungen

| Werkzeug | Stand für diesen Prototyp |
| --- | --- |
| Node.js | 22 oder neuer; lokal 24.21.0 installiert |
| pnpm | 11.19.0, über `packageManager` festgelegt |
| Capacitor | 8.5.2, im Lockfile festgelegt |
| Android Studio | 2025.2.1 oder neuer; lokal Rabbit 1 / 2026.2.1 installiert |
| Java für Gradle | JDK 21; lokal Eclipse Temurin 21.0.12.1 installiert |
| Android SDK | Plattform 36, Build-Tools 35.0.0/36.0.0 und Platform-Tools installiert |
| Testgerät | Samsung Galaxy S23 Ultra mit freigegebenem USB-Debugging oder zunächst ein Android-Emulator |

Die Programme wurden am 1. Oktober im Windows-Benutzerkonto eingerichtet. Android Studio steht im Startmenü und unter `%LOCALAPPDATA%\Programs\Android\android-studio`; Node.js und pnpm unter `%LOCALAPPDATA%\Programs\nodejs`; das SDK unter `%LOCALAPPDATA%\Android\Sdk`. `JAVA_HOME`, `ANDROID_HOME`, `ANDROID_SDK_ROOT`, `CAPACITOR_ANDROID_STUDIO_PATH` und der Benutzer-PATH sind eingerichtet. Bereits geöffnete Terminals bzw. Entwicklungsprogramme nach der Installation neu öffnen, damit sie die neuen Umgebungsvariablen übernehmen.

Android Studio verwendet seine eigene Java-25-Laufzeit. Das vorhandene Gradle 8.14.3 unterstützt als Laufzeit jedoch nur bis Java 24; für den App-Bau ist deshalb separat Java 21 unter `%LOCALAPPDATA%\Programs\Java` installiert. Die lokale, nicht versionierte Android-Studio-Projekteinstellung verwendet `JAVA_HOME` als Gradle-JDK. Bei einer erneuten Einrichtung unter **Settings → Build Tools → Gradle → Gradle JDK** Java 21 auswählen. Den SDK-Pfad liefern `android/local.properties` oder `ANDROID_HOME`. Lokale Pfade und Schlüsseldateien gehören nicht ins Repository. [Gradle-Kompatibilität](https://docs.gradle.org/current/userguide/compatibility.html).

## Vorbereiten und starten

Die folgenden Befehle werden im Verzeichnis `mobile` ausgeführt:

```powershell
pnpm install --frozen-lockfile
pnpm configure
pnpm android:sync
pnpm doctor
pnpm android:open
```

`pnpm configure` erzeugt die lokale Capacitor-Konfiguration. `pnpm android:sync` erstellt die gebündelte Fehlerseite und synchronisiert sie mit dem vorhandenen Android-Projekt. `pnpm web` baut diese Webdateien bei Bedarf separat. `pnpm doctor` prüft Voraussetzungen; ein erfolgreicher Lauf ist weder ein APK-Build noch ein Gerätetest.

Nach Einrichtung der Android-Werkzeuge und Anschluss des Testgeräts kann der Debug-Build aus Android Studio oder mit `pnpm android:run` gebaut und gestartet werden. Nach Änderungen an der Konfiguration oder den gebündelten Webdateien zuerst erneut `pnpm android:sync` ausführen. Das native Projekt unter `android/` bleibt erhalten; es muss nicht bei jedem Start neu erzeugt werden.

## Eine andere Testadresse verwenden

Optional `mobile.config.example.json` als `mobile.local.json` kopieren und dort die eigene HTTPS-Spieladresse eintragen:

```json
{
  "gameUrl": "https://play.unionofkingdoms.com/"
}
```

Danach `pnpm configure` und `pnpm android:sync` ausführen. Die Adresse bezeichnet den Einstieg der eigenen Spielinstallation und darf weder Zugangsdaten noch Query-Parameter oder einen Fragmentteil enthalten. Unverschlüsseltes HTTP und gemischte Inhalte sind für diesen App-Prototyp deaktiviert. Zugangsdaten werden erst im normalen Anmeldeformular eingegeben.

## Erster Geräte-Meilenstein

Bereits lokal geprüft: `node --test tests/mobile_config.cjs` prüft die HTTPS-Konfiguration und schließt Zugangsdaten in der Serveradresse aus. `node tests/mobile_shell_ui.cjs` prüft die Fehlerseite in Englisch, Deutsch und Französisch bei 1280 × 800, 390 × 844, 320 × 568, 844 × 390 und 568 × 320 Pixeln. Alle 15 Kombinationen bestanden auf einer Vorschau, die ausschließlich `/index.html` ausliefert; keine weiteren Ressourcenanfragen, Service-Worker-Registrierungen oder Browserfehler. Englisch bleibt ohne ausdrückliche Auswahl die Standardsprache. Aufnahmen: `artifacts/mobile-shell/`. Zusätzlich bestand die vorhandene Prüfung `tests/localization_pwa.cjs` für das unveränderte Standardverhalten im Browser. Die Browserprüfungen benötigen Playwright und Microsoft Edge, der PWA-Test zusätzlich PHP. Diese Ergebnisse ersetzen keine native Geräteprüfung.

### Erste Geräteprüfung am 1. Oktober 2026

Auf dem Samsung Galaxy S23 Ultra (Android 16 / API 36), Build `0.1.0-prototype`, im Hochformat mit 1440 × 3088 Gerätepixeln bestätigt:

- Der Nutzer hat sich selbst angemeldet; anschließend war das angemeldete Spiel sichtbar.
- Weltkarte → Stadt und Stadt → Weltkarte waren erreichbar. Beim ersten Tippen auf das Welt-Symbol blieb die Stadt sichtbar; ein anschließendes Tippen auf die Beschriftung öffnete die Weltkarte. Die gesamte Touchfläche ist damit noch nicht abgenommen.
- Das Inventar ließ sich öffnen; Android Zurück führte wieder zur Stadt.
- Nach Wechsel zum Android-Startbildschirm und erneuter Aktivierung der App blieben Anmeldung und Stadtansicht erhalten. Dies prüft keinen Prozessneustart.
- In den geprüften Aufnahmen lagen Ressourcenanzeige und untere Navigation außerhalb der Android-Systemleisten. Das ersetzt keine Prüfung von Tastatur, Querformat und allen Dialogen.

Aufnahmen liegen lokal unter `artifacts/android-device/`; insbesondere `04-village.png`, `05-inventory.png`, `06-back-to-village.png`, `09-after-app-switch.png` und `10-world-recheck.png`. Es wurden keine Spielaktionen ausgelöst, Gegenstände verwendet oder Nachrichten gesendet. Die vorübergehend aktivierte Bildschirm-Wachhaltung bei USB-Verbindung wurde auf den ursprünglichen Wert `0` zurückgesetzt und geprüft. Ein begrenzter Abruf der letzten Geräteprotokolle lieferte für den App-Prozess keine Einträge der ausgewählten Fehlerkanäle; die frühere SystemBars-Meldung ist damit nicht widerlegt oder behoben.

### Weltkarten-Leistung am 2. Oktober 2026

Nach der Nutzermeldung über Ruckeln wurde der lokale Kartenrenderer optimiert: Ohne aktive Textsuche werden keine Suchtexte für jedes Ziel erzeugt. Entfernte, unsichtbare Ziele erhalten keine Positions-/Größenänderungen; beim Eintritt in den sichtbaren Ausschnitt wird ihre Position aktualisiert. Ein Rand hält Ziele während des gepufferten Verschiebens bereit. Wassersegmente vollständig außerhalb des gezeichneten Ausschnitts werden einschließlich ihrer Uferbreite ausgespart.

Vergleich auf derselben lokalen synthetischen Karte mit 180 zusätzlichen Gegnern, 412 × 883 CSS-Pixeln, Pixelfaktor 3,5 und vierfacher CPU-Drosselung in Edge: 21 Neuzeichnungen benötigten zusammen vorher 672 ms, nachher 193 ms; das 95. Perzentil pro Neuzeichnung sank von 48,6 auf 12,4 ms. Die gesamten gemessenen Frame-Abstände lagen im 95. Perzentil weiterhin bei etwa 50 ms statt vorher 100 ms. Dies ist eine lokale Diagnose mit Messinstrumentierung, keine bestätigte Bildrate auf Android und keine Garantie für ruckelfreies Spielen. Rohdaten und Aufnahmen: `artifacts/world-performance/local-before.*` und `local-after.*`.

Bestanden: Gestenregression in Desktop, Hoch- und Querformat einschließlich unveränderter entfernter Ziele und korrektem Wiedereinblenden; gezeichnete Karte mit Zielaktionen in vier Formaten; Navigation zu Kartenzielen in der isolierten Haupt-App; 262.144 Wasser-/Landproben gegen die Serverregeln. Ein Pixelvergleich von 24 Wasserausschnitten bei drei Zoomstufen ergab keine sichtbaren Änderungen; die Zeichenaufrufe für Linien sanken dabei von 2.522 auf 404. Die Pixelprüfung lief gegen den vorherigen `world-landscape.js`-Stand aus Git.

Die Gerätemessung kam wegen getrennter USB-Verbindung nicht zustande. Die vorangegangene Bildschirm-Einstellung war auf `0` zurückgesetzt; der neue Versuch, sie vorübergehend zu ändern, scheiterte bereits mit „device not found“. Es liegen keine Messwerte des optimierten Stands auf dem S23 Ultra vor. Die Änderungen sind lokal; ein Upload auf den HTTPS-Spielserver wurde nicht durchgeführt und die installierte App lädt sie daher noch nicht.

Für die vollständige Abnahme mit einem dafür vorgesehenen Testkonto auf dem S23 Ultra prüfen und Android-Version, Build, Ausrichtung sowie Ergebnis festhalten:

1. Passwortanmeldung, Wiederherstellung, Abmeldung und erneute Anmeldung funktionieren; die Bildschirmtastatur verdeckt keine Hauptaktion.
2. Stadt und Weltkarte sowie ein Gebäude sind in Hoch- und Querformat erreichbar. Systemleisten, Aussparungen und Touch-Gesten stören die Bedienung nicht.
3. Ein Gebäudeausbau wird genau einmal ausgeführt. Nach App-Wechsel und Bildschirmsperre stimmen Spielstand und Timer.
4. Verbindungsverlust beim Start und während einer Aktion bleibt verständlich. Erneutes Verbinden und Wiederholen erzeugen keine doppelte Aktion.
5. Android Zurück schließt zuerst den passenden Dialog bzw. wechselt zur vorherigen Ansicht. Externe Links öffnen nachvollziehbar außerhalb des Spiels.
6. Das vom Betriebssystem beendete Spiel wird erneut gestartet. Anmeldung, Serverzustand und ein Auftrag mit unbekanntem Ergebnis werden korrekt behandelt.

Diese Punkte sind durch die erste Navigationsprüfung nur teilweise abgedeckt. Insbesondere müssen das native Zurück-Verhalten für weitere Dialoge und die Wiederaufnahme mit `mobile-pages.js` und `app-polling.js` vollständig geprüft werden. Auftragsbelege liegen derzeit in `sessionStorage`; das Verhalten nach Prozessende ist nicht nachgewiesen. Ebenso offen sind die Behandlung vorhandener PWA-Installationshinweise und Service-Worker, externe Fenster/Downloads sowie eine spätere native OAuth-Rückkehr.

Erst nach diesem Durchstich folgen die endgültige App-/API-Trennung, weitere Geräte, gegebenenfalls native Funktionen und die Vorbereitung der Store-Veröffentlichung. Die vollständige Abnahme bleibt in [MOBILE_APP_ROADMAP.md](../docs/MOBILE_APP_ROADMAP.md), die gemeinsame Architektur in [MOBILE_APP_STRATEGY.md](../docs/MOBILE_APP_STRATEGY.md) beschrieben.
