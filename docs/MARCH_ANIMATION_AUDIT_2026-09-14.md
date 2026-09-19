# Prüfung der Marschanimationen – 14. September 2026

## Ergebnis

Die acht gezeichneten Posen der 15 neuen Kreaturen reichen als Bewegungsentwurf, aber nicht als endgültige Premiumanimation. Mehr unabhängig mit Imagegen erzeugte Posen lösen das Kernproblem nicht: kleine Änderungen an Gesicht, Panzerung, Schmuck und Körperform erzeugen beim Abspielen ein sichtbares Flackern. Schnelle Bewegungen wirken mit acht Posen zusätzlich stufig.

Phönix und Drache zeigen die Zielqualität. Beide werden aus gegliederten, formstabilen Three.js-Modellen offline mit 30 Bildern pro Sekunde gerendert. Die Weltkarte lädt danach nur das fertige transparente WebP. Dieses Verfahren erzeugt echte Gelenkbewegung, hält Details stabil und fügt der mobilen Laufzeit kein WebGL-Modell hinzu.

Ein Test mit bidirektionalem Optical Flow erzeugte aus jedem Acht-Posen-Zyklus 24 Bilder. Der Ablauf wurde weicher, an Hufen, Flügelkanten, Tentakeln und wechselnden Silhouetten entstanden jedoch halbtransparente Doppelkonturen. Die Testdateien unter `artifacts/march-motion-smooth/` werden deshalb nicht als Produktionsassets verwendet.

## Einzelprüfung

| Skin | Aktueller Eindruck | Hauptproblem | Empfohlenes Ziel |
|---|---|---|---|
| Eisenkoloss | Gewicht lesbar | Loop-Naht und wechselnde Rüstungsdetails | 30 fps, gegliederte Beine, Hammer, Schild und Fahne |
| Rosenhirsch | Galopp klar | Acht Posen lassen den schnellen Zyklus springen | 30 fps, vierbeiniger Laufzyklus mit festen Hufkontakten |
| Sonnenskarabäus | Krabbeln erkennbar | Beinfolge und Panzerdetails wechseln | 24–30 fps, sechs getrennte Beinketten |
| Leuchtrücken | Schwimmidee klar | Flossenhub und Aufbau verändern sich zwischen Posen | 24 fps, Schultergelenke plus nachlaufende Fahnen |
| Frostmammut | Schwere Bewegung lesbar | Schritte zu wenig differenziert | 24 fps, vier Beine, Rüssel, Ohren und Schwanz |
| Jadeglockenlöwe | Dynamischer Satz | Körper- und Schmuckdetails flackern | 30 fps, vier Beine und verzögerte Glocke |
| Glutsalamander | Gute Krabbelbewegung | einzelne Beine verschmelzen in Übergängen | 30 fps, sechs Beine und segmentierter Schwanz |
| Rabenfürst | Flügelschlag deutlich | Flügelfedern wechseln ihre Form | 30 fps, Schulter, Unterarm, Handschwingen und Schwanzfächer |
| Uhrwerkhase | Sprung gut lesbar | schnelle Kontaktphase wirkt stufig | 30 fps, Beine und sichtbare Federn als feste Teile |
| Saphirpfau | Silhouette sehr stabil | Beine und schwerer Fächer bewegen sich zu wenig | 24 fps, kompletter Schritt und träge Fächerbewegung |
| Sternenwal | Flossen und Schweben erkennbar | größere Silhouettensprünge zwischen Posen | 24 fps, Flossen, Schwanzstiel und Fluke |
| Korallenleviathan | Körperwelle lesbar | Rumpfform und Korallen verändern sich | 24 fps, feste Rumpfsegmente mit laufender S-Kurve |
| Wurzelkoloss | Stampfen erkennbar | Wurzelfüße und Krone wechseln ihre Form | 24 fps, verankerte Wurzelfüße und nachlaufende Krone |
| Sturmqualle | Tentakelbewegung deutlich | Tentakelkonturen flackern | 24 fps, feste Tentakelketten mit versetzten Wellen |
| Finstersonnenwagen | ruhiges Gleiten | zu wenig erkennbare Eigenbewegung | 24 fps, rotierende Korona, Rad und nachlaufende Anhänger |
| Phönix | flüssig und formstabil | kein wesentlicher Mangel | vorhandene 36 Bilder bei 30 fps behalten |
| Drache | flüssig und formstabil | kein wesentlicher Mangel | vorhandene 48 Bilder bei 30 fps behalten |

## Empfohlene Produktionsmethode

Die 15 Kreaturen werden als kleine gegliederte Storybook-Modelle in `assets/city3d/march-creatures.js` aufgebaut. Große Körperteile bleiben eigene Gruppen mit festen Drehpunkten. Beine erhalten mindestens Hüfte, Unterschenkel und Fuß; Flügel Schulter, Unterarm und Spitze; flexible Körper erhalten kurze Ketten. Toonmaterialien, dunkelbraune Konturen und die jeweiligen Burgfarben kommen aus der bestehenden Storybook-Pipeline.

`tools/render-march-creatures.cjs` rendert jeden Zyklus offline in 24 bis 48 transparente Bilder. Der Client behält seinen jetzigen Vertrag: sichtbare laufende Märsche laden ein animiertes WebP, stehende oder reduzierte Ansichten ein Standbild. Damit bleibt die Kartenleistung auf Mobilgeräten praktisch unverändert.

Die Umsetzung sollte in drei Gruppen erfolgen:

1. Schnelle Bewegungen: Rosenhirsch, Uhrwerkhase, Rabenfürst, Jadeglockenlöwe, Glutsalamander und Sonnenskarabäus.
2. Schwere Bodenkontakte: Eisenkoloss, Frostmammut, Wurzelkoloss und Saphirpfau.
3. Schwimmen und Schweben: Leuchtrücken, Sternenwal, Korallenleviathan, Sturmqualle und Finstersonnenwagen.

Jede Gruppe wird bei normaler Kartengröße, in der großen Sammlungsvorschau, in 390 px und 320 px Breite sowie im Querformat geprüft. Abnahmebedingungen sind eine identische Pose bei Zyklusanfang und -ende, keine rutschenden Standfüße, stabile Gesichts- und Rüstungsdetails, passende Sekundärbewegung und ein statischer Reduced-Motion-Zustand.
