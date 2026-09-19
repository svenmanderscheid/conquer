# Regionale Rally-Bosse

Grumwald, der Wurzelbrecher (Smaragdwald), Frostgrimm (Frostlande), Sandmaul (Sonnendünen) und Glutramm (Aschenlande) sind als Rally-Monster integriert. Darstellung: die freigestellten Originalfiguren mit individuellen CSS-Ruhebewegungen und regionalen Partikeln.

## Regeln

- Je zehn Stufen: Frostgrimm 20202101–20202110, Sandmaul 20202201–20202210, Glutramm 20202301–20202310, Grumwald 20202401–20202410.
- Die mittleren Rally-Spawns (bestehende Deathkar-Gewichtung) erhalten je nach dominanter Region den passenden Boss, im Wald Grumwald. Bereits vorhandene Deathkar-Ziele behalten ihre Definition und werden nicht ausgetauscht.
- Regionen verwenden dieselben geschwungenen Grenzen wie `world-landscape.js`. Kollisionsreparaturen halten regionale Bosse in ihrem Biom.
- Kampfwerte, Truppenzahl und Edelsteinchancen entsprechen der jeweiligen Deathkar-Stufe. 25 AP beim Start; gemeinsamer Deathkar-Beutepool; 20 Hunter-XP pro Stufe, unter Teilnehmern aufgeteilt. Diese Gleichstellung ist der Ausgangspunkt für spätere Balance-Tests.
- Ausschließlich Rally-Angriffe; bestehende Auswahl 1/5/15/30 Minuten, Berichte, Verwundete und Rückkehr bleiben aktiv.
- Jede der 40 Regionalboss-Definitionen belegt `footprint: 2`: vom gespeicherten Anker (X,Y) bis (X+1,Y+1). Serverplatzierung, Spawn, Reparatur, Auswahl, Minikarte und Marschziel berücksichtigen diese vier Felder. Normale Truppmonster belegen weiterhin ein Feld. Die Silhouetten sind 3,6 Kachelbreiten und 3,8 Kachelhöhen groß; der Fußpunkt und die Auswahlfläche bleiben während der Bewegung fest.
- Explizite Monsterbilder haben Vorrang vor den allgemeinen animierten Einheiten in `world-encounters.js`. `lifeKind()` in `world-map.js` darf Monster mit `definition.art` nicht durch die Ork-Standardanimation ersetzen. Der Kartenfilter „Städte“ blendet auch Regionalbosse aus; „Alle“ und „Monster“ zeigen sie.
- Bevölkerungsgrenzen, Zeitfenster und Weltstatus gelten weiterhin. Ist eine Welt voll, kommen neue Arten erst beim regulären Nachfüllen.

## Lokale Vorschau

`php tools/preview-feature-fixture.php --chat --regional-bosses --port=18949` erstellt eine isolierte Testwelt mit je einem der vier Bosse und allen fünf Rohstoffstellen. Enter beendet und bereinigt sie. `node tests/regional_bosses_app.cjs` prüft diese Haupt-App mit allen vier Bossen, Kartenbildern und Rally-Vorschauen bei 1280, 390 und 320 px Breite sowie 568×320 px Querformat. Die eingebettete Stadt wird unter `/city#city` auf doppelte HUDs und in Gesamt-/Nahansicht geprüft.

`php tools/seed-regional-bosses.php` zeigt eine begrenzte Erstbesetzung an. `--apply` fügt höchstens einen fehlenden Boss je Region und aktiver Welt hinzu, wenn Population und Welteinstellungen dies zulassen. Vorhandene aktive Bosse werden mit Koordinaten gemeldet; wiederholtes Ausführen erzeugt keine doppelten Erstbesetzungen. Nur lokale Datenbanken. Keine Löschung oder Ersetzung vorhandener Ziele durch dieses Werkzeug.

Bei einer durch abgelaufene Monster blockierten Population zuerst den regulären `php cron/world_spawn_tick.php` ausführen. Dieser bereinigt abgelaufene oder besiegte Ziele unter Beachtung laufender Märsche und Rallys. Die Erstbesetzung kann danach die freigewordenen Plätze nutzen; Dichte und Populationslimit bleiben unverändert.

## Bildquellen

Erstellt mit dem eingebauten Imagegen-Werkzeug, aus den zuvor freigegebenen Konzeptbildern in `assets/art/concepts/rally-bosses/`. Die verwendeten Dateien sind `assets/art/monsters/grumwald.png`, `frostgrimm.png`, `sandmaul.png` und `glutramm.png`. Alle vier Dateien haben einen transparenten Alphakanal. Originale liegen weiterhin im Codex-Bildordner.

Grumwalds ursprüngliche Konzepttafel wurde in der Aufgabe „Vergleiche Spielinhalte mit Guide“ erstellt und am 12. September 2026 als `assets/art/concepts/rally-bosses/grumwald-v1.png` im Projekt gesichert. Die Spielfigur wurde aus genau diesem Konzept freigestellt; das Originaldesign mit Wurzelhörnern, Moos, Pilzen, Runengürtel und Baumstammkeule blieb erhalten.

Gemeinsamer Generierungs-Prompt (mit der jeweiligen Beschreibung eingesetzt):

> Turn the supplied character concept sheet into ONE isolated full-body game sprite, transparent RGBA background. Preserve the character identity, palette and equipment of the large left-hand character. [Description] Single three-quarter view, looking slightly toward viewer's left, slightly elevated isometric camera. All feet, horns, weapons and tail fully inside frame with 8 percent transparent margin. No sheet, no extra views, no text, no floor, no backdrop, no cast shadow outside figure. Simplify fine surface details for readability at 96 pixels, large rounded shapes, broad two-tone cel shading, subtle warm dark-brown contour. Hand-painted stylized 3D appearance fitting a friendly chunky fantasy village. No photorealism. Export genuinely transparent background.

Beschreibungen:

- Grumwald: A massive green woodland troll with rooted tree antlers, mossy leafy mushroom-covered shoulders, brown bark loincloth, blue rune belt and tree-trunk club.
- Frostgrimm: A massive white yeti with blue face, curved turquoise ice horns, ivory tusks, blue rune belt and stone ice hammer.
- Sandmaul: A massive low wide sandstone tortoise dragon with amber ochre skin, broad digging claws, domed sandstone plates, turquoise forehead gem and club tail.
- Glutramm: A massive squat four-legged basalt ram with large curled horns, charcoal stone plates, orange molten seams, amber eyes and heavy hooves.

Finaler Sandmaul-Korrekturprompt:

> Remove ALL background, floor, colored haze, glow and cast shadow surrounding this creature. Preserve the creature itself exactly with clean natural contour including all claws tail and shell. Output a genuine transparent RGBA cutout: every pixel outside the actual creature silhouette must be alpha zero. No black or white fill, no brown halo. Single isolated game sprite, no other changes.

## Verifikation

- `tests/regional_bosses.php`: Definitionen, zehn Stufen, Beute, regionale Verteilung, Landplatzierung und wiederholte Spawn-Limits.
- `tests/monster_rallies.php` mit `CONQUER_TEST_MONSTER_CODE` 20202101, 20202201, 20202301 und 20202401: jeweils 71 Prüfungen erfolgreich, einschließlich Solo-Abweisung, Beuteteilung, Rückkehr und Wiederholungsschutz.
- `tests/guide_progression.php`: 78 Prüfungen einschließlich echter HTTP-Antwort mit regionalem Bildpfad.
- `tests/march_windows.cjs`: 582 Prüfungen in fünf Bildschirmgrößen, nun mit Frostgrimms echtem Bild.
- `tests/guide_world.cjs`: drei Bildschirmgrößen mit geladenem Frostgrimm-Bild und Rally-Aktion.
- `tests/world_placement.php`: erfolgreich, inklusive Kollisionsreparaturen.
- Haupt-App: alle drei Regionen und Angriffsvorschauen visuell geprüft; Sandmaul zusätzlich bei 390, 320 und 568×320 Pixeln. Keine Browserfehler.
- 12. September 2026: `tests/regional_bosses_app.cjs` besteht 135 Prüfungen für alle vier Bossfiguren, größere Silhouetten, Rally-Vorschauen und die eingebettete Stadt. Desktop, 390/320 px und 568×320 px bestanden; keine JavaScript- oder API-Fehler. Screenshots unter `artifacts/regional-bosses/`.

## Lokale Erstbesetzung am 12. September 2026

Welt 1: regulärer Spawn-Lauf und anschließende Erstbesetzung ausgeführt. Grumwald Stufe 2 steht bei X 106 / Y 31, Frostgrimm Stufe 1 bei X 135 / Y 59, Sandmaul Stufe 1 bei X 112 / Y 201 und Glutramm Stufe 3 bei X 137 / Y 242. Region, trockenes Land, freie Zielfelder und aktive Lebensdauer wurden in der tatsächlichen Datenbank geprüft. Die Positionen können nach Kampf oder Ablauf wechseln.

Ein zweiter `--apply`-Aufruf meldete viermal `already_present`; keine doppelten Bosse. Dichte und Populationslimit wurden nicht verändert. Die Ergebnisse stehen in `artifacts/regional-bosses/spawn-tick.json`, `seed-applied.json`, `seed-repeated.json` und `live-bosses.json`.

## Vergrößerung und Animationen (12. September 2026)

Grumwald wiegt sich langsam, Frostgrimm atmet, Sandmaul verlagert sein Gewicht und Glutramm hebt schwer den Körper. Blätter, Eiskristalle, Sandstaub und Glutfunken verwenden jeweils vier kleine dekorative Elemente. Verborgene Ziele pausieren, reduzierte Bewegung zeigt eine feste Ruhepose. Die Originalgrafiken werden weiterhin im Rallyfenster verwendet.

`tools/resize-regional-bosses.php --apply` prüft bestehende Bosse unter der Weltplatzierungssperre und versetzt ausschließlich ungültig platzierte, unbeschäftigte Ziele innerhalb ihres Bioms. HP, Identität, Ablaufzeit und laufende Armeen bleiben erhalten. Bei der lokalen Anwendung waren alle vier bestehenden Positionen bereits gültig.

`tests/regional_bosses.php` prüft alle vier reservierten Felder gegen Monster, Städte und Rohstoffstellen sowie Grenzen, Gewässer und 100 frische Regionalspawns. `tests/world_placement.php` besteht einschließlich der bestehenden Kollisionsreparaturen. `tests/monster_rallies.php` besteht mit Grumwald einschließlich 73 Rally- und Charm-Prüfungen.

Der abschließende Haupt-App-Durchlauf besteht **222 Prüfungen**: alle vier Bosse bei 1280×800, 390×844, 320×568 und 568×320, vier belegte Auswahlfelder, stabile Hitboxen bei Bewegung, Rallyvorschauen, freie Beschriftungen oberhalb des Chats, alle fünf animierten Rohstoffstellen und reduzierte Bewegung. Eingebettete Stadt in Gesamt-/Nahansicht ohne doppeltes HUD, keine JavaScript- oder API-Fehler. `tests/world_life.cjs` bestätigt zusätzlich Spiel-/Systempausen, Standbilder außerhalb des Sichtfelds und mobile Sammelaktionen.
