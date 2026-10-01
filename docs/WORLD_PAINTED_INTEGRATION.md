# Painted world integration — 23 September 2026

The approved v11 gallery is connected to the production `ConquerWorld` renderer
(`assets/js/world-map.js`), including `/city#world`. It is no longer preview-only.

- Upright grass variants use world-coordinate hashing, overlap and feathered edges.
- Two separate painted tree sprites replace low-poly scenery. Occupied locations
  and water suppress trees; removal restores the deterministic decorations.
- Default castles, five resources and eight approved monsters use the selected art.
  Premium castles, regional bosses, shrines, Congress, charms and march skins retain
  their identities. No server data, spawning, teleport rules or footprints change.
- Shared `world-sprite-motion.js` preserves wing, hammer/chest and flag movements.
  Dragon optical compensation and the 219-unit hammer wielder remain.
- Frames are shared per sprite type and built lazily for animated visible content.
  Marker canvases are 192 square; terrain stays separately cached. The existing map
  loop controls animation, visibility, reduced-motion and light graphics settings.
- Original image geometry remains available for resource-card placement; canvas
  visuals do not intercept input. Asset URLs support a deployment subfolder.
- Water, bridges, regional navigation, minimap, territory overlays and selection
  remain intact. The painted land replaces only the ground presentation.

## Verification

### Drag responsiveness — 1 October 2026

Camera pointer events update the camera immediately but share one scheduled
display-frame update. Incoming map snapshots keep only the latest response until
the gesture ends. Painted sprite animations and decorative CSS motion pause while
panning or pinching, and artwork-ready callbacks defer marker work until idle.
Release, cancellation, lost pointer capture and scene changes clear gesture state;
switching worlds applies the new state immediately.

Sprite animation warmup uses one shared queue with roughly 4 ms work slices and
pauses during camera gestures, reduced motion and hidden scenes. All 24 poses and
the approved artwork remain; background preparation can take longer in exchange
for shorter uninterrupted browser work.

`tests/world_drag_rendering.cjs` exercises production modules at 1280×800,
390×844 and 844×390. The same 40-event input burst caused six full terrain redraws
including release before this change and one afterward. This is a redraw-work
comparison, not a live-server or device FPS measurement. `tests/world_sprite_build.cjs`
checks bounded work, the shared queue, pause/resume and error recovery.

`tests/world_painted.cjs`: production renderer in a read-only HTTP fixture at
1280×900, 390×844, 320×568 and 844×390. Assets, resource actions, direct monster
callback, optical sizes, animation, reduced motion, hidden scene, light mode,
premium skins, tree suppression/restoration and subfolder URLs pass without browser
errors. Screenshots: `artifacts/world-painted/`.

`tests/world_biomes.cjs`: region navigation and Congress actions pass at five sizes.
`tests/world_motion_preview.cjs`: shared motion rigs and fixed feet pass.
`tests/world_terrain.cjs`: 262144 client/server water samples pass.

The logged-in local `/city#world` was additionally inspected with its real HUD on
desktop, portrait mobile and landscape. The quarry menu opens with its new portrait
and actions; no gathering, attacks, teleportation or account changes were submitted.

Unrelated QA blockers: the disposable full-app fixture fails on the existing
duplicate `report_type` column when replaying migration 0107. `world_footprint.cjs`
fails its old minimap assertion (expected 1.882 px versus the existing minimum of
2 px). Neither migrations nor minimap geometry changed for this visual integration.
