# Union of Kingdoms — VIP abilities

Approved balance: user table supplied on 7 October 2026. `src/Game/Vip/VipService.php` is the authoritative table; the client displays the server's current, next and selectable-level values.

VIP levels 1–20 require these cumulative points, in order:

`0 · 200 · 500 · 1,000 · 5,000 · 10,000 · 20,000 · 50,000 · 100,000 · 150,000 · 200,000 · 250,000 · 500,000 · 1,000,000 · 1,500,000 · 2,000,000 · 3,000,000 · 4,000,000 · 8,000,000 · 12,000,000`.

Each sequence below lists VIP 1 through VIP 20; zero represents an empty reference cell. Values are totals at the selected level, not grants to add again at every level.

| Ability | Values at VIP 1–20 |
| --- | --- |
| Mastery / extra Hunter points (flat) | 0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19 |
| Maximum rally troop size (%) | 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 5, 10, 10, 15, 20 |
| Troop training cost (%) | 0, 0, 0, 0, 0, 0, 0, 0, -5, -5, -5, -10, -10, -10, -15, -15, -20, -20, -25, -25 |
| Troop training speed and amount (each, %) | 0, 0, 0, 0, 0, 0, 0, 0, 5, 5, 5, 10, 10, 10, 15, 15, 20, 20, 25, 25 |
| Maximum action points (flat) | 0, 0, 0, 0, 0, 0, 0, 10, 10, 10, 20, 20, 20, 30, 30, 40, 50, 60, 70, 80 |
| Mortality reduction (percentage points) | 0, 0, 0, 0, 0, 0, 5, 5, 5, 10, 10, 10, 15, 15, 15, 20, 20, 25, 25, 30 |
| Troop limit (%) | 0, 0, 0, 0, 0, 5, 5, 5, 10, 10, 10, 15, 15, 15, 20, 20, 25, 25, 30, 30 |
| Marching troop capacity (flat) | 0, 0, 0, 0, 0, 5000, 5000, 5000, 10000, 10000, 10000, 15000, 15000, 15000, 20000, 25000, 30000, 35000, 40000, 45000 |
| Extra dispatch queues (flat) | 0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 2, 2, 2 |
| Additional building queue | Locked at VIP 1–4; unlocked at VIP 5–20 |
| Action point regeneration (%) | 0, 0, 0, 3, 6, 9, 12, 15, 18, 21, 25, 30, 35, 40, 45, 50, 55, 60, 65, 70 |
| Research and construction speed (each, %) | 0, 0, 3, 6, 9, 12, 15, 18, 21, 25, 30, 35, 40, 45, 50, 55, 60, 70, 80, 100 |
| Gathering speed (%) | 0, 6, 9, 12, 15, 18, 21, 24, 27, 30, 35, 40, 45, 50, 55, 60, 65, 70, 80, 100 |
| Resource production (%) | 3, 3, 9, 12, 15, 18, 21, 24, 27, 30, 35, 40, 45, 50, 55, 60, 65, 70, 80, 100 |

## Gameplay integration

- Percentage bonuses enter the shared additive buff stack. Construction and production retain their existing dedicated VIP consumers to avoid applying the same bonus twice. Speed bonuses divide new durations by `1 + bonus`; +100% speed halves time. Existing queued jobs keep their finish time.
- Troop limit adds to `march_size`; the flat marching capacity is added after the base capacity is multiplied. At Castle 30, VIP 20 with no other bonuses permits 110,000 troops per march and five general dispatch queues.
- Rally capacity uses the captain's Hall and combined capacity bonuses. VIP 20 with Hall 30 and no other bonuses permits 3,000,000 troops. The joining player's own march limit still applies.
- Training cost, speed and amount apply to infantry, archers and cavalry, including previews and server-side validation. VIP 20 reduces base resource cost to 75%, raises training speed to 125% and permits 125% of the building's base batch size.
- AP perks increase the regeneration cap and regeneration rate, alongside Hunter talents. At VIP 20 without talents, the regeneration cap is 280 and the rate is 20.4 AP/hour. Existing refill/overflow rules remain in effect. Level changes settle elapsed regeneration at the previous rate and save the new rate without granting retroactive AP.
- Mortality reduction increases the wounded share of casualties in city, territory and shrine combat by the listed percentage points. With the current 70% death share, VIP 20 reduces it to 40%. Total casualties and survivors do not change. Monster combat already treats all casualties as wounded.
- Mastery allowances remain spendable extra Hunter talent points; they grant neither Hunter XP nor Hunter levels.

VIP remains world-scoped. New profiles start at zero points and VIP 1. Earned balances are preserved; their level follows the revised thresholds. `migrations/0135_vip_benefits.sql` changes defaults and refreshes world-level caches without rewriting points. It is repeatable.

Checks: `tests/vip_benefits.php`, `tests/vip_system.php`, `tests/vip_hunter_points.php`, `tests/vip_worlds.php`, `tests/vip_hunter_points_app.cjs`, and `tests/vip_worlds_app.cjs`. Browser checks use disposable accounts and the real `/city` app in English, German and French at desktop, 390 px, 320 px and landscape sizes. They do not replace a native device test.

Verification on 7 October 2026: all 547 VIP server checks passed, as did the Hunter/VIP browser matrix and the focused world-VIP browser flow (`VIP_WORLD_ONLY=1`). Research effect checks and 83 rally-capacity checks also passed. The separate full registration-page assertion in `vip_worlds_app.cjs` timed out; it is excluded only by the focused flag. The broader `defense_lifecycle.php` reached its old talent test and failed because it submits `defense_0`, which is absent from the current Hunter catalogue. These independent checks remain unresolved; the VIP-specific checks passed.
