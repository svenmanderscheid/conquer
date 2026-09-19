# Conquer interface materials

These SVG assets were drawn as code for the game's interface. They contain no raster screenshots, external resources, text instructions or scripts.

- `realm-seal.svg`: the kingdom's bronze castle shield, used as the application mark.
- `wood-grain.svg`: a subtle repeating wood grain for HUD and navigation rails.
- `stone-grain.svg`: a subtle painted-stone grain for windows and parchment accents.
- `corner-brass.svg`: a riveted bronze corner for game dialogs.

The supplied LOK screenshots informed hierarchy, prominent game icons and framed windows. These files are original UI decorations. The actual city, portraits, inventory objects and map remain separate assets.

`assets/css/game-theme.css` is the material layer. Load it after the existing component/layout styles. The world atlas may follow it with its own scoped styles. Research can use the shared `--game-*` variables; the theme deliberately leaves its graph geometry, connections and node states under `research-tree.css` ownership.

The theme does not change game data, button handlers, progress calculations or navigation. It maintains the existing responsive breakpoints and reduced-motion behavior.
