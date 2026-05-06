# Kenney Tiny Town — Placeholder Sprite Set for Conquer

This is a **temporary placeholder sprite set** used during early Conquer development. Once Conquer-native pixel art is produced, this set will be replaced by the `pixel/` set.

## What's here

- **`raw/`** — All 132 original Kenney tiles, named `tile_0000.png` through `tile_0131.png`. Don't rename or edit these — they're the unmodified source files.
- **`manifest.json`** — Set metadata (read by the renderer)
- **`tile-mapping.json`** — Maps Conquer game elements (e.g. `plains_01`) to physical Kenney tile files. The renderer uses this to know which file to load for which logical sprite.
- **`LICENSE.md`** — CC0 license + attribution notes
- **Subfolders** (terrain, trees, buildings, etc.) — Currently empty. If we want renamed copies of specific tiles for clarity, they go here.

## How the renderer uses this set

When the game asks for a sprite (e.g. "show me plains_01"):

1. Renderer reads `assets/sprites/active.json` → finds active set is `kenney-tiny-town`
2. Renderer reads `kenney-tiny-town/manifest.json` → gets dimensions and scaling rules
3. Renderer reads `kenney-tiny-town/tile-mapping.json` → finds `plains_01 = raw/tile_0000.png`
4. Renderer serves `assets/sprites/kenney-tiny-town/raw/tile_0000.png`
5. Browser scales it 2× with nearest-neighbor (16px → 32px effective)

When we later switch to native Conquer pixel art:
1. We change `active.json` from `kenney-tiny-town` to `pixel`
2. The `pixel/` set has its own `manifest.json` and the same logical sprite names (plains_01, plains_02, etc.)
3. Game code doesn't change at all
4. Players see the new art on next page load

This is the "re-skinnable sprite-set architecture" in action.

## Why 16×16, not 32×32?

Kenney Tiny Town is natively 16×16 px. Our Conquer spec uses 32×32. The renderer handles this automatically via `manifest.json`'s `render_scale_factor: 2` — Kenney tiles are drawn at 2× their native size so they match Conquer's logical 32×32 grid.

When we produce our own `pixel/` set later, it will be 32×32 native and `render_scale_factor: 1`. No code changes needed.

## Adding more tiles to the mapping

If you discover that a particular Kenney tile fits a Conquer element well:

1. Open `tile-mapping.json`
2. Add an entry under the appropriate category, e.g.:
   ```json
   "monsters": {
       "orc_t1": "raw/tile_0XXX.png"
   }
   ```
3. Save the file
4. The renderer picks it up on next request

You can preview tiles by opening them directly in the browser:
`https://conquer.svenmanderscheid.lu/assets/sprites/kenney-tiny-town/raw/tile_0042.png`
(Or locally: `http://localhost/conquer/assets/sprites/kenney-tiny-town/raw/tile_0042.png`)

## License

CC0 1.0 Universal — see `LICENSE.md`. You can use, modify, redistribute these tiles freely.

The Conquer credits page should mention Kenney as a courtesy:
> "Placeholder sprite art by Kenney Vleugels (kenney.nl) — CC0"

## Replacing this set later

When Conquer-native sprites are ready in `pixel/`:

1. Verify all tile names in `tile-mapping.json` exist as PNG files in `pixel/`:
   - e.g. `plains_01.png` exists in `pixel/terrain/`
2. Update `assets/sprites/active.json`:
   ```json
   {
       "default_set": "pixel",
       "available_sets": ["pixel", "kenney-tiny-town"],
       "user_selectable": true
   }
   ```
3. Optionally keep `kenney-tiny-town` as a player-selectable alternative theme
4. Or remove the folder entirely if no longer needed
