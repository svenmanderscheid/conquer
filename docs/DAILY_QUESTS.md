# Daily quests and activity rewards

The quest window separates Main (permanent starter missions), Daily (the thirteen daily quests and activity chests), and Events (the existing event window). Ready/claimed filters and scroll position belong to the selected category. The default is Main while any starter mission is unclaimed, otherwise Daily. This presentation change does not alter quest rewards, reset time, claim routes or point values.

Union of Kingdoms has thirteen daily quests and five daily activity chests. Their targets, activity values and rewards are defined in `data/daily_quests.json`. The existing eight quests retain their original rewards. Four gathering quests require 25,000 food, lumber, stone or gold; an optional alliance quest requires five successful helps.

Each ordinary daily quest awards ten activity points **when its reward is claimed**. Completion alone gives no points. Permanent starter missions and activity chests give none. The final chest requires 100 points, while the thirteen quests offer 130 points, so players can skip three tasks and still unlock every chest. Claiming a chest does not spend points.

| Activity | Chest contents |
| --- | --- |
| 20 | 20,000 of each resource |
| 40 | 40,000 of each resource |
| 60 | 60,000 of each resource |
| 80 | 80,000 of each resource, one silver chest, 50 gems and five common (grey) relic fragments |
| 100 | 100,000 of each resource, three silver chests, a 100 VIP point pack, 100 gems and five epic (purple) relic fragments |

Each resource means food, lumber, stone and gold, delivered as 10,000-unit inventory packs. Silver chests, VIP points and fragment items remain in the inventory until used; gems are credited directly. The single-fragment items `10207011` (common) and `10207013` (epic) each grant exactly one fragment of a random relic of that rarity, so five items provide five pieces in total. They reuse existing artwork and do not change the existing ten-fragment packs. These milestone contents follow the user's 6 October 2026 specification; previously claimed milestones remain claimed.

## Server behavior

Daily quest records and claims are account-wide and reset at **00:00 UTC**. Previous dates remain in `player_daily_quests`; no migration or scheduled reset job is required. Activity chests use reserved codes `daily_activity_20`, `daily_activity_40`, `daily_activity_60`, `daily_activity_80` and `daily_activity_100` in the same table. They are omitted from the ordinary quest list.

`DailyQuestService::getState()` returns:

- `quests`: daily and permanent starter quests; ordinary dailies include `activity_points`.
- `quest_activity`: actual `points`, `max_points` (100) and five `milestones`, each with `quest_code`, `target`, `progress`, `completed`, `claimed` and `rewards`.
- `quest_resets_at`: the next UTC midnight, in the existing `YYYY-MM-DD 00:00:00` format.

Both the kingdom state and `GET /api/quests/daily` expose this contract. Clients should cap the visual activity bar at `max_points` while displaying the actual points earned, which can reach 130. Existing `quest.claim` kingdom actions and `POST /api/quests/claim` accept the chest codes; clients never supply rewards or points.

Every read, progress event and claim binds its statements to one database UTC date. Progress events seed the current day before counting, including a first monster victory or chest opening after midnight. Claims serialize using the player row, validate server-derived activity and commit the claim flag and inventory/gem grants in one transaction. Failure rolls back the whole grant. A repeated or concurrent claim cannot award the chest again.

## Progress sources

`KingdomService::questState()` reconciles durable progress for dedicated quest endpoints; the main kingdom state/action paths use the same reconciliation:

- Gathering sums each resource from completed gathering marches returned during the current UTC day, across the player's worlds. Returning marches, future returns, previous days and other players are excluded. The legacy `wood` haul key is normalized to `lumber`. The existing total-gather quest uses the same account-wide sum.
- Alliance help counts successful entries in `community_help_log` for the current UTC day, across worlds. Repeated help requests reuse the community command receipt and do not create additional progress.
- Existing building, training and research quests retain their durable completion reconciliation in the active city/world.
- Existing monster victories and chest openings retain their server event hooks.

Reconciliation sets the greatest observed total, capped at the target; it does not add the same history again on refresh. Starter missions remain permanent and per world.

Quest titles and descriptions use `quests.daily.<code>.title` / `.description` in the common language catalogs. English remains the default and fallback.

## Verification

Run `php tests/daily_quest_activity.php` and the existing `php tests/starter_quest_rewards.php` with the local PHP/MySQL environment. Both create disposable databases and leave player data unchanged. The activity test covers real alliance-help commands, gathering boundaries and world scope, both authenticated claim routes, CSRF, transaction rollback, concurrent claims, UTC resets, optional points beyond 100 and permanent-mission exclusion.

Final local verification on 6 October 2026: 144 activity checks and 106 existing reward checks passed, including structured `503 QUEST_BUSY` responses when another request holds the player lock. `tests/quests_app.cjs` passed in German across six viewports. `tests/daily_quest_activity_app.cjs` passed in English and French across five viewports, with all thirteen ordinary quests and five chests claimed through the real authenticated API. Locale delivery, translation placeholders and JavaScript/PHP syntax checks also passed. Mobile checks used browser viewports, not a physical device.

Screenshots are under `output/playwright/daily-quests-baseline-20261005/`, `output/playwright/daily-quest-activity-20261005/` and `output/playwright/daily-quest-activity-fr-final-20261006/`. The disposable `--quest-activity` preview flag supplies completed objectives for the browser claim tests; it does not affect normal player state.

## Production release — 6 October 2026

Deployed to `https://play.unionofkingdoms.com` at 05:01:35 UTC after explicit user approval. The eleven-file package was merged onto downloaded production files, preserving live VIP calculations, existing artwork, inventory helpers and translations. Production's existing exclusion of permanent starter missions from the daily service was retained; the local starter integration was not enabled by this release. No migration or player-data maintenance was performed.

The private server backup is `/home/u171686647/uok-daily-quests-20261006T045600Z/before.tar.gz`, outside the public web root. The exact release, before/after hashes, patch, installation receipt and HTTP verification report are in `output/daily-quests-release-20261006/`. Do not replace these production files wholesale from the local checkout without reviewing unrelated differences.

Production verification passed for all eleven installed file hashes, eight existing table contracts, eleven reward item definitions and required artwork. Public JavaScript/CSS downloads matched the release hashes; the home/city pages and English, German and French locale assets returned HTTP 200. The unauthenticated quest endpoint correctly returned HTTP 401. Authenticated reward claims were tested only in disposable local databases, not against production player accounts. The live browser session could not be inspected because the desktop browser runtime was unavailable.

### Repair after deployment reset — 6 October 2026

The user's later screenshot exposed an actual server rollback, not a browser cache issue. Live Git reflog recorded `reset: moving to HEAD` at 05:08 UTC and several later times; the service, definitions, game script and locale hashes again matched their pre-release versions. The old service returned all eighteen seeded daily rows while recognizing only eight definitions, exposing internal codes and empty rewards, including activity milestones as ordinary quests.

At 11:15:46 UTC the eleven quest files were reapplied onto a fresh live snapshot, preserving the current unrelated inventory/artwork helpers. The activity chest buttons now explicitly override the global button background/shadow and share a continuous progress bar. Compact rows place an illustration left, objective/progress/reward strip centrally, and the action right. Local Main/Daily/Events category work remains intact; this repair does not publish its separate rollout.

The exact live release is committed on the server as `902a78933c8cb51f1f012734d994ef9991b1492b`, so the observed reset-to-current-HEAD operation retains the repair. The corresponding source feature is committed locally as `c24a4cc` (present on `origin/main`, verified 7 October 2026). Do not replace live with origin/main wholesale: the live checkout has other deployment divergence and untracked files which require separate reconciliation. No hosting settings were changed.

Private backup: `/home/u171686647/uok-daily-quests-repair-20261006T111330Z/before.tar.gz`. Exact package, hashes, receipt, diagnosis and screenshots: `output/daily-quests-repair-20261006/`. All eleven hashes and existing schema/item dependencies verified after installation and again after commit. The existing activity browser suite passed five viewports and all thirteen quest/five chest claims in a disposable local database. The authenticated live browser confirmed translated objectives, thirteen dailies, five milestones, current activity and the four-resource reward preview; no live rewards were claimed by the agent. Live portrait (390×844) and short landscape (567×320 with browser zoom) DOM geometry confirmed sufficient touch targets and a scrollable list inside the viewport. The live desktop screenshot is `live-quests.jpg`; narrow live screenshot capture was unavailable, with visual coverage supplied by the local main-app screenshots.

### Milestone reward adjustment — 6 October 2026

The five reward sets in the table above went live at 12:59:20 UTC. The six-file release changes only the milestone definitions, two single-fragment item definitions, and four translation entries per language. Existing ordinary daily quest definitions and rewards remain unchanged. Production commit: `62b94cfbc0121e9399e32a6f2616460fbd881f26`; corresponding local source commit: `c862a5f8ea9de2745bea91c9d1c4321fa3006cfa` (present on `origin/main`, verified 7 October 2026). No migration or claim reset was performed.

The activity integration suite passed 243 checks, including exact resource/chest/VIP/gem amounts, concurrent claims, and actual consumption of five common and five epic single-fragment items. The browser suite passed five desktop/portrait/landscape viewports, thirteen ordinary quest claims, all five milestone claims, exact reward previews, badge updates, and absence of browser errors in a disposable local database. Its responsive check now waits for actionable touch targets after resize/dialog transitions instead of sampling at a fixed 150 ms delay.

Authenticated production previews confirmed the 80-point and 100-point contents, including five fragments of the appropriate rarity. No production rewards were claimed during verification. All six installed hashes were verified. The private backup is `/home/u171686647/uok-daily-rewards-20261006/before.tar.gz`; package, manifest, receipt, scoped source patch and screenshots are in `output/daily-quest-rewards-20261006/`. `live-rewards.jpg` shows the final chest on the live server.
