# Union of Kingdoms – Apricotlicht-Rollout

Stand: 7. Oktober 2026. Die verbindliche Auswahl steht in [BRANDING_KIT.md](../BRANDING_KIT.md); dieser Bericht beschreibt die Umsetzung und ihre Prüfung im lokalen Projekt.

## Umsetzung

- Gemeinsame Spieloberfläche: helle Apricotlicht-Flächen, kräftige violette Fensterköpfe, plastische Rahmen und helle grüne Hauptaktionen. Status-, Rollen- und Seltenheitsfarben bleiben eigenständig.
- Bree Serif für Überschriften und Hauptaktionen; Nunito für Texte, Zahlen und Eingaben. Vier lokale WOFF2-Dateien einschließlich lateinischer Erweiterungen, OFL-Lizenzen und Quellen-/Hashmanifest liegen unter `assets/fonts/`.
- Anmeldung, Registrierung, HUD, Hauptmenüs, Dialoge, Gebäudeaktionen, Forschung, Ausbildung, Inventar, Berichte, Weltkarten-Overlays und Offline-Seite nutzen die gemeinsame Gestaltung. Das freigegebene Admin-modern-Design bleibt eigenständig.
- Im Inventar entfallen die abgewählten Filter „Owned“ und „All items“. Kategorien, Mengenwahl, „Use All“, Gegenstandsdetails und Fundorte bleiben erhalten. Ein direkter Fundort-Einstieg kann genau einen noch nicht besessenen Gegenstand anzeigen.
- Die Ausbildung verwendet am Desktop eine zweispaltige Kostenübersicht und reserviert Platz für die Hauptaktion. Die Profilnavigation erhält mehr Platz für vollständige Beschriftungen.
- Der Marschdialog berücksichtigt bei der Desktopvergrößerung die verfügbaren Bildschirmgrenzen.
- Browser-/Manifestfarben und mobile Startfarben sind angepasst. Der mobile Web-Fallback wurde mit den lokalen Schriften neu gebaut. Der öffentliche Service-Worker-Cache wurde auf v10 gesetzt.
- Freigegebene Illustrationen und zehn vorhandene Icon-/Masterdateien wurden nicht verändert. Der Icon-Export nutzt bei einem künftigen bewussten Export die neue violette Hintergrundfarbe.

## Verifikation

Die Browserprüfungen verwenden lokale Testkonten bzw. isolierte Testdatenbanken. Es wurden keine echten Spieleraktionen für die Prüfung ausgelöst.

| Prüfung | Ergebnis |
| --- | --- |
| Hauptmenüs: 22 Menüs × fünf Formate | 110 bestanden; 2.227 geprüfte Kontrasttexte, tatsächliche Bree-/Nunito-Glyphen und keine Browser-, Asset-, API- oder Darstellungsfehler |
| Profilnavigation und Porträtkarte | Alle 25 Navigationsziele in fünf Formaten mindestens 44 px hoch und per Trefferpunkt frei; fünf Namens-/Stufenkarten vollständig sichtbar |
| Ausbildung: drei Schulen × fünf Zustände × fünf Formate | 75 bestanden; keine verdeckten Aktionen oder überlappenden Angaben |
| Anmeldung und Registrierung: EN/DE/FR | 30 Layoutfälle bestanden, einschließlich Touch-Bedienung, Unterpfad-Assets und CSP |
| Inventar: vier Kategorien × fünf Formate | 20 Layoutfälle und Funktionsprüfungen bestanden; Mengenwahl, „Use All“, Doppelklickschutz, leere Zustände und gezielter unbesessener Gegenstand |
| Mobile Konfiguration und Web-Paket | 3 Prüfungen bestanden |
| Mobile Offline-Oberfläche: drei Sprachen × fünf Formate | 15 Fälle bestanden, lokal geladene Schriften |
| Schriftdateien im Browser | 9 Schrift-/Gewichtsfälle bestanden; EN/DE/FR-Sonderzeichen einschließlich großem ẞ mit Nunito-Fallback |
| Service-Worker-Schriften unter `/` und `/conquer/` | v9→v10-Wechsel und Hashgleichheit aller vier zuvor geladenen Schriftdateien im Offline-Modus bestanden |
| Markenperipherie | PHP-/JS-Syntax, JSON-Konfiguration, Android-XML, Manifest unter Root/Unterpfad und fünf Offline-Aufnahmen geprüft |
| Gebäudeaktionen | Alle 16 Gebäudefenster, 150 Kontrastmessungen in fünf Formaten, Voraussetzungen und 12 Warteschlangenfälle bestanden; Mindestkontrast der Hauptmessungen 5,51:1 |
| Fundorte und Weltkartenwechsel | Fundorte, gezielte Navigation, neun Rally-Beutebelohnungen und erreichbare Marschaktion in fünf Formaten bestanden; keine Spielschreibaktionen oder Browserfehler |
| Gebäude-/Forschungstitel nach letzter Schriftanpassung | Sechs Fälle bei 1280×800, 390×844 und 320×568 bestanden; tatsächlich gerenderte Bree-Serif-Glyphen mit Gewicht 400, erreichbare Fußaktionen und mindestens 4,99:1 Kontrast |

Die Abschlussmatrix verwendet 1280×800, 390×844, 320×568, 844×390 und 568×320. Profil, Forschung, Inventar, Ausbildung sowie Marsch- und Gebäudedialoge wurden zusätzlich anhand der Bildschirmaufnahmen kontrolliert. Syntaxprüfung aller 16 geänderten JavaScript-/Prüfmodule und `git diff --check` bestanden.

## Grenzen und bestehende Befunde

- Die allgemeine Suite `tests/localization_pwa.cjs` stoppt bereits vor dem Browserlauf an der bestehenden Sprachkatalog-Parität: Französisch fehlen gegenüber Deutsch 240 Schlüssel. Derselbe Befund ist unverändert in `HEAD` vorhanden; Englisch und Deutsch haben jeweils 10.213 Schlüssel, Französisch 9.973. Die gezielte Schrift-/Cache-Prüfung wurde unabhängig davon erfolgreich durchgeführt.
- Die Service-Worker-Schriftprüfung belegt die Offline-Nutzung bereits geladener Dateien; sie behauptet keine vollständige Vorabinstallation aller Schriften.
- Geprüft wurde in Chrome mit Desktop-, Handy- und Querformaten. Eine Prüfung auf physischen iOS-/Android-Geräten sowie ein neuer nativer Build stehen aus.
- Die Änderungen sind lokal umgesetzt. Dieser Bericht bestätigt weder einen Commit/Push noch eine Veröffentlichung auf dem Live-Server.

## Lokale Nachweise

- `output/playwright/menu-depth/fonts/font-browser-check.json`
- `output/playwright/apricot-light-final/report.json`
- `output/playwright/menu-depth/fonts/service-worker-font-check.json`
- `output/apricot-branding-2026-10-07/metadata-verification.json`
- `output/playwright/apricot-building/building-contrast-report.json`
- `output/playwright/apricot-building/title-recheck-report.json`
- `artifacts/training-mobile/`
- `artifacts/item-sources/`
- `artifacts/mobile-shell/`

Die Dateien unter `output/` und `artifacts/` sind lokale Prüfergebnisse. Die Designentscheidung und die Ergebnisse dieses Berichts bleiben unabhängig von einer späteren Archivierung der Aufnahmen im Projekt dokumentiert.
