# Painted village motion

The approved sprites remain the only artwork source. `city-painted.js` mounts one
SVG per finished building using the same cached WebP. A small masked part moves;
the building footprint and selection target stay still. Coordinates refer to the
runtime sprite, with the same bottom-centred contain fit as the original image.

- Castle, academy, watchtower, barracks: flag movement.
- Alliance hall and trading post: subtle cloth movement.
- Stable: pony breathing; archery range: target movement.
- Farm: wheat movement; lumber camp: wheel rotation and water highlights.
- Gold mine: short cart movement; quarry: suspended load sway.
- Hospital, storage and farm: chimney smoke.
- Academy and treasury: small magical/gold highlights; other working areas get
  restrained lamp light, water highlights or dust.

River highlights use a single 384 × 256 sampling pass over the same-origin terrain
image. Every candidate and its movement margin must contain blue water; the inner
village is excluded. No permanent canvas loop, new images or network calls are used.
Decorations ignore pointer input and are hidden on empty plots and construction.

CSS pauses motion outside city mode, behind app panels/dialogs and on document
visibility changes. Light graphics, the game's reduced-motion preference and the
OS reduced-motion preference remove ambient motion. The static sprites remain.

Tests: `tests/layered_village.cjs`, `tests/painted_city.cjs`. These cover four
viewport sizes, loaded assets, real transform changes, update idempotence, pausing,
light graphics, reduced motion, construction and selection/panning. Mobile viewport
testing is not a physical-device performance benchmark.
