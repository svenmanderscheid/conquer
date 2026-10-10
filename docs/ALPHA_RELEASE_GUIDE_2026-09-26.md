# Alpha veröffentlichen: GitHub, Hostinger, Android und iOS

Stand: 26.09.2026. Diese Anleitung ersetzt den historischen Sprint-0-Ablauf in `HOSTINGER_SETUP.md`. Sie beschreibt den geprüften lokalen Aufbau; Hostinger-Einstellungen und Live-Datenbank wurden in dieser Prüfung nicht aufgerufen oder verändert. Es wurde nichts gepusht oder veröffentlicht.

**Ergänzung 10. Oktober 2026 – Gerätebenachrichtigungen:** Die Push-Anbindung ergänzt erstmals Composer-Abhängigkeiten im Serverprojekt. Für dieses Feature gelten die Einrichtungsschritte in [PUSH_NOTIFICATIONS.md](PUSH_NOTIFICATIONS.md): Composer-Pakete aus dem Lockfile installieren, Migration `0145_web_push.sql` anwenden, private Web-Push-/Firebase-Konfiguration bereitstellen und den Versandjob einrichten. Die ältere Aussage unten, dass kein Composer-Schritt nötig sei, gilt für diesen neuen Funktionsumfang nicht mehr. Die Android-Anbindung benötigt außerdem eine passende Firebase-Konfiguration und einen neuen App-Build; das Aktualisieren der Webdateien allein genügt dafür nicht.

## Verbindliche Aufteilung von Website und Spiel

- `https://unionofkingdoms.com/` und `https://www.unionofkingdoms.com/`: öffentliche Website und Alpha-Warteliste. Keine Spieleranmeldung auf der Website; Login und Key-Einlösung verlinken auf die Spiel-Subdomain.
- `https://play.unionofkingdoms.com/`: eigener Spieleinstieg mit Anmeldung, Registrierung per Alpha-Key und Passwortwiederherstellung. Bestehende Spielsitzungen öffnen direkt `/city`.
- Lokal bleibt `http://127.0.0.1/conquer/` der Spieleinstieg. Die Marketingseite kann mit einem separaten lokalen VirtualHost geprüft werden.

Beim Deployment den DNS-Eintrag für `play` auf den vorgesehenen Webserver setzen, den Host dem App-Verzeichnis zuweisen und ein gültiges HTTPS-Zertifikat aktivieren. Beide öffentlichen Hosts und `play` können dieselbe Installation verwenden; der Einstieg wird anhand des Hosts gewählt. In `config/app.php` ist die produktive `base_url` **`https://play.unionofkingdoms.com`**, damit Bestätigungs- und Wiederherstellungslinks zum Spiel führen. Eventuelle OAuth-Callbacks ebenfalls auf diesen Host konfigurieren. Sitzungs-Cookies bleiben hostgebunden; keine gemeinsame Cookie-Domain `.unionofkingdoms.com` setzen.

Alte Website-Loginlinks werden zum Spiel weitergeleitet. Alte Passwort-POSTs werden nicht weitergereicht; die Anmeldung beginnt dort mit einem neuen CSRF-Token. Nach der Hostzuweisung Website, Registrierung, E-Mail-Link, Login, Rückkehr mit bestehender Sitzung und Logout auf HTTPS prüfen. Diese lokalen Änderungen richten weder DNS noch Hosting automatisch ein.

## 1. Freigabe vorbereiten

Die Alpha erst freigeben, wenn die offenen Punkte im `ALPHA_LAUNCH_REVIEW_2026-09-26.md` abgearbeitet sind. Ein erfolgreicher PHP-Syntaxcheck ersetzt weder Sicherheitstests noch eine Datenbankmigration auf einer Staging-Kopie.

Vorhandenes Repository: `git@github.com:svenmanderscheid/conquer.git`. Der ältere Projektleitfaden nennt `conquer.svenmanderscheid.lu` und `/home/u171686647/domains/svenmanderscheid.lu/public_html/conquer`. Diese Angaben im eigenen hPanel bestätigen. Insbesondere prüfen, ob ein Push auf `main` bereits automatisch live geht; für diese erste umfangreiche Bereinigung die automatische Veröffentlichung bis nach Staging und Sicherung deaktivieren.

Lokal wurden zu Beginn 357 geänderte/unversionierte Einträge gefunden. Viele gehören zu vorherigen Arbeiten. Sie müssen zusammen geprüft werden; ein pauschales `git add .` würde auch lokale Exporte und Prüfartefakte aufnehmen.

## 2. Lokale Änderungen nachvollziehbar auf GitHub sichern

In PowerShell im Projektordner:

```powershell
Set-Location C:\xampp\htdocs\conquer
git status --short
git diff --stat
git remote -v
git switch -c codex/alpha-release-review
git check-ignore config/app.php config/database.php
git ls-files config/app.php config/database.php .env
```

Der letzte Befehl darf keine echten Konfigurationsdateien ausgeben. `.gitignore` schützt keine bereits früher eingecheckten Geheimnisse. Bei einem früheren Leak Zugangsdaten widerrufen/wechseln und die Git-Historie gesondert bereinigen. Keine Passwörter, Alpha-Schlüssel, Spielerexporte, Sitzungen, Datenbanksicherungen oder API-Schlüssel in Commit, Ticket oder Screenshot aufnehmen.

Geänderte verfolgte Dateien einzeln prüfen und auswählen:

```powershell
git diff --name-status
git add -p
```

Neue Dateien gezielt mit `git add -- <Pfad>` hinzufügen. Für die ausdrücklich entfernten 3D-Dateien die Löschliste in `REMOVED_3D_2026-09-26.md` abgleichen und deren Löschungen gezielt aufnehmen. Benötigte neue 2D-Bilder, JavaScript-Module und Migrationen dürfen dabei nicht fehlen. `outputs/`, `artifacts/`, `preview/`, `tmp/`, `.codex-*`, `__pycache__/` und lokale Logs nicht als Spielrelease hochladen. Keine unbekannten Bilder allein aufgrund einer fehlenden Textreferenz löschen: Kataloge und dynamische Dateinamen laden Assets ebenfalls.

```powershell
git diff --cached --check
git diff --cached --stat
git diff --cached
git commit -m "Prepare alpha: remove 3D runtime and audit release readiness"
git push -u origin codex/alpha-release-review
```

Auf GitHub einen Pull Request dieses Branches nach `main` öffnen. Beschreibung: Änderungen, Löschumfang, Testergebnisse, bekannte offene Fehler und Datenbankänderungen. Branchschutz mit Review und erfolgreichen Prüfungen aktivieren. Verfügbare Geheimnisprüfung/Push Protection aktivieren. Vor dem Merge die endgültige Commit-ID des geprüften Stands notieren. Das Projekt benötigt aktuell keinen npm-Produktionsbuild und keinen Composer-Installationsschritt; keine erfundenen Buildbefehle hinzufügen. Es gibt bislang keine ausgefüllte CI-Pipeline.

Der übliche Push-Ablauf und die Vermeidung von Zugangsdaten im Repository sind auch in der [GitHub-Anleitung](https://docs.github.com/en/migrations/importing-source-code/using-the-command-line-to-import-source-code/adding-locally-hosted-code-to-github) beschrieben.

## 3. Eine echte Staging-Installation herstellen

Separate Subdomain, separates Verzeichnis, separate Datenbank und separate Konten verwenden. Zugang zur Staging-Seite begrenzen. PHP 8.2 oder neuer innerhalb eines noch unterstützten Versionszweigs sowie `pdo_mysql`, `mbstring`, `curl`, `openssl`, `fileinfo` und die für Bildprüfung verwendeten GD-Funktionen prüfen. Web-PHP und CLI-PHP müssen dieselbe geeignete Version verwenden. UTC für Datenbank-/Serverzeiten beibehalten.

Das Repository hat ein flaches Layout: `index.php` liegt neben `config/`, `src/`, `data/` und `migrations/`. Deshalb muss Apache/LiteSpeed die mitgelieferten `.htaccess`-Regeln tatsächlich auswerten. Auf nginx wären gleichwertige Serverregeln nötig. Vertrauliche Ordner dürfen niemals als statische Dateien erreichbar sein.

Die Upload-Ausnahmen verwenden Apache-2.4-`<If>`-Ausdrücke; auf LiteSpeed ist ein geeigneter Enterprise-Stand mit dieser Unterstützung nötig. Der neue `tests/apache_request_limits.php` hat 84 isolierte Prüfungen auf lokalem Apache bestanden. Die konkreten Hostinger-/LiteSpeed-Regeln separat testen. Normale Requests bleiben bei 65.536 Bytes, Profilbild-POST bei 5.500.000 und Bugreport-POST bei 1.300.000 Bytes. Ein global erhöhtes Limit wäre kein gleichwertiger Ersatz.

Die mitgelieferte `.user.ini` setzt `upload_max_filesize=5M` und `post_max_size=6M`. Dieselben Werte im PHP-Panel kontrollieren, damit das fachlich erlaubte Profilbild mit bis zu 5 MiB nicht bereits an einer kleineren PHP-Grenze scheitert. Die strengeren Webserver- und Endpunktgrenzen bleiben bestehen. Diese PHP-Werte im tatsächlich verwendeten Web-PHP prüfen; ein abweichendes CLI-php.ini beweist die Webkonfiguration nicht.

Im Staging-Ordner die Beispielkonfigurationen genau einmal kopieren, anschließend ausschließlich serverseitig editieren:

```sh
cp config/app.example.php config/app.php
cp config/database.example.php config/database.php
```

`config/app.php`: `env=production`, `debug=false`, korrekte HTTPS-`base_url`, korrektes `asset_url` für Domainwurzel oder Unterordner. `local_free_skins=false`; keine Entwicklungs-Paymentvorschau. OAuth nur mit echten Client-IDs, Geheimnissen und exakten HTTPS-Callback-URLs aktivieren. Mail-Absender und funktionierenden Mailversand konfigurieren. Profilbilder bleiben ohne erfolgreiche Moderation gesperrt. Geheimnisse möglichst über serverseitige Umgebungsvariablen bereitstellen.

`config/database.php`: dedizierte Datenbank, eigenes starkes Passwort, `debug=false`, `utf8mb4`, native vorbereitete PDO-Abfragen. Für laufende Spielanfragen keine globalen MySQL-Adminrechte vergeben. Schemaänderungen benötigen vorübergehend passende DDL-Rechte bzw. einen eigenen Deployment-Zugang. Die lokalen Testfixtures benötigen CREATE/DROP DATABASE und gehören nicht auf die Live-Datenbank.

Nur die tatsächlichen Laufzeitverzeichnisse für den PHP-Benutzer beschreibbar machen. Keine pauschalen 777-Rechte. Logs und private Uploads außerhalb öffentlicher Abrufbarkeit halten. TLS-Zertifikat, HTTPS-Umleitung und Cookie-Flags mit dem echten Hostnamen prüfen. Bei Proxy/CDN das HTTPS-Signal korrekt konfigurieren, keine frei übergebenen Forwarded-Header vertrauen.

## 4. Datenbank sicher aktualisieren

Vor einer Migration Dateien und Datenbank sichern, Wiederherstellung auf Staging ausprobieren und freien Speicher prüfen. Für bestehende Welten eine anonymisierte Kopie des aktuellen Schemas und relevanter Daten als Staging-Basis verwenden. Keine produktiven personenbezogenen Daten in Git übernehmen.

Der tatsächliche vollständige Runner ist:

```sh
php migrations/run.php
```

Er arbeitet alle noch nicht in `migrations.filename` erfassten SQL-Dateien in Dateinamensreihenfolge ab. Nicht nur `tools/migrate-security.php` ausführen: dieser Hilfsbefehl deckt ausschließlich 0103/0104 ab. Es existieren mehrfach vergebene Nummern, u. a. 0050 und 0115; der vollständige Dateiname ist entscheidend. Angewandte Dateien nicht nachträglich umnummerieren.

MySQL-DDL kann implizit committen. Ein fehlgeschlagener Lauf ist deshalb nicht automatisch zurückgerollt. Bei Fehler anhalten, Ausgabe sichern, teilweise angewandtes Schema prüfen und mit gezielter Reparatur oder Wiederherstellung fortfahren. Keine Zeile einfach als „erledigt“ markieren. Ein zweiter erfolgreicher Lauf muss „All migrations already applied“ melden. Zusätzlich `SELECT filename, applied_at FROM migrations ORDER BY filename;` prüfen.

**Neu in dieser Korrekturrunde:** `0118_oauth_identity_verification.sql` muss vor Wiederöffnung der Anmeldung angewandt sein. Sie speichert den Verifizierungszeitpunkt von Provider-Verknüpfungen. Alte ungeprüfte Links benötigen beim nächsten Login bestätigte passende E-Mail-Identitäten; es werden keine Links pauschal gelöscht. Vor und nach dem Upgrade kann `php tools/security-oauth-audit.php` die aggregierten Zahlen ohne E-Mail-/Tokenausgabe prüfen. Der Migrationsrunner verarbeitet CREATE und nachfolgende ALTER jetzt in ihrer tatsächlichen Reihenfolge; SELECT-Ergebnisse dynamischer SQL-Zweige werden vollständig abgeschlossen. `0116_treasure_partial_unlock.sql` bucht bereits bezahlte erste Reliktsterne bei Wiederholung nicht erneut ab.

Erst auf einer Staging-Kopie Anmeldung, Weltwahl, Bau, Ausbildung, Forschung, Sammeln, Kampf, Inventar, Allianz und Adminfunktionen testen. Preise und Beute müssen serverseitig entstehen; Wiederholen eines bestätigten Vorgangs darf nichts doppelt abbuchen oder belohnen.

## 5. Hostinger veröffentlichen

1. Vollständigen Snapshot der bisherigen Dateien inklusive privater Konfiguration und Datenbank außerhalb `public_html` anlegen. Zeitpunkt, alten Commit und Schema-Stand notieren. Wiederherstellungszugang vorab prüfen.
2. Wartungsfenster ankündigen und über Hosting-Zugangsschutz/Wartungsantwort neue Schreibzugriffe sperren. Ein bloßer Hinweis im Frontend sperrt die API nicht. Cronjobs während des Wechsels anhalten und laufende Jobs auslaufen lassen.
3. In hPanel Website → Dashboard → Advanced → Git das richtige Repository, den freigegebenen Branch und das bestehende Zielverzeichnis wählen. Bei bestehenden Installationen die vorhandene Verbindung verwenden. Den freigegebenen Stand ausrollen und die Commit-ID mit dem geprüften Stand vergleichen.
4. Live-Konfiguration erhalten, neue erforderliche Einstellungen bewusst ergänzen. Niemals lokale `database.php` hochladen. Migrationen einmal mit dem richtigen PHP-CLI ausführen. Web-PHP-Cache bei Bedarf über das Hosting kontrolliert leeren.
5. Die entfernten 3D-Dateien müssen auch auf dem Server verschwinden. Bei Git-Deployment passiert dies für zuvor verfolgte Dateien; bei SFTP die konkrete Löschliste verwenden. Nur Dateien innerhalb des bestätigten Projektverzeichnisses löschen. Keine Backup-/Uploadverzeichnisse pauschal synchronisieren oder leeren.
6. Vor Wiederöffnung die Abnahme unten durchführen. Danach Cron aktivieren, Wartung aufheben und Fehler-/Jobprotokolle prüfen.

Hostinger unterstützt derzeit GitHub/GitLab-Anbindung per OAuth sowie manuelles und automatisches Deployment. Menü und Berechtigungen hängen vom Tarif ab; die aktuelle [Hostinger-Anleitung](https://www.hostinger.com/support/1583302-how-to-deploy-a-git-repository-in-hostinger/) ist maßgeblich. Ein Code-Deployment führt die projektspezifischen SQL-Migrationen nicht automatisch aus.

Alternative SFTP: ein sauberes, geprüftes Release exportieren und ausschließlich dessen Dateien übertragen, einschließlich versteckter `.htaccess` und `.user.ini`. Verzeichnisstruktur und Groß-/Kleinschreibung bewahren. Zugangsdaten separat auf dem Server halten. Ein `git archive` enthält nur committete Dateien; unversionierte benötigte Bilder fehlen sonst.

## 6. Hintergrundjobs

Im hPanel absolute Pfade und die dort verfügbare PHP-CLI einsetzen. Bestehende Jobs zuerst inventarisieren, keine doppelten Scheduler anlegen:

| Häufigkeit | Skript | Aufgabe |
|---|---|---|
| Jede Minute | `cron/world_spawn_tick.php` | Weltintervalle, Spawns und UTC-Zeitfenster |
| Jede Minute | `cron/march_tick.php` | Märsche sowie Expeditionen, Dungeons, Rallys, Kongress, Gemeinschaft und Ereignisse |
| Täglich, z. B. 03:00 UTC | `cron/inactivity_check.php` | Inaktive Spieler/Städte ausblenden |

Ausgaben in geschützte Logs schreiben und auf Fehler/überlange Laufzeiten überwachen. Falls `flock` verfügbar ist, Überschneidungen je Job mit einer privaten Lockdatei verhindern. Separate `dungeon_tick.php`/`expedition_tick.php` nicht zusätzlich ohne Prüfung einplanen: der Marschjob ruft diese Systeme bereits auf. Welt-Seedskripte sind keine regelmäßig wiederholten Releasebefehle.

Ersten Superadmin in einer vertrauenswürdigen Terminalsitzung anlegen:

```bash
php bin/create_admin.php AlphaAdmin superadmin --must-change
```

Das Werkzeug fragt das Passwort zweimal verdeckt ab (Windows-Konsole oder Linux/macOS-Terminal). Passwörter benötigen 14–200 UTF-8-Bytes; das Passwort steht weder im Befehl noch in der Ausgabe. Alte Aufrufe mit Passwortargument werden abgewiesen. Ohne Terminal bricht das Werkzeug ab, statt das Passwort sichtbar einzulesen. Beim ersten Login muss das Startpasswort geändert werden. Standardrolle von `bin/create_admin.php` ist `moderator`; der alte Einstieg `cron/create_admin.php` verwendet dieselbe sichere Eingabe, behält aber seine Standardrolle `superadmin`.

Automatisierungen verwenden ausdrücklich `--password-stdin` und übergeben genau eine Passwortzeile per privater Pipe aus ihrem Secret-Store; alternativ unter Linux eine außerhalb des Webroots liegende, nur für den Betreiber lesbare Secret-Datei:

```bash
php bin/create_admin.php AlphaAdmin superadmin --must-change --password-stdin < /run/secrets/conquer_initial_admin
```

Der Inhalt wird weder gekürzt noch getrimmt; eine abschließende LF- oder CRLF-Zeilenendung ist erlaubt. Keine Klartextpasswörter in `echo`-/`printf`-Befehle, Shellvariablen-Zuweisungen, Prozessargumente oder CI-Logs schreiben. Den Pipe-Produzenten bzw. Secret-Store nicht mit Debugausgabe betreiben. Weitere Admins über die Verwaltung mit minimaler Rolle. Alpha-Schlüssel über die Verwaltung oder `bin/create_alpha_key.php` erzeugen und vertraulich verteilen.

## 7. Verbindliche Live-Abnahme

Mit getrennten Testkonten in derselben und einer anderen Welt prüfen:

- Ohne Sitzung liefern geschützte API-Lesezugriffe 401; schreibende Zugriffe ohne CSRF werden abgewiesen. Keine Spielaktion darf trotzdem ausgeführt werden. Spieler dürfen keine Admin-Endpunkte verwenden.
- Fremde Objekt-/Spieler-/Berichts-IDs dürfen keine privaten Daten oder Änderungen ermöglichen. Ratenbegrenzung, zu große Anfragen und ungültige JSON-Daten kontrolliert auf Staging prüfen.
- `/.git/config`, `/config/database.php`, `/data/monsters.json`, `/src/`, `/tools/`, `/tests/`, `/outputs/`, `/preview/` liefern Sperre oder 404 und keinen Inhalt. Für Unterordnerinstallation denselben Test mit Präfix wiederholen.
- HTTPS, HSTS, sichere HttpOnly-Sitzungscookies, CSP, `nosniff` und `Cache-Control: private, no-store` der privaten Antworten prüfen. Statische Bilder/CSS dürfen gecacht werden; API-/Kontodaten nicht.
- Anmeldung, Abmeldung, Reset-Mail, Alpha-Zugang, Sessionablauf und Browser-Zurück testen. Keine alte API-Antwort nach Kontowechsel aus Cache anzeigen.
- Je ein vollständiger Bau-, Ausbildungs-, Forschungs-, Heilungs-, Sammel- und Kampfablauf; Belohnung einmalig abholen. Cron auch ohne geöffnete Spielseite beobachten.
- 320/390 px Hochformat, 844×390 Querformat und Desktop: Knöpfe erreichbar, keine waagerechten Überläufe, lesbare Zahlen, richtige Bilder, keine fehlenden Assets oder Browserfehler. Echte Android-/iOS-Geräte ergänzen die Browseremulation.
- Alte 3D-Routen sind nicht mehr spielbar; normale Stadt und Welt funktionieren. Alte offene Tabs und Service-Worker-Updates kontrolliert prüfen.

Abnahmezeit, Commit-ID, Testkonto und Ergebnis dokumentieren. Keine echte Zahlung aktivieren, solange Anbieter, Webhookprüfung, Wiederholungsschutz und Rückerstattungsablauf nicht freigegeben sind.

## 8. Rückfall bei Problemen

Wartung wieder aktivieren und Cron stoppen. Bei reinem kompatiblem Codefehler den vorherigen freigegebenen Commit gezielt erneut deployen; keine lokale Konfiguration überschreiben. Bei inkompatibler Schemaänderung Code **und** zur Sicherung gehörende Datenbank gemeinsam wiederherstellen. Zwischenzeitliche Spieleraktionen würden dadurch verloren gehen: Wiederöffnung erst nach erfolgreicher Abnahme. Deshalb Migration und Smoke-Test im gesperrten Wartungsfenster durchführen. Nach erfolgreicher Wiederherstellung dieselben Basisprüfungen wiederholen, dann Jobs und Zugriff freigeben.

## 9. Danach Android und iOS mit derselben Web-Codebasis

Die gemalte 2D-Oberfläche bleibt gemeinsamer Client. PHP, MySQL, Spielregeln, Zeitberechnung und Geheimnisse bleiben auf dem Server. Die vorhandene PHP-gerenderte Seite kann nicht als PHP-Datei in Capacitor ausgeführt werden. Vor dem Verpacken einen statischen App-Einstieg mit derselben UI und einem authentifizierten Bootstrap-/Sitzungsendpunkt schaffen. API-Basis zentral konfigurieren; relative `/api`-Aufrufe müssen im nativen Client auf das HTTPS-Backend zeigen.

| Schritt | Umsetzung | Abnahme |
|---|---|---|
| M1 – Web-Alpha stabil | Offene Launchfehler schließen; API-Verträge und sichere Wiederholungen festhalten | Grüner Regressionstest und echte Geräteprüfung |
| M2 – App-Einstieg | Statische HTML-Hülle, geteilte Module/Assets, schmale Plattform-Schnittstelle | Stadt/Welt/Anmeldung nutzen dieselben Spielmodule |
| M3 – Sitzung | Bewusste Wahl Cookie-Sitzung mit erlaubten WebView-Ursprüngen oder widerrufbare App-Tokens; keine Wildcard-CORS mit Credentials; CSRF weiterhin korrekt | Login, Ablauf, Logout, Reset, erneutes Öffnen auf beiden Geräten |
| M4 – Capacitor-Prototyp | Native Projekte erzeugen, kleine Assetauswahl bündeln, API ausschließlich HTTPS | Kompletter Bau-/Trainingsablauf und Wiederaufnahme nach Netzverlust |
| M5 – Gerätefunktionen | Zurück-Taste, Safe Areas, Tastatur, App-Links, Push, externe Browseranmeldung hinter Adapter | Keine Doppelausführung bei Wechsel/Resume |
| M6 – Distribution | Signierung, Store-Assets, Datenschutzangaben, Kontolöschung, TestFlight/Play-Testkanal | Freigegebene Builds mit echten Konten und Geräten |

Capacitor 8 verlangt laut aktuell geöffneter Dokumentation Node 22+, für iOS macOS mit Xcode 26+, für Android Android Studio und Android SDK. Vor tatsächlicher Paketierung die dann gültigen Anforderungen erneut prüfen. [Offizielle Einrichtung](https://capacitorjs.com/docs/getting-started/environment-setup)

Erst nach M1–M3 im vorgesehenen App-Unterprojekt ausführen; `www/` muss die statische gebaute Oberfläche mit `index.html` enthalten:

```sh
npm init -y
npm install @capacitor/core @capacitor/android @capacitor/ios
npm install -D @capacitor/cli
npx cap init
npx cap add android
npx cap add ios
npx cap sync
npx cap open android
npx cap open ios
```

App-ID und Anzeigename vor `cap init` verbindlich festlegen; `webDir` auf den tatsächlichen Ausgabeordner setzen. iOS-Befehle auf dem Mac ausführen. Versionen und Lockdatei festhalten. [Installation](https://capacitorjs.com/docs/getting-started) und [Konfiguration](https://capacitorjs.com/docs/config).

`server.url` ist für Live-Reload vorgesehen und sollte nicht als dauerhafte Store-Verpackung der PHP-Website dienen. Ein vollständig eingebetteter Client benötigt ausdrücklich getestete Ursprünge, Sitzungen und Assetpfade. Push und Käufe erst nach dem Kernprototyp ergänzen. Bei digitalen Käufen die zum Einreichungszeitpunkt gültigen Apple-/Google-Regeln direkt prüfen; diese Prüfung hat keine Store- oder Zahlungsfreigabe durchgeführt.


## 10. Wiederholbarer lokaler Abnahmelauf

`tests/run_alpha.py` enthält die geprüfte Auswahl an Backend-, vollständigen App- und isolierten Browserprüfungen. Sie werden seriell ausgeführt. Nicht beliebig alle historischen PHP-Tests per Dateisuche starten: Einige alte Hilfsskripte verwenden noch die konfigurierte Datenbank direkt. Die hier zugelassenen Tests benutzen synthetische Wegwerfdatenbanken bzw. reine Browserfixtures.

Voraussetzungen: Python 3, PHP mit PDO-MySQL, lokale MySQL-/MariaDB-Instanz, Node.js, installiertes Playwright und Chrome. `config/database.php` muss auf eine lokale Entwicklungsdatenbank zeigen; der Testbenutzer benötigt CREATE/DROP-Rechte für die erzeugten Testdatenbanken. Niemals Live-Zugangsdaten benutzen. Die vorhandene Entwicklungsdatenbank wird als Schemaquelle gelesen; die Tests schreiben ihre Spielzustände in zufällig benannte Testdatenbanken. Keine parallele zweite Datenbankprüfung starten: MySQL-Namenssperren sind serverweit.

Beispiel für diesen Rechner (Playwright-Pfad gegebenenfalls auf die eigene Installation ändern):

```powershell
Set-Location C:\xampp\htdocs\conquer
$env:PHP_BINARY = 'C:/xampp/php/php.exe'
$env:NODE_PATH = 'C:/Users/svenm/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules'
$env:PLAYWRIGHT_MODULE = "$env:NODE_PATH/playwright"
$env:BROWSER_EXECUTABLE_PATH = 'C:/Program Files/Google/Chrome/Application/chrome.exe'
$env:PLAYWRIGHT_CHANNEL = 'chrome'
python tests/run_alpha.py --group all
```

Gruppen einzeln: `--group backend`, `--group app` oder `--group static`. Gezielte Wiederholung: `--group backend --only oauth_identity security_accounts security_api_matrix migration_lifecycle`. Exitcode 0 bedeutet, dass alle **in diesem Aufruf ausgewählten** Suiten bestanden haben. Ein Teilaufruf ersetzt keine übrigen Tests. Der Ordner `artifacts/alpha-audit-2026-09-26/final/` enthält vollständige Logs sowie einzelne `<suite>.result.json`; die Gruppen-JSON zeigt den jeweils letzten Aufruf, auch wenn das eine Teilmenge war.

`migration_lifecycle.php` führt den echten CLI-Migrationsrunner gegen eine leere Datenbank und einen historischen Stand bis einschließlich 0102 aus, vergleicht beide vollständigen Schemas, prüft wiederholte Ausführung und den Erhalt bestehender Testguthaben. Das ersetzt nicht den Restore eines realen Backups auf Staging oder eine zweite Prüfung auf der genauen Hostinger-Datenbankversion.

Automatisierte Passwortquellen müssen UTF-8 liefern. Windows PowerShell 5.1 verwendet für native Pipes ohne ausdrückliche Einstellung möglicherweise eine andere Kodierung. Linux-Terminalverhalten vor dem ersten Einsatz auf Staging testen.
