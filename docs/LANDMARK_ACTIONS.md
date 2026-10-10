# Union of Kingdoms: map landmark actions

## NPC balance (10 October 2026)

Neutral Communes are alliance rally objectives. Their default militia has 1,400,000 T1 infantry; canton Shrines have 2,100,000 and each Royal Congress/Castle objective has 2,800,000. The existing territory combat formula and 10% base fortification make these approximately 1M, 1.5M and 2M attacking troops respectively, using an evenly mixed T3 army without bonuses as the reference. A level-10 solo march loses. Troop tier, research, talents, support and additional fortifications still affect the result; these are strength targets, not hard minimum troop counts. A Hall of Alliance reaches a base capacity of 1M at level 20 and 2.5M at level 30.

`TerritoryRules::currentProfile()` upgrades the former 120/800/1,600 defaults in existing world profiles when they are read. Custom values are retained, and the next administrative save persists the balance revision. New worlds receive the new defaults directly. Existing campaign and crown-cycle snapshots keep their recorded rules. NPC inputs in the administration and server validation share the same 10M ceiling. Captured Communes and Shrines continue to use their stationed player armies; crown objectives retain their NPC guards.

Legacy elemental Shrines and Congress use their own attack-versus-HP/defense formula and are calibrated to the same approximately 1.5M/2M T3 attacking-army scale. Dungeons retain their separate 2–4-player group limits; see [DUNGEONS.md](DUNGEONS.md) for their stronger encounter balance.

Regression coverage includes `tests/territory_npc_balance.php`, actual troop reservation and rally resolution, existing-world defaults, custom tuning and immutable campaign snapshots.

Selecting a Commune, canton Shrine, Royal Congress, or a legacy Shrine/Congress opens the shared compact map menu. Details opens the existing full target view. Rally checks fresh authenticated eligibility before opening troop selection. An unavailable rally opens a centered explanation with the attack window and requirements; its muted button remains clickable for this explanation. Scouts can observe defenders without starting an attack.

Territory Rally uses the same attack window as monster rallies, from both the map shortcut and the territory details: troop portraits, sliders, saved formations, Max and the 1/5/15/30-minute gathering picker. The target retains its name, coordinates and shared territory artwork; crown rallies also retain the gate/arsenal/throne choice. Troops are sent only by the explicit Start rally action. The existing `territory/action` endpoint and saved territory receipt preserve string target IDs, world context and identical retries after a lost response. Rally speed and capacity use the existing PvP rally values.

Luxembourg Communes and Shrines show a static blue protection dome, shield icon and live countdown while an occupied target is outside its world-specific combat window. The countdown comes from the server calendar and uses the existing corrected game clock. Neutral targets have no timed shield; inactive regions show no invented unlock time. The selected card shows the current window status and any missing Commune majority. Protection is public and remains visible for friendly territory as well. Opening removes the shield, and closing counts down to the next server-defined window. These display changes never authorize an attack: Rally still checks fresh authenticated eligibility. The dome does not intercept pointer input or animate, and map framing includes its timer and safe screen edges.

Map pointer selection consumes its following compatibility click even when rendering takes longer than the short drag guard. This prevents one touch from immediately closing the newly opened target card. The timer also remains clear of HUD edge buttons in short landscape views.

English, German and French copy uses the `landmark.*` entries in the shared catalogs. Target names remain unchanged. Layout uses the common warm cream theme and supports portrait, landscape, reduced motion and browser Back. Landmarks have one measured card with a wrapping name, a separate 44-pixel close button and stationary action hit areas. The map nameplate disappears during selection. Small screens frame the artwork and card together, reducing map zoom when needed; overlapping map shortcuts reappear when the selection closes.

## Server contracts

- `POST /api/march/scout-landmark`: string `target_kind` (`territory` or `shrine`) and string `target_id`. Creates a real scouting journey, consumes a march slot, and writes one historical `landmark_scout` report at arrival. Territory IDs remain strings in the march metadata. No troops, loot or attack window are consumed. Closed land and inactive territories remain inaccessible.
- `POST /api/rally/start-shrine`: integer `target_id`, `rally_minutes` (1/5/15/30) and `troops`. Creates a legacy Shrine/Congress rally using the existing reservation, joining and capacity rules. Luxembourg territory rallies retain their existing endpoint and leadership requirements.
- Both write endpoints require an authenticated session, CSRF, expected world and command receipt. Retries return the original order without reserving again.
- Shrine event eligibility is checked at creation, joining, launch and scheduled arrival. A closed window cancels launch safely. Delayed settlement honors the original event instance and scheduled arrival. Settlement runs in the saved world, regardless of the caller’s current world.
- Winning Shrine/Congress rally members station their surviving troops as individual real garrisons. Wounded troops enter their own hospitals. Defeated or cancelled armies return once. Each member receives a combat report.

No database migration is required: these actions use the existing marches, rallies, garrisons and reports tables.

## Verification

`php tests/landmark_actions.php` uses a disposable database for arrival intelligence, reservations, joining, outcomes, world context, delayed event settlement, CSRF and command retries.

`tests/landmark_actions_app.cjs` runs against `tools/preview-feature-fixture.php --territory --port=18946`. It checks the actual main app, all three Luxembourg landmark categories, complete names, stationary edge clicks and touch taps, visible artwork without card overlap, centered blocked-rally information, shared troop selection from the map and details, and Back in five screen sizes. It also confirms a real Commune rally after explicit troop/time selection, drops the successful response and verifies that recovery sends the identical saved order, followed by real scouting dispatch. Restart the fixture after changing application assets: it copies the code when started.

`tests/march_windows.cjs` covers the shared territory composer for Commune, Shrine and crown targets in portrait, landscape and desktop, including crown objectives, rally capacity, PvP/cavalry speed, eligibility changes and duplicate-confirm prevention.

`tests/landmark_legacy_app.cjs` runs against `tools/preview-feature-fixture.php --teleport --landmark-actions --port=18947`. It checks Congress and four elemental Shrines, full details, closed event information, real rally dispatch and the arriving scout report in the actual mailbox.

`php tests/territory_protection.php` checks neutral, occupied, inactive, overnight, custom-world and exact-boundary calendar states without a database. `tests/territory_http.php` checks matching protection in map and detail responses. `tests/territory_protection_app.cjs` runs against `tools/preview-feature-fixture.php --territory --territory-protection --port=18948`, covering actual city/world views in five sizes, shields, live timers, majority requirements, server locks and reduced motion. Its browser-only clock offsets test opening and closing without changing game data.

Browser scripts use `PLAYWRIGHT_MODULE` when Playwright is outside the project. Only use the disposable localhost fixtures for these write tests.
