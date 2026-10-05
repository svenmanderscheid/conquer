# Hunter-Talente in Union of Kingdoms

Seit 5. Oktober 2026 verwendet der Talentkatalog Version 2: sechs Sternengitter mit jeweils 13 Knoten, insgesamt 78 Talente und Wegknoten. Das gemeinsame Fenster ist über das Hunter-HUD und Menü → Talente erreichbar.

## Punkte und Fortschritt

Hunter-Level 1 startet mit einem Punkt. Jedes weitere Level gibt einen Punkt; Level 10, 20, 30, 40 und 50 geben jeweils drei Punkte statt einem. Maximallevel ist 50. Die Formel lautet `level + 2 * floor(level / 10)` und ergibt maximal 60 Hunter-Punkte. VIP ergänzt `VIP-Level − 1`, höchstens 19 Punkte. Damit stehen insgesamt höchstens 79 Punkte zur Verfügung. Hunter-XP und VIP-Fortschritt gelten in der jeweiligen Welt.

Die bisherigen XP-Schwellen bis Level 50 bleiben erhalten. Importierte XP oberhalb des Maximums werden nicht gelöscht, erzeugen aber keine zusätzlichen Level oder Punkte. Wiederholte Monsterabrechnungen vergeben dank des bestehenden XP-Belegs keine doppelten XP.

## Verbundene Wege

Jeder Bereich beginnt ausschließlich mit dem oberen Wegknoten (Index 9). Er kostet einen Punkt und öffnet drei Routen. Vier kleine Wegknoten haben jeweils einen Rang; neun Hauptknoten können bis Rang 5 verbessert werden. Ein Rang eines bereits vom Einstieg aus erreichbaren Vorgängers genügt für den nächsten verbundenen Knoten. Es gibt keine Mindestpunktzahl pro Ebene und keine Pflicht, vorher einen Knoten auf Rang 3 oder 5 zu bringen.

Die Verbindung ist gerichtet von oben nach unten. Bei mehreren Vorgängern genügt ein erreichbarer Vorgänger. Punkte können zurückgenommen werden, solange alle verbleibenden gelernten Knoten eine gelernte Route vom Einstieg behalten. Der Meisterknoten (Index 8) verlangt zusätzlich Hunter-Level 40. Alle Pfade, Kosten und Ranggrenzen werden auf dem Server validiert.

## Bereiche und tatsächliche Wirkungen

| Bereich | Wirkungen |
|---|---|
| Infanterie | Angriff, LP, Verteidigung, Ausbildung; Angriff gegen Kavallerie, Widerstand und LP gegen Bogenschützen; eigene Stadtverteidiger und Verstärkungen; Schildwall ab 70 % Infanterie-Grundmacht. |
| Bogenschützen | Angriff, LP, Verteidigung, Ausbildung; Angriff gegen Infanterie, Widerstand und LP gegen Kavallerie; eigener Rally-Beitrag; gezielte Salve ab 70 % Bogenschützen-Grundmacht. |
| Kavallerie | Angriff, LP, Verteidigung, Ausbildung, eigene Traglast; Angriff gegen Bogenschützen, Widerstand gegen Infanterie; schnellerer PvP-Hinweg und Rückweg bei mindestens 70 % Kavallerie-Grundmacht. |
| Monster | Eigener Monster- und Bossangriff, Jagdmarschtempo, AP-Kosten und Regeneration, Monster-LP, gewöhnliche Ressourcenbeute, gezielte Heilung von Monsterwunden; Meisterjäger Rang 5 gibt einen zusätzlichen Solo-Jagdplatz. |
| Kampf | Allgemeine Kampfwerte, eigene Rally-Kampfwerte, Kapazität geführter Rallys, Stadtverteidigung, Heiltempo und eigene Marschkapazität; verbundene Waffen verlangen mindestens 20 % Grundmacht je Truppenart. |
| Sammeln | Abbaurate am Ressourcenfeld, Hin- und Rückreise, eigene Sammeltraglast, Dorfproduktion, Bau- und Forschungstempo, Ressourcenschutz; Karawanenmeister Rang 5 gibt einen zusätzlichen Sammelplatz. |

Prozentwerte im Katalog sind Bruchteile pro Rang. Die Darstellung zeigt den Gesamtbonus des gewählten Ranges. Der kleine Monster-Einstieg „AP-Reserve“ verwendet eine feste Einheit: **+10 maximale AP**, kein Prozentwert. Er füllt die aktuellen AP nicht sofort auf. AP-Regeneration, Rückerstattung und AP-Gegenstände berücksichtigen den höheren Höchstwert.

Konterboni werden mit dem Anteil der jeweiligen gegnerischen Truppenart an ihrer **Grundmacht** gewichtet. Sie gelten für die eigenen Truppen im PvP: Städte, Ressourcenfelder, besetzte Schreine und Territorialkämpfe. Widerstand wirkt auch in der tatsächlichen Verlustberechnung. Eigene Formationstalente richten sich ebenfalls nach Grundmacht, nicht nach bloßer Truppenanzahl. Allgemeine Truppenwerte und passende Formationseffekte erreichen auch Dungeons; die Dungeon-Rollen ordnen Infanterie der Verteidigung, Bogenschützen/Kavallerie dem Angriff, Monster dem Jäger, Sammeln dem Sammler und Kampf beiden Kampfrollen zu. Bestehende Dungeon-Rollen bleiben erhalten.

Rally-Angriffsboni bleiben beim jeweiligen Besitzer seiner Truppen. Mitglieder vervielfachen den Kapazitätsbonus des Anführers nicht. Kavallerie-Reisetalente gelten beim tatsächlichen PvP-Angriff und Rückweg; die Reise eines Mitglieds zum Rally-Anführer bleibt eine eigene Teilnahme-Reise. „Verfolgung“ wirkt nur auf den Hinweg, „Schneller Einsatz“ zusätzlich auf den Rückweg. Die beim Abschicken gespeicherte Rückreisedauer verhindert, dass der Hinwegbonus ungewollt auf den Rückweg übertragen wird.

Monster-AP-Kosten werden serverseitig mit `ceil(baseCost * (1 + talent_monster_ap_cost))` berechnet, bei positiven Grundkosten mindestens 1 AP. Kostenlose Angriffe bleiben kostenlos. Jeder menschliche Teilnehmer einer neuen Monster-Rally bezahlt seinen eigenen rabattierten AP-Betrag. Abgebrochene oder verspätete Teilnahmen erstatten den gespeicherten Betrag genau einmal. Bereits bestehende Teilnahmen ohne Kostenbeleg erhalten keine zusätzliche Erstattung.

Monster-Heilboni gelten ausschließlich für neue, als Monsterwunden gespeicherte Verwundete. Alte Verwundete behalten gewöhnliche Heilung. Eine gemischte Heilung berechnet die Zeit pro Anteil; neue Verwundete treten nicht nachträglich einer laufenden Heilung bei. Allgemeines Heiltempo und Monster-Heiltempo addieren sich für Monsterwunden. Laufende Heil-, Bau- und Forschungszeiten werden durch eine Talentänderung nicht neu berechnet.

Der Beutebonus erhöht ausschließlich gewöhnliche Ressourcen. Kristalle, Gegenstands-Dropchancen, Reliktfragmente und Hunter-XP erhalten keinen Beutemultiplikator. Sammeltraglast verbessert kein PvP-Plündern; Kavallerietraglast gilt dagegen für die eigenen Kavallerietruppen in beiden Situationen.

Die Zusatzplätze sind reserviert: nur Solo-Monsterangriffe beziehungsweise nur Sammeln. Sie ersetzen keine allgemeinen Marschplätze und erlauben keine zusätzliche Rally. Serverprüfung, Marschdialog und HUD berücksichtigen beide Arten.

## Bestehende Talentpläne und sichere Speicherung

`data/lord_talents_legacy.json` bewahrt den vorherigen Vier-Bereiche-Katalog. Gültige alte Pläne bleiben wirksam, bis der Spieler ausdrücklich einen neuen Plan speichert. Die neue Oberfläche zeigt dafür alle verfügbaren Punkte frei und erklärt den Wechsel. Die erste Umstellung kostet nichts und startet keine Umskill-Abklingzeit. Gespeicherte Spielerpläne werden nicht aus der Beispielverteilung des Entwurfs übernommen.

Spätere Rücknahmen oder Umverteilungen bleiben einmal je 24 Stunden kostenlos. Zusätzliche Punkte lassen sich ohne Umskillen hinzufügen. Aktive Armeen, Rallys, Garnisonen, Verstärkungen, Feldzüge und Dungeontruppen sowie unmittelbar bevorstehende Angriffe blockieren die Übernahme. Die bestehenden Welt-, Versions-, CSRF- und Vorgangsbelegprüfungen bleiben wirksam. Ein wiederholter Speicheraufruf nach verlorener Antwort zahlt oder verteilt keine Punkte doppelt. Verstrichene Produktion, Mauererholung und AP-Regeneration werden vor dem Wechsel mit den alten Boni abgerechnet.

## Oberfläche, Bilder und Sprachen

Die Reiter zeigen sechs Bereiche, auf schmalen Hochformaten in zwei Reihen. Das Sternengitter scrollt; Kopf, Reiter und Speicherleiste bleiben erreichbar. Ein Tipp auf einen Knoten öffnet Beschreibung, Wirkung, Rangänderung, Voraussetzungen und nächste Routen. Ein-Punkt-Knoten zeigen ihren kleinen vollständigen Bonus. Verbundene Linien, Ränge, Sperrhinweise und die grüne/goldene Markierung machen den Zustand ohne alleinige Farbcodierung verständlich. Die Detailansicht bewahrt den Entwurf, die Scrollposition und den Fokus.

Alle sichtbaren neuen Texte haben eine vollständige englische Fassung im gemeinsamen Sprachsystem. Deutsch umfasst auch alle 78 Knotennamen, Wirkungen und Beschreibungen. Die französische Oberfläche verwendet die gemeinsame englische Rückfallebene für noch nicht übersetzte Knotentexte. Ohne ausdrückliche Wahl bleibt Englisch die Standardsprache.

`assets/art/talents/painted-v1/` enthält 18 freigegebene transparente WebP-Motive mit 256 × 256 px, zusammen etwa 254 KiB. Die Zuordnung steht im Talentkatalog. Vorlage, transparente Quelle und exakte Bildprompts liegen unter `artifacts/talent-icons-v1/`; `tools/prepare-talent-icons.cjs` schneidet und komprimiert die vorhandenen Zeichnungen. Die Krone des Hunter-Kopfs verwendet weiterhin den bestehenden SVG-Bildsatz. Gemeinsame UI-Farben, Schriften und unsichtbare Browser-Scrollleisten bleiben in `village-theme.css`.

## Datenbank und Prüfung

`0130_hunter_constellations.sql` ergänzt `monster_count`, `monster_healing_count` und `ap_cost_paid` wiederholbar. Bestehende Wunden, aktive Heilzeiten und Teilnahmen bleiben erhalten. Der bestehende weltbezogene VIP-Vertrag benötigt zusätzlich `0129_world_vip.sql`. Beide Schemata sind lokal eingerichtet; vorhandene VIP-Fortschritte in der ursprünglichen Welt und VIP-Gegenstandsmengen wurden beim Upgrade auf Erhalt geprüft. Kein Deployment wurde ausgeführt.

- `tests/hunter_constellations.php` / `tests/lord_talents.php`: 471 Prüfungen in einer wegwerfbaren Datenbank für alle Wege, Punkte, Ranggrenzen, Englische Texte, AP, Monsterwunden, Formation, Konter, Beute, Sonderplätze, Versionskonflikte, Vorgangsbelege und alte Pläne.
- `tests/vip_hunter_points.php`: 35 Prüfungen für das kombinierte Budget und die weltbezogene VIP-Quelle.
- `tests/hunter_constellations_app.cjs`: echte `/city`-App, echte Speicherung und AP-Reserve; 90 Kombinationen aus sechs Bereichen, drei Sprachen und fünf Größen einschließlich 320 px und kurzem Querformat. Aufnahmen und Protokoll: `output/playwright/hunter-constellations/`.
- `tests/vip_hunter_points_app.cjs`: Quellenaufteilung und VIP-Punkte ohne Prozentzeichen in der echten App, drei Sprachen und vier Größen.
- `tests/progression_panel.cjs`: 25 Layoutkombinationen, Rang-Details, unverdeckte Speicherleiste, Entwurf, Rücknahme, Wiederholungsanfragen und verspätete Antworten bei Navigation/Weltwechsel.
- `tests/scout_report_app.cjs`: fünf Bildschirmgrößen, historische neue und alte Talentpläne, korrekte Bildpfade für Talente und Relikte sowie die flache AP-Reserve; zusätzlich Favoriten, Zurück-Navigation und Datenschutz der Verteidigeransicht.
- `tests/march_windows.cjs`: alle neuen Fälle für reservierte Jagdmarschplätze und gerundete AP-Rabatte bestehen. Die gesamte bestehende Prüfung wird wegen elf Desktop-Layoutfehlern nicht als bestanden gewertet: Fenstergrenzen bei 1280 × 720 und die Bestätigung nach Scrollen beim Monster-Rally-Beitritt bei 1280 × 800. Der Vergleich mit dem vorherigen Marschskript und den vorherigen CSS-Dateien reproduziert dieselben Maße; diese Änderungen betreffen die Talentregeln, nicht das Layout des Marschfensters.
- Gezielte bestehende Prüfungen für Hospital, Sammeln, Forschung, Rally-Beitritt/-Kapazität, Dungeon und Territorialkämpfe bestehen. Eine breitere bestehende Fortschrittsprüfung erreicht nach den Talentprüfungen einen Fehler im Reliktfragment-Fixture; sie wird nicht als vollständig bestanden gewertet.

Die mobilen Größen wurden im Browser geprüft. Eine native iOS-/Android-Geräteabnahme steht weiterhin aus.
