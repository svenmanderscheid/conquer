# Installation und Sprachen

Seit 30. September 2026 ist **Englisch die Haupt- und Standardsprache** von Union of Kingdoms. Ohne ausdrückliche Sprachwahl starten Server und Browser auf `en`, unabhängig von der Browsersprache. Bestehende Sprachwahlen bleiben erhalten. Englisch steht zuerst in der Auswahl und dient als Rückfallebene für fehlende Schlüssel; Installation, Offline-Seite und Konto-E-Mails verwenden ebenfalls Englisch. Neue Spieltexte müssen mit einer vollständigen englischen Fassung ausgeliefert werden. Deutsch und Französisch bleiben wählbar; neu ergänzte Texte ohne französische Redaktion verwenden vorläufig die englische Fassung.

Conquer hat ein öffentliches Web-App-Manifest, ein eigenes Symbol, eine Installationshilfe und eine Offline-Seite. Die Spielhandlungen benötigen weiterhin eine Verbindung zum Server. Die aktive Sprachwahl bietet Deutsch, Französisch und Englisch (`de`, `fr`, `en`). Regionale Sprachangaben wie `fr-LU` oder `en-US` werden auf ihre Basissprache normalisiert.

## Tatsächlich übersetzte Inhalte

Die drei gleich aufgebauten aktiven Kataloge in `data/i18n/` werden gemeinsam gepflegt; `php tests/localization.php` prüft ihre aktuelle Schlüsselzahl und Parität. Die Browserübersetzung verarbeitet ausschließlich bekannte, redaktionell geschriebene Texte:

- Navigation, Spielmenü, Bereichstitel, bekannte Schaltflächen und Formularbeschriftungen.
- Welt-/Allianzchat-Bedienelemente, Briefe und Geschenkeüberschrift, Allianz-Hilfe, Forschung, Ränge, Diplomatie und Ressourcenlieferungen.
- Konto-/Wiederherstellungsaktionen, Meisterschaft, Ereignisaktionen und Bedienelemente der Weltenauswahl.
- Anmeldung und ausgewählte Backoffice-Navigation, Formularfelder und Tabellenüberschriften.
- Explizit mit `data-i18n` versehene Texte, die Hauptbereiche und Dialoge der Spiel-App, Start-/Anmeldeseite, Kontowiederherstellung sowie die Offline-Seite.
- Häufige dynamische Muster wie Stufe, Truppenanzahl, Welt, Koordinaten und Auswahlmengen mit benannten Platzhaltern.

Die Erweiterung umfasst auch den Anfangsguide, Katalognamen und Beschreibungen für Gegenstände, Relikte, Forschung, Truppen, Talente, Dungeons, Kartenobjekte und Skins sowie Serverfehler und die öffentliche Startseite. Die neuen Texte wurden als maschineller Erstentwurf erstellt und in den geprüften Ansichten sowie bei zentralen Spielbegriffen nachbearbeitet; eine vollständige muttersprachliche Redaktion aller Texte steht aus. Neue Fachtexte müssen weiterhin beim Hinzufügen in allen drei Sprachdateien gepflegt werden. Die gemeinsame Laufzeitübersetzung erfasst keine Spielernamen, Nachrichten oder anderen Nutzereingaben. Zahlen und kurze Zeitangaben verwenden die aktive Sprache. Sämtliche Übersetzungen liegen lokal; das Spiel ruft keinen Übersetzungsdienst auf.

`copy.*` sind stabile, aus dem Quelltext abgeleitete Schlüssel für übernommene redaktionelle Texte. Die Laufzeit erkennt deutsche, englische und französische Quelltexte und bewahrt beim Wechsel die ursprünglichen Textknoten. Dynamische Muster werden nach ihrer Genauigkeit sortiert und zwischengespeichert; reine Platzhalter dürfen keine allgemeinen Übersetzungsregeln bilden. Platzhalterwerte bleiben unverändert. Zusammengesetzte Truppen-, Forschungs- und Reliktnamen werden deshalb bereits in ihren Präsentationsfunktionen mit `ConquerLocale.text()` übersetzt. Serverfehler verwenden `Locale::text()`; niemals einen gesamten API-Datensatz oder Spielernachrichten an diese Funktion übergeben.

Die öffentliche Startseite besitzt eine kompakte Sprachwahl und überträgt die ausgewählte Sprache mit der Wartelistenanmeldung. Kontowiederherstellung und Passwortzurücksetzung verwenden ebenfalls das gemeinsame System. Die Spiel-App lädt nach einem ausdrücklichen Sprachwechsel neu, damit auch vorher initialisierte Beschriftungen und Zahlenformate die Sprache übernehmen; dies löst selbst keine Spielaktion aus. Geschützte Tabellenwerte bleiben unverändert, ausdrücklich mit `data-i18n` markierte redaktionelle Tabellenbeschriftungen werden übersetzt.

Spielernamen, Allianz-/Weltnamen, private und öffentliche Nachrichten, Geschenktexte, Profile, Tabellenwerte und Eingabewerte bleiben unverändert. Ein Spieler darf beispielsweise „Forschung“ heißen: Sein Name wird auch bei französischer Oberfläche nicht zu „Recherche“. Neue Anzeigen mit Spielerinhalten müssen `data-user-content`, `translate="no"` oder `data-i18n-ignore` verwenden, wenn sie innerhalb eines übersetzten Bereichs liegen.

## Einbindung und weitere Übersetzungen

Vor `assets/js/localization.js` auf PHP-Seiten einmal den kleinen Bootstrap und die geordneten öffentlichen Sprachdateien einbinden:

```php
<?= \Conquer\Game\Locale::bootstrapScripts($landingCspNonce ?? null) ?>
<script src="<?= htmlspecialchars(APP_BASE, ENT_QUOTES) ?>/assets/js/localization.js?v=1" defer></script>
<link rel="stylesheet" href="<?= htmlspecialchars(APP_BASE, ENT_QUOTES) ?>/assets/css/localization.css?v=1">
```

Die JSON-Quelldateien bleiben im nicht öffentlich zugänglichen `data/`-Verzeichnis. Die öffentliche Route `/locale-assets/{en|de|fr|sources-de}.{Inhaltshash}.{json|js}` liefert ausschließlich diese redaktionellen Übersetzungen. Sie wird vor Datenbank- und Sitzungsinitialisierung behandelt. Strikte Namen-, Versions- und Formatauswahl, `nosniff`, passende Inhaltstypen und `GET`/`HEAD` begrenzen die Route; veraltete oder unbekannte Versionen erhalten eine nicht cachebare 404-Antwort. Kontodaten, Cookies und Spielstände gehen nicht in die Antwort ein.

Die Seitenausgabe enthält nur Sprache und URLs. Geordnete `defer`-Skripte laden zuerst Englisch, dann die aktuelle Cookie-Sprache und bei Bedarf die deutsche Quelltextzuordnung für ältere redaktionelle Beschriftungen. Die Zuordnung enthält nur deutsche Werte, die von Englisch abweichen; ein bereits geladenes vollständiges deutsches Zielverzeichnis genügt ebenfalls. Alle Zielkataloge bleiben vollständig. Andere Zielsprachen lädt `ConquerLocale` bei einer gespeicherten oder ausdrücklichen Sprachwahl über `catalogUrls` als JSON. `ConquerLocale.ready` ist vor dem erstmaligen Aufbau sprachabhängiger App-Beschriftungen abzuwarten; ein Ladefehler darf die englische Rückfallebene und den Appstart nicht blockieren.

Die öffentlichen Dateien verwenden Inhaltsversionen, `ETag` und `public, max-age=31536000, immutable`; normale Browser-HTTP-Caches können sie über Seitenwechsel hinweg wiederverwenden. Der kleine Inline-Bootstrap unterstützt die bestehende CSP-Nonce, und URLs verwenden `APP_BASE` auch bei verschachtelten Installationen. Authentifizierte Seiten, Anmeldung mit CSRF-Token und API-Antworten bleiben privat und nicht cachebar. Die Auswahl wird lokal und in einem validierten `SameSite=Lax`-Cookie gespeichert. Änderungen werden auch an gleichzeitige Fenster und eingebettete Ansichten derselben Herkunft weitergegeben.

Ein Platzhalter `<div data-locale-controls></div>` erzeugt die Sprach- und Installationsbedienung. `data-locale-compact="true"` reduziert die Darstellung; `data-locale-install="false"` blendet die Installationshilfe dort aus. Die aktuellen Konto-/Einstellungsbereiche erhalten die Bedienung auch automatisch, sobald sie angezeigt werden.

Neue Autoreninhalte sollen semantisch markiert werden:

```html
<span data-i18n="mail.send">Brief senden</span>
<input data-i18n-attrs="placeholder:login.placeholder_name;aria-label:login.username">
<span data-user-content>Vom Spieler gewählter Name</span>
```

`data-i18n` gehört auf ein reines Textelement, da dessen Textinhalt ersetzt wird. Für variable Werte stehen `data-i18n-params='{"count":3}'`, `ConquerLocale.t(key, parameters)` sowie PHP `Locale::t()` und HTML-sicheres `Locale::html()` zur Verfügung. Serverseitig übersetzte Elemente benötigen ebenfalls `data-i18n`, wenn sie unmittelbar auf einen späteren Sprachwechsel reagieren sollen. Neue Schlüssel in allen drei aktiven Dateien ergänzen; unbekannte Schlüssel erhalten die englische Rückfallebene. Katalogschlüssel mit Platzhaltern wie `Stufe {level}` werden auch auf dynamisch erzeugte, vollständig redaktionelle Beschriftungen angewendet.

## PWA-Verhalten

`manifest.php` ermittelt den Installationspfad aus `APP_BASE` beziehungsweise `SCRIPT_NAME`. Sowohl eine Installation im Domain-Stamm als auch `/conquer/` funktionieren. Symbole liegen unter `assets/icons/conquer-192.png`, `conquer-512.png` und `conquer.svg`.

```php
<link rel="manifest" href="<?= htmlspecialchars(APP_BASE, ENT_QUOTES) ?>/manifest.php">
<link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars(APP_BASE, ENT_QUOTES) ?>/assets/icons/conquer.svg">
<link rel="apple-touch-icon" href="<?= htmlspecialchars(APP_BASE, ENT_QUOTES) ?>/assets/icons/conquer-192.png">
```

Die Installation benötigt einen sicheren Browserkontext: HTTPS oder `localhost`. Unterstützt der Browser die Installationsaufforderung, öffnet die Schaltfläche diese. Ansonsten erklärt die Oberfläche den Weg über das Browsermenü. Die Website veröffentlicht oder verändert keine Browser-/Betriebssystemeinstellungen selbständig.

Der Service Worker:

- Ruft Spielnavigation zuerst über das Netz ab und zeigt bei Verbindungsfehlern ausschließlich die öffentliche Offline-Seite.
- Speichert **keine** Spiel-/Kontoseiten, `/api`, `/admin`, `/auth`, POST-Anfragen oder Antworten mit `private`/`no-store`.
- Lädt nur eine ausdrückliche Liste kleiner öffentlicher Dateien sowie die streng benannten versionierten Sprachdateien in den Cache, immer ohne Cookies und ohne übernommene Authentifizierungs-/CSRF-Header.
- Begrenzt den Cache auf 32 Einträge mit jeweils höchstens 512 KiB; große 3D-Assets werden nicht zwischengespeichert.
- Behält die Offline-Seite beim Verdrängen älterer Dateien und trennt Caches verschiedener Installationspfade.

Für Änderungen an der Offline-Vorladung `BUILD` in `service-worker.js` erhöhen. Normale freigegebene statische Dateien werden bei jeder Netzverbindung aktualisiert. Versionierte Sprachdateien werden zuerst aus dem Cache gelesen; jede Inhaltsänderung erzeugt eine neue URL. Dateien über 512 KiB verbleiben außerhalb von CacheStorage und nutzen weiterhin den normalen Browser-HTTP-Cache. Die Grenzen von 32 Einträgen und 512 KiB wurden dafür nicht erhöht.

## Prüfungen

- `php tests/localization.php`: Katalogparität, Sprachvalidierung, HTML-sichere Parameter und Bootstrap.
- `php tests/locale_delivery.php --evidence` und `php tests/locale_delivery.php --base=/conquer`: vollständige öffentliche Kataloge, Quelltextzuordnung, CSP-sichere Reihenfolge, EN-Standard, Cookies, Versionen, `HEAD`/`ETag`, Größenbeleg. Reine CLI-Prüfung ohne Datenbank; Größenbeleg unter `artifacts/ui-ux-implementation-2026-09-30/locale-delivery/source-bytes.json` bezeichnet unkomprimierte Quelldaten, keine gemessene Ladezeit.
- `node tests/locale_delivery.cjs`: echte Ausführung der Sprachassets, Frontcontroller ohne DB-/Sitzungsbootstrap, Service-Worker-Cachetreffer, öffentliche Anfrageheader und unveränderte Cachegrenzen für Domain-Stamm und verschachtelte Unterordner; kein Browser oder Datenbankstand nötig.
- `node tests/localization_landing.cjs`: Startseite in DE/EN/FR, tatsächliche CSP, Sprachwechsel, Formularsprache, unveränderte Eingaben und vier Bildschirmformate.
- `node tests/localization_app.cjs`: 20 Spielbereiche in EN/FR, Spracheinstellung, Spieleridentität, 40 Kombinationen aus wichtigen Spielansichten und Bildschirmgrößen sowie die gezeichnete Stadt. Benötigt die isolierte Vorschau `php tools/preview-feature-fixture.php --port=18987 --appearance --hud --chat` (oder `LOCALIZATION_URL`).
- `node tests/localization_pwa.cjs`: Browserprüfung für Deutsch/Französisch/Englisch, Speicherung, dynamische Beschriftungen und Platzhalter sowie unveränderte Spielernamen/Nachrichten/Eingaben. Prüft außerdem beide Installationspfade, Manifest und PNG-Größen, echte Service-Worker-Caches, Headerbereinigung, Cachegrenzen und eine französische Offline-Seite nach Navigation auf eine verschachtelte Spieladresse.

Der Browsertest nutzt einen eigenen lokalen HTTP-Server und berührt keine echten Spielkonten. Falls Playwright nicht im Projekt installiert ist, kann sein vorhandener Modulpfad über `PLAYWRIGHT_MODULE` angegeben werden.
