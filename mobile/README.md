# Union of Kingdoms – Android-Prototyp

Stand: 1. Oktober 2026. Auf ausdrücklichen Nutzerwunsch beginnt die Android-App jetzt mit einem kleinen Capacitor-Prototyp. Ziel des ersten Gerätebuilds ist Anmeldung → Stadt/Welt → Gebäudeausbau → App-Wechsel → korrekter Spielstand. Die gemeinsame Weboberfläche bleibt auch die Grundlage für eine spätere iOS-App.

**Status:** Die Android-Werkzeuge sind installiert. Am 1. Oktober 2026 wurde `assembleDebug` mit Gradle 8.14.3 und Java 21 erfolgreich ausgeführt; die APK-Signatur wurde geprüft. Die Testdatei liegt unter `artifacts/android/Union-of-Kingdoms-0.1.0-prototype.apk` (4.597.367 Bytes), Paket-ID `com.unionofkingdoms.app.prototype`, Version `0.1.0-prototype`, Mindest-API 24 und Ziel-API 36. SHA-256: `18e85af65f8954e59c58131ae01e8ec2374e8c008b206f6dafe31907a91df63d`. Anschließend wurde die APK per USB erfolgreich auf dem Samsung Galaxy S23 Ultra (SM-S918B, Android 16 / API 36) installiert und kalt gestartet; der App-Prozess blieb aktiv. Nach der Anmeldung durch den Nutzer bestanden erste Navigationsprüfungen im Hochformat: Stadt/Welt, Inventar mit Android Zurück und Wiederaufnahme nach App-Wechsel. Die vollständige native Geräteprüfung sowie die Freigabe für Google Play stehen noch aus.

Beim ersten Start meldete Capacitor 8.5.2 einmal `Error injecting safe area CSS: TypeError: Cannot read properties of null (reading 'style')` aus der eingebauten SystemBars-Erweiterung. Die App lief weiter. Das ist keine fehlerfreie Geräteabnahme: Bildschirmränder und Tastatur müssen im sichtbaren Spiel geprüft werden. Es wurden keine Kontodaten eingegeben oder Spielaktionen ausgeführt.

## Architektur und Grenzen

### Erste signierte Play-Console-Testfassung (8. Oktober 2026)

Der Nutzer hat ausdrücklich ein Upload-Paket für die erste Google-Play-Veröffentlichung und die Installation auf seinem verbundenen Handy angefordert. Deshalb sind signierte Release-Builds für die Play-Console-Testphase jetzt möglich. Diese Entscheidung ersetzt die bisherige pauschale Release-Build-Sperre; die ausstehenden Architektur- und Geräteprüfungen bleiben offen.

Version `0.2.0`, Versionscode `4`, Paket-ID `com.unionofkingdoms.app`, Mindest-API `24`, Ziel-API `36`. Das aktuelle freigegebene Kingdom-Icon wird mitgeliefert. Die App lädt weiterhin `https://play.unionofkingdoms.com/`; lokale Änderungen am Spiel erfordern ein separates Serverdeployment. Die Build-Dateien allein bestätigen weder eine Google-Play-Abnahme noch die Produktionsreife der Online-Hülle.

Für neue Apps erzeugt `pnpm signing:init` einmalig einen RSA-4096-Upload-Schlüssel unter `mobile/.signing/upload.jks`, das öffentliche Zertifikat `mobile/.signing/upload-certificate.pem` und die Zugangsdaten unter `mobile/android/key.properties`. Bestehende Schlüssel werden niemals überschrieben. Beide privaten Dateien müssen gemeinsam sicher gesichert werden; auf Windows sind die Berechtigungen auf das Benutzerkonto und SYSTEM begrenzt. Sie sind von Git ausgeschlossen, durch `mobile/.htaccess` vor Webzugriff geschützt und werden nicht ins App-Paket kopiert. Für spätere Updates stets denselben Upload-Schlüssel verwenden und den Versionscode erhöhen.

`pnpm android:release` baut die aktuelle Verbindungsseite, synchronisiert Capacitor und führt `bundleRelease`, `assembleRelease` und `lintRelease` aus. Standardmäßig wird der lokale Gradle-Cache offline verwendet; beim ersten Release bei fehlenden Prüfbibliotheken `pnpm android:release --online` ausführen. Ohne Upload-Signierung oder mit unsicherer HTTP-/Debug-Konfiguration bricht der Ablauf ab. Die fertigen Dateien und ihre SHA-256-Werte liegen unter `artifacts/android/`:

- `Union-of-Kingdoms-0.2.0-release.aab`: signiertes Android App Bundle für die Play Console und den angeforderten Early Access.
- `Union-of-Kingdoms-0.2.0-release.apk`: signierte APK zur direkten Installation auf dem Gerät.
- `Union-of-Kingdoms-0.2.0-release.json`: Versionsangaben und Dateiprüfsummen.

Die Release-App verwendet die endgültige Paket-ID und kann neben `com.unionofkingdoms.app.prototype` installiert werden. Anmeldung und App-Daten sind getrennt. Bei Google-generiertem App-Signierschlüssel lässt sich die direkt mit dem Upload-Schlüssel signierte APK später nicht einfach durch die Play-Fassung aktualisieren: für einen solchen Wechsel den Testclient entfernen oder die von Google signierte APK aus der Play Console verwenden. [Google Play App Signing](https://support.google.com/googleplay/android-developer/answer/9842756).

**Play-Console-Stand (9. Oktober 2026):** Die App **Union of Kingdoms** ist angelegt, Englisch (en-US) ist Standardsprache. Google hat das signierte AAB als Version 0.2.0 / Code 4 verarbeitet. Der Nutzer hat **Early Access weltweit, öffentlich und ohne Teilnehmerlimit** bestätigt. Der Track `Early Access` enthält `0.2.0 (4) - Early Access`; 177 Länder/Regionen und „Rest of World“ sind ausgewählt (178 Einträge). Offene Einladungen mit unbegrenzten Teilnehmern und `hello@unionofkingdoms.com` als Feedbackkontakt sind gespeichert. Die 16 Änderungen wurden eingereicht; Publishing overview bestätigt **Changes in review**. Die automatischen Vorprüfungen laufen noch. Die App ist damit noch nicht öffentlich verfügbar. Managed publishing ist aus: nach erfolgreicher Prüfung veröffentlicht Google die freigegebenen Änderungen automatisch. Die zusätzliche Google-Play-Games-PC-Verteilung ist abgewählt.

Die englische Kurz- und Vollbeschreibung, das freigegebene Kingdom-Icon, eine Titelgrafik mit 1024 × 500 Pixeln und zwei echte Handy-Screenshots (Stadt und Welt, je 675 × 1200) sind eingereicht. Icon und Titelgrafik sind entsprechend der Nutzerbestätigung als KI-generiert gekennzeichnet. Keine Werbung, keine Werbe-ID, keine Regierungs-, Finanz- oder Gesundheitsfunktionen wurden entsprechend dem geprüften aktuellen Projektstand deklariert. Die Zielgruppen 13–15, 16–17 sowie 18+ sind gespeichert. Der vom Nutzer freigegebene IARC-Fragebogen berücksichtigt Fantasy-Gewalt und selten beängstigende Monsterbilder; die berechneten Bewertungen umfassen PEGI 7, USK 6+ und ESRB Everyone 10+. Die inhaltliche Bewertung ist von der beabsichtigten Zielgruppe 13+ getrennt.

Die freigegebenen englischen Seiten [Privacy policy](https://unionofkingdoms.com/privacy) und [Account and data deletion](https://unionofkingdoms.com/account-deletion) sind öffentlich ohne Anmeldung verfügbar, auch unter dem Spielhost. Anmeldung, öffentliche Startseite, Konto und Optionen verlinken darauf. Der Betreiber Sven Manderscheid (Ekki) hat die regelmäßige Bearbeitung der Kontaktadresse und den verifizierten manuellen Löschablauf bestätigt; Betriebsanleitung: `docs/PRIVACY_OPERATIONS.md`. Die vier öffentlichen Routen wurden mit GET/HEAD (200) und POST (405) geprüft. Rechtsseiten und neue Optionslinks wurden in Desktop-, Hoch- und Querformat kontrolliert. Dies ersetzt keine vollständige native Geräteabnahme.

Die Datensicherheitserklärung ist ausgefüllt und eingereicht: zwölf Datentypen, HTTPS-Transport, Kontoerstellung mit Benutzername/Passwort, öffentliche Konto- und Teildatenlöschungswege und keine Weitergabe im Sinne der Google-Play-Definition. Der derzeitige Server hat keine Echtgeldzahlung, Discord-Anmeldung oder Profilfoto-Moderation aktiviert; optionale Support-Screenshots werden berücksichtigt. Das normale Prüferkonto `PlayReviewer` wurde ohne Verwaltungsrechte angelegt und per HTTPS-Anmeldung sowie authentifizierter Spielzustands-API geprüft. Seine Zugangsdaten liegen ausschließlich lokal unter der ignorierten, zugriffsbeschränkten Datei `mobile/.signing/play-review.json` und in Googles Prüferanweisungen. Die optionale Weitergabe an zusätzliche Partner-Testgeräte ist aus. Aufnahmen und Belege ohne Passwörter liegen unter dem geschützten, ignorierten Verzeichnis `output/play-console-2026-10-08/`. Das Dashboard verlangt für spätere Produktionsfreigabe weiterhin mindestens 12 Teilnehmer am geschlossenen Test über 14 zusammenhängende Tage. [App anlegen und Upload-Format](https://support.google.com/googleplay/android-developer/answer/9859152).

**Bau und Prüfung am 8. Oktober:** `bundleRelease`, `assembleRelease` und `lintRelease` bestanden; das App-Lint meldet 0 Fehler und 9 bestehende Warnungen. Der lokale SDK-Pfad wurde für Java-Properties korrekt maskiert. APK-Signatur (v2, RSA 4096), AAB-Signatur, Google `bundletool` 1.18.3, ZIP-Integrität, HTTPS-Konfiguration und identische Pixel des freigegebenen Launcher-Icons wurden geprüft. Keine privaten Schlüssel, PHP-/SQL-Dateien oder nativen `.so`-Bibliotheken im Paket. Das AAB ist 3.344.854 Bytes groß, die APK 3.459.199 Bytes. SHA-256: AAB `b729c324a1c9ec8eff6d1ac8265b15e6ea0506b2eb325552b9839c085c7b5ad6`; APK `e37809d916b5133b5adcc1be9d07ebd1b3f9a5b550c28f790b49bc3664b24022`. Das Upload-Zertifikat hat SHA-256 `dbdc0f02cecff8f7e6ff835407e19cf86c10f55022138a0eb80c1076ea139bac`; eine öffentliche PEM-Kopie liegt unter `artifacts/android/Union-of-Kingdoms-upload-certificate.pem`.

Die drei Konfigurationsprüfungen und alle 15 Sprach-/Bildschirmprüfungen der gebündelten Verbindungsseite bestanden (853.602 Bytes). Auf dem verbundenen S23 Ultra (SM-S918B, Android 16) wurde die Release-APK erfolgreich installiert und kalt gestartet (`Status: ok`, 741 ms Android-Startzeit, laufender Prozess). Die Paketprüfung bestätigt Version 0.2.0 / Code 4 und deaktiviertes App-Debugging. Die erste Aufnahme zeigt jedoch das gesperrte Always-on-Display; damit ist noch keine sichtbare Spiel- oder Anmeldeprüfung belegt. Prüfnachweise: `output/android-release-2026-10-08/`. Der AAB-Upload in die Play Console ist inzwischen erfolgt; vollständige Geräteabnahme und Store-Freigabe stehen weiterhin aus.

Die Play-Console-Kategorie „Game → Strategy“, die öffentliche Supportadresse `hello@unionofkingdoms.com` und die Website `https://unionofkingdoms.com` sind gespeichert. Bei den Abschlussprüfungen am 9. Oktober war kein USB-Gerät verbunden; die oben dokumentierte Installation vom 8. Oktober bleibt der letzte Gerätebeleg.

### Aktualisierter Android-Build (4. Oktober 2026)

Der aktuelle Projektstand wurde mit synchronisierter Fehlerseite und den aktuellen Android-Ressourcen einschließlich des freigegebenen Launcher-Icons als `0.1.2-prototype` (Versionscode 3) gebaut. Datei: `artifacts/android/Union-of-Kingdoms-0.1.2-prototype.apk`. `assembleDebug --offline` und die APK-Signaturprüfung bestanden. SHA-256: `d06c44f71c5d70e7fdb461435af8a085cca2abc1af071de95370c2b2015884d8`.

Beim Build war kein USB-Gerät verbunden; Installation und Geräteprüfung dieses Builds stehen aus. Die APK lädt weiterhin den HTTPS-Spielserver. Lokale Änderungen am eigentlichen Spiel benötigen zusätzlich ein Serverdeployment.

### Freigegebenes App-Icon (7. Oktober 2026)

Der Nutzer hat **A · Kingdom** als neues Motiv gewählt: eine elfenbeinfarbene Burg mit blauen Dächern, goldener Krone und kleinen grünen Büschen auf Weiß. Die Quelle liegt unter `assets/art/app-icon/union-of-kingdoms-kingdom-v1.png`; der gepolsterte Exportmaster unter `assets/icons/union-of-kingdoms-painted-master.png`. `tools/build-brand-icons.cjs` exportiert Web-/PWA-, Apple-Touch- und Android-Launcher-Symbole. Die maskierbare Fassung bewahrt die bemalte Silhouette innerhalb des zentralen Sicherheitskreises auf Weiß. Das separate transparente Favicon zeigt nur einen gekrönten Turm und verwendet `assets/icons/union-of-kingdoms-kingdom-favicon-master.png`. Diese Auswahl ersetzt das am 4. Oktober freigegebene Kampfmotiv; dessen ursprüngliche Quelle bleibt erhalten. Android verwendet weiterhin `drawable-nodpi/uok_launcher.png`; auf bereits installierten Geräten erscheint die Änderung erst nach einem neuen APK-Build und einer Aktualisierung der App. Der Symbolexport erstellt keinen neuen APK-Build und bestätigt keine Geräteprüfung.

### Ausblendbare Android-Navigation (3. Oktober 2026)

Der Debug-Build `0.1.1-prototype` (Versionscode 2) blendet die untere Android-Systemnavigation mit `WindowInsetsControllerCompat` aus. Ein Randwisch zeigt die Leiste vorübergehend als Overlay; Android Zurück bleibt unverändert verfügbar. Die Statusleiste und Capacitors bestehende Behandlung der sicheren Bildschirmränder bleiben erhalten. Start, Wiederaufnahme, Fensterfokus und Ausrichtungswechsel stellen den Modus wieder her. Während sichtbarer Bildschirmtastatur wird die Navigation nicht erneut verborgen; nach dem Schließen der Tastatur wird sie wieder ausgeblendet. Der Tastaturbeobachter ersetzt nicht Capacitors Insets-Listener.

`assembleDebug --offline` und die APK-Signaturprüfung bestanden. Die aktualisierte Testdatei liegt unter `artifacts/android/Union-of-Kingdoms-0.1.1-prototype.apk`; SHA-256: `513f5a01ffc1306720aabab44d3f4dab2bada30bc70ec88db00d491cc1153efc`.

Die Geräteprüfung steht aus: Beim Bau war kein USB-Gerät verbunden. Auf dem S23 Ultra Hoch-/Querformat, Stadt/Welt, Randwisch → Zurück/Home, App-Wechsel und Texteingabe → Tastatur schließen prüfen. Dabei insbesondere kontrollieren, dass die untere Spielnavigation den frei gewordenen Platz nutzt und Kameraaussparungen sowie Eingabefelder frei bleiben.

Capacitor 8.5.2 lädt für diesen Prototyp die vorhandene HTTPS-Spielseite, standardmäßig `https://play.unionofkingdoms.com/`. PHP-Anmeldung, Sitzungscookie, CSRF-Schutz und API-Aufrufe bleiben damit auf demselben Ursprung. PHP, Datenbank, Geheimnisse und verbindliche Spielregeln bleiben auf dem Server.

Das lokale Webpaket enthält eine eigenständige Verbindungs-/Fehlerseite: Schrift, gemeinsame Stile, Sprachsystem und Symbol sind vollständig in die HTML-Datei eingebettet; daneben liegen zwei Schriftlizenzen. Das ist erforderlich, weil Capacitor bei diesem entfernten Spielserver nur die genaue Fehlerseiten-URL lokal ausliefert. Eine mit Hashwerten begrenzte Inhaltsrichtlinie erlaubt die eingebetteten Stile und Skripte; die Fehlerseite registriert keinen Service-Worker und lädt keine Ressourcen nach. Sie enthält weder eine vollständige Kopie des Spiels noch einen Offline-Spielstand. Die Inhalte der entfernten Spielseite ändern sich mit deren Serverdeployment.

`server.url` ist laut [Capacitor-Konfigurationsdokumentation](https://capacitorjs.com/docs/config) für Entwicklung vorgesehen und keine Produktionsarchitektur. Vor einer Store-Veröffentlichung brauchen wir ein mitgeliefertes statisches Frontend sowie eine ausdrücklich entworfene und getestete Trennung von App-Start, Anmeldung, Sitzung und Server-API. Das bestehende `credentials: 'same-origin'` allein reicht dafür nicht.

Die Basis-ID lautet `com.unionofkingdoms.app`; Debug-Builds erhalten `.prototype` und sind damit separat installierbar. Seit dem ausdrücklichen Nutzerauftrag vom 8. Oktober sind lokal signierte Release-Builds für Play-Console-Tests möglich (siehe oben). Ein Upload oder die Reservierung eines Google-Play-Eintrags wird durch den Build nicht ausgeführt. Das offizielle Modul `@capacitor/app` 8.1.1 ist für Androids Zurück-Taste eingebunden: Sein Standardhandler navigiert durch den WebView-Verlauf, den die bestehenden mobilen Ansichten nutzen. Die Abnahme von Dialogen, Gesten und der ersten Ansicht steht noch aus. Seit dem lokalen Komfortpaket vom 9. Oktober 2026 verwendet das gemeinsame Frontend außerdem die nativen App-Zustandsereignisse für Pause und Wiederaufnahme der Spielstandsabrufe. Die Browserprüfung simuliert die Schnittstelle und bestätigt keine neue Geräteabnahme. Am 9. Oktober hat der Nutzer Commit und Push für die Handyprüfung beauftragt; für dieses Web-Paket ist kein neuer App-Build erforderlich. Einzelheiten: `docs/MOBILE_APP_STRATEGY.md`. Weitere native Funktionen sind nicht eingebunden.

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
pnpm run doctor
pnpm android:open
```

`pnpm configure` erzeugt die lokale Capacitor-Konfiguration. `pnpm android:sync` erstellt die gebündelte Fehlerseite und synchronisiert sie mit dem vorhandenen Android-Projekt. `pnpm web` baut diese Webdateien bei Bedarf separat. `pnpm run doctor` prüft Voraussetzungen; ein erfolgreicher Lauf ist weder ein APK-Build noch ein Gerätetest.

Nach Einrichtung der Android-Werkzeuge und Anschluss des Testgeräts kann der Debug-Build aus Android Studio oder mit `pnpm android:run` gebaut und gestartet werden. Nach Änderungen an der Konfiguration oder den gebündelten Webdateien zuerst erneut `pnpm android:sync` ausführen. Das native Projekt unter `android/` bleibt erhalten; es muss nicht bei jedem Start neu erzeugt werden.

## Benachrichtigungen auf Android (10. Oktober 2026)

Auf ausdrücklichen Nutzerauftrag ist das offizielle Modul `@capacitor/push-notifications` **8.1.3** jetzt in der gemeinsamen Hülle eingebunden und mit Android synchronisiert. Die zuvor installierte Fassung `0.2.0 (4)` enthält dieses Modul noch nicht. Deshalb wurde am 10. Oktober der neue signierte Build `0.3.0 (5)` erstellt und anschließend per USB auf dem S23 Ultra aktualisiert. Anmeldung, ausdrückliche Android-Freigabe und tatsächlicher Empfang einer Testmeldung im Hintergrund sind bestätigt; Antippen und die übrige vollständige Geräteabnahme werden getrennt geprüft. Es wurde nichts in Google Play hochgeladen.

**Neuer Push-Build:** **`0.3.0` / Versionscode `5`**, Paket-ID `com.unionofkingdoms.app`, bestehende Upload-Signierung und Spieladresse `https://play.unionofkingdoms.com/`. `pnpm run android:release --online` führte `bundleRelease`, `assembleRelease` und `lintRelease` erfolgreich aus (App-Lint: 0 Fehler, 9 Warnungen). Die fertigen APK/AAB und Prüfsummen liegen unter `artifacts/android/Union-of-Kingdoms-0.3.0-release.apk`, `.aab` und `.json`. APK-Signatur, AAB-Signatur, identische bisherige Zertifikatskennung, bundletool, ZIP-Integrität, native Push-Erweiterung, Firebase-IDs und Berechtigungen wurden geprüft. Keine privaten Schlüssel im Paket. Die durch Firebase hinzugefügte native DataStore-Bibliothek ist in allen vier ABIs auf 16 KB ausgerichtet; die APK-ZIP-Ausrichtung wurde ebenfalls geprüft. Belege: `output/android-push-release-20261010/`. Beim Build war kein USB-Gerät verbunden; eine Veröffentlichung oder Geräteabnahme ist damit nicht erfolgt.

Vorbereitung geprüft: Alle vier Tests in `tests/mobile_config.cjs` und der Abgleich der Versionsmetadaten bestanden. Der Gradle-Abhängigkeitsbericht für `releaseRuntimeClasspath` löste die neue FCM-Laufzeit `firebase-messaging:25.0.1` und ihre Abhängigkeiten ohne fehlende Einträge auf. Dieser reine Auflösungslauf kompiliert keine APK und benötigt keine Ersatz-Firebase-Datei. Bericht: `output/android-push-preparation/release-runtime-dependencies.txt`.

**Firebase und Server eingerichtet:** Das vorhandene Cloud-Projekt `union-of-kingdoms-push` ist jetzt als aktives Firebase-Projekt im kostenlosen Spark-Tarif freigegeben. Release- und Debug-App sind registriert und ihre echten Clientdateien liegen in den unten angegebenen, ignorierten Variantendateien mit eingeschränkten lokalen Berechtigungen. Der private Serverschlüssel liegt außerhalb des Webroots; die eigene Rolle erlaubt nur `cloudmessaging.messages.create`. Die echte Google-OAuth-/FCM-Prüfung mit `validate_only:true` bestand mit HTTP 200; Web- und Android-Verfügbarkeit sind am HTTPS-Spielserver aktiv. Dieser Prüfmodus stellt keine Nachricht zu. Diese Schritte sind auf dem angeschlossenen S23 Ultra durchgeführt: Update installiert, Gerätebenachrichtigungen aktiviert, Android-Berechtigung erteilt und echte Hintergrund-Testmeldung um 17:51 Uhr empfangen. Die Freigabe gilt nur für dieses Konto und Gerät. Details: `docs/PUSH_NOTIFICATIONS.md`.

`pnpm android:release` bricht weiterhin vor dem Build ab, wenn die Release-Datei fehlt, ungültig ist oder zur falschen Paket-ID gehört. Direkte Gradle-Builds prüfen die Firebase-Datei ebenfalls durch das Google-Services-Plugin.

Einrichtung im eigenen Firebase-Projekt:

1. Die Android-App **`com.unionofkingdoms.app`** registrieren und deren `google-services.json` lokal unter **`mobile/android/app/src/release/google-services.json`** speichern.
2. Für einen getrennten Debug-Gerätetest zusätzlich **`com.unionofkingdoms.app.prototype`** registrieren und deren Datei unter **`mobile/android/app/src/debug/google-services.json`** speichern. Die Paketnamen müssen genau stimmen. Eine gemeinsame Datei unter `mobile/android/app/google-services.json` wird ebenfalls unterstützt, wenn sie die passenden Clients enthält; eine Variantendatei hat Vorrang.
3. Die FCM-HTTP-v1-Zustellung auf dem PHP-Server einrichten. Private Service-Account-Schlüssel bleiben ausschließlich in der geschützten Serverkonfiguration; sie gehören weder in `google-services.json` noch ins App-Paket. Alle Firebase-Konfigurationsdateien sind lokal von Git ausgeschlossen. Die Übersicht für den Server steht in `docs/PUSH_NOTIFICATIONS.md`.
4. `pnpm configure`, `pnpm android:sync` und `pnpm run doctor` ausführen. Vor einem weiteren Play-Testbuild den Versionscode erhöhen und die bestehenden Signierschlüssel verwenden.
5. Mit einem Testkonto die neue App installieren und in **Settings → Device notifications** ausdrücklich aktivieren. Android 13 und neuer fragt nach der Betriebssystem-Berechtigung. Danach Testnachricht, Vordergrund, Hintergrund, Prozessneustart, Antippen einer Nachricht, Abmelden und Deaktivieren prüfen. Die vorhandene echte Gerätetestliste bleibt ebenfalls offen.

Der Benachrichtigungskanal heißt technisch `uok_game`; sein sichtbarer Name kommt aus dem gemeinsamen Sprachsystem. Das weiße Statussymbol verwendet die bereits freigegebene VIP-Krone. Firebase Messaging startet zunächst ohne automatische Registrierung; das offizielle Plugin aktiviert die Registrierung erst beim ausdrücklichen Einschalten und deaktiviert sie beim Ausschalten. Die Hülle lässt sichtbare Meldungen auch im Vordergrund zu. Eine Nachricht öffnet die gemeinsame Spieloberfläche; Spielregeln und Aufträge bleiben serverseitig.

`node --test tests/mobile_config.cjs` prüft die Plugin-Konfiguration sowie fehlende, fehlerhafte, unvollständige und zur falschen Variante gehörende Firebase-Dateien an synthetischen lokalen Fixtures. Diese Tests und `android:sync` bestätigen keine echte FCM-Zustellung. Native iOS-Projekte sind weiterhin nicht angelegt; iPhone-Web-Push verwendet die zum Home-Bildschirm hinzugefügte Web-App.

Offizielle Einrichtung: [Capacitor Push Notifications](https://capacitorjs.com/docs/apis/push-notifications), [Firebase Android Client](https://firebase.google.com/docs/cloud-messaging/android/client), [Firebase-Projektkonfiguration](https://firebase.google.com/docs/projects/learn-more#config-files-objects).

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
