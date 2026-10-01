# Union of Kingdoms: guided progression and the next alpha

This package extends the existing illustrated game and shared English-first interface. It does not create a new tutorial, currency, reward system or native app.

Subsequent boss updates: all five active families now have skills. Since 1 October 2026, new single-type counters require 50% base power and Dawnhorn requires 30% from each type. The original implementation notes below retain their historical values; current rules are in [Rally boss skills](RALLY_BOSS_SKILLS.md).

## Implemented behaviour

- The active beginner guide has nine goals: its original five milestones, plus returned gathering loot, a confirmed monster victory, a collected Charm and actual help given to an alliance member. The HUD chooses an available next activity while another task is waiting. Progress comes from existing server records, scoped to the authenticated player and world; it is not awarded by clicking through the guide. Unknown records do not count as completed. Reading preferences remain local. No additional polling or automatic game action is introduced.
- Inventory items, including zero stock, and relics link to an authenticated acquisition guide. The guide reads the current world's effective monster, chest, dungeon, expedition and trade rewards, plus owned fragment packs. It shows conditions and probabilities and rechecks the source before navigation. A random relic roll is distinguished from a guaranteed matching fragment. Opening a source does not purchase, consume, claim or dispatch anything.
- Grumwald restores 12% of damage from a nonlethal hit. Ranged troops contributing at least 30% of the combined base troop power suppress regeneration. The preview and actual battle share the server rule; other bosses and historical orders without the rule retain their previous behaviour. A rally preview concerns the selected player's contribution, whereas settlement counts all armies that reached the rally on time. See `GRUMWALD_MECHANIC.md` and the regression tests for the exact contract.
- The current alliance goal explains attack participation, garrison defence and support where applicable. A read-only contribution summary shows the player's support tasks and rewarded victories over the past seven days, currently active garrisons, and distinct participating current members. It uses the current alliance and world. Reviewing an option still requires the normal explicit action and server checks.
- Backoffice statistics include return cohorts, independent progression milestones, wounded troops, average stored resources, territory support and current Commune ownership. Return cohorts use first recorded activity in the selected world, not registration; only complete UTC return days are eligible. The selected time window covers return days, support tasks and conquests. Current stocks and milestones are snapshots, not a longitudinal economy ledger or a sequential conversion funnel.

EN, DE and FR share the same keys and placeholders. New panels use the existing UI variables and responsive navigation. No live world has been configured or converted, no real player state migrated and no deployment performed by this implementation.

## Small human playtest

Use a separate alpha world with about 20 consenting testers split across several alliances. The existing Luxembourg alpha settings allow two or three complete conquest cantons and at most one Shrine per alliance. Keep the whole map available for city placement. Choose play windows with participants; do not infer attendance from accounts or send invitations automatically.

Start with five inexperienced testers playing without coaching. Observe whether each can train troops, find and defeat a suitable monster, use the resulting progression and help another member. Record the first point where they need help and the elapsed time to their first meaningful success. Ask what they intend to do next.

Then use the larger group to run a complete shared Commune campaign: set a goal, prepare a rally, join, scout or supply, resolve combat, claim earned rewards and reinforce the territory. Include small armies and one player reconnecting during preparation. Observe whether those players understand their contribution and whether recovering from losses remains enjoyable.

Compare the return-day cohorts with feedback after the first and seventh fully observed day. Empty cohorts are unknown, not zero retention. Watch sustained concentration of Commune ownership, idle full stores, wounded troop burden and progression gaps. Change one balance variable at a time, record its rule version and test again. These metrics alone do not prove fairness or enjoyment.

## Real-device acceptance still required

On the agreed Samsung Galaxy S23 Ultra and iPhone 13, record OS/browser version and repeat the full journey in portrait and landscape. Check the actual keyboard, safe areas, Back behaviour, screen lock, background/resume, Wi-Fi/mobile switch and a temporarily lost response. Verify that reopening a source, guide or pending action neither loses context nor executes twice. Measure sustained map performance on a weaker Android device when one is available.

Browser emulation covers layout and navigation, not hardware performance or a native WebView release. Capacitor packaging remains a later phase after stable web gameplay.

## Verification

All database and main-app suites use synthetic disposable databases. Run them sequentially because existing game locks are server-wide.

- `tests/beginner_journey.php`, `tests/beginner_journey_selection.cjs`, `tests/beginner_journey_app.cjs`: authentic milestone evidence, ready/waiting selection, world isolation and actual guide navigation.
- `tests/item_sources_rules.php`, `tests/item_sources.php`, `tests/item_sources_app.cjs`: exact probability semantics, current effective sources, world/permission boundaries, no gameplay writes, source navigation and small layouts.
- `tests/boss_mechanics.php`, `tests/grumwald_rally.php`: threshold, healing bounds, no resurrection, preview parity, frozen rules, actual combat and replay.
- `tests/alpha_playtest.php`, `tests/improvement_app.cjs`: participation counts, complete-day cohorts, world boundaries and new main-app/backoffice layouts.
- `tests/world_target_navigation.cjs`, `tests/world_target_navigation_app.cjs`: scene-commit timing, superseded targets, leaving/re-entering the map, world changes, in-flight responses from the previous map position and exact current/next-week Dungeon cards.
- Existing affected suites cover monster rallies, battle preview, regional bosses, translations and mobile navigation.

## Local verification results

- Beginner journey: 27 server checks and the prerequisite/next-goal selection suite passed. The real app passed all nine goals, direct alliance-help navigation, world separation, stable focus/scroll, Back and no automatic game commands.
- Item sources: 21 probability/query rules and 19 database/HTTP checks passed. The real app passed zero-stock inventory and relic lookup, source navigation, stale-response dismissal and exact inventory selection without purchases or dispatches.
- Grumwald: 44 mechanic checks, 13 real rally checks, the existing 75 monster-rally checks, battle-preview and regional-boss suites passed. The real march dialog and calculator display the same server thresholds.
- Alpha measurements and contributions: all 14 database checks passed, including complete-day return cohorts, world/alliance separation, unique participation and read-only behaviour.
- Main-app layouts passed at 1280×800, 390×844, 320×568, 844×390 and 568×320. Screenshots are under `artifacts/beginner-journey/`, `artifacts/item-sources/` and `artifacts/improvements-2026-09-30/`. The affected territory frontend suite also passed in these five sizes.
- Shared-language key/parameter checks and syntax checks passed. EN remains the default, with complete DE/FR counterparts for the additions.
- The battle calculator passed its existing layout/navigation checks plus explicit EN/DE/FR monster/PvP checks. Its result labels and explanations now use authored shared keys, preserving troop counts and distinguishing fallen troops from unrelated translations.
- The final main-app rerun passed with loaded images and no browser errors. The existing `tests/mobile_pages_app.cjs` navigation-only run passed research/Back and scroll retention, chat, relic details/exchange, hospital, profile, mail and VIP. The isolated test server's initial document load has a separate 45-second navigation allowance; interaction timeouts and assertions remain unchanged.
- World-target navigation passed the production-function tests and the real app with a deliberately slow scene transition. Exact Dungeon source names remain visible in portrait and short landscape; the source finder was rerun successfully after this integration.

These are local tests with disposable data and browser emulation. The human alpha, physical-device acceptance and live deployment remain outstanding.
