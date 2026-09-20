# Backoffice

## Illustrated navigation and reward editor

The overview links directly to reward settings, the illustrated item catalog and
player support. `/admin/items` searches all 166 current items by name, number,
category and rarity. Gifts and reward rows use the same searchable image picker.
`assets/css/admin-backoffice.css` uses the shared `--ui-*` palette, with
`village-theme.css` still loaded last. The mobile menu retains text labels.

`/admin/rewards` supports all 99 canonical monster definitions (including rally
bosses), all six dungeons, three chest types and all nine boss/difficulty
combinations for expeditions. Source lists and long reward lists are searchable.

- Monster settings include guaranteed resources, gems and independent item
  probabilities/quantities. Solo charms have a separate chance and rarity split.
  Rally item/resource quantities are a shared pool, divided by contribution;
  rally monsters do not spawn map charms. Legacy world codes resolve to their
  canonical monster definition before an override is applied.
- Dungeons configure the target relic, base fragment count, overall item chance,
  quantity and weighted item pool. Exactly one item type is selected on success.
  Existing difficulty, side-room and gathering bonuses apply. Custom item chance
  is capped at 100%; untouched dungeon defaults retain the original 85% cap.
- Chests configure 1–20 draws and weighted item or relic-fragment entries,
  including mythic fragments. The same override is used by the daily treasury
  chest and owned inventory chest paths. Zero-weight entries never drop.
- Expeditions configure final resources, guaranteed gems and independent item
  drops for each boss and difficulty. Claim receipts contain the actual drops.

The new additive migration is `0083_reward_overrides.sql`. Apply it alone with
`php tools/migrate-rewards.php` or with the normal migration runner. It does not
create overrides or change catalog files. Overrides live in the database and
apply globally across worlds; the editor labels this scope and hides the world
picker on catalog pages. Back up `reward_overrides` with the game database.

Saves and resets use the existing superadmin check, CSRF check, reason, operation
receipt and audit transaction. Source updates are serialized; a revision check
prevents stale forms from overwriting another change. Reset retains a versioned
tombstone. Validation rejects unknown sources/items, duplicates, invalid
quantities/probabilities and empty weighted pools. Forms retain failed inputs.
The maximum of 200 rows stays below PHP's default input-variable limit.

New dungeon groups and expedition runs snapshot the configured rewards. Rally
pools are snapshotted when gathering starts. Existing runs keep their snapshots.
Solo combat reads the current definition, stores its rolled items/gems in the
return haul, and pays once when the army returns. This also replaces the old
separate goblin payout. Historical solo drops referring to nonexistent inventory
IDs are excluded and identified in the editor; administrators can select current
items in their place. Remaining default rows use the current item-code meaning,
rather than the obsolete descriptive labels from the source data.

`php tests/reward_admin.php` uses a disposable local schema and synthetic users
to check all source defaults, authorization, stale/replayed requests, resets,
validation, snapshots and real monster/chest payouts. Add `--browser` with
Playwright available on `NODE_PATH` to test login, saving/reloading, failed-input
retention, reset, search, picker, moderator controls and layouts at 1440, 390,
320 and 667×375. Browser writes target only the temporary fixture server.

The administration panel is available at `/admin`, with the separate existing
admin login at `/admin/login`. `superadmin` accounts can change state; moderators
have read-only access. Existing administrator accounts remain valid.

Apply migrations 0063–0065 before using these screens. Migration 0065 reconciles
the two historical audit schemas without removing existing entries. Event
settings additionally use the event-service migration 0068. Playable multiworld
sessions and memberships require 0072; city-specific scout protection uses 0073.

## Alpha invitations

`/admin/alpha-keys` (Community → Alpha-Keys) manages registration invitations
globally, independently of the selected world. Superadmins can create 1–50 keys
per operation, choose a label, 1–65,535 registrations per key and an optional
future expiry in UTC. Moderators can view metadata only. The list supports label
or ID search, availability filters and pagination. Revoking a key stops future
registrations; existing accounts and their login access remain unchanged.

Full keys appear once after creation with a copy button and a manual selection
fallback for browsers without clipboard access. The redirect temporarily carries
the output in the issuing admin's session (valid for five minutes); rendering
consumes it and responses are marked no-store. Only hashes are stored in the key
table; neither audit entries nor durable operation receipts contain plaintext
keys. Replayed submissions create no additional keys and cannot recover the
secret. If the output was lost, revoke those invitations and create replacements.

Creation and revocation use the existing authenticated POST/CSRF, reason,
operation receipt and audit transaction. No new migration is needed beyond the
existing `0100_alpha_access_keys.sql`. The CLI creator remains available.
`php tests/alpha_keys_admin.php --browser` verifies isolated service and HTTP/UI
flows, including permissions, limits, rollback, copying and mobile layouts.

## World management

Select the world in the header. `/admin/world` controls its name, status, speed,
gathering and transport factors, spawn settings and event schedule. New worlds
receive independent settings and initially start paused. Maps currently use
256×256 tiles, matching the established terrain and renderer. Creating a world
also reserves its central Congress and four regional shrines. EventService
manages additional competitive event targets separately.

Spawn controls use three different percentages:

- **Target density:** desired object count per 100 map tiles, independent for
  resource nodes and monsters. For example, 1% on a 256×256 map targets 655 nodes.
  This counts objects, not the physical area occupied by their footprints.
- **Spawn chance:** probability of creating an object for each eligible spawn
  slot during a pass. 0% never spawns and 100% always attempts placement.
- **Type distribution:** relative shares among new objects in that group. Each
  group's weights must sum to exactly 100%; gold mines can for example receive
  20% of the resource distribution. A weighted monster type with no definition
  in the selected level range is skipped and counted as a missed attempt.

Absolute population caps, a shared per-pass attempt budget, dry terrain and
non-overlapping footprints also constrain spawning. Lowering a target does not
delete healthy existing objects. New entities receive explicit expiry times;
existing expiring entities retain their expiry. Legacy monsters without an
expiry use the configured lifetime from their original spawn time. Active
marches and rallies protect their targets from cleanup until their completion.

Intervals are in minutes. Time windows use UTC; equal start/end times mean all
day and overnight windows are supported. Paused/closed worlds and disabled
spawners do not run. Saving rules makes the next allowed window due. Changes
take effect when the worker next runs. Configured worlds no longer use the
legacy per-player frontier replenishment, which would otherwise bypass limits.

Run the worker every minute through the hosting panel:

```cron
* * * * * php /absolute/path/to/conquer/cron/world_spawn_tick.php
```

The database enforces each world's own interval, so invoking the worker more
often does not produce duplicate passes. `cron/seed_field_objects.php` is a
compatibility alias to the same worker. No machine or hosting cron is installed
automatically by the source change. The dashboard shows the next due time, last
run and per-world run history including counts and failed placement attempts.

Loading a configured game world also invokes the same due-time worker with
source `game`; this supports local testing without bypassing intervals, UTC
windows or limits. A scheduled server worker remains necessary while nobody
is viewing a world. Attempts are shared proportionally between resource and
monster deficits so that one population cannot exhaust every pass first.

## Players and gifts

The player list searches account name, email or ID within the selected world
and supports banned/recently-active filters. Profiles expose the actual city
resources, 13 canonical buildings, research definitions and current garrison.
Resource edits set absolute balances and reset the production snapshot time.
Building edits settle elapsed production, update city power/castle level and
use the defense service for wall HP. Active upgrades on the edited building or
research are rejected so later completion cannot overwrite the admin edit.
Troop edits set the available city garrison; queues and dispatched troops
remain governed by their normal lifecycle.

Bans set `players.is_banned` and revoke all current game sessions. Unbanning
allows a new login. Account bans and gems apply globally; city resources,
buildings, research and shields apply to the selected world.

Gifts can contain resources, gems and one catalog item type, plus a title and
message. They can be sent to one player or all non-banned players with a city
in the selected world (up to 1,000 recipients per transaction). A world gift
checks the recipient count shown in the form before delivering. Resources are
world-specific; gems and inventory are account-wide. Each recipient gets an
`admin_gifts` receipt and an `admin_gift` notification. Delivery credits directly;
there is no extra claim action.

Every action requires a CSRF token, a currently valid superadmin account, a
reason and a 128-bit operation ID. Repeating the same operation returns its
receipt without applying the mutation twice. Reusing an ID with another payload
is rejected. Mutation, receipt and audit record commit atomically; any failure
rolls back the whole operation, including earlier recipients in a world gift.
Audit records expose the reason and before/after values. Gift receipts contain
per-recipient before/after balances.

## Verification

`php tests/backoffice_integration.php` creates a uniquely named empty throwaway
database, copies table definitions only, adds synthetic fixtures and drops the
test database in `finally`. It does not create or modify live players. It checks
authorization/CSRF, world isolation, real bans, idempotency, gifts, rollback,
building/wall updates, queue protection, catalog validation, spawn boundaries
and rendering of all admin screens. The integration test writes synthetic HTML
previews to the operating-system temp directory for optional visual review.
