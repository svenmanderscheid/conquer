# Design, mobile Bedienung und Alpha-Prüfung · 17. September 2026

## Einschätzung

Die violett-beige Oberfläche passt zur gezeichneten Spielwelt. Inventar, Forschung, Aufgaben und Profil haben bereits eine erkennbare gemeinsame Sprache. Eine neue Palette würde hier wenig verbessern. Priorität haben lesbare Gegenstände, nachvollziehbare Rückmeldungen und erreichbare Aktionen auf kleinen Geräten.

Der Spielkern eignet sich nach Klärung des Testbetriebs für einen betreuten, geschlossenen Pilotversuch. Eine öffentliche Alpha oder eine zugesicherte Zahl gleichzeitiger Spieler ist noch nicht belegt. Als organisatorischer Einstieg sind 10–20 gleichzeitig aktive Tester sinnvoll; das ist ausdrücklich keine gemessene Serverkapazität. Die ausführlichen Kampf-, Betriebs- und Leistungsergebnisse stehen in [ALPHA_COMBAT_AUDIT_2026-09-17.md](ALPHA_COMBAT_AUDIT_2026-09-17.md).

## Umgesetzte Verbesserungen

- **Echte Beute nach Truhenöffnung:** Inventartruhen und tägliche Gratis-Truhen zeigen erhaltene Gegenstände bzw. Reliktfragmente mit Namen, richtigem Bild und Menge. Rohstoffkisten und Fragmentpakete benutzen dieselbe Anzeige. Nur bestätigte Serverergebnisse werden dargestellt.
- **Wiederaufnahme bei Verbindungsabbruch:** Inventarnutzung und Gratis-Truhen speichern eine Vorgangskennung. Eine Wiederholung liefert dieselbe Beute, auch wenn die letzte Truhe bereits verbraucht wurde. Temporäre Sperrkonflikte sind vorübergehende Fehler; sie löschen den offenen Vorgang nicht.
- **Monstericons:** Gegenstandscodes und der gemeinsame Katalog bestimmen das Bild. Die bisherige Namensheuristik konnte eine Goldtruhe fälschlich als Gold zeigen. Auch historische Monsterberichte erhalten beim Lesen fehlende Bildinformationen.
- **Itemhinweise:** Der Katalog hat 192 Definitionen: 166 Verbrauchsitems und 26 Materialien. Zwei Materialien werden beim Gebäudebau verbraucht. Für 24 importierte Referenzitems fehlen konkrete Spielwirkungen, darunter Inhalte zweier Beschleunigerkisten. Diese wurden nicht mit erfundenen Wirkungen versehen; Bestand und verständliche Hinweise bleiben sichtbar. Bei 40 Ressourcenpaketen wurden fehlende Iconangaben ergänzt.
- **Schatzkammer auf dem Handy:** Vier Reliktspalten in einer halben Fensterbreite ergaben auf 390 px nur etwa 31 × 31 px pro Gegenstand. Jetzt sind es zwei größere Spalten; die Bilder bleiben zwischen den Mengen-/Stufenangaben erkennbar. Ausrüstungs- und Reiterschaltflächen sind im Hochformat 40 px hoch. Das sehr kurze Querformat verwendet teilweise 32 px hohe kompakte Aktionen.
- **Langsame Talentabfragen:** Mehrere gleichzeitige Renderanlässe teilen eine laufende Abfrage, statt ihre Antworten wiederholt ungültig zu machen. Ein absichtlich verzögerter Abruf wird zusammen mit erneutem Öffnen geprüft.
- **Lesbare Landstufen:** Der vollständig geladene Landdialog zeigte dunkle Schrift auf Blau mit nur 2,35:1 Kontrast. Die Stufenplakette verwendet nun dunkle Schrift auf dem gemeinsamen Goldbeige.
- **Dungeonberatung:** Zwei Formationsempfehlungen wurden anhand des bestehenden Kampfsimulators an die aktuellen Truppenwerte angepasst; Details im Kampfbericht.

Die Arbeit wurde auf drei abgegrenzte Subagents verteilt (Itembackend, Beuteoberfläche, Kampf/Alpha). Der Hauptagent kontrollierte Verträge, Quellcode und Screenshots, korrigierte die Reliktgrößen und Talentabfragen und verlangte eine weitere Überarbeitung des Beutedialogs, dessen erste Fassung durch eine Erfolgsmeldung überlagert wurde.

## Mobile Bewertung

Die geprüften Kernmenüs sind im Hoch- und Querformat nutzbar. Das bedeutet noch nicht, dass das Spiel auf jedem Handy ausreichend schnell läuft. Die Szene besitzt viele Objekte, Beschriftungen und ständig sichtbare Bedienelemente. Der ausgeklappte Chat nimmt einen erheblichen Teil des kleinen Bildschirms ein; ein späterer Fokus auf die wichtigsten Aktionen könnte das Spielfeld noch beruhigen.

Die 3D-Prüfung lieferte bei 390 × 844 px in der Nahansicht etwa 263.420 Dreiecke und 601 Zeichenaufrufe, bei 844 × 390 px etwa 494.843 Dreiecke und 1.615 Zeichenaufrufe. Das sind Szenenmesswerte eines lokalen Browsertests, keine gemessenen Bildraten auf Mobilhardware. Besonders das Querformat benötigt einen Test auf einem durchschnittlichen Android-Gerät und einem iPhone. App-Wechsel, längere Sitzungen, Speicherverbrauch und Wärmeentwicklung sind dabei noch offen.

## Prüfungen und Grenzen

- 150 Prüfungen der tatsächlichen 3D-Stadtansicht: vier Bildschirmgrößen, Gesamt-/Nahansichten, Kamera, Animation und keine doppelte eingebettete Navigation. Keine Browserfehler.
- Schatzkammer: 34 Prüfungen mit 82 Relikten, fünf Vorlagen und sechs Ausrüstungsplätzen, einschließlich 320 px und 568 × 320 px.
- Fortschrittsfenster: 20 Layoutkombinationen sowie 320-px-Talente, Entwurferhalt, langsamer Abruf, Rückmeldungen und verspätete Antworten.
- Gemeinsame Bildauflösung: sämtliche 192 Item- und 82 Reliktdefinitionen geprüft.
- Bestehende Inventar- und Monsterberichtprüfungen in der Haupt-App bestanden. Die Niederlage-Fixture musste an die aktuelle Siegschwelle angepasst werden; sie prüft weiterhin eine vom echten Kampfsystem berechnete Niederlage.
- 663 Prüfungen der Marschfenster sowie 231 Fensterprüfungen, 26 Itemprüfungen und vier Nutzungsprüfungen bestanden.
- Backend: 729 neue Beute-/Wiederholungsprüfungen, 1.354 Itemprüfungen und 294 Gratis-/Inventartruhenprüfungen bestanden. Dazu Beschleuniger, Post, Monsterberichte und Balanceimport.
- Der Hauptagent wiederholte die 729 Beuteprüfungen und die Stadt-PvP-/Rally-Suite selbst; beide bestanden. Die Kampfprüfung insgesamt umfasst 1.160 Assertions in 16 Suiten plus einem zusätzlichen HTTP-Durchlauf.
- Syntaxkontrolle: 194 PHP-Dateien aus Server, Ansichten und Hintergrundjobs sowie 42 JavaScript-Dateien der Haupt-App ohne Syntaxfehler.
- Der abschließende Menütest bestand **110 Kombinationen aus 22 Hauptbereichen und fünf Bildschirmformaten**, einschließlich vollständig geladener Fensterinhalte. Keine Browserfehler, fehlenden Assets, fehlgeschlagenen Menüabfragen oder erkannten Textkontrast-/Fensterproblemen. Ergebnis und Aufnahmen liegen unter `artifacts/fantasy-theme/`.
- Die Beuteprüfung in der echten Haupt-App verwendet eine verlorene Antwort, Neuladen, erneutes Abrufen, echte Inventar- und Gratis-Truhen, Zurück/Escape und fünf Formate. Aufnahmen unter `artifacts/reward-dialog-review/`.

Alle schreibenden Tests liefen auf wegwerfbaren lokalen Datenbanken. Existierende Spielkonten und Bestände wurden nicht verändert. Die MySQL-Spielersperren sind serverweit; Testdatenbanken mit denselben Spieler-IDs können kollidieren. Nach beobachteten Sperrkonflikten wurden die Datenbankläufe zeitlich abgestimmt. Fehlgeschlagene historische Tests und deren Ursachen bleiben im Kampfbericht nachvollziehbar.

## Vor einer externen Alpha

Benötigt werden eine konkret benannte Testinstallation mit HTTPS, nachgewiesen laufende Hintergrundjobs, Fehlerprotokolle und eine geprüfte Wiederherstellung. Hinzu kommen ein realistischer HTTP-Lasttest auf diesem Host und echte Handys. Für eine öffentliche Alpha ist außerdem der durchgehende Wiederholungsschutz bei Stadt-PvP-/Rally-Entsendungen offen: Ein bestehender Kampf wird nur einmal abgerechnet, aber ein nach verlorener Antwort neu gesendeter Befehl ist nicht in allen Wegen bereits derselbe Vorgang. Ein vollständiges Arena-Duell im Browser und längerfristige Spielbalance sind ebenfalls nicht durch die bisherigen automatisierten Kernprüfungen bewiesen.
