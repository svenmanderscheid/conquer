# Layered village

Built-in Imagegen derived these assets from the user-approved spacious village. The rejected ornate `fantasy-village-v1/*.png` building sprites are not used.

- `terrain.png`: empty terrain; walls, river, paths and fountain retained.
- Fifteen named PNGs: transparent individual buildings.
- `construction.png`: shared scaffold/foundation, replacing the finished building while the authoritative build queue contains its code.
- `runtime/*.webp`: resized delivery images, about 665 KB in total.
- `PROMPTS.md`: generation prompts; `sources.json`: generation provenance.

Rebuild delivery copies: `php tools/build-layered-village.php`. Original project PNGs are sufficient; the external source paths are only used if a project original is missing.

Integration: `assets/js/city-painted.js`. No client-side construction completion and no game-rule changes. Existing app countdown uses server-adjusted time. CSS dust pauses outside city mode, while dialogs are open, and when the document is hidden; reduced-motion/light settings disable it. No permanent animation loop is added. Walls remain baked into terrain and use the gate as the repair marker. Separate building tier artwork and independently animated wheels/workers are not implemented.

Tests: `tests/painted_city.cjs`, `tests/layered_village.cjs`. Test screenshots under `artifacts/layered-village/` use an isolated fixture, not actual player construction commands.
