# Troop names and completion icons

Updated 9 October 2026 for Union of Kingdoms. The active T1–T5 troops use names that follow their approved portraits: blue infantry guards, bow-equipped fire archers and shadow cavalry.

| Tier | Infantry | Archers | Cavalry |
| --- | --- | --- | --- |
| T1 | Village Guard | Bow Recruit | Mounted Scout |
| T2 | Shield Guard | Scout Archer | Dusk Rider |
| T3 | Iron Sentinel | Ember Archer | Night Lancer |
| T4 | Royal Guardian | Flame Ranger | Shadow Knight |
| T5 | Crown Warden | Phoenix Ranger | Eclipse Rider |

The English definitions and German source names live in `data/troops.json`. The shared `troop.name.<code>` entries in `data/i18n/` contain English, German, French and Luxembourgish versions. English remains the default and fallback; Luxembourgish entries are retained for the existing catalog without adding a language to the selector. `ConquerLocale.text` and `Locale::text` resolve the authored names through the existing common dictionary.

All twelve T2–T5 research unlocks use the corresponding troop name in `data/research/battle.json` and `assets/js/research-tree.js`. Internal troop and research codes remain stable, as do costs, statistics, timers and prerequisites. Historical report text and retired T6–T10 definitions keep their saved identity.

## City completion markers

The four dedicated transparent illustrations in `assets/art/status-icons-v1/` replace borrowed menu and inventory pictures in `assets/js/city-painted.js`:

| Marker | Artwork | Runtime asset |
| --- | --- | --- |
| Construction completed | Finished house and wooden builder's mallet | `building-complete.webp` |
| Research completed | Open purple spellbook and discovery spark | `research-complete.webp` |
| Training completed | Blue shield and practice sword | `training-complete.webp` |
| Healing completed | Ivory healer's satchel with green plus and bandage | `healing-complete.webp` |

The built-in Imagegen tool generated each icon separately with a transparent background. The original PNGs are preserved in `masters/`; `manifest.json` records the complete prompts and source/runtime hashes. Runtime exports retain alpha at 128×128 pixels and are each under 10 KB. The renderer keeps the existing success check, accessible labels, touch targets and server-confirmed completion behaviour. Free-chest markers keep the approved chest artwork.

## Verification

`tests/painted_city.cjs`, `tests/city_readiness_app.cjs` and `tests/research_catalog.js` cover the existing city renderer, real `/city#city` completion actions and the research catalog. Completion markers were checked at 1280×800, 390×844, 320×568 and 844×390. Local screenshots are under `output/playwright/troop-names-completion-icons/readiness/`.

Name verification passed with the isolated preview account: English/German/French, all 15 troops, all 12 research unlocks, and 1280×800, 390×844, 320×568, 844×390 and 568×320 (225 displayed-name checks), with no browser errors or missing artwork. Server-side name translation also passed for all three supported languages; all 15 Luxembourgish catalog entries are present. Evidence is recorded under `output/playwright/troop-names-completion-icons/names/`. The existing `tests/training_layout.cjs` passed all five viewports, both modes and its locked, missing-resource, running and unconfirmed-order states after updating its T2-name expectation to Schildwache. Browser checks do not establish physical-device coverage.

The broad `tests/localization.php` check encounters 240 missing French talent keys already present at `HEAD`; these are outside this change. All 15 new troop-name entries are complete in all four catalogs.
