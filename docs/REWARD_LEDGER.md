# Union of Kingdoms reward history

The administration records confirmed reward grants separately from rejected attempts. The history answers which player received a reward, in which world, from which source, and under which saved rule. A rejection is diagnostic evidence; it does not by itself establish player misconduct.

Implemented and verified locally on 10 October 2026. The checks use disposable databases and synthetic accounts. This document does not indicate a production deployment.

## Administration

**Drops & balance** has three tabs:

- **Drop rules** retains the existing source browsers, inline item and fragment tables, world overrides, explicit saves, reasons and revision checks.
- **Actual grants** lists committed item, fragment, whole-relic and reward-resource receipts.
- **Invalid attempts** lists rejected reward events from the observability store. Requested and credited amounts remain distinct; rejected attempts show zero credited.

History filters cover all worlds or one world, source type, player ID, item or relic ID, and the last 1, 7, 30 or 90 days. Results use a 50-row cursor rather than an unbounded export. Expanding a row shows its source, related action, rule fingerprint and operation identifier. Whole-relic rows include duplicate conversion results; fragment rows include the remaining balance and whether an unlock occurred. Links lead to the player and, when a source is identified, its current editable rule.

The current rule can differ from the rule that originally earned the reward. The recorded fingerprint identifies the saved rule; it is not a claim that the current editor still contains those values. Missing historical context appears as **Not recorded**.

Administration styling remains scoped to `body.admin-modern`. Labels use the shared English, German and French catalogs. Portrait, landscape, keyboard navigation and hidden scrollbars retain the administration conventions.

## Storage and rollout

`migrations/0144_reward_ledger.sql` adds `reward_grant_ledger`. It stores:

| Field | Meaning |
| --- | --- |
| `player_id`, `world_id` | Recipient and reward-origin world |
| `inventory_world_id` | Actual inventory scope; zero means account-wide stock |
| `reward_kind` | `item`, `fragment`, `relic` or `resource` |
| `item_code`, `resource_code`, `quantity` | Authoritative reward target and amount |
| `source_type`, `source_key`, `source_reference` | Source and related march, expedition, gift or other action |
| `rule_revision` | Saved rule content fingerprint when available |
| `operation_id` | Existing operation identifier when available |
| `result_json` | Relic unlock and duplicate-conversion outcome where applicable |
| `created_at` | Database timestamp in UTC |

There are no player or world foreign keys: deleting either entity must not erase its historical receipt. History can therefore retain numeric IDs after the associated name is unavailable. No pre-migration rewards are reconstructed.

Rejected attempts use `operational_events` from `0142_observability.sql`, with `category=reward` and `outcome=rejected`. `EventLog` uses a separate database connection, so the diagnostic event survives rollback of the gameplay transaction. Its bounded file fallback and health reporting apply if the diagnostic database sink is unavailable.

Before `0144` is present, confirmed receipt recording is unavailable and existing valid gameplay remains compatible. Once the table exists, a receipt-write failure rolls back its associated credit. Apply the additive migrations through the normal reviewed migration workflow; the automated checks never apply them to the live database.

## Transaction and replay contract

`InventoryService::addItems` validates its item, quantity and optional server-owned source context, then performs the inventory credit and `RewardLedger::granted` within one transaction. Nested callers use the shared connection's savepoints. A caller rollback removes both the stock change and its confirmed receipt. The same contract applies to central relic and fragment grants.

`RewardLedger::resources` records a credit already made by an authoritative reward operation inside that operation's transaction. It does not itself mutate resource balances. Calling it before the final operation completes is safe because any later failure rolls back the whole operation and its receipts.

Replay protection stays with existing operation owners. Replaying a chest, dungeon, march return or admin operation must return the existing result without calling the grant path again. The ledger deliberately has no blanket uniqueness rule on operation ID: multiple legitimate chest draws or different reward targets may share one operation. Tests verify both exactly-once replay and multiple legitimate grants within one operation.

Admin gifts commit their item and resource credits, reward-ledger rows, gift record, notification, admin audit and operation receipt together. Malformed quantities are logged before rejection, without storing raw arbitrary form values. A failure while writing a later resource receipt rolls back earlier item credits and ledger rows too.

## Source validation

Unknown or unregistered items, non-positive or out-of-range amounts, inventory overflow, and known items outside an authoritative saved source pool are rejected and logged. Unknown item IDs in submitted drop-rule changes are also recorded before the existing validation rejects the save.

Only server code supplies `allowed_items` and rule context. They are not accepted from request payloads. Monster and farm resolution stores the source context with the returning haul. Rally armies carry the saved monster context. Dungeon claims use the saved run definition; legacy runs without that snapshot do not invent a historical fingerprint. Chest draws and audit validation share the exact same authoritative cached chest configuration. Expedition receipts use the encounter's frozen reward rules.

This preserves already-earned rewards after an administrator changes a drop rule. Auxiliary dungeon quest drops are included explicitly in the server-generated monster and farm context. Deliberately disabled, retired or unavailable source entries filtered from pools are not automatically classified as player abuse.

## Coverage

| Reward path | Recorded coverage |
| --- | --- |
| Central inventory grants | Every successful `InventoryService::addItems` credit; explicit source context for major paths and a factual caller fallback for legacy callers |
| Central treasure grants | Fragments and whole relics, including unlock and duplicate-conversion results |
| Monster and farm returns | Items, fragments, relics and credited resource haul, using the stored origin world and saved rule context |
| Rally returns | Participant rewards and resources; monster rallies carry their original rule context |
| Chests and consumables | Item and treasure rewards; resource packs and resource boxes include their credited resources |
| Dungeons and expeditions | Saved run or encounter rewards and related action references |
| Administration gifts | Recipient-specific items and all positive food, wood, stone, gold and crystal credits |
| Daily and starter quests | Item grants, daily crystal rewards and starter resource rewards |
| Territory rewards and building refunds | Items and explicitly credited resources |
| Alliance gifts, shops and welcome rewards | Central item or treasure grants, with explicit source references where available |

This is a reward ledger, not a complete resource balance ledger. Passive production, every resource spend or transfer, action points, experience, VIP points, troops and every legacy direct SQL credit are not represented here. Do not infer total economic inflow, total player wealth or an observed drop probability solely from these rows. Some legacy callers lack a specific operation reference or historical rule snapshot; that missing context remains visible.

## Verification

`php tests/reward_ledger.php` verifies rollback, durable rejection events, source restrictions, amount bounds, frozen rules, forced receipt-write failure, relic conversion, replay, filtering and admin gifts. The admin-gift checks include negative, nonnumeric, array and excessive quantities, matching item/resource receipts, retry safety and a forced failure on the last resource receipt.

`php tests/reward_ledger.php --browser` additionally runs `tests/admin_reward_ledger.cjs` against a disposable authenticated HTTP fixture. It checks Actual grants and Invalid attempts in English desktop, German portrait, French landscape and a 320-pixel English viewport. It opens details, changes filters, returns to the existing rule editor and checks for overflow, visible scrollbars, untranslated labels and browser errors. Captures are stored under `output/playwright/admin-reward-ledger/`.

Existing reward administration, relic, rally, gathering, dungeon, expedition, bulk inventory, daily quest and mailbox suites passed during local integration. `ADMIN_MODERN_ONLY=1 php tests/reward_admin.php --browser` also passed the eight-section navigation, existing inline editors, world isolation and three-language/five-viewport checks. PHP syntax and relevant whitespace checks passed.

Run game-action database suites sequentially when they exercise player locks: some existing `GET_LOCK` names are shared across databases, even though fixture data is isolated.
