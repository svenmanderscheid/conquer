# Mythic relic retirement

At the user's request, mythic relics are no longer available in Union of Kingdoms.
The active collection contains 71 relics. Four non-mythic legacy definitions remain
available only to existing owners.

Retired relic codes: `60400003`, `60500001`, `60500101`, `60500102`, `60500103`,
`60500104`, `60500105`. The list includes the six previously active mythic
cards and the older Eternal Flame Crown. Mythic fragment pack `10207005` is also
retired. These IDs must never be reused.

The shipped relic/effect catalogs and chest tables exclude these entries.
`TreasureData` also rejects retired codes when loading any legacy definitions.
Existing ownership rows remain stored, but are omitted from the collection and
cannot be upgraded, exchanged, equipped or awarded. Previously equipped mythic
relics no longer contribute bonuses. Presets expose retired slots as empty and
retain valid relics when saved or applied. Old scout reports and item rewards
omit the retired entries when presented. Old dungeon reward snapshots remain
claimable, with retired payouts omitted from display and granting.

Saved reward overrides are filtered before previews and rolls. Chests with only
retired entries use their current default pool; dungeon overrides referencing a
retired relic use that dungeon's current default relic. The reward editor cannot
configure mythic fragment drops. Platinum chest descriptions reflect the change
in English, German and French.

Checks: `tests/retired_mythic_relics.php`, `tests/treasure_catalog.php`,
`tests/treasure_loadouts.php`, `tests/treasure_presets.php`, `tests/daily_chests.php`,
`tests/inventory_rewards.php`, `tests/reward_admin.php` and
`tests/retired_mythic_relics_app.cjs`. The new checks are registered in
`tests/run_alpha.py`. The app check uses the real main app in an isolated preview
with five desktop/portrait/landscape viewport sizes and synthetic login.

The older standalone `tests/treasure_panel.cjs` currently reports an intrinsic
height overflow in its synthetic `.treasury-loadout` at 1280×720. The identical
failure reproduces with its original test, original 77-entry catalog and original
panel code. The real main-app retirement check above passes in all five formats.
