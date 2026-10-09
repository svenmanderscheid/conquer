# Union of Kingdoms: map landmark actions

Selecting a Commune, canton Shrine, Royal Congress, or a legacy Shrine/Congress opens the shared compact map menu. Details opens the existing full target view. Rally checks fresh authenticated eligibility before opening troop selection. An unavailable rally opens a centered explanation with the attack window and requirements; its muted button remains clickable for this explanation. Scouts can observe defenders without starting an attack.

English, German and French copy uses the `landmark.*` entries in the shared catalogs. Target names remain unchanged. Layout uses the common warm cream theme and supports portrait, landscape, reduced motion and browser Back.

## Server contracts

- `POST /api/march/scout-landmark`: string `target_kind` (`territory` or `shrine`) and string `target_id`. Creates a real scouting journey, consumes a march slot, and writes one historical `landmark_scout` report at arrival. Territory IDs remain strings in the march metadata. No troops, loot or attack window are consumed. Closed land and inactive territories remain inaccessible.
- `POST /api/rally/start-shrine`: integer `target_id`, `rally_minutes` (1/5/15/30) and `troops`. Creates a legacy Shrine/Congress rally using the existing reservation, joining and capacity rules. Luxembourg territory rallies retain their existing endpoint and leadership requirements.
- Both write endpoints require an authenticated session, CSRF, expected world and command receipt. Retries return the original order without reserving again.
- Shrine event eligibility is checked at creation, joining, launch and scheduled arrival. A closed window cancels launch safely. Delayed settlement honors the original event instance and scheduled arrival. Settlement runs in the saved world, regardless of the caller’s current world.
- Winning Shrine/Congress rally members station their surviving troops as individual real garrisons. Wounded troops enter their own hospitals. Defeated or cancelled armies return once. Each member receives a combat report.

No database migration is required: these actions use the existing marches, rallies, garrisons and reports tables.

## Verification

`php tests/landmark_actions.php` uses a disposable database for arrival intelligence, reservations, joining, outcomes, world context, delayed event settlement, CSRF and command retries.

`tests/landmark_actions_app.cjs` runs against `tools/preview-feature-fixture.php --territory --port=18946`. It checks the actual main app, all three Luxembourg landmark categories, centered blocked-rally information, troop selection, Back and real scouting dispatch in five screen sizes.

`tests/landmark_legacy_app.cjs` runs against `tools/preview-feature-fixture.php --teleport --landmark-actions --port=18947`. It checks Congress and four elemental Shrines, full details, closed event information, real rally dispatch and the arriving scout report in the actual mailbox.

Browser scripts use `PLAYWRIGHT_MODULE` when Playwright is outside the project. Only use the disposable localhost fixtures for these write tests.
