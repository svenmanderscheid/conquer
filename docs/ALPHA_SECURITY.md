# API-Schutz für die geschlossene Alpha

Die Anwendung begrenzt Anfragen serverseitig. HTTPS schützt den Transport; auch angemeldete Bots können gültige Anfragen senden. Die neuen Grenzen reduzieren Missbrauch und Serverlast, versprechen aber keine botfreie Alpha.

## Aktivierung

Vor dem Ausrollen Migrationen `0103_security_rate_limits.sql` und `0104_api_receipts_and_activity.sql` anwenden. Für bestehende Installationen führt `php tools/migrate-security.php` ausschließlich diese beiden additiven Migrationen aus und vermerkt sie in der Migrationstabelle. Der normale Migration Runner enthält sie ebenfalls. PHP, Views und JavaScript zusammen ausrollen. Alte geöffnete Clients ohne Vorgangskennung erhalten bei geschützten Aufträgen `400 OPERATION_KEY_REQUIRED` und müssen neu geladen werden. Ohne Limit-Tabelle lehnt die API Anfragen mit `503 SECURITY_UNAVAILABLE` ab; ohne Belegtabelle scheitern geschützte Aufträge vor ihrer Ausführung.

Für Produktion `env=production`, `debug=false`, `log_level=info` einstellen. Die Beispielkonfiguration verwendet diese Vorgaben. Die lokale Entwicklungskonfiguration bleibt lokal nutzbar. Bootstrap unterdrückt Diagnoseausgaben für entfernte Clients auch bei versehentlich kopierter Entwicklungskonfiguration. `.user.ini` schaltet zusätzlich Startfehler-Ausgaben für FastCGI/LiteSpeed ab; die tatsächliche Übernahme beim Hoster prüfen.

## Grenzen

Die atomaren Token-Buckets gelten über PHP-Prozesse hinweg. Ein Bucket erlaubt eine Anfangsspitze bis zur angegebenen Kapazität und füllt sich gleichmäßig über den Zeitraum auf; es handelt sich nicht um ein starres Zeitfenster.

| Bereich | Kapazität / Auffüllzeit | Bindung |
| --- | --- | --- |
| Gesamte API | 2.400 / Minute | IP, für gemeinsam genutzte Netze |
| Nicht angemeldete Anfragen | 60 / Minute | IP |
| Angemeldete API | 120 / Minute, `rate_limit_per_minute` | Konto, über alle Sitzungen und Welten |
| Schreibaktionen | 10 / 10 Sekunden | Konto |
| Kartensuche | 15 / Minute | Konto |
| Anmeldung | 5 / Minute plus 10 / 15 Minuten | IP plus normalisierter Kontoname |
| Admin-Anmeldung | 5 / Minute plus 5 / 15 Minuten | IP plus normalisierter Kontoname |
| Wiederherstellung | 10 / 15 Minuten | IP plus normalisierter Kontoname |

Bestehende zusätzliche Login-/Wiederherstellungslimits bleiben wirksam. Erschöpfte Budgets antworten mit HTTP 429 und `Retry-After`. Schreibaktionen werden nicht automatisch erneut ausgeführt. Lesende Abfragen können bei erschöpftem Schreibbudget weiterlaufen, solange ihr Gesamtbudget frei ist. Neue Tabs oder Sitzungen setzen Kontolimits nicht zurück. Weiterleitungsheader wie `X-Forwarded-For` werden nicht vertraut; ein Reverse Proxy muss `REMOTE_ADDR` vertrauenswürdig normalisieren.

Vor allen Spieler-Schreibaktionen prüft die zentrale API-Schicht das sitzungsgebundene CSRF-Token. Signierte Zahlungsbenachrichtigungen behalten ihren eigenen Authentifizierungsweg; IP- und Größenlimits gelten auch dort. API-Antworten sind `private, no-store`. Anfragekörper sind auf 64 KiB begrenzt; Apache/LiteSpeed begrenzt auch Übertragungen ohne Content-Length. Bestehende strengere Handlergrenzen bleiben bestehen.

Interne Verzeichnisse einschließlich Testwerkzeugen, Vorschauartefakten und Templates sind auch bei Installation unter `/conquer` durch relative Rewrite-Regeln gesperrt. HTTPS-Weiterleitung erfolgt vor dem internen Routing. Der HSTS-Header verwendet die tatsächliche TLS-Verbindung und wird zusätzlich von PHP gesetzt; unbekannte Proxy-Header können ihn nicht aktivieren.

## Beobachtung und Grenzen

`security_rate_limits` enthält SHA-256-Schlüssel, den Zeitpunkt der letzten Warnung und die Zahl abgewiesener Anfragen. Weder Cookies noch Passwörter noch Anfragekörper werden dort gespeichert. Abgelaufene Einträge werden begrenzt und stichprobenartig entfernt. `logs/app.log` enthält bei wiederholten Limitüberschreitungen höchstens alle fünf Minuten pro Bucket eine `SECURITY rate_limit`-Warnung. Konto-Schlüssel können für eine Untersuchung als `sha256('api.player:' + Spieler-ID)` bzw. `sha256('api.write:' + Spieler-ID)` zugeordnet werden. Dies ist eine Grundlage für manuelle Prüfung, keine automatische Bot-Erkennung oder Benachrichtigung. Limitüberschreitungen führen nicht zu permanenten Sperren.

Alpha-Einladungen bleiben die Registrierungshürde. CAPTCHA/Turnstile ist nicht eingerichtet und braucht eine gesonderte Integration samt Betreiber-Schlüsseln. Langsame Bots unterhalb der Grenzwerte sowie verteilte Konten werden hierdurch nicht zuverlässig erkannt.

## Wiederholungsschutz für Armeeaufträge

Marschstarts (Monster, Stadt-PvP, Späher, Sammeln, besetzte Felder, Charms und Verstärkungen), Stadt-/Monster-Rally-Starts und Rally-Beitritte benötigen `operation_key`. Der alternative Endpunkt `defense/action` schützt Spähen, Verstärken, Beförderungsstart und Mauerreparatur ebenfalls. Andere Aktionen behalten ihre bestehenden Regeln; dies ist kein pauschaler Wiederholungsschutz für sämtliche Endpunkte.

Spieländerung und Erfolgsbeleg werden in derselben Datenbanktransaktion gespeichert. Verschachtelte Diensttransaktionen verwenden Savepoints; Spieler- und Kampfsperren bleiben bis zum Abschluss gehalten. Ein erneuter identischer Auftrag mit derselben Kennung liefert das ursprüngliche Ergebnis und `X-Operation-Replayed: 1`, ohne erneut Truppen oder Kosten abzuziehen. Gleiche Kennung mit anderem Inhalt, Endpunkt oder anderer Welt wird mit `409 OPERATION_CONFLICT` abgewiesen. Fehlgeschlagene Transaktionen erzeugen keinen Erfolgsbeleg.

Der Browser sichert den exakten Auftrag vor dem Senden im Sitzungsspeicher, getrennt nach Konto und Welt. Nach verlorener Antwort oder Neuladen erlaubt „Auftrag sicher fortsetzen“ einen bewussten Wiederholungsversuch: Bereits abgeschlossene Aufträge werden bestätigt; zuvor nicht ausgeführte werden gestartet. Kein automatischer Hintergrund-Neuversuch. Ein ungelöster Auftrag blockiert andere geschützte Aufträge in diesem Tab. Neuanmeldung bzw. erneute Freigabe einer pausierten Welt kann vor der Wiederaufnahme erforderlich sein. Sitzungsspeicher ist keine geräteübergreifende Wiederherstellung; beim Schließen des Tabs kann er verloren gehen.

`api_operation_receipts` speichert Konto, Welt, Kennung, Inhaltshash und Erfolgsantwort, keine Cookies oder Passwörter. Belege werden absichtlich nicht zeitgesteuert gelöscht: Sonst könnte eine alte Kennung erneut ausgeführt werden. Vor einer späteren Archivierung muss deren Eindeutigkeit weiterhin garantiert bleiben. Wiederholungsschutz verhindert doppelte Ausführung derselben Kennung, nicht Bots, die neue gültige Aufträge erzeugen.

## Aktivitätshinweise zur manuellen Prüfung

Nur neu bestätigte geschützte Aufträge zählen; Abfragen, fehlgeschlagene Aktionen und Wiederholungen zählen nicht. `security_activity` enthält Viertelstunden-Zähler je Konto/Welt. Prüfhinweise entstehen bei mindestens 128 Aufträgen mit Aktivität in allen letzten 32 Viertelstunden oder mindestens 500 Aufträgen in den letzten vier Viertelstunden-Blöcken (kein gleitendes exaktes Stundenfenster). Die Schwellen sind erste Alpha-Heuristiken, kein Bot-Nachweis.

`php tools/security-report.php` zeigt bis zu 200 Hinweise der letzten sieben Tage. Zusätzlich erscheint `SECURITY review_required` im Serverlog. Pro Konto/Welt/Tag/Grund wird ein Hinweis zusammengeführt; es gibt keine automatische Sperre oder externe Benachrichtigung. Zähler älter als sieben Tage werden stichprobenartig bereinigt, Prüfhinweise bleiben erhalten. Ausfälle dieser Auswertung ändern einen bereits bestätigten Spielauftrag nicht nachträglich in einen Fehler. Betreiber müssen Hinweise regelmäßig prüfen und Schwellen anhand echter Alpha-Nutzung bewerten.

## Prüfung

`php tests/security_guard.php` verwendet eine separate, anschließend entfernte Testdatenbank. Es prüft parallele PHP-Prozesse, CSRF vor dem tatsächlichen Front Controller, Authentifizierung, kontoweite Limits, unabhängige Leseabfragen, Retry-After, Größenbegrenzung, Logout, Cache-Schutz und fehlende Limit-Tabelle.

`php tests/army_receipts.php` prüft Wiederholungen, geänderte Nutzdaten, tatsächliche Truppenbestände, den alternativen Verteidigungs-Endpunkt, vollständigen Rollback bei fehlgeschlagener Belegspeicherung und Aktivitätshinweise ohne automatische Sperre. `node tests/army_receipts_app.cjs` simuliert eine verlorene Antwort nach einem tatsächlich gestarteten Spähauftrag, lädt die App neu und prüft den exakten Wiederholungsversuch einschließlich Originalbeleg auf Desktop, schmalem Handy und im Querformat. Beide verwenden ausschließlich wegwerfbare Testdatenbanken.

Nach Deployment auf dem vorgesehenen Alpha-Host HTTPS-Weiterleitung, HSTS, PHP-Konfiguration, Migration und die normale mobile Spielrunde prüfen. Lokale Tests bestätigen keine Hosting-Konfiguration. Die Limits während der betreuten Alpha anhand tatsächlicher Abfragen beobachten und bei legitimen Überschreitungen gezielt anpassen.

Lokal bestanden: Sicherheitsprüfung einschließlich 12 paralleler PHP-Prozesse; Heilungs- und Beschleuniger-Regressionen; `tests/training_http_app.cjs` im Touchformat 390 × 844 auf einem HTTP-Test-Origin, einschließlich Ausbildung, Heilung und Wiederaufnahme nach verlorener Antwort. Die Browser-Fixture verwendet ein eigenes Sitzungsverzeichnis innerhalb ihrer temporären Installation.
