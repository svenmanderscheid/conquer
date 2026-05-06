# CLAUDE.md — Project Orientation

This file is read **first** by Claude Code when entering this repository. It explains how to navigate and contribute to the project.

## What is this project?

**Conquer** (working codename — final name TBD) is a browser-based 4X strategy MMO. It's a hobby project by Sven Manderscheid built as a spiritual successor to League of Kingdoms (sunsetting May 2026).

## How to orient yourself

### Step 1 — Read the spec section relevant to the task

The master specification is `docs/SPEC.md` (~4,170 lines, 28 sections). **Don't read it all at once.** Read only the section relevant to what you're working on. Use the table of contents at the top (lines 1-50) to navigate.

Common section pointers:
- Resource system → §3
- Buildings → §4
- Map / world → §7
- World spawn cron → §7.10
- Marches / battle → §8-9
- Treasures / charms → §12-13
- Monsters / drop economy → §14
- VIP system → §17
- Database schema → §22
- API endpoints → §23
- Sprint roadmap → §26
- Open questions (unresolved decisions) → §27
- Asset specifications → §28 + `docs/Conquer_Asset_Specifications.pdf`

### Step 2 — Check `data/*.json` for canonical game values

The JSON files in `data/` are the **single source of truth** for game balance and mechanics:

- `data/monsters.json` — All 58 monster entries with stats, drops, and spawn metadata
- `data/charms.json` — 24 charm types + rarity distribution per monster tier
- `data/world_spawn.json` — Hourly cron spawn rates and per-sector caps for all entities

If the spec and the JSON disagree, **the JSON is authoritative** for balance values. If you change values, update the JSON file, not hardcoded constants in PHP.

### Step 3 — Follow the established conventions

This codebase uses **vanilla PHP 8.2 OOP, no framework**. The owner has rejected Laravel/Symfony intentionally — the project is a learning vehicle and he wants to read every line.

Conventions:
- PSR-4-style autoloading via a small custom autoloader in `src/Autoloader.php`
- PDO for all database access (no ORM)
- Strict types (`declare(strict_types=1);`) at the top of every PHP file
- All input validated server-side; never trust the client
- HTML/CSS/JS in `public/` is mostly inline-script-tag Alpine.js, no build step
- Game data loaded once at request start via `src/GameData.php` which JSON-decodes the `data/*.json` files into PHP arrays
- All time stored as UTC datetimes in MySQL; converted to user TZ only at display layer

## How to do specific tasks

### Adding a new building

1. Read `docs/SPEC.md` §4 (Buildings)
2. Check the "13 buildings" table — if your building isn't there, the owner hasn't approved it. Stop and ask.
3. Add config to `data/buildings/{name}.json`
4. Add migration to `migrations/` if any DB columns needed
5. Update `src/Game/Buildings/Building.php` if behavior differs from the standard pattern
6. Test in `tests/buildings/`

### Adding a new monster

1. Read `docs/SPEC.md` §14 (Monster system)
2. Add entry to `data/monsters.json` following the existing schema
3. Add spawn rate to `data/world_spawn.json`
4. Run `php tests/validate_data.php` to ensure JSON is valid
5. Note: only Orc/Skeleton/Golem drop charms (per §14.14). Don't add `charm_drop` to other monster types.

### Implementing a new API endpoint

1. Read `docs/SPEC.md` §23 for the API conventions
2. Add the route to `public/api/index.php`
3. Add the handler class to `src/Api/Handlers/`
4. All endpoints must:
   - Validate session (use `src/Auth/Session.php`)
   - Validate input (use `src/Validation/`)
   - Return `{"ok": true, "data": {...}}` on success or `{"ok": false, "error": "..."}` on error
   - Log errors to `logs/api_errors.log`

### Modifying balance values

1. **Don't touch hardcoded constants** in PHP. Look in `data/*.json` first.
2. If you must add a new tunable, put it in `data/balance.json` (Sprint 1 deliverable)
3. Document the change in a PR description. Pacing is calibrated for ~12-month F2P L30 per §14.12.
4. If your change might break the pacing target, flag it for the owner.

## How NOT to break things

- **Don't introduce a framework.** No Laravel, no Symfony, no Slim. Plain PHP only.
- **Don't add a build step.** No webpack, no Vite, no TypeScript transpilation. Direct file editing.
- **Don't store secrets in code.** Use `config/secrets.php` (gitignored) or environment variables.
- **Don't make breaking changes to data file schemas** without bumping the version field at the top of the JSON.
- **Don't deploy to production directly.** All work goes through `dev` branch first, manually merged to `main` after testing.
- **Don't use blockchain, NFTs, or crypto** for anything. The owner has explicitly excluded these.

## When unsure — ask

If a design question comes up that isn't answered by the spec or by `data/*.json`, **don't guess**. Ask the owner. Common cases:
- New mechanic that touches multiple systems
- Balance value tuning that might shift the F2P pacing
- Anything that affects the F2P-vs-paid player gap
- Any visual / artistic decision (refer to `docs/Conquer_Asset_Specifications.pdf` first)

The owner's design pillars (in priority order):
1. **No pay-to-win** — paid currency saves time, never unlocks content
2. **Strategic depth over UI polish** — game must reward thinking, not reflexes
3. **Long-term progression is the game** — 12-month F2P journey to L30 is intentional
4. **Solo-friendly** — alliance is rewarded but not required for endgame
5. **Code readable** — owner reads every PR, prefers clarity over cleverness

## Active development context

Current sprint: **Sprint 0 (asset foundation)** — see `docs/SPEC.md` §26.2  
Next sprint: **Sprint 1 (project skeleton + auth + DB schema)** — 4 weeks  
Open issues: see GitHub Issues tab

## Quick reference

- Owner: Sven Manderscheid (Ekki), Luxembourg
- Spec version: 1.5 (2026-05-04)
- World speed factor (default): 1.0
- Castle max level: 30
- Map size: 1024 × 1024 tiles
- Sectors per world: 8
- Max players per world: 5,000
- F2P endgame target: ~12 months Castle L30 + T5 troops
