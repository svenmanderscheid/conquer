# Operational administration

The approved administration expansion of 10 October 2026 uses the existing independent neutral admin design. It does not redesign the public website or painted game interface. All work described here is local until published.

## Workspaces and navigation

The overview prioritizes activity analysis, with economy/drops and stability one click away. Four headline cards show online players, active players, open role-visible cases and players affected by recorded technical errors. Current urgent cases, invalid rewards, grouped incidents and overdue scheduled spawns appear beside the analysis. World state and recent actions follow below.

The selected world and rolling UTC period carry into drilldowns. A session counts as online only when unexpired and active within five minutes; its active world is authoritative. Activity counts distinct players, including across worlds, and compares against an equal preceding period. Chart buckets use two-hour boundaries for 24 hours and UTC days for longer periods, with partial edge buckets. Registration counts describe account creation among players with a city in the selected world, not new entries into that world. Open cases and online players are current state, not historical period counts.

`/admin/activity` offers event history and separate active/online/new-account lists. Event filters combine player, world, category, result, action name, request or operation identifier and time period. Fifty-row cursor pages preserve filters. Safe action names make generic successful API responses distinguishable. Event details and return links preserve the investigation context. Player profiles link to their activity, connection observations, reward grants and cases.

`/admin/technical` groups stored errors and selected connection/reward incidents, then opens the relevant observations and safe metadata. Client reports are labeled observations and are not evidence of a completed game action. A failed network request, browser offline notification and confirmed subsequent response are distinct signals. The dashboard failure metric excludes browser offline notifications. Duration percentiles cover recorded server actions only. No percentage of lost connections is calculated without a complete session/request denominator.

`/admin/analytics` shows scoped monster defeats, PvP reports, message counts and completed gathering returns. The gathering resource totals are not a complete economy ledger or net inflation measure. Choosing one world enables the existing alpha/onboarding analysis. Actual credits and rejected reward attempts have their own pages under Drops & rewards; editing rules remains separate from these histories.

## Team access

Superadmins retain settings and grant privileges. Support staff enter the bug/support inbox; moderators handle reported contents. Case evidence is limited to the authorized case's player, world and a bounded time window, with uncertain relationships explicitly labeled. Support does not receive general browsing access to player inventories, messages or technical logs. Public replies and private team notes are separate records. Assignment, status, notes and moderation use existing authenticated, CSRF-protected transactional operations and audit receipts.

## Availability and retained history

Apply migrations `0142_observability.sql`, `0143_team_reports.sql` and `0144_reward_ledger.sql` together with the matching code. Tests apply them only to disposable schemas. No existing local gameplay database or live installation is migrated automatically by these changes.

Unavailable telemetry is shown as unavailable rather than zero. The first and last retained records describe currently retained data, not guaranteed instrumentation start or continuous service health. Private fallback files generate an all-world indexing-gap warning; they are not included in central metrics until reviewed/imported. Error collection, bounded queues and crash limitations are documented in [OBSERVABILITY.md](OBSERVABILITY.md). Retention is an explicit maintenance command, not an installed schedule. Existing historical actions or rewards are not fabricated or backfilled.

## Verification

- `php tests/admin_operations.php` checks world and period isolation, distinct players, matching drilldowns, business rejection versus errors, connection semantics, action search, cursor pagination, completed gathering, missing instrumentation and fallback visibility.
- `php tests/admin_operations_browser.php` checks the real rendered overview, filters and drilldowns, all three analysis tabs, technical details and world-specific analytics in desktop, portrait and landscape sizes across English, German and French. Screenshots are under `output/playwright/admin-operations/`.
- Reports, observability, reward ledger and existing direct drop/world editors have separate isolated regressions described in their feature documentation.

Mobile tables retain readable column widths inside labeled, keyboard-focusable scroll regions. Browser scrollbars remain hidden while touch, wheel and keyboard scrolling remain available. Browser emulation does not replace a physical-device check.
