# KI-3D-Versuch: Infanterist

## Ziel und Quelle

Ein einzelnes, original gestaltetes Infanteriemodell anhand von `assets/art/knight.png` erzeugen. Die bisherigen programmgenerierten Modelle sind keine Eingabereferenz. Das neue Ergebnis zunächst separat prüfen und nicht automatisch in die Stadt übernehmen.

## Vorgesehener Versuch

Anbieter: Meshy, Arbeitsbereich „Bild zu 3D“. Der im Browser geprüfte Arbeitsbereich bietet Meshy 7, Standardauflösung, Texturierung und eine A-Pose an. Ausgangspunkt ist die vorhandene Ritterillustration. A-Pose soll verdeckte Körperpartien und spätere Animation erleichtern; Waffen und Schild sind beim Ergebnis besonders auf Verschmelzungen mit Händen und Rumpf zu prüfen. Keine neue Bildverbesserung zur Änderung der gewählten Identität erzwingen.

Eine private Generierung bevorzugen. Keine öffentliche Veröffentlichung oder Änderung der Lizenz vornehmen. Falls der verfügbare Tarif nur öffentliche Generierungen erlaubt oder ein Kauf nötig wird, die Entscheidung dem Nutzer vorlegen. Der konkrete Creditpreis muss mit den endgültigen Optionen vor der Generierung geprüft werden.

## Abnahme

- Original wiedererkennbar: helles asymmetrisches Haar, großes ausdrucksstarkes Gesicht, blaue Tunika, orangefarbener Schal, breite braune Schuhe.
- Gesicht, Frisur und Kleidung bilden überzeugende zusammenhängende Flächen; keine verschmolzenen Finger, Waffen oder schwebenden Details.
- Vorderseite, beide Seiten und Rückseite im echten 3D-Viewer prüfen.
- Erst visuelle Rohfassung beurteilen, anschließend spielgeeignete Vereinfachung und Rigging prüfen. Eine vom Anbieter angegebene Polygonzahl nicht ungeprüft als Dreieckszahl behandeln.
- GLB und Texturen lokal sichern; Skeleton, Animationen, Materialzuordnung, Größe und Darstellung in Three.js prüfen. Mobile Optimierung und Toonmaterialien erst danach.

## Aktueller Stand

Der Nutzer hat sich angemeldet und am 14. September 2026 den Upload der Ritterillustration sowie einen Versuch unter CC BY 4.0 ausdrücklich bestätigt. `assets/art/knight.png` wurde hochgeladen; eine Generierung mit Meshy 7, Standardauflösung, Textur aktiviert und Bildverbesserung deaktiviert wurde gestartet. Die Oberfläche zeigte dafür 30 Gratis-Credits bei zuvor 100 verfügbaren Credits. A-Pose und private Lizenz sind im kostenlosen Konto gesperrt und wurden nicht aktiviert. Kein Abo abgeschlossen. Das Ergebnis wird zunächst im Anbieter-Viewer beurteilt.

Offizielle Anleitung: https://docs.meshy.ai/en/webapp/image-to-3d

## Ergebnis des ersten Versuchs

Die Generierung wurde abgeschlossen. In den Meshy-Assets vom 14. September 2026 liegen eine untexturierte und eine texturierte Ritterversion. Die texturierte Version wurde im Viewer von vorne, seitlich und hinten angesehen und anschließend wieder zur Vorderansicht gedreht. Frisur, Gesicht, Tunika, Schal, Schwert und Schild sind erkennbar näher an der Illustration als beim vorherigen lokalen Prototyp. Die Rückseite ist räumlich ausgearbeitet.

## Fortsetzung nach Abo-Abschluss am 14. September 2026

### Lokale Gewichtskorrektur nach Dateiübergabe

Der Download `Meshy_AI_Meshy_Merged_Animations.glb` wurde lokal gefunden (14.354.604 Bytes). Unveränderte Sicherung: `artifacts/knight-review/original.glb`. Das Asset enthält ein zusammenhängendes Mesh mit 10.445 Dreiecken, 11.801 UV-/Normalen-getrennten Vertices, 28 Knochen und Running, Walking, restpose. Schwert und Schild sind keine getrennten Objekte.

`tools/fix-knight-equipment.cjs` erzeugt die separate Datei `artifacts/knight-review/knight-rigid-equipment.glb`: 614 Schwert-/Griffpunkte sind vollständig RightHand zugeordnet, 1.374 Schildpunkte LeftHand. Die bindungsräumlichen Masken wurden von vorne und hinten kontrolliert; Texturen, Geometrie und Animationsclips bleiben erhalten. Es handelt sich um starre Gewichte innerhalb desselben Meshes, nicht um abgetrennte Austauschwaffen. Original bleibt unverändert.

Die lokale Vergleichsansicht `/artifacts/knight-review/` bietet Korrektur an/aus, Bindepose, Gehen und Laufen. Stichproben über 41 Zeitpunkte pro Clip prüfen Formstabilität an jeweils 16 ausgewählten Punkten pro Ausrüstungsteil. Der relative Abstandsfehler nach Korrektur liegt unter 0,000001; das beweist nicht Kollisionsfreiheit jedes Frames. Geh-/Laufansicht und Handyformat wurden visuell geprüft, keine Browserfehler beobachtet. Die Datei ist weiterhin rund 14 MB groß; mobile Texturverkleinerung und Prüfung in der echten Stadt stehen aus. Noch keine Ersetzung der produktiven Spielfiguren.

Die Vorschau korrigiert zusätzlich die Fußausrichtung und verstärkt das Abrollen beim Aufsetzen. Eine ruhige Kampfhaltung stabilisiert Unterarme und Hände nach der Animation, sodass Schwert und Schild ihre lesbare Silhouette behalten. Die Gesichtstextur erhielt einen kleinen gezeichneten Mund; über den Schalter „Mund“ ist das Original weiterhin direkt vergleichbar. Gehen und Laufen wurden von vorne und seitlich kontrolliert, ohne neue Browserwarnungen. Diese drei Korrekturen sind noch Laufzeit-/Vorschaukorrekturen und noch nicht in die herunterladbare GLB eingebrannt.

Nach visueller Freigabe wurde `assets/city3d/conquer-knight.glb` erzeugt. Diese Fassung enthält die Mundtextur und die starren Handbindungen für Schwert und Schild direkt in der GLB; die identische Abnahmekopie liegt unter `artifacts/knight-review/conquer-knight.glb`. SHA-256 und Dateigröße beider Kopien stimmen überein. Die Fuß- und Kampfhaltungs-Nachbearbeitung liegt wiederverwendbar in `assets/city3d/knight-motion-profile.js` und muss unmittelbar nach `AnimationMixer.update()` angewendet werden. Sie bleibt bewusst laufzeitseitig, damit dieselbe Korrektur für alle Clips konsistent greift.

Ein sichtbares Ruckeln an der Schleifennaht wurde auf Animations-Zeitspuren zurückgeführt, deren erster Key erst bei 0,0667 Sekunden lag. Der Build normalisiert die Zeitspuren von Walking und Running nun auf 0,0000 Sekunden. Dadurch entfällt der von Three.js vor jedem Umlauf gehaltene Startframe, ohne Posen oder Skinning zu verändern. Running läuft danach von 0 bis 0,6333 Sekunden, Walking von 0 bis 0,9667 Sekunden.

## Trainingsübersicht

Die Infanterie-Vorschau der Trainingsübersicht lädt nun `assets/city3d/conquer-knight.glb`; Bogenschützen und Kavallerie behalten vorerst ihre bisherigen prozeduralen Figuren. `assets/city3d/knight-showcase-motion.js` ergänzt eine ruhige Bereitschaftspose mit leichter Atmung und Blickbewegung. Etwa alle 6,8 Sekunden folgt eine klar lesbare, weich ein- und ausgeblendete Wachroutine: leicht absenken, Schild vor der Brust abstützen, Schwert in Schlagbereitschaft bringen, kurz halten und kontrolliert lösen. Bei reduzierter Bewegung bleibt die Figur vollständig ruhig. Die Ansicht behält das Bild als Lade-/Fehlerfallback und verwendet den gecachten Ritter bei Stufenwechseln.

Die echte Trainingsansicht wurde bei 1280×800, 390×844, 320×568, 844×390 und 568×320 sowie in allen Ausbildungszuständen geprüft. Es entstanden keine Überläufe oder Browserfehler; `tests/training_layout.cjs` und `tests/training_app.cjs` sind erfolgreich.

Der Nutzer hat das Abo selbst abgeschlossen. Nach Neuladen zeigte Meshy 1.120 Credits und freigeschaltete Werkzeuge. Für das vorhandene texturierte Modell wurde über „Animieren → Rig“ eine Kopie mit Ziel 10K und Dreieckstopologie erzeugt (angezeigter Preis 0 Credits). Danach wurde der humanoide Rigging-Assistent durchgeführt. Die automatischen Gelenkpositionen lagen teilweise auf Ausrüstung statt Körper; Symmetrie wurde deaktiviert und Kinn, Schultern, Ellbogen, Handgelenke, Leiste, Knie und Knöchel visuell korrigiert.

Das Rigging ist abgeschlossen. Der Viewer zeigt Bewegung und meldet 10.445 Flächen sowie 5.227 Scheitelpunkte. „Gehen“ ist bereits eine Standardanimation. Das ist noch keine Freigabe der Verformungsqualität oder mobilen Leistung. Der sichtbare Saldo stieg zwischenzeitlich auf 1.170; daraus wird kein zusätzlicher Verbrauch abgeleitet.

Exportdialog vorbereitet: GLB, MeshyRig, Riggierter Charakter an, Alle hinzugefügt, Einzelne Datei an. Download wurde ausgelöst, aber die Browserautomatisierung erhielt kein Downloadereignis und im üblichen Downloads-Ordner erschien keine neue Datei. Lokale Dateiübergabe ist deshalb noch offen. Keine Meshy-Datei wurde in Conquer eingebaut. Das ursprüngliche freie Modell wird nicht allein wegen des späteren Abos als rückwirkend privat lizenziert behandelt.

Das ist eine visuelle Rohfassung in der Pose der Illustration. Es wurden weder Rigging noch Animierbarkeit, Topologie oder mobile Performance nachgewiesen. Besonders Hand-/Ausrüstungsverbindungen und Schaldetails müssen nach einem erlaubten Export untersucht werden. Ein Abo wurde nicht abgeschlossen, kein Modell heruntergeladen und die Spielassets wurden nicht ersetzt. Die Oberfläche meldete während des Versuchs zusätzlich eine automatische Check-in-Gutschrift von 30 Credits; der sichtbare Saldo blieb dadurch bei 100.
