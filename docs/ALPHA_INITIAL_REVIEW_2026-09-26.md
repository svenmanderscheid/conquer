# Abschlussprüfung vor der Alpha

Stand: 26. September 2026. **Ergebnis: noch keine vollständige Launchfreigabe.** Die geprüften Kernabläufe und die Oberfläche sind weit fortgeschritten; acht Backend-Suiten haben offene Abbrüche. Sicherheit und Betrieb sind auf dem Zielserver noch nicht vollständig abgenommen.

## Unterlagen

| Thema | Dokument |
|---|---|
| Genaue Soll-/Ist-Liste aller 44 angenommenen Planpakete, Lücken und Abhängigkeiten | [Funktionsmatrix und Implementierungsplan](ALPHA_FEATURE_MATRIX_2026-09-26.md) |
| Architektur, echte Backend-Testergebnisse und Fehlerdiagnosen | [Architekturprüfung](ALPHA_ARCHITECTURE_2026-09-26.md) |
| Design, Bilder, Touchgrößen und Grenzen der Prüfung | [Designprüfung](ALPHA_DESIGN_REVIEW_2026-09-26.md) |
| Exakte Liste des entfernten Codes und Sicherung | [3D-Entfernung](REMOVED_3D_2026-09-26.md) |
| GitHub, Hostinger, Migrationen, Backup, Rückfall und spätere Apps | [Veröffentlichungsleitfaden](ALPHA_RELEASE_GUIDE_2026-09-26.md) |

Die Arbeitsheftbasis wurde im Projektordner gesucht. Eine eigenständige aktuelle Gesamtarbeitsheftdatei wurde nicht gefunden. Verwendet wurden `docs/SPEC.md` und die jüngeren angenommenen Planpakete aus `docs/planning/IMPLEMENTATION_PLAN.md` und `ROADMAP_AGENT.md`. Reliktkatalog-Exporte bilden nur Teilbereiche ab. Alle 44 angenommenen IDs A01/A02/E01–E42 sind genau einmal in der Matrix enthalten; das ist keine Behauptung über ein anderswo gespeichertes Arbeitsheft.

## Bereits umgesetzt

1. **3D entfernt:** 162 nachweislich exklusive Dateien mit 229.801.259 Bytes, einschließlich Three.js, Modelle, Renderer, eigene Endpunkte, Umschalter und iframe-Brücken. Die gezeichnete Stadt und Weltkarte bleiben aktiv. Der tatsächliche Arbeitsstand der entfernten Dateien wurde vorher unter `C:/Users/svenm/AppData/Local/Temp/conquer-removed-3d-2026-09-26.zip` gesichert. Diese temporäre Sicherung bei Bedarf dauerhaft außerhalb des Webroots aufbewahren.
2. **OAuth-Kontoübernahme geschlossen:** Neue Google-/Discord-E-Mail-Verknüpfungen akzeptieren nur eindeutig bestätigte Adressen und gültige Provider-IDs. Ein gezielter Angriffstest reproduzierte den Fehler vor der Änderung und besteht danach. Historische Links werden nicht automatisch bereinigt.
3. **Design vereinheitlicht:** dokumentierte Beige-/Violettfarben, dunkle Köpfe, lesbare Aktionsfarben, plastische Rahmen und lokale Schriften. Reine 3D-CSS-Regeln entfernt. Aktive Illustrationen unverändert erhalten.
4. **Releasehygiene:** lokale Artefakte, Exporte, Vorschauen und Arbeitsdateien von Git ausgenommen; widersprüchliche Dokumentationsstellen aktualisiert. Echte lokale Konfiguration bleibt ignoriert. Bereits früher verfolgte Dateien werden durch Ignore-Regeln nicht automatisch aus der Historie entfernt.
5. **Uploadgrenzen korrigiert:** normale Anfragen bleiben auf 64 KiB begrenzt. Nur exakte Profilbild-/Bugreport-POST-Routen erlauben die vorgesehenen 5,5 MB bzw. 1,3 MB. Auch interne Weiterleitung, Unterordner und Chunked-Anfragen sind geprüft. `outputs/` und `preview/` werden zusätzlich durch die Webserverregeln gesperrt.

Vorher vorhandene Änderungen wurden nicht zurückgesetzt. Es wurde kein Push, Deployment oder Storebuild durchgeführt.

## Sicherheitsbewertung

Das explizit angeforderte Codex-Security-Plugin wurde mit seinem Standard-Scan und anschließendem Fix-Finding-Verfahren verwendet. Scan-ID `9304f4b1-4219-4052-b0df-0af308802c79`. Der versiegelte Bericht hält den ursprünglichen Befund fest; die Behebung steht im Ergänzungsbericht:

- [Originalbericht](C:/Users/svenm/.codex/state/plugins/codex-security/scans/conquer/88a782254adf5500940ab63916474010f633a8dd_20260926T110630Z_422bhi16/report.md)
- [Behebung, Nachweise und offene Kontrollen](C:/Users/svenm/.codex/state/plugins/codex-security/scans/conquer/artifacts-25dc0983bd14f907d5f66eae9181cdb918907b82e5d3f838186428d633db961b/artifacts/ALPHA_SECURITY_REVIEW_2026-09-26.md)

Diese Links verweisen auf die lokale verwaltete Pluginablage, nicht auf Dateien des Git-Releases. Der Scan hat ausdrücklich **partielle Coverage**: zentrale Grenzen plus Stichproben, keine lückenlose Prüfung sämtlicher 6.896 inventarisierter Dateien. Wegen ausgeschöpfter Agentenkapazität gab es keinen zusätzlichen unabhängigen Security-Worker. Die parallel geänderte 3D-Ansicht wurde anschließend separat geprüft.

Aktuelle Referenzen wurden am Prüftag nachgeschlagen: [OWASP ASVS 5.0.0](https://github.com/owasp/asvs), [OWASP Top 10:2025](https://top10.owasp.org/2025/) und [OWASP API Security Top 10:2023](https://api-security.owasp.org/editions/2023/en/0x11-t10/). Sie sind Prüfreferenzen, keine pauschal durch Installation eines Plugins erfüllte Zertifizierung.

| Kontrollbereich | Stand / Nachweis | Noch erforderlich |
|---|---|---|
| Authentifizierung | Sitzungsprüfung und neuer OAuth-Fix; `oauth_identity.php` bestanden | Echter OAuth-Callback, Ablauf, Recovery-Mail und eventuell vorhandene historische Verknüpfungen |
| API-Autorisierung | Zentraler Spieler-/Weltkontext; Eigentümer-/Adminstichproben | Vollständige Matrix eigener/fremder Spieler, fremde Welt, ausgeloggt und gesperrt je geschütztem Endpunkt |
| CSRF | Alle Schreibmethoden im `security_guard.php` abgewiesen ohne gültiges Token | Echte WebView-/Cross-Origin-Sitzung vor Capacitor |
| Missbrauch und Parallelität | Atomare Limits, 12 gleichzeitige Testworker, gemeinsame Spielerquote; fail-closed bei fehlender Limit-Tabelle | Lastmessung und Betriebslimits auf dem Zielhost |
| Wiederholte Aktionen | Vorgangsbelege und erfolgreiche Army-/Inventory-/Shop-Teilprüfungen | Alle schreibenden Fachabläufe unter Retry, Parallelität und Weltwechsel |
| Injection/Dateien | PDO-Vorbereitung, Escaping-/Uploadstichproben, private Verzeichnisse gesperrt | Vollständige DOM-XSS-/Templateprüfung, Zielserverregeln und Abhängigkeiten |
| Transport/Sitzungen | HTTPS-/HSTS-/Cookie-/CSP-Konfiguration vorhanden | Tatsächliche HTTPS-/Proxy-/Cache-Header auf Staging/Live messen |
| Betrieb | Logging, CLI-Cron und Migrationsrunner vorhanden | Cronüberwachung, Backup-Restore, minimale DB-Rechte und frische Installation |

Die API lässt sich nicht dadurch sichern, dass nur die offizielle App Anfragen senden darf: Ein Client ist veränderbar. Jede Aktion muss auf dem Server Sitzung, Berechtigung, Welt, Eingaben und Zustand prüfen. Ein ins Android-/iOS-Paket eingebauter geheimer API-Schlüssel würde diese Kontrollen nicht ersetzen.

## Verifizierter Stand

| Prüfung | Ergebnis |
|---|---|
| 31 isolierte Backend-Suiten | 23 bestanden, 8 fehlgeschlagen; Details im Architekturbericht |
| OAuth-Regression | Bestanden; neue unbestätigte Verknüpfungen abgewehrt, legitime Google-/Discord-Fälle funktionieren |
| Zentraler Security-Guard | Bestanden: 401/403/413/429/503, Logout und parallele Ratenbegrenzung |
| Isolierter echter Apache | 84 Prüfungen bestanden: Uploadgrenzen, Chunked, Unterordner, Querystrings, falsche Pfade/Methoden und gesperrte Exporte; keine App-Datenbank verwendet |
| Oberfläche | 110 Menü-/Viewportkombinationen bestanden; 2.306 sichtbare Textproben; keine erfassten Browser-/Bild-/Menü-API-Fehler |
| Gemalte Stadt/Welt | Drei spezifische Browserprüfungen bestanden; vier Formate, Assets und Animationen |
| Alte Registrierungsprüfung | Separat fehlgeschlagen: Test sendet keine inzwischen erforderliche E-Mail; Testharness isolieren und aktualisieren |
| Zusätzliche Gesamtprüfung `alpha_final_app.cjs` | Mobiler Login, gemalte Stadt/Menü in fünf Formaten, Hintergrundpause/Wiederaufnahme und grundlegende Karten-Datenfilter bestanden; Abbruch bei `locked land hides cities`: eine Teststadt wurde in der vom Test als gesperrt erwarteten Zentralregion geliefert. Aktuelle Regionsregeln gegen Fixture klären; nachgelagerte Kampf-/HTTP-Szenarien nicht erreicht. |

Die Zahl erfolgreicher Suiten ist keine prozentuale Funktions- oder Sicherheitsabdeckung. Abgebrochene Suiten prüfen ihren verbleibenden Ablauf nicht.

## Reihenfolge bis zur Freigabe

1. **Kampfvertrag klären:** Glückswürfe und Vorschau konsistent beschreiben; reproduzierbare Testeingaben und erwartete Zufallsgrenzen. `mvp_rules` und `battle_preview` sind derzeit rot.
2. **Relikt-/Fragmentvertrag abgleichen:** aktuelles Universalfragmentkonto und reale Effekte prüfen; `full_progression` und `inventory_bulk` sind rot. Keine Tests ohne fachliche Begründung auf neue erwartete Werte umstellen.
3. **Weitere Abbrüche lösen:** `reward_admin`, `regional_rewards`, `dungeon_lifecycle`, `guide_progression`; Editor, Regionsgrenzen, echte Kämpfe und Bossbilder vollständig erneut abnehmen.
   Die zusätzliche Browser-Gesamtprüfung meldet ebenfalls einen Konflikt zur Zentralregion. Ob die Testregion nach den aktuellen Regeln tatsächlich noch gesperrt sein muss, ist offen; bis zur Klärung keine pauschale Aussage zur vollständigen Kartenisolation.
4. **Sicherheitsnegativtests abschließen:** insbesondere alle Objektberechtigungen und Weltgrenzen. Historische OAuth-Links prüfen, falls OAuth bereits öffentlich aktiv war.
5. **Release auf Staging üben:** leere Datenbank und Upgrade einer Sicherung, unterstützte PHP-/DB-Versionen, HTTPS, Mail, Cron, Backup-Restore und Fehlerüberwachung. Produktionsbetrieb wurde hier nicht getestet.
6. **Reale Geräte:** Einstieg bis Belohnung, Zurück, Bildschirmtastatur, Netzwechsel, Hintergrund und Wiederaufnahme auf Android und iPhone.
7. **Alphaumfang ausdrücklich festlegen:** fehlende 44-Pakete-Erweiterungen anhand der Matrix priorisieren. Danach GitHub-Release/Live-Deployment nach Leitfaden; erst nach Web-Stabilisierung den gemeinsamen Capacitor-Client bauen.

Eine betreute geschlossene Alpha kann einen kleineren angekündigten Funktionsumfang haben. Ungeklärte Zugriffs-, Beute- oder Fortschrittsfehler bleiben trotzdem Freigabepunkte.
