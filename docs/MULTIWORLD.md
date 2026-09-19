# World selection and inventory protection

`WorldContext::id()` is the authenticated session's active world. Session loading
validates that the player owns a city there. Background processing uses persisted
city, march, expedition or shipment IDs; `WorldContext::run($worldId, $fn)` restores
the previous context even if the callback fails.

`GET /api/worlds/state` lists statuses, owned cities and available join/select
actions. `POST /api/worlds/action` accepts `action` (`join` or `select`), `world_id`,
`expected_world_id` and a unique `request_id` of 16–80 letters, digits, `_` or `-`.
The normal session cookie and CSRF header are required. Join and select are
atomic under the shared player lock. Duplicate requests return receipts;
replaying an old selection cannot undo a subsequent selection. An old browser
tab must send its original world with `X-World-ID` or `expected_world_id` so the
request guard can reject stale actions with HTTP 409.

Account profile, gems, VIP, items and treasures remain account-wide. Cities,
resources, building/research queues, garrisons, rankings, arena challenges,
alliances, mail/chat, shipments and expeditions follow the selected world.
Alliance membership is unique per player and world. Historic expedition rewards
and returning troops use their persisted participant/mission city. Closed or
paused worlds remain visible while new spending and dispatch are rejected.

Migrations 0072 and 0073 are additive. New playable worlds have 256×256 tiles,
one central Congress and four regional shrines. Existing landmarks are retained.

`KingdomService::state()` exposes `inventory_shop`. `inventory.buy` accepts a
catalog `item_code`, quantity 1–100 and a durable `request_id`. Prices come from
the server catalog; debiting gems, granting inventory and writing the receipt
commit together. The ordinary `inventory.use` action activates a purchased or
gifted item:

- 10102051: 8-hour scout protection, 50 game gems, stored in the active city's
  `anti_spy_until`.
- 10102061: 8-hour city shield, 100 game gems, activated through DefenseService
  with its existing hostile-march restrictions.

Repeat use extends an active duration. No protection items are automatically
granted to existing players. The legacy inventory endpoint delegates to the
same canonical transaction and rejects a city from another world.

`php tests/multiworld_integration.php` copies schema only to a disposable database
and tests session selection/replay, world joins/landmarks, two-world memberships,
resource/research isolation, chat privacy, expedition claims/returns, shop
receipts, protection scope and paused-world restrictions. No live player data is
copied or mutated. The companion backoffice and community suites use isolated
databases as well.
