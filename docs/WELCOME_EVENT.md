# Kingdom Foundations welcome event

The existing Events screen contains a permanent welcome campaign inspired by the reference game's newcomer support and growth tracks. It awards supplies for seven distinct UTC visit dates and six completed growth milestones. This does not change daily quests, activity chests, starter missions, Conquest, invasions, or scheduled event shortcuts.

## Player rules

- The first authenticated kingdom-state visit in a world starts that world's campaign and counts as day 1. Installing the feature never fabricates previous logins for an existing account.
- Each later UTC calendar date with a visit adds exactly one day, up to seven. Multiple devices, refreshes and repeated requests on the same date do not add days.
- Missing days do not reset progress. Returning after twenty days adds one visit, not twenty. Days do not have to be consecutive.
- Rewards do not expire. Unclaimed earlier days remain available. Collecting is an explicit action.
- Growth uses completed progress in the active world, including progress achieved before the campaign began: castle levels 3 and 5; 500 and 2,000 trained troops; 3 and 10 total research levels.
- Troops still training do not count. Completed training is counted from processed queue rows whose finish time has passed; troop losses do not remove that historical training progress. Research counts learned levels, not pending queues.
- Each milestone can be claimed once per player and world. Progress and claims do not carry between worlds. Granted items use the game's existing inventory scope: these ordinary supplies and speedups enter the shared item inventory and are applied in the world where the player uses them.

## Rewards and balance

`data/welcome_event.json` is the authoritative reward list. It uses existing, usable item codes and existing illustrations; no premium currency, randomized drops or special event currency is added.

| Track | Food | Lumber | Stone | Gold | Universal speedups | Building | Research | Training |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| All seven visit rewards | 110,000 | 110,000 | 110,000 | 110,000 | 205 min | 60 min | 60 min | 60 min |
| All six growth rewards | 190,000 | 180,000 | 180,000 | 210,000 | 15 min | 60 min | 180 min | 180 min |
| Entire campaign | 300,000 | 290,000 | 290,000 | 320,000 | 220 min | 120 min | 240 min | 240 min |

Resources remain as 10,000-unit packs until explicitly used. Reward previews and confirmed claim results contain the exact same item/quantity arrays. The reward configuration is independent from the existing daily quest economy; nothing is automatically claimed or spent.

## Server contract

`WelcomeEventService::state()` is exposed as `welcome_event` in the authenticated kingdom state. It returns `available`, `code`, `player_id`, `world_id`, translated title/description/rules, `started_at`, `visit_days`, `visited_today`, `next_visit_at`, `claimable_count`, `login_rewards` and `growth_rewards`.

Each reward row contains a stable `code`, translated title and description, numeric `progress`/`target`, `completed`/`claimed`, and an authoritative `rewards` array. Growth rows also contain a navigation hint to `city`/castle, `army` or `research`. `next_visit_at` is midnight UTC in the existing SQL datetime format.

Claims use the existing authenticated and CSRF-protected kingdom action endpoint:

```json
{
  "action": "welcome.claim",
  "milestone_code": "login_1",
  "operation_key": "a-stable-unique-operation-key",
  "expected_world_id": 1
}
```

The result contains `message`, `milestone_code` and the confirmed `rewards`; the normal kingdom action envelope also returns refreshed state. Client-submitted quantities, thresholds and balances are ignored.

`Operation::run()` persists the original result for a retry with the same operation key and payload. A changed payload using that key is rejected. A new operation key cannot grant an already claimed milestone again. The frontend retains pending claim receipts and displays only server-confirmed loot through the existing reward dialog.

The service also opens its own transaction and locks the city and campaign row. A unique `(player_id, world_id, milestone_code)` claim record and every item grant commit together. A failed item grant rolls back earlier grants and the claim marker. The database's UTC clock is sampled once for the visit and its progress snapshot.

## Schema and rollout

`migrations/0133_welcome_events.sql` adds only `player_welcome_events` and `player_welcome_event_claims`. The standard migration runner discovers this file automatically. No existing quest tables or player records are rewritten.

Apply the migration through the established deployment process before enabling the event on an installation. Code deployed ahead of the schema returns `available: false` and leaves the rest of the game readable; a claim reports temporary unavailability. Development verification applies the migration only to disposable fixture databases. No player database was migrated as part of these tests.

## Verification

`tests/welcome_event.php` checks nonretroactive visits, repeated dates, skipped dates, the seven-day cap, completed growth, isolation between players/worlds, invalid claims, session and CSRF guards, operation receipts, altered payloads, ignored client rewards, rollback after a late grant failure, concurrent direct claims, closed-world rejection and pre-migration compatibility.

Backend results: 149 welcome-event checks, 106 existing starter-reward checks and 144 existing daily-quest/activity checks pass in disposable databases.

`tests/welcome_event_app.cjs` passed against the actual main app at 1280×800, 390×844, 320×568, 568×320 and 844×390. It checks both reward tracks, scrolling, touch targets, real login and growth claims, exact reward previews and HUD counts. A committed claim whose reply is lost is restored after a page reload and retried with the same operation key, without duplicate rewards. No page errors occurred. Screenshots under ignored `output/video-reference/qa/welcome/` were visually inspected. Physical mobile-device verification remains open.
