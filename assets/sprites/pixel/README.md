# Pixel Sprite Set

The default Conquer sprite set — pixelart with the Conquer 32-color palette.

See `docs/Conquer_Asset_Specifications.pdf` for the complete style guide and AI generation prompts.

## Structure

```
pixel/
├── manifest.json           # Set metadata (read by renderer)
├── terrain/                # 32×32 ground tiles
│   └── transitions/        # Edge tiles between terrain types
├── cities/                 # 64×64 player cities (4 tiers)
│   └── skins/             # Phase 4 cosmetic skins
├── conquest/               # 128×128 shrines + congress
├── field_objects/          # 32×32 resource mines, GEM nodes
├── monsters/
│   ├── map/               # 32×32 / 48×48 / 64×64 map sprites
│   ├── battle/            # 64×64 / 96×96 / 128×128 battle sprites
│   └── animations/        # Sprite sheets for idle/attack/death
├── troops/                 # 48×48 battle sprites (15 total)
├── treasures/              # 48×48 icons by grade
│   ├── normal/
│   ├── rare/
│   ├── epic/
│   ├── legendary/
│   ├── mythic/
│   └── frames/            # Grade-frame overlays
├── charms/                 # 32×32 icons (24 total)
│   └── frames/
├── ui/                     # UI icons by category
│   ├── resources/         # Food, Lumber, Stone, Gold, GEMS
│   ├── speedups/
│   ├── actions/
│   ├── buffs/
│   ├── notifications/
│   ├── vip/
│   └── avatars/
├── buildings/              # 96×96 city view (Phase 2)
└── atlases/                # Generated sprite sheets (don't edit manually)
```

## Adding a sprite — checklist

1. Use the **Conquer 32-color palette** (`data/palette/conquer-32.gpl`)
2. **No anti-aliasing** — crisp pixel edges
3. Match the **size** for the category (see `manifest.json` dimensions)
4. **Naming**: `category_name_variant.png` lowercase with underscores
5. **PNG-32** with alpha channel, transparent background
6. After adding files: update `manifest.json` `completeness.completed_categories` if appropriate

## AI generation workflow

1. Generate at high resolution (256×256 or 512×512) using prompts from the asset PDF
2. Downscale to target size with **nearest-neighbor** in Aseprite
3. Reduce palette to Conquer 32 (Aseprite: Sprite → Color Mode → Indexed)
4. Hand-clean for 2-5 min — fix bad pixels, ensure tileability
5. Save as PNG-32

## Tools

- **Aseprite** ($20) — recommended for pixelart + animations
- **Piskel** (free, web) — quick edits without install
- **LibreSprite** (free) — Aseprite fork
- **GIMP** with the `.gpl` palette imported — also works

See `docs/Conquer_Asset_Specifications.pdf` for full guidance.
