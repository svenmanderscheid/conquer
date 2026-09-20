# Installation und Sprachen

Conquer hat ein öffentliches Web-App-Manifest, ein eigenes Symbol, eine Installationshilfe und eine Offline-Seite. Die Spielhandlungen benötigen weiterhin eine Verbindung zum Server. Die aktive Sprachwahl bietet Deutsch, Französisch und Englisch (`de`, `fr`, `en`). Regionale Sprachangaben wie `fr-LU` oder `en-US` werden auf ihre Basissprache normalisiert.

## Tatsächlich übersetzte Inhalte

Die drei gleich aufgebauten aktiven Kataloge in `data/i18n/` enthalten jeweils **607 explizite Schlüssel**. Die Browserübersetzung verarbeitet ausschließlich bekannte, redaktionell geschriebene Texte:

- Navigation, Spielmenü, Bereichstitel, bekannte Schaltflächen und Formularbeschriftungen.
- Welt-/Allianzchat-Bedienelemente, Briefe und Geschenkeüberschrift, Allianz-Hilfe, Forschung, Ränge, Diplomatie und Ressourcenlieferungen.
- Konto-/Wiederherstellungsaktionen, Meisterschaft, Ereignisaktionen und Bedienelemente der Weltenauswahl.
- Anmeldung und ausgewählte Backoffice-Navigation, Formularfelder und Tabellenüberschriften.
- Explizit mit `data-i18n` versehene Texte, die Hauptbereiche und Dialoge der Spiel-App, Start-/Anmeldeseite, Kontowiederherstellung sowie die Offline-Seite.
- Häufige dynamische Muster wie Stufe, Truppenanzahl, Welt, Koordinaten und Auswahlmengen mit benannten Platzhaltern.

Längere Fachtexte und neue Kataloginhalte müssen weiterhin beim Hinzufügen in allen drei Sprachdateien gepflegt werden. Die gemeinsame Laufzeitübersetzung erfasst keine Spielernamen, Nachrichten oder anderen Nutzereingaben. Zahlen und kurze Zeitangaben verwenden die aktive Sprache; ältere, vollständig serverseitig zusammengesetzte Datums- und Laufzeitmeldungen werden schrittweise auf semantische Werte umgestellt.

Spielernamen, Allianz-/Weltnamen, private und öffentliche Nachrichten, Geschenktexte, Profile, Tabellenwerte und Eingabewerte bleiben unverändert. Ein Spieler darf beispielsweise „Forschung“ heißen: Sein Name wird auch bei französischer Oberfläche nicht zu „Recherche“. Neue Anzeigen mit Spielerinhalten müssen `data-user-content`, `translate="no"` oder `data-i18n-ignore` verwenden, wenn sie innerhalb eines übersetzten Bereichs liegen.

## Einbindung und weitere Übersetzungen

Vor `assets/js/localization.js` auf PHP-Seiten einmal den öffentlichen Katalog einbetten:

```php
<script><?= \Conquer\Game\Locale::bootstrap() ?></script>
<script src="<?= htmlspecialchars(APP_BASE, ENT_QUOTES) ?>/assets/js/localization.js?v=1" defer></script>
<link rel="stylesheet" href="<?= htmlspecialchars(APP_BASE, ENT_QUOTES) ?>/assets/css/localization.css?v=1">
```

Die JSON-Dateien bleiben im nicht öffentlich zugänglichen `data/`-Verzeichnis. Der Browser lädt diese Dateien nicht direkt und erhält keine Kontodaten im Sprachbootstrap. Die Auswahl wird lokal und in einem validierten `SameSite=Lax`-Cookie gespeichert. Änderungen werden auch an gleichzeitige Fenster und eingebettete Ansichten derselben Herkunft weitergegeben.

Ein Platzhalter `<div data-locale-controls></div>` erzeugt die Sprach- und Installationsbedienung. `data-locale-compact="true"` reduziert die Darstellung; `data-locale-install="false"` blendet die Installationshilfe dort aus. Die aktuellen Konto-/Einstellungsbereiche erhalten die Bedienung auch automatisch, sobald sie angezeigt werden.

Neue Autoreninhalte sollen semantisch markiert werden:

```html
<span data-i18n="mail.send">Brief senden</span>
<input data-i18n-attrs="placeholder:login.placeholder_name;aria-label:login.username">
<span data-user-content>Vom Spieler gewählter Name</span>
```

`data-i18n` gehört auf ein reines Textelement, da dessen Textinhalt ersetzt wird. Für variable Werte stehen `data-i18n-params='{"count":3}'`, `ConquerLocale.t(key, parameters)` sowie PHP `Locale::t()` und HTML-sicheres `Locale::html()` zur Verfügung. Serverseitig übersetzte Elemente benötigen ebenfalls `data-i18n`, wenn sie unmittelbar auf einen späteren Sprachwechsel reagieren sollen. Neue Schlüssel in allen drei aktiven Dateien ergänzen; unbekannte Schlüssel erhalten die deutsche Rückfallebene. Katalogschlüssel mit Platzhaltern wie `Stufe {level}` werden auch auf dynamisch erzeugte, vollständig redaktionelle Beschriftungen angewendet.

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
- Lädt nur eine ausdrückliche Liste kleiner öffentlicher Dateien in den Cache, immer ohne Cookies und ohne übernommene Authentifizierungs-/CSRF-Header.
- Begrenzt den Cache auf 32 Einträge mit jeweils höchstens 512 KiB; große 3D-Assets werden nicht zwischengespeichert.
- Behält die Offline-Seite beim Verdrängen älterer Dateien und trennt Caches verschiedener Installationspfade.

Für Änderungen an der Offline-Vorladung `BUILD` in `service-worker.js` erhöhen. Normale freigegebene statische Dateien werden bei jeder Netzverbindung aktualisiert.

## Prüfungen

- `php tests/localization.php`: Katalogparität, Sprachvalidierung, HTML-sichere Parameter und Bootstrap.
- `node tests/localization_pwa.cjs`: Browserprüfung für Deutsch/Französisch/Englisch, Speicherung, dynamische Beschriftungen und Platzhalter sowie unveränderte Spielernamen/Nachrichten/Eingaben. Prüft außerdem beide Installationspfade, Manifest und PNG-Größen, echte Service-Worker-Caches, Headerbereinigung, Cachegrenzen und eine französische Offline-Seite nach Navigation auf eine verschachtelte Spieladresse.

Der Browsertest nutzt einen eigenen lokalen HTTP-Server und berührt keine echten Spielkonten. Falls Playwright nicht im Projekt installiert ist, kann sein vorhandener Modulpfad über `PLAYWRIGHT_MODULE` angegeben werden.
