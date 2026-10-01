# Luxemburg als Spielwelt — Union of Kingdoms

## Curved two-field rivers — 28 September 2026

`tools/build-lux-game-rivers.py` derives `game-hydrology.json` from official
main rivers. The play view loads this 134 KB dataset in both border modes.
The former orthogonal steps are replaced by gentle quadratic bends with a
constant two-field width. Small meanders are simplified between junctions;
rounding remains inside the local control-point hull, with no spline overshoot.
Curves are sampled and flattened to a 0.01-field tolerance. Drawing and all
city/teleport checks use the same resulting polylines and widths; there are no
extra blocked riverbanks.

Nine river courses remain: Mosel, Sauer, Our, Alzette, Attert, Eisch, Wiltz,
Clerve and Woltz. Mamer, Syre, White/Black Ernz, Chiers and Wark are removed from
both drawing and placement. All ten large lake polygons keep their shorelines.
Navigation anchors follow the curves and removed rivers leave the dropdown.
This is a fictional game adaptation, not an exact river map.

`node tests/lux_game_rivers.mjs` checks two-field widths, smooth bends,
retained junctions/lakes, navigation and bank-side placement. It compares
water-blocked 4x4 sites against the original 15-river geometry, requiring at
least 15% fewer blocked sites; other placement rules still apply. The old
25% comparison for the narrow orthogonal version is superseded.

## Official source geometry

The play view uses the official Géoportail / AGE INSPIRE Hydrographic Network
and Standing Waters datasets (download snapshot: June 2026, CC0):

- https://data.public.lu/en/datasets/inspire-annex-i-theme-hydrography-physical-waters-waterbody-watercourselink/
- https://data.public.lu/en/datasets/inspire-annex-i-theme-hydrography-physical-waters-waterbody-standing-water-age-3/

`tools/build-lux-hydrology.py` and `tools/lux-hydro-project.cjs` transform the
source EPSG:3035 northing/easting coordinates using Proj4js 2.22.0 and the same
projection as the canton data. Original files and the build-only library are
cached in `artifacts/world-lux-preview/hydro-source`. The intermediate
`hydrology.json` contains only the 15 selected main rivers
(37 source links) and 10 lake polygons of at least 50,000 m² (5 hectares),
including the Upper Sûre reservoir. Minor tributaries and small ponds are
excluded during the build, so they neither draw nor block cities/teleports.
Line simplification is bounded to 0.12 field (roughly nine metres); endpoints
and confluences stay fixed. River widths are deliberately enlarged for the
game view and are not measurements of the real channels.

Overview, minimap and close view use the same selection of main rivers and
large lakes, leaving larger contiguous areas for alliance towns. **Fluss / Gemeinde** jumps
to the retained game river coordinates. The former sine-wave river, oval pond and invented
periodic bridges have been removed from this play view. The older illustrated
concept (`index.php`) remains a separate artistic sketch.

`hydrology.mjs` indexes line segments/lakes spatially. Drawing, dry-land city
generation, resources, trees and the teleport probe share the same geometry.
Placement checks the complete rectangle against river widths and lake rings,
including narrow channels that would evade a centre/corner-only check. Initial
example landmarks seek a dry position near Mersch. No production game rules or
server state change. Rivers use the same game paths in both border modes;
portions outside the smoothed playable silhouette remain unbuildable.

Checks: `node tests/lux_hydrology.mjs`, `node tests/lux_world_preview.mjs`,
`node tests/lux_play_borders.mjs`, JavaScript/PHP syntax and browser views at
desktop, narrow portrait and landscape. No physical-device performance test.

## Simplified game boundaries and placement probe — 28 September 2026

The play view now defaults to `borders=game`; `borders=original` restores the
previous administrative geometry. Both use the same camera and rendering.
The overview has comparison buttons, an optional dashed original overlay and
an optional city layer. Its commune jumps and the minimap follow the active data.

`tools/build-lux-play-borders.py` produces `game-geography.json` from the preserved
`geography.json`, using Python/NumPy. A four-field grid smooths canton outlines
first and communes inside those cantons second. Disconnected remnants are
reassigned to adjacent communes. Each commune and canton remains connected;
the shared grid supplies closed polygon edges without overlaps or gaps.
The result has 467,936 land fields before water exclusions. These are fictional
game boundaries, not an updated administrative dataset.

The **TP-Platzprobe** checks all 16 occupied fields (including corners), dry
ground, a single canton, existing cities/landmarks and the same procedural
resource/monster positions used by the renderer. Click/tap a target or use
arrow keys on the focused map; **Freien Platz finden** searches the nearby area.
Green/red footprints include a written reason. This only probes the example
world: it never teleports an account, consumes an item or calls a game endpoint.
Production conditions such as army state and alliance permission are not simulated.

`node tests/lux_play_borders.mjs` checks each exported grid cell against both
polygon layers, count/coverage/footprints and valid/blocked placement cases.
`node tests/lux_world_preview.mjs` also retains the original-geometry regression.

## Normal game view — added 28 September 2026

`play.php` adds the requested large, freely pannable view using the approved
grass tiles, city skins and nameplate assets from the existing world renderer.
The illustrated overview links to it via **Spielansicht**. Its own minimap and
overview dialog show the exact same cities and current camera rectangle.

The illustrative field frame is 768 × 1100 (about 468,000 fields inside the
Luxembourg silhouette before water exclusions). Field grid and municipality
borders can be toggled. Cities reserve 4 × 4 fields. Alpha spreads 20 deterministic
example towns across all 12 cantons. Live retains those towns and adds 980,
covering all 100 communes and filling the largest unoccupied spaces over the
entire land surface. Towns do not cluster around Mersch or commune centres;
Mersch is simply the example player's home. All towns and major landmarks have dry,
non-overlapping footprints. Only visible buildings, nearby scenery and tiles
are rendered; the entire world is never rasterized into a giant texture.

The population selector demonstrates **20 / 1000 total example cities**, not
online users or proven server capacity. The user's future target is **1000
simultaneously active players**. Offline players keep cities, so total accounts,
active connections, map density and server load must be sized independently.
Production remains restricted to 256 × 256; this preview does not change it.

`node tests/lux_world_preview.mjs` checks exact counts, stable Alpha towns when
switching to Live, canton/commune coverage, spatial spread, dry land and
non-overlapping footprints.
The browser was checked at desktop, 320px portrait and 844 × 390 landscape,
including population selection, grid toggle, minimap, commune navigation and
native city dialogs. No physical-device multitouch or server load test was run.
Extra art encodings are reproduced by `tools/build-lux-play-art.py`.

Read-only illustrated world concept at `/assets/world-lux-preview/`.

The Luxembourg outline and the 12 canton / 100 municipality boundaries use ACT
Luxembourg / SIG-GR 2026 data, CC BY 4.0. Geographic coordinates are projected
with a local equirectangular projection (49.8° reference latitude). Attribution
and source links are in the information dialog. The source data was previously
downloaded for the municipality prototype in `artifacts/realm-map/geodata.json`.

Landscapes, roads, waterways, cities, ownership and encounters are fictional
examples. The world is deliberately compressed for an overview; the final game
would need much larger playable areas inside each municipality. The Krounbuerg
uses an existing castle illustration as a placeholder. No game API, session,
database, service worker or player account is loaded or modified by the preview.

## Interaction

- Drag / single-finger pan, wheel / two-pointer pinch, plus and minus controls.
- Keyboard: arrow keys pan the focused map; + / − zoom; Home resets it.
- Landscape, canton and municipality layers; direct polygon selection.
- All 100 municipalities are also reachable through the native select.
- Clickable sample cities, resources, monsters, dungeons and the crown fortress.
- Native dialogs, Escape/backdrop/close button, browser Back/Forward, deep links.
- The dungeon link opens the corresponding existing dungeon-atlas concept.
- No automatic animation loop; canvas redraws only when the view changes.

## Assets and build

`art/` contains resized WebP copies of approved existing world artwork. Originals
are untouched. `tools/build-lux-world-preview.py` documents source paths and
rebuilds the image copies plus `geography.json` using Python/Pillow. The generated
files are standalone: neither Python nor the source GeoJSON is needed at runtime.
The preview uses local fonts and loads the shared `village-theme.css` last.
UI colours come from its `--ui-*` tokens. No new 3D/WebGL runtime or external CDN.

## Verification — 28 September 2026

- JavaScript syntax and PHP lint pass.
- Browser checks at 1280×800, 320×700, 320×568 and 844×390.
- Boundary layers, native commune search, direct map selection, city and crown
  dialogs, close actions, Escape and browser Back/Forward exercised.
- Preview asset loading and browser console checked. Screenshots are in
  `artifacts/world-lux-preview/`.
- Existing `/city#city` inspected on desktop, portrait and landscape as the
  style reference, including the building-selection controls. No main-app files
  or gameplay actions changed.
- Physical-device touch / multitouch remains unverified; the preview implements
  Pointer Events and supplies buttons/selects for every discovery action.
