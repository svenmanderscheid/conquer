# Item and relic fragment sources

The inventory inspector offers **Find sources**, including catalog items with zero stock. Every relic inspector and fragment exchange offers **Find fragments**, including locked relics with zero fragments. The result opens in the shared game dialog and uses the common mobile page frame.

`GET /api/item-sources` requires a player session and exactly one of `item_code` or `treasure_code`. Optional `expected_world_id` rejects responses for an outdated world selection. The endpoint uses the authenticated player's current city and world. It never creates a gameplay record, claims a chest, advances an encounter, buys an offer or sends an army.

`ItemSourceService` derives sources from the rules that actually award rewards:

- Monsters use `MonsterData::get`, including effective global/world reward overrides and historical spawn aliases. Only living, unexpired monsters in the current world and open land receive a map destination. Results use the nearest eligible target. An available map location does not promise a combat victory or sufficient action points.
- Alliance Badges (`119000002`) have a 50% default drop chance from rally monsters: 2 at levels 1–3, 3 at 4–6, 4 at 7–9 and 5 at level 10 on a successful roll. Their source entries show the actual quantity and chance from the effective reward rules. Each victorious participant, including the captain, rolls independently and receives any earned badges at return; Alliance Coins are a separate item.
- Chests use effective weighted reward tables. A displayed percentage is per independent draw. A direct random fragment includes the probability of selecting the requested relic from the active rarity pool. Fragment packs show both the pack's acquisition chance and the separate relic selection when opening it. Free-chest timers and current inventory stock control availability; platinum and owned inventory chests open the corresponding inventory item.
- Dungeons use the weekly rotation and current reward rules. Rewards describe a normal victory without side-room or gathering bonuses. Default item selection follows the same player-specific selection as settlement; configured overrides use their weights. Role specialization, free troops and an existing active group are checked.
- Expeditions show only configured matching item drops, their castle/chapter conditions and coalition requirements. Existing encounters retain their saved rewards.
- Caravan and VIP offers come from the player's current personalized shop state, including rotation, level gates, prices and remaining stock. Future or unselected caravan offers are not presented as current offers.
- Owned fragment packs also appear as inventory sources. Opening them remains a separate confirmation.

The importer mapping `source_item_map.json` and item `loot_sources` descriptions are not acquisition rules. The finder deliberately does not infer rewards from those files. The result states which source families it checks, so an empty list does not claim that no quest, event or other reward can ever award the item.

The browser rechecks the source before navigation. Closing the dialog or switching worlds invalidates pending responses. Navigation only reveals a map target or the relevant feature; it does not start gameplay actions.

Checks:

- `tests/item_sources_rules.php`: query validation, exact relic pools, fixed and random fragment packs, zero-probability/zero-quantity exclusion.
- `tests/item_sources.php`: isolated database and HTTP checks for authentication, reward overrides, live world targets, locked land, free chest prerequisites, personalized shop stock, dungeon selection and absence of gameplay writes.
- `tests/item_sources_app.cjs`: actual isolated app at desktop, 390/320 px portrait and short landscape sizes; zero-stock entry points, precise destination navigation and late-response dismissal.
