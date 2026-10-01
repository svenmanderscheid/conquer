# Integrated gathering animations — 23 September 2026

Production: `assets/js/world-resource-motion.js`, called by `world-painted.js?v=2`.
Only a server-reported `gatherer_march_id` activates work. Free fields keep their
original still artwork. Cached transparent sprites are loaded once per resource
type, only when an occupied visible marker first needs them. Nothing changes
server coordinates, permissions, gathering times or attack rules.

- Gold: empty mine plate plus separate cart, following the rails inward and back,
  shrinking/dimming inside the doorway over a 6.4-second loop.
- Quarry: empty crane plate plus suspended stone, variable-length rope and slight
  load sway over 5.8 seconds. The quarry itself stays fixed.
- Lumber: four painted work poses, registered to a fixed stump, 2.4-second cycle.
- Farm: four scythe poses registered to the feet, 3.2-second cycle.
- Crystal: cached facet-only light mask, 3.8-second cycle; doorway excluded.

The existing 192px per-marker canvas is reused, with no new animation loop in the
game and no animated full-map redraw. Idle resource canvases are not repainted.
Reduced motion and light graphics use a stable work pose; invisible maps stop.
Blue/red ownership rings and flags and all server-owned action rules remain.

## Preview and checks

`/assets/world-preview/work.html` shows the five real renderer animations without
changing any game state. Includes gathering/free and reduced-motion switches.

`tests/world_painted.cjs`: all five work cycles, reduced motion, release cleanup,
existing monsters/castles, assets, subfolder URLs and four viewport sizes.
`tests/gathering_occupation_app.cjs`: actual disposable app, own/ally/enemy/free,
five sizes from 320×568 to 1280×800, light/reduced/hidden, relationship refresh.
`tests/resource_work_preview.cjs`: gallery in four sizes, all five assets,
reduced-motion and idle transitions; screenshots in `artifacts/resource-work/`.
No real marches were sent or recalled. The logged-in local app was also inspected.
An actual phone performance check remains for a connected mobile test device.

## Generated assets and prompts

Created with the **imagegen skill and built-in Imagegen**, not the CLI fallback.
Deployment images preserve transparency and are downscaled by
`tools/build-resource-work.ps1`; six PNGs total about 1.47 MiB, lazily loaded.
All final assets are project-local under
`assets/art/fantasy-village-v1/work-v1/`:

| File | Size | Original generation ID |
|---|---|---|
| gold-empty.png | 384×384 | d6c828af-0dca-4397-8934-069ddbd3c4e5 |
| gold-cart.png | 256×256 | 35b84a1c-aab0-4f0c-b541-a36241f527d6 |
| quarry-empty.png | 384×384 | fe19806e-3c79-4d9b-8425-70ed7004dacc |
| quarry-load.png | 256×256 | 990c98d6-a8ef-4bdd-bc4b-caf0d2ef32c2 |
| lumber-worker.png | 768×768 | 271399d7-be0b-460f-8bf2-9544c17de5cb |
| farm-worker.png | 768×768 | 1044fb0a-0b43-41fd-86e7-67ef7c75fd19 |

Originals retained in the built-in generated-images directory. Existing game
artwork is untouched. The first experimental combined mine/cart atlas was rejected
because its cells were not aligned and the cart retained track fragments.

### gold-empty.png — reference/edit target: world-gold-v2.png

Use case precise-object-edit. Edit this exact square gold mine game sprite: REMOVE ONLY the mine cart, its gold load and its wheels from in front of the mine entrance. Reconstruct the now unobstructed tracks and dark interior beneath it. Absolutely preserve the composition, positioning, size, framing, every rock, timber, bushes, gold nuggets, light, paint style of the original. Do NOT zoom in. Do NOT add or remove anything else. Keep original transparent background and square canvas. This is the empty background plate for animating the cart separately. No text, no border.

### gold-cart.png — reference/edit target: world-gold-v2.png

Use case background-extraction. Make a single isolated sprite of ONLY the little gray GOLD-FILLED MINE CART in this reference, including wheels and golden ore. Nothing else. No rails, NO TRACKS, no wooden sleepers, no ground, no mine, no shadows outside cart. Actual fully transparent background. Keep the cart's original three-quarter isometric view and soft hand-painted fantasy cartoon drawing, brown contours. Cart facing lower-left, wheel side visible on right, gold load visible on top. Center the entire cart, large in a square image with 15 percent transparent margin on all sides. No text.

### quarry-empty.png — reference/edit target: world-quarry-v8.png

Use case precise-object-edit. This exact square quarry sprite is the edit target. Remove ONLY the suspended gray stone block and the hanging rope below the pulley, in the middle-left under the crane beam. Reconstruct the unobstructed quarry wall/ground behind the removed hanging block. Preserve the crane itself including wooden posts, beam, wheel/pulley. Preserve absolutely all other details, same framing, same scale, same transparent square canvas and hand-painted soft cartoon fantasy style. Do not zoom or recenter. No text or new elements. This is a clean plate for animating the load separately.

### quarry-load.png — reference/edit target: world-quarry-v8.png

Use case background-extraction. Isolate ONLY the single hanging stone block carried by the crane in this quarry, with its V-shaped rope sling around it and little loop at the top of sling. No long suspension rope above the sling. No crane, no rocks, no ground, no background. A separate movable game sprite on actual transparent background. Preserve the soft hand-painted fantasy cartoon style, warm gray stone, brown rope, original three-quarter isometric view. Centered square composition, object occupies middle 65% of canvas, entire load visible. No text.

### lumber-worker.png — style reference: world-lumber-v8.png

Use case stylized-concept. Reference image is STYLE ONLY. Create a game SPRITE SHEET of a tiny CHIBI LUMBERJACK chopping a short log on a stump. ONE square transparent canvas with exactly FOUR equal square cells arranged 2 columns x 2 rows. Same identical character, stump, scale and camera in all four cells; feet and stump at same coordinates per cell. Entire pose stays INSIDE its own cell with generous transparent margins; no crossing cell boundaries, no gridlines, no text. Character at left of stump, facing right in high-angle three-quarter view. Big head, stout body, green cap, russet shirt, brown boots, short beard, warm hand-painted fantasy village style matching reference. Top-left: axe raised high with both hands. Top-right: axe swinging forward halfway. Bottom-left: axe head contacts log on stump, body leans into chop, two tiny chips. Bottom-right: axe halfway lifted recovering to top-left. Include full person, axe and stump in every cell, no hut, no grass patch, no shadows/background or other elements. Actual alpha transparency. Simple chunky silhouette, not realistic. Each cell is a consecutive animation frame.

### farm-worker.png — style reference: world-farm-v8.png

Use case stylized-concept. Reference is STYLE ONLY. Create a game SPRITE SHEET of a tiny CHIBI FARMER harvesting with a long curved SCYTHE, not a hand sickle. ONE square transparent canvas, exactly FOUR equal square cells in 2 columns x 2 rows. Same identical character, scale, camera and foot position in all four cells. Character faces LEFT toward a small tuft of wheat. Big head, stout short body, straw hat, cream shirt, blue overalls, brown boots. Full person and complete wooden scythe with silver curved blade visible inside every cell with generous margins. No overlap between cells, no grid, no labels. Top-left frame: scythe drawn back ready to sweep. Top-right: scythe sweeping forward at waist height. Bottom-left: leaning into completed low sweep to left, three wheat stalks falling. Bottom-right: gently straightening and drawing scythe back. Warm soft hand-painted storybook fantasy style matching reference, brown contours, chunky simplified shapes. No house, no fence, no ground patch, no backgrounds, NO text. Actual alpha transparent background.
