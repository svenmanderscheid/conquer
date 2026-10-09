# Union of Kingdoms administration

The modern administration design was approved on 5 October 2026. It uses its own system typography, neutral surfaces and blue actions, scoped to `body.admin-modern` in `assets/css/admin-modern.css`. The shared game theme loads first and keeps scrollbar behavior and semantic colors. The game retains its painted fantasy design.

## Navigation

Seven primary areas group existing features; secondary links expose every page:

| Area | Pages |
| --- | --- |
| Overview | Dashboard, statistics |
| Players & alliances | Players, gifts, alliances |
| Worlds | World settings and spawns, world creation, land development |
| Drops & rewards | Mines, monsters, dungeons, chests, expeditions |
| Catalog | Illustrated item catalog |
| Access & reports | Alpha keys, alpha waitlist, bug reports, chat moderation |
| System & history | Audit log, game layout editor |

The dashboard uses live counts and recent audit records. The mobile menu retains language selection and logout. English remains the default and fallback; new copy lives in `data/i18n/`.

## Deleting a world

Superadmins open **Delete world** from the dashboard or **Worlds → World settings**. Select the world and close it in its settings first. The deletion card shows the affected city and alliance counts. Enter the exact world name, acknowledge permanent deletion and provide an audit reason. The final remaining world cannot be deleted.

`POST /admin/action/world-delete` uses the existing authentication, CSRF validation, operation receipt and audit transaction, plus the shared combat, world spawn and reward locks. It removes world-scoped records, city/alliance descendants, legacy children without foreign keys and world notifications. It revokes game sessions currently using the deleted world and redirects the administrator to a surviving world. A successful request replay returns its receipt even after the world is gone; failures roll back the deletion, sessions, receipt and audit together.

Player accounts, global inventory, cosmetics, global rules, other worlds, admin gift history and audit history survive. Migration `0137_world_deletion_purchase_history.sql` makes the original world/city references in purchase orders nullable, preserving orders, entitlements and provider receipts. Pending or paid orders block deletion until resolved. Apply this migration during deployment; locally, `php tools/migrate-world-deletion.php --apply` applies only this migration. Login and stale sessions choose an existing world if the original world has been removed; retained accounts can use the existing explicit world-join action to start again.

Verification: `php tests/admin_world_delete.php --browser` uses a disposable database. It covers authorization, confirmations, closed/last-world restrictions, purchase preservation, foreign keys and legacy dependencies, future declared children, rollback after a late failure, receipt replay, session recovery and explicit re-entry. The browser checks English, German and French in desktop, narrow portrait and landscape layouts, including successful deletion and redirect. Captures: `output/playwright/admin-world-delete/`.

## Editing mine and monster drops

Open **Drops & rewards → Mines** (`/admin/rewards?type=farm`) or **Monsters**. Choose **All worlds** for global rules or **World override** and a world for a local rule. The **Whole relics**, **Relic fragments** and **Items** tabs have independent rows with a target, quantity and chance. Whole relic rows choose a specific current relic; fragment rows choose a specific relic or a random rarity. Average per 100 events is quantity multiplied by percentage chance. Adding a row starts at 0%.

Search, level and rule-status filters narrow the displayed sources. Edits to filtered-out sources remain pending. Long lists scroll inside a keyboard-accessible region with a fixed table header, leaving the save controls just below the list. A change note and **Save all changes** save only changed fields for changed sources. Resource rewards, crystals, charms and the other drop groups remain exactly as saved. The maximum batch is 200 sources; item rows are limited to 200, fragment and whole relic rows to 100 each per source.

Whole relics unlock the first effect without consuming existing fragments. Each additional copy grants `TreasureService::UNLOCK_COST` fragments of that relic (currently 10); it is not equipped automatically. Monster rules are stored at attack or rally start; mine rules apply at full depletion. Partial gathering and early recalls do not trigger relic drops. Direct rewards arrive with returning troops.

In **Chests**, use **Add reward** and select **Whole relic drops** or **Fragments of a specific relic** in the illustrated picker. Every current relic is offered in both forms, clearly labeled and using the shared game portrait. Both forms coexist with ordinary items and random fragments in the same weighted pool. Weight determines each entry’s chance per draw, and quantity determines the number of whole relics or fragments awarded.

**Details** opens the full editor, including direct resource rewards, crystals, charms, defaults and the existing drop preview. Dungeons, chests and expeditions keep their full editors because their reward rules include weighted pools or additional settings.

## Mutation contract

The authenticated `POST /admin/action/reward-batch-save` uses the existing CSRF check, superadmin role, operation receipt, reward lock and audit transaction. `updates_json` is a list of source keys, revisions, inherited global revisions and changed `rows`/`fragment_rows`/`relic_rows` groups. Whole relic targets use `relic:CODE`; specific fragments use `treasure:CODE`, random fragments `fragment:GRADE`. Values are validated with the authoritative reward catalog. Duplicates, unsupported types, stale revisions and invalid rows reject the entire batch, including receipts and audit entries. A repeated successful operation returns its receipt without another revision. A failure also clears the runtime reward cache.

World rules retain separate revisions. If a world rule inherits its global rule, the submitted global revision must still match. Failed input is retained as a bounded draft and can be discarded with **Reload saved values**. Leaving with pending edits prompts before navigation. Saving remains explicit.

## Verification

- `php tests/reward_admin.php`: source validation, consumers, batch rollback, receipt replay, role enforcement, world isolation and runtime cache.
- `php tests/relic_drop_rewards.php`: exact whole/specific-fragment chest rewards, bulk receipt replay, unlock/duplicate behavior, validation and frozen solo returns. `tests/monster_rallies.php` and `tests/gathering_lifecycle.php` cover whole relic returns, failure/recall cases and no duplicate credit.
- `php tests/reward_preview.php`, `php tests/item_sources.php`, `php tests/reward_presentation.php` and `node tests/reward_catalog.cjs`: separate whole/fragment identities, chances, metadata and localized reward labels.
- `php tests/reward_admin.php --browser`: modern navigation, batch saves, existing detail forms, item picker, reset, retained input, moderator access, localization and responsive layouts against a disposable database.
- `ADMIN_MODERN_ONLY=1`: focus on direct tables, navigation and malformed draft recovery.
- `ADMIN_FRAGMENTS_ONLY=1`: focus on full mine/monster fragment editors.
- `ADMIN_RELICS_ONLY=1`: whole relic batch/detail saves, specific and random chest fragment choices, picker boundaries, four languages and desktop, narrow portrait and landscape layouts (`tests/admin_relic_drops.cjs`).
- `tests/fantasy_theme_app.cjs`: independent administration appearance while game theme assertions remain intact.

Browser references: `output/playwright/admin-modern/` and `output/playwright/admin-relic-drops/`.

The general `tests/localization.php` parity check still reports 240 pre-existing missing French `talents.*` keys (also present in HEAD on 6 October 2026). All new relic labels and placeholders are present in English, German, French and Luxembourgish.

Game reward receipts and monster reports passed five desktop, portrait and landscape sizes, including whole/fragment distinctions, duplicate conversion amounts, receipt replay and direct report links (`tests/reward_dialog_app.cjs`, `tests/monster_report_app.cjs`). The focused `tests/relic_sources_app.cjs` checks whole relic quantities and the shared relic name in three screen sizes. Captures are under `output/playwright/relic-rewards/`, `relic-monster-reports/` and `relic-sources/`. The broader `tests/item_sources_app.cjs` currently stops at its 1280×800 rally window containment/send-button assertion, on a target with eight ordinary item drops plus crystals and no direct relic rewards; this change does not alter march layout CSS.
