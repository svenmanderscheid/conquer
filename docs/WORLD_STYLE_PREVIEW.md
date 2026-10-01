# World-style interaction prototype — v11

The approved v11 design is now integrated in the production world map. See
`WORLD_PAINTED_INTEGRATION.md` for scope, preserved gameplay and verification.

Version 11 increases only the hammer wielder (internal ID magdar, user calls Deathkar) from 190 to 219 world units, approximately 15%. Position, proportions, breathing and hammer animation remain unchanged.

Version 10 keeps green dragon size unchanged and adds optical scale compensation after alpha-height normalization: red +14%, gold +10%, accounting for their narrower silhouettes. Animations and all other objects remain unchanged.

Version 9: calmer 2.8-second shoulder-led dragon wing sweep replaces the membrane folding. Dragon source alpha bounds (threshold 32) normalize visible rest height to 170 world units, preserving aspect ratios and actual foot anchors. Magdar's chest has independent weighted breathing in the hammer rig. Castle size increases from 205 to 250, with waving flag regions and warm window flicker. Castle masonry is rendered directly, outside the moving flag masks. Animation scheduling now also runs when only castles are visible; pause and reduced motion remain effective. Five cached rigs use approximately 30 MiB pixel storage. Alliance demo spacing grows to 245 units. Full game remains unchanged.

Open /conquer/assets/world-preview/index.html?v=9 on local Apache. No login, game API calls or saved-game changes. Production world and city renderers are untouched.

## Ground and decoration

Two approved open meadow tiles (world-tile-0.png, world-tile-1.png) are selected by stable coordinate hash, always upright. Cached 768-square tiles use 128-unit smoothstep overlap; generated edges are not pixel matched. No water is present in this isolated preview; production integration must preserve server water and placement rules.

Trees now use two transparent painted sprites (world-tree-oak-v7.png, world-tree-pine-v7.png). Deterministic jittered cells produce individuals and small groups. Only nearby cells are evaluated. Full tree rectangles intersecting object bounds plus 18 units of clearance are suppressed. Decorative trees do not block placement. Allianz-Nachbarn toggles two adjacent demo castles; decorations reappear deterministically after removal. Real teleportation, server occupancy and gameplay rules are not implemented here.

Old baked woodland assets remain saved but are no longer loaded. Built-in Imagegen prompts and output filenames: assets/art/fantasy-village-v1/WORLD-TREE-SPRITES-V7-PROMPTS.md.

## Object and animation gallery

14 selectable sample objects: castle, gold mine, quarry, crystal mine, lumber camp, grain farm, orc, skeleton, golem, treasure goblin, green/red/gold dragon and Magdar. Castle and gold mine use the v5 painted assets. The four other resource sites now use world-quarry-v8.png, world-crystal-v8.png, world-lumber-v8.png and world-farm-v8.png, generated in the gold mine style with transparent grassy edges. Built-in Imagegen prompts: assets/art/fantasy-village-v1/WORLD-RESOURCES-V8-PROMPTS.md. Old assets remain intact. Monsters use bright-v2 PNGs; the hammer wielder is internally named Magdar (user referred to Deathkar).

Animations are local 2D image rigs, not full skeletal combat animation. monster-motion.js bends the visible main wing of each dragon and rotates/deforms Magdar's hammer/forearm region while keeping torso and feet stable. Four sets of 24 frames at 256-square resolution are cached once (about 24 MiB raw pixel storage), yielding between generation batches. Dragon cycles last 1.6 s, hammer cycles 2.5 s. Other monsters have stronger breathing (slower on golem). Resource sites retain glints and dust/motes. Reduced motion renders the unwarped source sprites. Pause freezes effects; hidden documents stop the timer. Visible animated objects trigger at most 30 scheduled redraws per second; terrain and trees are cached as a viewport background, rebuilt only when camera, viewport or occupancy changes. DPR capped at 2. No full-game mobile performance claim.

Touch drag, pinch, wheel, keyboard and zoom controls remain available. The horizontally scrollable object list focuses every object. Overview shows all sample types. Controls follow shared violet/beige tokens.

## Verification

tests/world_style_preview.cjs with PLAYWRIGHT_MODULE pointing to installed playwright-core and Edge: desktop 1280x800, phone 390x844, narrow 320x700 and landscape 844x390. Checks 14 selections, animation advancement, pause, reduced motion, occupancy and restoration, drag, pinch, overflow, browser errors and no non-GET requests. Screenshots: artifacts/world-style-preview/, including crystal/golem closeups. Browser-emulated mobile checks, not physical-device tests.

tests/world_motion_preview.cjs verifies all four rigs produce distinct poses and keep multiple foot anchors fixed. motion-poses.png shows four phases per creature for visual checks of wing/arm movement and clipping.
