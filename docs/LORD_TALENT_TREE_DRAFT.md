# Lord-Level und Talentbaum – Entwurf

Stand: 11. September 2026. Historischer Designvorschlag. Die implementierten Regeln und Abweichungen sind in [LORD_TALENTS.md](LORD_TALENTS.md) dokumentiert; der verbindliche Katalog steht in `data/lord_talents.json`.

## Ausgangslage

- `src/Game/Player/LordLevel.php` enthält ein XP-basiertes Lord-Level mit Obergrenze 50. Ein eigenes Hunter-Level wurde im geprüften Spielcode nicht gefunden.
- `src/Game/March/MarchTick.php` vergibt für einen bestätigten Monsterkill derzeit Monsterlevel × 10 Lord-XP; Deathkar erhält dort den Faktor × 20.
- `src/Game/Player/MasteryService.php` vergibt derzeit Burgstufe minus 1 Punkte für sechs einfache Talente. Diese Vergabe ist vom Lord-Level unabhängig.
- `docs/SPEC.md` erwähnt ein späteres Mastery-System und VIP-Punkte dafür. Eine verbindliche Ausarbeitung des hier gewünschten Talentbaums bis Level 60 wurde in den geprüften Spezifikationen nicht gefunden. Der vorliegende Vorschlag ersetzt diese älteren Punktequellen.

## Empfohlenes Fortschrittsmodell

**Ein gemeinsames Lord-/Jagdlevel bis 60**, gespeist durch Jagd-XP. „Jagd-XP“ bezeichnet die Erfahrung, „Talentpunkte“ die ausgebbaren Punkte. Eine zweite, parallel laufende Hunter-Leiste wäre damit überflüssig. Falls zwei getrennte Fortschritte beabsichtigt sind, ist diese Annahme vor der Implementierung anzupassen.

- Start auf Lord-Level 1 mit einem Talentpunkt. Jeder weitere Levelaufstieg gibt genau einen zusätzlichen Punkt. Level 60 erlaubt insgesamt 60 Punkte, auch bei mehreren Levelaufstiegen durch eine Belohnung.
- Punkte werden aus dem erreichten Level abgeleitet; kein mehrfaches Ausschütten bei wiederholter Verarbeitung. Burgstufe und VIP geben keine zusätzlichen Talentpunkte.
- Normale Jagd: als vorläufige Ausgangsbasis die vorhandenen 10 XP × Monsterlevel beibehalten. Boss-XP anhand Schwierigkeit und tatsächlicher Teilnahme vergeben. Die finale XP-Kurve wird anhand des gewünschten Tempos festgelegt; die alte 50er-Kurve nicht lediglich durch Austausch der Maximalzahl erweitern.
- Bei kooperativen Bossen belohnt ein definierter XP-Pool echte Beteiligung. Kein Bonus für den letzten Treffer, keine pauschale Belohnung bloß angemeldeter Teilnehmer. Wiederholtes Aufrufen einer Abrechnung erzeugt weder zusätzliche XP noch Punkte.
- Kein Talent erhöht Jagd-XP. Die Jagdspezialisierung verbessert Jagdeffizienz und gewöhnliche Beute, ohne einen unmittelbaren XP-Multiplikator zu geben.
- Lord-Level und Talentverteilung sollten für jede Welt getrennt sein. So startet ein Veteran eine neue Welt ohne die vollständigen Kampfboni seiner alten Welt. Dies ist eine bewusste Änderung gegenüber den derzeit accountweiten Lord-Feldern.
- Auf Level 60 enden Erfahrung und Punktgewinne. Normale Beute und gegebenenfalls getrennte Eventpunkte bleiben erhalten. Spätere Levelerweiterungen brauchen ein neues Punktebudget und Balancing.

## Punkte und Freischaltungen

Vier Bereiche: **Angriff**, **Verteidigung**, **Sammler/Dorf**, **Jäger**. Jeder Bereich besitzt neun Talente mit jeweils fünf Rängen: acht reguläre Talente und ein Abschlusstalent. Jeder Rang kostet einen Punkt. Alle Talente zusammen benötigen 180 Punkte; der Spieler erhält maximal 60.

| Ebene | Talente je Bereich | Punkte in vorherigen Ebenen dieses Bereichs | Zusätzliche Voraussetzung |
|---|---:|---:|---|
| Grundlagen | 2 × 5 Ränge | 0 | Lord-Level 1 |
| Spezialisierung | 2 × 5 Ränge | 5 | Direkt verbundenes Talent auf Rang 3 |
| Erfahrung | 2 × 5 Ränge | 15 | Direkt verbundenes Talent auf Rang 3 |
| Meisterschaft | 2 × 5 Ränge | 25 | Direkt verbundenes Talent auf Rang 3 |
| Abschlusstalent | 1 × 5 Ränge | 35 | Lord-Level 40; mindestens ein Talent der vorherigen Ebene auf Rang 3 |

Die beiden Spalten bilden nachvollziehbare Verbindungspfade. Punkte aus späteren Ebenen zählen nicht rückwärts zur Freischaltung früherer Ebenen. Ein Punkt kann nur entfernt werden, wenn alle bereits gelernten Folgetalente gültig bleiben.

Ein Abschlusstalent kostet mindestens 36 Punkte einschließlich Voraussetzungen; Rang 5 benötigt mindestens 40 Punkte. Zwei Abschlusstalente wären mit mindestens 72 Punkten verbunden und sind bei Level 60 ausgeschlossen. Ein vollständiger Bereich kostet 45 Punkte; alternativ sind 40 Punkte im Hauptbereich und 20 Punkte im Nebenbereich möglich. Eine Verteilung von 30/30 verzichtet auf Abschlusstalente und erhält dafür eine breitere Auswahl mittlerer Talente.

## Talente und vorläufige Werte

Die Prozentwerte sind additive Talentbeiträge zum jeweiligen Bonus und vorläufige Testwerte. Tempo ist nicht gleich Zeitverkürzung: beispielsweise reduziert +10 % Tempo die Dauer auf 1 / 1,10. Kampfkontexte müssen eingehalten werden; ein PvP-Bonus gilt nicht gegen Monster. Rally-Boni verstärken ausschließlich die eigenen Truppen.

| Ebene | Angriff: Talent | Pro Rang | Maximum |
|---|---|---:|---:|
| 1 | Waffenschule: Angriff angreifender Truppen im PvP | +1 % | +5 % |
| 1 | Marschdisziplin: Tempo von PvP-Märschen | +2 % | +10 % |
| 2 | Rekrutenausbildung: Ausbildungstempo | +2 % | +10 % |
| 2 | Schlachtordnung: eigene Truppen-LP beim PvP-Angriff | +1 % | +5 % |
| 3 | Rammböcke: Mauerschaden nach erfolgreichem Stadtangriff | +3 % | +15 % |
| 3 | Große Heere: Truppenkapazität je Marsch | +1 % | +5 % |
| 4 | Entschlossener Angriff: PvP-Angriff | +1 % | +5 % |
| 4 | Rally-Taktik: Angriff eigener Truppen in PvP-Rallys | +1 % | +5 % |
| 5 | Eroberer: PvP-Angriff | +2 % | +10 % |

| Ebene | Verteidigung: Talent | Pro Rang | Maximum |
|---|---|---:|---:|
| 1 | Schutzwall: Verteidigung eigener Truppen in Städten | +1 % | +5 % |
| 1 | Vorratsverstecke: geschützte Ressourcenmenge | +2 % | +10 % |
| 2 | Standhafte Wachen: eigene Truppen-LP bei Stadtverteidigung | +1 % | +5 % |
| 2 | Heilkundige: Heiltempo | +2 % | +10 % |
| 3 | Ausgebaute Lazarette: Hospitalkapazität | +3 % | +15 % |
| 3 | Wiederaufbau: natürliche Mauerregeneration | +3 % | +15 % |
| 4 | Verbundene Schilde: Verteidigung eigener Truppen in Städten | +1 % | +5 % |
| 4 | Heimvorteil: eigener Angriff bei Stadtverteidigung | +1 % | +5 % |
| 5 | Bastion: eingehender PvP-Schaden bei Stadtverteidigung | −1 % | −5 % |

Stadtverteidigung umfasst auch eigene Verstärkungen in einer verbündeten Stadt. Hospitalkapazität rettet keine bereits verlorenen Truppen rückwirkend. Ressourcen können niemals über ihren tatsächlichen Bestand hinaus geschützt sein.

| Ebene | Sammler/Dorf: Talent | Pro Rang | Maximum |
|---|---|---:|---:|
| 1 | Erntemeister: Sammeltempo | +2 % | +10 % |
| 1 | Vorratswirtschaft: Dorfproduktion | +2 % | +10 % |
| 2 | Traglast: Tragfähigkeit beim Sammeln | +3 % | +15 % |
| 2 | Bauleitung: Bautempo | +2 % | +10 % |
| 3 | Pfadkenntnis: Tempo von Sammelmärschen | +2 % | +10 % |
| 3 | Gelehrtenrat: Forschungstempo | +2 % | +10 % |
| 4 | Feldarbeit: Sammeltempo | +2 % | +10 % |
| 4 | Planvolle Entwicklung: Dorfproduktion | +2 % | +10 % |
| 5 | Karawanenmeister: Sammeltempo | +2 % | +10 % und auf Rang 5 ein zusätzlicher Sammelmarschplatz |

Der zusätzliche Platz erlaubt ausschließlich Sammeln. Er ist kein Kampf-, Rally- oder Transportplatz. Sammelboni verändern weder die maximale Menge eines Rohstofffelds noch Edelstein-Dropraten. Dorfproduktion umfasst Nahrung, Holz, Stein und Gold.

| Ebene | Jäger: Talent | Pro Rang | Maximum |
|---|---|---:|---:|
| 1 | Bestienkunde: Angriff gegen Monster/PvE-Bosse | +2 % | +10 % |
| 1 | Fährtenleser: Jagdmarschtempo | +3 % | +15 % |
| 2 | Rüstzeug: eigene Truppen-LP gegen Monster/PvE-Bosse | +1 % | +5 % |
| 2 | Großwildjagd: Angriff eigener Truppen in PvE-Rallys | +2 % | +10 % |
| 3 | Fellhändler: gewöhnliche Ressourcenbeute | +2 % | +10 % |
| 3 | Jagdausdauer: AP-Regeneration | +2 % | +10 % |
| 4 | Monsterbrecher: Angriff gegen Monster/PvE-Bosse | +2 % | +10 % |
| 4 | Rudeljäger: eigene Truppen-LP in PvE-Rallys | +2 % | +10 % |
| 5 | Meisterjäger: Angriff gegen Monster/PvE-Bosse | +2 % | +10 % |

Fellhändler erhöht weder Jagd-XP noch Edelsteine, Fragmente oder Ereignispunkte. Jagdausdauer verkürzt das zeitbasierte AP-Regenerationsintervall, anstatt kleine ganzzahlige AP-Kosten abzurunden. Jeder Rang soll wirken; Bruchteile der Regenerationszeit bleiben erhalten.

## Beispielverteilungen

- Eroberer: 40 Angriff / 20 Verteidigung; offensive Spezialisierung mit Grundschutz.
- Stadtwächter: 40 Verteidigung / 20 Sammler; standhafte Garnison und schnellerer Ausbau.
- Versorger: 40 Sammler / 20 Verteidigung; zusätzlicher Sammelmarsch und Ressourcenschutz.
- Monsterjäger: 40 Jäger / 20 Sammler; gute PvE-Leistung mit tragfähiger Dorfwirtschaft.
- Mischform: 30 Angriff / 30 Verteidigung; breite mittlere Talente, kein Abschlusstalent.

Für die 40/20-Beispiele sind im Hauptzweig die Ränge `[5,5,5,5,5,5,5,0,5]` und im Nebenzweig `[5,5,5,5,0,0,0,0,0]` gemeint. Die ausgelassene Fähigkeit der vierten Ebene bleibt eine echte Wahl; bei Bedarf kann die andere Spalte zum Abschluss führen.

## Oberfläche und Wechselregeln

- Lord-Level und Jagd-XP-Fortschritt neben dem Spielerporträt; Hinweis auf freie Talentpunkte. Klicken öffnet den Talentbaum.
- Vier Bereichsreiter mit bereits investierten Punkten. Verbundene Talentkarten zeigen Rang, Maximalbonus und Freischaltung. Details unterscheiden aktuelle Wirkung, nächsten Rang und Voraussetzungen.
- Änderungen zunächst planen, erst mit „Verteilung übernehmen“ gemeinsam speichern. Die beiliegende Vorschau ist ausschließlich eine lokale Planung und speichert nichts im Spiel.
- Ein kostenloser vollständiger Wechsel pro 24 Stunden als erste Balancing-Regel. Keine zusätzlichen Punkte durch Bezahlen oder VIP. Die Alpha kann diese Sperre zum Testen abschalten.
- Wechsel nur ohne eigene laufende Märsche, Rally-Teilnahmen und auswärts stationierte Verstärkungen; nicht bei bevorstehendem feindlichem Eintreffen. Ein konfigurierbarer Vorlauf von 10 Minuten verhindert sofortiges Umskillen vor einem sichtbaren Angriff. Bei Verlust des zusätzlichen Sammelplatzes muss dieser unbenutzt sein.
- Laufende Bau-, Forschungs- und Heilaufträge behalten ihre bei Start festgelegten Zeiten. Stadtproduktion wird vor dem Wechsel bis zum aktuellen Zeitpunkt mit alten Boni abgerechnet; ab dann gelten neue Boni. Keine rückwirkenden Vorteile.
- Keine Löschung von Verwundeten durch geringere Hospitalkapazität nach einem Wechsel; nur Neuaufnahmen berücksichtigen das neue Limit.

## Umsetzung nach Freigabe des Entwurfs

1. Weltbezogenen Lord-Fortschritt und 60-Level-Kurve ergänzen. Bestehende XP vollständig erhalten; Migration alter Punkte und bestehender Weltzuordnungen ausdrücklich festlegen, keine unbemerkten XP-Kopien auf frisch beigetretene Welten.
2. Burgbasierte Meisterschaft durch den Talentkatalog ersetzen. Alte Verteilung zurückgeben, nur das neue Lord-Punktebudget zulassen und Übernahme den Spielern erklären.
3. XP auf bestätigte Kill-/Boss-Abrechnungen mit eindeutiger Ereigniskennung und Transaktion buchen. Kooperative Kämpfe und Rückkehrfälle berücksichtigen.
4. Alle 36 Boni an echte Berechnungen anschließen. Ein angezeigter Bonus ohne Spielwirkung gilt nicht als fertig. Prozentbruchteile nicht auf Ganzzahlen kürzen; alle Reduktionen begrenzen.
5. Vorschau und gespeicherte Verteilung trennen; Punkte, Voraussetzungen, Welt, Wechselbedingungen und parallele Anfragen serverseitig prüfen.
6. Mit Angriff/Verteidigung und PvE/PvP getrennt testen: Bonuswirkung, Weltisolation, doppelte Belohnungen, Kapazitätsänderungen, Punktentzug, Rangvoraussetzungen und Level-60-Grenze.

Reservierte Treasure-Dateien, BuffEngine-Änderungen anderer Arbeiten und Migration 0074 wurden für diesen Entwurf nicht verändert.
