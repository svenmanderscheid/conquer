# Finale lokale Alpha-Prüfung · 20. September 2026

## Einordnung

Frontend, Backend, Synchronisation, Kämpfe und mobile Darstellung wurden lokal geprüft und konkrete Fehler korrigiert. Die Änderungen bereiten einen betreuten, geschlossenen Web-/PWA-Alpha-Test vor. Eine Freigabe für einen öffentlichen Start oder die App Stores lässt sich daraus noch nicht ableiten: Zielserver, reale Mobilgeräte und ein längerer Lasttest waren nicht verfügbar.

Es wurde nichts veröffentlicht. Datenbanktests und angemeldete Browserprüfungen arbeiteten mit eigens angelegten, anschließend entfernten Testdatenbanken und synthetischen Konten. Vorhandene Spielstände wurden nicht verändert. Bereits vorhandene Änderungen im Arbeitsverzeichnis wurden beibehalten.

## Umgesetzt

### Kampf und Weltkarte

- Neuer authentifizierter Endpunkt `POST /api/march/preview` und ein sichtbarer **Kampfrechner** im Marschfenster für Monster, Monster-Rallys, Stadtangriffe und Stadt-Rallys.
- Monsterberechnungen verwenden denselben Resolver wie tatsächliche Kämpfe. Aktuelle Monster-LP, eigene Truppen, Kapazität, Boni, Welt, Zielkoordinaten und Ablauf werden serverseitig geprüft.
- Für Stadt-PvP teilen tatsächliche Abrechnung und Vorschau die Stärke-, Sieg- und Verlustregeln. Gleichstand bleibt ein Verteidigersieg. Es wurde kein neues Balancing eingeführt.
- Die PvP-Vorschau rechnet mit eingegebenen Gegnertruppen, gemeinsamem Gegnerbonus und Mauerbonus. Sie verrät keine gegnerische Garnison. Weitere Rally-Teilnehmer, Verstärkungen und besondere Verteidigertalente sind ausdrücklich nicht Teil dieser Beispielrechnung. Bei Monster-Rallys wird nur der eigene Beitrag berechnet.
- Ergebnisse zeigen Überlebende, Verwundete und Gefallene. Sie reservieren keine Truppen, erzeugen keine Kampfberichte und starten keinen Angriff. Freie Hospitalplätze und Änderungen bis zur tatsächlichen Ankunft können das spätere Ergebnis beeinflussen.
- Das ausgewählte Marschziel bleibt erhalten, wenn eine Kartenaktualisierung den sichtbaren Ausschnitt ersetzt. Berechnung und Entsendung prüfen das Ziel anschließend weiterhin serverseitig.
- Die Haupt-App erhielt bisher keine `players`-Liste. Benachbarte Spielerburgen werden jetzt mit öffentlichen Namen, Koordinaten, Erscheinungsbild und Allianz angezeigt. Eigene, versteckte, außerhalb des Ausschnitts liegende und fremden Welten zugehörige Städte werden ausgeschlossen; geschlossene Zonen bleiben verborgen. Bestände, E-Mail-Adressen und sonstige private Kontodaten gehören nicht zur Antwort.

### Darstellung und Bedienung

- Den ausgeblendeten Menüknopf wieder erreichbar im gemeinsamen HUD platziert.
- Hauptmenüs mit definierten, abgerundeten Vektorsymbolen versehen; Allianzaktionen nutzen vorhandene gemalte Spielsymbole. Ressourcenhilfen zeigen die gemeinsamen Ressourcenbilder, Truppen im Marschfenster das passende Stufenporträt.
- Kontrastfehler in Ereigniskalender, aktuellem Tag, Allianzwappen, Chatvorschau und neuen Rechnerfeldern korrigiert.
- Schreinaktionen und Belohnungen abgeschlossener Schreinereignisse nach der Kalenderumstellung wieder zugänglich gemacht. Ein manueller Aktualisierungsknopf bleibt verfügbar.
- Fehlende Wartelistentexte für Deutsch, Englisch und Französisch ergänzt. Anmeldung zur Warteliste, Einwilligung, ungültige Angaben und unveränderte Wiederholungseinträge geprüft.
- Der Kampfrechner hält die Truppenauswahl beim Schließen oder Zurückgehen fest. Auf schmalen Geräten wird nach einer PvP-Berechnung das Ergebnis in den sichtbaren Bereich gescrollt.

### Leistung und Akku

- Eine gemeinsame Abfrageschleife vermeidet überlappende Hintergrundläufe, stoppt bei ausgeblendeter App oder Offline-Zustand und aktualisiert beim Wiederöffnen.
- Die Hauptschleife verwendet 15 Sekunden im ruhigen Zustand und 5 Sekunden auf der Weltkarte beziehungsweise bei laufenden Marsch-, Bau-, Ausbildungs- oder Forschungsaufträgen. Marktdaten werden im geöffneten Markt geladen.
- Die eingebettete Stadt verwendet dieselbe Ablaufsteuerung und führt im nicht sichtbaren Zustand keine eigenen Spielstandsabfragen aus. Unnötige Aktualisierungen von Zeitbeschriftungen werden vermieden.
- Unsichtbare Weltkarte und Stadt stoppen ihre Animationsschleifen. Die Stadt berücksichtigt auch WebGL-Kontextverlust und Wiederaufnahme.
- Für Geräte mit primärer Touch-Eingabe: maximal 30 Bilder pro Sekunde, begrenzte Zeichenauflösung von 1,2 und stromsparende WebGL-Präferenz. Bewegung und reduzierte Animationen bleiben steuerbar.
- Server-Wartezeiten nach HTTP 429 werden bei weiteren lesenden Haupt-App-Anfragen berücksichtigt. Schreibaktionen werden dadurch nicht automatisch erneut ausgeführt.

Dies reduziert nachweislich angeforderte Arbeit. Eine Prozentangabe zur Akkuersparnis wäre ohne Messung auf echten Geräten unbelegt.

### Schutz und Testpflege

- Zusätzlich zu den bereits gesperrten internen Verzeichnissen sind Dokumente und Exporte direkt im Projektstamm nicht mehr öffentlich abrufbar. Ein dort vorhandenes lokales PDF bleibt auf der Festplatte erhalten und liefert über Apache HTTP 403.
- Historische Tests an aktuelle VIP-Boni, Traglasten, Belohnungen, Menüstruktur, Vorgangskennungen und das aktuelle Stadtmodell angepasst. Sicherheitsgrenzen und Spielregeln wurden dafür nicht abgeschwächt. Nur die isolierte Massendarstellungs-Fixture erhält ein höheres Leselimit; Sicherheitstests verwenden die regulären Grenzen.

## Belegte Prüfung

Die finalen Ergebnisse liegen in `artifacts/alpha-final-2026-09-20/`; ältere Fehlversuche bleiben zur Nachvollziehbarkeit erhalten. Für den letzten Stand sind `backend-final.json`, `backend-extra-final.json`, `syntax-final.json` und die jeweiligen `*-current.log` maßgeblich.

| Bereich | Nachweis |
|---|---|
| Backend-Basis | 45 isolierte Testsuiten: Produktion, Bau, Ausbildung, Items, Handel, Boni, Heilung, Rallys, Berichte, Kommunikation, Rechte, Wiederholungen, Warteliste und Kampfrechner |
| Ergänzende Kämpfe und Synchronisation | Stadt-PvP, tatsächliche HTTP-Marschzusammenstellung, Forschungsboni, Dungeons, Verteidigung, Expeditionen, mehrere Welten sowie erneut Karten- und Zugriffsschutz |
| Hauptoberfläche | 110 Menü-/Formatkombinationen, gemeinsame Farben, tatsächliche Almendra-/Lora-Schriftzeichen, Kontrast, fehlende Assets, API- und Browserfehler; Anmeldung und Backoffice zusätzlich |
| Fenster und Marschauswahl | 231 Fensterprüfungen sowie 666 Prüfungen der Marschfenster; scrollbare Allianzaktionen bleiben erreichbar |
| Neuer Rechner in der Haupt-App | Desktop 1280×800, Handy 390×844 und 320×568, Querformat 844×390 und 568×320; Monster- und PvP-Berechnung, CSRF, Authentifizierung, veraltete Welt, Mengengrenzen, Zurück-Verhalten und unveränderte Bestände |
| Daten auf der Karte | Sichtbare Nachbarn vorhanden; eigene/versteckte/fremde/entfernte Städte ausgeschlossen, gesperrtes Zentrum verborgen, nur öffentliche Felder |
| Verlorene Serverantwort | Spähauftrag nach Neuladen mit ursprünglicher Kennung fortsetzbar; tatsächliche Ausbildung, Beschleunigung und Heilung auch auf einem unsicheren lokalen HTTP-Origin im Handyformat |
| Hintergrund und 3D | Keine geprüften Spielstandsabfragen während der simulierten Unsichtbarkeit, anschließende Wiederaufnahme; mobile Auflösungs-/Bildgrenzen; Gesamtansicht und Nahansicht; keine doppelten eingebetteten HUDs |
| Weltanimationen | Bewegungs-, Kontakt- und Aufräumprüfungen bestanden; im gemessenen Marschtest kein erneutes Zeichnen des Geländes während der Bewegung |
| PWA und Sprachen | Geschützte Spiel-/API-Seiten nicht im Offline-Cache; begrenzter statischer Cache, Installation in Unterverzeichnissen, DE/FR/EN und Offline-Rückmeldung |
| Apache | Dokument, Konfiguration, interne Dokumentation und Testartefakt liefern 403; öffentliches Spielsymbol liefert 200 |
| Syntax | 326 PHP- und 223 JavaScript-Dateien ohne Syntaxfehler im abschließenden breiten Prüflauf; spätere Änderungen zusätzlich gezielt geprüft |

Ansichten: `artifacts/alpha-final-2026-09-20/app/` und `artifacts/fantasy-theme/`. Bildschirme wurden auch visuell kontrolliert. Diese Prüfung ist kein Beweis für jeden möglichen Spielzustand oder für reale Gerätegeschwindigkeit. Bestehende MySQL-Namenssperren gelten serverweit; Datenbank-Suiten daher nacheinander starten.

## Vor der Einladung externer Alpha-Spieler

- [ ] **Staging-/Alpha-Server abnehmen:** konkrete URL, HTTPS, sichere Cookies, Produktionskonfiguration ohne Diagnoseausgaben, Zugriffssperren, Fehlerprotokolle und Anmelde-/Wiederherstellungsablauf. Datenbankstand einschließlich Migrationen 0103–0106 prüfen; PHP, Views, JavaScript und Assets gemeinsam ausrollen.
- [ ] **Betrieb ohne offene Browser beweisen:** `cron/march_tick.php` und `cron/world_spawn_tick.php` wie vorgesehen minütlich ausführen und tatsächliche Abrechnung/Spawn nachweisen. Workerfehler und Rückstände beobachten.
- [ ] **Backup wiederherstellen:** Datenbank und erforderliche Konfiguration sichern, Wiederherstellung auf einer getrennten Installation erproben und Rückkehr zur vorherigen Version vorbereiten.
- [ ] **Echte Handys testen:** mindestens ein iPhone und ein Android-Gerät, längere Spielrunde mit Stadt/Karte/Kampf, Wärme und Akku, Hintergrund/Vordergrund, Displaysperre, Netzwechsel und Offline-Phasen, Touch und Bildschirmtastatur in Hoch-/Querformat. Native Capacitor-Pakete sind weiterhin eine spätere Phase.
- [ ] **Last auf dem Zielhost messen:** gleichzeitig angemeldete Tester, reale Abfragefrequenz, Entsendungen, gleichzeitige Kämpfe und gefüllte Historien. Antwortzeiten, Fehler, Sperrwartezeiten, Speicher und Worker-Rückstand protokollieren. Die lokalen Tests belegen keine bestimmte gleichzeitige Spielerzahl.
- [ ] **Testbetrieb organisieren:** begrenzte Einladungen, sichtbarer Fehlerkanal, Versionskennung, bekannte Einschränkungen, Verantwortlicher bei Störungen und klare Aussage zu späteren Spielstand-Resets. Balance und Ressourcenwirtschaft mit Testspielern beobachten.
- [ ] **Inhalte und Konten abnehmen:** Herkunft/Nutzungsrechte für Bilder, Texte und Spieldaten dokumentieren; Datenschutz- und Kontaktangaben sowie einen verlässlichen Auskunfts-/Löschprozess festlegen. Weitere Sprachversionen sprachlich prüfen. Lokale Dokumente und Testartefakte aus dem Veröffentlichungspaket ausschließen.

Echtgeldfunktionen, Zahlungsanbieter und Store-Veröffentlichung wurden in dieser Prüfung nicht freigegeben. Falls sie zum Alpha-Umfang gehören sollen, brauchen sie einen eigenen Ende-zu-Ende-Abnahmelauf.

## Wiederholen

Wichtige selbstständig isolierende Einstiegstests:

```text
php tests/battle_preview.php
php tests/security_guard.php
php tests/army_receipts.php
php tests/alpha_waitlist.php
node tests/app_polling.cjs
node tests/alpha_final_app.cjs
node tests/fantasy_theme_app.cjs
node tests/army_receipts_app.cjs
node tests/training_http_app.cjs
```

Browserprüfungen benötigen Playwright und Chrome; die Tests unterstützen die jeweils dokumentierten Umgebungsvariablen für Laufzeitpfade. Keine alten Tests ungeprüft gegen gespeicherte Spielerkonten oder den öffentlichen Host richten.
