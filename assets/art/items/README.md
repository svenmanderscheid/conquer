# Inventory artwork

The inventory uses server-owned item definitions. Consumable and relic frames follow their declared rarity: grey, blue, violet, orange and red for mythic items. Counts, durations, levels and fragment progress remain live UI overlays.

## Original code-native SVG icons

`speedup.svg`, `hammer.svg`, `research.svg`, `helmet.svg`, `healing.svg`, `production.svg`, `gathering.svg`, `shield.svg`, `gems.svg`, `energy.svg`, `prestige.svg`, `book.svg`, `pouch.svg`, `strap.svg`, `compass.svg`, and the three `chest-*.svg` icons were drawn as SVG code for this interface. Resource types, speedup specialties, and chest materials have different silhouettes or details.

## Supplied reference artwork

The following PNG files were copied unchanged from the user's LOK.zip, under `LOK/Treasures/`. CSS presents the artwork inside the actual game relic grade frame. Source rights remain with their respective owners.

| Local file | Supplied source |
|---|---|
| manure.png | manure.png |
| woodcutter.png | axe.png |
| stone-amulet.png | chisel.png |
| feather-cap.png | acher hat.png |
| iron-shield.png | kite shield.png |
| hunters-bow.png | long bow.png |
| cavalry-spurs.png | saddle.png |
| builders-hammer.png | steel hammer.png |
| drillmasters-horn.png | war horn.png |
| dragon-shield.png | celestial shield.png |
| phoenix-bow.png | Dragon Tooth Bow.png |
| shadow-blade.png | cursed sword.png |
| archmage-staff.png | corrupted staff.png |
| warlord-banner.png | war flag.png |
| harvest-idol.png | golden grain.png |
| titan-gauntlet.png | hand of vampire.png |
| oracles-eye.png | Mirror of Truth.png |
| blood-moon-totem.png | dark crystal.png |
| serpent-scale.png | dragon scale mail.png |

Inventory resource packs now use the original SVG illustrations under `backpack/`. The supplied `../ui-resources/` artwork remains in use in the resource HUD.

The item-code-to-name/art mapping is in `assets/js/mvp-panels.js`. Quantity, duration, fragment counts, level, usable status, effects, and equipment slots come directly from the authenticated kingdom state.


## Complete reference collection (2026-09-11)

All 77 supplied treasure PNGs are copied unchanged under `treasures/`. Canonical art/name metadata is now in `data/treasures.json` and returned by TreasureService; the older JS map is a compatibility fallback. See `docs/TREASURE_CATALOG.md` and `tests/fixtures/treasure_reference_manifest.json` for the complete source mapping and byte hashes.

32 consumable SVG icons were redrawn or added in the established code-native icon system, using the supplied Inventory screenshots as visual references. These include golden speedup arrows, production arrows, troop helmets, shields, chests, eggs, fragments and teleport castles. No generated bitmap or external asset download was used for these SVGs. They were checked at 136, 48 and 24 pixels. The four legacy book/compass/pouch/strap icons remain unchanged.

## Backpack reference update (2026-09-13)

`backpack/` contains 20 original code-native SVGs: bundle, crate and cart variants for food, wood, stone, gold and gems, plus five blue speedup icons. Rebuild them with `node tools/build-backpack-icons.cjs`, or rebuild only the speedups with `node tools/build-speedup-icons.cjs`. Their silhouettes and layout were informed by the supplied Kingshot screenshots; no pixels were extracted from those screenshots. The red gem retains Conquer's currency identity. Existing bonus, chest and relic artwork is reused. Quantities and durations remain live HTML overlays. See `tests/inventory_app.cjs` for real-app checks at desktop, phone and landscape sizes.

The speedup set uses rounded arrow tips, broad blue highlights and warm outlines. Building, training, research and healing each have a larger integrated hammer, helmet, book or green cross emblem. Inventory tiles, item details and the overview use the same complete SVG, keeping the emblem readable at small sizes. The resource icons and the legacy gold arrow used elsewhere remain unchanged.

## Supplied inventory close-ups (2026-09-17)

Most PNGs under `reference/` are isolated from the three inventory close-ups supplied directly by the user. They replace the temporary code-drawn backpack artwork for matching catalog entries: resources, action points, VIP points, dragon eggs, fragment packs and teleporters. Text, quantities and screenshot tile frames are not part of the assets; those remain live UI elements. `tools/import-reference-item-icons.py` records the crop and cleanup process. Five symbols without a reliable catalog meaning remain unassigned in `artifacts/item-icons/nicht-zugeordnete-icons.png`.

The five `speedup-*-v2.png` files were rebuilt at high resolution with the built-in image generator after the screenshot extraction proved too small and incomplete in the live inventory. The supplied speedup screenshot and the generated universal double-arrow were used as visual references. Each output has a real transparent background and no baked-in duration, quantity, frame or glow. The shared golden double-arrow identifies the item family; hammer, purple research flask, golden training helmet and green healing crosses keep the four dedicated roles readable at phone size.
