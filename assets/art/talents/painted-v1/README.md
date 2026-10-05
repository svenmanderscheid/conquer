# Union of Kingdoms talent icons

Eighteen approved illustrated motifs, extracted with the built-in image-generation tool on 5 October 2026. Each runtime image is a transparent 256 × 256 WebP. `manifest.json` records dimensions, crop rectangles, file sizes and SHA-256 hashes.

The six Hunter constellations use the infantry, archer, cavalry, monster, combat and gathering emblems. Their 78 node images and authoritative effects come from `data/lord_talents.json` version 2. Small waypoints have one rank; main nodes have five. See `docs/LORD_TALENTS.md` for progression and connected-path rules.

Original approved sheet: `artifacts/talent-icons-v1/design-sheet-v2.png`. Transparent source: `artifacts/talent-icons-v1/runtime-atlas-v1.png`. The local source folder also contains the exact revision prompt and cell registration. `tools/prepare-talent-icons.cjs` crops and compresses the transparent source without repainting it; use the project's Node runtime with `sharp` available.

## Exact extraction prompt

Use case: background-extraction.
Asset type: transparent production atlas for Union of Kingdoms talent icons.
Input image 1 is the EDIT TARGET, the user-approved eighteen-icon concept sheet. Extract the existing drawings for use in the game. Remove the beige paper background completely and remove ALL lettering, including the header, subheading and eighteen labels. Preserve all eighteen original drawings, their colours, shapes, outlines and relative order. Do not redesign or add objects.
Composition: one transparent portrait atlas, with exactly 3 columns and 6 rows, one complete icon per cell, each centered with generous clear separation. Equal cell size. Place icons at consistent optical scale, fully inside their own cells, no clipping. Row order exactly: blue infantry shield and gold sword / bow and green arrow / pony head; green monster head / crossed red-grip swords / sickle and wheat; purple AP flask / rolled bandages with leaf / chest and coins; boot with blue motion lines / canvas backpack / red heart; blue defence shield / three helmets and purple pennant / training dummy and practice sword; construction hammer and beam / open book and purple ribbon / resource crate with wheat and logs.
Invariants: all eighteen complete distinct motifs from the approved image, thick warm brown outlines, soft chunky game shapes and the same simple illustrated shading. No beige residue, no solid background anywhere around or between drawings. Keep intended opaque ivory/cream inside shields, cloth and pages. Internal spaces between arrows, bow string, pennant, handles and props must also be transparent. No text, no labels, no title, no frames, no halos, no ground shadows, no extra decorations. Actual alpha transparency, not a drawn checkerboard.
