# Truppenfiguren: Produktionsplan

## T10 Drachenstahl – eingebundene Fassung, 16. September 2026

- `assets/city3d/dragonsteel-knight.js` baut die freigegebene Figur samt Referenzschwert, bereinigtem Schild und identischen Panzerhandschuhen. Trainingsansicht und Review verwenden dasselbe Modul.
- `dragonsteel-motion.js`: deterministische Stehpose mit Atmung, Blick und Wachbewegung, geschlossener Laufzyklus sowie kurze Kampfsequenz. Reduzierte Bewegung hält die Bereitschaftspose; die Trainingsansicht pausiert im Hintergrund und gibt Ressourcen beim Schließen frei.
- T10 in `training-portrait.js` ist eine drehbare 3D-Ansicht. T2–T9 behalten ihre bisherigen Bilder.
- `infantry-t10-ui-v2.png` (768 px), `infantry-t10-report-v2.png` (512 px), `infantry-t10-thumb-v2.webp` (192 px) sind aus derselben Figur gerendert. Trainingsauswahl, Spieler- und Monsterberichte verwenden die neuen Dateien.
- Review: `/artifacts/dragonsteel-review/` mit Stehpose/Laufen/Kampf und Bildexport. Für reproduzierbaren lokalen Dateiexport `node tools/receive-dragonsteel-renders.cjs` starten und die Review mit `?export=local` öffnen; der ausschließlich an Loopback gebundene Empfänger beendet sich nach drei Bildern oder fünf Minuten.
- `tools/optimize-dragonsteel-assets.cjs` erzeugt separate Laufzeitdateien mit 1024-px-Texturen; Originaldateien bleiben erhalten. Körper rund 2,1 MB, Schild rund 12,3 MB. Das Schild benötigt vor einem mobilen Release noch eine Polygonreduktion; dies ist kein fertig optimierter Masseneinheiten-Asset.
- Sichtprüfung im echten Training: Desktop, 390×844, 320×568 und 844×390. Ein echter Mobilgerätetest steht aus.

## Neue Infanterie-Zuordnung T1–T10

Die spielbaren Infanteriestufen verwenden die zuvor als Materialstufen 3–12
entworfene Reihe. Stoff und Leder bleiben außerhalb der regulären T-Stufen für
Rekruten, Milizen und zivile Figuren reserviert.

| Spielstufe | Material | Meshy-A-Pose | Schwert | Schild |
|---|---|---|---|---|
| T1 | Kette | `infantry-t1-chainmail-meshy-apose-v1.png` | `infantry-t1-chainmail-sword-meshy-v1.png` | `infantry-t1-chainmail-shield-meshy-v1.png` |
| T2 | Bronze | `infantry-t2-bronze-meshy-apose-v1.png` | `infantry-t2-bronze-sword-meshy-v1.png` | `infantry-t2-bronze-shield-meshy-v1.png` |
| T3 | Eisen | `infantry-t3-iron-meshy-apose-v1.png` | `infantry-t3-iron-sword-meshy-v1.png` | `infantry-t3-iron-shield-meshy-v2.png` |
| T4 | Stahl | `infantry-t4-steel-meshy-apose-v1.png` | `infantry-t4-steel-sword-meshy-v1.png` | `infantry-t4-steel-shield-meshy-v1.png` |
| T5 | Silber | `infantry-t5-silver-meshy-apose-v1.png` | `infantry-t5-silver-sword-meshy-v1.png` | `infantry-t5-silver-shield-meshy-v2.png` |
| T6 | Gold | `infantry-t6-gold-meshy-apose-v1.png` | `infantry-t6-gold-sword-meshy-v1.png` | `infantry-t6-gold-shield-meshy-v1.png` |
| T7 | Platin | `infantry-t7-platinum-meshy-apose-v1.png` | `infantry-t7-platinum-sword-meshy-v1.png` | `infantry-t7-platinum-shield-meshy-v1.png` |
| T8 | Diamant | `infantry-t8-diamond-meshy-apose-v1.png` | `infantry-t8-diamond-sword-meshy-v2.png` | `infantry-t8-diamond-shield-meshy-v1.png` |
| T9 | Mithril | `infantry-t9-mithril-meshy-apose-v1.png` | `infantry-t9-mithril-sword-meshy-v1.png` | `infantry-t9-mithril-shield-meshy-v1.png` |
| T10 | Drachenstahl | `infantry-t10-dragonsteel-meshy-apose-v1.png` | `infantry-t10-dragonsteel-sword-meshy-v1.png` | `infantry-t10-dragonsteel-shield-meshy-v1.png` |

Alle Dateien in dieser Tabelle liegen unter `assets/art/characters/`.

Alle zehn Vorlagen zeigen dieselbe Figur frontal und waffenlos in einer sauberen
A-Pose. Schwert und Schild werden als getrennte 3D-Objekte erzeugt und später an
den Handknochen befestigt, damit Auto-Rigging und Laufanimationen die Ausrüstung
nicht mit Armen, Rüstung oder Beinen verschmelzen.

## Modellfamilien

Je Truppengattung entstehen fünf Grundmodelle. Zwei benachbarte T-Stufen teilen Skelett und Grundgeometrie, unterscheiden sich aber durch Textur und ein klar sichtbares Ausrüstungsteil.

| Stufen | Rolle | Sichtbare Entwicklung |
|---|---|---|
| T1–T2 | Rekrut | Stoff, Leder, Holz, einfache Waffe |
| T3–T4 | Soldat | erste Metallteile, besserer Schild |
| T5–T6 | Veteran | geschlossene Rüstung, Rollenfarben |
| T7–T8 | Elite | markante Silhouette, verzierte Ausrüstung |
| T9–T10 | Legendär | stärkste Form, Goldakzente, keine kleinteilige Überladung |

Infanterie und Bogenschützen sollen möglichst dasselbe humanoide Skelett und denselben Kernanimationssatz verwenden. Kavallerie benötigt wegen des Reittiers eine eigene Familie.

## Verbindliche Ausgaben je T-Stufe

- optimierte GLB für Trainingsansicht und 3D-Spielwelt
- quadratisches Einheitenporträt für Auswahl, Bestand und Formation
- kleines Rangbild für T1–T10-Auswahl
- Berichtsbild für Kampf- und Monsterberichte
- transparente Ganzkörperansicht für weitere Interfaceflächen

Die 2D-Ausgaben werden nach Abnahme aus demselben 3D-Modell mit festen Kameras und Licht gerendert. Dadurch bleiben Gesicht, Ausrüstung, Farben und Stufenerkennung in Spiel, Interface und Berichten identisch.

## Technische Ziele

- etwa 5.000–12.000 Dreiecke pro Figur
- eine reduzierte 1024-Pixel-Farbtextur, bei Bedarf 512 Pixel für mobile Ansichten
- ungefähr 3–5 MB je optimierter GLB
- gemeinsame Animationen: Stand, Wachroutine, Gehen, Laufen, Angriff, Schildblock, Treffer und Sieg
- keine separate Daueranimation in Listen oder kleinen Berichtsbildern

## Aktueller Startpunkt

Der T1-Rekrut ist als Stilanker freigegeben: braunes Haar, blaue Stofftunika, Leder, kleines Holzschild und schlichtes Eisenschwert. Die Produktionsdateien sind:

- `assets/art/characters/infantry-t1-concept-v1.png` – freigegebene Kampfpose und Designreferenz
- `assets/art/characters/infantry-t1-apose-v1.png` – waffenlose A-Pose für sauberes Auto-Rigging
- `assets/art/characters/infantry-t1-ui-v1.png` – transparente Ganzkörpergrafik für die Trainingsansicht
- `assets/art/characters/infantry-t1-report-v1.png` – quadratisches Porträt für Kampf- und Monsterberichte

T2 führt dieselbe Figur sichtbar weiter: tiefere blaue Tunika, ein verstärkter Leder-Schulterschutz, ein stabileres Eisenschwert und ein eisenbeschlagenes Holzschild. Die Produktionsdateien sind:

- `assets/art/characters/infantry-t2-concept-v1.png` – Designreferenz der zweiten Infanteriestufe
- `assets/art/characters/infantry-t2-ui-v1.png` – transparente Ganzkörpergrafik für die Trainingsansicht
- `assets/art/characters/infantry-t2-report-v1.png` – quadratisches Porträt für Kampf- und Monsterberichte

T3 ist der erste klar erkennbare Ritter derselben Figurenlinie: offener Eisen-Stirnreif mit Nasenschutz, Kettenzeug, kompakte Schulterplatten, blauer Waffenrock und ein höheres, eisenbeschlagenes Schild. Haare und Gesicht bleiben bewusst sichtbar. Die Produktionsdateien sind:

- `assets/art/characters/infantry-t3-concept-v1.png` – Designreferenz der dritten Infanteriestufe
- `assets/art/characters/infantry-t3-ui-v1.png` – transparente Ganzkörpergrafik für die Trainingsansicht
- `assets/art/characters/infantry-t3-report-v1.png` – quadratisches Porträt für Kampf- und Monsterberichte

T4 entwickelt ihn zum gepanzerten Wächter mit Wangenplatten, breiteren Schultern und einem hohen Verteidigungsschild. T5 ergänzt Brustpanzer, goldene Beschläge und einen kurzen blau-cremefarbenen Halbmantel zum Kreuzritter, ohne bereits wie eine Endspiel-Einheit zu wirken.

- `assets/art/characters/infantry-t4-concept-v1.png`, `infantry-t4-ui-v1.png`, `infantry-t4-report-v1.png`
- `assets/art/characters/infantry-t5-concept-v1.png`, `infantry-t5-ui-v1.png`, `infantry-t5-report-v1.png`

Die zweite Entwicklungsreihe ist ebenfalls vollständig. T6 betont den erfahrenen Schildkampf, T7 die schwere Gardistenrüstung, T8 die verfeinerte Eliterüstung, T9 die königliche blau-violette Garde und T10 den goldgefassten Kronenwächter. Gesicht und charakteristische braune Haare bleiben über alle zehn Stufen erhalten.

- `assets/art/characters/infantry-t6-concept-v1.png`, `infantry-t6-ui-v1.png`, `infantry-t6-report-v1.png`
- `assets/art/characters/infantry-t7-concept-v1.png`, `infantry-t7-ui-v1.png`, `infantry-t7-report-v1.png`
- `assets/art/characters/infantry-t8-concept-v1.png`, `infantry-t8-ui-v1.png`, `infantry-t8-report-v1.png`
- `assets/art/characters/infantry-t9-concept-v1.png`, `infantry-t9-ui-v1.png`, `infantry-t9-report-v1.png`
- `assets/art/characters/infantry-t10-concept-v1.png`, `infantry-t10-ui-v1.png`, `infantry-t10-report-v1.png`

Für die zehn kleinen Rangschaltflächen werden zusätzlich die mobil optimierten Dateien `infantry-t1-thumb-v1.webp` bis `infantry-t10-thumb-v1.webp` verwendet. Sie sind jeweils 192 × 192 Pixel groß und werden nur auf den Infanterie-Kacheln geladen.

Alle zehn Körpermodelle sind in Meshy texturiert und humanoid geriggt. Das gemeinsame Spielpaket verwendet `Gehen`, `Laufen` und `Kampf Leerlauf`. Der verbindliche Export ist GLB mit `MeshyRig`, eingeschaltetem geriggtem Charakter, `Alle hinzugefügt` und `Einzelne Datei`. Die erwarteten Dateinamen, Quellen und Interfacebilder stehen in `assets/art/characters/infantry-3d-manifest.json`.

Meshy erzeugt den zusammengeführten Export korrekt, der eingebettete Browser übergibt den Download derzeit jedoch nicht an das Windows-Dateisystem. Bis die zehn GLB-Dateien unter ihren Manifestnamen vorliegen, bleibt `assets/city3d/conquer-knight.glb` der unveränderte 3D-Fallback und T2–T10 verwenden die bereits eingebundenen tiergenauen Illustrationen. Schwert und Schild bleiben separate Modelle und werden beim lokalen Optimierungsschritt an die Handknochen gebunden.
