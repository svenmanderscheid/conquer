# Union of Kingdoms loot rules

Approved on 9 October 2026. These are the shipped global defaults. Saved global and world overrides retain the existing precedence; there were no saved overrides in the local database when this change was installed. The online server has not been deployed by this task.

| Source | Reward rule |
| --- | --- |
| Every monster, including rallies and goblins | One random grey or blue relic, equal rarity shares, 1–5 matching fragments. Combined chance increases linearly from 20% at level 1 to 80% at level 10. |
| Ordinary solo monsters | 10% for one blue chest. Existing training/healing speedups, resources and solo crystal rewards remain. |
| Rally monsters | 40% for `ceil(level / 2)` alliance badges (1–5); independently, 50% for one energy bottle worth **10 action points**, 10% for one blue chest and 30% for `20 × monster level` crystals. Existing resource rewards and other boss rewards remain. Actual contributors roll independently; support armies earn no personal loot. |
| Treasure goblins | 30% for one blue chest, 10% for one violet chest; gold, food, lumber and stone packs, normal speedups and the shared monster fragment rule. Gold is 5,000 per level; each other resource is 1,000 per level. |
| Fully depleted resource nodes | Additional Glutblüte der Vorräte / Ember of Supplies fragments (`60300105`): 40% at levels 1–2, 80% from level 3. Quantity is uniform from 1 to `min(level, 4)`. Partial gathering, recalls and filling troop capacity without depletion do not trigger this reward. |

The monster fragment rows share an `exclusive_group`: their chances are unconditional shares of a single draw, so at most one relic drops. For example, at level 1 each rarity has 10%; at level 10 each has 40%. The successful quantity draw is uniform and independent of the rarity.

Blue chests (`silver`, item `10105001`) use one main draw per opening: 30% for 1–5 grey/blue fragments, 5% for **10 fragments of one grey/blue relic**, and 65% for ordinary resources, speedups, buffs or action-point items. Grey and blue have equal shares in both fragment categories. Ten fragments follow the existing automatic first-effect unlock rule; an already unlocked relic keeps the ten fragments. No whole-relic grant is substituted for the fragment package.

Violet chests (`gold`, item `10105002`) use one guaranteed main fragment draw: blue 50%, violet 40%, gold 10%; quantity is uniform from 3–5. Independently, each of four 1,000-resource packs has a 20% bonus chance, and each of four basic production buffs has a 10% bonus chance. Multiple bonuses can coexist with the fragments. Platinum chests retain their existing table.

`count_min` (monster/farm/item rewards) and `quantity_min` (weighted chest rewards) enable inclusive uniform ranges. Omitting the minimum keeps the former fixed-quantity behavior. The backoffice preserves these fields, the exclusive groups and independent chest bonuses on batch/detail saves. Previews average the minimum and maximum before computing expected rewards, and the acquisition guide and march preview show the range. English, German, French and Luxembourgish labels live in the shared catalogs.

Rules are calculated on the server. Monster rules freeze at dispatch; mine rewards roll on complete depletion and freeze in the returning haul. Existing transaction and receipt protections remain authoritative.

Verification: `tests/loot_balance.php` checks every source, exact probabilities, ranges, mutually exclusive rarity selection, malformed inputs and editor round-trips. Existing `monster_reward_balance.php`, `relic_drop_rewards.php`, `reward_admin.php`, `reward_preview.php`, `gathering_lifecycle.php`, `monster_rallies.php`, `daily_chests.php`, `item_sources.php`, `reward_presentation.php` and `reward_catalog.cjs` cover consumers, credits, rollback, replay and presentation. The real `/city#city` application and rally overlay, chest detail save and monster batch save were checked using synthetic accounts at 1280×800, 390×844 and 844×390. Browser screenshots are in `output/playwright/loot-20261009/`. These are browser checks, not a physical mobile-device test.
