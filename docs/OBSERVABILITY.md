# Operational diagnostics and player action history

The administration uses `operational_events` (migration `0142_observability.sql`). All times are UTC. The local implementation does not apply migrations to an existing installation or enable a scheduled job.

## Recorded signals

- API mutation outcomes after the response factory settles any command receipt transaction: `action` with `success`, `replayed`, `rejected`, or `failed`. A replay is not another successful game action. Routine reads are not stored as actions.
- Stable action identifiers, request/operation identifiers, server-authenticated player/world, response status, elapsed time and selected numeric target/result identifiers. Raw bodies and response snapshots are excluded.
- Every API response at status 500 or above is also an `error`; a normal game-rule rejection is an informational action. Responses taking at least two seconds produce `system / API_SLOW`.
- PHP warnings, handled runtime errors, uncaught exceptions and fatal errors detectable during shutdown. `Logger` warnings/errors enter the same store. PDO errors in `Connection::query/execute` are observed even when a compatibility fallback handles them. Duplicate handling of one exception is deduplicated; SQL constraints use `system / DATABASE_CONSTRAINT`.
- Browser script errors, unhandled promise rejections, resource failures, malformed responses in the common game API helper, failed network requests, timeouts, browser online/offline signals, and successful API responses after a connectivity failure. Client signals always have `origin=client` and `outcome=observed`; they are not proof of a gameplay outcome or misconduct.
- Rejected reward attempts use `category=reward, outcome=rejected` through `EventLog::record`. Committed reward amounts belong to the separate reward ledger, not these diagnostics.

An API response after an outage confirms that a response was received; it does not prove that every service or route is healthy. Browser `online` alone never creates `CONNECTION_RESTORED`. A user-requested `AbortError` is not counted as a lost connection.

## Ingestion and trust

`POST /api/telemetry` requires an existing player session and the same CSRF token used for game actions. It accepts at most ten observations per 16 KiB request and six batches per minute per player. It does not use the gameplay write budget or player gameplay lock, and works while a world is paused. The existing global IP limiter still applies.

The server selects allowed codes/severities and supplies player identity. A queued browser event is attributed to a world only if its page world matches the authenticated active world on arrival. Otherwise its world remains unknown and the original untrusted page value appears as `context.client_world_id`. Client timestamps are clamped to the last 24 hours and durations to one day. Unique event identifiers make resubmission idempotent.

The client keeps at most 50 observations for 24 hours in tab/session storage. Identical rapid repeats are coalesced for ten seconds with `context.repeat_count`; distinct error locations remain separate. Dashboard event counts count stored observations, not every coalesced repeat. Queue overflow produces `CLIENT_QUEUE_DROPPED` once sending resumes. Failed collector requests are never reported recursively. Closing the tab, unavailable storage, terminated processes, and exhausted queue bounds can still lose observations.

Server `release_id` comes from application configuration `release_id`, falling back to `version`. The browser also sends the version it loaded as untrusted `context.client_release`; this can differ after a deployment. An empty identifier means the deployment supplied no version.

## Privacy and storage

The collector has an explicit scalar metadata allowlist. It never ingests form bodies, passwords, cookies, session/CSRF tokens, private message content, screenshots, raw SQL, SQL parameters, raw browser exception messages, or full browser stacks. URL queries/fragments are removed. File locations are limited to application-relative source/asset paths. Diagnostic text sent by a browser is ignored. Existing server log messages receive redaction before the file and central sinks.

Diagnostics use an independent database connection, no gameplay foreign keys, and a short lock timeout. Consequently a rejected reward or error record survives rollback of the gameplay transaction. Failure to write diagnostics never turns an otherwise successful game action into a failed action.

If the central sink is missing/unavailable, events go to private `logs/operational-fallback-YYYY-MM-DD.ndjson`, capped at 10 MiB per day. These files are denied by `logs/.htaccess` and ignored by Git. `EventLog::fallbackStatus()` reports their count, size and newest modification time without returning contents. Fallback files are not automatically imported into the administration; their presence therefore indicates an indexing gap even after recovery. A full disk, unwritable directory or exhausted cap can lose diagnostics.

## Retention and limits

`php bin/prune-observability.php 30` removes at most 1,000 central rows older than 30 days per run. Values are bounded to 7–365 days. The command is not scheduled automatically. Fallback files need separate operator review and retention; do not delete them before reviewing an indexing gap.

These are event records, not complete server monitoring: there is no proxy/web-server log ingestion, host process supervisor, worker heartbeat, continuous connection/session denominator, universal economy balance ledger, or all-request latency histogram. The latency percentile in the administration describes recorded mutation responses only. Errors before bootstrap, killed processes and native/webview crashes may never execute a collector. Successful direct output that bypasses `Response` is not automatically an action record. Existing in-memory compatibility fallbacks may omit a non-database exception unless they explicitly call `EventLog::exception`.

## Verification

- `php tests/observability.php`: disposable MySQL and HTTP server, rollback survival, server identity, client bounds/deduplication, credentials/raw-data exclusion, caught database errors, CSRF, independent rate limit, retention and file fallback.
- `node tests/client_telemetry.cjs`: fetch/Request compatibility, timeout versus abort, recovery, no recursive ingestion, metadata privacy, queue bounds/expiry and explicit truncation.
- Existing `tests/army_receipts.php`, `tests/app_polling.cjs`, and `tests/mobile_comfort_app.cjs`: command replay/rollback, polling lifecycle, and the main app at desktop, narrow portrait and landscape sizes. Telemetry POSTs are distinguished from gameplay mutations.

`FeatureDatabase` initializes diagnostics only against its disposable schema. Tests never need to migrate or write to the user's gameplay database.
