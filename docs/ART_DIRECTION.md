# Cartoon fantasy assets

For all menus, HUD elements, building dialogs, backoffice screens and world-map colours, also follow **`docs/UI_STYLE_GUIDE.md`**. The shared UI tokens and appearance layer are in `assets/css/village-theme.css`; the active world terrain palette is in `assets/js/world-landscape.js`. These extend the village reference below.

The user-approved interface reference is **`assets/art/ui-violet-beige-reference.png`** (variant A, 13 September 2026): dark-violet headers, warm beige surfaces, restrained gold accents, Almendra text and Lora numerals. This applies to the main app, standalone 3D controls and labels, map overlays and backoffice. Use the shared UI tokens for those surfaces; the painted buildings, role colours and natural landscape continue to use the world palette below.

Generated with the built-in Imagegen tool on 2026-09-08. These are new original illustrations; the supplied screenshot informed the requested stylistic direction and was not used as a source of extracted assets. No CLI image-generation fallback was used.

Files are project-local under `assets/art/`. The production village is `village2.png`; `village.png` is the unused first draft. `knight.png`, `archer.png`, `rider.png`, `orc.png`, `skeleton.png` and `golem.png` illustrate the guide, troops and encounters. `world.png` is the map backdrop.

## Active 3D style rules

`assets/art/village2.png` is the binding visual reference for the playable 3D city. The prompts below document the origin of the 2D artwork; their restrictions such as “no textures” describe that illustration and do not forbid restrained, hand-painted textures in the 3D scene.

The 3D city should look like a playable version of the illustration: chunky toy-like buildings, rounded and slightly exaggerated silhouettes, a muted sage landscape, warm paths, strong roof colors, broad toon shading and selective dark brown outlines. Readability from the normal isometric camera matters more than small realistic detail. New objects should use the shared colors and materials from `assets/city3d/storybook-style.js` wherever possible.

The shared `paintedMap()` surface in that module is part of the required material language. It adds very soft broad color variation and a faint relief to stone, plaster, wood, cloth and roofs. It must remain subtle: the reference uses clean illustrated areas rather than dirt, photographic grain or weathered realism. New building materials should go through `storybookMaterials` or `shadeStorybookRoot()` so they inherit this surface automatically. Completely clean single-color materials should be reserved for light effects, water highlights and very small symbols.

### What the supplied screenshots establish

The active reference is the simpler `village2.png`, not the more detailed first draft `village.png`. Its buildings use warm ivory walls, saturated blue, orange, purple and red roofs, dark espresso outlines and only two broad light values. Roof seams and a few oversized functional props provide detail. Surfaces remain calm and clean.

Paths are pale warm tan ribbons with soft edges and only occasional flat oval marks. They do not use dense gravel, regular paving rows or high-frequency noise. Grass is a muted yellow-green. Dark teal pines frame the outside, while rounded shrubs and a few rocks, flowers and mushrooms fill gaps without covering buildings.

Character proportions follow `knight.png`, `archer.png`, `rider.png`, `orc.png`, `skeleton.png` and `golem.png`: the head occupies roughly 40–50 percent of total height, limbs are short and rounded, facial features are simple and equipment is oversized. These proportions take priority over realistic anatomy.

### Surfaces and paths

Natural surfaces may use small procedural or project-local image textures when flat color cannot communicate the material. Textures must be seamless, softly hand-painted and limited to a few related colors. They should add broad mottling, grain or embedded stones without photographic noise, sharp pixel art, visible square repetition or realistic gloss.

City paths use the warm dirt-and-gravel texture created by `paintedRoadTexture()` in `assets/city3d/full-city.js`. New paths and extensions must use this material and scale their UVs by physical path length so the texture retains the same density. Use only sparse, irregular 3D border stones. Do not restore evenly spaced rectangular slabs. Path details remain flat and the center remains clear for pedestrians.

### Buildings and props

Start with a simple, recognizable silhouette and exaggerate the part that communicates the function: large crossed swords for a barracks, a broad wheel for a mill or an open rock arch for a mine. Prefer curved roofs, broad arches, thick beams and a few oversized props. Apply dark outlines to the main shapes, not to every tiny mesh. Repeated roof tiles, stones, plants and small props should use instancing.

The village identity pass of 17 September 2026 uses large functional landmarks:
crossed swords and shield practice for infantry, bow and round targets for archers,
horse head and ponies for cavalry, an open book for research, twin grain bins for
storage, a coffer above the treasury door, paired heraldry for the alliance hall,
wheat on the farm roof, a saw and axe at the lumber mill, cut blocks at the quarry,
and exposed ore at the gold mine. Keep these cues readable from the normal camera;
do not replace them with a collection of tiny props. The hospital cross and the
market's striped stalls remain their existing landmarks.

The playable island and its sloping banks share one irregular shoreline in
`village-landscape.js`; the old ten-sided study disc is hidden in the game scene.
Low shore rocks, reeds and lily pads stay outside the walls and clear of the south
bridge. Repeated details and moving water strokes use instances. Preserve the flat
playable ground, building coordinates and authored walking routes.

### Trees and plants

Trees use crooked trunks, visible root flares and off-centre crowns. Mix rounded cloud-like deciduous trees with squat layered pines. Change crown offsets, scale, lean and tone deterministically so rows never look cloned. Fruits and flowers are sparse accents. Avoid perfect cones, straight poles and three identical centered foliage layers.

### People and animation

Characters use clear chibi proportions: the head is large, the torso and legs are short, hands and boots are broad, and one clothing or equipment shape identifies the role. Faces remain simple and readable. Farmers, craftspeople, pedestrians and guards should differ through hats, tunics, tools, shields or color rather than realistic anatomy.

Walking uses short steps, visible arm swing, a small vertical bounce and slight body lean. Pedestrians follow the authored road polylines and may use only small lane offsets. Workers remain beside their workplace. Wall guards stand and walk on the center of the visible wall walkway and turn before the gate. Animated characters must never cross buildings, props, walls or closed ground.

### Premium castle and march skins

Every premium castle skin needs its own visible, continuously readable animation. Animate the part that defines the castle fantasy—such as wings, gears, water, roots, lightning or celestial bodies—rather than adding a generic glow. Keep motion calm enough that building labels and selection remain clear, and provide a stable reduced-motion state.

The matching march skin must sell the same fantasy through a unique silhouette and movement. A recolor of the standard soldiers is not a premium march skin. Prefer a creature, construct, vehicle or unmistakable formation that remains identifiable at the normal world-map size. Its arrival animation must complete the same theme: the moving subject, impact shape, colors and particles belong to that skin. Snapshot the selected skin when the march starts so changing equipment cannot alter an active march or its arrival effect.

Review castle, march loop and arrival together before release. Verify their shared palette and motifs, the complete animation cycle, reduced motion, mobile readability and effect cleanup. Arrival effects may not redraw terrain, shift the authoritative march position or obscure map controls.

### Review checklist for every visual addition

- Compare the new content with `village2.png` and the surrounding 3D objects at normal zoom.
- Inspect the silhouette and material once in a close view and once in the full-city view.
- Confirm paths and animated routes remain unobstructed.
- Confirm labels, selection and mobile controls remain readable and clickable.
- Check JavaScript syntax, current cache-version imports and browser warnings or errors.
- Keep repeated details instanced and compare the scene metrics after substantial additions.

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
