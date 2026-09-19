# Conquer MVP — September 2026

> The latest cooperative PvE implementation and its verification status are described in [PVE_MVP_STATUS.md](PVE_MVP_STATUS.md). The text below records the earlier starter MVP.

## Current playable scope

Local URL: `http://localhost/conquer/`. Register a name and password on the welcome screen; the kingdom is stored in MySQL. Existing OAuth accounts remain supported by the previous routes.

- Illustrated 2D cartoon village with six interactive building areas and access to all thirteen building upgrades.
- Server-authoritative resource production, costs, capacity, construction prerequisites and timed upgrades.
- Three starter troop types, training costs, timers and persistent troop counts.
- Selected production and combat research, prerequisites and permanent bonuses.
- Nearby world targets, beginner Ork-Späher encounters, gathering, marching, return trips and visible combat reports with losses and resource rewards.
- Account registration/login/logout, CSRF protection, login rate limiting, persisted progress and a guided first-session checklist.
- Desktop and mobile navigation; keyboard-accessible controls and native dialogs.

This is the first playable strategy MVP. The legacy alliance, PvP, inventory, VIP, event and administration modules remain in the repository but are not part of this MVP's primary interface or verification claim. There is no new collectible-hero system: the chibi characters illustrate troop types and the guide.

## Architecture

`views/welcome.php` is the entry screen. `views/game.php`, `assets/js/game.js` and `assets/css/game.css` provide the new client. `/api/game/state` composes the read model; existing city, training, research and march endpoints execute actions. No local-storage-only game simulation, frontend build or new framework was introduced.

`FrontierService` replenishes a small number of nearby introductory encounters and resource nodes at most once per thirty minutes per player, under a world spawn lock. Existing world spawners continue to support the larger map. `MonsterData` reconciles historical world codes so combat and UI use the same definitions.

Time is UTC in PHP and in each database connection. Per-player database locks serialize requests and cron settlement. Troop training and march returns credit resources/troops atomically. The cron worker calls the same march service as browser polling.

## Run and verify

Start Apache and MySQL in XAMPP, configure the existing local `config/database.php`, then:

```text
C:\xampp\php\php.exe migrations\run.php
C:\xampp\php\php.exe tests\mvp_rules.php
C:\xampp\php\php.exe tests\mvp_smoke.php http://localhost/conquer
```

The HTTP smoke test creates a separate `MvpAutoHHMMSS` test kingdom on the local database. It uses real queue durations (about two minutes), checks spending and rejection paths, battles, gathering, returns, rewards and login persistence. Test kingdoms remain available in the local database; their generated passwords are not logged. It refuses remote hosts.

For unattended march settlement:

```text
C:\xampp\php\php.exe cron\march_tick.php
```

## Repairs included

- Resumable historical migrations and reconciliation of the early field-object schema.
- Added password-based entry rather than requiring configured OAuth credentials.
- Fixed duplicate resource accrual when passing a computed snapshot into an action.
- Gathering now checks, reserves and returns real troops; capacity depends on troop count.
- Corrected the gather-dispatch return type and wood/lumber resource mismatch.
- Unified monster definitions and connected research bonuses to actual production/combat.
- Added introductory encounters and actual resource loot to complete the gameplay loop.
- Shared browser/cron settlement and atomic return/training credits prevent duplicate rewards.

## Visual direction

The user's second reference is the current direction: bold outlined 2D cartoon fantasy with chibi figures, flat shading and warm colors. It supersedes older pixel-art requirements. Original generated artwork is stored under `assets/art/`. The active village is `village2.png`; `village.png` is an unused earlier visual draft. See `ART_DIRECTION.md` for generation provenance and prompts.

No deployment or Git push was performed as part of this local MVP implementation.

## Verification performed

On 2026-09-08 the rule suite and the full HTTP smoke suite passed. The successful HTTP run created `MvpAuto233930` and verified construction/training/research completion using real timers, a scout victory, gathering, exact troop returns, actual resource rewards, rejection of invalid actions and session persistence.

Manual browser checks covered registration, castle upgrade, research, troop training, attack dispatch, building cancellation/refund, the displayed researched production rate (306 food/hour), and all five primary navigation tabs. Desktop and 390×844 mobile layouts were inspected. The mobile document had no horizontal overflow and no broken image elements. Source, configuration, data and test URLs returned 403; the new public assets returned 200. Changed PHP files passed syntax checks and `assets/js/game.js` passed its syntax check.

This verification establishes the first-session MVP; it is not a load test of a populated MMO world or a certification of the legacy advanced modules.
