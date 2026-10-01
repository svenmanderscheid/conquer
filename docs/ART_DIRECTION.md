# Cartoon fantasy assets

For all menus, HUD elements, building dialogs, backoffice screens and world-map colours, also follow **`docs/UI_STYLE_GUIDE.md`**. The shared UI tokens and appearance layer are in `assets/css/village-theme.css`; the active world terrain palette is in `assets/js/world-landscape.js`. These extend the village reference below.

The user-approved interface reference is **`assets/art/ui-violet-beige-reference.png`** (variant A, 13 September 2026): dark-violet headers, warm beige surfaces, restrained gold accents, Almendra text and Lora numerals. This applies to the main app, map overlays and backoffice. Use the shared UI tokens for those surfaces; the painted buildings, role colours and natural landscape continue to use the world palette below.

Generated with the built-in Imagegen tool on 2026-09-08. These are new original illustrations; the supplied screenshot informed the requested stylistic direction and was not used as a source of extracted assets. No CLI image-generation fallback was used.

Files are project-local under `assets/art/`. The production village is `village2.png`; `village.png` is the unused first draft. `knight.png`, `archer.png`, `rider.png`, `orc.png`, `skeleton.png` and `golem.png` illustrate the guide, troops and encounters. `world.png` is the map backdrop.

## Active painted-world style rules

Since 26 September 2026 the game uses only the painted city (`assets/js/city-painted.js`) and the existing illustrated world map. The separate 3D/2.5D city, Three.js runtime and GLB models were removed at the user's request; see `REMOVED_3D_2026-09-26.md`. `village2.png` remains the binding style reference. Current city and world asset paths are defined by the active renderer modules; preserve those approved illustrations.

Use chunky, rounded silhouettes, warm ivory walls, saturated roof colors, dark espresso outlines and two broad light values. Paths are warm tan with soft edges and sparse irregular marks. Landscapes use muted sage grass and varied tree silhouettes. Characters use large heads, short limbs and clear, oversized role equipment. Avoid photographic textures and glossy realism. Add interface depth through restrained shadows, layering and light edges, using the shared UI variables.

Animation stays calm, supports reduced motion and never obscures labels, selection or touch controls. Inspect the whole city and building dialogs in the actual app at desktop, narrow portrait and landscape sizes. Keep images compressed and reuse sprites.

Construction reuses the approved scaffold artwork with a fixed frame and worker silhouette. Only the isolated hammer moves, with small timed dust/chip accents at the strike point; do not stack transparent whole-frame poses or move the entire building. The detail SVG is mounted only for an authoritative active build queue, retained across refreshes and removed when that queue settles. Countdown expiry alone does not finish the work. Timer labels stay above the scaffold. Light graphics retains the small tool motion and omits particles; reduced motion uses a fully still worksite. Dialogs, hidden tabs and the world view pause construction. Check `tests/city_construction_app.cjs` and `tests/painted_city.cjs`.

Luxembourg conquest landmarks use the eight transparent illustrations in `assets/art/territory-v2/`: six commune benefits, a canton fortress and the Royal Castle. The 30 September revision gives them broader halls, stronger foundations, heavier bastions and more substantial walls while retaining the original roof colors and benefit emblems. Map and territory dialogs share `assets/js/territory-art.js`; their illustrated size follows the authoritative footprint. Sources, prompts, compression and visual checks are documented in `TERRITORY_ART.md`. The previous `territory-v1` set remains available for comparison.

### Premium castle and march skins

Every premium castle skin needs its own visible, continuously readable animation. Animate the part that defines the castle fantasy—such as wings, gears, water, roots, lightning or celestial bodies—rather than adding a generic glow. Keep motion calm enough that building labels and selection remain clear, and provide a stable reduced-motion state.

The matching march skin must sell the same fantasy through a unique silhouette and movement. A recolor of the standard soldiers is not a premium march skin. Prefer a creature, construct, vehicle or unmistakable formation that remains identifiable at the normal world-map size. Its arrival animation must complete the same theme: the moving subject, impact shape, colors and particles belong to that skin. Snapshot the selected skin when the march starts so changing equipment cannot alter an active march or its arrival effect.

Review castle, march loop and arrival together before release. Verify their shared palette and motifs, the complete animation cycle, reduced motion, mobile readability and effect cleanup. Arrival effects may not redraw terrain, shift the authoritative march position or obscure map controls.

### Review checklist for every visual addition

- Compare the new content with `village2.png` and the surrounding illustrated assets at normal zoom.
- Inspect the silhouette and artwork once in a close view and once in the full-city view.
- Confirm paths and animated routes remain unobstructed.
- Confirm labels, selection and mobile controls remain readable and clickable.
- Check JavaScript syntax, current cache-version imports and browser warnings or errors.
- Reuse image assets and inspect mobile memory and loading after substantial additions.

## Final village prompt

Landscape 1536x1024 flat 2D cartoon fantasy mobile game village scene. EXTREMELY SIMPLE BOLD GRAPHIC STYLE, thick dark brown 5px outlines around every object, flat solid color fills, TWO-TONE cel shading only, no textures, no hatching, no painterly detail, no rendered lighting, no realistic 3D. Cute chunky oversized buildings with rounded roofs, simplified storybook shapes, chibi RPG environment art. Muted sage grass, stylized pine trees around border, a curving tan path between six buildings. Large squat blue roof castle centered at x52% y24%; orange roof farm with small yellow wheat field at x23% y31%; timber lumbermill at x81% y36%; purple roof wizard academy at x24% y65%; red roof barracks at x52% y68%; gold mine at x82% y66%. Each building very simple, 2-3 windows, single colored roof, thick outlines, looks like a cute toy sticker. Small blue river at far left. A few tiny mushrooms and flowers. High angle three quarter view. No characters, no UI, no labels, no letters. All buildings fully visible with breathing room. Match flat chibi mobile RPG cartoon drawings, prioritise simple readable shapes over any detail.

## Knight prompt

A single cute chibi fantasy knight mascot, full body, facing slightly right, oversized head 50 percent body height, tiny stout body, fluffy ivory white hair, determined simple black oval eyes, pointed ears, blue tunic, orange scarf, brown boots, holding a chunky golden sword and little round blue shield. Very simple flat 2D mobile cartoon RPG art, thick dark espresso outlines, only 2 tone cel shading, bold clean shapes, charming proportions like a sticker character from a casual mobile adventure game. Flat pale cream background. No text, no border, no detailed textures, no realism, no 3D. Centered, generous margins.

## Archer prompt

Single cute chibi fantasy archer mascot, full body centered, oversized head 50 percent body height, tiny stout body, fluffy auburn hair under a green hood, simple black oval eyes, pointed ears, moss green tunic, golden belt, brown boots, carrying chunky wooden bow and quiver. Very simple flat 2D mobile cartoon RPG art, thick dark espresso outlines, only 2 tone cel shading, bold clean shapes, charming proportions like a sticker from a casual mobile adventure game. Flat pale cream background. No text, no border, no detailed textures, no realism, no 3D. Centered generous margins.

## Rider prompt

Single cute chibi fantasy cavalry mascot, full body centered, tiny knight with oversized head, silver round helmet with golden crest, simple black oval eyes and red scarf, riding very small stout adorable brown pony with blue saddle blanket. Very simple flat 2D mobile cartoon RPG art, thick dark espresso outlines, only 2 tone cel shading, bold clean shapes, charming proportions like a sticker character from a casual mobile adventure game. Flat pale cream background. No text, no border, no detailed textures, no realism, no 3D. Centered generous margins.

## Orc prompt

Single cute enemy chibi green orc with large square head 50 percent body, tiny muscular body, two little tusks, dark eyebrows, simple cartoon eyes, leather belt and brown loincloth, holding a short wooden club. Looks fierce but playful. Very simple flat 2D mobile cartoon RPG art, thick dark espresso outlines, only 2 tone cel shading, bold clean shapes, charming proportions like a sticker character from a casual mobile adventure game. Flat pale cream background. No text, no border, no detailed textures, no realism, no 3D. Centered generous margins.

## Skeleton prompt

Single cute chibi skeleton warrior with a very large round ivory skull, tiny stout body, simple black eye sockets, a chipped short sword and purple scarf. Full body centered, very simple flat 2D mobile cartoon RPG art, thick dark espresso outlines, two tone cel shading, bold clean shapes, charming sticker character proportions. Flat pale cream background, generous margins. No text, no border, no detailed textures, no realism, no 3D.

## Golem prompt

Single cute chibi chunky stone golem with an oversized square gray rock head, tiny stocky rock body, turquoise glowing eyes and rune, and moss growing on its shoulders. Full body centered, very simple flat 2D mobile cartoon RPG art, thick dark espresso outlines, two tone cel shading, bold clean shapes, charming sticker character proportions. Flat pale cream background, generous margins. No text, no border, no detailed textures, no realism, no 3D.

## World prompt

Landscape background for a top-down 2D chibi cartoon fantasy strategy game world map. Simple thick dark green outlines, flat muted sage and olive colors, two-tone cel shading, very simple clean graphic shapes matching cute mobile RPG art. Broad open grassy clearing across the center 70 percent of image, small clustered rounded pine tree forests at corners and edges, a narrow winding tan trail, small turquoise pond in far lower right corner, tiny gray stone clusters and mushrooms at edges. View directly from above, not perspective. Keep center very clear so many interactive game markers can overlay without visual clutter. No buildings, no characters, no icons, no text, no labels, no grid, no interface, no realistic shading or textures. Premium hand-drawn cartoon world, friendly and inviting.
