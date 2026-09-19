# Conquer – Layout und Funktionsziel

Stand: 9. September 2026. Neue Referenzen des Nutzers. Dies ist das Zielbild für weitere Umsetzung, kein Nachweis bereits fertiger Funktionen.

## Grundlage

Die zehn vom Nutzer gelieferten Dateien sind unverändert unter `design/lok-layout/` gesichert. Die angezeigten Bilder dienen als Layoutreferenz; bei GIFs wurde die im Gespräch sichtbare Darstellung ausgewertet, nicht jede Animationsphase. Der Nutzer möchte mit diesem Material auch den späteren Funktionsumfang verdeutlichen.

Zusätzlich gelesen: [BuLaDiFu: League of Kingdoms, Part Two, 23. April 2021](https://buladifu.blogspot.com/2021/04/league-of-kingdoms-part-two.html). Historische Beschreibung eines anderen Spiels, keine aktuelle Conquer-Spezifikation. Der Artikel erläutert Stadt und Außenwelt, Ressourcen, Aufträge und Navigation. Besonders hilfreich ist seine Kritik am Chat: blockierende Vollbildansicht und springende Scrollposition erschweren die Koordination. Für Conquer sollen neue Nachrichten die Leseposition nicht verschieben; Angriffsalarme müssen auch bei geöffnetem Chat sichtbar bleiben.

Die folgenden Layoutentscheidungen sind aus den gelieferten Bildern abgeleitete Conquer-Vorschläge. Spielwerte auf Screenshots werden nicht übernommen.

## Hochformat und visuelle Richtung

Die Stadt beziehungsweise Weltkarte wird zur bildschirmfüllenden Spielfläche. Kleine, klar erkennbare Spielsymbole mit kurzen Beschriftungen ersetzen die bisherige Vorschau-Anmutung. Unser weicher 3D-Cartoon-Stil bleibt die Grundlage für Gebäude und Vegetation. Die neuen Referenzen konkretisieren die Bedienoberfläche; die Kingshot-orientierten Gebäudeaktionen passen weiterhin dazu.

| Bereich | Umsetzung für Conquer im Hochformat |
|---|---|
| Kopf | Kompaktes Spielerbild mit Name und Macht; Antippen öffnet das Profil. Ressourcen in einer platzsparenden Zeile darunter. |
| Mitte | Möglichst freie, verschiebbare Stadt oder Weltkarte. Gebäude bleiben direkt auswählbar. |
| Gebäude | Name, Stufe und laufende Fortschrittsbalken; nach Antippen kurze Aktionen. |
| Linker Rand | Einklappbare Auftragsübersicht. In der Stadt Bau, Forschung, Training und Heilung; auf der Weltkarte Märsche. Antippen führt zum passenden Gebäude oder Ziel. |
| Rechter Rand | Kleine kontextbezogene Aktionen, etwa Allianz-Hilfe und Events. Nur tatsächlich verfügbare Aktionen anzeigen. |
| Unterer Rand | Fünf Zugänge: Aufgaben, Inventar, Nachrichten, Allianz und Stadt/Welt. Große Touchflächen mit kurzen deutschen Namen. |
| Oberhalb der Navigation | Einklappbare Chatvorschau und ein kompaktes aktuelles Aufgabenziel. Bei Platzmangel hat die Spielfläche Vorrang. |

Der Querformat-Aufbau der Bilder wird nicht einfach verkleinert. Im Hochformat brauchen die Elemente eine neue Anordnung. Zielgröße für Touchflächen mindestens 44 CSS-Pixel; Menüs berücksichtigen die Bildschirmtastatur und sicheren Bildschirmränder.

## Gewünschte spätere Ansichten

Aus den sichtbaren Referenzen ergibt sich folgende Funktionsgliederung für die Planung:

- **Profil:** Spieleridentität, Macht, Allianz, Truppenübersicht und Einstellungen. Weitere Fortschrittssysteme erst nach Festlegung ihrer Regeln.
- **Ressourcen und Inventar:** Ressourcenpakete mit Bestand und Mengenwahl; eigener Bereich für spezialisierte und allgemeine Speedups. Vor Verbrauch klar anzeigen, was ausgegeben und erhalten wird.
- **Aufgaben:** Fortschritt, Ziel und Belohnung; Haupt-, Tages- und Eventaufgaben als übersichtliche Bereiche. Abholbare Belohnungen erhalten einen Hinweis.
- **Nachrichten:** Kampf- und Spähberichte, Systemnachrichten und Belohnungen. Private Gespräche über den Chat erreichbar.
- **Allianz:** Mitglieder, Ränge, Hilfe, gemeinsame Aktivitäten und Shrine-Belohnungsverteilung. R4/R5 dürfen gemäß bestehender Entscheidung an alle Mitglieder verteilen.
- **Welt:** Eigene und fremde Städte, Monster, Ressourcen, Shrine-Ziele und sichtbare Marschwege. Koordinaten, Zielsuche, Lesezeichen und Rückkehr zur eigenen Stadt vorsehen.
- **Chat:** Welt-, Allianz- und private Gespräche; teilbare Ziele und Berichte. Kompakte Vorschau, größere Ansicht bei Bedarf.

Inventar und Profil dürfen eigene größere Ansichten haben. Die Ablehnung der großen Gebäudekarte bedeutet nicht, dass umfangreiche Listen vollständig über einem Gebäude angezeigt werden sollen. Bauinformationen und Timer bleiben direkt in der Stadt.

## Bestehende Entscheidungen und offene Punkte

Bestätigt bleiben: eine dauerhafte Stadt, Hochformat, echte 3D-Gebäude, angekündigte Shrine-Events, spätere Saisonwelten und die vereinbarten Krankenhaus-/Verlustregeln. Spezialisierte Speedups sind farmbar; allgemeine Speedups stammen aus begrenzten Belohnungen. Die alten Excel-Entwürfe bleiben ausgeschlossen.

Das VIP-Bild zeigt ein mögliches Layout für Fortschritt und Belohnungen. Ob Conquer ein VIP- oder anders benanntes Treuesystem erhalten soll und welche Vorteile zulässig wären, ist damit noch nicht entschieden. Kaufbare Macht, Ressourcen oder Beschleunigungen wären mit der bisherigen kosmetischen Monetarisierung abzugleichen. Blockchain, NFT-Land, Fremdspiel-Kosten, Slotzahlen und Freischaltstufen sind keine automatisch übernommenen Anforderungen.

## Nächster Umsetzungsschritt

Zuerst die 3D-Stadt mit dem gemeinsamen Spielrahmen aus Profil, Ressourcen und unterer Navigation ausstatten. Vorhandene Funktionen an echte Ansichten anbinden; fehlende Funktionen eindeutig als noch nicht verfügbar kennzeichnen. Danach Auftragsübersicht und Chatvorschau ergänzen. Anschließend die Weltansicht in denselben Rahmen integrieren.

Abnahme: Die Stadt bleibt auf schmalen Bildschirmen gut erreichbar. Gebäudebalken und Menüflächen überdecken sich nicht dauerhaft. Navigation führt zum angekündigten Ziel. Anzeigen verwenden gespeicherte Spielstände; keine erfundenen Nachrichten, Belohnungen oder Aufträge. Auf dem S23 Ultra anschließend erneut durch den Nutzer prüfen lassen.

Der technische Arbeitsplan für den vollständigen Shrine-Test bleibt bestehen. Dieses Dokument ergänzt dessen Oberfläche und langfristigen Funktionsrahmen; es ersetzt keine Backend-Abnahmen.

## Umsetzungsstand – erster Spielrahmen
Profilzugang, echte Gebäudemacht, vier Ressourcenfelder und fünf Hauptzugänge sind in der 3D-Stadt umgesetzt. Nachrichten führt zu vorhandenen Kampfberichten, Welt zur bisherigen Weltansicht. Aufgaben, Inventar und Allianz zeigen explizite Verfügbarkeitshinweise. Profil enthält Zugang zu Truppen, Forschung, Übersicht, Abmeldung und Messwerten. Chatvorschau und seitliche Auftragsübersicht folgen später.
PHP-/JavaScript-Syntax und Browseransichten bei 390 × 844 geprüft: Profil, Inventarhinweis, Berichte und Weltansicht. Keine Spielaktionen oder Käufe für diese Prüfung ausgelöst. Nur lokale Spielkopien geändert, kein Push oder Deployment.
