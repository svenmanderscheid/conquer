# Password reset

The email flow is `/auth/recover` → emailed `/auth/reset?token=…` → new password → login. The configured `base_url` determines the email destination; request headers cannot replace it.

## Retry fix — 10 October 2026

A new request previously marked every earlier reset link as used before attempting mail delivery. A delayed email or a failed retry therefore left the player with an unusable link, even within its advertised 30-minute validity. Read-only production inspection confirmed that a request at 08:41:58 UTC invalidated the preceding 08:37:51 UTC link at exactly the same time. This establishes the premature invalidation; the server's mail invocation log does not establish inbox delivery.

Requests now retain earlier links until their expiry or a successful account change. If mail handoff fails or throws, only the newly created, undelivered token is removed. The public response remains identical for known and unknown addresses. Transport exceptions are logged by class only, without recipient addresses or reset tokens.

A successful reset still atomically consumes every outstanding link, invalidates recovery codes, verifies the mailbox and revokes existing player sessions. Password and email changes also retain their existing token invalidation. The 30-minute expiry and per-IP/per-account request limits are unchanged. No database migration or user-interface change is required.

## Focused verification

- `php tests/password_reset_delivery.php`: repeated requests, delayed/out-of-order mail, failed mail handoff, transport exceptions, and invalidation after use.
- `php tests/password_reset_http.php`: the actual recovery and login routes, CSRF, unknown addresses, expiry/reuse, session revocation, and login using the new password.
- `php tests/security_accounts.php`: existing account-change and token security checks.

These suites use disposable local databases and a mail sink. They do not send email or modify production players. Production mailbox delivery must be checked separately; PHP mail handoff alone is not proof of receipt. Previously invalidated links stay invalid and require a fresh request.

The retry regression failed against the original service and passed after the correction. All three suites passed against an isolated copy of the committed baseline plus this fix, excluding unrelated working-directory changes.
