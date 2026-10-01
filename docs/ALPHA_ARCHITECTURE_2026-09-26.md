# Alpha: Architektur und Funktionsprüfung

Stand: 26.09.2026. Prüfung des lokalen, bereits umfangreich veränderten Arbeitsbaums. Der Erstbefund wurde nach beauftragten Korrekturen ergänzt. Keine Nutzerspielstände wurden verändert. Entfernung der 3D-Ansicht und Sicherheits-/Designprüfung sind im zentralen Alpha-Abnahmebericht dokumentiert.

## Ergebnis

Die App hat eine tragfähige Grundarchitektur für eine betreute Web-Alpha: serverseitige Spiellogik, getrennte API-Handler, persistente MySQL-Zustände, Weltkontext und wiederholsichere Aktionen. **Die lokalen Abbrüche des ersten Durchlaufs sind inzwischen behoben und neu geprüft.** Die ursprüngliche 23/31-Einordnung unten ist ein historischer Diagnosebefund, keine aktuelle Freigabeliste. Einzelne alte Adminfixtures beendeten sich vorzeitig mit Exit 0; jetzt sind echte Anmeldung und vollständige Abschlussmarker nachgewiesen. Aktuelle Ursachen, Laufzeitkorrekturen und Regressionen stehen in `ALPHA_FIX_SYSTEMS_2026-09-26.md`; der Gesamtbericht ergänzt die breite App- und Sicherheitsprüfung. Die Zahl ist keine prozentuale Funktionsabdeckung. Der vollständige Funktions-/Planabgleich steht in `ALPHA_FEATURE_MATRIX_2026-09-26.md`.

## Struktur und Bewertung

| Schicht | Bestand / Bewertung |
|---|---|
| Einstieg | `index.php` normalisiert Unterordner und routet öffentliche Anmeldung, Spiel und API. `src/Bootstrap.php` initialisiert Konfiguration, Diagnose, Sitzungsparameter, Autoload und DB. Funktional sinnvoll, jedoch zentraler Einstieg sehr breit; der veraltete Stub-Kommentar wurde entfernt. |
| API und Rechte | `src/Api/Handlers/`, `src/Security/ApiGuard.php`, `ApiOperation.php`, Session und WorldContext trennen Transport und Spielzustand. Ein API-Key im Apppaket wäre kein Ersatz für diese Nutzer-/Objektberechtigung. Detaillierte Sicherheitsbewertung bleibt dem Codex-Security-Bericht vorbehalten. |
| Domänen | `src/Game/` gliedert Bau, Truppen, Forschung, Kampf, Inventar, Allianz, Welt und Events. Regeln liegen serverseitig. Mehrere große Services bündeln inzwischen viele Aufgaben; neue Domänen zuerst separat anbinden, anschließend gezielt extrahieren. |
| Bestände/Konkurrenz | PDO, Transaktionen, Zeilensperren und Vorgangsbelege (`Operation`, ApiOperation, CommunityService) sind vorhanden. Unterschiedliche Belegmuster erhöhen Wartungsaufwand: gemeinsame Vertragsregeln für Schlüssel, Weltbindung, Nutzlastvergleich, Ergebnisablage und Aufbewahrung festhalten. |
| Hintergrund | `cron/march_tick.php` und `cron/world_spawn_tick.php` sind von offenen Browsern unabhängige Einstiegspunkte. Zielhost muss tatsächlichen regelmäßigen Betrieb, Rückstand und Fehlerüberwachung beweisen. Lokale erfolgreiche Fachtests belegen keinen eingerichteten Cron. |
| Oberfläche | Views plus Vanilla-JavaScript und CSS; Funktionsmodule wie `mvp-panels.js` und `world-map.js`. Große Dateien sind Wartungsrisiko, kein alleiniger Performancebeweis. `village-theme.css` lag beim Lesen bei rund 311 KB, `world-map.js` bei 137 KB, `mvp-panels.js` bei 95 KB. Kompression/Ladezeiten am Host messen. |
| Daten/Regeln | `data/*.json` und DB-Overrides bilden Kataloge und Balance ab. Dateiexistenz ist kein aktiver Inhalt. Quellen-/Aktivstatus und Revisionen zentral halten; Tests müssen aktuelle Relikt- und Kampfverträge verwenden. |
| Migrationen | Viele additive SQL-Dateien und mehrere thematische `tools/migrate-*.php`. Das Fixture klont die lokale Schemaform und ergänzt ausgewählte Migrationen. Zusätzlich hat `migration_lifecycle.php` den echten CLI-Runner auf leerer DB und historischem Stand bis 0102 geprüft: 123 Migrationen, 1.090 übereinstimmende Spalten, Guthabenerhalt und unveränderte Wiederholung. Ein Restore eines echten Backups auf dem Zielhost bleibt separat. |
| Qualitätssicherung | Umfangreiche PHP-/Browser-Skripte vorhanden, aber `.github/workflows` enthielt bei Prüfung keine Workflowdatei. Einen dokumentierten Kernlauf in CI anlegen; Datenbankjobs zunächst seriell. |
| Dokumentation | `src/README.md` beschreibt jetzt die tatsächlich vorhandenen Servermodule; Haupt-README verweist auf die aktuelle Alpha-Abnahme. Der neue Releaseleitfaden beschreibt den echten Migrationsrunner und die gemeinsame mobile Zukunft. SPEC bleibt ausdrücklich historische Planungsgrundlage. |
| Mobile Zukunft | Frontend bleibt gemeinsam, PHP/MySQL bleiben Server. Cookie-/Origin-/CSRF-/Lifecycle-Verträge vor Capacitor prüfen; native Pakete erst nach stabiler Webabnahme. 3D ist entsprechend aktuellem Auftrag kein Ziel mehr. |

## Historischer Erstlauf (durch Korrekturbericht ergänzt)

Laufzeit: `C:/xampp/php/php.exe`, Arbeitsverzeichnis `C:/xampp/htdocs/conquer`. Ausgeführt wurden ausschließlich unten aufgeführte Tests, die `tests/Support/FeatureDatabase.php` verwenden. Dieses Fixture prüft localhost, erstellt zufällige `conquer_feature_test_*`-Datenbanken, kopiert Tabellenstruktur und die Welt-1-Konfiguration, erzeugt synthetische Konten und entfernt seine Testdatenbank. Tests liefen **nacheinander**, weil benannte MySQL-Sperren serverweit gelten. Keine produktiven Accounts wurden angelegt oder bearbeitet. Einige Tests protokollieren zusätzlich in lokale Logdateien.

Rohdaten: `artifacts/alpha-audit-2026-09-26/backend-results.json`, pro Suite `<name>.log`. Für die heutige Abnahme werden Exitcode und vollständiger Abschlussmarker gemeinsam geprüft; Exit 0 allein reichte bei alten Adminfixtures nicht. Ein Fehllauf deckt spätere Prüfungen dieser Suite nicht ab. Ältere Ergebnisse vom 20.09. wurden nicht als neue Bestätigung übernommen.

**Bestanden (23):** army_receipts, training_costs, training_durations, training_unlocks, hospital_healing, gathering_lifecycle, charm_lifecycle, land_progression, map_land_access, map_search, inventory_rewards, trading_shop, daily_chests, vip_system, lord_talents, reward_preview, monster_rallies, regional_bosses, combat_reports, mailbox, world_chat, research_snapshot, building_plots.

| Nicht bestanden | Beobachtung | Einordnung / erforderliche Korrektur |
|---|---|---|
| mvp_rules | `battle resolution is deterministic` | Bewiesener Vertragskonflikt: `BattleEngine.php:133` würfelt bei jedem Aufruf `BattleLuck::roll()`. Test erwartet identische Vollergebnisse. Kontrollierten Glückswert für reproduzierbare Tests ermöglichen und Zufallsgrenzen separat prüfen. |
| battle_preview | `preview matches actual monster engine for 8 troops` | `BattlePreview.php:48–49` und Vergleich rechnen mit unabhängigen Würfen. Auch Nutzer bekommen wechselnde Zahlen ohne expliziten Glückshinweis im aktuellen `notice`. Vorschau als eindeutig erklärte Referenz oder Spanne gestalten; reale Kämpfe bleiben serverseitig zufällig. |
| full_progression | `relic flat hospital and fractional protection are distinct` | Test verwendet konkrete alte Reliktannahmen. Gegen neuen Reliktkatalog/aktive Ausrüstung prüfen; danach Events/Invasionen nachholen, die nach dem Abbruch nicht mehr ausgeführt wurden. |
| inventory_bulk | `fragment totals match actual credit` | Aufgelöste Erstdiagnose: konkrete Reliktfragmente werden korrekt gebucht; die erste automatische Freischaltung verbraucht zehn. Tests beweisen jetzt verbleibende Fragmente plus bezahlte Freischaltungen und unveränderte Wiederholungen. |
| reward_admin | `Render monster editor` | Fachliche Solo-Auszahlung vorher erfolgreich, Editor-Renderprüfung abgebrochen. HTTP-Antwort und erwartetes Markup/Authentisierung isoliert prüfen; keine Produktions-Editorfreigabe aus diesem Lauf. |
| regional_rewards | `closed central ...` | Erwartung zur geschlossenen Zentralregion schlägt fehl. Fixture-Phase, aktuelle Spawnregeln und direkte LandAccessPolicy gegeneinander prüfen. Bis geklärt keine Zusage, alle regionalen Grenzen seien fehlerfrei. |
| dungeon_lifecycle | `real battle reaches victory` | Tatsächlicher Testkampf erreicht den erwarteten Sieg nicht. Aktuelle Balance/Zufall gegen Fixture-Truppen und Dungeonresolver untersuchen; Sieg/Verlust/Abbruch/Rückkehr erneut testen. |
| guide_progression | `HTTP state preserves regional boss artwork and biome` | Boss-Darstellung/Biom in Spielzustand entspricht Erwartung nicht. Katalog, Mapping, aktive Art und API-Vertrag angleichen; regionale Bosse visuell kontrollieren. |

Diese Fehler sind **nicht alle bewiesene Produktfehler**. Belegt ist der rote Lauf; nur bei ausdrücklich beschriebener Ursache ist die Diagnose bestätigt. Die nachfolgende Korrekturrunde ersetzt fachlich veraltete Erwartungen durch belegte aktuelle Verträge und ergänzt Regressionen für die tatsächlichen Produktfehler.

Zum Wiederholen nach einer konkreten Korrektur, jeweils seriell:

```powershell
& C:/xampp/php/php.exe tests/battle_preview.php
& C:/xampp/php/php.exe tests/inventory_bulk.php
& C:/xampp/php/php.exe tests/dungeon_lifecycle.php
```

## Freigabegates und verbleibende Grenzen

1. Erledigt: acht Erstabbrüche fachlich aufgelöst und betroffene Suiten vollständig erneut ausgeführt. Die dokumentierten Regressionen in den laufenden Releasecheck übernehmen.
2. Lokale Neuinstallation und synthetisches historisches Upgrade sind bestanden. Zusätzlich den Restore eines echten Backups und die genaue Datenbankversion auf Staging abnehmen.
3. Auf Staging Login→Bau→Ausbildung→Forschung→Marsch→Kampf→Rückkehr→Beute→Heilung→Allianz/Post ohne Adminhilfen spielen, einschließlich Offline/Retry/Weltwechsel.
4. HTTPS, Cookies, API-Objektberechtigungen, Backoffice, externe E-Mail, Cronbetrieb und Backup-Wiederherstellung auf dem konkreten Zielhost abnehmen.
5. Mehrere gleichzeitige Spieler und gefüllte Historien messen; keine konkrete Kapazität aus lokalen Einzeltests ableiten.
6. Reales iPhone und Android-Gerät mit Touch, Tastatur, Netzwechsel, Hintergrund/Wiederaufnahme in Hoch-/Querformat prüfen. Browseremulation allein beweist keinen Gerätebetrieb.

Echtgeldzahlungen und Stores sind nicht freigegeben. Eine geschlossene Alpha benötigt einen erklärten Umfang, bekannte Einschränkungen, Fehlerkanal, Resetpolitik und einen Verantwortlichen für Störungen. Offene Roadmap-Erweiterungen sind getrennt von diesen Betriebs- und Korrektheitsgates zu planen.

## Zusätzliche Korrekturen aus dem breiten Abschlusslauf

Der Migrationsrunner prüfte früher ALTER-Bedingungen vor vorhergehendem CREATE derselben Datei. Er führt nun Anweisungen geordnet aus und schließt dynamische SQL-Ergebnisse. Die erfolglose Platzsuche ging für jeden Suchradius erneut über das gesamte Quadrat; sie besucht jetzt nur dessen Rand in unveränderter Reihenfolge und beendet vollständig gesperrte Welten sofort. Der Regressionstest verwendet eine ausdrücklich unterstützte 256er-Testwelt statt der Größe der lokalen Nutzerdatenbank.
