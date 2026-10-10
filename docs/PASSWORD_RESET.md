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

## Missing delivery — 10 October 2026

The user subsequently confirmed that no email arrived. An explicitly authorized technical message to the operator's mailbox reproduced the actual transport failure: PHP mail returned false with `550 Sender rate overlimit - 290.8 / 24h` at 09:03:18 UTC. Setting an explicit envelope sender did not resolve it. The retry correction above fixes a separate problem; it cannot restore a quota-blocked transport.

Account mail now supports the authenticated [Hostinger Mail API](https://api.mail.hostinger.com/). `HostingerMailTransport` sends only to the provider's fixed HTTPS mailbox-send endpoint, verifies TLS, disallows redirects, bounds connection/request time, and records only generic failures and numeric status codes. There is no automatic retry or fallback to blocked PHP mail. A successful handoff requires a completed 2xx response; receiving-mailbox verification remains a separate check. Both reset and verification mail handle transport failures without throwing into registration.

Copy `config/mail.example.php` to the ignored `config/mail.php` on the server and supply the mailbox resource ID and a token restricted to that mailbox. Bootstrap merges this file into only the `mail` section; other application settings remain unchanged. Restrict the file to mode 0600. The authenticated mailbox determines the actual sender. Install and test the code before activating the private file. Removing the private override restores the previous configuration, although it does not resolve PHP-mail quota exhaustion.

Hostinger currently grants mailbox tokens SMTP/IMAP and webhook management access, including reading and deleting mail; a send-only token is not offered in its control panel. Creating and storing this access requires the operator's explicit approval. Never commit a token or include it, reset links, message bodies, or recipients in diagnostics.

Additional checks: `tests/hostinger_mail_transport.php` covers the provider request, TLS, errors, input validation and diagnostic redaction; `tests/account_mail_configuration.php` covers private configuration, legacy transport, safe failures and message validation. These tests make no external requests.
