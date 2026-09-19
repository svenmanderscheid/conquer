# Marsch-Skins

Die ursprüngliche Basiskollektion umfasst 17 transparente Chibi-Garden passend zu den Burg-Skin-IDs. Die Dateien heißen `march-{id}.webp` und werden von `assets/js/march-skins.js` für Sammlung und Weltkarte verwendet. Eigenständige Kreaturen verwenden stattdessen ein `flight-{id}.webp` mit einer statischen PNG-Pose für reduzierte Bewegung.

Die Illustrationen wurden am 13. September 2026 mit dem eingebauten **Imagegen** erzeugt. Jede Garde erhielt einen eigenen Aufruf. Die vollständigen verwendeten Prompts und die Herkunft der Originale stehen in `docs/march-skin-art-prompts.json`. Es wurde kein externer API-/CLI-Generator eingesetzt.

Die Originale enthalten echte Transparenz. `tools/prepare-march-skin-art.cjs` verkleinert sie ohne inhaltliche Änderung auf 512 × 512 Pixel und kodiert sie als WebP mit erhaltenem Alphakanal. Der aktuelle Satz umfasst 18 statische Spieldateien einschließlich der neuen Drachenvorschau; eine Datei liegt ungefähr zwischen 56 und 80 KiB. Die Kartenansicht lädt nur die benötigten Skins, die Sammlung zeigt vier Einträge je Seite.

`tools/review-march-skin-art.cjs` prüft die vollständige Sammlung in Vorschaugröße und auf Wald-, Eis-, Sand- und Aschehintergrund. Die Übersicht liegt in `artifacts/march-skins/collection.png`; technische Bildprüfungen stehen in `artifacts/march-skins/art-validation.json`.

Die 15 neuen Kreaturen besitzen derzeit acht gezeichnete Schlüsselposen als animierte WebP-Datei. Phönix und Drache werden bereits aus gegliederten Three.js-Modellen mit 30 Bildern pro Sekunde offline gerendert. Die Prüfung vom 14. September 2026 empfiehlt dieselbe formstabile Rig-Pipeline für die übrigen Kreaturen; Einzelbefunde und Zielbildraten stehen in `docs/MARCH_ANIMATION_AUDIT_2026-09-14.md`.

## Eigenständig animierter Phönix

Die Phönixgarde nutzt nun `flight-phoenix.webp` und `flight-phoenix.png` anstelle ihrer bisherigen Gardistenillustration. Das eigene Toon-Modell in `assets/city3d/march-creatures.js` wird mit `tools/render-march-creatures.cjs` gerendert; es verwendet keine extrahierten Bilder aus der Videoreferenz. Die 36 unterschiedlichen Posen bilden einen nahtlosen Flugzyklus von 1,2 Sekunden. Die animierte Datei hat 384 × 384 Pixel und benötigt etwa 350 KB; für reduzierte Bewegung wird das PNG verwendet. `flight-phoenix-poses.jpg` zeigt vier Posen zur Sichtprüfung.

## Eigenständig animierter Drache

Der Drachenmarsch nutzt im Auswahlmenü die mit dem eingebauten Imagegen erzeugte `march-dragon.webp`. Auf der Weltkarte ersetzt ein eigenes gegliedertes Toon-Modell mit smaragdgrünen Schuppen, Basaltpanzer, gealterter Bronze, breiten Fledermausflügeln und einem beweglichen Schwanz die normalen Truppen. Seine 48 Posen bilden einen nahtlosen Flugzyklus von 1,6 Sekunden. `flight-dragon.webp` hat 384 × 384 Pixel und benötigt rund 330 KB; `flight-dragon.png` ist die unbewegte Alternative. `flight-dragon-poses.jpg` zeigt vier markante Flügelschläge. Die übrigen Premium-Skins verwenden ihre eigenständigen Kreaturenillustrationen und werden gemäß Animationsprüfung schrittweise auf dieselbe formstabile Rig-Pipeline umgestellt.
