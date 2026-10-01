# Union of Kingdoms — Luxembourg dungeon atlas concept

Open `assets/dungeon-preview/` through the existing PHP server, for example
`http://localhost/conquer/assets/dungeon-preview/`.

This is an isolated, read-only prototype. It does not load player sessions, call
game APIs or change the current dungeon system. Twelve cantons have draft dungeon
names, bosses and unique material drops. Existing approved dungeon and item art
illustrates these concepts; the item pictures are placeholders, not new items in
the game catalog. The optional progression overlay is a fictional example.

Select a canton on the map or in the list to open its dungeon in a modal popup.
The map has no permanent details sidebar. The popup has a fixed violet title bar,
close button, scrollable content and a fixed footer in portrait and landscape.
Close with the × button, “Zur Karte”, Escape, a backdrop tap or browser Back;
focus returns to the initiating marker or list card. Forward reopens the popup.
“Nächster Kanton” replaces the current popup selection, so Back still closes it
in one step. A fragment URL opens its dungeon directly and survives reload.
Native dialog focus containment and reduced motion are respected.

## Luxembourg lore — September 27, 2026

All twelve dungeon stories, bosses and signature drops now draw on a specific
place, craft, historical episode or legend from their canton. Each popup includes
the expandable “Luxemburg hinter der Legende” section with a short factual note
and links to municipal, cultural or official tourism sources. The note identifies
invented elements and distinguishes the Melusina legend from historical facts.
Research and the proposed broader world direction are recorded in
[`docs/LUXEMBOURG_FANTASY_CONCEPT.md`](../../docs/LUXEMBOURG_FANTASY_CONCEPT.md).
The proposed crown fortress, municipalities and court offices are design ideas;
they are not implemented game systems.

The longer stories and expanded history sections were checked at 1280×900,
390×844, 320×568, 844×390 and 740×360. All twelve popups load their illustrations,
item images, history and source links. No horizontal overflow, browser errors,
missing assets or write requests were observed. Popup navigation and touch remain
functional. Updated screenshots are in `artifacts/dungeon-popup/`.

## Geography and attribution

Source: **ACT Luxembourg. Harmonization: SIG-GR / GIS-GR 2026**.
[Cantons in Luxembourg 2026](https://data.public.lu/en/datasets/cantons-in-luxembourg-2026/),
updated January 21, 2026, [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/).

`cantons.json` derives from the source GeoJSON using a local equirectangular
projection at latitude 49.8°, flipped screen coordinates and rounding to 0.1 SVG
units. Original canton boundaries are retained; colors, dungeon locations and
vegetation are fictional. Attribution is also displayed below the map.

To regenerate, download the dataset's GeoJSON and run:

```
python tools/build-dungeon-atlas-map.py path/to/cantons-lux-2026.geojson
```

The browser loads only local assets. No map framework or runtime external service
is required. UI materials and fonts use the shared game theme loaded last.

## Preview verification — September 26, 2026

Checked in Chromium at 1440×1100, 1280×900, 390×844, 320×700 and 844×390:
no horizontal overflow, overlapping 44 px marker targets, missing images or browser
errors. All twelve marker centers remain within their respective canton boundaries.
Verified touch selection for every canton, list selection, example progression,
Back/Forward, selection after reload, keyboard activation, focus after Next and
reduced-motion behavior. The preview issues no write requests. Screenshots and local
verification output are in `artifacts/dungeon-atlas/`.

## Popup verification — September 27, 2026

Verified the popup at 1280×900, 390×844, 320×568, 844×390 and 740×360.
Title bar, close control and footer remain within the viewport while details scroll.
Checked all twelve selections, touch, keyboard access, focus restoration, every
closing method, list entry, repeated Next followed by Back, Forward, direct fragment
URLs, reload and locked-state examples. No browser errors, missing assets or write
requests. The visible name, document title and accessible return link now use
**Union of Kingdoms**. Screenshots and results: `artifacts/dungeon-popup/`.
