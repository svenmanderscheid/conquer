# Marsch-Skins

Der Serverkatalog liegt in `data/march_skins.json`. Er enthält zu jedem der 18 Burg-Skins genau einen Marsch-Skin. Legendäre Skins kosten zunächst 1.200 Juwelen, mythische 2.400 Juwelen. Diese Preise sind reine Katalogwerte und können später ohne Schemaänderung angepasst werden.

## Verbindliche Gestaltungsregel

Ein Premium-Marschskin ist eine eigene Spielfigur und keine bloße Umfärbung vorhandener Truppen. Jeder neue Premium-Skin braucht eine aus der normalen Weltkamera sofort erkennbare Silhouette, eine zum Motiv passende Bewegung und einen eigenen Ankunftseffekt. Die Ankunft greift Material, Farbe und Charakter der Figur auf, bleibt an der Weltkoordinate verankert, löst keine Gelände-Neuzeichnung aus, respektiert reduzierte Bewegung und das Limit von vier gleichzeitigen Effekten. Premium-Burgskins enthalten ebenfalls mindestens eine klar sichtbare, zum Bauwerk passende Animation. Burg und Marsch bilden ein gestalterisches Paar, bleiben aber getrennt kauf- und ausrüstbar.

Alle ausgerüsteten Marsch-Skins geben denselben Bonus von 5 Prozent auf die Reisegeschwindigkeit. Der Faktor 1,05 wird nach den vorhandenen Forschungs-, Talent- und Weltgeschwindigkeitsfaktoren angewendet. Besitz mehrerer Skins stapelt den Bonus nicht. `default` (Grenzlandzug) kostet keine Juwelen und kann ab Burgstufe 2 einmalig beansprucht werden.

## API

`GET /api/kingdom/state` liefert `march_skins.entries`, `march_skins.equipped` und `march_skins.bonus_pct`. Jeder Eintrag ergänzt die Katalogfelder um `owned`, `equipped` und `can_claim`. `profile.march_skin` ist die ausgerüstete ID oder `null`.

Schreibende Aktionen verwenden den bestehenden authentifizierten, CSRF-geschützten Endpunkt `POST /api/kingdom/action`:

- `{"action":"march_skin.claim","march_skin":"default"}`
- `{"action":"march_skin.buy","march_skin":"ironkeep"}`
- `{"action":"march_skin.equip","march_skin":"ironkeep"}`

Kauf und Freischaltung sind wiederholbar, ohne erneut Juwelen abzuziehen. Kauf, Kontostandprüfung und Besitzänderung laufen in derselben Transaktion unter der bestehenden Spielersperre.

## Dispatch-Snapshot

Normale Märsche und Expeditionsmissionen speichern `march_skin` und `march_speed_bonus_pct` beim Absenden. Rally-Anführer speichern beides beim Start, Teilnehmer beim Beitritt. Die Rally-Laufzeit nutzt die gespeicherten Boni aller beteiligten Armeen; die Weltkarte zeigt den gespeicherten Skin des Anführers. Rückmärsche behalten den Snapshot. Ein späterer Skinwechsel verändert weder Aussehen noch Zeiten laufender Märsche.

Ältere Zeilen haben `march_skin=NULL` und `march_speed_bonus_pct=0`. Clients zeigen dafür die bestehende neutrale Truppengruppe. Automatisch erzeugte Rückmärsche übernehmen ihren Ursprungssnapshot, soweit einer vorhanden ist.

Für eine Vorschau liefert `troop_defs` bereits fertig berechnete Werte. `monster_march_speed` gilt für Solo-Monster, `monster_rally_speed` für Monster-Rallys, `charm_march_speed` für Charms, `pvp_march_speed` für Stadtangriffe und `pvp_rally_speed` für Stadt-Rallys. Verstärkung nutzt `reinforce_march_speed`. Neutrale Schreinangriffe und Garnisonen nutzen `shrine_neutral_speed`, Angriffe auf besetzte Schreine `shrine_occupied_speed`. `gather_speed` und `field_attack_speed` bleiben die Werte für Ressourcenfelder. Der Client darf auf diese Werte keinen weiteren Skinfaktor anwenden.

Die Expeditionsregeln geben `march_seconds` und `return_seconds` ebenfalls mit dem aktuellen Skinfaktor aus. Beim Absenden werden dieselben Zeiten zusammen mit dem Skin eingefroren.

## Prüfung

`php tests/march_skins.php` verwendet eine eigene temporäre Datenbank. Geprüft werden Katalogvollständigkeit, Level-Freischaltung, idempotente Käufe, unzureichendes Guthaben, Besitzprüfung beim Ausrüsten sowie eingefrorene Geschwindigkeits- und Darstellungswerte.

## Sammlung und Grafiken

Die Sammlung ist über das eigene Dorfmenü → Skins → Marsch-Skins sowie über Armee → Marsch-Skins erreichbar. Burg und Marsch besitzen getrennte Auswahlen. Nach Kauf oder kostenloser Freischaltung bleibt der Skin zum direkten Anlegen geöffnet. Die Kaufbestätigung zeigt Preis, Guthaben und Restguthaben.

Die individuellen Imagegen-Illustrationen liegen als transparente WebP-Dateien in `assets/art/marches/`. Prompts und Quellen sind in `docs/march-skin-art-prompts.json` festgehalten; Bildaufbereitung und Prüfung stehen in `assets/art/marches/README.md`. Eine Übersicht der ursprünglichen Motive liegt in `artifacts/march-skins/collection.png`.

Die Phönixgarde verwendet einen eigenständig animierten Feuervogel in den rotgoldenen Farben des Phönixschlosses. Obsidianpanzer, Goldfassungen, Sonnenkranz und mehrlagige Federn greifen die Architektur des Schlosses auf. Das gegliederte Storybook-Modell in `assets/city3d/march-creatures.js` bewegt Flügel, Kopf und Schwanzfedern. `node tools/render-march-creatures.cjs` erzeugt daraus `flight-phoenix.webp` (36 Bilder, 1,2 Sekunden, 384 px, etwa 350 KB) und ein PNG für reduzierte Bewegung. Der Drache verwendet dieselbe etablierte Flugpipeline mit 48 Bildern.

## Marschliste und Kameraverfolgung

`assets/js/world-march-hud.js` zeigt eigene aktive Märsche mit Status, Koordinaten, Restzeit und Fortschritt. Die neue Liste ersetzt die ältere HUD-Zusammenfassung. Das Antippen einer Zeile oder einer Marschfigur startet eine weiche Kameraverfolgung; Herkunft, Ziel, Truppendetails und der bestehende authentifizierte Rückruf sind im ausgewählten Marsch erreichbar. Kartenverschieben, Zielauswahl, Schließen und Escape beenden die Verfolgung. Serveraktualisierungen erhalten die Auswahl; abgeschlossene Märsche werden entfernt. Rückrufe drehen unterwegs um, einschließlich der serverseitigen Mindestdauer von zwei Sekunden.

Die Kamera verschiebt zwischengespeicherte Kartenebenen zwischen gelegentlichen Neuzeichnungen. Der Boden wird mit einem Rand von 96 beziehungsweise 144 px vorbereitet. Bewegte Figuren und Effekte bleiben an Weltkoordinaten gebunden. Browser-Hintergrund und reduzierte Bewegung verwenden beim Phönix das Standbild. Beschleunigen und Emotes aus der Videoreferenz sind noch keine implementierten Spielfunktionen.

`tests/world_march_follow.cjs` prüft Auswahl per Liste und Figur, Kamerabewegung, manuelles Abbrechen, Rückruf ohne Positionssprung, Serveraktualisierungen, Sammeln und vier Bildschirmformate. `tests/march_skin_world_app.cjs` prüft die Bedienung zusätzlich in der echten Haupt-App mit synthetischen Spielständen und echtem Rückruf-Endpunkt. Die interaktive Vorschau liegt unter `artifacts/world-march-follow-20260913/`.

Prüfstand 13. September 2026: `tests/march_skins.php` besteht 20 Backend-Prüfungen; `tests/march_windows.cjs` besteht 658 Prüfungen einschließlich der routenspezifischen Vorschauzeiten. `tests/march_skin_app.cjs` prüft Käufe, Freischaltung, Auswahl und Speicherung in der echten Haupt-App mit synthetischem Guthaben. `tests/world_march_skins.cjs` prüft Darstellung, reduzierte Bewegung, alte Märsche und Bildfehler. `tests/city_skin_app.cjs` besteht 44 Prüfungen der bisherigen Burgwahl und der echten 3D-Ansicht einschließlich Nahansicht, Gesamtansicht und eingebetteter Haupt-App ohne doppelte HUDs.

`tests/march_skin_world_app.cjs` verbindet die Komponenten: echter Monster-Marsch mit Phönixgarde, Übereinstimmung von ETA und Serverzeit, sichtbare Bewegung sowie unverändertes Aussehen und Timing nach Skinwechsel und Neuladen. Dafür `tools/preview-feature-fixture.php --march-skins --march-skin-world --port=18979` in einem Terminal offen halten und den Test gegen diese wegwerfbare Testwelt ausführen. Alle schreibenden Tests verwenden ausschließlich getrennte Testdatenbanken. Geprüfte Oberflächen umfassen Desktop, 390 px und 320 px breite Handyformate sowie kurzes Querformat. Ein physisches Mobilgerät stand nicht zur Verfügung.

Die Phönix-Ankunft in `assets/js/march-effects.js` zeichnet einen 2,1 Sekunden langen Feuerfächer, einen unterbrochenen Sonnenkranz und ausklingende Federn auf eine kleine transparente Canvas. Ein kurzer Anflug hebt und neigt ausschließlich die Grafik vor dem Einschlag. Marschposition, Kamera und Serverzeit bleiben dabei unverändert. Regionale Staubfarben, das Limit von vier gleichzeitigen Effekten und reduzierte Bewegung werden berücksichtigt. `tests/phoenix_arrival.cjs` prüft die Animation in vier Regionen, den Verzicht auf Gelände-Neuzeichnungen, Einmaligkeit und Aufräumen.

Der Drachenmarsch gehört zur Drachenfestung und kostet zunächst 2.400 Juwelen. Seine smaragdgrüne Drachensilhouette mit gealterter Bronze unterscheidet sich klar von der Phönixgarde. Beim Angriff folgt auf einen kurzen Sturzflug ein smaragd- und bernsteinfarbener Feueratem mit ausklingender Glut. Der Effekt verwendet eine einzelne kleine Canvas, verändert weder Gelände noch Kamera und wird bei reduzierter Bewegung vollständig ausgelassen. `tests/dragon_arrival.cjs` prüft Ablauf, Aufräumen, Geländeunabhängigkeit und das gleichzeitige Effektlimit.

Die übrigen 15 Premium-Themen verwenden eigene Kreaturen statt umgefärbter Truppen: Eisenkoloss, Rosenhirsch, Sonnenskarabäus, Leuchtrücken, Frostmammut, Jadeglockenlöwe, Glutsalamander, Rabenfürst, Uhrwerkhase, Saphirpfau, Sternenwal, Korallenleviathan, Wurzelkoloss, Sturmqualle und Finstersonnenwagen. Ihre passenden vektorbasierten Ankunftsmotive reichen vom Schildschlag und Rosenaufblühen bis zu Welle, Schneekristall, Sternorbit, Wurzelschlag und dunkler Korona. Alle verwenden dieselbe kleine Canvas-Pipeline mit regionaler Bodentönung, höchstens 14 Partikeln und maximal vier gleichzeitigen Effekten. Ablauf, Verdrängung, Kartenwechsel und reduzierte Bewegung geben die Canvas-Puffer frei. `tests/march_theme_arrivals.cjs` prüft alle Motive, ihre Unterscheidbarkeit, Weltverankerung ohne Geländeneuzeichnung, Effektlimit und Cleanup.

Die Original-PNGs liegen unter `asset-workflow/01-source/marches/<id>/march-source.png`. `tools/build-premium-march-assets.cjs` trimmt sie reproduzierbar, setzt rundum mindestens 32 Pixel transparenten Sicherheitsraum und schreibt mobile 512×512-WebP-Dateien nach `assets/art/marches`. Werkzeug, Generierungsmodus und die verwendeten Motiv-Prompts stehen in `docs/premium-march-creature-art.json`.

## Bewegung auf der Weltkarte

`ConquerMarchSkins.locomotion(id)` weist jedem Motiv seine eigene Fortbewegung zu. Eisenkoloss, Frostmammut und Wurzelkoloss stampfen schwer; Rosenhirsch und Jadeglockenlöwe galoppieren; Sonnenskarabäus und Glutsalamander krabbeln; Leuchtrücken und Korallenleviathan schwimmen; der Uhrwerkhase hüpft und der Saphirpfau stolziert. Rabenfürst fliegt, Sternenwal und Finstersonnenwagen gleiten, die Sturmqualle schwebt.

Alle 17 Premium-Skins besitzen nun echte animierte Bildfolgen. Bei den 15 neuen Kreaturen bewegen sich je nach Körperbau Beine, Klauen, Flügel, Flossen, Rüssel, Ohren, Schwänze, Wurzeln oder Tentakel über acht von Imagegen abgeleitete, registrierte Einzelposen. Die Retained Sheets liegen unter `asset-workflow/02-animation-sheets/<id>/sheet.png`; Bewegungsabläufe und Bodenkontakte stehen in `docs/march-creature-motion-spec.json`. `tools/build-articulated-march-assets.py` entfernt den Vorschauhintergrund, isoliert jede Pose, richtet die Silhouette aus und schreibt transparente 256×256-WebPs sowie passende Standbilder. Die verwendeten Bildprompts und Quellen dokumentiert `docs/march-creature-animation-art.json`.

Die Weltkarte lädt die animierte Datei nur für einen sichtbaren, tatsächlich laufenden Marsch. Angehaltene, sammelnde, außerhalb des Bildes liegende und im Hintergrund befindliche Märsche verwenden das Standbild. Die Sammlung aktiviert Animationen mit `IntersectionObserver` nur für sichtbare Porträts. Ein Ladefehler fällt einmalig auf das Standbild zurück. `prefers-reduced-motion` und die Spieleinstellung für reduzierte Bewegung erzwingen ebenfalls Standbilder und stoppen Spur sowie Schattenimpuls. Phönix und Drache behalten zusätzlich ihr größeres Fluglayout; die übrigen Kreaturen nutzen die normale Kartensilhouette.

Die Gangarten bewegen nur die Darstellung. Kartenposition, Marschzeit und Serverzustand bleiben unverändert. Bodenwesen erhalten einen mit dem Tritt synchronisierten Kontaktschatten und eine Bodenspur; Wasserwesen erzeugen Wellen, Flugwesen eine leichte Luftspur. `tools/validate-articulated-march-assets.cjs` prüft Framezahl, Abmessungen, Transparenz und mobile Dateibudgets. `tests/march_motion_contract.cjs`, `tests/march_motion_loading.cjs` und `tests/world_march_locomotion.cjs` prüfen Medienvertrag, sichtbarkeitsabhängiges Laden, Fallbacks und alle Bildschirmformate.
