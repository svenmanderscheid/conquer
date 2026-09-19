# Bewegte Farmen und Weltmonster

Die Weltkarte verwendet transparente, aus den Spielmodellen gerenderte Sprites. Die fünf Rohstoffstellen entstehen in `assets/city3d/world-resources.js`: Getreidehof mit drehender Windmühle, Feld und Bauer; Holzlager mit Säge und Baumgruppen; Steinbruch und Goldmine mit arbeitenden Bergleuten; Kristallader als freigelegter Fels mit roten Kristallen, Erzkorb und Bergmann. Hof, Steinbruch und Goldmine verwenden die bestehenden Dorfmodelle aus `storybook-production.js`, ergänzt um eine eigene kompakte Kartenanordnung. Der Hof behält Getreide, Windfahne und Hühner aus `farm-life.js`. Ork, Skelett, Golem und Goblin entstehen in `assets/city3d/world-monsters.js` mit den gemeinsamen Storybook-Materialien. Regionale Bossillustrationen bleiben erhalten und erhalten eigene CSS-Ruhebewegungen und Partikel; ihre vier belegten Felder sind in `REGIONAL_RALLY_BOSSES.md` beschrieben.

Die dritte Monsterfassung orientiert sich direkt an den vorhandenen Charakterillustrationen: abgerundeter breiter Orkkopf und vorgelagerte Stoßzähne, offener Skelettbrustkorb mit sichtbarem Schal, asymmetrische Golemblöcke sowie große Goblinohren und Beutesack. Waffen liegen außerhalb der Kopfsilhouette. Die Monsterkamera schaut etwas flacher auf die Gesichter; Farmkamera und Spielfeldgeometrie bleiben gleich. Die Materialfabrik `createStorybookMaterial()` bewahrt den Pigment-Shader, den ein einfaches Material-`clone()` verlieren würde. Gesichter und Ausrüstung sind an ihre bewegten Körperteile gebunden, Pupillen liegen vor dem Weiß der Augen. Der lokale Vergleich liegt unter `/conquer/artifacts/monster-design-20260912/`.

## Assets aktualisieren

`node tools/render-world-life.cjs` rendert die fünf Rohstoffstellen und vier normalen Monster; `--kinds=farm,lumber,quarry,gold,crystal` aktualisiert ausschließlich die Rohstoffstellen. Der Renderer benötigt Playwright mit Chrome und Python mit Pillow. Er schreibt 256 × 256 Pixel große PNG-Standbilder und animierte WebP-Dateien nach `assets/art/map/life-*`. Die Animation verwendet feste Bildgrenzen und eine viersekündige Schleife mit 80 Quellbildern (20 Bilder/s). Sie läuft vorwärts ohne doppelte Endbilder oder künstliche Pause. Vor der Aufnahme wird der Schattenpass aufgewärmt. Das Skript prüft Bewegung, transparente Hintergründe und identische Anfangs-/Endposen.

`world-encounters.js` liefert diese Bildpfade. `world-map.js` verwendet Standbilder bei reduzierter Bewegung, verborgenem Tab und Markern außerhalb der Ansicht. Die gleiche Auswahl gilt für die Detailansicht; Suchergebnisse verwenden Standbilder. Dateizeitstempel verhindern veraltete Bilder im Browsercache.

## Bodenintegration und Regionen (13. September 2026)

Die fünf Rohstoffstellen besitzen jeweils vier regionale Kartenfassungen: `life-{farm,lumber,quarry,gold,crystal}-{forest,ice,sand,lava}.{png,webp}`. Die Auswahl richtet sich nach `ConquerLandscape.biomeAt()` an der tatsächlichen Objektposition, einschließlich des halben Feldes bei geraden Auswahlflächen. Das gilt ebenfalls für Suchergebnisse, Zielansichten, Standbilder und nach einer Positionsänderung im Serverzustand. Bossidentität und Stadtgestaltung bleiben eigenständig; ihr Bodenkontakt richtet sich nach dem örtlichen Gelände.

Die Modelle werden ohne ihre breite Bodenplatte gerendert. Eine unsichtbare Schattenebene nimmt die Schatten der Gebäude, Werkzeuge und Arbeiter auf und wird mit in die transparenten Sprites gebacken. Die Karte benötigt dafür keine zusätzlichen 3D-Szenen. Die vier Regionen verwenden passende Gesteinsfarben; die Eisfassung erhält Schnee auf oberen Dach-, Fels- und Baumflächen, die Sandfassung kleinere salbeigrüne Kronen und die Lavafassung kahle, verkohlte Bäume. Die Stadtmodelle werden durch diese optionalen Renderparameter nicht verändert.

`objectGround()` in `world-landscape.js` zeichnet weiche Bodentönungen und wenige unregelmäßige Gräser, Schneeauflagen, Sandverwehungen oder Aschesteine. Die Farben und die Häufigkeit regionaler Details verwenden dieselben gemischten Klimagewichte wie das Gelände. Ufernahe Flächen werden auf Land begrenzt. Die Stadt bekommt einen auslaufenden Erdweg statt des rautenförmigen Vorplatzes. Die dauerhaften quadratischen Rohstoffhintergründe und die zusätzlichen Schlagschatten rund um Objektsilhouetten entfallen; belegte Felder und Auswahlmarkierungen bleiben erhalten.

Der normale Renderbefehl erzeugt sowohl die bisherigen neutralen Bilder als auch die Regionalvarianten. Nur die regionalen Ressourcen aktualisieren:

```text
node tools/render-world-life.cjs --kinds=farm,lumber,quarry,gold,crystal --biomes=forest,ice,sand,lava
```

Die 20 animierten Regionaldateien benötigen zusammen rund 4,4 MiB; jede Variante bleibt bei 256 × 256 Pixeln. Die Aufbereitung erfolgt vollständig aus den vorhandenen Geometriemodellen. Es werden keine Bitmapillustrationen nachbearbeitet.

Die Kristallader trägt rote, prismatische Kristalle mit korallroten Lichtflächen und dunkelroten Schattenflächen, passend zur Kristallwährung. Die Farbe bleibt in allen vier Regionen erhalten. Für ein ausgewogenes Größenverhältnis zum Getreidehof vergrößert der Renderer die regionale Holzfällergrafik um 20 %, den Steinbruch um 25 % und die Kristallader um 35 %, jeweils um ihren festen Bodenkontakt. Die sichtbaren Silhouetten sind damit ungefähr gleich breit. Die gemeinsame Bildauflösung bleibt erhalten. Alle normalen Rohstoffstellen belegen jetzt 1×1 statt 2×2 Felder; ihre Bildfläche wird gemeinsam auf 1,85 Kachelbreiten verkleinert.

`node tests/world_grounding.cjs` prüft alle 20 Regionalmotive, 1×1-Auswahlflächen, Koordinatennavigation und Sammelaktionen in Desktop-, Handy- und Querformat, deterministische Bodendarstellung, Uferbegrenzung und die Aktualisierung bei einem Ortswechsel. Die interaktive Prüfszene mit allen fünf Rohstoffstellen in jeder Region liegt unter `/conquer/artifacts/world-grounding-20260913/`. `tests/regional_bosses_app.cjs` prüft ergänzend die echte Haupt-App mit isolierten Beispieldaten.

## Landschaft und Bewegung

Die sichtbare Kartenlandschaft verwendet für Bäume, Felsgruppen und Berge ausschließlich transparente Renderings räumlicher Toonmodelle. Dazu gehören krumme Nadel- und Laubbäume, seltene rosa Blütenbäume, facettierte Felsen und mehrteilige Bergsilhouetten mit glaubwürdiger Lichtseite. Regionale Farbanpassungen verbinden dieselben Modelle mit Wald, Eis, Sand und Asche. Die früher direkt auf das Canvas gezeichneten Vulkane, Kristallspitzen und Reliktsilhouetten werden nicht mehr dargestellt; in den Aschenlanden stehen stattdessen niedrige dunkle Basaltgruppen. Dekorationen bleiben deterministisch, nach ihrer Weltposition tiefensortiert und mit großzügigem Abstand zu Dörfern, Monstern, Rohstoffen und Marschzielen.

Marschskins verwenden einen zweifachen Schritt mit leichtem Neigen, Stauchen und Anheben der Gruppe. Die sechs kleinen Spurpartikel nehmen das Motiv des gespeicherten Skins auf: Glut, Frost, Blätter, Sternenmagie, Sturm, Dampf oder Staub. Der Bodenanteil passt sich Wald, Eis, Sand und Lava an. Die Detailansicht der Skin-Auswahl zeigt denselben Marschtakt. Es werden die vorhandenen WebP-Grafiken verwendet; zusätzliche große Bilder oder laufende Three.js-Szenen sind nicht nötig.

Die Truppenposition wird in `requestAnimationFrame` über einen CSS-Transform aktualisiert. Truppenzusammensetzung, Beschriftung und Routengeometrie werden nicht mehr zehnmal pro Sekunde neu aufgebaut. Kleine Korrekturen der gerundeten Serveruhr werden mit begrenzter Geschwindigkeit ausgeglichen, ohne den Trupp rückwärts zu bewegen. Ziehbewegungen werden pro Bild zusammengefasst. Unverändertes Gelände bleibt auch bei Serverupdates im Canvas; die Weltübersicht zeichnet nur bei geöffnetem Kompass neu.

Beim beobachteten Eintreffen eines Angriffs entsteht einmalig ein größerer Ring mit Splittern und einem Zeichen in den Skinfarben. Dieser kosmetische Ankunftseffekt trifft keine Aussage über den Kampfausgang. Sammeln, Charms, Spähen, Verstärkung, Garnison und Rückkehr lösen ihn nicht aus. Serverupdates können den Effekt nicht duplizieren. Verpasste Ankünfte werden nach einem Tab-Wechsel nicht nachgeholt. Höchstens vier Effekte leben gleichzeitig und werden nach 1,5 Sekunden entfernt. Außerhalb der Ansicht und bei reduzierter Bewegung entfallen sie; stehende Trupps setzen keine Schritte fort.

`tests/world_march_motion.cjs` prüft Bewegung pro Bild, Uhrkorrekturen, Wiederverwendung des Geländes, Eingabebündelung, vier Regionen und Bildschirmgrößen, einmalige Ankünfte, Rückruf, Serverübergänge, Tab-Wechsel, Partikelbegrenzung und reduzierte Bewegung. Die interaktive Beispielsicht liegt unter `/conquer/artifacts/world-march-motion-20260913/` und verwendet ausschließlich synthetische Daten. `tests/march_skin_world_app.cjs` prüft zusätzlich einen echten Marsch in einer isolierten Haupt-App (`--march-skins --march-skin-world --chat`).

`world-landscape.js` zeichnet weiche, deterministische Bodenflächen, regionale Vegetation, Mineralgruppen und Ufer. Wasserreflexe und kleine Glutpartikel liegen auf einer separaten, durchklickbaren Canvas-Ebene. Diese wird höchstens zehnmal pro Sekunde aktualisiert; der Geländeuntergrund und die Minikarte bleiben zwischengespeichert. Reduzierte Bewegung und ausgeblendete Tabs pausieren die Atmosphäre. Beim Verschieben und Zoomen wird sie zur Karte ausgerichtet.

Der Dorf-Bauernhof animiert instanzierte Ähren, eine Windfahne und zwei Hühner innerhalb des bestehenden Hofes. Pausieren, reduzierte Bewegung und die Sichtbarkeit des eingebetteten Dorfes werden berücksichtigt. Koordinaten, Wassergeometrie, Auswahlflächen, Laufwege und Spielregeln bleiben unverändert.

## Prüfung und Vorschau

- `node tests/world_life.cjs`: Bilder, sichtbare Bewegung, Standbildumschaltung, getrennte Atmosphäre, Auswahl und Bildschirmgrößen.
- `node tests/world_biomes.cjs`: vier Regionen, Übergänge, Navigation und reduzierte Bewegung.
- `node tests/world_footprint.cjs`: belegte Stadtfelder, benachbarte Ziele, Minikarte und Kartennavigation.
- `node tests/world_terrain.cjs`: Übereinstimmung der Wassergeometrie mit dem Server.
- `node tests/village_viewport.cjs`: eingebettetes Dorf, Hofanimation, Pause, Beschriftungen und mobile Formate.
- `php tests/city3d_importmap.php`: lokale Modulverknüpfungen und Versionswechsel.

Die interaktive, ausschließlich lokale Prüfszene liegt unter `/conquer/artifacts/world-life-review-20260912/`. Sie verwendet die echten Grafikmodule und Beispieldaten ohne Spiel-API-Aufrufe. Ihre HTML-Datei lässt sich mit `tests/fixtures/world_life.cjs` neu erzeugen.

## Überarbeitete Rohstoffstellen

Alle fünf normalen Rohstoffstellen belegen 1×1 Felder. Ihre quadratischen Bildflächen verwenden 1,85 Kachelbreiten einschließlich transparenter Ränder; kleine Monster verwenden 1,45 Kachelbreiten. Die 29 WebP-Dateien einschließlich Regionalvarianten benötigen zusammen rund 7 MiB; die größten einzelnen Dateien bleiben unter 500 KiB. Windmühle und Säge drehen kontinuierlich; Arbeiter bewegen sich lokal. Das Spiel lädt die kompakten gerenderten Bilder, ohne zusätzliche Three.js-Szenen auf der Weltkarte zu betreiben.

Die verfeinerte Kristallader bildet einen asymmetrisch aufsteigenden Fächer aus sieben schlanken Rubinkristallen. Abgeschrägte Kanten, lange Spitzen und schmale gemalte Lichtreflexe geben den roten und rosigen Toonflächen Tiefe. Die Felsbasis ist niedrig und kleiner als die Kristallgruppe; der kleine Erzkorb besitzt einen gebogenen Henkel. Der Bergmann trägt einen Helm mit Grubenlicht, steht dem Fels zugewandt und hebt seine Spitzhacke langsam vor dem schnellen Schlag. Felsen übernehmen die regionalen Steinfarben und Schneeauflagen, die Kristalle behalten ihre rote Rohstoffidentität. Gleichfarbige Facetten werden in gemeinsamen Materialgruppen gerendert. `node tools/render-world-life.cjs --kinds=crystal` aktualisiert die fünf Fassungen einschließlich neutraler Vorschau; die regionalen Animationen bleiben unter 100 KiB. Die direkte 3D-Ansicht liegt unter `/conquer/artifacts/encounter-review-20260913/#crystal`.

Die Haupt-App-Prüfung `tests/regional_bosses_app.cjs` umfasst die fünf Motive, sichtbare Bewegung, 1×1-Auswahl bei Rohstoffen und unveränderte 2×2-Auswahl bei Regionalbossen, erreichbare Sammelaktionen und Standbilder bei reduzierter Bewegung. Kleine Hochformate verwenden kompakte Zielknöpfe und lassen die Rally-Beschriftung oberhalb des Chats frei.


## Kompakte Zielkarten und natürliche Ruhebewegung (13. September 2026)

Das WhatsApp-Referenzvideo dient als Orientierung für kompakte Proportionen, natürliche Bewegung und das zusammenhängende Ziel-Popup. Es werden keine Spielgrafiken aus dem Video übernommen. Die Monster behalten ihre Conquer-Identität: Ork mit Schulterleder und Keule, schief stehendes Skelett mit Schal, schwerer moosiger Golem und vorgeneigter Goblin mit Beutesack. Brustatmung, gehaltene Blicke, Blinzeln und verzögert nachlaufende Ausrüstung ersetzen das gemeinsame Schaukeln. Füße und Zielkoordinaten bleiben fest. Arbeiter heben ihr Werkzeug langsamer, führen den Arbeitsschlag aus und machen eine kurze Pause. Windmühle und Säge laufen durch; die Bauernhof-Kleintiere verwenden beim Kartenrendern periodische Bewegungen. Der normale Dorfanimationsablauf bleibt erhalten.

`WorldPlacement::footprint()` ist die Serverreferenz: `resource` und `monster` belegen genau das Ankerfeld; regionale `boss`-Ziele weiterhin 2×2. Kartenmittelpunkt, Auswahl, Hitbox, Marschziel und Bodentönung entsprechen dieser Belegung. Die bestehenden zwei freien Felder zwischen Rohstoffstellen bleiben Teil der Platzierungsregel. Die Änderung braucht keine Datenmigration und verändert weder Objekt-IDs, Vorräte, Lebenspunkte noch laufende Märsche.

Normale Rohstoffstellen und Monster öffnen eine kompakte `.is-encounter`-Karte aus `village-theme.css`: blauer Kopf, Elfenbeinfläche, Holzrahmen, Bild, Stufe, Koordinaten, belegte Felder und Vorrat beziehungsweise Lebenspunkte. Die maximale Monster-HP ergibt sich aus den Serverwerten `definition.stats.hp × definition.amount`. Darunter stehen eine große Sammel-/Angriffsaktion sowie Details und Teilen. Besetzung, Angriff auf besetzte Rohstoffstellen und Rückruf bleiben erhalten. Die Position berücksichtigt Modell, HUD und Chat. Escape, Schließen und Browser-Zurück schließen das Zielmenü.

`tests/encounter_cards_app.cjs` prüft die sechs kleinen Zieltypen in fünf Formaten einschließlich 320×568 und 568×320 mit dem echten App-HUD, erreichbaren Aktionen, Marschdialogen und Zurück-Verhalten. `tests/world_placement.php` prüft Serverbelegung, freigegebene Nachbarfelder, Randfelder, Rohstoffabstand und Schutz laufender Märsche. Die interaktive 3D-Modellprüfung mit Gesamt- und Nahansichten liegt unter `/conquer/artifacts/encounter-review-20260913/`; App-Bilder liegen unter `artifacts/encounter-cards-20260913/`.
