# Union of Kingdoms — Community upgrade

Implemented locally on 30 September 2026. The shared web frontend remains the basis for the future Capacitor apps. New interface text uses the common English catalog with `social.*`, `social_chat.*` and `alliance_community.*` keys; player names and messages remain plain, untranslated user content.

## Player entry points

| Route in the main app | Purpose |
| --- | --- |
| `/city#community` | Community overview, persistent conversations, friends and player search, official news, privacy and notification settings. |
| `/city#alliance-community` | Alliance recruitment, applications, announcements, shared goals, calendar, attendance and polls. |
| `/city#alliance-tools` | Existing alliance help, research, buildings, members, diplomacy and deliveries. Existing `community-open` actions now route here. The mail action opens the shared mailbox. |
| `/city#alliance` | Alliance overview for members; direct discovery, join/application actions and incoming invitations for players without an alliance. R4/R5 find recruitment and invitations under Members and More. |
| Chat dock and player profiles | World, alliance and private chat with the same conversation state as Community. |
| `/admin/chat` | Player reports, temporary chat restrictions, official news and the world-chat log. |

The Community overview links into existing mail, chat, alliance and help surfaces. It does not replace the existing alliance economy or mailbox services.

## Contacts, conversations and chat

- Search players in the current world, request friendship, accept or decline requests, cancel outgoing requests and remove friends. Blocked players can be managed from Community settings.
- Private conversations are stored on the server. Existing message history populates the conversation list; opening a new conversation explicitly also creates a durable entry.
- Read cursors are scoped to player, world and channel or private partner. Merely loading the chat preview does not mark messages read. An expanded chat acknowledges messages when the reader reaches the bottom. Reloading or using another device keeps the same unread state.
- World and alliance messages from players blocked by the viewer are excluded before pagination. Private history stays readable for reporting evidence after blocking; sending and reacting are restricted server-side.
- Avatars and names open player profiles. Replies quote an authorized message. Mentions use a participant picker with at most five server-validated player IDs. The supported reactions are Like, Love, Laugh and Celebrate.
- Older messages load in pages of 50. Replies, reactions, report links and shared map locations remain available in history. Reaction and pin mutations return the updated message so older loaded messages update immediately.
- Officers, vice leaders and leaders may pin or unpin up to five alliance-chat messages.
- Touch-accessible message actions include replying, reacting, reporting and blocking. Reporting offers a reason and optional details. The backend stores the reported content as a moderation snapshot.

Private-message permissions are `everyone`, `friends`, `alliance` or `nobody`. Notification choices are `all`, `mentions` or `off` for each of world, alliance and private chat. These preferences are saved per world and player and synchronize across devices. Channel unread counts respect notification preferences; individual conversation rows retain their actual unread count. These are in-game notifications, not native push notifications.

## Alliance organization

Recruitment can be open or application-based. Alliance search supports language, preferred activity period, play style, recruitment mode and minimum power. Recruitment stores an IANA time zone. Existing join entry points enforce the same admission rules, including capacity, world membership and minimum power.

Leaders and vice leaders manage recruitment and applications. Officers and higher ranks can create announcements or shared goals, pin or archive notices, complete goals, create or cancel events and create or close polls. Members can respond to events and vote. Authorization is checked again on the server for each mutation.

### Invitations and direct discovery (9 October 2026)

Players without an alliance enter discovery directly from the main Alliance button. Incoming invitations identify the alliance, sender, member count and expiration, with explicit Accept & join and Decline actions. Open alliances offer direct joining; application alliances retain their application and approval workflow. Advanced discovery filters are collapsible so that the search and results remain easy to reach on phones.

R4 and R5 members can search unallied players in their current world by name or exact player ID, send an invitation and withdraw a pending invitation. Invitations last seven days; sending one does not change membership. Acceptance authorizes entry into an application alliance, while current capacity, minimum power, world access and existing membership are checked again. Joining or founding an alliance closes other pending invitations. A repeat of the same request returns its saved result. The retired `/api/alliance/join` entry remains blocked with HTTP 410; active Kingdom and Community join paths enforce the admission rules.

The public profile of an unallied player also offers **Invite to alliance** to R4/R5 viewers. It sends directly from the profile, disables repeated clicks while waiting and shows **Invitation sent** on success. Own profiles, allied players and viewers without invitation rights do not show the button. A retry after a lost response retains the original request ID; a stale profile from another world cannot send an invitation.

`GET /api/community/alliance` additionally returns `invitations`, leadership-only `sent_invitations` and `invite_candidates` filtered by `invite_search`. The existing action endpoint accepts `invitation.send` with `player_id`, or `invitation.accept`, `invitation.decline` and `invitation.revoke` with `invitation_id`. All use the existing authentication, CSRF and request receipts. Only the recipient can accept or decline; only the sending alliance's R4/R5 can revoke.

The additive migration `0141_alliance_invitations.sql` creates only the invitation table. `tools/migrate-alliance-invitations.php --apply` installs this migration in the local database without running other pending migrations. Deployment must include the migration before the updated PHP read model is used. New text is supplied through `alliance_community.*` in EN/DE/FR/LB; English remains the default and fallback.

Focused checks: `tests/alliance_invitations.php`, `tests/alliance_admission_http.php`, `tests/alliance_community.php`, `tests/alliance_ranks.php`, `tests/alliance_community_panel.cjs` and `tests/alliance_recruitment_app.cjs`. The browser test uses the real `/city` shell with controlled recruitment responses and a disposable database; the PHP tests verify actual invitation state, authenticated HTTP requests and concurrent capacity checks. Browser captures are stored in `output/playwright/alliance-recruitment/`. These checks do not constitute native device or production verification. The local invitation migration was applied on 9 October 2026; this does not deploy it to the live server.

Calendar events have a title, description, start time, duration and time zone. Attendance is Yes, Maybe or No. Upcoming events to which the player replied Yes or Maybe receive an in-app reminder in the alliance overview during the preceding 24 hours. Choice and time polls support one changeable vote per member. No automatic Discord posting, external-calendar synchronization or native reminders are implied by these features.

## Moderation and official news

The existing admin chat page now includes reports with New, Reviewing, Resolved and Dismissed states, internal notes and captured message content. Admin operations use the existing authenticated, CSRF-protected, receipt-backed audit path. Community moderation and official-news mutation permissions are checked by the backend.

Temporary chat restrictions are scoped to the selected world and can be lifted. They are distinct from the existing account ban. The world-chat log remains available for review. Official news can be drafted, published and edited in the admin page; the in-game News tab lists published posts for the player's world, plus any global posts stored by the server.

## Interfaces and implementation

| Interface | Role |
| --- | --- |
| `GET /api/community/social` | Friends, requests, blocks, preferences, conversations and optional player search. |
| `POST /api/community/social-action` | `friend.*`, `block.*`, `preferences.save`, `conversation.open`, `chat.read`, `chat.react`, `chat.pin`, `report.submit`. |
| `GET /api/community/chat` | Existing live chat snapshot, enriched with durable conversations, read cursors, notification counts, preferences, pins, roles, reply and reaction data. |
| `GET /api/community/history` | Authorized older messages using `channel`, optional private `player_id`, and `before_id`. |
| `POST /api/community/action` | Existing chat sending and alliance actions. `chat.send` additionally accepts `reply_to_id` and `mention_ids`. |
| `GET /api/community/alliance` | Recruitment search and alliance planning state. |
| `POST /api/community/alliance-action` | Recruitment, applications, notices, goals, calendar attendance and polls. |
| `GET /api/community/news` | Published news and an optional validated Discord invite. |

New POST requests include the current `world_id`, `expected_world_id` and a request ID, together with the existing CSRF header. Request IDs are retained for retries after lost responses. World membership, message visibility, peer identity, roles and trusted game values are checked on the server. GET requests do not acknowledge messages or trigger gameplay actions.

The principal frontend files are `assets/js/social-hub.js`, `assets/js/world-chat.js` and `assets/js/alliance-community.js`, with corresponding feature styles. `assets/js/game.js` connects them to shared navigation and mobile page history. `assets/css/village-theme.css` remains the final source of common materials, colors and type.

The chat exposes `update`, `open`, `close`, `openPrivate`, `openChannel`, `refresh` and `syncBadge`. Its context accepts `openPublicProfile`, `openSharedReport`, `openSharedLocation`, `onOpen` and `onClose`. Navigation closes use `close({navigating:true})` to avoid a duplicate mobile Back action.

Community settings and search drafts survive game refresh, explicit refresh and tab changes. Chat drafts, replies and mentions are scoped by world and channel. Responses from a previous world or conversation do not replace the active view. A failed send or report retains the user's input; a report dialog closes only after a successful mutation.

## Database and local rollout

Three additive migrations are required:

| Migration | Data |
| --- | --- |
| `0121_community_social.sql` | Friends, blocks, preferences, conversations, read cursors, message metadata, reactions, pins, reports and chat restrictions. |
| `0122_alliance_community.sql` | Recruitment fields, applications, notices, events, attendance, polls, votes and alliance operation receipts. |
| `0123_community_news.sql` | Official-news drafts and published posts. |

`tools/apply-community-migrations.php` checks only these migrations. Without `--apply` it reports their status. With `--apply` it applies only the missing feature migrations and records them. This helper deliberately accepts only a local database host; deployments must use the normal server migration process.

The local status check on 30 September 2026 reported all three migrations already applied. This confirms the local development database only. Production deployment is not established by this document. Browser integration tests use the isolated database created and cleaned up by `tools/preview-feature-fixture.php`; synthetic test players and reports are not live-player content.

## Verification

| Check | Coverage |
| --- | --- |
| `tests/community_social.php` | Social state, privacy, blocking, read cursors, scoped history, mentions, reactions, moderation and replay protection. |
| `tests/alliance_community.php` | Recruitment/application permissions, shared goals, events, attendance, polls and scoped mutations. |
| `tests/community_news.php` | News validation, permissions and Discord invite handling. |
| `tests/community_chat_app.cjs` | Persistent conversation and read state; history; escaped content; replies and mentions; current and older-message reactions; pins; reports; blocks; settings; lost-response retries; profile/report/map navigation; world races. Four desktop/mobile layouts and touch reachability. |
| `tests/social_hub_app.cjs` | Polling preserves the focused input node and drafts; refresh/tab draft retention; preferences retry; player search; friend request/cancel/accept; failed-report retention; stale-world isolation. |
| `tests/alliance_community_panel.cjs` | Preserved drafts and expanded forms; lost-response request receipts; automatic alliance discovery; cache refresh; pending reads during a world switch. |
| `tests/community_upgrade_app.cjs` | The actual main app with an isolated fixture: 20 Community and 20 alliance layouts, HTTP CSRF/world validation, private chat persistence, reporting/profile navigation, saved preferences, recruitment, goals, event attendance and poll voting. |
| `tests/community_panel.cjs` | Existing 36 alliance-tool layouts and legacy action/draft/retry behavior under the intentionally renamed `alliance-tools` route. |
| `tests/alliance_overview_app.cjs` | Existing alliance overview, all eight original destinations, leader/power display and touch geometry. |
| `tests/world_chat_app.cjs` | Original two-message preview, swipe behavior, chat channels, notification settings, private send and responsive height. |

The final main-app run passed all 40 Community/alliance layouts and the persisted settings, private conversation, report, profile, recruitment, goal, attendance and poll flows listed above. The additional alliance-panel regression fixture also passed. Community selected tabs use the central gold/inset rule; the hub fixture checks the computed background, border and inset marker under the main-app theme.

Screenshots from the new fixtures are under `artifacts/community-upgrade/` and `artifacts/community-upgrade/qa/`. A fixture test is not an actual-device performance check. No physical iOS/Android device validation or store build is claimed here.

## Discord rollout

The **Union of Kingdoms** Community server was created and Community features enabled with the owner's explicit approval. Server ID: `1554839128896573480`. The owner can open its welcome channel at https://discord.com/channels/1554839128896573480/1554843189469380619.

Configured channels: `start-here` (rules and welcome), `announcements` (official updates), `general`, `deutsch`, `help-and-guides`, the forums `bug-reports`, `suggestions` and `alliance-recruitment`, the private `moderator-only` channel, and the voice room `Tavern`. Welcome/rules, opening announcement and help/German introductions are published. The forums contain posting guidelines and topic tags. Rules and announcements deny member posting; their thread and external-app posting overrides are also denied.

The owner's follow-up visual refresh added matching emoji prefixes to all ten channels and organized the public channels into `🏰 WELCOME`, `🍻 COMMUNITY`, `📚 HELP & FEEDBACK` and `🔊 VOICE`. Existing channel permissions were retained during the moves. The pinned welcome message now includes an English first-steps guide, direct channel references, the game link and clearly separated community rules. The verified server preview is saved in `artifacts/community-upgrade/discord-emojis.png`.

Community setup requires verified email and filters sensitive media from all members. Discord reports all three raid protections and all five DM/spam protections enabled. Mention-spam blocking is enabled; the additional `Block suspicious spam` AutoMod rule was saved. No bot or webhook credentials were created.

Pending owner confirmation: grant the unassigned `Moderator` role only message management, message pinning, thread/post management and member timeouts; create/use a permanent public invite with unlimited uses and publish it in the game. Automatic approval review declined these two access changes because the general setup request did not explicitly identify the permission/public-access scope. The role name is saved with no extra permissions, and the game's invite remains unconfigured until approval. No role has been assigned to another person.

The game supports a public invite configured with `CONQUER_DISCORD_INVITE` or `data/community-discord.json` (`invite_url`). Only HTTPS Discord invitation URLs are accepted. An absent or invalid invite hides the join link. This public link is independent of the existing Discord OAuth sign-in configuration. OAuth support and an invite setting alone do not establish that a community server has been created or configured.

### Bot setup preparation — 1 October 2026

The owner requested the recommended bot setup and separately authorized development of the custom minigame bot. Two optional roles, `Event Notifications` and `Playtester`, were created with no additional permissions and no members. They are not proof of an automatically functioning role picker.

The following channels were created and verified in Discord:

| Channel | ID | Current state |
| --- | --- | --- |
| `🎭・choose-roles` | `1555153994056343663` | In WELCOME; English topic; ordinary member messages and thread creation/replies denied. Carl role-picker message pending. |
| `🎫・support` | `1555154100717617213` | In HELP & FEEDBACK; English topic explains setup is pending and directs confidential requests to the owner meanwhile. Ordinary member messages and thread creation/replies denied. Ticket panel pending. |
| `📅・events` | `1555154183093620756` | In COMMUNITY; English topic for playtests and community events. No event or notification subscription was created. |
| `🎮・minigames` | `1555154319186198548` | In COMMUNITY; English topic explicitly says minigames are in development and resource rewards are inactive. |

Official invitations for Carl-bot, Ticket Tool and sesh were prepared for this server, with Administrator absent. Carl's server/channel administration, kick/ban, nickname, webhook, message deletion, mass-mention and voice permissions were deselected. Its remaining permissions are Manage Roles, View Audit Log, View Channels, Send Messages, Embed Links, Attach Files, Read Message History and Add Reactions. Ticket Tool requests Manage Roles/Channels/Messages and ordinary channel messaging/file/history permissions. Sesh was limited to channel messaging, event creation/management, embeds/files/history, reactions and application commands; role management, mass mentions and thread management/creation were deselected.

Installation and dashboard authorization remain pending explicit confirmation of these persistent accesses. No bot is installed by this preparation, no paid subscription was purchased, and no bot credentials were created. Before continuing, inspect the live integrations and authorization screens again; do not assume a prepared invitation was approved. Carl will need an explicit Send Messages override in choose-roles, Ticket Tool in support, and a bot-specific private logging/ticket configuration. Do not expose existing private staff channels as a shortcut.

The separately requested first-party bot is implemented locally; setup, deployment boundaries, player flow and validation are documented in `DISCORD_MINIGAMES.md`. That code does not by itself register a Discord application, deploy the game changes or activate live rewards.

### Confirmed bot configuration — 1 October 2026

This is a later status update to the preparation record above. Carl-bot, Ticket Tool and sesh are now installed on the **Union of Kingdoms** server. Installation alone does not mean that each dashboard or feature has been fully configured.

#### Carl-bot role selection

Carl-bot's role is directly below `Moderator` and above `Event Notifications` and `Playtester`. Carl-bot has an explicit Send Messages allowance in the otherwise read-only `🎭・choose-roles` channel.

The normal-mode reaction-role panel was published and pinned in `🎭・choose-roles`, message ID `1555162904112791622`:

| Reaction | Optional role |
| --- | --- |
| 🎉 | `Event Notifications` |
| 🧪 | `Playtester` |

Both role assignments were tested in Discord, including holding both roles simultaneously and removing each role by removing its reaction. After verification, the owner's account again held neither optional role.

#### sesh event configuration

The owner separately approved sesh dashboard authorization with its additional email scope. The following settings were saved:

| Setting | Saved value |
| --- | --- |
| Server time zone | `Europe/Luxembourg` |
| Time format | 24-hour |
| Timestamp display | Discord timestamps; legacy timestamps disabled |
| `/create`, `/ai`, `/delete`, `/poll` | `Moderator` and administrators |
| `/list`, `/link`, `/remind`, `/timestamp` | Everyone |
| Manager and log access | Administrators only |
| Default event channel | `📅・events` |
| Default poll channel | `📅・events` |
| Only Admins Can Override | Enabled for event and poll channels |
| Require Send Messages Permissions | Enabled for event and poll channels |
| Mentions On Create | None |
| Mentions On Start | None |
| Allow Anyone To Add Poll Options | Disabled |

No event was scheduled. These saved mention defaults do not establish a server-wide change to members' personal DM preferences.

#### Private moderation logging

A new private `moderation-log` channel, ID `1555165319608271019`, was created with access for `Moderator`, Carl-bot and the owner. The following Carl-bot logging selections were saved:

- Enabled: channel creation, updates and deletion; role creation, updates and deletion; server updates; member role changes; bans, unbans and timeouts.
- Disabled: message copying, join/leave messages, name/avatar changes, voice activity and thread logs.

Carl-bot confirmed both `/log channel` and `/modlog set` with `📋moderation-log` as their saved destination. A live delivery test passed: changing this channel's topic produced a Carl-bot `Text channel updated` log at 12:35, showing the previous topic as None and the new description as After. This verifies delivery of a channel-update log; no actual moderation penalties were applied or tested.

The Carl-bot dashboard was subsequently read back: the new moderation log channel is selected as Default, the other destination selectors remain None and inherit that default, and the saved logging selections above were confirmed again.

Carl-bot AutoMod was not changed. Its dashboard showed Spam, Mention, Attachment and Link controls as Disabled; these Carl-bot settings are separate from the existing Discord AutoMod rules described above.

#### Remaining setup and minigame status

The owner approved Ticket Tool's additional email scope, and its dashboard authenticated as Ekki for Union of Kingdoms. No further OAuth approval is pending.

The private `🔒 SUPPORT TICKETS` category, ID `1555166603958816889`, was created. Carl-bot's log confirmed View Channel and Connect denied for `@everyone`, and allowed for `Moderator` and Ticket Tool, with no other role overrides. Ticket Tool also received an explicit Send Messages allowance in the public `🎫・support` channel, ID `1555154100717617213`; Carl-bot logged that saved change at 12:38.

Panel 1, `Union of Kingdoms Support`, was created in the dashboard. `Moderator` was selected as its Support Team and `SUPPORT TICKETS` as its private ticket category. English panel and ticket descriptions were entered. The following defaults were verified in the dashboard:

| Setting | Observed value |
| --- | --- |
| `@everyone` View Channel | Denied for opened and closed tickets |
| Support Team View Channel / Send Messages | Allowed for opened and closed tickets |
| Ticket owner View Channel / Send Messages | Allowed for opened tickets; closed tickets inherit the category denial |
| Two Step Close / Two Step Ticket | Enabled |
| Open tickets per user | 1 |

Support Team Only was enabled for Reopen, Delete and Transcript, and Save was executed. Send to the public support channel was then executed, but repeated browser-tool timeouts prevented readback. Publication, persistence of all final values and the complete ticket workflow remain unconfirmed. The support-channel topic still contains its setup placeholder. The current blocker is the browser connection, not authorization; inspect the dashboard and channel before retrying publication to avoid duplicate panels.

The custom minigame code is unchanged by this configuration work. Its previously reported 54 backend checks remain the recorded validation result; they were not rerun for this documentation update. A read-only local preview check passed with PHP 8.2.12, Sodium, PDO MySQL and cURL available. The feature remains locally disabled and has not been deployed, so live minigame resource rewards are inactive. See `DISCORD_MINIGAMES.md` for the implementation and activation requirements.

### Verified ticket workflow and first-party app — 1 October 2026

This update supersedes the pending-publication status above. Browser control recovered after resetting its session. Three Ticket Tool panel messages were present in `support`: `1555170099470667777`, `1555173634853703733` and `1555176236312166471`. The newest panel was updated in place with the English support description. The two older copies remain; permission to permanently delete those messages was requested but has not yet been received. Do not send another panel.

The panel and private ticket descriptions are now saved and verified in Discord. In this dashboard, filling a text field alone did not update its preview; a keyboard edit triggered the change before saving. The support channel topic now directs players to Create ticket, states one open ticket per person and warns against sharing passwords or login codes.

The owner opened test ticket `ticket-0001`, channel `1555183270952701963`, through the panel. It appeared under `SUPPORT TICKETS` with the intended greeting. Actual channel permissions were inspected: `@everyone` View Channel is denied, `Moderator` View Channel is allowed, and the access list contains Moderator, the ticket owner and Ticket Tool. Both closing steps succeeded and the bot changed the channel to `closed-0001`, with a Ticket Closed receipt and support-team controls. The closed test channel was preserved. This was an owner-account workflow test, not a separate ordinary-member login test. The saved Delete button's Support Team Only flag was also read back as enabled.

The owner explicitly approved Discord's developer terms/policy and completed the human-verification step. First-party application `Union of Kingdoms` now exists with application ID `1555183090434056262`. Its English description was saved; User Install was disabled, Guild Install remains enabled with only `applications.commands`. Discord confirmed authorization and installation on guild `1554839128896573480`. No Administrator or privileged gateway intents were enabled.

The owner generated the bot token and entered it into ignored local `config/discord.php`. A read-only request to Discord's official bot application endpoint returned HTTP 200 and matched the configured application ID. The token was not printed or copied into documentation, the release checkout or source control. The local feature remains disabled. Commands and the public interaction endpoint have not yet been configured.

An isolated release checkout was prepared at `C:/Users/svenm/.codex/worktrees/discord-minigames-release/conquer`, based on `38e081fbcabd59321d2c4b5ce29fddecb428555d`. It contains only the Discord integration plus the minimal English-default/fallback correction needed by the new account panel. All 54 backend checks passed on this isolated composition; five additional language-selection checks, syntax, offline registration preview and diff checks passed. Read-only browser checks at 390x844, 320x568, 568x320 and 1280x800 found no dialog overflow and 44px action heights. The linked-world HTML test string remained plain text. An additional fixture Create linking code click was rejected by automatic approval review, so that browser click was not performed; the independent backend tests remain the behavioral evidence. No commit, push, live deployment or production migration has occurred.

Hostinger readback: `play.unionofkingdoms.com` maps to `/home/u171686647/domains/unionofkingdoms.com/public_html`; the existing Git deployment uses `git@github.com:svenmanderscheid/conquer.git`, branch `main`, install path `/`. The actually deployed revision and migration state still need verification. PHP 8.3 is selected, PDO is enabled and cURL is built in; Sodium is currently unchecked. SSH is INACTIVE with existing authorized keys. Temporary SSH activation for inspection/deployment, followed by disabling it again, was requested and is awaiting the owner's answer. No Hostinger settings were changed. Do not upload the original dirty working tree or blindly run every pending local migration.

- The corrected support panel (message 1555176236312166471) was pinned at 14:05 Europe/Luxembourg; Discord's pin receipt was verified. The two older duplicate panels remain unchanged pending deletion approval.

### Minigame bot live — 1 October 2026

The custom Union of Kingdoms app is deployed and enabled. Discord validated the HTTPS endpoint and /link plus /expedition are registered in the guild. A real signed /expedition request from the verified unlinked account returned private linking instructions; no expedition or reward was created. The minigames channel topic now explains the live flow. Hostinger Sodium is enabled and SSH was deactivated afterward, confirmed INACTIVE. See DISCORD_MINIGAMES.md for the exact artifact, backups, migration and verification record. The user's game account still needs its private linking code; no account was linked during setup.

