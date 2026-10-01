# Gathering occupation visibility — 23 September 2026

The production map shows blue flags/label borders for one's own and allied gatherers,
red for attackable outsiders. Integrated painted work layers communicate active
gathering: a travelling mine cart, hoisted quarry load, chopping lumberjack,
farmer with a scythe and softly glowing crystal facets. Floating tool icons were
removed. Text labels supplement colour. Source art/prompts and implementation:
`RESOURCE_WORK_ANIMATIONS.md`.
Labels now share a compact beige plaque below the visible sprite, without the old
level chip covering artwork. One row: "Lvl: N", owner-only progress bar and collected
amount / actual troop carrying capacity, or gathering player's name for enemies/allies.
The countdown remains in the march list. Selection uses a fine gold plaque outline
and subtle gold sprite accent; resource ground rings/cell highlights are removed.
Free fields retain just the level. Full names remain in the accessible label and
target card. Server filtering already excludes foreign timers; the renderer also
ignores any foreign timer/progress present in a malformed/stale snapshot.
`withOccupations` returns a private sampled amount, rate, capacity and resource limit
only to the owner, using the same formula as gathering settlement. Client display
interpolates between snapshots, clamped to the finish time, capacity and field limit.
Free fields have no work overlay. Server updates remove/recolour the overlay and
replace actions; own finish times are updated in the encounter card each second.

The existing server-owned `is_own_gathering`, `can_attack` and `gatherer_march_id`
fields determine the presentation. No new client-side combat permission is granted.
`GatherService` already rejects attacks against self/allies both on dispatch and
arrival and rejects ordinary gathering at occupied fields. The existing attack
endpoint and troop-selection flow are reused. Allied fields retain a disabled
occupation button; Details/Share are read-only. Own fields retain Recall.

`tests/gathering_lifecycle.php`: all checks passed against a disposable schema,
including outsider attacks, own/allied protection, changing membership at arrival,
replacement occupants, returns, recalls, world scope and private timers.

`tests/gathering_occupation_app.cjs`: real app with the isolated `--gathering`
fixture. Own, ally, enemy and free states at 1280×800, 390×844, 320×568, 844×390,
568×320. Colours, permitted/disabled actions, unclipped buttons, normal/reduced/light/
hidden animation, relationship updates and occupation cleanup pass without browser
errors. The test collapses the march list using its own control on narrow screens.
Screenshots: `artifacts/gathering-occupation/`. No real player troops were dispatched.

Test support fix: FeatureDatabase replays the three additive 0107 columns separately
with IF NOT EXISTS, since the cloned source schema may already include them. This
only affects disposable test schemas; production migrations/data are not changed.
