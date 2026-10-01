# Combined direction v6

Built-in Imagegen; no CLI. Concept preview only, not integrated into the game. Earlier versions preserved.

Output: `village-overview-v6-combined.png`
Inputs: v5 reference style (edit target) and v4 matte military square (layout reference).

User selected the simpler reference drawing style combined with the inner terrace wall and spacious plots. Visual review: inner wall and central stairs restored, fifteen buildings retained with simplified rendering. Archery remains close to outer wall; exact plot boundaries and sprite positioning still require implementation work.

## Full prompt

Use case: precise-object-edit. Image 1 is the EDIT TARGET and authoritative simple cartoon style. Image 2 is only a reference for the inner terrace wall and generous building plots, NOT its rendering style. Produce a single 1536x1024 full-village overview combining image 1's simple matte hand-drawn shapes, brown outlines, broad flat colors and soft two-tone shadows with image 2's inner terrace division. Restore a low warm gray stone inner wall across the middle separating the upper civic district from the lower military/production district, with a wide central open stair/ramp aligned to entrance bridge and fountain. Wall runs BELOW hospital/alliance hall and market/warehouse, ABOVE stable/barracks and farm/lumbermill, without hiding buildings or cutting their plots. Fountain remains above the ramp on upper terrace. Keep all fifteen existing buildings, their identities, colors, approximate positions, and outer island/water/wall footprint from image 1. Castle stays centered at top. Maintain generous individual lawn plots and clear connecting paths. Three military buildings bottom-left (blue stable, green archery with targets, red barracks) retain a shared open training square and should have clear breathing space from each other and outer wall. No extra buildings. Keep simplified trees, sparse decoration, muted sage grass, sandy paths. No shiny rendering, metallic detailing, tiny floral clutter, neon, UI, text or people. Preserve new simple illustration style throughout, do not revert to elaborate image 2 style.

