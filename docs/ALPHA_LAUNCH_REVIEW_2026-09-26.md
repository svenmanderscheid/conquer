# Alpha-Abnahme nach den Korrekturen

Stand: 26. September 2026. Die Erstprüfung wurde um Produktkorrekturen und breite Regressionen erweitert. Die frühere rote Liste ist als [historische Erstprüfung](ALPHA_INITIAL_REVIEW_2026-09-26.md) erhalten und beschreibt nicht den aktuellen Stand. Erfolgreiche lokale Tests ersetzen keine Abnahme des Liveservers.

## Ergebnisse und Unterlagen

**Lokale Abnahme: 153 von 153 geprüften Testsuiten bestanden** – 83 Backend-, 33 vollständige App- und 37 weitere Frontend-Suiten. Nach relevanten Korrekturen wurden die betroffenen Suiten wiederholt. Zusätzlich sind 365 PHP- und 193 JavaScript-/CJS-Dateien syntaktisch gültig. Die Menüprüfung umfasst 110 Kombinationen aus 22 Bereichen und fünf Größen sowie 2.345 sichtbare Textproben; Fehlerlisten für Browser, Bilder, Menü-API und Gestaltung sind leer.

Nachweise: `artifacts/alpha-audit-2026-09-26/final/summary.json`, `all-reviewed-results.json`, Einzellogs und `syntax-results.json`. Die zusammengefasste Liste verwendet die letzten belegten Einzelresultate; ein isoliert direkt ausgeführter Beschleunigertest hat ausdrücklich keine gemessene Laufdauer. Testsuiten sind keine Prozentangabe für sämtliche möglichen Spielzustände. Neuinstallation und Upgrade: 123 Migrationen, 1.090 übereinstimmende Schemaspalten, Guthabenerhalt und erneute Ausführung ohne Änderungen.

| Thema | Ergebnis und Dokument |
|---|---|
| Sicherheit | OAuth-Verknüpfung und historische Identitätsprüfung, Adminrollen/Sitzungen, POST-/CSRF-Logout, Reset-/Recovery-Belege und vertrauenswürdige Mail-URLs korrigiert. Öffentliche Profile verraten keine fremden Garnisonen; Benachrichtigungen sind weltgebunden. Admin-Passwörter werden verdeckt oder über ausdrücklich gewählten stdin-Kanal eingegeben. |
| Spielsysteme | Regionalsperren bleiben bestehen; Prozentboni, Fragmentbilanzen, wiederholte Reliktmigration, mythische Karten und historischer Besitz korrigiert. [Systemkorrekturen](ALPHA_FIX_SYSTEMS_2026-09-26.md) |
| Kampf | Rechner wieder aktiviert: ausdrücklich 0%-Referenz, echte Kämpfe weiterhin serverseitig −10% bis +10% Glück. Keine fremden Garnisonen in der Vorschau. Karten-/Dialognavigation und verletzte Monster-Lebensleisten korrigiert. [Kampfkorrekturen](ALPHA_FIX_COMBAT_2026-09-26.md) |
| Installation | CREATE/ALTER werden in richtiger Reihenfolge verarbeitet, dynamische SQL-Resultsets abgeschlossen. Echter Migrationsrunner wird mit Neuinstallation, historischem Upgrade und Wiederholung geprüft. [Architektur](ALPHA_ARCHITECTURE_2026-09-26.md) |
| Gestaltung | Gemeinsames Violett/Beige, semantische Farben, lesbare Kontraste, mobile Reliktplätze, Marschfenster, Heilungsaktionen und richtige Projektbilder. Schatten und plastische Wirkung bleiben. [Designprüfung](ALPHA_DESIGN_REVIEW_2026-09-26.md) |
| 3D | 162 exklusive Dateien mit 229.801.259 Bytes entfernt; gezeichnete Stadt und Welt bleiben. [Löschmanifest und Sicherung](REMOVED_3D_2026-09-26.md) |
| Arbeitsheftabgleich | Kein eigenständiges aktuelles Gesamtarbeitsheft gefunden. Grundlage: SPEC, IMPLEMENTATION_PLAN und ROADMAP_AGENT. Alle 44 angenommenen IDs A01/A02/E01–E42 mit Soll/Ist und Implementierungsreihenfolge. [Funktionsmatrix](ALPHA_FEATURE_MATRIX_2026-09-26.md) |
| Veröffentlichung | GitHub, Hostinger, Migration 0118, sichere Uploadkonfiguration, Backup/Rückfall, Testlauf und gemeinsame Capacitor-Apps. [Detaillierter Leitfaden](ALPHA_RELEASE_GUIDE_2026-09-26.md) |

Geplante Erweiterungen sind keine bereits fertigen Alpha-Funktionen. Veraltete Tests wurden anhand belegter aktueller Verträge angepasst: echte Serverkontrollen, Negativfälle, Guthabenerhaltung und einmalige Buchungen blieben erhalten. Die Testauswahl verwendet synthetische Wegwerfdatenbanken oder Browserfixtures und läuft bei Datenbankzugriff seriell. Einstieg: `python tests/run_alpha.py --group all`; Abschnitt 10 des Leitfadens erklärt Voraussetzungen und Einzelwiederholung.

Keine Nutzerspielstände wurden migriert oder verändert, vorhandene Änderungen anderer Arbeiten nicht zurückgesetzt. Kein Push, Deployment oder Storebuild ausgeführt. Die Sicherung entfernter Arbeitsdateien liegt unter `C:/Users/svenm/AppData/Local/Temp/conquer-removed-3d-2026-09-26.zip`; bei dauerhaftem Bedarf außerhalb des Webroots aufbewahren.

## Vor der Veröffentlichung noch tatsächlich abnehmen

1. Migrationen einschließlich 0118 auf Staging anwenden. Der lesende lokale Audit fand zwei historische Google-Bindungen ohne bestätigte lokale E-Mail: kein Missbrauchsnachweis, aber individuelle Neubestätigung erforderlich. Bestehende Nutzersitzungen wurden nicht pauschal widerrufen.
2. Zielhost: HTTPS/Proxy, Cookies, CSP, private Cache-Regeln, Pfadsperren, PHP-/Uploadgrenzen und DB-Rechte messen; echte OAuth-Callbacks, Mailzustellung, Recovery und Admin-Sitzungen prüfen. Verdeckte Linux-Terminaleingabe zusätzlich vor Ort testen; Windows und stdin sind lokal geprüft.
3. Backup auf Staging wiederherstellen, Cronbetrieb ohne offene App beobachten, Fehlerüberwachung und repräsentative Mehrspieler-/Lastprobe durchführen. Lokale Tests belegen keine Livekapazität.
4. Echtes Android und iPhone: Touch, Tastatur, Zurück, Safe Areas, Netzwechsel und Wiederaufnahme. Browseremulation ersetzt diese Prüfung nicht.
5. Angekündigten Alphaumfang anhand der Matrix festhalten. Capacitor, native Sitzungsintegration, Signierung und Storeabnahme folgen nach stabiler Web-Alpha; Echtgeld und Stores sind nicht freigegeben.

## Codex Security und formale Grenze

Das angeforderte Plugin wurde mit Standard-Scan und gezielten Korrekturverfahren eingesetzt. Ursprüngliche Scan-ID: `9304f4b1-4219-4052-b0df-0af308802c79`. Der erste Scan hatte ausdrücklich partielle Coverage. [Aktualisierter Sicherheitsbericht](C:/Users/svenm/.codex/state/plugins/codex-security/scans/conquer/artifacts-25dc0983bd14f907d5f66eae9181cdb918907b82e5d3f838186428d633db961b/artifacts/ALPHA_SECURITY_REVIEW_2026-09-26.md), lokal in der verwalteten Pluginablage.

Der abschließende neue Pluginlauf konnte nicht starten: **“The selected scan target changed while the scan was starting. Try again.”** Dafür existieren keine neue Scan-ID und kein bestandener Abschlussbericht. Die [Desktopregel des Security-Skills](C:/Users/svenm/.codex/plugins/cache/openai-curated-remote/codex-security/0.1.31/skills/security-scan/references/desktop-scan.md) schreibt vor: “If the direct start fails or returns malformed context, surface that error. Do not invent scan ownership, start a replacement scan, open setup, or switch to a terminal workflow.” Deshalb wurde kein Ersatzscan gestartet. Code-, HTTP- und Browserprüfungen wurden fortgesetzt; sie ersetzen keine Pluginfreigabe.

Am Prüftag nachgeschlagene Referenzen: [OWASP ASVS 5.0.0](https://github.com/owasp/asvs), [OWASP Top 10:2025](https://top10.owasp.org/2025/) und [OWASP API Security Top 10:2023](https://api-security.owasp.org/editions/2023/en/0x11-t10/). Das ist keine Zertifizierung. Schutz entsteht durch serverseitige Prüfung von Sitzung, Berechtigung, Welt, Eingaben und Zustand. Ein Schlüssel im Browser oder App-Paket ersetzt das nicht. Nicht jede Kombination von Objekt, Rolle und Zustand sowie jede HTML-Einfügestelle wurde dynamisch geprüft.
