# Core gameplay responsiveness — 10 October 2026

The first pass covers city → building upgrade → world map → solo monster attack → confirmed rewards, using the existing Fantasy · Bree Serif + Warmes Creme & Königsgold interface.

## Behaviour

- City/world navigation commits on the next animation frame, then fades the existing transition for 160 ms. The former 260 ms pre-commit and 420 ms minimum post-commit waits are removed. Controls become available during the reveal. Superseded frame callbacks are cancelled; reduced motion still commits immediately. City and map instances remain mounted and are reused.
- Core city/map data is applied as soon as its own response arrives. Supplemental kingdom, expedition, rally and public-march data no longer delay this display.
- A gameplay command does not wait for an earlier polling batch. Starting a command invalidates that batch. Both its city and supplemental responses are discarded if they subsequently arrive. After confirmation, an authoritative city read reconciles resources, queues and troops before the command is released; supplemental reads follow independently. No costs, timers, combat results or rewards are predicted on the client.
- Confirmed reward receipts display before reconciliation reads finish. Exact operation keys, server replay protection and recovery after a missing response remain in use. A delayed receipt does not reopen a newer dialog. Late dialog polls also cannot replace a newer dialog.
- Resource controls update their numbers and accessible labels in place. Unchanged dock controls retain their DOM nodes. Closed/edited dialog views skip unnecessary full-state serialization. Existing gesture protection and scroll retention remain in use.
- Existing painted ground/tree assets can warm during a city idle period, without mounting a map, starting world animation or requesting gameplay state. Data-saving connections and hidden pages skip this preparation. This does not add new artwork or change map rules.

## Verification

`tests/core_flow_app.cjs` runs actual upgrade and march endpoints and actual server settlement in an automatically removed synthetic database. Its CLI time-control helper validates the temporary root, database name, player, city, world and active queue/march before advancing time. It never changes real players or production timers.

The browser run covers 1280×800, 390×844, 320×568, 844×390 and 568×320. It holds an old polling response and supplemental reads open during an upgrade, verifies the upgrade becomes visible before release, rejects obsolete resource/profile snapshots, preserves resource/navigation controls and checks confirmed monster rewards. Screenshots and measurements are under `output/playwright/core-flow/`.

Related suites cover scene reuse, reduced motion, cancelled target navigation, building contrast, monster reports, audio, comfort/scroll retention and exact reward replay after a lost response. The reward suite explicitly holds the post-command city read while asserting that the confirmed receipt is already visible.

Final focused runs passed: `core_flow_app`, `scene_transition_app`, `world_target_navigation_app`, `building_contrast_app`, `reward_dialog_app`, `monster_report_app`, `game_audio_app`, `world_target_navigation` and `app_polling`; the comfort browser suite also passed during implementation. Per-suite evidence is in `output/playwright/core-flow-verified/`, `core-flow-navigation/`, `core-flow-final/` and `core-flow-regressions/`. Earlier failing attempts are retained; use the latest successful per-suite result, rather than an older aggregate run.

The final complete-flow run measured first map transitions at 660, 434, 361, 372 and 399 ms respectively across the five viewports. The separate scene suite measured reused transitions around 239–373 ms. These are local headless Chromium observations, not a physical-device or production performance guarantee. The core-flow result contains no browser errors or missing assets. The city test pans the existing city before selecting a farm, keeping its hit area clear of the fixed HUD and chat. No new camera behaviour is introduced.

Race tests explicitly hold the scene commit and stale responses rather than relying on fixed sleeps. The navigation test blocks service workers so its injected race gate cannot be bypassed by a cached application script. Read-only tests exclude the existing `/api/telemetry` diagnostic collector from their gameplay-write assertion; all gameplay POSTs remain prohibited.

Browser viewport/touch emulation does not establish physical-device frame rate, battery use or production network latency. First-map image/layout work remains separate from the removed fixed waits. No live deployment or live player-state changes are part of this pass.

The full existing localization suite currently reports catalog parity failure for French. A baseline comparison also finds 240 missing French talent keys in HEAD, with the same count after this change. The new `hud.resource.open` key has matching `{name}`/`{amount}` parameters and authored EN, DE, FR and LB text.
