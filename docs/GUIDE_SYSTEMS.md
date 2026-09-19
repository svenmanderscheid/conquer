# Umsetzung nach dem LoK-Guide

Stand: 12. September 2026. Erster Umsetzungsschritt zum [Vergleich](LOK_GUIDE_COMPARISON.md).

## Jetzt angeschlossen

- **Monster-Rallies:** Starten, Beitreten, früher Start, Abbruch, gemeinsamer Kampf und Rückkehr benutzen den bestehenden Rally-Lebenszyklus. Die Allianzhalle begrenzt die Gesamtarmee. Nur der Anführer zahlt die AP des Monsterkatalogs; ein Abbruch vor Abmarsch erstattet sie. Monster-Rallies heben den Stadtschutz nicht auf.
- **Eigene Kampfergebnisse:** Boni werden pro Besitzer berechnet. Jeder Teilnehmer erhält einen Bericht mit seinen entsandten Truppen, Verwundeten und Belohnungen. Überlebende und Beute treffen erst bei Rückkehr ein. Wiederholte Abrechnung schreibt nichts doppelt gut. Verschwindet das ursprüngliche Monster, wird kein Ersatzmonster am gleichen Ort angegriffen.
- **Alle acht Kartenkristalle:** Bau, Forschung, Truppen-LP, Angriff, Verteidigung, Traglast, Marschtempo und Sammeltempo werden auf die vorhandenen Bonuskategorien abgebildet. Abgelaufene Effekte werden ausgeschlossen.
- **Burg und Kaserne:** Marschkapazität und Ausbildungsmenge steigen mit der Gebäudestufe. Serverprüfung und Truppenauswahl verwenden dieselben Werte. Ausbaumenüs zeigen den Grundwert vor und nach dem Ausbau.
- **Lager:** Eine feste Grundmenge je Ressource ist vor Plünderung geschützt. Vorhandene prozentuale Boni kommen dazu, begrenzt auf den tatsächlichen Bestand. Produktionslager und Plünderschutz bleiben unterschiedliche Werte und werden entsprechend bezeichnet.
- **Sammeln:** Die langsamste ausgewählte Truppenart bestimmt die Reisezeit. Die Traglast ist die Summe der Katalogwerte aller entsandten Einheiten einschließlich ihrer Boni. Am Ziel arbeitet die Armee bis zur vollen Traglast oder Erschöpfung des Feldes. Ein Rückruf bringt den bisherigen Ertrag heim. Offline-Zeit, Edelsteine, verschwundene Ziele und alte laufende Sammelmärsche werden berücksichtigt.
- **Anzeigen:** Sammelrate, Sammeldauer und Traglast stehen in der Vorschau; aktive Sammler haben eine Endzeit und eine Rückruftaste. Rallies erscheinen einmal in der gemeinsamen Marschliste und belegen entsprechend einen Platz. Karten-/Truppendaten der Haupt-App werden in der ausgewählten Welt gelesen.

## Vorläufiges Balancing

Der Guide beschreibt Mechaniken, aber keine vollständigen Zahlentabellen. Folgende Kurven sind bewusst festgelegte Conquer-Startwerte, keine behauptete exakte Nachbildung von LoK:

| Regel | Grundwert vor Boni |
|---|---|
| Burg, Stufe 1–30 | `5.000 + floor(45.000 × (Stufe − 1) / 29)` Truppen pro Marsch |
| Kaserne, Stufe 1–30 | `500 + floor(4.500 × (Stufe − 1) / 29)` Truppen pro Auftrag |
| Lager | `round(10.000 × 1,2^(Stufe − 1))` geschützte Nahrung, Holz, Stein und Gold; ohne Lager kein Grundschutz |
| Allianzhalle | Stufe 1: 20.000; 5: 50.000; 10: 100.000; 20: 250.000; 30: 500.000 Rally-Truppen, dazwischen linear |
| Sammelarbeit | `10 × Feldstufe` Ressourcen/Sekunde; Edelsteine `0,1 × Feldstufe`; anschließend Sammelboni und Weltfaktor |
| Sammelreise | `floor(Entfernung × 100 / langsamstes effektives Truppentempo)`, mindestens 5 Sekunden; Welt-Tempo wirkt auf die Reise |

Sammeltempo verbessert ausschließlich die Arbeit am Feld. Marschtempo und das Sammelmarsch-Talent verbessern die Reise. Arbeitstempo und Traglast werden beim Abmarsch gespeichert; spätere Ausrüstungs- oder Kristallwechsel verändern diesen Auftrag nicht. Das Feld bleibt bis zur Abrechnung reserviert. Ein offline fälliger Auftrag kann in einem Tick reisen, sammeln und heimkehren.

Die verwendbaren Monster-Rally-Gegenstände stehen in [data/rally_rewards.json](../data/rally_rewards.json). Ältere Monsterdaten enthalten Gegenstandscodes, die nicht mehr zum aktuellen Inventarkatalog passen. Deshalb gibt es explizite Pools für Deathkar, Dragon und Magdar. Ein gemeinsamer Pool wird proportional zu den entsandten Truppenzahlen verteilt; Rundungsreste werden eindeutig zugewiesen. Persönliche Jagd-Beuteboni verbessern anschließend nur die normalen Ressourcen des jeweiligen Besitzers. XP, Edelsteine und Gegenstände werden davon nicht vervielfacht.

## Datenbank und Prüfung

Die Migrationen `0079_monster_rallies.sql` und `0080_gathering_duration.sql` sind auf der lokalen Entwicklungsdatenbank angewendet. Für weitere Installationen: `php tools/migrate-guide-systems.php`. Das Werkzeug akzeptiert nur eine lokale Datenbank. Bestehende Stadt-Rallies und Truppenbestände werden nicht umgeschrieben.

Prüfungen erfolgen mit isolierten Datenbanken bzw. einem synthetischen Vorschaukonto:

- `php tests/monster_rallies.php`: 71 Prüfungen für Rally-Lebenszyklus, persönliche Berichte und Kristalle.
- `php tests/guide_progression.php`: 77 Prüfungen für Progression, Sammeln, Rückruf, Offline-Abrechnung und echte HTTP-Handler.
- Bestehende Prüfungen für Forschungseffekte, Forschungskampf, Lord-Talente, Verteidigung sowie Stadtangriffe/Rallies.
- `node tests/march_windows.cjs`: Truppenwahl, Sammeln und Monster-Rally in fünf Fenstergrößen; 582 Prüfungen. Die Laufzeit benötigt Playwright/Chrome.
- `node tests/guide_world.cjs`: Rally-Monster sind in drei Fenstergrößen sichtbar, als Rally beschriftet und mit der Truppenauswahl verbunden.
- Echte Browseransicht unter `/city#city`: Gesamtansicht und Nähe, laufende Figuren und freie Wege, Baumvarianten, Gebäudenamen, Bedienelemente und kein doppeltes Stadt-HUD. Sammelmarsch und Rückruf wurden im Testkonto ausgeführt.
- Der vollständige Weg Karte → Monster-Rally → Start wurde im eigenen Vorschaukonto geprüft; AP und belegter Marschplatz wurden angezeigt.

Separater Testbefund: `tests/world_footprint.cjs` scheitert in seiner unveränderten Prüfung der sechzehn Dorf-Felder an einem erwarteten Dorf-Menü-Ereignis. Die neuen, gezielten Rally-Kartenprüfungen bestehen. Dieser Befund wird hier nicht als erfolgreiche Gesamtabnahme der Weltkarte gewertet.

## Noch offen aus dem Vergleich

Allianz-Münzen/Shop, funktionaler Wachturm, mehrere unabhängig ausbaubare Gebäude/Außenbauplätze und regionale Landentwicklung sind eigenständige weitere Schritte. Ebenso offen bleiben eine wirksame Hospital-Kapazitätsregel, die vollständige Einführung bis zur Allianzinteraktion sowie Entscheidungen über VIP-Meilensteine, Truhenskalierung und Lord-Levelbelohnungen. Die drei regulären Marschplätze und der zweite Bauplatz ab VIP 4 bleiben vorerst bestehen.
