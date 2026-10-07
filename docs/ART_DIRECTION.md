# Cartoon fantasy assets

For all menus, HUD elements, building dialogs, backoffice screens and world-map colours, also follow **`docs/UI_STYLE_GUIDE.md`**. The shared UI tokens and appearance layer are in `assets/css/village-theme.css`; the active world terrain palette is in `assets/js/world-landscape.js`. These extend the village reference below.

The user-approved interface reference is **`assets/art/ui-violet-beige-reference.png`** (variant A, 13 September 2026): dark-violet headers, warm beige surfaces, restrained gold accents, Almendra text and Lora numerals. This applies to the main app, map overlays and backoffice. Use the shared UI tokens for those surfaces; the painted buildings, role colours and natural landscape continue to use the world palette below.

Generated with the built-in Imagegen tool on 2026-09-08. These are new original illustrations; the supplied screenshot informed the requested stylistic direction and was not used as a source of extracted assets. No CLI image-generation fallback was used.

Files are project-local under `assets/art/`. The production village is `village2.png`; `village.png` is the unused first draft. `knight.png`, `archer.png`, `rider.png`, `orc.png`, `skeleton.png` and `golem.png` illustrate the guide, troops and encounters. `world.png` is the map backdrop.

## Active painted-world style rules

World-map proportions (3 October 2026): default castles use a 3.65-tile illustration, resource sites 1.75 tiles, basic solo monsters approximately 1.35 tiles and Magdar 2.6 tiles. Dragon silhouettes retain their transparent-padding and optical-height compensation. Regional bosses remain approximately 2.6–2.7 tiles, and Congress uses a 6.2-tile illustration above its unchanged 7×7 authoritative footprint. Actual coordinates, occupancy, gathering and battle rules do not change. Sprite backing stores follow displayed size (80–192 px); animation geometry stays stable between frames and buffered off-screen sprites are not animated. Checks: `tests/world_painted.cjs`, `tests/world_drag_rendering.cjs`, `tests/world_optimization_app.cjs`. Main-app captures: `artifacts/map-optimization/`.

Since 26 September 2026 the game uses only the painted city (`assets/js/city-painted.js`) and the existing illustrated world map. The separate 3D/2.5D city, Three.js runtime and GLB models were removed at the user's request; see `REMOVED_3D_2026-09-26.md`. `village2.png` remains the binding style reference. Current city and world asset paths are defined by the active renderer modules; preserve those approved illustrations.

The city terrain uses `assets/art/village-layered-v2/runtime/terrain-extended.webp` (1 October 2026), with additional woodland outside the walls. The painting scrolls with the existing 3:2 building area and fills the space behind the HUD; do not add a separately scaled fixed copy behind it. The original `terrain.webp` remains available. The built-in Imagegen prompt and source coordinates are recorded in `terrain-extended.source.json` beside the artwork. Desktop, narrow portrait, short landscape and building-action captures are under `artifacts/terrain-extension/`.

Lower-edge regression coverage: `tests/city_terrain_app.cjs` checks the actual app at six desktop, portrait and landscape sizes, including left/center/right panning to the bottom, undistorted terrain, building touch/actions and returning from the world map. A fixed village background combined with bottom scroll padding exposes a second scaled painting and must fail this check. On 5 October the live site was found serving this older renderer again, while the local renderer already contained the correction. The user-approved fix was deployed as a targeted change to the two live display files, preserving their other features. Both versioned live hashes matched the tested package; the online lower edge and building information dialog passed inspection. Evidence: `output/playwright/village-bottom-fix/verification.json` and `live-bottom-fixed.png`. Deploy the renderer and its matching village styles together.

User correction, 7 October 2026: every Archer tier uses a hand-drawn bow and arrow. Do not infer crossbow artwork from legacy troop or research names. The active Archer portraits use the `fire-archer-bow` prefix in `assets/art/characters/fantasy-troops-v3/`; T4 and T5 reuse the original corrected bow masters. Their string meets the arrow nock at the draw hand. The red role clothing and each tier's equipment colours remain intact. See `archer-bow-manifest.json` beside the portraits for sources and export hashes.

Use chunky, rounded silhouettes, warm ivory walls, saturated roof colors, dark espresso outlines and two broad light values. Paths are warm tan with soft edges and sparse irregular marks. Landscapes use muted sage grass and varied tree silhouettes. Characters use large heads, short limbs and clear, oversized role equipment. Avoid photographic textures and glossy realism. Add interface depth through restrained shadows, layering and light edges, using the shared UI variables.

Animation stays calm, supports reduced motion and never obscures labels, selection or touch controls. Inspect the whole city and building dialogs in the actual app at desktop, narrow portrait and landscape sizes. Keep images compressed and reuse sprites.

The 5 October 2026 image review aligns building dialogs, training prerequisites and quest cards with the active city. `assets/art/buildings/painted-v3/` reuses the fifteen approved runtime buildings and adds a clearly finished wall illustration. The default castle portrait also reuses `castle_rounded.webp`. The lumber-camp worksite repeats approved poses 1, 2, 4, 2 to remove the third pose's discontinuous beam and trestles.

`assets/art/map/painted-v2/` contains the painted alliance centre, four elemental shrines and seventeen legacy castle skins. Stable skin identifiers, colours and signature motifs remain intact. Castle and shrine stills are transparent 512px PNGs; their WebP loops use four registered source poses in a six-step forward/back sequence. Stationary architecture is held fixed while the defining ornament, banner, canopy, wing or magical element moves. `tools/prepare-painted-world.py` records source hashes, prompts, registration, moving regions and output hashes in the asset manifest. The existing four elemental castle skins and Luxembourg territory illustrations retain their approved artwork. Congress follows the active territory-art mapping. Audit and verification evidence is under `output/image-audit-2026-10-05/`.

Since 5 October 2026, construction uses 16 individually approved painted worksites in `assets/art/city-construction-v1/`. Exterior timber scaffolds follow each building's walls, towers, eaves or rock terraces, with recognisable entrances and roof colours. Each compressed WebP atlas contains four registered poses of that building and its workers. A clipped SVG image switches poses every 1.2 seconds; never crossfade transparent whole-building frames. The loaded worksite replaces the underlying sprite during construction to avoid doubled walls and roofs. Until it loads, the original building remains visible. Building bounds, timer labels and touch controls stay fixed.

| Building | Upgrade work |
| --- | --- |
| Castle | Pulley lifts a masonry block above the new stone course |
| Academy | Workers hoist a finial beside the stepped tower scaffold |
| Treasure house | Vault locking wheel is fitted and turned |
| Hospital | Painter and roofer work along the low wing scaffold |
| Alliance hall | Crest banner is raised between wooden supports |
| Trading post | New striped awning is unfolded over its frame |
| Storage | Timber plank slides into the new storage rack |
| Watch tower | Rope hoists a ladder up the scaffold |
| Stable | New stall gate swings on its hinge |
| Archery range | Target is aligned on a new wooden stand |
| Barracks | Mallet fixes a shield to the armoury stand |
| Farm | Terracotta tiles are lowered onto new roof rafters |
| Lumber camp | Saw cuts a beam resting on trestles |
| Gold mine | Cart delivers timber and stone along newly laid rails |
| Quarry | Chisel shapes a stone block with small chips |
| Wall | Crenellation block is lowered onto a new wall section |

The atlas is loaded only for an authoritative active build queue, retained across refreshes and removed when that queue settles. Countdown expiry alone does not finish the work. Light graphics also retains the four poses; reduced motion uses a fully still worksite. Dialogs, hidden tabs and the world view pause construction motion. Castle construction uses the approved standard castle worksite; the equipped skin returns after completion. Each atlas is below 200 KB, with a maximum frame dimension of 384 pixels. Source hashes and alignment are recorded in `manifest.json`; `tools/prepare-city-construction.cjs` packs approved source sheets without repainting them. Check `tests/city_construction_app.cjs` (all 16 tasks, mobile touch, pauses, motion settings and queue cleanup) and `tests/painted_city.cjs` (including missing and late atlas loads). Actual-app captures for this revision are under `output/city-scaffolds/app/`; browser checks do not establish physical-device coverage.

Since 5 October 2026, conquest landmarks use the eight transparent illustrations in `assets/art/territory-v3/`: six unfortified commune buildings, a sacred tree and standing-stone circle for Luxembourg shrines, and an open council forum for Congress. These replace the castle silhouettes at the user's request. The four classical elemental shrines retain their approved non-castle `map/painted-v2/` motifs; classical Congress also uses the new council forum. Map and territory dialogs share `assets/js/territory-art.js`; illustrated sizes still follow authoritative footprints. Sources, prompts, compression and visual checks are documented in `TERRITORY_ART.md`. The previous `territory-v1` and `territory-v2` sets remain available for comparison.

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

### Approved inventory icons (3 October 2026)

On 5 October 2026 the user extended the family-icon layout to the remaining items and requested a stronger owned-stock badge. Resource packs share one illustration per resource; action-point refills, VIP-point packs and resource boxes share one motif each. Boosts share a motif per effect, with live strength and duration labels. Random fragment packs share one motif per grade; specific relic fragments, dragon eggs, chest types, teleport modes and materials retain their distinct identities. All reuse approved `painted-v2/` artwork. `ConquerItemArt` also resolves historical code-only receipts and future variants with known family metadata. Inventory stock is a bold gold-edged badge at the top right, with package value below. Counts from 10,000 use compact notation on the tile while details, accessible labels and use actions retain exact quantities. Rarity and authoritative game values are unchanged. Earlier individual variant assets remain preserved.

On 5 October 2026 the user approved one fixed illustration per speedup type with a large live duration and separate stock badge. Universal, building, research, training and healing reuse `painted-v2/10103001.webp`, `10103011.webp`, `10103021.webp`, `10103031.webp` and `10103041.webp` respectively. All duration variants of a type share that motif across inventory, overview, queues, shop offers and rewards. Duration backgrounds are grey below one hour, blue from one hour to below one day, violet from one day to below seven days, and orange from seven days upward (currently 7 and 30 days). Time sits on a dark readable strip at the bottom; stock sits separately at the upper right. Other item and relic identities retain their approved illustrations and rarity colours. The earlier individual speedup files remain preserved.

On 4 October 2026 the user approved installation of all 190 individual item illustrations from `artifacts/item-icons-unique-preview/`. Their transparent 256px WebP files are shipped in `assets/art/items/painted-v2/` (about 3.1 MB in total). The shared item-art helper resolves exact catalog item codes before generic motifs, including quantity and duration variants. Inventory, quest rewards, reward receipts, shop offers and queue speedup choices use those exact illustrations. Existing unique relic portraits and authoritative amount/time labels and rarity frames remain in place. Unknown future items retain their existing fallback artwork. The preview folder preserves generation prompts, provenance and review evidence.

The user approved the twelve chunky, espresso-outlined inventory motifs. Transparent 256px WebP assets live in `assets/art/items/painted-v1/`; the approved transparent source and preparation record remain alongside them. `assets/js/item-art.js` maps compatible resource packs, generic/building/research speedups, action-point potions, city shields, gold chests and epic fragment packs. Amount/time labels and rarity frames remain authoritative. Training/healing, other chest grades and unique relic portraits retain their distinct existing artwork. Inventory, summaries, reward receipts and item shop offers share this mapping.

6 October 2026: Resource amounts in the HUD, costs, rewards, reports, market and inventory use the same approved resource-family illustrations. Food, lumber, stone, gold and gems reuse painted-v2/10101001.webp, 10101011.webp, 10101021.webp, 10101031.webp and 10101041.webp through ConquerItemArt.resourceUrl. Strength, quantity, shortage and rarity semantics remain unchanged.
