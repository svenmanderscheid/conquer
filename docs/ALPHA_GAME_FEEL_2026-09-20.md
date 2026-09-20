# Spielgefühl vor dem Alpha-Release

Die fünf vereinbarten Verbesserungen sind lokal umgesetzt. Kein Deployment und keine Änderungen an echten Spielständen.

1. **Aktionen sofort bestätigen:** Der auslösende Knopf zeigt während des Auftrags „Wird bestätigt …“. Doppelte Aufträge bleiben gesperrt; Navigation und Schließen bleiben möglich. Eine verspätete Antwort schließt kein inzwischen neu geöffnetes Dialogfenster.
2. **Ein nächstes Ziel:** Ein kleiner Hinweis führt direkt zu einem offenen Einstiegsziel aus dem vorhandenen Anfangsguide. Ausblenden wird pro Spieler und Welt auf diesem Gerät gespeichert; im Guide lässt sich der Hinweis wieder aktivieren.
3. **Vor dem Angriff:** Laufzeit und Aktionspunkte stehen zusammen mit Verlustprognose, freien Hospitalplätzen und den Folgen für den Stadtschutz in der Marschansicht. Monster werden nach einer kurzen Eingabepause serverseitig berechnet. Bei Spielern bleibt die Rechnung ausdrücklich ein Beispiel anhand eingegebener Gegnerwerte. Rally-Ergebnisse beziehen sich auf den eigenen Beitrag.
4. **Bei der Rückkehr:** Nach mindestens fünf Minuten Abwesenheit zeigt ein unaufdringlicher Hinweis bestätigte Gebäude- und Ausbildungsabschlüsse, Forschungen und abgeschlossene Rückmärsche einschließlich Rallies. Die Übersicht enthält direkte Wege zu den passenden Bereichen und höchstens sieben Tage Verlauf. Keine zusätzliche regelmäßige Hintergrundabfrage.
5. **Weniger Wiederholungen:** Die letzte erfolgreich entsandte Truppenauswahl wird pro Auftragsart, Spieler und Welt auf diesem Gerät gemerkt und an verfügbare Truppen und Kapazität angepasst. „Standard wählen“ stellt die Empfehlung wieder her. Menüfilter bleiben bestehen; relevante Listenpositionen werden beim Wiederöffnen wiederhergestellt. Nach einer Aufgabenbelohnung führt ein Knopf zur nächsten Belohnung oder Aufgabe, ohne automatisch etwas abzuholen.

## Prüfung

- `tests/return_summary.php`: Zeitgrenzen, bestätigte Abschlüsse, Spieler- und Welttrennung, Garnisonen, Rallies und nebenwirkungsfreie Wiederholung.
- `tests/game_comfort_app.cjs`: echte Haupt-App und authentifizierte Schnittstellen in einem automatisch entfernten Testspielstand; langsame Antwort, Zielnavigation und Ausblenden, Rückkehr, Prognose, gemerkte Auswahl, Aufgabenfortsetzung und Scrollposition.
- Ansichten: 1280 × 800, 390 × 844, 320 × 568, 844 × 390 und 568 × 320. Screenshots und Prüfprotokoll: `artifacts/game-feel-2026-09-20/`.
- Bestehende Marsch- und Menüprüfungen sowie Syntax- und Übersetzungsprüfung ergänzen die Ablaufprüfung.

Die Kampfprognose ist eine Momentaufnahme: Zielzustand, Boni und weitere Rally-Mitglieder können sich bis zur Ankunft ändern. Die tatsächliche Berechnung bleibt auf dem Server. Echte iOS-/Android-Geräte und Akkumessungen bleiben Teil der Release-Abnahme aus `ALPHA_FINAL_REVIEW_2026-09-20.md`.

## Musik und Klänge

Auf den zusätzlichen Wunsch hin ergänzt:

- Eigene, vollständig synthetisierte Dorfmusik mit Harfen- und Flötenklang: 38,4 Sekunden, sanfter 3/4-Takt. Eine lokal geladene, wiederholbare WAV-Datei (1,7 MB), keine externen Audiodienste oder fremden Samples. Erzeugung und Herkunft: `assets/audio/README.md`.
- Kurze Klänge für bestätigte Aufträge, Ausbildung, Ausbau, Forschung, Belohnungen sowie fertige Gebäude und Truppen. Schreibende API-Aufträge spielen Erfolgstöne erst nach einer erfolgreichen Serverantwort; reine Zustandsabfragen, Chat und Kampfprognosen bleiben stumm. Abschlüsse während langer Abwesenheit werden nicht nachträglich vertont.
- Menü → Optionen → Musik & Klänge: getrennte Schalter und Lautstärken, Gesamtstummschaltung und Hörproben. Voreinstellung Musik 20 %, Effekte 45 %. Einstellungen bleiben auf diesem Gerät gespeichert und sind auf Deutsch, Englisch und Französisch verfügbar.
- Kein Audioladen vor der ersten Interaktion. Ein AudioContext, einmaliges Dekodieren, native Schleife ohne JavaScript-Musiktimer. Verborgene Seiten, Seitenwechsel und Stummschaltung stoppen den Ton und suspendieren den AudioContext. Bei fehlender Musik bleiben die Effekte verfügbar; kein automatischer Wiederholungssturm.
- Nebenfund im Handytest: Die Bauaktionen lagen teilweise auf der ersten Rohstoffanzeige. Der Abstand ist korrigiert; auf kurzen Hochformatbildschirmen stehen die vier Auftragsknöpfe platzsparend in zwei Reihen.

`tests/game_audio_app.cjs` prüft reale Ausbildungs- und Bauaufträge im isolierten Spielstand, ausstehende Serverantworten, Audiostart nach Touch, Aufräumen der Effekte, gespeicherte Lautstärken/Stummschaltung, Hintergrund/Pause/Wiederaufnahme einschließlich schneller Seitenwechsel, Abschlussmeldungen, Ladefehler und fehlende Web-Audio-Unterstützung. Dazu kommen Übersetzungen und dieselben fünf Bildschirmgrößen. Protokoll und Screenshots: `artifacts/audio-2026-09-20/`. Die Audiodatei enthält keine übersteuerten Samples; Schleifengrenze und Laufzeit sind numerisch geprüft.

Der automatische Browserlauf prüft Wiedergabezustände und Bedienung. Die klangliche Abnahme über echte Lautsprecher/Kopfhörer sowie iOS-/Android-Unterbrechungen durch Anrufe bleiben vor Veröffentlichung erforderlich.
