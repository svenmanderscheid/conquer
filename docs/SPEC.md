# Conquer — Game Specification v1

**Project:** Conquer (working codename) — a browser-based 4X strategy MMO  
**Owner:** Sven Manderscheid (Ekki)  
**Spec version:** 1.5  
**Last updated:** 2026-05-04  
**Inspiration:** League of Kingdoms (sunsetting May 2026). Mechanics blueprint, not a clone — names, art, lore, balancing diverge.

> ⚠️ **NAMING NOTE:** "Conquer" is an **internal working codename** for this project. The final marketing name is undecided and will be chosen later (likely after an EU trademark lawyer review for €500-1500). All references to "Conquer" throughout this spec should be understood as placeholders for the final game name. The codename is used in: GitHub repo, file names, internal documentation, and development environment. When the final name is chosen, a global find-and-replace will be performed across all project artifacts.

---

## Status of this document

This is the **master specification** for Conquer. Every other document, file, and decision in the project should be consistent with this spec. When a conflict appears between this spec and another file, **this spec wins** unless explicitly updated.

This is also a **living document**. Sections marked `[OPEN]` are unresolved. Sections marked `[DEFAULT]` use placeholder values that should be tuned during balancing. Sections marked `[ASSUMED]` are best-guess values where source data was incomplete.

---

## Table of Contents

1. [Vision & Pillars](#1-vision--pillars)
2. [Tech Stack & Project Structure](#2-tech-stack--project-structure)
3. [Resource System](#3-resource-system)
4. [Buildings](#4-buildings)
5. [Troops](#5-troops)
6. [Research Trees](#6-research-trees)
7. [Map System](#7-map-system)
8. [March System](#8-march-system)
9. [Battle System](#9-battle-system)
10. [Hospital & Mortality](#10-hospital--mortality)
11. [Spy System](#11-spy-system)
12. [Treasure System](#12-treasure-system)
13. [Charm System](#13-charm-system)
14. [Monster System](#14-monster-system)
15. [Alliance System](#15-alliance-system)
16. [Conquest Event](#16-conquest-event)
17. [VIP System](#17-vip-system)
18. [GEMS & Premium Shop](#18-gems--premium-shop)
19. [Items & Inventory](#19-items--inventory)
20. [Beginner Protection & Inactivity](#20-beginner-protection--inactivity)
21. [Tutorial Flow](#21-tutorial-flow)
22. [Database Schema](#22-database-schema)
23. [API Endpoints](#23-api-endpoints)
24. [Notifications & Polling](#24-notifications--polling)
25. [Admin Panel](#25-admin-panel)
26. [Roadmap](#26-roadmap)
27. [Open Questions](#27-open-questions)
28. [Asset Specifications](#28-asset-specifications)

---

## 1. Vision & Pillars

### 1.1 What Conquer is

Conquer is a browser-based 4X (eXplore, eXpand, eXploit, eXterminate) strategy MMO. Players build a single city, train troops, research technologies, gather resources, kill monsters, and join alliances to compete for control of strategic map structures (Shrines and the Congress) during recurring 2-week Conquest Events.

A single world hosts up to ~5,000 active players on a 1024×1024 tile map. Worlds run indefinitely. Player cities and alliance possessions persist across Conquest Events.

### 1.2 Design pillars

1. **Long-form competitive play.** A new player who joins today has a meaningful path to relevance over weeks and months. No reset-and-restart.
2. **Alliances matter.** Solo play is viable for early game; mid- and end-game require alliance cooperation. The Conquest pyramid (C → B → A → S Shrines) is impossible to clear solo.
3. **Genre-familiar.** Targets former League of Kingdoms players. Mechanics, terminology, and pacing should feel immediately recognizable. Differentiation comes from polish, balance tweaks, and original art/lore.
4. **No pay-to-win, but pay-to-skip.** GEMS accelerate progression (instant builds, resource bundles, VIP boosts). They do not unlock gameplay otherwise unobtainable.
5. **Honest scope.** This is a multi-year solo/hobby project. The MVP is intentionally narrow. Features expand in clearly defined phases (see Roadmap, §26).

### 1.3 Target audience

- LoK refugees looking for a successor (primary)
- Lords Mobile / Kingshot / Rise of Kingdoms players seeking a browser alternative (secondary)
- Tribal Wars veterans curious about modernized 4X (tertiary)

### 1.4 Out of scope

- Mobile-native app (browser PWA only)
- Real-time PvP (combat is march-based, not interactive)
- Cosmetic-only competitive content (e.g. ranked seasons with skin rewards) — castle skins exist for monetization but are not gameplay
- User-generated content (player-made worlds, modding)

---

## 2. Tech Stack & Project Structure

### 2.1 Stack

| Layer | Choice | Rationale |
|---|---|---|
| Backend | PHP 8.2+ (OOP, PDO, no framework) | Owner controls every line; runs on shared hosting; no Composer/Artisan dependency |
| Frontend | HTML5 + CSS3 (hand-written) + Alpine.js 3.x | Owner is learning Alpine; minimal build step; reactive without complexity |
| Database | MySQL 8.x | Standard, available everywhere |
| Map rendering | HTML5 Canvas 2D | Pannable/zoomable tile map |
| Realtime | HTTP long-polling (15s default, dynamic) | Shared-hosting-friendly; no WebSocket dependency |
| Auth | PHP Sessions + bcrypt | Standard, secure, no library required |
| Hosting | Hostinger shared hosting (initial); migrate to VPS at ~500 active players | Owner has existing Hostinger infrastructure |

**No Laravel, no Composer-mandatory dependencies, no Node.js build pipeline.** The owner is learning the codebase and must be able to read every file. All code is owner-written or copy-pasted from documented sources.

### 2.2 Dependencies (CDN / vendored only)

- **Alpine.js** 3.x — frontend reactivity, via CDN
- **PHPMailer** — email (vendored, single-file include)
- **GD or Imagick** — server-side image manipulation (PHP built-in)

No npm. No Composer. Vendored libraries live in `/lib/`.

### 2.3 Folder structure

```
/
├── index.php                    # Single entry point, routes via ?screen= or REST-like /api/...
├── .htaccess                    # URL rewriting, security headers
├── config/
│   ├── database.php             # PDO connection
│   ├── world.php                # World config (loaded from DB at boot)
│   └── secrets.php              # NOT in git — gitignored
├── app/
│   ├── controllers/             # One per screen / API endpoint group
│   │   ├── AuthController.php
│   │   ├── CityController.php
│   │   ├── BuildingController.php
│   │   ├── TroopController.php
│   │   ├── ResearchController.php
│   │   ├── MapController.php
│   │   ├── MarchController.php
│   │   ├── BattleController.php
│   │   ├── SpyController.php
│   │   ├── AllianceController.php
│   │   ├── TreasureController.php
│   │   ├── MonsterController.php
│   │   ├── ConquestController.php
│   │   ├── ShopController.php
│   │   └── AdminController.php
│   ├── models/                  # One per main entity, thin layer over PDO
│   │   ├── Player.php
│   │   ├── City.php
│   │   ├── Building.php
│   │   ├── Troop.php
│   │   ├── Research.php
│   │   ├── March.php
│   │   ├── Alliance.php
│   │   ├── Treasure.php
│   │   ├── Monster.php
│   │   ├── Shrine.php
│   │   └── ...
│   ├── services/                # Game logic, pure functions where possible
│   │   ├── BattleEngine.php     # Combat resolution
│   │   ├── ResourceCalculator.php
│   │   ├── BuffStackEngine.php  # Aggregates additive + multiplicative buffs
│   │   ├── MarchTimer.php
│   │   └── ...
│   ├── views/                   # HTML templates, Alpine-decorated
│   │   ├── layout.php
│   │   ├── city.php
│   │   ├── map.php
│   │   ├── ...
│   └── helpers.php              # Global utility functions
├── public/
│   ├── css/
│   ├── js/
│   │   ├── alpine.min.js
│   │   ├── map.js               # Canvas map renderer
│   │   ├── poller.js            # Notification polling
│   │   └── ...
│   ├── assets/
│   │   ├── buildings/           # Sprite sheets
│   │   ├── troops/
│   │   ├── treasures/
│   │   ├── terrain/
│   │   └── icons/
├── data/                        # Static game data (JSON, source of truth)
│   ├── buildings/               # castle.json, academy.json, ...
│   ├── research/
│   │   ├── battle.json
│   │   ├── production.json
│   │   └── advanced.json
│   ├── troops.json
│   ├── monsters.json
│   ├── field_objects.json
│   ├── treasures.json
│   └── vip.json
├── admin/                       # Separate admin entry, password-protected
│   ├── index.php
│   └── ...
├── lib/                         # Vendored third-party (PHPMailer)
├── logs/                        # Application logs (gitignored)
├── cron/                        # Scheduled scripts
│   ├── tick.php                 # Runs every minute: resolves marches, battles
│   ├── inactive_cleanup.php     # Daily: marks inactive players
│   ├── conquest_event_tick.php  # Hourly: progresses Conquest Events
│   └── ...
├── docs/                        # Internal docs
│   ├── SPEC.md                  # THIS FILE
│   ├── DATABASE_SCHEMA.md       # Mirrors §22 below, kept in sync
│   ├── ROADMAP.md               # Mirrors §26 below
│   └── CLAUDE.md                # Pointer file for Claude Code sessions
└── tests/                       # PHPUnit-light test suite
    └── ...
```

### 2.4 Static data philosophy

All balance numbers live in `/data/*.json`. PHP loads them at request time (cached via APCu when available). Editing balance does not require code changes — only JSON edits.

The JSON files in `/data/` are the **source of truth** for game balance. The data we have today (extracted from LoK) seeds the project. Every value will be tuned over time.

### 2.5 Why no framework

The owner explicitly chose to write his own thin MVC layer rather than learn Laravel. Reasons:

- **Read every line.** No "magic" auto-loading or service-container indirection.
- **Hosting freedom.** No Composer means it runs on any LAMP host — including subdirectories on Hostinger.
- **Learning.** Hand-rolling auth, routing, and ORM-light gives deeper PHP fluency.
- **Performance.** A lean dispatcher with no middleware stack outperforms framework cold-start on shared hosting.

**Risk:** owner must implement security primitives (CSRF, rate limiting, input sanitization) himself. Mitigated by checklist in §22.6.

---

## 3. Resource System

### 3.1 The five resources

Conquer uses **four resources** (Food, Lumber, Stone, Gold) plus a separate **GEMS** premium currency. There is no Crystal resource.

| Resource | Code (item_code) | Source | Used for |
|---|---|---|---|
| **Food** | 10100001 | Farm production + map gathering | Building, troop training, troop upkeep [PHASE 2] |
| **Lumber** | 10100002 | Lumber Camp production + map gathering | Building, troop training |
| **Stone** | 10100003 | Quarry production + map gathering | Building, troop training, walls |
| **Gold** | 10100004 | Gold Mine production + map gathering | Building, troop training, research, treasures |
| **GEMS** | (not a resource — currency) | Real-money purchase + small drop pool from monsters and rare GEM Nodes on map | Premium shop, instant completion, resource bundles, VIP points |

### 3.2 Resource production formula

Each resource-production building produces resources passively over time. Production rate scales with building level and global modifiers.

```
hourly_production = base_rate(building, level)
                  × (1 + sum(additive_production_buffs))
                  × prod(1 + multiplicative_production_buffs)
                  × world.speed_factor
```

**Additive production buffs:**
- VIP `Resource Production` bonus (up to +100% at VIP 20)
- Research `food_production` / `wood_production` / etc. (Production Tree)
- Research `advanced_food_production` etc. (Production Tree, late-game)
- Research `resource_production` (Advanced Tree, +10%/level × 10 levels)

**Multiplicative production buffs:**
- Treasure boosts (each treasure boost stacks multiplicatively with others)
- Active Charm effects (when buffing the matching production stat)
- Active Boost Items (e.g. `ITEM_CODE_FOOD_BOOST_8H` = +25% food production for 8h [DEFAULT])

### 3.3 Storage cap

Each resource has a per-city storage cap. Production stops when the cap is reached. The cap is determined by the **Storage** building (for Food, Lumber, Stone) and the **Treasure House** (for Gold), modified by research.

```
storage_cap(resource) = base_cap(building, level)
                     × (1 + sum(additive_capacity_buffs))
                     × prod(1 + multiplicative_capacity_buffs)
```

### 3.4 Resource update on read

Resources are not stored as continuously-updating values. They are stored as a snapshot plus a `last_resource_update` timestamp. When a player request needs the current value, the server computes:

```php
$elapsed = time() - $city->last_resource_update;
$gained = ($elapsed / 3600) * hourly_production($city, $resource);
$current = min($city->{$resource} + $gained, storage_cap($city, $resource));

// On write (any action that consumes resources):
//   - apply the gain
//   - update last_resource_update = now
//   - persist new resource value
```

This gives infinite resolution (sub-second accuracy) without per-tick database writes.

### 3.5 Resource Protection

When a city is attacked successfully, a portion of the defender's resources is plundered. The unprotected portion is determined by:

- **Base**: 100% of resources above zero are at risk
- **Resource Protect** research (Production Tree): up to −20% via `resource_protect`
- **Resource Protect** research (Advanced Tree): additional reduction
- **Treasure** boosts (Food/Lumber/Stone/Gold Protection)
- **Wall** does NOT directly protect resources but absorbs damage to wall HP first (see §9)

```
plundered_amount = min(
    attacker_carry_capacity,
    (defender_resource - protected_amount) × world.haul_factor
)
```

Where `protected_amount` is computed per-resource from the buffs above.

### 3.6 T5 Troops &amp; Mythic upgrades — high-end Resource costs

Tier 5 troops (Crusader, Sniper, Dragoon) and Mythic treasure upgrades cost **only the four standard resources** — Food, Lumber, Stone, Gold — but in very high quantities. There is no special resource for late-game content.

- T5 troop training: 5-10× the cost of T4 troops [DEFAULT — to be tuned during balancing]
- Mythic treasure final-star upgrades: same fragment system as other treasures (see §12.4), no extra resource gating

This keeps the economy simple and avoids the trap of late-game content being gated by an obscure rare resource.

### 3.7 GEMS — premium currency

GEMS are entirely separate from the resource system. They are **never required** for content unlocks, only for time-saving and convenience.

- **Acquisition**:
  - Real-money purchase (primary revenue source)
  - **Monster drops** — small GEM amounts drop from solo and rally monsters (see §14.10)
  - **GEM Nodes** on the map — rare spawns at all Castle levels (see below)
  - **Quest rewards** — small amounts from daily and milestone quests
  - **Conquest Event rewards** — significant GEM rewards for participation
- **Storage**: Per-account, NOT per-city. GEMS persist across worlds if cross-world play is later enabled.
- **No cap.** No production. No gathering via standard resource buildings.

**GEM Nodes on the Map**

Rare special field objects that drop a fixed quantity of GEMS when gathered. Available to all players regardless of Castle level (no gating).

| Node Type | Drop Amount | Spawn Rarity (per sector per hour) |
|---|---|---|
| Gem Node Lv 1 | 50 GEMS | 1.0 |
| Gem Node Lv 2 | 100 GEMS | 0.4 |
| Gem Node Lv 3 | 200 GEMS | 0.1 |

These are extremely rare — Gem Node Lv 3 spawns ~10 times per day across the entire world. They create competitive moments ("a Gem Node spawned in Sector 4!") and provide a steady trickle of free GEMS for active players.

**Free-to-play GEM income estimate**

For an active F2P player:
- ~50-90 GEMS/day from monster drops (mostly solo Lv 4-10 + occasional Goblins)
- ~10-30 GEMS/day from Gem Nodes (RNG, depends on competition)
- ~30-50 GEMS/week from Daily Quests
- ~500-2000 GEMS per Conquest Event participation

Total: **~80-200 GEMS/day** for active F2P play. A €5 GEMS package equals ~3-6 days of F2P grinding. Whales pay for **speed**, not for **content access**.

### 3.8 Resource starting values

A new player's city begins with:

```
food:    10,000
lumber:  10,000
stone:   10,000
gold:     5,000
gems:        50    (tutorial bonus)
```

[DEFAULT — balance during onboarding tuning]


---

## 4. Buildings

### 4.1 Building list

Conquer has **13 buildings** in each player city. Each has 30 levels. Level 1 exists by default at city creation; only upgrades are constructed.

| ID | Name | Role | First unlocked at |
|---|---|---|---|
| `castle` | Castle (HQ) | City level marker. Capping-gate for all other buildings. | Default L1 |
| `wall` | Wall | Defense buffs. HP pool absorbs incoming attacks. | Default L1 |
| `farm` | Farm | Food production | Default L1 |
| `lumber_camp` | Lumber Camp | Lumber production | Default L1 |
| `quarry` | Quarry | Stone production | Default L1 |
| `gold_mine` | Gold Mine | Gold production | Default L1 |
| `storage` | Storage | Food / Lumber / Stone capacity | Default L1 |
| `treasure_house` | Treasure House | Gold capacity, Treasure slots | Default L1 |
| `barrack` | Barrack | Troop training | Default L1 |
| `hospital` | Hospital | Heal wounded troops, capacity cap | Default L1 |
| `academy` | Academy | Research | Default L1 |
| `trading_post` | Trading Post | VIP Shop (Mon 00:00 UTC weekly), Caravan (00:00/08:00/16:00 UTC daily), Resource trade P2P (Phase 2+) — see §4.9 | Default L1 |
| `hall_of_alliance` | Hall of Alliance | Alliance reinforcement capacity, rally size | Default L1 |

**No Watch Tower.** (Removed per owner decision.)
**No Embassy.** (Replaced by Hall of Alliance.)

### 4.2 Cost / time / power data

Per-level costs, build times, and power values come from `/data/buildings/<building_id>.json` files. These are seeded from extracted LoK data and editable. Each level entry has shape:

```json
{
  "valid": true,
  "resources": [
    { "type": "lumber", "value": 6000 },
    { "type": "stone", "value": 6000 },
    { "type": "gold", "value": 2500 }
  ],
  "requirements": [
    { "type": "castle", "level": 1 },
    { "type": "quarry", "level": 1 }
  ],
  "time": 1,
  "power": 600
}
```

- `valid: false` at level 1 = building exists by default; only level 2+ are buildable
- `time` is in seconds
- `power` is the power contribution at that level (cumulative when summed across all levels achieved)
- `requirements` are AND-conditions (all must be met)

At Castle Level 30, a final upgrade requires `golden_pillar: 1` — a special item (acquired from S-Shrine Conquest Event victory or Mythic monster drop).

### 4.3 Upgrade rules

- **One build slot at a time** by default. A second slot is unlocked via **VIP 4+** (the "Additional Building Queue" benefit).
- Resources are consumed at the moment construction starts.
- Cancelling a build returns 50% of resources [DEFAULT].
- An upgrade can be instant-completed using GEMS or Speedup items.
- Build time is reduced by **construction_speed** buffs (additive across VIP, research, treasures, charms).

### 4.4 Unlock dependencies (the dependency graph)

Critical insight: Castle is **not** the universal prerequisite. Each non-Castle building has its own prerequisite chain. Castle in turn depends on other buildings before it can level up further.

Examples (selected key levels):

| Castle Level | Requires |
|---|---|
| L5 | Wall L4, Watch Tower L4 → **adapt to: Wall L4, Trading Post L4** (since no Watch Tower) |
| L10 | Wall L9, Trading Post L9 |
| L15 | Wall L14, Academy L14 |
| L20 | Wall L19, Hospital L19 |
| L25 | Wall L24, Storage L24 |
| L30 | Wall L29, Treasure House L29 + 1 golden_pillar |

This means **Castle leveling is gated by deep specialization in support buildings**, encouraging breadth.

The Watch Tower removal requires editing `castle.json` requirements at certain levels. See §27 Open Questions for the patch plan.

### 4.5 Treasure slot unlock (Treasure House)

The Treasure House controls how many treasure slots a player can equip simultaneously.

| Treasure House Level | Slots Unlocked |
|---|---|
| 1 | 2 slots |
| 5 | 3 slots |
| 10 | 4 slots |
| 20 | 5 slots |
| 25 | 6 slots |

Slots beyond 6 are not planned.

### 4.6 Hospital capacity

Wounded troops occupy hospital capacity. When the hospital is full, additional wounded die instead.

```
hospital_capacity(level) = base_capacity[level]
                         × (1 + sum(additive_hospital_buffs))
                         × prod(1 + multiplicative_hospital_buffs)
```

Buffs come from research `hospital_capacity` (Battle Tree, 10 levels), VIP, and treasures.

Healing speed (time per wounded troop heal) is reduced by `healing_time_reduced` research and `Healing Speed` treasures.

### 4.7 Wall mechanics

The Wall provides **three** defensive contributions:

1. **Wall Durability (HP)** — A pool of HP attached to the city. On a successful attack against the city, defender troops AND wall HP are damaged. The wall does not absorb damage on behalf of troops; both take damage simultaneously per the battle resolution.

2. **Wall Attack/Defense Buff** — Multiplicative buff on defender troop attack and defense, scaling with wall level (L1: +5%, L30: +40%). This applies **only** when defending the home city.

3. **Defense Mortality Reduction** — A flat **30%** reduction in mortality rate for defending troops at home (constant across all wall levels). When a defender troop is "lost" in battle, 30% more of those losses become wounded (saveable in hospital) instead of permanent dead.

#### 4.7.1 Wall HP scaling

| Wall Level | HP | Atk Buff | Def Buff | Power |
|---|---|---|---|---|
| 1 | 5,000 | +5% | +5% | 600 |
| 5 | 7,000 | +9% | +9% | 7,477 |
| 10 | 10,000 | +14% | +14% | 34,132 |
| 15 | 12,500 | +19% | +19% | 108,455 |
| 20 | 15,000 | +24% | +24% | 300,109 |
| 25 | 17,500 | +29% | +29% | 775,233 |
| 30 | 25,000 | +40% | +40% | 1,925,781 |

Defense Mortality Reduction is **30% at every level**.

#### 4.7.2 Wall HP destruction → forced teleport

When wall HP reaches **0** during a battle, the defender's city is **teleported to a random location** on the map after the battle resolves.

- "Random" means: random valid empty 2×2 area not within 30 tiles of any Shrine/Congress and not within 10 tiles of another city.
- Teleport is automatic, immediate after battle resolution, and free.
- All buildings, troops, and city state are preserved — only the coordinates change.
- Wall HP regenerates to full after the teleport.
- The city's notification system informs the player.

This is the **primary loss condition** in Conquer. Players are never permanently destroyed, but a wall break is a major event — it loses position relative to alliance, takes the player out of any active gathering, and forces re-establishment of strategic placement.

#### 4.7.3 Wall HP regeneration

Outside of battle, wall HP regenerates over time:

```
wall_hp_regen_per_hour = max_wall_hp × 0.10   [DEFAULT — 10% per hour]
```

Wall HP can also be instantly repaired using `Recover` items (`ITEM_CODE_RECOVER_*`).

### 4.8 Building UI

The city view is a top-down illustrated layout. The building positions follow the LoK layout spec preserved in `enum.py`'s `BUILDING_POSITION_MAP` and `BUILD_POSITION_UNLOCK_MAP`. Some positions are unlocked at specific Castle levels (5, 10, 15) to give a sense of city growth.

For MVP, a **list-based fallback view** is also available — every building shown as a card with level, upgrade button, and cost. This is the priority view for MVP launch; the illustrated city view comes in Phase 2.

### 4.9 Trading Post — VIP Shop, Caravan & Resource Trade

The Trading Post is the **commerce hub** of the city. It hosts three independent subsystems:

1. **VIP Shop** — VIP-gated discounted bundles, refreshed **weekly (Monday 00:00 UTC)**
2. **Caravan** — random discounted item pool, refreshed **3× daily at 00:00, 08:00, and 16:00 UTC**
3. **Resource Trade** — player-to-player resource exchange (Phase 2+)

The Trading Post building level controls the size and quality of the Caravan (more slots, better discounts at higher levels). VIP Shop is gated by VIP level only — Trading Post level does not affect what's available there, only that the building exists at L1.

#### 4.9.1 Trading Post — power & caravan stocks per level

| Level | Caravan Slots | Power | Level | Caravan Slots | Power |
|---|---|---|---|---|---|
| 1 | 5 | 600 | 16 | 12 | 123,279 |
| 2 | 5 | 1,590 | 17 | 13 | 150,656 |
| 3 | 6 | 2,993 | 18 | 13 | 183,409 |
| 4 | 6 | 4,873 | 19 | 14 | 222,508 |
| 5 | 7 | 7,333 | 20 | 14 | 269,108 |
| 6 | 7 | 10,444 | 21 | 15 | 324,603 |
| 7 | 8 | 14,360 | 22 | 15 | 390,597 |
| 8 | 8 | 19,225 | 23 | 16 | 468,961 |
| 9 | 9 | 25,225 | 24 | 16 | 561,993 |
| 10 | 9 | 32,561 | 25 | 17 | 672,293 |
| 11 | 10 | 41,516 | 26 | 17 | 803,032 |
| 12 | 10 | 52,393 | 27 | 18 | 957,825 |
| 13 | 11 | 65,517 | 28 | 18 | 1,140,964 |
| 14 | 11 | 81,344 | 29 | 19 | 1,357,501 |
| 15 | 12 | 100,399 | **30** | **25** | **1,613,397** |

(L30 has a special bonus: jumps from 19 to 25 caravan slots — encourages full Trading Post upgrade.)

`Troops Load` field on Trading Post is 0% at all levels — it is reserved for future use (currently unused; the column is kept for compatibility with other building data structures).

Source data lives in `data/buildings/trading_post.json`.

#### 4.9.2 VIP Shop

The **VIP Shop** is a curated catalog of discounted bundles. Each bundle is gated by a minimum VIP level. Bundles refresh **weekly, every Monday at 00:00 UTC** (server-wide, fixed schedule).

**How it works:**

- The shop displays all bundles for which the player meets the VIP requirement.
- Each bundle has a fixed **stock per refresh** (the "Remain" count). The player can buy up to that many copies during the current refresh cycle.
- Bundles cost either **GEMS** or **resources** (Food, Lumber, Stone, or Gold) — the price field in `data/vip_shop.json` is paid per bundle, not per unit.
- The **price shown in-game is for "Buy All"** (i.e. price for buying the full remaining stock at once). For a per-unit price, the client divides `price / remaining_stock`.
  - Example: VIP 6 "8h Food Production Boost" — price 100 GEMS for 5 remaining = 20 GEMS per item.
- All VIP Shop bundles carry a fixed **discount label** (−30%, −40%, −50%, −60%, −70%, −80%, −90%) that's a marketing display — the actual price field already reflects the discount.
- Stock resets every weekly refresh, regardless of whether previously bought or not.

**Catalog structure** (`data/vip_shop.json`):

```json
{
  "bundles": [
    {
      "id": "vip1_speedup_5min",
      "vip_level_required": 1,
      "label": "5 minute Speedup",
      "discount_display": "-40%",
      "stock_per_refresh": 50,
      "price": { "currency": "food", "amount": 150000 },
      "rewards": [
        { "item_code": 10103001, "count": 1, "label": "5min Speedup" }
      ]
    },
    {
      "id": "vip2_food_100k",
      "vip_level_required": 2,
      "label": "100,000 Food",
      "discount_display": "-80%",
      "stock_per_refresh": 20,
      "price": { "currency": "gems", "amount": 14 },
      "rewards": [
        { "item_code": 10101015, "count": 100000, "label": "100k Food" }
      ]
    }
  ]
}
```

**VIP Shop catalog summary (initial seeded set):**

| VIP Level | # Bundles | Sample Items |
|---|---|---|
| VIP 1 | 2 | 100 VIP Points (500 GEMS / 10 stock), 5min Speedup (150k Food / 50 stock) |
| VIP 2 | 4 | 100k Food / Lumber / Stone / Gold (280 GEMS / 20 stock each) |
| VIP 3 | 5 | 10x Healing Potion (200k Food / 20 stock), 1h Troop HP/Def/Atk/Spd Boosts (500 GEMS / 5 stock each) |
| VIP 4 | 4 | 10 VIP Points (150k Food / 30 stock), 50x Healing Potion (1k GEMS / 20 stock), 8h Gathering Boost (200 GEMS / 5 stock), 30min Speedup (300k Food / 20 stock) |
| VIP 5 | 2 | 1h Speedup (1k GEMS / 50 stock), 8h Speedup (2k GEMS / 20 stock) |
| VIP 6 | 5 | 8h Food/Lumber/Stone/Gold Production Boosts (100 GEMS / 5 stock each), 1 Piece Fragment (1k GEMS / 10 stock) |
| VIP 7 | 4 | 1d Food/Lumber/Stone/Gold Production Boosts (300 GEMS / 5 stock each) |
| VIP 8 | 5 | 1M Food/Lumber/Stone/Gold (2k GEMS / 20 stock each), 1d Speedup (600 GEMS / 5 stock) |
| VIP 9 | 5 | 500 VIP Points (2.5k GEMS / 10 stock), 1h Rally HP/HP+/Def+/Atk+ Boosts (1k GEMS / 5 stock each) |
| VIP 10 | 3 | 1h Rally Speed+ (1k GEMS / 5 stock), 5 Piece Fragments (5k GEMS / 5 stock), Treasure Chest (2k GEMS / 20 stock) |
| VIP 11 | 2 | 1h Rally Banner (2.5k GEMS / 5 stock), 1h Rally Banner Speed (1k GEMS / 5 stock) |
| VIP 12 | 3 | 1d Healing/Training/Healing+ Speed (2.5k GEMS / 10 stock each) |
| VIP 13 | 4 | 3d Healing/Training/Healing+ Speed (3.625k GEMS / 5 stock each), Speedup Box L (15k GEMS / 5 stock) |
| VIP 14 | 3 | 7d Healing/Training/Healing+ Speed (4.95k GEMS / 3 stock each) |
| VIP 15 | 1 | 30d Speedup (6.5k GEMS / 1 stock) |
| VIP 16 | 1 | Epic Treasure Chest (6k GEMS / 3 stock) |

**Total seeded bundles: ~53** across VIP 1-16. VIP 17-20 currently have no exclusive bundles; the system extensible — add bundles to the JSON file at any time.

#### 4.9.3 Caravan

The **Caravan** is a randomized rotating market. Three times per day — at **00:00, 08:00, and 16:00 UTC** (fixed wall-clock schedule, server-wide) — the Caravan stocks `N` item slots drawn from a global pool. `N = caravan_slots(trading_post_level)` per the table in §4.9.1.

**How it works:**

- At each refresh, the server rolls `N` distinct items from `data/caravan_pool.json`, each with:
  - A randomized **discount** (between −30% and −90%, weighted toward −50%)
  - A randomized **currency** (GEMS / Food / Lumber / Stone / Gold) — usually 50% GEMS, 50% resources
  - Quantity = 1 per slot (always — caravan items are not "Buy All" bundles, they're single-quantity discounted items)
- Each slot can be bought **once per refresh cycle** (not stack-buyable).
- The "Remain: 1" indicator is fixed at 1 for caravan items.
- **No force-refresh.** Players cannot pay GEMS to re-roll. The schedule is hard.

The fixed schedule means every player sees a refresh at exactly the same moments. This is intentional:
- Predictable for players ("the 08:00 caravan dropped good stuff today")
- Easier server load (one cron tick refreshes everyone, can be batched)
- No FOMO around skipped refreshes (you missed one slot, the next is in ≤8h)

**Pool structure** (`data/caravan_pool.json`):

```json
{
  "weights": {
    "speedups": 0.20,
    "resource_packs": 0.30,
    "boosts": 0.20,
    "fragments": 0.15,
    "rally_buffs": 0.10,
    "specials": 0.05
  },
  "items": [
    {
      "id": "caravan_speedup_5min",
      "category": "speedups",
      "rewards": [{ "item_code": 10103001, "count": 1 }],
      "base_price_gems": 10,
      "base_price_resources": { "food": 50000 }
    },
    {
      "id": "caravan_stone_50k",
      "category": "resource_packs",
      "rewards": [{ "item_code": 10101017, "count": 50000 }],
      "base_price_gems": 40,
      "base_price_resources": { "gold": 50000 }
    }
  ]
}
```

**Discount roll:**

```python
def roll_caravan_slot():
    item = weighted_pick(pool.items, by_category=pool.weights)
    discount = weighted_pick([-30, -40, -50, -60, -70, -80, -90], 
                             weights=[0.10, 0.15, 0.30, 0.15, 0.15, 0.10, 0.05])
    use_gems = random() < 0.5
    if use_gems:
        price = item.base_price_gems * (1 + discount/100)  # discount is negative
        currency = "gems"
    else:
        resource = random.choice(list(item.base_price_resources.keys()))
        price = item.base_price_resources[resource] * (1 + discount/100)
        currency = resource
    return CaravanSlot(item, currency, max(1, int(price)), discount)
```

**Refresh trigger:**

A scheduled cron job runs at the three fixed times:

```
0 0,8,16 * * *  /usr/bin/php /path/to/cron/caravan_refresh.php
```

The script iterates over all active players (across all worlds) and:
1. Rolls `N` new caravan slots based on each player's current Trading Post level
2. Resets all `bought` flags to false
3. Updates `caravan_state.refreshed_at` and `caravan_state.next_refresh_at`

For lazy refresh (alternative implementation): refresh per player on first `/trading-post/caravan` GET if `now() >= next_refresh_at`. Simpler, but means caravan_state writes happen during user requests. Performance trade-off — pick at implementation time.

**Caravan UI:**

- Grid of slots (5 at TP1, up to 25 at TP30)
- Each slot shows: item icon, quantity, discount tag, price, BUY button
- "Next refresh in HH:MM:SS" countdown at the top, ticking down to the next 00:00 / 08:00 / 16:00 UTC

**Caravan vs VIP Shop:**

The Caravan is the **everyday** shop — fast turnover (3× daily), varied items, available at any Trading Post level. The VIP Shop is the **strategic** shop — slow turnover (weekly), predictable bundles, gated by VIP investment. They serve different progression loops.

#### 4.9.4 Resource Trade (Phase 2+)

In Phase 2, the Trading Post enables **player-to-player resource trades**. Mechanics [DEFAULT — to be detailed during Phase 2 design]:

- Player A posts a trade offer: "I send 100k Food, you send 50k Stone"
- Trade must be between alliance members (initially)
- Each trade sends a March (`MARCH_TYPE_TRADE` [TO ADD]) of generic Caravan units
- Trade march is interceptible (gathering-style PvP)
- Tax rate: small percentage of resources lost in transit (5% default), reduces with Trading Post level

Out of MVP scope. Documented here for completeness.

#### 4.9.5 Database tables

```sql
-- VIP SHOP STATE PER WORLD
CREATE TABLE vip_shop_refresh (
    world_id        INT NOT NULL,
    refresh_index   INT NOT NULL,         -- monotonic counter, increments each weekly refresh
    refreshed_at    DATETIME NOT NULL,
    PRIMARY KEY (world_id),
    FOREIGN KEY (world_id) REFERENCES worlds(id)
);

-- VIP SHOP PURCHASES (per player, per refresh cycle)
CREATE TABLE vip_shop_purchases (
    player_id       INT NOT NULL,
    world_id        INT NOT NULL,
    refresh_index   INT NOT NULL,
    bundle_id       VARCHAR(50) NOT NULL,
    bought_count    INT DEFAULT 0,        -- how many copies bought this cycle
    PRIMARY KEY (player_id, world_id, refresh_index, bundle_id),
    FOREIGN KEY (player_id) REFERENCES players(id)
);

-- CARAVAN STATE PER PLAYER (each player has independent caravan rolls)
CREATE TABLE caravan_state (
    player_id       INT NOT NULL,
    world_id        INT NOT NULL,
    refreshed_at    DATETIME NOT NULL,
    next_refresh_at DATETIME NOT NULL,
    slots_json      JSON NOT NULL,        -- array of {item_id, quantity, currency, price, discount, bought}
    PRIMARY KEY (player_id, world_id),
    FOREIGN KEY (player_id) REFERENCES players(id)
);
```

(These tables to be appended to §22 in a future revision; documented here for locality.)

#### 4.9.6 API endpoints

| Endpoint | Method | Description |
|---|---|---|
| `/trading-post/vip-shop` | GET | List visible VIP bundles + remaining stock for current refresh |
| `/trading-post/vip-shop/buy` | POST | Buy N copies of a bundle (body: `{bundle_id, count}`) |
| `/trading-post/caravan` | GET | Get current caravan slots + countdown to next refresh |
| `/trading-post/caravan/buy` | POST | Buy a caravan slot (body: `{slot_index}`) |

(These endpoints to be appended to §23 in a future revision; documented here for locality.)


---

## 5. Troops

### 5.1 Three types, five tiers

Conquer has three troop types and five tiers per type. **No special troops** (no spies-as-units, no siege engines, no nobles). The three types form a counter cycle.

| Type | Counter (deals +30%) | Countered by (takes +30%) |
|---|---|---|
| Infantry | Ranged | Cavalry |
| Ranged | Cavalry | Infantry |
| Cavalry | Infantry | Ranged |

A "neutral" matchup (Infantry vs Infantry) applies a ×1.0 modifier.

### 5.2 Troop roster

All 15 troops, source: `/data/troops.json` (extracted from troop data).

| Tier | Infantry | Ranged | Cavalry |
|---|---|---|---|
| T1 | Fighter | Hunter | Stableman |
| T2 | Warrior | Longbow Man | Horseman |
| T3 | Knight | Ranger | Heavy Cavalry |
| T4 | Guardian | Crossbow Man | Iron Cavalry |
| T5 | Crusader | Sniper | Dragoon |

Final names will be re-themed away from LoK terminology before launch (Open Question §27).

### 5.3 Troop stats

Each troop has:

| Field | Meaning |
|---|---|
| `code` | Unique identifier (e.g. 50100101 = Fighter) |
| `type` | 1=Infantry, 2=Ranged, 3=Cavalry |
| `hp` | Hit points |
| `attack` | Attack stat (used in damage formula) |
| `defense` | Defense stat |
| `speed` | March speed (higher = faster) |
| `power` | Power contribution per troop |
| `carry` | Resource haul capacity per troop |
| `need_*` | Resource cost per troop (Food, Lumber, Stone, Gold) |
| `time` | Training time per troop in seconds |
| `heal_time` | Hospital heal time per troop in seconds |

Sample (all tier 1):

| Stat | Fighter (Inf T1) | Hunter (Rgd T1) | Stableman (Cav T1) |
|---|---|---|---|
| HP | 130 | 115 | 120 |
| Attack | 45 | 57 | 54 |
| Defense | 35 | 25 | 32 |
| Speed | 65 | 75 | 95 |
| Carry | 2 | 1.5 | 1 |
| Train time | 3s | 3s | 3s |
| Heal time | 0.5s | 0.5s | 0.5s |

**Genre archetype:** Infantry has highest HP/Defense, Ranged has highest Attack, Cavalry has highest Speed but lowest Defense.

### 5.4 Tier unlocks

Tier 2-5 troops are gated by Academy level and a research unlock node:

| Tier | Academy | Research Required |
|---|---|---|
| T1 | L1 | (default) |
| T2 | L10 | `warrior` / `longbow_man` / `horseman` (1 level each, Battle Tree) |
| T3 | L16 | `knight` / `ranger` / `heavy_cavalry` |
| T4 | L23 | `guardian` / `crossbow_man` / `iron_cavalry` |
| T5 | L30 | `crusader` / `sniper` / `dragoon` |

### 5.5 Training

Training happens at the **Barrack** building. Training queue:

- One troop type at a time (e.g. you can train 1000 Fighters OR 1000 Hunters but not both simultaneously) — **per Barrack instance**.
- Multiple Barracks (positions unlocked at Castle 5, 10, 15) allow parallel training of different types.
- Per-batch quantity is capped by:
  - **Resource availability** (cost × count must be ≤ player resources)
  - **Troop Storage** research (Battle Tree, `troops_storage` + Production Tree `infantry_storage` etc.) — global cap on standing army size
  - **Farm capacity** for troop upkeep [PHASE 2 — see §5.7]

### 5.6 Troop power

Each troop contributes its `power` value to the player's total power score (the ranking metric). Power scales with tier — a single Crusader (T5 Inf) is worth 30 power, a Fighter (T1 Inf) is worth 2.

### 5.7 Troop upkeep [PHASE 2 — DEFERRED]

Currently Conquer has **no troop upkeep**. Troops cost food only at training time, not ongoing.

This is a deliberate MVP simplification. In Phase 2, food upkeep can be added as a balancing lever:

```
food_consumed_per_hour = sum(troop_count × upkeep_cost(troop)) for each troop
```

If food production cannot meet upkeep, training stops and gathering becomes mandatory. This adds strategic depth but is not required for MVP.

### 5.8 Promotion

Lower-tier troops can be **promoted** to the next tier, consuming resources and time. Promotion is unlocked by the higher-tier research node.

```
promote(T1 → T2): 
  cost = (T2 training cost) × 0.7   [DEFAULT — cheaper than building from scratch]
  time = (T2 training time) × 0.5
```

The owner has flagged promotion as a wanted feature; exact mechanics tunable later.

### 5.9 Troop march order

When multiple troop types march together:

- **March speed** = MIN(speed of all troop types in the march)
- **Carry capacity** = SUM(troop_count × carry) across all troops
- **Combat contribution** = each troop fights with its own stats; counter modifiers apply per troop type

This means including a single slow Cavalry tier in an Infantry march doesn't slow it (Cavalry are usually faster) — but mixing T1 with T5 of any type means you take T1 speed (T1 is slower than T5).


---

## 6. Research Trees

### 6.1 Three player trees + one alliance tree

| Tree | File | Nodes | Tab Name (UI) |
|---|---|---|---|
| Battle | `data/research/battle.json` | 55 | "Battle" |
| Production / Economy | `data/research/production.json` | 34 | "Economy" |
| Advanced | `data/research/advanced.json` | 40 | "Advanced" |
| Alliance Battle | `data/alliance_research/battle.json` [TO BUILD] | TBD | "Alliance > Battle" |
| Alliance Production | `data/alliance_research/production.json` [TO BUILD] | TBD | "Alliance > Production" |

**Total player nodes: 129.** Researched in the **Academy** (player) or **Alliance HQ** (alliance, accessed via Hall of Alliance).

### 6.2 Research node format

```json
"infantry_hp": [
  {
    "level": "1",
    "resources": [
      { "type": "food", "value": "500" },
      { "type": "lumber", "value": "250" },
      { "type": "stone", "value": "750" },
      { "type": "gold", "value": "1500" }
    ],
    "requirements": [
      { "type": "academy", "level": "1" }
    ],
    "stats": {
      "ability": "Infantry HP",
      "ability_relations": "Multiply",
      "ability_value": "0.01"
    },
    "time": "60",
    "power": "50"
  },
  ...
]
```

- `ability_relations: Multiply` means the buff is **additive** (despite the name — this is LoK convention; the value is added to a sum, then `(1 + sum)` is multiplied with the base stat).
- `ability_value: 0.01` = +1% per level.
- `requirements` are AND-conditions (all must be met to research).

### 6.3 Battle Tree summary (55 nodes)

Phase 1 — basic stats (tiered by Academy level 1-9):
- `infantry_hp` / `ranged_hp` / `cavalry_hp` (5 levels each)
- `infantry_def` / `ranged_def` / `cavalry_def` (5 levels each)
- `infantry_atk` / `ranged_atk` / `cavalry_atk` (5 levels each)
- `infantry_spd` / `ranged_spd` / `cavalry_spd` (5 levels each)
- `troops_storage` (5 levels) — global troop cap

Phase 2 — tier-2 troops (Academy 10+):
- `warrior` / `longbow_man` / `horseman` (1 level each, unlock T2)
- Training Amount / Speed / Cost per troop type (5 levels each, 9 nodes)

Phase 3 — march & T3 (Academy 14-16):
- `march_size` (5 levels) — march cap per slot
- `march_limit` (1 level) — adds an extra march slot
- `knight` / `ranger` / `heavy_cavalry` (1 level each, unlock T3)

Phase 4 — generic stat boosts & T4 (Academy 17-23):
- `troops_spd` / `troops_hp` / `troops_def` / `troops_atk` (10 levels each)
- `hospital_capacity` / `healing_time_reduced` (10 levels each)
- `guardian` / `crossbow_man` / `iron_cavalry` (1 level each, unlock T4)

Phase 5 — advanced stats & T5 (Academy 24-30):
- `rally_attack_amount` (10 levels)
- `advanced_*_hp` / `*_def` / `*_atk` / `*_spd` per troop type (30 nodes total)
- `crusader` / `sniper` / `dragoon` (1 level each, unlock T5)

### 6.4 Production Tree summary (34 nodes)

Tier 1 (Academy 1-11):
- `food_production` / `wood_production` / `stone_production` / `gold_production` (5 levels each)
- `food_capacity` / `wood_capacity` / `stone_capacity` / `gold_capacity` (5 levels each)
- `food_gathering_speed` / `wood_gathering_speed` / `stone_gathering_speed` / `gold_gathering_speed` (5 levels each)

Tier 2 (Academy 13+):
- `infantry_storage` / `ranged_storage` / `cavalry_storage` (5 levels each) — per-type cap
- `research_speed` (5 levels)
- `construction_speed` (5 levels)
- `resource_protect` (5 levels)

Tier 3 advanced (Academy 22+):
- `advanced_*_production` / `*_capacity` / `*_gathering_speed` per resource (10 levels each)

### 6.5 Advanced Tree summary (40 nodes)

Late-game tree, gated by Academy 23+.

- `resource_production` (10 levels) — flat global production multiplier
- **Counter buffs**: `infantry_*_against_archer`, `archer_*_against_cavalry`, `cavalry_*_against_infantry` — extra HP/Def/Atk specifically against the countered type (10 levels each, 9 nodes total)
- `resource_capacity` (10 levels)
- **Castle Defense**: `castle_defending_*_hp/def/atk` per troop type (9 nodes)
- `resource_protect` (Advanced version, 10 levels)
- **Composition buffs**: stats boosted when the march is composed of only one troop type (`infantrys_*_when_composed_of_infantry_only` etc., 9 nodes)
- **Rally buffs**: stats boosted when participating in a Rally (`*_when_participating_a_rally`, 10 nodes including troop_speed_when_participating_a_rally)

### 6.6 Alliance Trees [TO BUILD]

The Alliance Tree exists in two tabs: **Battle** and **Production**. These are researched at the alliance level (cost paid from alliance treasury, time elapses globally), and the buff applies to all alliance members.

Suggested Alliance research nodes [DEFAULT — to be specified later]:

**Alliance Battle:**
- Member capacity expansion (30 → 40 → 50 → 60 → 75 over 5 levels)
- Alliance territory combat buff (member troops fight stronger when within alliance territory)
- Reinforcement capacity
- Rally capacity boost
- Alliance March Speed

**Alliance Production:**
- Alliance Help boost (more % per click, more clicks allowed)
- Alliance Resource Pool size (alliance treasury cap)
- Trading Post efficiency
- Daily Alliance Gift quality

Final Alliance research data files to be created during Phase 3.

### 6.7 Research execution

- **One research at a time** by default.
- Resources are consumed at research start.
- Cancellation refunds 50% [DEFAULT].
- Research time is reduced by `research_speed` buffs (additive: VIP, base research, advanced research, treasures).
- Research cannot be abandoned if it provides a precondition for an active feature (e.g., cannot un-research T2 unlock if you have T2 troops).


---

## 7. Map System

### 7.1 Map dimensions

The world map is **1024 × 1024 tiles**. Coordinates are integer (x, y) with origin at (0, 0) in the top-left corner.

Total tiles: **1,048,576**.

### 7.2 Tile types

Every tile has a `terrain_type` (visual) and an `occupant` (logical):

**Terrain (visual only):**
- Plains
- Forest
- Mountains
- Water (impassable for marches; blocks city placement)
- Desert (no special effect, visual only)
- Snow (visual only)

Terrain distribution is generated at world creation, biased toward thematic clusters (a forest band, a mountain range, etc.). For MVP, a procedural generator with a fixed seed per world produces a stable, replayable map.

**Occupants (logical):**
- Empty
- Player city (2×2 footprint)
- Alliance fortress / outpost (2×2 or 3×3 footprint)
- Shrine — 22 in total (4×4 footprint, see §16)
- Congress — 1 (4×4 footprint, central)
- Field Object (Farm/Forest/Quarry/Goldmine/GEM Node — 1×1 footprint)
- Monster (1×1 footprint)
- Gem Node (1×1 rare spawn, see §3.7)

A tile can host only one occupant. Player cities cannot be placed on water.

### 7.3 Footprint examples

A player city occupies a 2×2 area: if anchored at (x, y), it owns tiles (x, y), (x+1, y), (x, y+1), (x+1, y+1).

A Shrine occupies a 4×4 area: if anchored at (x, y), it owns 16 tiles from (x, y) to (x+3, y+3).

### 7.4 Field Objects (Resource Nodes)

Resource nodes spawn passively across the map. Source: `data/field_objects.json`.

Five types:

| Type | Resource | Levels | Drops |
|---|---|---|---|
| Farm | Food | 1-10 | Resource Boxes (LV1-LV4), Speedups |
| Forest | Lumber | 1-10 | Resource Boxes, Speedups |
| Quarry | Stone | 1-10 | Resource Boxes, Speedups |
| Gold Mine | Gold | 1-10 | Resource Boxes, Speedups |
| Gem Node | GEMS | 1-3 | Direct GEMS (50/100/200) — rare spawn |

Higher levels yield more resources per gather but are typically further from spawn or in contested areas.

**Spawning logic** [DEFAULT — tune per world]:
- Initial seed at world creation: ~3000 nodes total, distributed by level (more low-level nodes near edges, high-level near center)
- Respawn: when a node is depleted, a new node of similar type spawns in the same map sector (within 50 tiles) after 30 minutes
- Gem Nodes are rarer and can spawn anywhere on the map (not gated by region)

### 7.5 Gathering

To gather, a player sends troops via a March (see §8) of type `MARCH_TYPE_GATHER`.

```
gather_speed = base_gather_rate(node_level)
             × (1 + sum(additive_gather_buffs))
             × prod(1 + multiplicative_gather_buffs)
             × world.gather_factor
```

Buffs include:
- Research `food_gathering_speed` etc. + `advanced_*_gathering_speed`
- VIP `Gathering Speed` bonus
- Charm `Farm Speed` (matching resource)
- Treasure `*_Gathering_Speed` boosts
- Active Boost Item `ITEM_CODE_GATHERING_BOOST_8H/1D`

Gather capacity per march = SUM(troop_count × carry). When capacity is full or node is depleted, troops return automatically.

### 7.6 Combat at gathering nodes

While gathering, troops are vulnerable. Another player can attack a gathering march:

- **Ramming the gather**: Attacker sends an attack march to the same node. On arrival, defender's gathering troops fight the attacker. If attacker wins, they take whatever resources are on-march from the gather plus normal battle losses apply.
- This is the core "open-field PvP" mechanic. Unprotected gathers near contested zones invite attacks.

### 7.7 Map rendering (client)

The map is rendered client-side using **HTML5 Canvas 2D**. The renderer is in `public/js/map.js`.

Key requirements:

- **Pannable**: drag with mouse, swipe on touch
- **Zoomable**: mouse wheel + pinch on touch, 4 zoom levels (1×, 2×, 4×, 8×)
- **Tile streaming**: load only visible tiles + 1 buffer ring; unload off-screen tiles
- **Click handlers**: on tile click, request occupant info from API
- **Visual style**: similar to Tribal Wars / classic browser strategy games — colored tile backgrounds with sprites for buildings, monsters, and players

For MVP, a simplified map view is acceptable: simple colored tiles with icons. Polished art comes in Phase 2.

### 7.8 Player spawn placement

When a new player creates a character, they are placed at a random valid map position:

- Within 200 tiles of the map edge (not center)
- Not within 50 tiles of another player city
- Not within 100 tiles of any Shrine
- On Plains terrain (not Water/Mountains)

If no valid spot exists in a region, fall back progressively (loosen distance constraints).

### 7.9 Map sectors (kingdoms)

For organization and event scoping, the map is divided into **8 kingdoms** (sectors). Each kingdom is a 256 × 512 region (4 columns × 2 rows over the 1024 × 1024 map). Players are assigned a kingdom at spawn based on their spawn location.

Alliance membership is **not** restricted to one kingdom (alliances are global), but kingdom identity matters for:
- Daily kingdom rankings
- Some tutorial messages
- Future inter-world events

This concept is from the LoK source data (`field_object` type `kingdom`). MVP can ignore kingdom mechanics and treat the whole map uniformly; kingdom features come in Phase 2+.

### 7.10 World Spawn System

The world map is populated by a **fixed-schedule hourly spawn cron**. Every hour at xx:00 UTC (server time), the spawn tick runs across all worlds and all 8 sectors:

```
Cron: 0 * * * *  /usr/bin/php /path/to/cron/world_spawn_tick.php
```

**Algorithm:**

```python
for each world:
    for each sector (0..7):
        for each entity_type in spawn_table:
            # Check sector cap — skip if already at maximum
            current_count = count_entities_in_sector(world, sector, entity_type)
            if current_count >= entity_type.cap_per_sector:
                continue
            
            # Compute spawn count (Poisson-like for fractional rates)
            rate = entity_type.spawn_per_sector_per_hour
            if rate >= 1:
                spawn_count = floor(rate) + (1 if random() < (rate - floor(rate)) else 0)
            else:
                spawn_count = 1 if random() < rate else 0
            
            # Don't exceed cap
            spawn_count = min(spawn_count, entity_type.cap_per_sector - current_count)
            
            for i in range(spawn_count):
                place_entity_at_random_valid_tile(world, sector, entity_type)
```

**Spawn Table (per sector per hour):**

Source-of-truth: `data/world_spawn.json`. Values for tuning during balancing.

| Entity Type | Lv | Spawn/Sector/h | Cap/Sector | Despawn (h) | Notes |
|---|---|---|---|---|---|
| Resource Mine (Farm/Forest/Quarry/Gold Mine) | 1-3 | 60 | — | 24 (if not gathered) | Massenangebot for new players |
| Resource Mine | 4-6 | 40 | — | 24 | Mid-tier |
| Resource Mine | 7-8 | 20 | — | 24 | High-tier |
| Resource Mine | 9-10 | 10 | — | 24 | Endgame contested |
| GEM Node Lv 1 (50 GEMS) | — | 1.0 | 10 (combined) | 12 | Free-to-play GEM trickle |
| GEM Node Lv 2 (100 GEMS) | — | 0.4 | 10 (combined) | 12 | Rarer |
| GEM Node Lv 3 (200 GEMS) | — | 0.1 | 10 (combined) | 12 | ~10/day world-wide |
| Orc | 0-3 | 30 | 500 (all monsters combined) | 12 | Tutorial-friendly |
| Orc | 4-6 | 25 | — | 12 | Mid |
| Orc | 7-8 | 15 | — | 12 | Charm Tier 3 |
| Orc | 9-10 | 8 | — | 12 | Charm Tier 4 (Legendary farm) |
| Skeleton | 1-3 | 30 | — | 12 | (mirror of Orc distribution) |
| Skeleton | 4-6 | 25 | — | 12 | |
| Skeleton | 7-8 | 15 | — | 12 | |
| Skeleton | 9-10 | 8 | — | 12 | |
| Golem | 1-3 | 30 | — | 12 | (mirror of Orc distribution) |
| Golem | 4-6 | 25 | — | 12 | |
| Golem | 7-8 | 15 | — | 12 | |
| Golem | 9-10 | 8 | — | 12 | |
| Treasure Goblin | 1-2 | 6 | 80 (all goblin levels combined) | 0.5 | Despawns in 30 min |
| Treasure Goblin | 3 | 4 | — | 0.5 | F2P standard workhorse |
| Treasure Goblin | 4 | 2 | — | 0.5 | Mid-tier |
| Treasure Goblin | 5 | 1 | — | 0.5 | Endgame F2P-best |
| Deathkar | 1-3 | 2 | 30 | 12 | Mid-Rally content |
| Deathkar | 4-6 | 1 | — | 12 | High-Rally |
| Deathkar | 7-10 | 0.3 | — | 12 | Endgame-Rally |
| Green Dragon | 1 | 0.5 | 5 (all dragons combined) | 12 | Rare endgame |
| Green Dragon | 2 | 0.2 | — | 12 | Sehr rare |
| Green Dragon | 3 | 0.05 | — | 12 | ~10/day world-wide |
| Red Dragon | 1-3 | (gleiche Verteilung wie Green Dragon) | 5 (combined) | 12 | |
| Gold Dragon | 1-3 | (gleiche Verteilung wie Green Dragon) | 5 (combined) | 12 | |
| Magdar | 1 | 0.05 | 1 | 24 | Endgame Boss |
| Magdar | 2 | 0.02 | 1 | 24 | Sehr endgame |
| Magdar | 3 | 0.005 | 1 | 24 | ~1/day world-wide |

**Despawn rules:**

- Field Objects (Resource Mines, GEM Nodes): despawn after listed hours if not actively being gathered
- Monsters (Orc/Skel/Golem/Deathkar/Dragons/Magdar): despawn after listed hours if not engaged
- Treasure Goblin: despawns after 30 minutes regardless (intentional urgency, see §14.13)
- A separate cron runs every 5 minutes to remove entities past their despawn timer

**Spawn placement rules:**

- New entity placed on a random empty tile within the sector
- Cannot spawn within 5 tiles of a player city
- Cannot spawn within 30 tiles of any Shrine (preserves clean Conquest area)
- Cannot spawn on Water/Mountains terrain
- Higher-level entities (Magdar, dragons, Lv 9-10 monsters) prefer center-of-sector spawn (away from spawn-edge)

**Why a fixed hourly schedule (not per-entity timers):**

- One cron job for the whole world = simpler ops
- Predictable for players ("the 14:00 spawn just happened, let me check the map")
- Server load is concentrated in one hour-tick, easier to monitor and optimize
- Matches the Caravan refresh design (also fixed wall-clock, see §4.9.3)


---

## 8. March System

### 8.1 March types

A March is any troop movement on the map. Source enum: `MARCH_TYPE_*` from `enum.py`.

| Type Code | Name | Description |
|---|---|---|
| 1 | `gather` | Send troops to a Field Object to gather resources |
| 2 | `attack` | Attack another player's city or gathering troops |
| 5 | `monster` | Attack a Monster on the map |
| 7 | `support` | Send reinforcements to an alliance member |
| 8 | `rally` | Join or initiate a Rally (multi-player coordinated attack) |
| 9 | `spy` | Send a spy mission (no troops, see §11) |

A march occupies a **March Slot**. When the march completes (arrives, fights, returns), the slot is freed.

### 8.2 March slots

Each player has a base of **2 march slots**, expandable:

| Source | Slots Added |
|---|---|
| Default | 2 |
| Research `march_limit` (Battle Tree, Academy 15, 1 level) | +1 (= 3 total) |
| VIP 6+ "Troop Dispatch Queue" | +1 (= 4 total) |
| VIP 17+ | +1 (= 5 total) |

Maximum: **5 march slots** in the late-game.

A spy march occupies a slot just like a troop march.

### 8.3 March speed formula

The march time formula is calibrated against owner-provided test data:

```
march_time_seconds = floor(distance_tiles × 100 / troop_speed / world.speed_factor)

distance_tiles = sqrt((x2 - x1)² + (y2 - y1)²)
troop_speed    = MIN(speed of all troops in the march)
world.speed_factor = 1.0 (default world); higher for "express" worlds
```

**Verified with owner's test case:**
- Origin (3, 2939), target (17, 2939) → distance = 14 tiles
- T1 Infantry (Fighter, speed 65): floor(14 × 100 / 65) = **21s** ✓
- T1 Ranged (Hunter, speed 75): floor(14 × 100 / 75) = **18s** ✓
- T1 Cavalry (Stableman, speed 95): floor(14 × 100 / 95) = **14s** ✓

**Speed buffs** modify `troop_speed`:

```
effective_speed = troop_speed
                × (1 + sum(additive_speed_buffs))
                × prod(1 + multiplicative_speed_buffs)
```

Buffs include:
- `*_spd` research (Battle Tree, per troop type)
- `troops_spd` research (generic)
- `advanced_*_spd` research
- `troop_speed_when_participating_a_rally` (Advanced, Rally only)
- VIP, charms (`Speed` charm), treasures with speed boosts

### 8.4 March cap (size)

Each march has a maximum troop count cap:

```
march_cap = base_cap(player)
          × (1 + sum(additive_march_cap_buffs))
          × prod(1 + multiplicative_march_cap_buffs)
```

Sources:
- `march_size` research (Battle Tree, 5 levels)
- VIP "Marching Troop Capacity" bonus (kicks in at VIP 6+)
- Treasures with `Marching Troop Capacity` boost

`base_cap(player)` = function of player level / Castle level. [DEFAULT — start at 5,000 troops, scales to ~50,000 at endgame.]

### 8.5 March states

A march progresses through states:

```
QUEUED → MARCHING → ARRIVED → RESOLVING → RETURNING → COMPLETE
```

- **QUEUED**: just dispatched, server validation in progress
- **MARCHING**: en route to target, `arrive_time` set
- **ARRIVED**: arrival timestamp passed; cron tick will resolve
- **RESOLVING**: actively being processed (battle calc running, gather collecting)
- **RETURNING**: heading back to origin city, `return_time` set
- **COMPLETE**: troops returned to city, slot freed

States are enforced by `cron/tick.php` running every 60 seconds. The tick processes any march where `arrive_time <= now()` and resolves it (battle, gather, etc.).

### 8.6 Cancellation / recall

A player can recall a marching troop column at any time before arrival. The recalled troops then march back from current position.

- **Recall** while in MARCHING state: troops turn around immediately, return time = elapsed march time.
- **Cannot recall**: a march in RESOLVING state.
- **Auto-return**: troops automatically return on COMPLETE.


---

## 9. Battle System

### 9.1 Overview

Battles are resolved server-side, deterministically, with a **single-pass** damage calculation. Both sides attack simultaneously. The side with the lower remaining-troop ratio loses; both sides typically take losses.

This is **Model C** from design discussion: stat-multiplier with counter cycle, no multi-round, no front-line / back-line ordering.

### 9.2 Buff stack (the canonical buff engine)

Two buff categories: **Additive** and **Multiplicative**. Implementation in `app/services/BuffStackEngine.php`.

```
ADDITIVE buffs (sum first, then apply as one factor):
- VIP bonuses
- Player research (Battle Tree, Production Tree, Advanced Tree)
- Active Charm effects
- Alliance research (when in alliance territory or rally)
- Wall Defense Mortality Reduction (constant 30%, defender at home only)

MULTIPLICATIVE buffs (each is its own factor, all multiplied together):
- Treasure boosts (each treasure stat is its own factor)
- Counter-cycle modifier (×1.30 / ×1.00 / ×0.70)
- Wall Attack/Defense buff (defender at home only, scales with wall level)
- Composition buffs (single-type march, Advanced Tree)
- Rally buffs (Advanced Tree, when in rally)
- Active Boost Items (e.g. resource production boosts)
```

### 9.3 Effective stats

For each troop in battle, compute effective Attack, HP, Defense:

```python
def effective_attack(troop, role, context):
    base = troop.attack
    additive = (
        vip.attack_bonus
        + research.troops_atk[troop.type]
        + research.advanced_troops_atk[troop.type]
        + (research.composition_atk if context.is_single_type_march else 0)
        + (research.rally_atk if context.is_rally else 0)
        + active_charm.attack_bonus_if_matching
    )
    multiplicative_factors = [
        1 + treasure.attack_boost_total,
        1 + (wall.atk_buff if context.is_defender_at_home else 0),
        counter_modifier(troop.type, context.enemy_dominant_type),
    ]
    
    result = base * (1 + additive)
    for f in multiplicative_factors:
        result *= f
    return result

def effective_hp(troop, role, context):
    base = troop.hp
    additive = (
        vip.hp_bonus
        + research.troops_hp[troop.type]
        + research.advanced_troops_hp[troop.type]
        + (research.composition_hp if context.is_single_type_march else 0)
        + (research.rally_hp if context.is_rally else 0)
        + active_charm.hp_bonus_if_matching
    )
    multiplicative_factors = [
        1 + treasure.hp_boost_total,
    ]
    return base * (1 + additive) * prod(multiplicative_factors)

def effective_defense(troop, role, context):
    base = troop.defense
    additive = (
        vip.defense_bonus
        + research.troops_def[troop.type]
        + research.advanced_troops_def[troop.type]
        + research.castle_defending_*_def[troop.type] if context.is_defender_at_home
        + (research.composition_def if context.is_single_type_march else 0)
        + (research.rally_def if context.is_rally else 0)
        + active_charm.defense_bonus_if_matching
    )
    multiplicative_factors = [
        1 + treasure.defense_boost_total,
        1 + (wall.def_buff if context.is_defender_at_home else 0),
    ]
    return base * (1 + additive) * prod(multiplicative_factors)
```

### 9.4 Counter-cycle modifier

The counter modifier is computed against the **enemy's dominant troop type** (the type with the highest count or highest power contribution — design choice: use highest power contribution).

```
counter_modifier(my_type, enemy_dominant_type):
  if my_type counters enemy_dominant_type:    return 1.30
  if enemy_dominant_type counters my_type:    return 0.70
  else:                                       return 1.00
```

Counter cycle:
- Infantry counters Ranged
- Ranged counters Cavalry
- Cavalry counters Infantry

If the enemy is balanced (no clear dominant type — within 20% of each other), the modifier is **1.00**.

### 9.5 Single-pass damage resolution

```python
def resolve_battle(attacker_troops, defender_troops, context):
    # Step 1: compute effective stats for every troop on each side
    att_stats = [(t, count, effective_attack(t, 'attacker', context)) for t, count in attacker_troops]
    def_stats = [(t, count, effective_attack(t, 'defender', context)) for t, count in defender_troops]
    
    # Step 2: total damage pool each side can deal
    attacker_damage_pool = sum(count * eff_atk for _, count, eff_atk in att_stats)
    defender_damage_pool = sum(count * eff_atk for _, count, eff_atk in def_stats)
    
    # Step 3: total HP pool each side can absorb
    # Effective HP = HP + Defense (Defense soaks damage 1:1 in this model)
    attacker_total_hp = sum(count * (effective_hp(t, ...) + effective_defense(t, ...))
                            for t, count in attacker_troops)
    defender_total_hp = sum(count * (effective_hp(t, ...) + effective_defense(t, ...))
                            for t, count in defender_troops)
    
    # Step 4: loss ratios (bounded 0..1)
    attacker_loss_ratio = min(1.0, defender_damage_pool / attacker_total_hp)
    defender_loss_ratio = min(1.0, attacker_damage_pool / defender_total_hp)
    
    # Step 5: per-troop losses (proportional)
    attacker_losses = {t: floor(count * attacker_loss_ratio) for t, count in attacker_troops}
    defender_losses = {t: floor(count * defender_loss_ratio) for t, count in defender_troops}
    
    # Step 6: split losses into wounded/dead via mortality rate (see §10)
    attacker_wounded, attacker_dead = split_losses(attacker_losses, attacker.mortality_rate)
    defender_wounded, defender_dead = split_losses(defender_losses, defender.mortality_rate)
    
    # Step 7: wounded go to hospital (capacity-capped; overflow → dead)
    attacker_wounded_actual = clamp_to_hospital(attacker_wounded, attacker.hospital_capacity)
    defender_wounded_actual = clamp_to_hospital(defender_wounded, defender.hospital_capacity)
    
    # Step 8: determine winner
    if attacker_loss_ratio < defender_loss_ratio:
        winner = "attacker"
    elif defender_loss_ratio < attacker_loss_ratio:
        winner = "defender"
    else:
        winner = "draw"
    
    # Step 9: if attacker won and target was a city, plunder + wall damage
    if winner == "attacker" and context.target_is_city:
        plundered = compute_plunder(attacker_surviving_carry, defender_resources, defender_protect_buffs)
        defender_resources -= plundered
        defender_wall_hp -= compute_wall_damage(attacker_damage_pool)
        if defender_wall_hp <= 0:
            teleport_city_random(defender)
    
    # Step 10: build battle report, return surviving troops to their cities
    return BattleReport(...)
```

### 9.6 Wall damage during city attack

When attacking a player's home city, the **Wall HP** also takes damage. The damage to the wall is computed in parallel with the troop damage:

```
wall_damage_received = attacker_damage_pool × wall_damage_coefficient

wall_damage_coefficient = 0.10   [DEFAULT — 10% of attacker damage targets the wall]

defender_wall_hp_after = max(0, defender_wall_hp - wall_damage_received)
```

If `defender_wall_hp_after == 0`, trigger **teleport on city** (see §4.7.2).

### 9.7 Plunder calculation

```
attacker_surviving_carry = sum(surviving_troop_count × carry) for all attacker troops

per_resource:
    defender_resource_at_risk = max(0, defender_amount - protected_amount(resource))
    plundered = min(defender_resource_at_risk, attacker_surviving_carry × world.haul_factor)
    
total_plundered = sum across resources, capped by carry
```

Plundered resources are loaded onto returning troops; they arrive at attacker's city when the march returns.

### 9.8 Battle reports

Every battle generates a **Battle Report** for both attacker and defender. The report contains:

- Timestamp, both player names, target type (city / gather / monster)
- Attacker forces (per troop type, count, surviving, wounded, dead)
- Defender forces (same)
- Buff summary (which buffs were active for each side)
- Damage dealt by each side (totals)
- Wall damage (if city attack)
- Resources plundered
- Battle outcome (winner)
- Wall break / teleport notice (if applicable)

Reports are stored in DB indefinitely. Players can mark them read; old reports auto-purge after 60 days [DEFAULT].

### 9.9 Battle vs Monster

When attacking a Monster (`MARCH_TYPE_MONSTER`):

- Monster has fixed stats (HP, Attack, Defense, count) from `data/monsters.json`.
- Monster does NOT have a counter type (treated as neutral, modifier always 1.00) — alternative: assign type based on monster lore [OPEN]. Default: neutral.
- Attacker uses normal effective stats.
- Monster takes damage; if HP × count is depleted, monster dies → drops fire.
- Attacker takes losses per normal mortality rules.
- **Stamina (Action Points)** is consumed (see §14).

### 9.10 Battle vs Shrine garrison

Shrines, on first attempt, are defended by an NPC garrison. See §16 for garrison composition.

- Attack must be a **Rally** (`MARCH_TYPE_RALLY`). Solo attacks rejected.
- All Rally participants' troops fight as a single combined force.
- Rally bonuses (Advanced Tree) apply.
- Counter modifier uses the garrison's dominant type (set per shrine in shrine config).
- After garrison is defeated, the shrine becomes "open" — the alliance that wins captures.

### 9.11 Determinism

Battle resolution is **fully deterministic**. Given the same inputs (troops, buffs, context), the output is always identical. No randomness in damage rolls. This:
- Makes battle reports reproducible (debugging)
- Prevents "lucky win" complaints
- Allows balance modeling via formulas

The only randomness in the system is in:
- Monster drops (rolled per drop slot)
- Charm drops from monsters
- Treasure chest contents
- City teleport target location after wall break


---

## 10. Hospital & Mortality

### 10.1 Mortality rate

After a battle determines troop losses, those losses are split into **wounded** and **dead**.

```
mortality_rate = base_mortality_rate                       # 0.0 in MVP
              + research_mortality_rate                    # [PHASE 2 — not in MVP]
              + treasure_mortality_rate                    # only for Mortality treasure stats
              - vip_mortality_reduction                    # up to -30% at VIP 20
              - wall_defense_mortality_reduction           # -30% constant for defender at home

mortality_rate = clamp(mortality_rate, 0.0, 1.0)
```

For **MVP** the `base_mortality_rate` is **0.0**. This means by default:
- All attacker losses are **wounded** (heal at attacker's hospital)
- All defender losses are **wounded** (heal at defender's hospital)
- Effectively no permanent troop loss

**This makes early gameplay very forgiving** — players can attack and recover. Hospital becomes the bottleneck; if a player's hospital is full, additional losses become permanent.

In **Phase 2** the system can be activated by raising `base_mortality_rate` to e.g. 0.3, which means 30% of losses are dead even before any reductions. This change will increase pressure on resource management and treasury investment.

### 10.2 Hospital capacity

```
hospital_capacity = base_capacity[hospital_level]
                  × (1 + research.hospital_capacity_total)
                  × (1 + treasure.hospital_capacity_boost)
```

`base_capacity` per Hospital level is in `data/buildings/hospital.json`.

The `hospital_capacity` research (Battle Tree) provides up to +100% across 10 levels (10% per level).

### 10.3 Wounded queue

Wounded troops occupy hospital capacity. Healing happens automatically over time:

```
heal_time_per_troop = troop.heal_time
                    × (1 - research.healing_time_reduced_total)
                    × (1 - treasure.healing_speed_total)
                    × (1 - vip.healing_speed)
```

When healed, a wounded troop returns to the active troop pool.

### 10.4 Healing cost

Healing costs **resources** [DEFAULT — start at 50% of training cost], paid per troop healed. Player can pause healing if resources are insufficient.

A player can pay GEMS or use Recover items (`ITEM_CODE_RECOVER_*`) to instantly finish hospital healing.

### 10.5 Hospital overflow

When the hospital is full and a battle produces more wounded:

```
wounded_overflow = max(0, new_wounded - (hospital_capacity - currently_wounded))
overflow_troops_become_dead
```

This is the primary way troops die permanently in MVP.

### 10.6 Why mortality is staged this way

The phased approach:

1. **MVP (mortality 0)**: encourages experimentation, lower frustration for new players. They lose troops temporarily but can heal. Hospital capacity becomes the strategic bottleneck (when full, losses become permanent).
2. **Phase 2 (mortality enabled)**: introduces resource pressure and player-vs-treasury tension. VIP / treasure investment now matters for "saving" troops.
3. **Phase 3+**: mortality may scale with Conquest Event participation (e.g., shrine defense kills are 100% mortality on the attacker, encouraging cautious commitment).

The DB schema includes a `mortality_rate` column on the world config table from day 1, defaulting to 0.0. Activation requires only a config update.

---

## 11. Spy System

### 11.1 Sending a spy

A player initiates a spy mission by clicking on a foreign city (or alliance structure) on the map and choosing "Spy".

- **No troops are sent.** The mission is abstract.
- **Resource cost** to send a spy [DEFAULT — to be tuned]:
  - 50,000 Gold + 20,000 Lumber per mission
- **March slot** is occupied for the duration of the spy mission.
- **Spy mission duration** = same as a march to the target (using a dedicated "spy" speed = 200) [DEFAULT]:
  ```
  spy_time_seconds = floor(distance_tiles × 100 / 200 / world.speed_factor)
  ```
  This makes spies faster than any troop (T5 Cavalry max speed ≈ 110 + buffs). Effectively a spy at distance 100 tiles = 50 seconds.

### 11.2 Spy reach limit

A spy can only target a player whose **Castle level** is within ±10 of the spy's own Castle level.

```
abs(target.castle_level - my.castle_level) <= 10
```

This prevents:
- New players from spying powerful end-game players (irrelevant info)
- End-game players from stalking new players (anti-griefing)

The UI shows a lock icon and tooltip "Out of spy range" on cities outside the limit.

### 11.3 Spy success / failure

A spy is **always successful** unless the target has an **Anti-Reconnaissance** buff active.

| Anti-Recon Item | ID [ASSUMED] | Duration |
|---|---|---|
| Anti-Reconnaissance 8h | 10102011 | 8 hours |
| Anti-Reconnaissance 24h | 10102012 | 24 hours |

(IDs assumed based on enum.py gap; verify against final game data.)

When Anti-Recon is active:
- Spy mission "fails"
- Attacker is notified: "Spy mission failed. Target may have anti-reconnaissance active."
- Defender is notified: "Someone tried to spy on your city. Anti-reconnaissance blocked the attempt."
- Spy resources are still consumed (no refund).

### 11.4 Spy report contents

A successful spy report contains:

- **Target name + coordinates**
- **Castle level**
- **All building levels** (visual layout of city)
- **All research levels** (every research node + level)
- **All troop counts** (per type and tier)
- **All resources** (Food, Lumber, Stone, Gold)
- **Equipped Treasures** (with their stats)
- **Active Charms** (with remaining duration)
- **Power score**
- **Alliance name + tag** (if any)
- **Active boost items** (e.g. resource production boost, anti-recon if visible)
- **Buff summary**: full additive + multiplicative buff list with totals as percentages

Spy reports persist in DB. Old reports auto-purge after 7 days [DEFAULT] since their accuracy decays quickly.

### 11.5 Spy on alliance structures

Spies can target alliance fortresses, outposts, and shrines (when held by an alliance). Reports include:

- Garrison composition (troop types and counts)
- Captured shrine status (if relevant)
- Defender alliance name/tag
- Buff summary

Spy on Congress and Shrines is allowed for everyone but more expensive [DEFAULT — 3× cost].

### 11.6 Spy on monsters

Spy is **not** allowed on monsters. Monster stats are public (visible in tooltip).


---

## 12. Treasure System

### 12.1 Slots and unlock

Each player has up to **6 Treasure Slots**. Slots are unlocked by Treasure House level:

| Treasure House Level | Slots Unlocked |
|---|---|
| 1 | 2 |
| 5 | 3 |
| 10 | 4 |
| 20 | 5 |
| 25 | 6 |

Equipping a treasure into a slot is instant and free. Removing is also instant. A treasure can only be in one slot at a time.

### 12.2 Treasure inventory

Treasures are stored in the player's inventory. There are **77 unique treasures** (initial set, source: `data/treasures.json`), distributed by **Grade**:

| Grade | Count | Stats per Treasure | Star Levels (max) |
|---|---|---|---|
| Normal (Grey) | 14 | 4 (2 Boost + 2 Master) | 5 |
| Rare (Blue) | 15 | 6 (3 Boost + 3 Master) | 5 |
| Epic (Violet) | 26 | 8 (4 Boost + 4 Master) | 5 |
| Legendary (Gold) | 16 | 10 (5 Boost + 5 Master) | 5 |
| Mythic (Turquoise) | 6 | 10 (5 Boost + 5 Master) | 5 |

Mythic treasures have the same stat count as Legendary but **stronger numerical values** (e.g. Mythic Infantry HP +55% vs Legendary +25%).

### 12.3 Treasure boosts vs master bonuses

Each treasure has two halves to its stats:

- **Treasure Boosts** (left column in UI): always-on stat bonuses. Visible from level 1 (1-star).
- **Master Bonuses** (right column in UI): additional stat bonuses, **locked initially**. They unlock as Treasure Boosts are upgraded.

```
Star 1: Treasure equipped, Boost 1 active.
Star 2: Boost 1 maxed (after 5 upgrade levels). Master 1 unlocks.
Star 3: Master 1 maxed. Boost 2 unlocks (or maxed if it was step-2).
...etc.
```

Specifically (per owner spec): when a Treasure Boost is upgraded **5 levels** (fully maxed), the corresponding Master Bonus unlocks.

**No active skills.** All Master Bonuses are passive % stat increases. (Initial LoK data included active skills like Battle Cry / Teleport / Summon Monster — these are EXCLUDED from Conquer.)

### 12.4 Fragments

Each treasure is built from **Item Fragments**. Fragments come from monster drops, chests, events, and the Caravan trade.

- **Initial unlock**: collect **10 Item Fragments** of a treasure to unlock (instantiate) the treasure at 1-star.
- **Upgrade to next star**: requires more fragments per upgrade tier:

| Upgrade | Fragments Required |
|---|---|
| 1 → 2 stars (max Boost 1) | 10 |
| 2 → 3 stars | 20 |
| 3 → 4 stars | 30 |
| 4 → 5 stars | 40 |
| 5 → 6 stars [if Master tier 5] | 50 |

Total to fully upgrade one treasure: 10 (unlock) + 10+20+30+40+50 = **160 fragments**.

### 12.5 Generic Piece Fragments

In addition to per-treasure fragments, generic **Piece Fragments** drop from monsters and chests. These can be exchanged for specific treasure fragments at an exchange rate per grade:

| Grade | Pieces per Fragment |
|---|---|
| Normal | 1 |
| Rare | 5 |
| Epic | 25 |
| Legendary | 100 |
| Mythic | 500 |

[DEFAULT — tune for economy balance]

This gives players a reliable, slow path to specific high-end treasures while monster drops provide RNG bursts.

### 12.6 Buff stack from treasures

When a treasure is equipped:

- All its **active stats** (Boost + unlocked Master Bonuses) are applied to the player's effective stat calculations.
- Treasures stack **multiplicatively** with each other (each treasure's stat is its own factor in the multiplicative chain).

Example: equipping Magic Plow (Rare, Food production +10%) AND Manure (Normal, Food Production +5%):
```
food_production_multiplier = 1.10 × 1.05 = 1.155 → +15.5% (not +15%)
```

### 12.7 Treasure stats reference

Stats used by treasures in `data/treasures.json` include:

- Production: `Food/Lumber/Stone/Gold Production`
- Storage: `Food/Lumber/Stone/Gold Storage`
- Gathering: `Food/Lumber/Stone/Gold Gathering Speed`
- Protection: `Food/Lumber/Stone/Gold Protection`, `All Resource Production`
- Combat: `Infantry/Archery/Cavalry Attack/Defense/HP/Speed`
- Carry: `Infantry/Archery/Cavalry Load`
- Generic: `Troops Attack/Defense/HP`
- Special: `Marching Troop Capacity`, `Research Speed`, `Construction Speed`, `Healing Speed`, `vs Monster HP/Defense/Attack`, `Action Points`, `AP Regeneration`, `Troops Training Rate/Speed`, `Mortality Reduction`
- Endgame (Mythic): `Infantry HP When Participating A Rally`, `Max Reinforcement Size`, etc.

All values are percentages applied multiplicatively.

### 12.8 Treasure UI

The Treasures menu shows:
- **Inventory** tab: all owned treasures, sortable by grade/stat/equipped.
- **Slots** tab: 6 slots (unlocked progressively), drag-and-drop equipping.
- **Detail view**: one treasure showing all stats, current star level, fragments owned, fragments-needed-for-next-upgrade.
- **Exchange** tab: convert Piece Fragments to specific treasure fragments.


---

## 13. Charm System

### 13.1 What charms are

Charms are temporary, single-stat buffs that drop from monster kills. Players activate a charm to apply its effect for a fixed duration.

- **Not equipped** like treasures — charms go directly into the inventory and can be activated at any time.
- **One charm per stat-category active at a time**: activating a second charm of the same category overrides the first (timer resets).
- **Multiple categories can stack**: e.g. one Construction-Speed charm + one Attack charm + one Gather-Speed charm = all three active simultaneously.

### 13.2 Charm grades

Three rarities with linear scaling of effect and duration:

| Grade | % Stat Boost | Duration |
|---|---|---|
| Normal | +3% | 30 minutes |
| Epic | +5% | 2 hours |
| Legendary | +10% | 4 hours |

(Per owner spec.)

### 13.3 Charm stat categories

A single charm boosts exactly one of the following stats:

- Construction Speed
- Research Speed
- Troops HP
- Troops Attack
- Troops Defense
- Carry Capacity (Troops Load)
- March Speed (Troops Speed)
- Gathering Speed (all resources)

(Per owner spec — final list confirmed.)

### 13.4 Charm drops

Charms drop from monster kills. The drop pool is:

- Any monster has a base chance to drop **1 charm** per kill.
- Charm rarity is rolled independently:
  - **Normal**: 70% [DEFAULT]
  - **Epic**: 25% [DEFAULT]
  - **Legendary**: 5% [DEFAULT]
- The **stat category** is rolled uniformly from the 8 categories.

Monster drop rates can be tuned per monster type and level in `data/monsters.json` (extending the existing drop slot mechanism with a "charm_rate" and "charm_grade_distribution" field).

### 13.5 Activation

- From inventory, click "Activate" on a charm.
- Effect starts immediately.
- Timer counts down regardless of whether player is online.
- When timer expires, the buff disappears; player gets a small notification.

### 13.6 Stacking with other buffs

Charm bonuses are **additive** in the buff stack (per §9.2). So a Charm Construction Speed +5% adds to the sum of Construction Speed buffs from VIP, research, treasures, etc., before being applied to the base.

### 13.7 Charm UI

Charms have their own tab in the player profile. Layout:
- **Active Charms** section at top: shows currently active buffs with timers
- **Inventory** below: stack of charms, grouped by stat then by grade

### 13.8 Charm trade / gift

Charms are **bound on drop** — cannot be traded between players or given as alliance gifts. (They can be discarded.)

This prevents alliance-internal "charm farms" where high-power players feed weaker members.

---

## 14. Monster System

### 14.1 Stamina (Action Points)

To attack a monster, a player spends **Action Points** (also called Stamina). AP regenerates over time.

- **Max AP** = 100 [DEFAULT — base, increases with VIP and treasures]
- **AP regen rate** = 1 AP per 5 minutes [DEFAULT — modified by `AP Regeneration` from VIP, charms, treasures]
- AP cap can exceed 100 with VIP / treasure bonuses.

### 14.2 AP cost per monster type

| Monster | Solo / Rally | AP Cost |
|---|---|---|
| Orc | Solo | 10 |
| Skeleton | Solo | 10 |
| Golem | Solo | 10 |
| Treasure Goblin | Solo | 20 |
| Deathkar | Rally only | 20 |
| Dragons (Green/Red/Gold) | Rally only [Phase 3+] | 30 |
| Magdar | Rally only [Phase 3+] | 30 |

For Rally-only monsters, AP is consumed by the **Rally Captain** (initiator) only. Joiners spend troops, no AP.

### 14.3 Monster types and levels

Source: `data/monsters.json` (~57 entries — newly designed, **not** the LoK 167-entry dump).

**Solo-attackable monsters (MVP):**
- **Orc** — Lv 0-10 (10 levels) — Lumber-focused drops
- **Skeleton** — Lv 1-10 (10 levels) — Lumber-focused drops, slightly tougher
- **Golem** — Lv 1-10 (10 levels) — Stone-focused drops
- **Treasure Goblin** — Lv 1-5 (5 levels) — semi-rare spawn (per-level rates in §7.10 and §14.13); the **F2P Build-SP source**, primary path for Solo players to access Build/Research speedups

**Rally-only monsters:**
- **Deathkar** — Lv 1-10 (10 levels), AP 25 — mid-tier Rally content (introduced after alliance system in Phase 3)
- **Green Dragon / Gold Dragon** — Lv 1-3 (3 levels each), AP 40 — Build/Research SP source for alliances (Phase 3+)
- **Red Dragon** — Lv 1-3 (3 levels), AP 40 — Train/Recover SP source (Phase 3+)
- **Magdar** — Lv 1-3 (3 levels), AP 50 — Mythic-fragment endgame target (Phase 3+)

### 14.4 Monster stats

Each monster entry includes:

| Field | Meaning |
|---|---|
| `code` | Unique monster code |
| `name` | Display name |
| `level` | 0-10 |
| `amount` | Number of NPC units in this monster encounter (e.g. Orc Lv 0 = 100 units) |
| `hp`, `attack`, `defense` | Per-unit stats |
| `power` | Power per unit |
| `xp` | Experience awarded on kill |
| `item_*` / `count_*` / `prob_*` | Drop slots (up to 11) |
| `alliance_gift_*` | Gift to alliance on kill (Phase 3) |
| `buff_grade_min/max` | Charm drop range (used by §13.4) |

### 14.5 Monster combat

Monster combat uses the same battle engine (§9) with these adjustments:

- Monsters have NO buff stack (no research, no treasures)
- Monsters have no counter type (always neutral 1.00 modifier) — [OPEN: should monsters have type? Default: no]
- Monsters do NOT have a Hospital — destroyed monsters are gone, respawn elsewhere
- Player troops fight with full buff stack
- Treasures with `vs Monster HP/Atk/Def` apply (Legendary tier has these)

If the player kills the monster, drops are rolled and added to inventory. If the player loses (rare for matched levels), troops are wounded/dead per normal rules.

### 14.6 Monster drops

Each monster has up to 11 drop slots. Per slot:

```
if random() < prob_n:
    add item_n × count_n to player inventory
```

Drop slots typically include:
- Resources (Food/Lumber/Stone/Gold boxes by tier)
- Speedups (mixed types and durations)
- Treasure fragments (specific treasure pieces)
- Piece Fragments (generic, exchangeable)
- Charms (rolled separately, see §13.4)

### 14.7 Monster respawning

- When a monster is killed, a replacement monster of similar level spawns within the same map sector after 10-30 minutes [DEFAULT].
- Total monster count per map is balanced to ensure constant availability.
- Some special monsters (Treasure Goblin) have rarer spawn rates.

### 14.8 Rally — multi-player attack

A **Rally** is a coordinated attack where multiple alliance members join troops together for a single battle.

- **Initiator** (Rally Captain) clicks "Start Rally" on a target (Shrine garrison or Rally-only monster).
- Initiator's troops form the core of the rally, plus AP cost applies to initiator only.
- Other alliance members can **join** the rally with their own troops within a fixed window (e.g. 30 minutes [DEFAULT]).
- **Rally Capacity** is determined by the initiator's **Hall of Alliance** level (and Alliance Tree research).
- When the join window expires, the rally launches: combined troops march to target.
- Battle resolves with all rally participants' troops combined; Rally bonuses (Advanced Tree) apply.
- Surviving troops return to their respective owner's cities with proportional shares of any plundered resources / drops.

### 14.9 Rally Capacity

| Hall of Alliance Level | Rally Capacity Cap (troops) |
|---|---|
| 1 | 20,000 |
| 5 | 50,000 |
| 10 | 100,000 |
| 20 | 250,000 |
| 30 | 500,000 |

[DEFAULT — to be tuned. The cap is the total troops across all participants, not per-participant.]

Alliance Tree `Rally Size` research can extend this cap further.

### 14.10 Drop Economy Overview

The drop economy is the **engine that converts player time into progression**. The values below are calibrated for the post-balance-fix curve (Castle L26-30 with ×1.30/×1.50 multipliers, World Speed Factor 2.0). They are **deliberately nerfed** from the LoK source data — the original LoK drops assumed Castle L30 = 160d build time. With our compressed build curve, original drops would let a hardcore alliance complete Castle L30 from a single mega-rally, which would trivialize endgame.

**Key principle: Solo players can max Train + Recover SPs. Build + Research SPs are gated behind rare Treasure Goblin spawns and Rally-only Dragons.** This keeps alliance content meaningful without making solo play unviable.

**Solo monster drops (Orc / Skeleton / Golem) — example at Lv 10:**

| Drop Slot | Probability | Quantity | Avg per Kill |
|---|---|---|---|
| Resource Pack (Lumber for Orc/Skeleton, Stone for Golem) | 100% | 35 stack | 35 packs |
| Train Speedup 15min | 50% | 25 | 12.5 × 15min = ~3.1h |
| Recover Speedup 15min | 50% | 25 | 12.5 × 15min = ~3.1h |
| Food 100 (small, supplementary) | 8% | 10 | tiny |
| T1 Troop Pack | 10% | 1 | reinforcement |
| Treasure Fragment (Normal grade, `10601xxx`) | 50% | 3 | 1.5 avg |
| Treasure Fragment (Rare grade, `10602xxx`) | 25% | 3 | 0.75 avg |

**Treasure Goblin drops (rare spawn, AP 20) — example at Lv 5:**

| Drop Slot | Probability | Quantity | Avg per Kill |
|---|---|---|---|
| **Build Speedup 3h** | **100%** | **1** | **3h guaranteed** |
| **Research Speedup 3h** | **100%** | **1** | **3h guaranteed** |
| Treasure Fragment (Epic grade, `10603xxx`) | 50% | 1 | 0.5 |
| Treasure Fragment (Legendary grade, `10604xxx`) | 10% | 1 | 0.1 |
| Gold Pack (medium) | 100% | 5 | 5 packs |

Goblins are the **F2P linchpin** — see §14.13.

**Deathkar drops (Rally-only, AP 25) — example at Lv 10:**

| Drop Slot | Probability | Quantity | Avg per Kill |
|---|---|---|---|
| Gold Packs (5k) | 100% | 35 | 35 packs |
| Train Speedup 30min | 100% | 4 | 2h guaranteed |
| Recover Speedup 30min | 100% | 4 | 2h guaranteed |
| Treasure Fragment (Legendary, `10604xxx`) | 40% | 2 | 0.8 |
| Gold Chest | 30% | 5 | 1.5 chests |
| Food 100k | 20% | 10 | 2 packs |

**Green / Gold Dragon drops (Rally-only, AP 40) — example at Lv 3:**

| Drop Slot | Probability | Quantity | Avg per Kill |
|---|---|---|---|
| **Build Speedup 30min** | **100%** | **2** | **1h guaranteed** |
| **Research Speedup 30min** | **100%** | **2** | **1h guaranteed** |
| Treasure Fragment (Epic, `10603xxx`) | 40% | 1 | 0.4 |
| Resource Packs (Lumber for Green, Gold for Gold-Dragon) | 100% | 15 | 15 packs |
| Gold Chest | 30% | 3 | 0.9 |
| Platinum Chest | 3% | 1 | rare endgame |

**Red Dragon drops (Rally-only, AP 40) — example at Lv 3:**

| Drop Slot | Probability | Quantity | Avg per Kill |
|---|---|---|---|
| Train Speedup 1h | 100% | 5 | 5h guaranteed |
| Recover Speedup 1h | 100% | 5 | 5h guaranteed |
| Gold Pack (large) | 100% | 1 | 1 pack |
| Treasure Fragment (Epic) | 40% | 1 | 0.4 |
| Gold Chest | 30% | 5 | 1.5 |

**Magdar drops (Rally-only, AP 50, hardest) — example at Lv 3:**

| Drop Slot | Probability | Quantity | Avg per Kill |
|---|---|---|---|
| Special Item `10104100` (event token) | 100% | 2 | 2 |
| Special Item `10104104` (event token) | 100% | 2 | 2 |
| T1 Troop Pack (large) | 100% | 3 | 3 |
| Treasure Fragment (Mythic, new code range `10605xxx`) | 8% | 1 | rare endgame |
| Platinum Chest | 8% | 1 | 0.08 |
| Gold Chest | 40% | 5 | 2.0 |

All concrete numbers live in `data/monsters.json`. Tweak there, no code change.

### 14.11 Speedup Sources Matrix

The hard separation between solo-grindable and rally-only speedups is the central balance lever:

| Speedup Type | Solo Sources | Rally Sources |
|---|---|---|
| **Generic Speedup** | Daily Quest rewards only | — |
| **Build Speedup** | **Treasure Goblin** (rare spawn), Conquest Event rewards, Daily Quest milestones | Green Dragon, Gold Dragon |
| **Research Speedup** | **Treasure Goblin**, Conquest Event rewards, Daily Quest milestones | Green Dragon, Gold Dragon |
| **Train Speedup** | Orc, Skeleton, Golem (abundant) | Deathkar, Red Dragon |
| **Recover Speedup** | Orc, Skeleton, Golem (abundant) | Deathkar, Red Dragon |

Implications:

- **Solo F2P player** has reliable supply of Train + Recover SPs (army builds fast, hospital heals fast)
- **Solo F2P player** depends on Treasure Goblin spawns for Build + Research SPs (slower, gated by RNG and competition for spawns)
- **Alliance member** with Dragon Rally access gets ~10× more Build/Research SPs per week
- **Solo F2P endgame timeline** is intentionally longer than alliance — but always reachable

### 14.12 Pacing Reference — F2P Endgame in ~12 months

The drop values are calibrated to hit this target. **F2P Endgame** = Castle L30 reached, all T5 troops trainable (Crusader, Sniper, Dragoon), all 6 Treasure Slots unlocked, all major mechanics active. World Speed Factor 1.0 (default) is assumed.

This 12-month target reflects how genuine deep-strategy MMOs work — League of Kingdoms, Tribal Wars, OGame all have 6-18 month progression curves for serious players. The journey IS the game.

**F2P Player Profile (no real money, no GEMS spending, no alliance Dragon Rallies):**

| Month | Milestone | Cumulative Activity |
|---|---|---|
| Month 1 | Castle L8-10, basic Production maxed, T2 troops | Tutorial done, Daily Quests, ~3-5 Goblins/week |
| Month 2 | Castle L13-15, Academy L13, joins first Alliance | Conquest Event #1 done, Alliance Help unlocked |
| Month 3 | Castle L17-19, T3 troops, first Treasure equipped | Goblin grinding established, ~2 Goblins/day |
| Month 4-5 | Castle L20-22, T4 research started, 5 Treasure Slots | Conquest Event #2-3, alliance research kicking in |
| Month 6-7 | Castle L23-25, T4 troops, all 6 Treasure Slots, GEM Nodes accessible | Mid-game endgame: most mechanics active |
| Month 8-10 | Castle L26-28 (Prestige curve), late-game research | Treasure upgrades to Epic/Legendary, established alliance role |
| Month 11-12 | **Castle L30, T5 troops trained, full F2P endgame achieved** | Prestige goals unlocked |

**Casual Alliance member (1 Rally per week with ~50 Dragons, no whaling):**

Same path, ~25-35% faster due to Dragon SP supplements. **F2P Endgame in ~8-9 months.**

**Hardcore Alliance Player (2+ Rallies per week, ~200 Dragons, optimal play):**

**F2P Endgame in ~6-7 months.** Castle L30 Prestige reached, fully optimized military.

**Whale (real money, optimized GEMS spending):**

**F2P Endgame in ~2-3 months.** No content gates skipped — pure time savings via speedups, resource bundles, and queue acceleration. Whale advantage = velocity, not power ceiling.

**Calibration note:** these are targets, not contracts. Real monitoring during MVP soft launch will inform tuning of `data/monsters.json` drop values, `data/buildings/*.json` costs, and `world.speed_factor`. The pacing reference is the **decision oracle** — if data shows F2P reaches Castle L30 in 6 months (too fast) or 18 months (too slow), drop values and/or build times get adjusted by 20-30% in the appropriate direction.

### 14.13 Treasure Goblin — design specification

The Treasure Goblin is a **newly designed monster** (not in the LoK source data). It exists specifically to give solo F2P players a path to Build/Research speedups without forcing alliance dependency.

**Design parameters:**

| Field | Value |
|---|---|
| Code | 20200401 - 20200405 (Lv 1-5) |
| Name | Treasure Goblin |
| Type | Solo |
| HP per unit | 80 / 120 / 180 / 250 / 320 (Lv 1-5) |
| Amount (NPC unit count) | 200 / 500 / 1,500 / 5,000 / 15,000 |
| Action Point Cost | 20 (vs 10 for standard solo monsters) |
| Power per unit | 5 / 10 / 18 / 30 / 50 |
| **Spawn rate** | **Lv 1-2: 6/sector/h, Lv 3: 4/sector/h, Lv 4: 2/sector/h, Lv 5: 1/sector/h** (per-level, see §7.10) |
| **Despawn timer** | **30 minutes** if not engaged (escapes if not killed quickly) |
| **Visual identifier** | Distinct gold/glittering sprite — "obvious bag of loot" cue |

**Why higher AP cost (20 vs 10)?** AP = scarcity. A goblin gives ~2× the value of a standard solo monster but costs 2× AP. Net effect: same AP-efficiency but bigger Build-SP discrete reward per kill.

**Why despawn timer?** Prevents farming via "park troops at goblin spawn, kill instantly". The 30-minute window means you have to be active or notified. Creates urgency and competition (other players may grab the goblin first).

**Why only 5 levels?** Tighter range = clearer progression. Lv 1 is for Castle L5+, Lv 5 is for Castle L20+ (the "endgame" range). Lv 3 is the common workhorse.

**Drop Profile (full table):**

| Lv | HP/Amount | Build SP | Research SP | Treasure Frags | Gold |
|---|---|---|---|---|---|
| 1 | 80 / 200 | 100% × 1× 30min | 100% × 1× 30min | Rare 30% × 1 | 5× Gold 1k |
| 2 | 120 / 500 | 100% × 2× 30min | 100% × 2× 30min | Rare 40% × 1, Epic 10% × 1 | 5× Gold 5k |
| 3 | 180 / 1,500 | 100% × 1× 1h | 100% × 1× 1h | Epic 30% × 1 | 5× Gold 10k |
| 4 | 250 / 5,000 | 100% × 2× 1h | 100% × 2× 1h | Epic 40% × 1 | 5× Gold 50k |
| 5 | 320 / 15,000 | 100% × 1× 3h | 100% × 1× 3h | Epic 50% × 1, Legendary 10% × 1 | 5× Gold 100k |

**Notes:**
- Build/Research SP drops are **100% guaranteed** — no RNG frustration. Every Goblin kill = guaranteed payoff.
- Quantity scales linearly with level. Lv 5 Goblin = 3h Build SP per kill (the F2P endgame standard).
- Treasure Fragment side-drops give Goblins a secondary value as treasure-progression source.
- No charm drops from Goblins (charms come from Orc/Skel/Golem only — keeps standard monsters relevant).

**F2P value analysis:**

A diligent F2P player kills ~3-5 Goblins per day (Lv 3 average): **3-15 hours of Build SP per day**. Over 12 months (~365 days) = **~1,100 to 5,500 hours of Build SP**. Cumulative Castle L1→L30 build time at Speed 1.0 ≈ 142 days = ~3,400 hours. With 2 parallel build queues (VIP 4) and the 12-month real time available, F2P Castle L30 + T5 is feasible. ✓

### 14.14 Charm drops by Monster

Charms drop **only from Orc, Skeleton, and Golem** — the standard solo monsters. Treasure Goblins, Deathkar, Dragons, and Magdar have their own valuable drop pools (Build/Research SPs, Legendary/Mythic Fragments, GEMS) — adding charms would over-reward those targets and reduce the strategic significance of standard monster grinding.

This means **all charm farming is solo**. Even in an active alliance, a player who wants Legendary Charms must personally hunt Lv 9-10 Orc/Skeleton/Golem monsters. There is no shortcut.

**Charm drop chance** = 30% per kill (a charm of some grade drops). When a charm drops, the rarity is rolled per the table below — no Normal Charms drop from Lv 7+ monsters; no Legendary Charms from Lv 0-6 monsters. The stat category is uniform random across the 8 categories (§13.3).

**Charm Rarity Distribution Table:**

| Monster | Level Range | Normal | Epic | Legendary |
|---|---|---|---|---|
| Orc | Lv 0-3 | 82% | 18% | 0% |
| Orc | Lv 4-6 | 70% | 30% | 0% |
| Orc | Lv 7-8 | 0% | 80% | 20% |
| Orc | Lv 9-10 | 0% | 50% | 50% |
| Skeleton | Lv 1-3 | 75% | 25% | 0% |
| Skeleton | Lv 4-6 | 65% | 35% | 0% |
| Skeleton | Lv 7-8 | 0% | 75% | 25% |
| Skeleton | Lv 9-10 | 0% | 50% | 50% |
| Golem | Lv 1-3 | 75% | 25% | 0% |
| Golem | Lv 4-6 | 65% | 35% | 0% |
| Golem | Lv 7-8 | 0% | 75% | 25% |
| Golem | Lv 9-10 | 0% | 50% | 50% |
| Treasure Goblin / Deathkar / Dragons / Magdar | (any) | — | — | — |

**Four progression tiers**:
- **Tier 1 (Lv 0-3)**: Tutorial-level — Normal-dominated, no Legendary chance
- **Tier 2 (Lv 4-6)**: Mid-game — Normal + Epic mix, no Legendary
- **Tier 3 (Lv 7-8)**: Endgame — kein Normal, Epic-dominated, moderate Legendary
- **Tier 4 (Lv 9-10)**: Legendary Farm Tier — 50/50 Epic/Legendary

This creates two clear graduation moments in a player's progression:
1. Unlocking Lv 7+ monsters → sudden access to Epic+Legendary
2. Unlocking Lv 9+ monsters → 50% Legendary drop tier

Source-of-truth for the mapping: `data/charms.json` and `charm_drop` blocks in `data/monsters.json`.

### 14.15 GEMS drops by Monster

In addition to all other drops, monsters drop small amounts of **GEMS** as a free-to-play income stream. This makes the F2P-vs-Whale gap progression-based rather than content-gated.

**GEMS Drop Rate Table:**

| Monster | Level Range | Drop Chance | Quantity | Avg per Kill |
|---|---|---|---|---|
| Orc / Skeleton / Golem | Lv 0-3 | 15% | 10 GEMS | 1.5 |
| Orc / Skeleton / Golem | Lv 4-6 | 15% | 30 GEMS | 4.5 |
| Orc / Skeleton / Golem | Lv 7-10 | 15% | 40 GEMS | 6.0 |
| Treasure Goblin | Lv 1-2 | 50% | 30 GEMS | 15 |
| Treasure Goblin | Lv 3 | 50% | 50 GEMS | 25 |
| Treasure Goblin | Lv 4-5 | 50% | 75 GEMS | 37.5 |
| Deathkar | Lv 1-5 | 30% | 50 GEMS | 15 |
| Deathkar | Lv 6-10 | 30% | 100 GEMS | 30 |
| Green / Red / Gold Dragon | Lv 1 | 50% | 100 GEMS | 50 |
| Green / Red / Gold Dragon | Lv 2 | 50% | 200 GEMS | 100 |
| Green / Red / Gold Dragon | Lv 3 | 50% | 300 GEMS | 150 |
| Magdar | Lv 1 | 100% | 500 GEMS | 500 |
| Magdar | Lv 2 | 100% | 1000 GEMS | 1000 |
| Magdar | Lv 3 | 100% | 2000 GEMS | 2000 |

**F2P income calculation:**
- Mid-game player (Lv 4-6 farming, ~15 kills/day): 15 × 4.5 = **~67 GEMS/day**
- Endgame player (Lv 7-10 farming + Goblins + Rally participation): **~150-200 GEMS/day**
- Magdar Lv 3 rally (split among ~30 alliance members): ~67 GEMS per participant per kill

These values are calibrated so that:
- A €5 GEMS pack (~500 GEMS) equals ~3-7 days of active F2P grinding
- F2P players never feel "locked out" of premium features — only slower
- Whales pay for **velocity**, not for **content access**


---

## 15. Alliance System

### 15.1 Membership

- Each player can be in **0 or 1 alliance** at a time.
- An alliance has a **founder** (Leader) who can:
  - Invite players
  - Promote/demote members
  - Edit alliance description, tag, banner
  - Disband alliance
- **Member capacity**: 30 by default. Expandable via Alliance Battle research up to 75.

### 15.2 Roles

| Role | Permissions |
|---|---|
| **Leader** (R5) | All permissions, promote/demote, disband |
| **Vice-Leader** (R4) | Invite, kick, manage permissions, manage diplomacy |
| **Officer** (R3) | Invite players, send alliance gifts |
| **Veteran** (R2) | Invite (with approval), join rallies |
| **Member** (R1) | Default; can request promotions, join rallies |

### 15.3 Alliance HQ (Hall of Alliance)

Each player has a `Hall of Alliance` building in their city. Its level determines:

- **Rally capacity** (when this player initiates a rally) — see §14.9
- **Reinforcement capacity** (max troops a player can host as alliance reinforcements when defending or supporting)

Note: the Hall of Alliance is **per-player** (each member levels their own), not a shared alliance-wide structure. The shared alliance state lives in the `alliances` table.

### 15.4 Alliance treasury & research

The alliance has its own resource pool: the **Alliance Treasury**. Members can donate resources to the treasury; the alliance leader spends from it on:

- **Alliance Research** (Battle and Production trees, see §6.6)
- **Alliance Buildings** (territory, fortresses, outposts) — Phase 3+

Treasury cap scales with Alliance Tree research.

### 15.5 Alliance Help

When a member starts a building upgrade, research, or troop healing, an **Alliance Help** request is automatically posted to the alliance.

- Other members click "Help" — each click reduces the timer by **1%** (or 30 seconds, whichever is greater) [DEFAULT].
- Maximum help reduction per task: **30%** [DEFAULT].
- Each member can help up to **30 tasks per day** [DEFAULT].
- Hall of Alliance level affects how many help slots a member can post AT ONCE.
- Alliance Tree research (Phase 3+) can boost the % per click and the cap.

This is a major QoL feature and a strong incentive to join active alliances.

### 15.6 Reinforcements (Support)

A player can send troops to another alliance member's city as reinforcements (`MARCH_TYPE_SUPPORT`).

- Reinforcement troops fight as defenders if the host city is attacked.
- They consume the host's **Hall of Alliance** reinforcement capacity (not their own).
- Reinforcements can be recalled at any time — they march back to origin.
- If the host's wall breaks during attack, reinforcements teleport back to origin (not to the host's new random location).

### 15.7 Diplomacy

Alliance pairs can establish diplomatic relations:

| Status | Effect |
|---|---|
| **Ally** | No attacks possible between members; reinforcements free; mutual rally invites |
| **NAP (Non-Aggression Pact)** | No attacks; no reinforcement bonus |
| **Neutral** (default) | Normal rules; can attack and rally each other |
| **War** | Mutual war declaration; specific rewards/penalties [Phase 3+] |

Diplomacy is set by Leader/Vice-Leader and recorded in `alliance_diplomacy`. State changes are visible in the alliance log.

### 15.8 Alliance chat

Real-time chat (via polling, not WebSocket).

- Two channels: **World** (visible to whole world) and **Alliance** (members only)
- Messages persist 7 days, then auto-purge [DEFAULT]
- Mentions (@username) trigger notification
- Anti-spam: one message per 2 seconds, max 200 chars [DEFAULT]

### 15.9 Alliance gifts

When a member kills certain monsters or participates in rallies, the alliance is rewarded with **Alliance Gifts**. Source: `alliance_gift_*` fields in monster JSON.

- All members claim from the gift pool (each member can claim once per gift)
- Gifts contain Resources, Speedups, fragments, etc.
- Gifts auto-expire after 7 days [DEFAULT]

This rewards active alliances and encourages collective monster hunting.

### 15.10 Alliance forum / message board

A simple thread-based message board within the alliance UI:

- Threads (title + body)
- Replies under threads
- Pinned threads (Leader/Vice-Leader only)
- Notification on @mention
- Persists indefinitely (until alliance disbands)

This replaces a complex forum and is sufficient for coordination.

### 15.11 Disband

Leader can disband the alliance. All members are released, treasury is forfeit (or distributed evenly, [OPEN]). All shrines held by the alliance become unowned. Alliance name reserved for 30 days.

---

## 16. Conquest Event

### 16.1 Overview

The **Conquest Event** is the central late-game / end-game mechanic. Every 2 weeks, alliances compete for control of map structures: **Shrines** (3 tiers: C, B, A) and the **Congress** (S tier, single instance).

Captures persist between events. Events cycle in 4 phases that progressively unlock higher tiers, then enter steady-state where all tiers are contested.

### 16.2 Map structure layout

The Conquest area is in the **center** of the map. The structures form a pyramid, with the Congress at the apex.

**Counts:**
- **20 C-Shrines** (lowest tier)
- **12 B-Shrines** (middle tier)
- **4 A-Shrines** (highest shrine tier)
- **1 Congress** (S — the apex)

**Layout (per owner image):** 4 quadrants around the Congress. Each quadrant contains:
- 5 C-Shrines (outer ring)
- 3 B-Shrines (middle ring)
- 1 A-Shrine (inner ring)

The Congress sits in the dead center. Each A-Shrine is adjacent to the Congress and gates access to it.

### 16.3 Adjacency / capture chain

The capture chain enforces the pyramid:

- **C-Shrines**: capturable by anyone (any alliance, no prerequisite)
- **B-Shrines**: capturable only by an alliance that already controls a specific neighboring C-Shrine
- **A-Shrines**: capturable only by an alliance that already controls a specific neighboring B-Shrine
- **Congress**: capturable only by an alliance that already controls a specific neighboring A-Shrine

The C → B and B → A relationships are pre-defined per shrine in `data/shrines_layout.json` [TO BUILD], based on the owner's table:

```
Quadrant 1 (top-left):
  C1, C2, C3 ─→ B1, B2 ─→ A1
  C7, C9     ─→ B5     ─→ A1
  ...
```

The pyramid is **rigid**: an alliance that wants the Congress must control at least one full chain of C → B → A in one quadrant.

### 16.4 Event phases (first 4 events)

A new world starts with phased Conquest. Each event opens more tiers:

| Event # | Active Tiers |
|---|---|
| 1 (Day 14-28) | C only |
| 2 (Day 28-42) | C + B |
| 3 (Day 42-56) | C + B + A |
| 4 (Day 56-70) | C + B + A + S |
| 5+ (steady state) | All tiers always active |

After Event 5, the event is permanent — every 2 weeks all shrines refresh and combat for control resumes.

### 16.5 Garrison NPCs (initial defense)

When a shrine is **un-claimed** (start of world or after a loss), it is defended by an NPC garrison. Once an alliance captures it, the garrison is replaced by alliance-deployed troops.

| Shrine Tier | Garrison Force (Default) |
|---|---|
| C | 1,000,000 T2 troops (mixed Inf/Ranged/Cav) |
| B | 2,000,000 T4 troops (mixed) |
| A | 3,000,000 T4 troops (mixed) |
| Congress (S) | 4,000,000 T4 troops (mixed) |

(Owner-specified.)

The garrison is balanced (33% / 33% / 34% Inf / Ranged / Cav).

### 16.6 Capture mechanic

To capture a shrine:

1. **Send a Rally** to attack the shrine. Solo attacks are rejected.
2. The Rally's combined troops fight the garrison (NPC) or the current holder's deployed troops.
3. If the rally wins, the shrine becomes **contested** — a 1-hour timer starts.
4. **Hold the shrine for 1 uninterrupted hour** to claim it.
5. While the timer runs, anyone can attack the shrine. Each successful attack RESETS the timer.
6. Once the alliance holds 1 hour without interruption, the shrine is **secured for the rest of the event** — no further attacks are allowed.

Once the event ends (after 14 days), all shrines reset:
- All "secured" status is cleared
- Alliances retain ownership but can be challenged again in next event
- Garrison NPCs reset only on un-owned shrines (alliance-held shrines keep their alliance troops)

### 16.7 Defense (alliance-held shrine)

When an alliance owns a shrine:

- The alliance can **garrison troops** at the shrine (sent via Support march to the shrine).
- Garrison troops live at the shrine, not in member cities.
- Rally Capacity from the holder's Hall of Alliance applies.
- Troops at a shrine can be recalled at any time (back to owner's city).

### 16.8 Shrine bonuses (alliance-wide buffs)

Owning a shrine grants the alliance and all its members a passive buff. Bonuses vary by tier and shrine:

| Tier | Bonus Pool |
|---|---|
| **C-Shrine** | Small bonus, e.g. +5% one resource production for all members [DEFAULT] |
| **B-Shrine** | Medium bonus, e.g. +10% troop attack OR +10% research speed [DEFAULT] |
| **A-Shrine** | Large bonus, e.g. +20% troop HP OR unlock T5 troops in member cities [DEFAULT] |
| **Congress (S)** | Server-wide title "Ruler of [WorldName]" + dramatic buff (e.g. +50% all stats) for the duration |

Specific bonus per shrine to be defined in `data/shrines_layout.json` (each shrine has a unique perk; alliances strategically pick which to capture). [DETAILED DEFINITION DEFERRED — DEFAULT TIER-WIDE BONUSES FOR MVP]

### 16.9 Event progression UI

The Conquest tab shows:
- Current event phase
- Time until next event end / next event start
- World map highlighting all shrines, their tier, and current owner
- Rankings of alliances by shrine count, weighted by tier
- Personal contributions log (which shrines the player helped capture)

### 16.10 Inter-world events [Phase 4+]

Future plan: link multiple worlds via cross-world tournaments. Out of scope for MVP through Phase 3.


---

## 17. VIP System

### 17.1 Overview

VIP is a 20-level account-wide progression system providing passive bonuses. Players earn **VIP Points** to level up, then receive permanent bonuses at each level reached.

**VIP is account-bound, NOT city-bound.** Even after city teleport or future world migration, VIP level persists.

### 17.2 VIP point sources

- **Daily login**: small daily reward (e.g. 10 points)
- **Quest completion**: variable per quest
- **GEMS purchase**: 1 GEM = 1 VIP Point [DEFAULT]
- **VIP Items**: `ITEM_CODE_VIP_*` (10 / 100 / 1k / 10k packs)
- **Premium subscription** (if added Phase 4): daily VIP point boost

### 17.3 Level thresholds

| VIP Level | Cumulative Points |
|---|---|
| 1 | 0 |
| 2 | 200 |
| 3 | 500 |
| 4 | 1,000 |
| 5 | 5,000 |
| 6 | 10,000 |
| 7 | 20,000 |
| 8 | 50,000 |
| 9 | 100,000 |
| 10 | 150,000 |
| 11 | 200,000 |
| 12 | 250,000 |
| 13 | 500,000 |
| 14 | 1,000,000 |
| 15 | 1,500,000 |
| 16 | 2,000,000 |
| 17 | 3,000,000 |
| 18 | 4,000,000 |
| 19 | 8,000,000 |
| 20 | 12,000,000 |

(Source: VIP_TREASURE_GAME.xlsx, "VIP SYSTEM Benefits" sheet.)

### 17.4 VIP benefits per level

Bonuses are unlocked progressively. Many benefits start showing at low levels and scale up to VIP 20.

| Benefit | Unlocks at | Max value (VIP 20) |
|---|---|---|
| Resource Production | VIP 1 (+3%) | +100% |
| Gathering Speed | VIP 2 (+6%) | +100% |
| Research Speed | VIP 3 (+3%) | +100% |
| Construction Speed | VIP 3 (+3%) | +100% |
| Action Point Regeneration | VIP 4 (+3%) | +70% |
| Additional Building Queue | VIP 4 | UNLOCK |
| Troop Limit | VIP 6 (+5%) | +30% |
| Marching Troop Capacity | VIP 6 (5,000) | +45,000 |
| Troop Dispatch Queue | VIP 6 | +1 (extra march slot) |
| Mortality Reduction | VIP 7 (5%) | 30% |
| Action Point items | VIP 8 | UNLOCK (use AP items from inventory) |
| Troops Training Speed | VIP 9 (+5%) | +25% |
| Troops Training Cost | VIP 9 (-5%) | -25% |
| Troops Training Amount | VIP 9 (+5%) | +25% |
| Troop Dispatch Queue (2nd) | VIP 17 | +1 (5 march slots total) |
| Maximum Troop Size for Rally | VIP 16 (+5%) | +20% |
| Mastery Points | per level (1 → 19) | for Mastery system [Phase 3+] |

### 17.5 VIP integration into buff stack

VIP bonuses are **ADDITIVE** in the buff stack (per §9.2). E.g. VIP 20 Resource Production +100% adds to:

```
total_resource_production_bonus = 1.00 (VIP)
                                + research_production_bonus  
                                + treasure_production_bonus (multiplicative, separate)
```


---

## 18. GEMS & Premium Shop

### 18.1 GEMS — what they are

**GEMS** are the premium currency. They are entirely separate from the resource system.

**Acquisition (in priority order):**
1. **Real-money purchase** (primary revenue source)
2. **Monster drops** — small GEM amounts from solo monsters (15% chance × 10-40 GEMS), larger amounts from rally monsters and apex bosses (see §14.10)
3. **Gem Nodes** on the map (rare, available at all Castle levels, see §3.7)
4. **Conquest Event rewards** (significant amounts for participation, hundreds to thousands)
5. **Quest rewards** (small daily and milestone rewards)

**Active F2P income**: ~80-200 GEMS/day combining all sources. See §3.7 for detail.

### 18.2 GEMS spending

The Premium Shop offers items priced in GEMS:

| Category | Items | GEM Cost (Default) |
|---|---|---|
| **Speedups** | 1m / 5m / 10m / 30m / 1h / 3h / 8h / 1d (all types) | 5 / 20 / 35 / 90 / 160 / 380 / 800 / 1500 |
| **Resources** | 1k / 5k / 10k / 50k / 100k / 500k / 1M / 5M / 10M packs | scales: 5 / 20 / 35 / 150 / 280 / 1200 / 2200 / 9000 / 16000 |
| **Boosts** | 8h / 1d resource/gathering boosts | 200 / 500 |
| **Action Points** | 10 / 20 / 50 / 100 packs | 50 / 95 / 220 / 400 |
| **Chests** | Silver / Gold / Platinum chests (treasure fragments) | 100 / 500 / 2000 |
| **VIP Points** | direct VIP point packages | 1 GEM = 1 VIP Point |
| **Castle Skins** | (Phase 4+) cosmetic city skins | 500 - 5000 |

[DEFAULT prices — to be tuned with monetization analysis]

### 18.3 GEM bundles (real-money packages)

[DEFAULT — to be defined with payment gateway in Phase 3]

| Pack | GEMS | Price (€) | Bonus |
|---|---|---|---|
| Starter | 100 | 0.99 | — |
| Small | 500 | 4.99 | +10% bonus |
| Medium | 1,500 | 14.99 | +20% bonus |
| Large | 5,000 | 44.99 | +30% bonus |
| Mega | 12,000 | 99.99 | +40% bonus |

Real prices and bundle structure depend on payment gateway integration (Stripe, PayPal). Out of scope for MVP.

### 18.4 No pay-to-win commitments

The owner has explicitly committed to:
- **No GEMS-only weapons or units**: every troop, treasure, building accessible via free play
- **No GEMS-only tier unlocks**: tier 5 troops require Castle 30 + research, not GEMS
- **GEMS = time saver**, not power gate
- **No GEMS auctions** for unique map possessions

Premium players accelerate (faster builds, more carry, more research, more healing capacity), but free players reach the same content given enough time.

### 18.5 Castle skins

Visual reskins for the player's city, applied to the map view. Acquired via:
- Real-money purchase (cosmetic packs, Phase 4)
- Achievements (rare seasonal)
- Conquest Event victory (top alliances)

Castle skins are **purely cosmetic**. No gameplay effect.

---

## 19. Items & Inventory

### 19.1 Item taxonomy

Item codes follow the LoK numbering scheme (see `enum.py`). The first 4 digits indicate the category:

| Code Prefix | Category | Examples |
|---|---|---|
| `1010xxxx` | Resources & Currency | Food/Lumber/Stone/Gold packs, GEMS, VIP packs, AP packs |
| `10102xxx` | Buffs | Resource production boosts, Gathering boost, Anti-Recon, Golden Hammer |
| `10103xxx` | Speedups | Generic / Building / Research / Train / Recover, durations 1m to 1d |
| `10104xxx` | Misc | Chests (Silver/Gold/Platinum), Resource/Speedup boxes, T1 troops, Alliance Teleport |
| `10603xxx` | Special | Orb of Portal pieces, Golden Pillars, etc. |
| `20100xxx` | Field Objects | Map structures (managed in `field_objects.json`) |
| `20200xxx` | Monsters | Monster definitions |
| `30100xxx` / `30101xxx` | Buildings | Building definitions |
| `30102xxx` | Buff applications | Internal buff IDs |
| `40100xxx` | Building codes | Linked to construction logic |
| `50100xxx` | Troops | Troop definitions |

This taxonomy is preserved from LoK source data for compatibility.

### 19.2 Inventory storage

Each player has a **per-account inventory** (not per-city). Items stack by code; no slot count limit.

```sql
CREATE TABLE inventory (
    player_id    INT NOT NULL,
    item_code    INT NOT NULL,
    quantity     INT NOT NULL DEFAULT 0,
    PRIMARY KEY (player_id, item_code)
);
```

When item count reaches 0, the row is deleted. New items insert or update.

### 19.3 Item types and use

**Use-on-self** (consumed by clicking "Use"):
- Resources: directly added to current city's resource pool (capacity-clamped)
- Speedups: applied to a specific active task (Building/Research/Train/Recover)
- Boosts: activate a timed buff (8h or 1d)
- Action Points: refill stamina
- Anti-Recon: activate spy protection
- VIP Points: increase VIP level
- Chests: rolled — produce random items per chest tier

**Equipment** (Treasures):
- Equipped, not consumed (see §12)

**Auto-applied** (consumed automatically):
- Treasure Fragments: auto-deposited on collection, used when upgrading treasures
- Charms: stored, manually activated (see §13)

### 19.4 Resource Boxes

Resource boxes (`ITEM_CODE_RESOURCE_BOX_LV1-LV4`) contain a random mix of resources scaled to the player's current Castle level.

| Box Tier | Total Value (resources combined) |
|---|---|
| LV1 | ~5x current level cost |
| LV2 | ~10x |
| LV3 | ~25x |
| LV4 | ~100x |

[DEFAULT — to be tuned for economy]

Opening a box uses Castle level as a scaling factor. Prevents end-game players from hoarding low-level resource boxes.

### 19.5 Speedup Boxes

Same scaling concept for speedups (`ITEM_CODE_SPEEDUP_BOX_LV1-LV4`): contain a mix of speedup durations sized for the player's level.

### 19.6 Chests

Chests yield treasure fragments (specific or generic), other items, and rare drops:

| Chest | Drop Pool |
|---|---|
| Silver Chest | Mostly Normal/Rare fragments, small chance of Epic |
| Gold Chest | Mostly Rare/Epic fragments, small chance of Legendary |
| Platinum Chest | Mostly Epic/Legendary, small chance of Mythic |

Drop tables in `data/chests.json` [TO BUILD].

### 19.7 Inventory UI

A single Inventory tab with sub-tabs:
- **Resources** (consumable resources)
- **Speedups** (filtered by category)
- **Buffs** (boosts)
- **Treasures** (separate from regular inventory, see §12)
- **Charms** (separate UI, see §13)
- **Misc** (chests, boxes, special items)


---

## 20. Beginner Protection & Inactivity

### 20.1 Beginner Shield

When a new player first creates their city, they receive a **7-day Beginner Shield**.

**Effects of an active shield:**
- The city **cannot be attacked** by any other player.
- The city **cannot send attacks** against other players (gathering and monster combat allowed).
- Spy missions are **not blocked** (others can spy on the protected player; protected player can also spy).
- The shield is **visible** on the map (a translucent dome icon over the city).

**Shield expiration:**
- Automatically expires after 7 days from account creation.
- **Voluntarily ends** if the player attacks any other player. (Once you swing first, no more protection.)
- Player can **manually disable** the shield at any time (one-way; cannot reactivate).

[DEFAULT 7 days. Tunable per world if "express" worlds shorten this.]

### 20.2 Newbie shield items (Phase 2+)

Players can purchase additional shields from the GEMS shop:

| Shield Duration | GEMS |
|---|---|
| 4 hours | 100 |
| 12 hours | 250 |
| 24 hours | 500 |
| 3 days | 1500 |

These shields **cannot be used during Conquest Event participation** (when the player has marched troops to a Shrine within the past 24 hours). This prevents abuse.

### 20.3 Inactivity stages

| Days Inactive | Status | Effect |
|---|---|---|
| 0-7 | Active | Normal play |
| 7-30 | Idle | Marked as idle in alliance; reinforcements timeout faster |
| 30+ | **Hidden** | City is hidden from the world map (still exists in DB) |
| 60+ | Inactive | Player marked as inactive; no new attacks against them counted in events |
| 90+ | Stale | Consider archiving (admin decision) |

(Per owner spec: at 30 days the city disappears from the map but is NOT deleted.)

### 20.4 City hidden mechanic

When a city is hidden:
- It is removed from the visible map (not occupying any tile slot for collision purposes — its tile becomes available for new placements over time)
- It remains in the database
- The player can resume by logging in; on login, the city is re-placed:
  - First try: original coordinates (if still empty)
  - Fallback: random valid empty area
- Resources resume calculating from `last_resource_update`
- Troops are intact

This is the explicit owner choice: never delete data, just hide.

### 20.5 Inactive city protection

A hidden inactive city:
- Cannot be attacked
- Cannot be spied
- Does not show in spy / world rankings
- Does not appear in alliance member list (until player returns)

### 20.6 Returning from inactivity

When a player logs back in after >30 days:
- City is re-placed on the map
- "Welcome back" notification with summary of changes
- Beginner Shield re-applied for 24 hours [DEFAULT — give them time to reorient]

---

## 21. Tutorial Flow

### 21.1 Onboarding sequence

The tutorial is a **guided sequence of forced first actions**. It introduces core mechanics in a controlled environment.

| Step | Action | Reward |
|---|---|---|
| 1 | "Welcome to Conquer" — name your kingdom | 50 GEMS |
| 2 | Upgrade Castle to L2 (instant, free) | 1k of each resource |
| 3 | Upgrade Lumber Camp to L2 | Lumber speedup 1m × 5 |
| 4 | Upgrade Farm to L2 | Food speedup 1m × 5 |
| 5 | Upgrade Quarry to L2 | Stone speedup 1m × 5 |
| 6 | Upgrade Gold Mine to L2 | Gold speedup 1m × 5 |
| 7 | Upgrade Barrack to L2, train 100 T1 Infantry | Speedup train 5m × 3 |
| 8 | Upgrade Academy to L2, research Infantry HP L1 | Speedup research 5m × 3 |
| 9 | Open the world map, click on a Lv1 monster | (info popup) |
| 10 | Attack the Lv1 monster (uses 10 AP) | First charm (Normal grade), first piece fragments |
| 11 | Equip a treasure (give them a free Normal treasure: Manure) | (treasure equipped tutorial) |
| 12 | Tutorial complete: 100 GEMS, 1 day Resource Production Boost | |

After the tutorial: a "main quest" feed prompts the player toward intermediate goals (Castle 5, join an Alliance, attack first monster Lv 5, etc.).

### 21.2 Tutorial UX

- **Forced**: tutorial steps cannot be skipped. Any other UI is dimmed.
- **Skippable** (after step 5): a "Skip Tutorial" button appears, granting all remaining rewards instantly.
- **Quick-resume**: if a player closes the browser mid-tutorial, they resume on next login at the same step.
- **No tutorial dialog past step 12**: from there on, the game is self-directed.

### 21.3 Quest system (post-tutorial)

A simple quest system in the side UI:

- **Daily quests**: 5-10 quests per day, refresh at world midnight UTC
- **Main quests**: long-running goals (Castle 10, Castle 15, etc.) with milestone rewards
- **Event quests**: time-limited (Conquest Event participation, monster boss kills)

Quest data in `data/quests.json` [TO BUILD]. MVP includes minimal quest set (~20 quests). Expanded in Phase 2.

### 21.4 Help system

A persistent help icon (?) opens a modal with:
- Topic search
- Topic categories (Buildings, Troops, Combat, Treasures, etc.)
- Embedded GIFs / screenshots showing UI flows

Help content is HTML pages bundled with the app. No external CMS needed.


---

## 22. Database Schema

This section defines the canonical database schema. A separate `DATABASE_SCHEMA.md` file in the repo is kept in sync with this section.

All tables use InnoDB, UTF-8 (utf8mb4_unicode_ci), strict mode.

### 22.1 Core tables

```sql
-- WORLDS
CREATE TABLE worlds (
    id                  INT PRIMARY KEY AUTO_INCREMENT,
    name                VARCHAR(50) NOT NULL,
    slug                VARCHAR(20) UNIQUE NOT NULL,
    status              ENUM('open','running','paused','closed') DEFAULT 'open',
    speed_factor        FLOAT DEFAULT 1.0,
    gather_factor       FLOAT DEFAULT 1.0,
    haul_factor         FLOAT DEFAULT 1.0,
    base_mortality_rate FLOAT DEFAULT 0.0,
    map_size            SMALLINT DEFAULT 1024,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    started_at          DATETIME NULL,
    INDEX (status)
);

-- PLAYERS (account, NOT per-world)
CREATE TABLE players (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    username        VARCHAR(30) UNIQUE NOT NULL,
    email           VARCHAR(100) UNIQUE NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login      DATETIME NULL,
    is_banned       TINYINT(1) DEFAULT 0,
    vip_level       SMALLINT DEFAULT 1,
    vip_points      INT DEFAULT 0,
    gems            INT DEFAULT 0,
    INDEX (last_login)
);

-- CITIES (per player per world; max 1 per world)
CREATE TABLE cities (
    id                  INT PRIMARY KEY AUTO_INCREMENT,
    player_id           INT NOT NULL,
    world_id            INT NOT NULL,
    name                VARCHAR(50) NOT NULL,
    coord_x             SMALLINT NOT NULL,
    coord_y             SMALLINT NOT NULL,
    is_hidden           TINYINT(1) DEFAULT 0,
    is_shielded         TINYINT(1) DEFAULT 0,
    shield_expires_at   DATETIME NULL,
    food                BIGINT DEFAULT 10000,
    lumber              BIGINT DEFAULT 10000,
    stone               BIGINT DEFAULT 10000,
    gold                BIGINT DEFAULT 5000,
    last_resource_update DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    wall_hp_current     INT DEFAULT 5000,
    wall_hp_max         INT DEFAULT 5000,
    wall_last_update    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    castle_level        TINYINT DEFAULT 1,
    power               BIGINT DEFAULT 0,
    action_points       SMALLINT DEFAULT 100,
    ap_last_update      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY one_city_per_world (player_id, world_id),
    UNIQUE KEY no_overlapping_cities (world_id, coord_x, coord_y),
    INDEX (world_id, is_hidden, coord_x, coord_y),
    FOREIGN KEY (player_id) REFERENCES players(id),
    FOREIGN KEY (world_id) REFERENCES worlds(id)
);

-- BUILDINGS PER CITY
CREATE TABLE city_buildings (
    city_id         INT NOT NULL,
    building_code   VARCHAR(30) NOT NULL,    -- 'castle', 'wall', 'farm', etc.
    level           TINYINT DEFAULT 1,
    PRIMARY KEY (city_id, building_code),
    FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE CASCADE
);

-- BUILDING UPGRADE QUEUE
CREATE TABLE building_queue (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    city_id         INT NOT NULL,
    building_code   VARCHAR(30) NOT NULL,
    level_to        TINYINT NOT NULL,
    started_at      DATETIME NOT NULL,
    finishes_at     DATETIME NOT NULL,
    is_processed    TINYINT(1) DEFAULT 0,
    INDEX (finishes_at, is_processed),
    FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE CASCADE
);

-- TROOPS PER CITY
CREATE TABLE city_troops (
    city_id         INT NOT NULL,
    troop_code      INT NOT NULL,            -- 50100101 = Fighter, etc.
    count           INT DEFAULT 0,
    PRIMARY KEY (city_id, troop_code),
    FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE CASCADE
);

-- TROOP TRAINING QUEUE
CREATE TABLE troop_queue (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    city_id         INT NOT NULL,
    troop_code      INT NOT NULL,
    count           INT NOT NULL,
    barrack_slot    TINYINT DEFAULT 1,
    started_at      DATETIME NOT NULL,
    finishes_at     DATETIME NOT NULL,
    is_processed    TINYINT(1) DEFAULT 0,
    INDEX (finishes_at, is_processed),
    FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE CASCADE
);

-- WOUNDED TROOPS (HOSPITAL)
CREATE TABLE city_wounded (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    city_id         INT NOT NULL,
    troop_code      INT NOT NULL,
    count           INT NOT NULL,
    healed_at       DATETIME NOT NULL,    -- target completion time
    INDEX (city_id, healed_at),
    FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE CASCADE
);

-- RESEARCH PER CITY (or per player?)  PER PLAYER (single tech tree per player)
CREATE TABLE player_research (
    player_id       INT NOT NULL,
    world_id        INT NOT NULL,
    research_code   VARCHAR(50) NOT NULL,
    level           TINYINT DEFAULT 0,
    PRIMARY KEY (player_id, world_id, research_code),
    FOREIGN KEY (player_id) REFERENCES players(id),
    FOREIGN KEY (world_id) REFERENCES worlds(id)
);

-- RESEARCH QUEUE
CREATE TABLE research_queue (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    player_id       INT NOT NULL,
    world_id        INT NOT NULL,
    research_code   VARCHAR(50) NOT NULL,
    level_to        TINYINT NOT NULL,
    started_at      DATETIME NOT NULL,
    finishes_at     DATETIME NOT NULL,
    is_processed    TINYINT(1) DEFAULT 0,
    INDEX (finishes_at, is_processed),
    FOREIGN KEY (player_id) REFERENCES players(id),
    FOREIGN KEY (world_id) REFERENCES worlds(id)
);

-- MARCHES (in-flight troop movements + spy missions)
CREATE TABLE marches (
    id                  INT PRIMARY KEY AUTO_INCREMENT,
    player_id           INT NOT NULL,
    world_id            INT NOT NULL,
    march_type          TINYINT NOT NULL,    -- 1=gather,2=attack,5=monster,7=support,8=rally,9=spy
    origin_city_id      INT NOT NULL,
    target_x            SMALLINT NOT NULL,
    target_y            SMALLINT NOT NULL,
    target_type         TINYINT NULL,        -- 1=city,2=field_object,3=monster,4=shrine
    target_id           INT NULL,
    troops_json         JSON NULL,
    departure_time      DATETIME NOT NULL,
    arrival_time        DATETIME NOT NULL,
    return_time         DATETIME NULL,
    state               ENUM('marching','arrived','resolving','returning','complete') DEFAULT 'marching',
    haul_json           JSON NULL,           -- resources brought back, set on resolution
    rally_id            INT NULL,            -- if part of a rally
    INDEX (arrival_time, state),
    INDEX (return_time, state),
    INDEX (rally_id),
    FOREIGN KEY (player_id) REFERENCES players(id),
    FOREIGN KEY (world_id) REFERENCES worlds(id),
    FOREIGN KEY (origin_city_id) REFERENCES cities(id) ON DELETE CASCADE
);
```

### 22.2 Battle, spy, alliance tables

```sql
-- RALLIES (multi-player coordinated attacks)
CREATE TABLE rallies (
    id                  INT PRIMARY KEY AUTO_INCREMENT,
    initiator_player_id INT NOT NULL,
    world_id            INT NOT NULL,
    target_x            SMALLINT NOT NULL,
    target_y            SMALLINT NOT NULL,
    target_type         TINYINT NOT NULL,    -- 3=monster, 4=shrine
    target_id           INT NOT NULL,
    join_window_until   DATETIME NOT NULL,
    departure_time      DATETIME NOT NULL,
    arrival_time        DATETIME NOT NULL,
    state               ENUM('forming','marching','resolving','complete') DEFAULT 'forming',
    capacity_max        INT NOT NULL,
    INDEX (state, arrival_time),
    INDEX (initiator_player_id),
    FOREIGN KEY (initiator_player_id) REFERENCES players(id),
    FOREIGN KEY (world_id) REFERENCES worlds(id)
);

-- BATTLE REPORTS
CREATE TABLE battle_reports (
    id                  INT PRIMARY KEY AUTO_INCREMENT,
    world_id            INT NOT NULL,
    attacker_id         INT NULL,        -- NULL for monster initiator
    defender_id         INT NULL,
    attacker_city_id    INT NULL,
    defender_city_id    INT NULL,
    target_type         TINYINT NOT NULL,
    target_id           INT NULL,
    outcome             ENUM('attacker_wins','defender_wins','draw') NOT NULL,
    data_json           JSON NOT NULL,    -- full battle details
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    attacker_read       TINYINT(1) DEFAULT 0,
    defender_read       TINYINT(1) DEFAULT 0,
    INDEX (attacker_id, created_at),
    INDEX (defender_id, created_at),
    FOREIGN KEY (world_id) REFERENCES worlds(id)
);

-- SPY REPORTS
CREATE TABLE spy_reports (
    id                  INT PRIMARY KEY AUTO_INCREMENT,
    world_id            INT NOT NULL,
    spy_player_id       INT NOT NULL,
    target_player_id    INT NULL,
    target_type         TINYINT NOT NULL,
    target_id           INT NULL,
    target_x            SMALLINT NOT NULL,
    target_y            SMALLINT NOT NULL,
    outcome             ENUM('success','blocked') NOT NULL,
    data_json           JSON NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    spy_read            TINYINT(1) DEFAULT 0,
    INDEX (spy_player_id, created_at),
    INDEX (target_player_id, created_at),
    FOREIGN KEY (world_id) REFERENCES worlds(id)
);

-- ALLIANCES
CREATE TABLE alliances (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    world_id        INT NOT NULL,
    name            VARCHAR(50) NOT NULL,
    tag             VARCHAR(6) UNIQUE NOT NULL,
    leader_id       INT NOT NULL,
    description     TEXT NULL,
    member_capacity SMALLINT DEFAULT 30,
    treasury_food   BIGINT DEFAULT 0,
    treasury_lumber BIGINT DEFAULT 0,
    treasury_stone  BIGINT DEFAULT 0,
    treasury_gold   BIGINT DEFAULT 0,
    power           BIGINT DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (world_id),
    INDEX (power),
    FOREIGN KEY (leader_id) REFERENCES players(id),
    FOREIGN KEY (world_id) REFERENCES worlds(id)
);

CREATE TABLE alliance_members (
    alliance_id     INT NOT NULL,
    player_id       INT NOT NULL,
    role            ENUM('leader','vice_leader','officer','veteran','member') DEFAULT 'member',
    joined_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (alliance_id, player_id),
    UNIQUE KEY one_alliance_per_player (player_id),
    FOREIGN KEY (alliance_id) REFERENCES alliances(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id)
);

CREATE TABLE alliance_diplomacy (
    alliance_a_id   INT NOT NULL,
    alliance_b_id   INT NOT NULL,
    relation        ENUM('ally','nap','war') NOT NULL,
    set_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (alliance_a_id, alliance_b_id),
    FOREIGN KEY (alliance_a_id) REFERENCES alliances(id) ON DELETE CASCADE,
    FOREIGN KEY (alliance_b_id) REFERENCES alliances(id) ON DELETE CASCADE
);

CREATE TABLE alliance_research (
    alliance_id     INT NOT NULL,
    research_code   VARCHAR(50) NOT NULL,
    level           SMALLINT DEFAULT 0,
    PRIMARY KEY (alliance_id, research_code),
    FOREIGN KEY (alliance_id) REFERENCES alliances(id) ON DELETE CASCADE
);

CREATE TABLE alliance_help (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    alliance_id     INT NOT NULL,
    player_id       INT NOT NULL,
    task_type       ENUM('building','research','training','healing') NOT NULL,
    task_id         INT NOT NULL,
    helps_received  INT DEFAULT 0,
    max_helps       INT DEFAULT 30,
    posted_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closes_at       DATETIME NOT NULL,
    INDEX (alliance_id, closes_at),
    FOREIGN KEY (alliance_id) REFERENCES alliances(id) ON DELETE CASCADE
);

CREATE TABLE alliance_chat (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    alliance_id     INT NULL,        -- NULL for World channel
    player_id       INT NOT NULL,
    channel         ENUM('world','alliance') NOT NULL,
    message         TEXT NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (alliance_id, created_at),
    INDEX (channel, created_at)
);
```

### 22.3 Treasure & charm tables

```sql
-- PLAYER TREASURE OWNERSHIP
CREATE TABLE player_treasures (
    player_id       INT NOT NULL,
    treasure_id     SMALLINT NOT NULL,
    fragments       INT DEFAULT 0,
    star_level      TINYINT DEFAULT 0,    -- 0 = locked, 1 = unlocked, up to 5
    is_equipped     TINYINT(1) DEFAULT 0,
    slot_index      TINYINT NULL,          -- 1..6
    PRIMARY KEY (player_id, treasure_id),
    FOREIGN KEY (player_id) REFERENCES players(id)
);

-- ACTIVE CHARMS
CREATE TABLE player_charms_active (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    player_id       INT NOT NULL,
    stat_category   ENUM('construction','research','troops_hp','troops_atk','troops_def','carry','speed','gathering') NOT NULL,
    grade           ENUM('normal','epic','legendary') NOT NULL,
    bonus_value     FLOAT NOT NULL,        -- 0.03, 0.05, 0.10
    activated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at      DATETIME NOT NULL,
    UNIQUE KEY one_active_per_category (player_id, stat_category),
    INDEX (expires_at),
    FOREIGN KEY (player_id) REFERENCES players(id)
);

-- CHARMS IN INVENTORY (not yet activated)
CREATE TABLE player_charms (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    player_id       INT NOT NULL,
    stat_category   ENUM('construction','research','troops_hp','troops_atk','troops_def','carry','speed','gathering') NOT NULL,
    grade           ENUM('normal','epic','legendary') NOT NULL,
    acquired_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (player_id),
    FOREIGN KEY (player_id) REFERENCES players(id)
);
```

### 22.4 Map state tables

```sql
-- FIELD OBJECTS (Resource Nodes, Gem Nodes, etc.)
CREATE TABLE field_objects (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    world_id        INT NOT NULL,
    object_code     INT NOT NULL,        -- 20100101 (Farm L1), etc.
    coord_x         SMALLINT NOT NULL,
    coord_y         SMALLINT NOT NULL,
    remaining       INT NOT NULL,        -- resource pool remaining
    spawned_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_gathered   DATETIME NULL,
    INDEX (world_id, coord_x, coord_y),
    INDEX (world_id, object_code),
    FOREIGN KEY (world_id) REFERENCES worlds(id)
);

-- MONSTERS ON MAP
CREATE TABLE field_monsters (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    world_id        INT NOT NULL,
    monster_code    INT NOT NULL,        -- 20200101 (Orc L0), etc.
    coord_x         SMALLINT NOT NULL,
    coord_y         SMALLINT NOT NULL,
    hp_current      INT NOT NULL,
    spawned_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (world_id, coord_x, coord_y),
    INDEX (world_id, monster_code),
    FOREIGN KEY (world_id) REFERENCES worlds(id)
);

-- SHRINES (CONQUEST EVENT TARGETS)
CREATE TABLE shrines (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    world_id        INT NOT NULL,
    shrine_code     VARCHAR(20) NOT NULL,    -- 'C1', 'C2', 'B1', 'A1', 'Congress'
    tier            ENUM('C','B','A','S') NOT NULL,
    coord_x         SMALLINT NOT NULL,
    coord_y         SMALLINT NOT NULL,
    owner_alliance_id INT NULL,
    captured_at     DATETIME NULL,
    secured_at      DATETIME NULL,         -- after 1h hold; locked from re-attack
    contesting_alliance_id INT NULL,
    contest_started_at DATETIME NULL,
    UNIQUE KEY one_shrine_per_code (world_id, shrine_code),
    INDEX (world_id, owner_alliance_id),
    INDEX (world_id, tier),
    FOREIGN KEY (world_id) REFERENCES worlds(id)
);

-- SHRINE PREREQUISITE GRAPH
CREATE TABLE shrine_dependencies (
    higher_shrine_id    INT NOT NULL,
    lower_shrine_id     INT NOT NULL,
    PRIMARY KEY (higher_shrine_id, lower_shrine_id),
    FOREIGN KEY (higher_shrine_id) REFERENCES shrines(id) ON DELETE CASCADE,
    FOREIGN KEY (lower_shrine_id) REFERENCES shrines(id) ON DELETE CASCADE
);
```

### 22.5 Inventory, items, & misc

```sql
-- INVENTORY
CREATE TABLE inventory (
    player_id       INT NOT NULL,
    item_code       INT NOT NULL,
    quantity        INT NOT NULL DEFAULT 0,
    PRIMARY KEY (player_id, item_code),
    FOREIGN KEY (player_id) REFERENCES players(id)
);

-- ACTIVE BUFFS (boost items, anti-recon, etc.)
CREATE TABLE active_buffs (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    player_id       INT NOT NULL,
    buff_code       INT NOT NULL,           -- ITEM_CODE_FOOD_BOOST_8H, etc.
    activated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at      DATETIME NOT NULL,
    INDEX (expires_at),
    FOREIGN KEY (player_id) REFERENCES players(id)
);

-- NOTIFICATIONS (for polling)
CREATE TABLE notifications (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    player_id       INT NOT NULL,
    notif_type      VARCHAR(30) NOT NULL,  -- 'attack','battle_report','spy_report','build_done','message'
    data_json       JSON NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at         DATETIME NULL,
    INDEX (player_id, read_at),
    FOREIGN KEY (player_id) REFERENCES players(id)
);

-- MAILS / IN-GAME MESSAGES
CREATE TABLE messages (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    sender_id       INT NULL,            -- NULL = system
    receiver_id     INT NOT NULL,
    subject         VARCHAR(100) NOT NULL,
    body            TEXT NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at         DATETIME NULL,
    INDEX (receiver_id, created_at)
);

-- TRADING POST: VIP SHOP REFRESH STATE PER WORLD
CREATE TABLE vip_shop_refresh (
    world_id        INT NOT NULL PRIMARY KEY,
    refresh_index   INT NOT NULL,
    refreshed_at    DATETIME NOT NULL,
    FOREIGN KEY (world_id) REFERENCES worlds(id)
);

-- TRADING POST: VIP SHOP PURCHASES (resets each refresh cycle)
CREATE TABLE vip_shop_purchases (
    player_id       INT NOT NULL,
    world_id        INT NOT NULL,
    refresh_index   INT NOT NULL,
    bundle_id       VARCHAR(50) NOT NULL,
    bought_count    INT DEFAULT 0,
    PRIMARY KEY (player_id, world_id, refresh_index, bundle_id),
    FOREIGN KEY (player_id) REFERENCES players(id),
    FOREIGN KEY (world_id) REFERENCES worlds(id)
);

-- TRADING POST: CARAVAN STATE (per player, rolled at refresh)
CREATE TABLE caravan_state (
    player_id       INT NOT NULL,
    world_id        INT NOT NULL,
    refreshed_at    DATETIME NOT NULL,
    next_refresh_at DATETIME NOT NULL,
    slots_json      JSON NOT NULL,
    PRIMARY KEY (player_id, world_id),
    INDEX (next_refresh_at),
    FOREIGN KEY (player_id) REFERENCES players(id),
    FOREIGN KEY (world_id) REFERENCES worlds(id)
);

-- ADMIN
CREATE TABLE admins (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    player_id       INT NOT NULL,
    role            ENUM('superadmin','moderator') DEFAULT 'moderator',
    FOREIGN KEY (player_id) REFERENCES players(id)
);

CREATE TABLE admin_logs (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    admin_id        INT NOT NULL,
    action          VARCHAR(100) NOT NULL,
    target_player_id INT NULL,
    target_world_id INT NULL,
    details_json    JSON NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (created_at)
);
```

### 22.6 Schema design principles

- **JSON columns** for unstructured payload (battle data, march troops, drops). MySQL 8 JSON support is robust.
- **Cascade deletes** on city → city_buildings, city_troops, etc. Account deletions are rare and handled separately.
- **Indexes** on every (world_id, coord_x, coord_y) tuple for spatial lookups.
- **Indexes** on every `_at` timestamp used by cron tick.
- **No foreign keys** between worlds and shared cross-world tables (players, inventory, notifications) — players can play multiple worlds in future.

### 22.7 Security / sanitization checklist

Per-request:
- Bind all SQL parameters via PDO `prepare()`
- HTML-escape all user output via `htmlspecialchars()` in views
- CSRF token in every form (regenerated per session)
- Rate-limit: max 10 actions/sec per player (sliding window)
- Login: bcrypt with cost ≥ 12; lockout after 5 failed attempts in 15 min
- Inputs from JSON requests: validate schema before use (Sanitize integers, enforce string length)


---

## 23. API Endpoints

The API follows a REST-like pattern with JSON request/response. The single entry point is `index.php?path=...` (or rewritten via `.htaccess` to `/api/...`).

All responses are JSON with this envelope:
```json
{
    "ok": true,
    "data": { ... },
    "error": null
}
```

On error:
```json
{
    "ok": false,
    "data": null,
    "error": {
        "code": "NOT_ENOUGH_RESOURCES",
        "message": "Insufficient food for upgrade"
    }
}
```

### 23.1 Authentication

| Endpoint | Method | Description |
|---|---|---|
| `/auth/register` | POST | Create new account |
| `/auth/login` | POST | Login, sets session cookie |
| `/auth/logout` | POST | Logout |
| `/auth/me` | GET | Current user info |
| `/auth/password/reset` | POST | Request password reset email |
| `/auth/password/change` | POST | Change password (logged in) |

### 23.2 Cities & buildings

| Endpoint | Method | Description |
|---|---|---|
| `/city/state` | GET | Full city snapshot (resources, buildings, troops, queue) |
| `/city/upgrade-building` | POST | Start a building upgrade |
| `/city/cancel-build/:queue_id` | POST | Cancel a queued build |
| `/city/instant-build/:queue_id` | POST | Use GEMS or speedup to instant-finish |
| `/city/repair-wall` | POST | Spend resources or items to repair wall HP |
| `/city/list-builds` | GET | Active build queue |

### 23.3 Troops & training

| Endpoint | Method | Description |
|---|---|---|
| `/troops/train` | POST | Train troops (specify type + count) |
| `/troops/cancel-train/:queue_id` | POST | Cancel training queue |
| `/troops/list` | GET | All troops in city, broken down by type |
| `/troops/promote` | POST | Promote troops from T_n to T_n+1 |
| `/troops/heal` | POST | Heal wounded troops (from hospital) |

### 23.4 Research

| Endpoint | Method | Description |
|---|---|---|
| `/research/list` | GET | All player research (current levels per code) |
| `/research/start` | POST | Start research on a node |
| `/research/cancel/:queue_id` | POST | Cancel research |
| `/research/instant/:queue_id` | POST | Instant complete |
| `/research/data` | GET | Static research data (from JSON) |

### 23.5 Map

| Endpoint | Method | Description |
|---|---|---|
| `/map/tiles` | GET (params: x_min, y_min, x_max, y_max) | Get all map objects in a region |
| `/map/tile/:x/:y` | GET | Detailed info on one tile |
| `/map/jump-to/:player_id` | GET | Get coordinates of another player's city |

### 23.6 Marches

| Endpoint | Method | Description |
|---|---|---|
| `/march/dispatch` | POST | Dispatch a new march (gather/attack/etc.) |
| `/march/recall/:march_id` | POST | Recall a marching column |
| `/march/list` | GET | All active marches (incoming + outgoing) |
| `/march/spy` | POST | Send a spy mission |

### 23.7 Battle

| Endpoint | Method | Description |
|---|---|---|
| `/battle/reports` | GET | List battle reports (paginated) |
| `/battle/report/:id` | GET | Detailed battle report |
| `/battle/mark-read/:id` | POST | Mark report as read |

### 23.8 Spy

| Endpoint | Method | Description |
|---|---|---|
| `/spy/reports` | GET | List spy reports |
| `/spy/report/:id` | GET | Spy report detail |

### 23.9 Treasures & charms

| Endpoint | Method | Description |
|---|---|---|
| `/treasures/list` | GET | All owned treasures |
| `/treasures/data` | GET | Static treasure data |
| `/treasures/equip/:treasure_id/:slot` | POST | Equip to slot |
| `/treasures/unequip/:slot` | POST | Unequip slot |
| `/treasures/upgrade/:treasure_id` | POST | Spend fragments to upgrade |
| `/treasures/exchange-pieces` | POST | Exchange Piece Fragments for Item Fragments |
| `/charms/list` | GET | List charms in inventory + active |
| `/charms/activate/:charm_id` | POST | Activate a charm |

### 23.10 Inventory

| Endpoint | Method | Description |
|---|---|---|
| `/inventory/list` | GET | Items grouped by category |
| `/inventory/use/:item_code` | POST | Use an item (boost, speedup, resource pack) |

### 23.11 Alliance

| Endpoint | Method | Description |
|---|---|---|
| `/alliance/me` | GET | My alliance info |
| `/alliance/list` | GET | List of alliances (paginated) |
| `/alliance/create` | POST | Create new alliance |
| `/alliance/join/:alliance_id` | POST | Apply to join |
| `/alliance/leave` | POST | Leave alliance |
| `/alliance/invite/:player_id` | POST | Invite player |
| `/alliance/kick/:player_id` | POST | Kick member |
| `/alliance/promote/:player_id` | POST | Change role |
| `/alliance/donate-resources` | POST | Donate to treasury |
| `/alliance/research/start` | POST | Start alliance research |
| `/alliance/help/:task_id` | POST | Help an alliance task |
| `/alliance/diplomacy/set` | POST | Change diplomacy with another alliance |

### 23.12 Conquest

| Endpoint | Method | Description |
|---|---|---|
| `/conquest/state` | GET | Current event phase, all shrines status |
| `/conquest/shrines` | GET | All shrines with current owner / contest state |
| `/conquest/shrine/:id` | GET | Detail on one shrine |
| `/conquest/rally/start` | POST | Start a rally on a shrine or rally-only monster |
| `/conquest/rally/join/:rally_id` | POST | Join an existing rally |
| `/conquest/rally/leave/:rally_id` | POST | Leave before launch |

### 23.13 Shop

| Endpoint | Method | Description |
|---|---|---|
| `/shop/items` | GET | GEMS shop items |
| `/shop/buy` | POST | Spend GEMS on item |
| `/shop/payment/intent` | POST | Stripe / payment gateway integration (Phase 3) |

### 23.14 Trading Post

| Endpoint | Method | Description |
|---|---|---|
| `/trading-post/vip-shop` | GET | List VIP bundles available + remaining stock for current refresh |
| `/trading-post/vip-shop/buy` | POST | Buy N copies of a bundle (body: `{bundle_id, count}`) |
| `/trading-post/caravan` | GET | Current caravan slots + countdown to next refresh |
| `/trading-post/caravan/buy` | POST | Buy a caravan slot (body: `{slot_index}`) |
| `/trading-post/trade/list` | GET | List active P2P trades [Phase 2+] |
| `/trading-post/trade/post` | POST | Post a new trade offer [Phase 2+] |
| `/trading-post/trade/accept` | POST | Accept a trade offer [Phase 2+] |

### 23.15 Notifications

| Endpoint | Method | Description |
|---|---|---|
| `/notifications/poll` | GET | Long-polling endpoint, returns new events |
| `/notifications/list` | GET | Recent notifications |
| `/notifications/read/:id` | POST | Mark notification as read |

### 23.16 Admin

| Endpoint | Method | Description |
|---|---|---|
| `/admin/world/list` | GET | All worlds |
| `/admin/world/create` | POST | Create world |
| `/admin/world/edit/:id` | POST | Edit world settings |
| `/admin/player/list` | GET | All players (filterable) |
| `/admin/player/grant-resources` | POST | Manually adjust player resources |
| `/admin/player/ban/:player_id` | POST | Ban player |
| `/admin/logs` | GET | Admin audit log |

---

## 24. Notifications & Polling

### 24.1 Polling architecture

Conquer uses **HTTP long-polling** for real-time updates. No WebSocket dependency.

Frontend polls `/notifications/poll` every **15 seconds** when the page is in focus. Polls slow to **60 seconds** when the page is hidden (browser tab not active).

### 24.2 Poll request

```javascript
// Frontend (Alpine.js / vanilla JS)
async function poll() {
    const response = await fetch('/api/notifications/poll', {
        method: 'GET',
        credentials: 'same-origin'
    });
    const data = await response.json();
    
    if (data.ok) {
        if (data.data.attacks > 0) showIncomingAttacks(data.data.attacks);
        if (data.data.battle_reports > 0) updateBadge('battle_reports', data.data.battle_reports);
        if (data.data.alliance_chat) appendChatMessages(data.data.alliance_chat);
        updateResources(data.data.resources);
    }
}

setInterval(poll, document.hidden ? 60000 : 15000);
```

### 24.3 Poll response

```json
{
    "ok": true,
    "data": {
        "resources": {
            "food": 12345,
            "lumber": 12345,
            "stone": 12345,
            "gold": 12345
        },
        "attacks_incoming": 1,
        "build_completed": ["building_queue_id_42"],
        "research_completed": [],
        "training_completed": [],
        "battle_reports": 2,
        "spy_reports": 0,
        "messages": 0,
        "alliance_chat": [
            {"player": "Sven", "msg": "Hey", "ts": 1715000000}
        ],
        "marches_completed": ["march_id_99"],
        "active_buffs": [...],
        "wall_hp": 8500,
        "ap_current": 75
    }
}
```

### 24.4 Adaptive polling

The polling interval can dynamically adjust:

- **15s** default
- **5s** when there's an active march arriving in <30s (high-resolution UI updates)
- **60s** when no active activity (idle player)
- **Stop** entirely when user is away (page-hidden + 5min)

This keeps server load reasonable while feeling responsive during action.

### 24.5 Server-side optimization

The poll endpoint is hit frequently. Optimizations:

- **APCu cache** for static data (research config, building costs)
- **Batched DB queries**: one query for resources, one for queues, one for chat — total <5 queries per poll
- **ETag header**: client sends `If-None-Match`, server returns `304 Not Modified` if no changes (saves payload)
- **Indexed lookups only**: every poll query hits an index

Target: **< 50ms server response** per poll, even at 5,000 concurrent users.


---

## 25. Admin Panel

### 25.1 Overview

The admin panel is a separate, password-protected entry point at `/admin/`. Only users in the `admins` table can access it. It exposes operational tools for:

- World management (create/edit/pause/close worlds)
- Player management (search, view, ban, grant items)
- Game data (view JSON files, hot-reload after edits)
- Audit log (every admin action is logged)
- Dev tools (instant-finish any task, jump-to-coordinate, kill monsters, force events)

The admin panel runs on the same codebase but uses a separate Alpine app and styled UI to avoid confusion with player UI.

### 25.2 Roles

| Role | Permissions |
|---|---|
| **superadmin** | Everything: worlds, players, refunds, ban, edit DB, admin management |
| **moderator** | Read-only player view, chat moderation, ban (with reason logging), notification broadcast |

A superadmin promotes a player to moderator by adding a row in `admins`.

### 25.3 Pages

#### 25.3.1 Dashboard

Top-level metrics for all worlds:
- Active players (last 24h)
- Active marches
- Active battles in last hour
- New registrations (7-day chart)
- Revenue (GEMS purchased, last 30 days) [Phase 3+]
- System status: cron tick last run, DB queries/sec, error count

#### 25.3.2 World management

- List of all worlds with status, player count, age
- Create new world: name, slug, speed factor, start date
- Edit world: change speed factor, mortality rate, gather factor
- Pause world (no marches resolve, no production gain — useful for emergency maintenance)
- Close world (read-only mode; players can view but not act)

#### 25.3.3 Player management

- Search by username, email, ID, alliance tag, world
- Player detail page:
  - Account info (created, last login, VIP, GEMS, IP history)
  - All cities across worlds (coords, level, resources, troops summary)
  - Battle / spy report history (filterable)
  - Action history (login, build, march, payment events)
  - **Edit actions** (superadmin):
    - Grant resources (Food/Lumber/Stone/Gold/GEMS)
    - Grant items (any item code, quantity)
    - Grant VIP points
    - Force-complete any active queue
    - Teleport city to coordinates
    - Adjust wall HP
    - Reset password (sends email with temp password)
    - Ban / unban (with reason)
    - Mark city as inactive

#### 25.3.4 Dev mode

A "developer mode" toggle for the logged-in admin player. When ON:

- Build/research/training are instant (no time wait)
- All buildings unlocked / requirements bypassed
- Infinite resources (always shows max)
- Free marches (no resource cost on dispatch)
- All map objects visible (including hidden cities)

This is ONLY for testing on dev/staging worlds. **Disabled in production** unless world.is_dev is true. Activation logged to admin_logs.

#### 25.3.5 Game data

- View / edit JSON files in `/data/` (browser-based JSON editor, validated)
- Hot-reload: after editing, click "Reload" to clear APCu cache and force config re-read
- Diff view: compare current JSON with last committed version (Git)

This avoids SSH for routine balance changes.

#### 25.3.6 Audit log

Every admin action logged:
- Timestamp
- Admin username
- Action (e.g. "grant_resources")
- Target (player ID, world ID, etc.)
- Before / after values (for edits)
- Optional reason text

Filterable by admin, action type, date range. Read-only — never deletable.

#### 25.3.7 Chat moderation

- Live view of all world / alliance chat (real-time stream via polling)
- Hide message (soft delete; flagged for review)
- Issue mute (player cannot send chat messages for X duration)
- Issue ban (full account ban, requires reason)

### 25.4 Anti-cheat / monitoring

The admin panel surfaces flags when:

- A player gains > N resources/hour (production overflow alert)
- A player wins > 95% of battles in last 7 days (potential exploit)
- Multiple accounts log in from the same IP (potential alt-farming)
- Suspicious GEM purchase patterns (chargeback risk) [Phase 3+]
- Cron tick took > 30s in last execution (performance alert)

Flags are listed in dashboard for review. No auto-action — humans decide.

### 25.5 Backups

Daily automated DB backup via cron:
```
0 4 * * * /usr/bin/mysqldump --single-transaction --quick conquer | gzip > /backups/$(date +\%Y-\%m-\%d).sql.gz
```

Retention: 30 days local + weekly off-site copy [DEFAULT].
Recovery procedure documented in `docs/OPERATIONS.md` [TO BUILD].

---

## 26. Roadmap

This is a multi-year solo / hobby project. Realistic pacing assumes ~10-15 hours of dev time per week.

### 26.1 Phase 0 — Setup (1-2 weeks)

Done at project start.

- [ ] Project skeleton (folder structure, .htaccess, index.php router)
- [ ] DB schema initial migration
- [ ] Auth system (register / login / sessions)
- [ ] Data file imports (all `/data/*.json` from extracted sources)
- [ ] Config loader + APCu cache
- [ ] Basic layout with Alpine.js
- [ ] Local dev environment (XAMPP) + Hostinger staging deploy
- [ ] Git workflow + initial CI hooks (lint, test stubs)

### 26.2 Phase 1 — MVP Core (4-6 months)

The smallest playable game. **Single-player city builder + monster hunting + map exploration.** No alliances, no Conquest Event, no GEMS purchase.

**Sprint 1 (4 weeks): City basics**
- City view UI (list-based building grid)
- All 13 buildings with cost / time / level data loaded from JSON
- Build queue with one slot
- Resource production with on-read calculation
- Storage caps
- Wall HP and regen (no teleport yet)
- Building dependency validation

**Sprint 2 (4 weeks): Troops & research**
- Troop training UI
- All 15 troops definitions
- Research tree UI for all 3 player trees
- Research queue
- Buff stack engine (additive + multiplicative)
- Effective stat calculator

**Sprint 3 (4 weeks): Map**
- Canvas map renderer
- Tile streaming
- Field objects spawning
- Monsters spawning
- Click-to-info tile interactions
- Resource gathering (gather marches)

**Sprint 4 (4 weeks): Marches & battle**
- March system (dispatch, recall, return)
- Battle engine with full formula
- Wall damage + teleport on break
- Hospital + healing
- Battle reports
- Spy system + reports

**Sprint 5 (4 weeks): Polish & deploy**
- Tutorial flow (12 steps)
- Beginner shield
- Inventory UI
- Item use (resources, speedups, boosts)
- Daily quests (basic set)
- Notification polling
- Deploy to Hostinger production
- Soft launch with 5-20 testers

### 26.3 Phase 2 — Alliances + Treasures (3-4 months)

**Sprint 6 (4 weeks): Alliance basics**
- Alliance create / join / leave
- Member roles
- Alliance chat
- Alliance Help system
- Reinforcements (support marches)

**Sprint 7 (4 weeks): Treasure system**
- Treasure inventory
- Slot equipping
- Boost / Master Bonus system
- Fragment collection from monsters/chests
- Star upgrade UI
- Charm system (drops + activation)

**Sprint 8 (4 weeks): Advanced features**
- T5 troop training preparation (Academy L23+ research)
- Gem Nodes (rare map spawns)
- Diplomacy (Ally/NAP/Neutral/War)
- Alliance research trees (Battle + Production)
- Trading Post (resource trade between alliance members)

### 26.4 Phase 3 — Conquest Event + Rally (3-4 months)

**Sprint 9 (4 weeks): Rally system**
- Rally formation + join window
- Rally capacity + size cap
- Rally bonuses (Advanced Tree research)
- Rally-only monsters (Deathkar, Dragons stub)

**Sprint 10 (4 weeks): Shrines**
- 22 shrines + Congress placed on map
- Garrison NPC system
- Shrine capture mechanic (1h hold)
- Shrine prerequisite chain
- Shrine bonuses (passive buffs)

**Sprint 11 (4 weeks): Conquest Event loop**
- 4-phase event progression (C → C+B → C+B+A → C+B+A+S)
- Event scheduling (every 2 weeks)
- Event UI: world map, rankings, contributions
- Persistent ownership between events
- Event-end report (top alliances, MVP players)

**Sprint 12 (4 weeks): GEMS shop + monetization**
- Stripe integration (or PayPal)
- GEMS bundles for purchase
- Premium shop (speedups, resources, AP, chests)
- Castle skins (cosmetic)
- VIP point packages

### 26.5 Phase 4 — Polish, scale, retain (ongoing)

**Sprint 13+:**
- Mobile-friendly UI / touch controls
- PWA installability
- Castle skin marketplace
- Cross-world events
- Mastery system
- Detailed analytics dashboard
- Custom alliance banners
- World-wide chat search & filters
- Anti-cheat rules and monitoring rules
- Migration from shared hosting to VPS (when ~500 concurrent)
- Multi-language support (DE / FR / LU primary)
- Themed seasons (Halloween, Winter, etc.)
- Community features: forum, fan-art submissions, public API for stats

### 26.6 Phase 5 — Sunset / migration (someday)

If the game ever sunsets, the plan is:
- 6-month notice
- Free GEMS to all paying users for remaining time
- Optional: open-source the codebase
- Final tournament event
- Permanent archive page with player stats

---

## 27. Open Questions

Tracked unresolved decisions. Each item has a default that can be applied if no answer is given before the relevant sprint.

### 27.1 Game data patches

**[OQ-1] Castle.json Watch Tower removal**  
Current `castle.json` requires Watch Tower at L5 (and other levels) for upgrades. Watch Tower is removed from Conquer.  
**Patch needed**: replace `watch_tower` requirement in castle.json with `trading_post` (closest analog) at all referenced levels.  
**Default**: apply patch.

**[OQ-2] Monster type assignment**  
Monsters in source data have no troop type (Inf/Ranged/Cav). Should they be assigned a type for counter-cycle calculation?  
**Default**: NO — monsters fight as neutral (modifier 1.00). Simpler and matches LoK behavior.

**[OQ-3] Troop names re-theme**  
Names like "Knight", "Crusader", "Sniper" are too LoK-derivative. Need original names before launch.  
**Default**: keep current names through MVP, re-theme in Phase 2 (one alliance member can suggest naming pass).  
**Suggested theme**: medieval-fantasy with original flavor (e.g. "Pikeman → Halberdier → Vanguard → Zealot → Champion" for Infantry).

### 27.2 Game balance

**[OQ-4] Anti-Reconnaissance item codes**  
Owner indicated these items should exist in source data, but enum.py extract showed a gap (10102011, 10102012 not present in our sample).  
**Assumed codes**:
- `ITEM_CODE_ANTI_RECON_8H` = 10102011
- `ITEM_CODE_ANTI_RECON_1D` = 10102012  

**Default**: use these codes; verify against full LoK item list during Sprint 4.

**[OQ-5] Wall damage coefficient**  
Currently set at 10% of attacker damage pool routed to wall HP.  
**Open**: should this scale with attacker damage type, with wall level, or with castle level?  
**Default**: keep flat 10% for MVP.

**[OQ-6] T5 troop training cost balance**  
T5 troops (Crusader, Sniper, Dragoon) cost only Food/Lumber/Stone/Gold (no special resource — see §3.6).  
**Default**: T5 cost = 5-10× T4 cost. Tune during balancing based on F2P pacing data. Original "Crystal" gating has been removed; T5 is gated by Academy L30 and research only.

**[OQ-7] Mortality rate activation timing**  
Phase 2 activates `base_mortality_rate` from 0.0 to nonzero. When exactly?  
**Default**: at start of Phase 2 (Sprint 6), set to 0.2 (20% base mortality), tune from there based on data.

**[OQ-8] Resource Box scaling formula**  
Resource boxes scale with player Castle level. Exact formula?  
**Default**: `box_value = base_value × (1.5 ^ castle_level)`.

### 27.3 Conquest event details

**[OQ-9] Per-shrine bonuses (specific)**  
Each shrine should have a unique passive bonus to make capture choice strategic. MVP just uses tier-wide bonuses.  
**Default**: keep tier-wide bonuses for Phase 3 launch. Define per-shrine perks in Phase 4.

**[OQ-10] Rally Capacity formula**  
The 20k-500k linear scale per Hall of Alliance level may be too generous at high levels.  
**Default**: keep current scale; adjust with alliance research limits.

**[OQ-11] Alliance disband — treasury distribution**  
When an alliance is disbanded, what happens to its treasury?  
**Default**: forfeit (treasury becomes void). Alternative considered: distribute equally among members at time of disband. Final decision before Phase 2.

**[OQ-12] Shrine ownership during inactivity**  
If an alliance owns a shrine but the alliance becomes inactive (no logins for 14 days), should the shrine be released?  
**Default**: yes — auto-release shrines from inactive alliances at next event boundary.

### 27.4 UX / UI

**[OQ-13] Map view: illustrated city vs list view**  
MVP uses list-based city view. When does the illustrated city view ship?  
**Default**: Phase 2, after alliance system. Asset production blocks this.

**[OQ-14] Multi-language support**  
DE / FR / LU strings are not in MVP. When?  
**Default**: Phase 4. EN-only for MVP, allowing focused testing.

**[OQ-15] PWA / mobile install**  
Browser-only or installable PWA?  
**Default**: Plain browser for MVP. Add PWA manifest in Phase 4.

### 27.5 Monetization

**[OQ-16] Real money payment processor**  
Stripe vs PayPal vs Lemon Squeezy?  
**Default**: Stripe for EU base; LemonSqueezy as backup for non-EU. Decide before Phase 3.

**[OQ-17] Refund policy**  
What's the refund policy for accidentally-spent GEMS?  
**Default**: case-by-case, admin discretion. Document policy in `/docs/REFUND_POLICY.md` before Phase 3.

**[OQ-18] Subscription model**  
Should there be a monthly subscription tier with daily VIP point boost?  
**Default**: NO at launch. Reconsider after 3 months of revenue data.

### 27.6 Operational

**[OQ-19] Hostinger → VPS migration trigger**  
What metrics signal when to leave shared hosting?  
**Default**: migrate when 500 concurrent players OR DB > 5GB OR cron tick > 10s.

**[OQ-20] Backup strategy**  
Daily mysqldump is in §25.5. Is that sufficient?  
**Default**: yes for MVP. Add hourly incremental + S3 off-site after Phase 2.

**[OQ-21] Legal review for trademark / IP**  
The game is heavily inspired by LoK. Names, art, lore are being re-themed. Is more legal review needed?  
**Default**: do an IP review before Phase 4 public launch. For MVP/closed beta, current state is acceptable (all art is original or placeholder).

---

## 28. Asset Specifications

A complete reference for all visual assets needed for the game ships as a separate document: **`Conquer_Asset_Specifications.pdf`**. This 16-page document covers:

- Map design philosophy (top-down 2D quadratic tile grid, 64×64 px tiles)
- Sprite size reference for all element types
- Detailed counts and visual descriptions per category (terrain, cities, monsters, treasures, charms, UI)
- AI generation prompts for static sprites (per monster, per troop tier, per terrain type)
- AI animation prompts for monster idle/attack/death animations
- Production tips (style guide, color palettes, free asset sources, sprite sheet packing)
- File organization and naming conventions
- Sprint 0 minimum asset plan (~25-40 hours = playable prototype)

**Total MVP asset count: ~330 sprites, estimated 230-375 designer hours.**

The PDF is the canonical reference for all sprite work — refer to it when commissioning art, evaluating AI-generated assets, or planning asset sprints. Update the PDF when significant changes occur (new monsters, new building tiers, etc.) and bump the asset spec version.

---

## End of Specification

This document is **v1.5** of the Conquer specification.

Updates and amendments should bump the version (1.6, 1.7, ...) and append a changelog at the bottom of this file.

### Changelog

| Version | Date | Author | Notes |
|---|---|---|---|
| 1.0 | 2026-05-04 | Sven Manderscheid + Claude | Initial complete specification (under former working title "Ascendancy") |
| 1.1 | 2026-05-04 | Sven Manderscheid + Claude | Added §4.9 Trading Post (VIP Shop, Caravan, Resource Trade); added Trading Post DB tables to §22; added Trading Post API endpoints to §23.14; renumbered Admin API to §23.16 |
| 1.2 | 2026-05-04 | Sven Manderscheid + Claude | Caravan refresh fixed to 00:00/08:00/16:00 UTC (server-wide cron, not rolling); VIP Shop refresh fixed to Monday 00:00 UTC; removed Force-Refresh endpoint and GEMS cost (no longer purchasable) |
| 1.3 | 2026-05-04 | Sven Manderscheid + Claude | Drop economy rebalanced: nerfed solo monster SP quantities by ~50%, dragon Build/Research SP by ~80% to match compressed build curve. Added §14.10 Drop Economy Overview, §14.11 Speedup Sources Matrix, §14.12 Pacing Reference (F2P Endgame target 3-5 months for Castle L25), §14.13 Treasure Goblin spec (NEW solo monster, F2P Build/Research SP source), §14.14 Charm drops clarification. New `data/monsters.json` shipped alongside spec. Updated §14.3 monster type list (Goblin Lv 1-5, not 1-10). |
| 1.4 | 2026-05-04 | Sven Manderscheid + Claude | **Crystal resource removed completely** (§3.6 rewritten as T5/Mythic costs note, §3.7 GEMS expanded with full F2P income table, §6.4 crystal_gathering_speed removed, §7.4 Crystal Mine entry removed, §22 schema crystal column removed, §27 OQ-6 rewritten). T5 troops now cost only standard resources. **GEMS drops from monsters added** (15% chance × 10/30/40 GEMS for solo, scaling for rally/boss monsters — see §14.15). **Charm drop table finalized** (4-tier per Lv: 0-3/4-6/7-8/9-10, only Orc/Skeleton/Golem drop charms — see §14.14). **F2P Endgame target updated to ~12 months for Castle L30 + T5** (§14.12 rewritten to match owner's design intent). **§7.10 World Spawn System added** with full hourly cron-tick spawn table for all entity types per sector. Goblin spawn rates updated to 6/4/2/1 per sector/h (Lv 1-5). **§28 Asset Specifications added** — references new asset specification PDF companion document (16 pages, all sprite specs + AI prompts + monster animation prompts). New `data/charms.json` ships with this version; `data/monsters.json` updated with charm_drop blocks per finalized table and gems_drop blocks per all monsters; new `data/world_spawn.json` ships with spawn rates and caps. |
| 1.5 | 2026-05-04 | Sven Manderscheid + Claude | **Project renamed from "Ascendancy" to "Conquer"**. After extensive trademark/domain research showed "Ascendancy" was heavily conflict-laden (Logic Factory 1995 4X, OneMoreTurnGames 2025 Kickstarter, Natures Ascendancy EU TM, Path of Exile association, ascendancy.com premium-priced), the project owner decided to use **"Conquer" as a temporary internal working codename** for the duration of development. The final marketing name will be chosen later, likely with assistance from an EU trademark lawyer (€500-1500 for proper recherche). All 17 occurrences of "Ascendancy" in this spec have been replaced with "Conquer". The asset specification PDF has been renamed to `Conquer_Asset_Specifications.pdf`. **No technical changes** in this version — this is purely a project-name rebrand. All gameplay mechanics, balance values, drop tables, and roadmap items remain unchanged from v1.4. |

