# Alpha-Korrekturen: Regionen, Relikte, Belohnungen und Uploads

Stand: 26.09.2026. Änderungen im gemeinsamen Arbeitsbaum; kein Commit, kein Deployment und keine Nutzer-Spielaktionen. Die Prüfungen verwendeten ausschließlich synthetische Konten in wegwerfbaren lokalen Datenbanken, einen temporären Apache-Webroot oder einen Browser mit abgefangenen Testantworten.

## Nachgewiesene Produktfehler und Korrekturen

| Fehler | Korrektur und Beleg |
|---|---|
| `LandProgressService::ensureWorld()` öffnete ausdrücklich gesperrte Zonen bei der nächsten Initialisierung erneut. Dadurch konnten Weltkarte und Fachaktionen administrativ gesperrte Bereiche wieder anzeigen. | Neue Welten bleiben wie vorgesehen vollständig offen. Bestehende Sperren werden beim normalen Initialisieren bewahrt; nur die ausdrückliche historische Migration 0109 öffnet Altwelten. `land_progression`, `regional_rewards` und `map_land_access` prüfen offene Standardwerte, persistente Sperren, Monster, Rohstoffe, Schreine, Charms, Rally-Ziele und fremde Städte einschließlich direkter Detailzugriffe. |
| Prozentuale Reliktkapazitäten wurden vom BuffEngine pauschal als feste Truppenplätze behandelt. | `TreasureService::getEquippedBuffs()` berücksichtigt die Einheiten jedes Effekts und Meisterbonus. Marschkapazität in Prozent wird zu `march_size`; Lazarettprozente bleiben relativ. Alte ausdrücklich flache Werte behalten ihre Einheit. `full_progression` verbessert und rüstet die aktuellen Relikte tatsächlich aus, prüft +25 % Lazarettkapazität beziehungsweise +10 % Marschgröße und das Entfernen des Bonus. |
| Fünf aus dem aktiven Katalog entfernte Relikte ließen bestehendes Eigentum ohne Definition zurück. | `data/legacy-treasures.json` erhält die ursprünglichen fünf Definitionen. TreasureData kennt 82 Definitionen, aber der Zufallspool enthält weiterhin exakt 77 aktive Relikte. TreasureService liefert alte Karten nur bei vorhandenem Besitz. Ursprüngliche 24 Balancewerte und 77 Referenzbilder werden unabhängig über das bestehende Manifest überprüft. |
| Migration 0116 zog bei jeder Wiederholung erneut zehn Fragmente ab. | Eine temporäre Kandidatenmenge enthält ausschließlich Relikte ohne ersten Teilstern. Abzug und Freischaltung erfolgen gemeinsam in einer Transaktion. Wiederholung verändert weder schon bezahlte Freischaltungen noch Fragmente. `treasure_loadouts` führt die Migration mehrfach aus und prüft Altbesitz, Fragmentbilanzen und weltbezogene Ausrüstung. |
| Die Schatzkammer filterte alle sechs mythischen Relikte und gerettete alte Definitionen aus. Außerdem verlangte das Anlegen Restfragmente, obwohl die ersten zehn beim Freischalten vollständig ausgegeben werden können. | Der Client zeigt den serverseitigen Katalog vollständig; bestätigte Freischaltung und Nutzbarkeit bestimmen das Anlegen. Alle 77 aktiven Karten sowie ein zusätzlich besessenes altes Relikt sind im Browser geprüft. |
| Mythische Karten bekamen einen zusätzlichen Rahmen; sechs Ausrüstungsplätze liefen bei 320 px seitlich heraus; ein unnötiger Bonusknopf verdrängte im Querformat die Bonusliste. | Bestehende gerahmte Projektbilder werden vollflächig verwendet. Mobile Slots können rechteckig sein; der nur im Hochformat benötigte Bonusknopf entfällt im Querformat. Eigene Sammlung, Detailansicht, Boni und Truhen werden auf vier Bildschirmformaten geprüft. |
| Apache lehnte erlaubte Profilbilder und Fehlermeldungs-Screenshots schon oberhalb 64 KiB ab. | Das Standardlimit bleibt 65.536 Byte. Ausschließlich die ursprünglichen POST-Anfragen für `api/kingdom/profile-image` und `api/bug-reports` erhalten 5.500.000 beziehungsweise 1.300.000 Byte, auch mit Unterordner, Querystring und internem Rewrite. 84 isolierte Apache-Prüfungen decken exakte Grenzen, Chunking und Umgehungsversuche ab. `outputs/` und `preview/` sind öffentlich gesperrt. |

## Überholte Prüfannahmen sachlich ersetzt

- Fragmentpakete gutschreiben weiterhin konkrete Reliktfragmente. Die automatische erste Freischaltung verbraucht zehn davon. `inventory_bulk` und `item_catalog` prüfen deshalb den Gesamtwert aus verbleibenden Fragmenten und bezahlten Freischaltungen; sie reduzieren nicht die erwartete Belohnung. Wiederholte Vorgänge bleiben unverändert.
- Das aktuelle Sternsystem verlangt gezielte Effektaufwertung. Mehr gesammelte Fragmente erhöhen Werte nicht automatisch. Loadout-/Presettests prüfen diese Regeln, Produktionsabrechnung vor Bonusänderungen, Welt-/Spielerisolation, Rollbacks, gesperrte Slots, manipulierte Daten, CSRF und pausierte Welten.
- Der Katalog enthält negative Effekte und Prozentwerte. Katalogtests erhalten Originalreferenzen und prüfen gültige endliche Werte, Einheiten und genaue Poolgröße statt pauschal positive Werte zu verlangen.
- `balance_import` bewahrt die exakten Quellvergleiche. Für die bewusst neu ausbalancierten Solo-/Deathkar-Auszahlungen prüft der Konsumententest die beiden garantierten Beschleuniger mit der tatsächlichen Monsterstufe; sonst bleiben Originaldrops maßgeblich.
- Admin-Renderprüfungen suchten einen Fehlertext auch im eingebetteten Übersetzungswörterbuch. Sie prüfen jetzt das tatsächliche Fehlerbanner. `reward_admin`, `regional_rewards` und `reward_preview` melden sich mit echten synthetischen Adminzugängen an, statt nach verschärfter Authentifizierung unvollständige Sitzungen vorzutäuschen. Ein Exitcode 0 ohne abschließenden Prüfmarker gilt nicht als Abnahme.
- `guide_progression` bindet die aktuelle Bossgrafik und das Eisbiom an Monster-ID und vorhandene Bilddatei; ein alter Dateiname war kein gültiger Sollvertrag mehr.
- Der Schatzkammer-Browsertest verwendet das aktuelle Effektmodell, Prozentkapazitäten, faltbare mobile Boni und die gestapelte Hochformatansicht. Er prüft echte Erreichbarkeit und fehlende Überlappung, statt die alte Desktopanordnung auf Handys zu erzwingen. Verzögert geladene Bilder werden im isolierten Fixture ausdrücklich geladen, bevor ihre Integrität geprüft wird.

## Ausgeführte Regressionen

Rohprotokolle: `artifacts/alpha-audit-2026-09-26/*-fix.log`; Apache separat `apache-request-limits.log`. PHP 8.2 aus XAMPP; Browser Chromium/Chrome über Playwright. Alle Datenbanksuiten liefen nacheinander, um globale MySQL-Sperren nicht miteinander zu verwechseln.

| Suite | Ergebnis |
|---|---|
| `land_progression`, `map_land_access` | vollständig bestanden, einschließlich administrativer Sperren und offener neuer Welten |
| `regional_rewards` | 20 Prüfungen bestanden |
| `full_progression` | vollständig bis Events/Invasion/Conquest bestanden |
| `inventory_bulk` | 47 Prüfungen bestanden |
| `item_catalog` | 1.373 Prüfungen für 194 Gegenstände bestanden |
| `kingdom_regressions` | vollständig bestanden |
| `reward_admin`, `reward_preview` | 36 beziehungsweise 20 Prüfungen bestanden |
| `guide_progression` | 79 Prüfungen bestanden |
| `treasure_catalog` | 3.941 Prüfungen bestanden |
| `balance_import` | vollständige Quellen-/Konsumentenprüfung bestanden |
| `treasure_loadouts`, `treasure_presets` | 110 beziehungsweise 54 Prüfungen bestanden |
| `apache_request_limits` | 84 Prüfungen bestanden |
| `treasure_panel.cjs` | 77 aktive Karten, fünf Vorlagen, sechs Plätze; Desktop 1280×720, Hochformat 390×844 und 320×568, Querformat 568×320; zusätzlicher Altbesitz geprüft |

Die ersten drei Kampf-/Dungeonabbrüche werden im parallelen Kampfbericht behandelt; ihre erneuten finalen Protokolle `mvp_rules`, `battle_preview` und `dungeon_lifecycle` bestätigen erfolgreiche vollständige Läufe. Dieser Bericht ersetzt die offenen Relikt-/Regionendiagnosen des ersten Architekturberichts. Vollständige API-Sicherheit, Gesamt-App-Abnahme und Deployment sind Gegenstand der weiteren Alpha-Berichte.

## Grenzen

Der Apache-Nachweis betrifft die lokale Apache-2.4-Umgebung. Die Konfiguration verwendet auch von aktuellen LiteSpeed-Versionen unterstützte Bedingungen; die konkrete LiteSpeed-/Hostkonfiguration muss trotzdem auf Staging geprüft werden. Kein Browserfixture beweist reale iOS-/Android-Hardware, Hochlast oder den Dauerbetrieb von Cron und E-Mail. Neue Installation, Backup-Restore und Zielhostfreigabe haben eigene Nachweise. Keine Aussage hier ist eine Freigabe für Echtgeldzahlung oder Stores.