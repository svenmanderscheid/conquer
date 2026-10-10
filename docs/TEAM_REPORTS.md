# Team reports and player conversations

The unified local backoffice at `/admin/cases` reads original `bug_reports` and `community_reports` records. Old report IDs, screenshots and `/admin/bug-reports#meldung-ID` links remain usable. No reports are copied or replaced. Migration `0143_team_reports.sql` adds the `support` administrator role, the support report type, a waiting state, assignment metadata and conversation history.

The inbox has all/mine/unassigned scopes, world/type/status/priority/search filters and server pagination. Cases show original player evidence, assignment, original screenshots, conversation and actor/time history. Statuses are new, in progress, waiting for player, resolved and closed. A player reply reopens waiting, resolved or closed cases. Explicit private notes and player replies use different forms and visibility labels.

| Role | Case permissions |
| --- | --- |
| Superadmin | All cases and existing administrative privileges |
| Support | Bug, idea and support cases, with bounded evidence inside the case |
| Moderator | Reported content and scoped chat restrictions for that reported player/world |

Support cannot enter general player administration, technical logs, world settings, layout data or link exports. Resource and reward grants keep the existing superadmin authorization. Existing generic moderation mutations also retain their superadmin gate. The narrowly scoped case entry delegates restrictions to the same authoritative community moderation service. Roles are reread on every authenticated request and mutation.

Create a support account through the existing secure CLI, for example `php bin/create_admin.php SupportName support --must-change`. The CLI reads and confirms the password without displaying it; do not pass passwords as command arguments. No accounts are created by migration.

Case writes require the separate administrator session, CSRF token, current source revision and a unique operation ID. Assignment/status changes, replies, notes, receipt and audit commit together. A stale revision rejects the write and retains the authored text. Legacy source edits also invalidate modern forms. Retry receipts cannot be reused for another payload; a failed audit rolls back the public answer as well as the state change. No live presence or automatic resource correction is implied.

Player support uses the existing report panel and adds a dedicated Support type plus My reports & support. Authenticated `/api/support-cases` reads return an explicit safe field set for that player only. The conversation excludes internal notes, assignment data, staff audit details and technical/moderation evidence. Public replies have their own CSRF and operation receipt checks; a lost response can be retried without delivering another message. The list currently shows the latest 100 cases and says so when that limit is reached.

Optional evidence panels use `operational_events` and `reward_grant_ledger`: at most 20 rows per source, for the case player and world, in the ten minutes before or after the report. This temporal match is labelled as an unconfirmed relationship. It does not imply the report caused an error or that a nearby grant satisfies the missing reward. Evidence includes identifiers, source references and confirmed quantities without exposing another player's data, full inventory, log context blobs or chat messages. Missing optional migrations are shown as unavailable; no history is invented or backfilled.

Verification on disposable local data:

- `php tests/team_reports.php --browser`: authorization, role changes, private/public separation, waiting/reopening, rollback/replay, source revisions, scoped evidence, CSRF, legacy links and EN/DE/FR responsive case views.
- `node tests/team_reports_city_app.cjs`: real `/city` player submission, real admin private note/public answer, lost-response retry and reachable desktop/portrait/landscape controls.
- `node tests/team_reports_city_app.cjs --legacy-bug`: existing bug/idea submission and diagnostic-context regression against the unified inbox.
- `php tests/admin_cli.php`: credential transport and provisioning of the scoped support role with isolated stubs.

Evidence is in `output/playwright/team-reports/`, `output/playwright/team-reports-city/` and `artifacts/bug-reports/`. These are browser checks, not physical-device approval or deployment. No live player messages, accounts or case records were changed.
