# Lord-Level und Talente

Implementiert am 11. September 2026. Öffnen: Lord-Schaltfläche im HUD oder Spielmenü → Lord-Talente (`/city#mastery`).

## Darstellung und Bedienung

Vier Bereiche mit je neun Talenten: Angriff, Verteidigung, Sammler und Jäger. Die Ansicht verwendet die Dorfpalette aus `storybook-style.js`: Elfenbein, warmes Holzbraun, blaue Dächer und gedecktes Grün. Eigene, weich gezeichnete SVG-Symbole liegen in `assets/icons/talents.svg`. Verbundene Talentkarten zeigen den Rang und direkte Plus-/Minus-Schaltflächen. Gelernte Karten sind grün umrandet, vollständige golden; gesperrte bleiben lesbar.

Punkteanzeige, Bereiche und Speicherleiste bleiben sichtbar, während der Baum scrollt. Ein Klick auf den Talentnamen öffnet Wirkung und Voraussetzungen als Detailfenster. Zurück oder Escape schließt es, ohne den Baum zu versetzen. „Deine Boni“ fasst die Wirkungen aller Bereiche zusammen; das Drei-Punkte-Menü enthält Verwerfen und Neuverteilen. Auf kleinen Bildschirmen bleiben zwei verbundene Spalten erhalten.

Punkte werden zunächst lokal geplant. Erst „Speichern“ übernimmt die gesamte Verteilung. Fehlgeschlagene Anfragen behalten den Plan und verwenden beim Wiederholen dieselbe Vorgangskennung. Eine Versionsnummer verhindert das Überschreiben einer neueren Verteilung aus einem zweiten Fenster. Ein Weltwechsel verwirft verspätete Antworten der alten Welt.

## Fortschritt und Regeln

- Lord- und Jagdlevel sind ein gemeinsamer Fortschritt: Start auf Level 1 mit einem Punkt, Obergrenze 60 mit insgesamt 60 Punkten. Burg und VIP vergeben keine weiteren Talentpunkte.
- Monsterkills geben 10 Jagd-XP × Monsterlevel, Deathkar 20 × Monsterlevel. Kills und XP werden in derselben Transaktion abgerechnet. Der Kampfbericht zeigt die tatsächlich erhaltenen XP; am Levelmaximum gibt es keine zusätzlichen XP.
- Feldzugssiege verteilen einen Pool von 500 × Weltkapitel des Begegnungskatalogs proportional zum tatsächlichen Bossschaden. Es wird pro Spieler abgerundet; reine Anmeldung und Versorgungsbeiträge erhalten keine Jagd-XP. Eindeutige Belege verhindern doppelte Belohnung.
- Die kumulierten alten XP-Schwellen von Level 2 bis 50 bleiben erhalten. Level 1 beginnt jetzt bei null. Die zehn neuen Stufen kosten jeweils den alten Level-50-Schritt × 1,08^(Level−50), auf ganze XP aufgerundet. Werte sind erste Balancingwerte.
- Jeder Rang kostet einen Punkt. Die fünf Reihen verlangen 0 / 5 / 15 / 25 / 35 Punkte in **vorherigen Reihen desselben Bereichs**. Verbundene Vorgänger brauchen Rang 3. Beim Abschlusstalent reicht einer der beiden Vorgänger; zusätzlich ist Lord-Level 40 erforderlich.
- Ein Bereich kostet vollständig 45 Punkte. Ein maximiertes Abschlusstalent ist ab 40 investierten Punkten möglich; zwei Abschlusstalente benötigen mehr als 60 Punkte.
- Punkte entfernen oder umverteilen ist einmal alle 24 Stunden kostenlos. Zusätzliche Punkte zu vergeben verbraucht keinen Reset. Die erste Umverteilung ist sofort verfügbar.
- Eine neue Verteilung kann erst bei zurückgekehrten Armeen übernommen werden. Märsche, Rallys, Feldzugsarmeen, Verstärkungen und Schreingarnisonen werden berücksichtigt. Angekündigte Stadt-Rallys sowie Stadtangriffe mit höchstens zehn Minuten Restzeit sperren ebenfalls den Wechsel.
- Produktion, Mauerregeneration und Aktionspunkte werden vor einem Wechsel mit den bisherigen Werten abgerechnet. Bereits laufende Bau-, Forschungs- und Heilaufträge behalten ihre gesetzten Endzeiten.

## Wirkungen

Der vollständige Katalog mit Namen, Texten, Werten, Voraussetzungen und vorhandenen Spielsymbolen liegt in `data/lord_talents.json`. `MasteryService` validiert den gesamten Plan und liefert die Boni an den bestehenden `BuffEngine`. `TalentEffects` trennt Kampfkontexte und Sammeltraglast.

| Bereich | Angeschlossene Spielwirkungen |
|---|---|
| Angriff | Eigener PvP-Angriff und LP, PvP-Marschtempo, Ausbildung, Marschkapazität, Mauerschaden, eigener Rally-Angriff. PvP-Talente wirken bei Stadtangriffen und gegen besetzte Schreine, nicht gegen Monster. |
| Verteidigung | Eigene Stadtverteidiger und eigene Verstärkungen: Angriff, Verteidigung, LP und Schadensreduktion. Zusätzlich Ressourcenschutz, Heiltempo, Lazarettkapazität und Mauerregeneration. |
| Sammler | Stadtproduktion, Sammeltempo, Sammelmarschtempo, Sammeltraglast, Bau- und Forschungstempo. „Karawanenmeister“ Rang 5 gibt einen ausschließlich zum Sammeln verfügbaren Zusatzplatz; Server und Marschdialog prüfen denselben Grenzwert. |
| Jäger | Monsterangriff, Monster-LP, Jagdmarschtempo, Feldzugsangriff, gewöhnliche Ressourcenbeute, AP-Regeneration und Feldzugskapazität. Spezialdrops, Edelsteine und XP erhalten keinen Beutemultiplikator. |

„Rudeljäger“ verbessert gegenüber dem Entwurf die **Feldzugskapazität** um 2 % pro Rang: Der vorhandene verlustfreie Feldzugskampf besitzt keinen LP-Verbrauch, ein reines LP-Talent hätte dort keine Wirkung. „Rüstzeug“ verbessert die LP bei Weltkartenmonstern. Das bestehende Sammelsystem erntet bei Ankunft; dessen Sammeltempo beschleunigt die Reise, ohne zusätzliche Ressourcen im Feld zu erzeugen. Bau-, Forschungs- und Heiltempo der Talente teilen die bisher berechnete Dauer durch 1 + Talentbonus; bestehende Forschungs- und Reliktformeln bleiben bestehen.

Aktionspunkte bleiben accountweit. `player_ap_regeneration` speichert den zuletzt angewandten Regenerationssatz und Bruchteile eines AP. Bereits verstrichene Zeit wird nicht nachträglich mit einem frisch gewählten Jagdbonus bewertet; bei vollem Vorrat wird keine Regenerationszeit angespart.

## Daten und Migration

`0075_lord_talents.sql` ergänzt `player_lord_progress`, `player_lord_talents`, `lord_xp_receipts` und `player_ap_regeneration`. XP und Talente sind weltbezogen. Bei der einmaligen Übernahme werden bisherige accountweite Jagd-XP der Welt mit der ältesten Stadt des Spielers zugeordnet; weitere Welten starten bei null. Die vorhandenen `players.lord_xp`, `players.lord_level` und `player_masteries` bleiben als Archiv erhalten, wirken aber nicht zusätzlich auf das neue Punktebudget. Neu vergebene XP oberhalb der alten 50er-Grenze gehen nicht verloren. Bereits vorhandene XP über dem neuen Maximum werden ebenfalls erhalten, ohne weitere Punkte zu erzeugen.

Lokales gezieltes Upgrade: `php tools/migrate-lord-talents.php --verify`, anschließend `php tools/migrate-lord-talents.php`. Der erste Aufruf nutzt eine wegwerfbare Datenbank. Die Migration ist wiederholbar und überschreibt keine vorhandenen Lord-Fortschritte. Ein Rollback sollte die neuen Tabellen erhalten, damit neue Jagd-XP und Verteilungen nicht verloren gehen.

## Prüfung

- 200 Prüfungen in `tests/lord_talents.php`: Schwellen, Migration, Welttrennung, Punktebudget, Voraussetzungen, Reset, Versionskonflikte, Idempotenz, AP-Bruchteile, Kampfkontexte und tatsächliche Wirtschafts-/Marschwirkungen.
- `tests/full_progression.php`, `tests/defense_lifecycle.php`, `tests/expedition_lifecycle.php`, `tests/research_effects.php` und `tests/research_live_services.php` bestanden. Monster- und Boss-XP werden auch im tatsächlichen Abrechnungsweg auf wiederholte Verarbeitung geprüft.
- `tests/progression_panel.cjs`: 20 Layoutkombinationen und eine zusätzliche Talentansicht mit 320 px Breite. Geprüft werden direkte Rangänderungen, feste Speicherleiste, Entwürfe beim Bereichswechsel, Detailfenster mit Fokus-/Scrollwiederherstellung, Boni, Verwerfen und Neuverteilen sowie fehlgeschlagene Anfragen, Idempotenz und Navigation während einer laufenden Anfrage.
- `tests/march_windows.cjs`: 444 Prüfungen bestanden, darunter das Freigeben und Sperren des zusätzlichen Sammelplatzes im echten Marschdialog.
- Das Fenster wurde außerdem in der laufenden Haupt-App mit einem isolierten Testspieler geprüft: Lord-HUD, alle vier Bereiche, Talentfreischaltung, Speichern und erneutes Laden. Auch bei 320 px Breite gab es keinen horizontalen Überlauf und keine fehlenden Talentsymbole. Die eingebettete 3D-Stadt wurde in Gesamt- und Nahansicht auf HUD, Gebäudebeschriftungen und bewegte Figuren geprüft; keine Browserwarnungen oder JavaScriptfehler. Testdaten verändern keine echten Spieler.
- Breitere bestehende Tests melden weiterhin Fehler außerhalb des Talentbaums: `city_combat.php` bei der erwarteten Verlagerung eines alten Rohstofffelds; `pve_integration.php` bei der alten 3D-Bridge-Zeichenfolge. Diese beiden Prüfungen sind nicht als vollständig bestanden gewertet.
