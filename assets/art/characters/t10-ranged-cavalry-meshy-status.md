# T10 ranged and cavalry Meshy status

2026-09-16: Uploaded the seven approved individual PNG references documented in t10-ranged-cavalry-meshy-v1.md to Meshy Batch Images to 3D.

Settings verified: Meshy 7.1 Flagship, high detail, Ultra 2K, pose None (preserve supplied poses), image enhancement OFF, Private already selected.

One batch submitted: 175 credits; balance changed from 1020 to 845. Seven new untextured model cards appeared (lance, shield, quiver, bow, horse, two humanoids). First humanoid inspected in viewer: geometry exists, separated hands/fingers visible in front view. This is not full geometry or animation approval.

Pending: inspect all models and variants from multiple angles, choose geometry, texture, rig humanoids and horse separately, export, optimize and assemble equipment. No new GLBs downloaded or integrated in Conquer yet. Do not regenerate this batch just because local GLBs are absent.

Workspace: https://www.meshy.ai/de/workspace?model-tab=batch-images-to-3d

## Texturing pass

## Local archer motion prototype — 2026-09-16

User subsequently deferred the shot. Current archer review now shows ONLY a relaxed idle: lowered arms, downward hands, subtle four-second breathing and head motion, feet fixed. Shot selectors removed; source GLB and its clips preserved. Front/side visual check and JS syntax check passed. Still unequipped and not integrated into the game.

Downloaded `Meshy_AI_Golden_Dragon_Paladin_All_Animations.glb` is preserved in Downloads and copied to `archer-t10-rigged-source-v1.glb`. Contains Running, Walking, Archery_Shot, Archery_Shot_3 and restpose. This supersedes the older no-download notes below.

`artifacts/archer-review/` provides a standalone procedural two-bone arm correction with original-animation comparison, rotation, pause and phase scrubbing. Lower draw targets keep the hand below the oversized head. Front and side of the drawn pose inspected; JS syntax check passed. This is a bare-body motion prototype, not a finished equipped shot or baked animation. Bow/string/arrow attachment, hand gripping, equipment-aware motion QA, mobile optimization and game integration remain pending. Approved knight untouched.

## Rigging pass

Both remeshed humanoids completed. Selected the new remeshed copies (top two cards), opened actual humanoid rig wizard. Kept front alignment and default 1.7m normalization. Adjusted crotch marker down from waist to leg split in each model; then submitted. Both rigged outputs are now visible with animation badges. Balance still 775.

Archer rig inspected in animated viewer: 10,448 triangles / 5,201 vertices. Added preset "Bogenschießen Schuss"; selected card shows checkmark / Entfernen and model animation visibly plays. No bow equipped yet. Full deformation review, idle/walk selection, downloads, horse rig and assembly remain pending. The bare-body shot is a test, not production approval.

All seven texture jobs completed and colored cards were observed. Used original image inputs, Meshy 7 texture model, 2K textures, PBR maps OFF. Cost 70 credits total, balance 775. Archer front and three-quarter views visually inspected; complete animation/deformation QA is still pending.

Two 10K triangle remesh preparation jobs submitted via the Rig workflow (UI cost 0). At least one completed card observed; second was last seen at 90 percent. Rig button still opened polygon preparation rather than confirmed joint setup; do not claim rigging complete or blindly resubmit. No animation clips or downloads completed in this pass. Horse rigging still pending.

## Production integration — 2026-09-17

Horse quadruped rigging is complete and visually verified in Meshy. The full skinned horse is preserved as `cavalry-t10-horse-rigged-v1.glb`; rider and archer source files remain preserved separately. Mobile production copies were generated at 1024px texture resolution:

- `archer-t10-rigged-mobile.glb` — 2.25 MB
- `cavalry-t10-rigged-mobile.glb` — 2.02 MB
- `cavalry-t10-horse-rigged-mobile.glb` — 1.25 MB

`assets/city3d/meshy-t10-units.js` now provides the production T10 archer idle and a composite cavalry idle. The rider is scaled and seated on the horse, with bent knees at the stirrups, lowered hands near the reins, subtle breathing, head motion and horse tail movement. Both units are connected to the training portrait for troop types 2 and 3 at tier 10.

Desktop close-up and 390×844 mobile-layout checks passed in `/city#army`. No model-specific browser errors were reported. The archer shot remains deliberately deferred; only the approved relaxed idle is used in production.

The T10 training cards now use the approved Dragonsteel concepts (`assets/art/training/2-10.webp` and `3-10.webp`). Separate 512px interface and 768px report renders are available as `archer-t10-ui-v1.png`, `archer-t10-report-v1.png`, `cavalry-t10-ui-v1.png` and `cavalry-t10-report-v1.png`.
