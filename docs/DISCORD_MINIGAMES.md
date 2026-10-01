# Union of Kingdoms Discord minigames

This first version is disabled until configured. It runs on the existing PHP game server using Discord HTTP interactions, with no Gateway process, message access or privileged intents. It has not been deployed or registered by the implementation task.

## Player flow

1. In the game, select the world which should receive rewards and open **Account → Discord minigames → Create linking code**.
2. In the official server, enter `/link` and paste the code into its `code` option. Responses are private. Never send the code as an ordinary message or to support staff. Codes expire after ten minutes, work once, and replacement codes invalidate older ones.
3. Use `/expedition`. Choose the orchard (food), forest (wood) or quarry (stone), then choose careful (500 guaranteed) or bold (50% 1,000; otherwise 250). No payment, troops or existing resources are spent.
4. Rewards are credited directly to the linked world's city. There is one journey per UTC day across all worlds, enforced separately for the game account and Discord account. Reopening `/expedition` resumes the current run or shows its receipt. Unfinished runs expire at 00:00 UTC; completed rewards cannot be claimed again.

The Account panel shows the linked Discord ID and destination world. Unlinking revokes future access without changing the day's limits or removing the audit trail. To change destination, unlink and relink in the new world; any existing journey still counts for that day. This association does not create/change OAuth login identities and never matches email addresses.

## Deployment checklist

1. Deploy the reviewed code and apply pending migrations with the normal migration runner, including `0125_discord_minigames.sql`. Never run the fixture tests against production; they create a separate local database. Back up the deployment database as usual.
2. Ensure PHP 8.2+, Sodium, PDO MySQL, and cURL (registration only) are available. The app needs a public HTTPS game origin; localhost is not a Discord interaction endpoint.
3. Create a Discord application in the Developer Portal. Install its application commands in the target server. `applications.commands` suffices for these commands; Administrator, Manage Roles, message content and other privileged intents are unnecessary. A bot token is needed only for the registration command below.
4. Copy `config/discord.example.php` to ignored `config/discord.php`. Set `UOK_DISCORD_APPLICATION_ID`, `UOK_DISCORD_PUBLIC_KEY`, `UOK_DISCORD_GUILD_IDS`, `UOK_DISCORD_BOT_TOKEN` and `UOK_DISCORD_ENABLED=1` in the host environment. Guild allowlist values must be strings. The intended server ID is `1554839128896573480`. Keep the token out of files served publicly, source control, logs and screenshots. Apache already denies direct access to `config/`.
5. In the Developer Portal, set the Interactions Endpoint URL to the game root plus `/api/discord/interactions`, preserving any deployment subdirectory. Signed PING requests work without a database; invalid signatures return 401. Application-command requests additionally require the configured app ID, allowlisted guild and member ID. Direct messages are rejected.
6. Preview with `php bin/discord-register.php`. Register with `php bin/discord-register.php --apply --guild=1554839128896573480`. It upserts only `/link` and `/expedition`, preserving unrelated commands. Run against a staging guild first if one is available.
7. Test with a dedicated non-production game account: link, both button steps, reconnect/retry, full storage, a paused world, and duplicate clicks. Restrict command usage to the minigames channel through Discord's application integrations permissions if desired.

## Reliability and operation

Signatures use Ed25519 over the unchanged timestamp and raw body; timestamps outside ±300 seconds fail. Keep the server clock synchronized. Interaction ID receipts, expedition progress and credits commit in one database transaction. Per-Discord and per-player advisory locks fail immediately when busy. InnoDB row waits are limited to one second in the interaction request; a busy response asks the player to retry. No external network call runs on the interaction request.

Discord requires an initial response within **three seconds**. This synchronous MVP is suitable only when the database and game host meet that budget. Verify real public endpoint latency under expected load before enabling rewards. The application has no background queue/deferred-response worker; hosts with sustained slow database calls need that extension before wider rollout. The CLI registration makes external requests only when `--apply` is explicitly supplied.

Rewards are deliberately fixed in server code and limited to at most 1,000 of one basic resource per day. They do not grant crystals, XP or premium items. Production is settled before the reward; earned loot can exceed the production storage ceiling, consistently with the game's normal reward rules. The response reports the committed reward. Banned/deleted players, missing cities and paused/closed worlds cannot play. A forged button cannot change the player, world, reward or previously selected route.

All shipped strings are in the shared English/German/French catalogues. Discord starts in English regardless of Discord/browser language; the game panel follows the player's explicit game language choice. Discord does not currently offer a separate language selector.

Monitor request latency and server error logs; logs deliberately omit raw interactions, tokens and linking codes. Inspect `discord_expeditions` for reward audits. Keep daily rows to prevent relink-based bypasses; do not prune them casually. `discord_interaction_receipts` can be purged after 30 days and expired `discord_link_codes` deleted by an operator-maintained job, since no request with an old signature is accepted. No maintenance automation is installed by this feature. Disabling `enabled` hides the Account panel and prevents all new interactions without deleting links or rewards.

## Local verification

- `php tests/discord_minigames.php`: isolated MySQL fixture, signed-request protocol, account-link expiry/reuse/conflicts, binding, daily limits, bans/paused worlds, duplicate rewards, transaction rollback, and production overflow.
- `node tests/discord_account_panel.cjs`: the real game window shell in desktop, narrow portrait and short landscape, linking/unlinking UI and disabled feature state.
- `php bin/discord-register.php`: safe command-definition preview, without a token or network request.

Protocol references: [Discord interaction overview](https://docs.discord.com/developers/interactions/overview), [responses and deadline](https://docs.discord.com/developers/interactions/receiving-and-responding), [guild commands](https://docs.discord.com/developers/interactions/application-commands).

## Live activation — 1 October 2026

The first-party app is now enabled on `https://play.unionofkingdoms.com/api/discord/interactions`. Discord accepted the signed endpoint verification. Guild commands `/link` and `/expedition` were registered and read back for guild `1554839128896573480` and application `1555183090434056262`.

Production was at `b8584532df24e2f6274329c798150a0b6fd08ecb`, with clean tracked files. A fresh artifact based on that exact commit excluded the later unrelated admin changes. Its 15 runtime/setup files were uploaded and all deployed SHA-256 hashes verified. Archive SHA-256: `923df6aaeeaeeede4bf1dd56cdbf6d3eec8be5b0adc28c617487dff8473a8dae`. This is a targeted file deployment, not a Git push or main-branch update; retain these changes during later deployments.

Files and database were backed up privately outside the web root at `/home/u171686647/uok-discord-backup-20261001T121500Z`. The database dump completed, the compressed file backup passed integrity validation, and backup permissions are restricted. The same directory holds the package, file manifest and deployment receipt. No private configuration or production data was downloaded locally.

Only `0125_discord_minigames.sql` was applied. All four new table column lists, InnoDB engines and both daily uniqueness constraints were checked before recording the migration. Existing migrations through 0107 were already applied; no other pending migration was run. Existing game data was not modified by this setup. PHP 8.3 remained selected; only the Sodium extension was enabled. The live configuration holds the public application ID, public key and guild allowlist, with no bot token. The token remains in the ignored local configuration and was used only to register the commands.

The exact production-base composition passed all 54 backend, signed HTTP, concurrency and reward checks locally against a disposable schema, plus syntax, language fallback and file-scope checks. Public smoke checks: game entry 200, disabled Discord endpoint 503, enabled unsigned POST 401, wrong method 405, private Discord config 403. The signed `/expedition` request was then tested through the real Discord client on the verified unlinked account: the bot returned its private account-linking instructions successfully. Both before and after that test, linked-account and expedition counts were zero. No daily reward or journey was consumed. Production linked-account payouts and sustained load have not been exercised; those behaviors were covered by the local tests.

An automatic approval review initially blocked the live command because a linked account could create a game action. A read-only production check established that there were zero links, codes and expeditions. The safely scoped retry was accepted and returned only the expected linking instructions.

Local release artifact: `C:\Users\svenm\AppData\Local\Temp\uok-discord-deploy-b858453-42341a4730\uok-discord-b858453-runtime.zip`. The original development checkout and earlier managed release worktree remain intact. The game Account panel provides the user's next step; create the private code there and enter it only as `/link`'s code argument.

After completion, Hostinger SSH was deactivated and the UI explicitly confirmed INACTIVE. The minigames channel topic was updated to the live account-linking instructions. Screenshots: discord-minigames-live.png and hostinger-ssh-disabled.png in the task visualization directory.

