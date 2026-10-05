# Union of Kingdoms — Alpha launch configuration

Production was updated on 4 October 2026 at 21:38 UTC. The first release verified 303 installed files against SHA-256 checksums, including 222 decoded raster assets. The 190 new painted item illustrations and their common item-art integration are included. Existing worlds and player cities were preserved.

On 5 October 2026, the live admin world settings closed **Alphawelt (ID 2)** at the user's request. **World 1 (ID 1)** was already closed. **Luxembourg Alpha · 2× (ID 3)** is now the only open world and remains the registration default, with speed and gathering factors of 2. Existing player cities and progress were retained. The live game world selector confirmed both older worlds as closed and Luxembourg as open; evidence: `artifacts/alpha-2026-10-04/live-worlds-only-luxembourg-2026-10-05.jpg`.

## New player world

- World ID **3**, `alpha-luxembourg-2x`, **Luxembourg Alpha · 2×**, open and the default for new registrations.
- Luxembourg geography, 768 × 1,100 fields; build, research, training and production speed ×2, gathering ×2, haul factor 1.
- All first cities spawn around (170, 370), in Wiltz canton `08`, inside a 64-field radius. Placement checks reserve unoccupied footprints. A full or closed starting world rejects registration instead of sending players into an older world.
- 500,000 food, lumber, stone and gold. Initial storage and treasury production caps are 600,000; rewards above a production cap are preserved.
- New password registrations receive seven days of beginner protection. Existing PvP rules remove that protection when a protected player initiates PvP.
- Illustrated guide explains Missions, Inventory, Alliance, World Map and Crystal Shop, and recommends Barracks level 2 and training 100 troops.
- Seven permanent missions per player/world reward actual completed buildings, training and promotions. Each resource payout is between 15,000 and 40,000 per resource. Claims use a city lock and a unique database marker to prevent duplicate payouts.
- Nearby level 1 resource fields include Crystal farms. Background spawns run every 15 minutes and concentrate 70% of new resource/monster positions near the starting region; Crystal weight is 10% of resource spawns.
- On 5 October, the Alpha spawn caps were reduced from 1,000 resources / 600 monsters to 250 / 150, with density targets of 0.03% / 0.018% and 30 placement attempts per run. Before expiring surplus idle targets, all world-3 target rows were copied to `ops_lux_resources_20261005` and `ops_lux_monsters_20261005`. Active gatherers, marches and rallies were excluded. The normal spawn worker removed the expired targets; live counts confirmed 250 resources and 150 monsters.
- The Luxembourg map loader was repaired on the same day: Apache now serves `.mjs` as `application/javascript`, and the hydrology import uses `v=2` to bypass cached `text/plain` responses. Live terrain and resource-dialog checks passed, along with the existing hydrology and river tests. Evidence: `artifacts/alpha-2026-10-04/live-lux-map-fixed-2026-10-05.jpg`. The browser viewport override did not take effect, so these live checks do not establish mobile layout coverage for this repair.
  - Recheck on 5 October at 06:48 UTC: fresh public requests to `play.unionofkingdoms.com` again returned `text/plain` for the hydrology module and a loader importing `v=1`. The earlier successful observation does not establish the current deployment state. The local MIME/version fix remains prepared in `output/lux-module-delivery.patch`; `tests/lux_module_delivery.cjs` verifies actual Apache headers and browser imports without accessing a player account.
- Crystal Shop uses earned game currency. Payment providers remain unavailable. Relics are accessed through the separate Treasury, and Inventory has four categories.
- Unknown or Unassigned item codes cannot enter new rewards, chests or dungeon loot. Historical inventory is preserved.

## Backups and recovery

The hosting account has a private, permission-restricted directory outside the public game root:

`/home/u171686647/uok-alpha-20261004/backups`

A minute-based hosting cron checks whether a backup is due. It creates one database snapshot per UTC hour and one full runtime/configuration/assets snapshot per UTC day. Snapshots are kept for 14 days; completed snapshots have SHA-256 manifests. The full snapshot immediately before deployment is `uok-20261004T213803Z-9616836c`. Existing provider weekly backups remain enabled.

A complete post-deployment runtime/database snapshot was confirmed at 22:13 UTC: `uok-20261004T221302Z-6107a980`. The final-manifest helper also creates a fresh paired snapshot whenever the verified release manifest changes, so a release can be restored with its matching database. This latest hosting snapshot was created successfully; its own restore-verification flag remains false.

The final snapshot, including the direct Crystal-Shop guide link, completed at **22:31:35 UTC**: `uok-20261004T223102Z-f4b1824e`. Its receipt matches final manifest SHA-256 `b4526f0d3f642c35b880668b53bcadddbcb66e0feffa3afc618fb169bf801d2c`. The final server preflight confirms 178 tables and all 306 runtime hashes. This snapshot's own restore-verification flag remains false; the separate restore exercise described below used the earlier live snapshot.

Database backups are compressed SQL; `runtime.zip` contains the matching server files and configuration. Keep both private. For recovery, stop game writes, select the matching database and runtime snapshot, check their manifest hashes, and restore both together. Do not run the restore-verification method against the source database: it only creates and removes a disposable database.

Local full-file/database restoration was verified. The live snapshot `uok-20261004T205504Z-ebf2a64e` was also restored into a separate local database: all **175 tables** were recovered. The older local MariaDB client required removing the modern dump-client sandbox comment and translating `utf8mb4_uca1400_ai_ci` to the supported `utf8mb4_unicode_520_ci` collation. The unmodified live SQL and manifest are retained in the private local backup directory; this proves a compatibility restore, not an identical-version hosting restore. The live runtime archive was checksum-verified on hosting rather than downloaded.

Private local copies: `C:/Users/svenm/.codex/backups/union-of-kingdoms/alpha`. Uploaded deployment helpers are CLI-only and are outside the public web root. No SSH access or paid hosting upgrade was enabled.

## Verification and remaining launch decisions

The audit uses disposable databases and preview accounts. Test outputs are in `artifacts/alpha-2026-10-04`. The final results are recorded separately so earlier failures remain available as diagnostic history. Actual new-player UI checks cover English default, Luxembourg/2×, starting stock, seven missions, the illustrated guide and Crystal Shop in desktop, portrait and landscape layouts. Village construction, building selection, completion indicators, reduced motion and responsive menu navigation are covered.

The first release included 303 changed runtime files and 222 decoded raster images, including 190 new painted Inventory icons. The final server preflight checked 306 files with no hash drift. Live browser checks revealed overly restrictive permissions on the three newly created public art directories; these were corrected to the normal public-directory permissions. All 22 Inventory images in the inspected live view and all guide images then loaded successfully. The deployment helper now creates public runtime directories with the correct permission mask.

Other confirmed fixes include the startup overlay's stale progress reference, correct Luxembourg map dimensions in world selection, reachable return summaries through the main menu, and a march-follow card that fits short landscape screens. Live screenshots are saved beside the audit results. The hosting Git HEAD still points to `7ad6633a2bf66b96a37449c4a25bcbab30f15298`; this was a verified file deployment, so a later Git deployment must account for the installed runtime changes.

Authentication, ownership/world boundaries, CSRF, mutation receipt replay, reward claims, upload body limits and payment unavailability have regression coverage. The isolated Apache request-limit integration passed 84 checks. The dedicated Codex Security scan could not start because its tool rejected the scope argument schema; it was not retried. These tests are not a guarantee of complete application security.

Before inviting the full Alpha group, check the first registration and verification email on an actual phone, distribute Alpha keys, and decide whether Alpha progress will be reset. Real iOS/Android devices and concurrent-player load remain unverified in this audit.

Final verification: **93/93 backend suites, 47/47 isolated UI suites, and 56/56 actual-app suites passed** (196 total). The latest result for each suite is selected by completion time in `artifacts/alpha-2026-10-04/verification-summary.json`; earlier diagnostic failures remain available. Older tests were updated for the current compact navigation, painted catalog icons, explicit language selection, preview runtime locations and fixture loading time. Changed PHP and JavaScript files also passed syntax checks.

Final live evidence: `live-final-preflight.json`, `live-final-backup.json`, `live-world-final.jpg`, `live-inventory.jpg`, `live-guide.jpg`, and `live-crystal-shop.jpg` in the same audit directory. The live browser inspected the actual main village, building actions, Inventory, guide, Crystal Shop and world selection. Browser portrait and landscape checks complement the disposable main-app tests; they do not substitute for a physical device check.
