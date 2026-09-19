# Illustrated game HUD

These eight standalone SVG icons were drawn as code for the Conquer interface. They are actual UI assets, not flattened screenshot mockups. The inventory chest reuses the original SVG already drawn for this project's inventory.

| File | Destination |
|---|---|
| quest.svg | Aufgaben: purple journal and green check |
| inventory.svg | Inventar: gold-bound wooden chest |
| reports.svg | Post: parchment envelope and red wax seal |
| alliance.svg | Allianz: blue and gold shield, crossed swords |
| city.svg | Stadt: stone castle with blue roof and red flag |
| world.svg | Welt: folded illustrated map |
| expeditions.svg | Feldzüge: sword and flame |
| menu.svg | Menü: four parchment panels in a framed board |

The user-supplied LOK `Overlay_TOP.png`, `mapoverlay.png`, `overlay_villageview.png`, and `MAP/MAP_Screen.png` informed HUD hierarchy, icon scale, plaque placement and colors. No screenshot pixels are used in these SVGs.

`assets/css/mobile-shell.css` positions the profile, resource strip, five-button dock, quick actions and compact objective. It must follow older shell/layout styles; `map-overlay.css` may follow it to position the map-local controls.
