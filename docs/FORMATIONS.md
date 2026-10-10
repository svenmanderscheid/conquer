# Saved army formations

Defense → Formations shows all six slots, names, troop counts, and the infantry / archer / cavalry mix. Empty slots create a new template; editing a filled slot loads its current configuration. Existing formations retain their fixed amounts.

The compact slot overview is separate from the editor. Selecting a tile opens its settings; its selected border and pressed state identify the destination slot. The editor groups slot, name and desired size first, then troop distribution, then the current army preview and save actions. Desktop percentages sit in three aligned cards; narrow screens use three full-width rows. Available and selected counts have distinct positions. Tier details and allocation guidance expand inside the preview. Deleting a saved formation is available beside the save action in the editor footer. Labels have explicit local spacing instead of inheriting the general form margins. Selecting a tile scrolls only the content region and resets incidental outer-dialog scrolling, preserving the window header and mobile Back button.

The editor offers **Percentages** and **Troop amounts**. Percentages are whole numbers from 0 to 100 and must add up to 100%. Choose a desired troop count, or use the current march capacity. For example, 70 / 30 / 0 with 10,000 desired troops selects 7,000 infantry, 3,000 archers, and no cavalry. Current stocks, the actual selected count, shortages, and a tier breakdown remain visible. Available higher tiers are used first.

Saving percentages retains the requested mix and troop count, rather than only the current preview amounts. When loading a template for a march or reinforcement, availability and the current capacity are applied again. A shortage reduces the whole army instead of substituting another troop type. A 0% type stays excluded. Whole-troop rounding differs from each mathematical quota by less than one troop. If a required type is absent, the template can still be saved for future use, but the preview army is empty.

Saving never deploys, reserves, or creates troops. Switching to fixed amounts preserves the current selection; saving fixed amounts clears percentage metadata. Dispatch retains its authenticated server checks, including stock and capacity checks.

## Storage and rollout

`0138_formation_percentages.sql` adds nullable `troop_formations.composition_json`. Fixed troop snapshots remain in `troops_json`; old formations do not need conversion. The new field stores `{"percentages":{"1":70,"2":30,"3":0},"total":10000}`. Server validation and preview allocation live in `FormationComposition`; the shared browser allocator is `assets/js/formation-composition.js`. The legacy amount-save endpoint clears percentage metadata when replacing a slot.

Install the additive migration before serving the updated PHP files. `php tools/migrate-formation-percentages.php` reports its state; `--apply` installs it under a database lock. The local development database received this migration on 9 October 2026. No deployment is implied.

## Verification

- `tests/formation_composition.cjs`: 5,159 allocation and invariant cases, including every integer percentage split, rounding, highest tiers, shortages, 0%, and capacity limits.
- `tests/formation_percentages_app.cjs`: actual `/city#city` app and building actions, five formation sizes (1280×800, 390×844, 320×568, 844×390, 568×320), actual percentage save/reload, stock preservation, reinforcement selection, fixed amounts, and English/German/French. Uses only the disposable loopback preview with `--appearance --march-roster`. Evidence: `output/playwright/formation-percentages/result.json`.
- `tests/defense_panel.cjs`: 20 existing defense layouts and action contracts.
- New checks in `tests/defense_lifecycle.php` passed persistence, validation, no reservation, future empty templates, and switching modes in an isolated schema. The full older suite still fails later at its unrelated `defense_0` talent-plan expectation.
- New checks in `tests/march_formations.cjs` passed 70/30/0 loading, highest tiers, remaining rally capacity, and missing types. Its older 320×568 layout assertion still expects two fully visible troop rows where the current march list shows one.

These are browser checks, not a physical mobile-device or store-build verification.
