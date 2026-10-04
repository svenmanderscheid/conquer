# Monster rally reinforcement

Union of Kingdoms supplies a marked Royal Vanguard (AI) to gathering monster
rallies in quiet worlds. It is a virtual army, not an account or an alliance member.
It never enters city attacks or territorial campaigns.

Default policy (`config/rally_support.php`): at most 10 active humans in the world
and at most 3 in the rally alliance, measured over the preceding 24 hours. Activity
uses authenticated sessions in that world and recent login for players with a city
there. Support starts after 45 seconds; an early launch before then receives none.
Only open/running worlds with the captain still in the original alliance qualify.

The army mirrors the troop types and tiers of the captain and arrived humans.
Its total cannot exceed their combined total, 20,000 troops, or the remaining
Hall capacity after reserving space for every human including travelling joiners.
It has no research/talent buffs and adds no travel delay. The standard battle
resolver handles its power, counters and casualties; victory is not guaranteed.

Humans can replace the virtual units even when the combined capacity display is
full. Population and strength are recomputed while gathering and frozen at launch.
The API exposes `is_ai`, `name_key`, and `human_capacity_remaining`. The team list
labels the reinforcement in English, German and French using the shared catalog.

The existing `RallyService::tick()` handles this both through game requests and
`cron/march_tick.php`. No new endpoint, scheduled job or schema migration is needed.
The row lock and combat lock serialize joins, reinforcement updates and launch.
Disable new assistance with `enabled=false`; launched armies retain their snapshot.

AI receives no XP, quest progress, kill count, loot, items, hospital stock or returned
troops. Only real participants receive personal battle reports and grants. The
ordinary monster kill receipt, charm and alliance/world progression remain once
per defeated monster. Personal reports preserve the reinforcement snapshot.

Validation: `tests/rally_support.php` exercises timing, population gates, human
priority, repeated ticks, combat loss/victory, receipts, returns and cancellation in
a disposable database. `tests/rally_windows.cjs` includes marked AI in desktop,
narrow portrait and short landscape rosters. Existing join and Grumwald regression
tests cover authentication, reservation, travel and boss mechanics.
