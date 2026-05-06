# CLAUDE.md — Project Orientation for Claude Code

This file is read **first** by Claude Code when entering this repository. It explains the project, current state, conventions, and how to contribute usefully.

## Project at a glance

**Conquer** (working codename — final marketing name TBD) is a browser-based 4X strategy MMO. Hobby project by Sven Manderscheid (Ekki) in Luxembourg, built as the spiritual successor to League of Kingdoms (which is sunsetting May 2026).

- **Genre:** 4X strategy MMO
- **Platform:** Browser (web + PWA), mobile-friendly
- **Stack:** PHP 8.2 OOP + MySQL 8 + Alpine.js 3 + Canvas 2D + HTTP polling
- **No framework.** No Composer dependencies. Plain PHP. The owner wants to read every line.
- **No build step.** Direct file editing, deploy via `git push` (Hostinger auto-deploys).

## Current state — Sprint 0 done, starting Sprint 1

**What's already in place:**

- ✅ Complete master specification at `docs/SPEC.md` (4170+ lines, 28 sections)
- ✅ Asset specifications at `docs/Conquer_Asset_Specifications.pdf` (16 pages)
- ✅ Game data files in `data/` — `monsters.json`, `charms.json`, `world_spawn.json`
- ✅ Repo deployed to `https://conquer.svenmanderscheid.lu/` showing Coming Soon page
- ✅ Hostinger auto-deploy via webhook on `git push origin main`
- ✅ SSL active (Let's Encrypt Lifetime cert)
- ✅ Security: per-folder `.htaccess` blocks `src/`, `data/`, `config/`, `docs/`, etc.
- ✅ `.htaccess` security verified — all sensitive paths return 403

**What's NOT yet in place (Sprint 1 work):**

- ❌ No real PHP code yet — `index.php` is a placeholder Coming Soon page
- ❌ No autoloader, no Bootstrap, no Database connection class
- ❌ No migrations beyond the stub `migrations/run.php`
- ❌ No authentication, no session management
- ❌ No game logic of any kind

## Sprint 1 goals (4 weeks, this sprint)

Per `docs/SPEC.md` §26.2:

1. **Project skeleton** — Autoloader (PSR-4-style, custom not Composer), Bootstrap class, Error/Exception handler, simple Logger
2. **DB Connection** — PDO singleton class with proper transaction support
3. **First migrations** — `users`, `cities`, `sessions` tables (DDL per `docs/SPEC.md` §22)
4. **Migration runner** — Replace stub `migrations/run.php` with working migration system
5. **Authentication** — Register, login, logout, session management, CSRF tokens
6. **API conventions** — `/api/*` endpoints with `{"ok": bool, "data": {...}, "error": "..."}` response format
7. **City view** — First real page — shows user's Castle + list of 13 buildings

By end of Sprint 1, a player should be able to: register, log in, see their (empty/default) city, and log out.

## How to navigate the spec

`docs/SPEC.md` is large — **don't read it cover to cover**. Read only the section relevant to your current task:

| Topic | Section |
|---|---|
| Resource system | §3 |
| Buildings (the 13 buildings, costs, requirements) | §4 |
| Map / world | §7 |
| Marches / battle | §8-9 |
| Treasures / charms | §12-13 |
| Monsters / drop economy | §14 |
| VIP system | §17 |
| **Database schema (Sprint 1 critical)** | **§22** |
| **API endpoints** | **§23** |
| Sprint roadmap | §26 |
| Open questions | §27 |
| Asset specifications | §28 |

For Sprint 1, the most-referenced sections are §22 (DB schema) and §23 (API conventions).

## Hard rules — DO NOT violate these

1. **No frameworks.** No Laravel, no Symfony, no Slim, no Lumen. Plain PHP only.
2. **No Composer dependencies.** No `composer.json`, no `vendor/`. Custom autoloader only.
3. **No build step.** No webpack, no Vite, no TypeScript, no Sass compiler. Direct file editing.
4. **No frontend frameworks beyond Alpine.js 3.** No React, Vue, Svelte, etc.
5. **Strict types.** Every `.php` file starts with `declare(strict_types=1);`.
6. **PDO only.** No `mysqli_*`, no ORM. Prepared statements always.
7. **All time stored as UTC.** Convert to user TZ only at display layer.
8. **All input validated server-side.** Never trust the client.
9. **Secrets never in code.** Use `config/database.php` and `config/app.php` (both gitignored).
10. **Don't break the data file schemas.** If you must change a `data/*.json` schema, bump its `version` field and ensure the loading code handles both versions during transition.
11. **No blockchain, NFTs, or crypto.** Owner has explicitly excluded these.

## Conventions

### File / Folder structure (FLAT layout — important)

The repo uses a flat layout because Hostinger Shared Hosting doesn't allow custom document root. The web document root IS the repo root. Sensitive folders are blocked via per-folder `.htaccess`.

```
conquer/                       ← Document root (= repo root)
├── index.php                  ← Front controller (web-accessible)
├── .htaccess                  ← Routing + security headers + RedirectMatch 403
├── assets/                    ← Sprites, icons, fonts (web-accessible)
├── src/                       ← BLOCKED — PHP source (your code lives here)
├── data/                      ← BLOCKED — JSON data files
├── config/                    ← BLOCKED — local-only config (gitignored)
├── migrations/                ← BLOCKED — SQL migrations
├── cron/                      ← BLOCKED — cron job scripts
├── tests/                     ← BLOCKED — test scripts
└── docs/                      ← BLOCKED — specifications
```

### Namespaces

Use the `Conquer\` namespace root, organized by subsystem:

```
Conquer\Bootstrap
Conquer\Db\Connection
Conquer\Db\Migration
Conquer\Auth\Session
Conquer\Auth\Login
Conquer\Auth\Register
Conquer\Game\Buildings\Building
Conquer\Game\Resources\Calculator
Conquer\Api\Handlers\BuildingHandler
Conquer\Api\Response
Conquer\Validation\Validator
Conquer\Logger
```

File path matches namespace: `Conquer\Db\Connection` lives in `src/Db/Connection.php`.

### PHP file template

```php
<?php
declare(strict_types=1);

namespace Conquer\Subsystem;

/**
 * Brief class description.
 */
final class ClassName
{
    public function __construct(
        private readonly Dependency $dep,
    ) {}

    public function method(): ReturnType
    {
        // implementation
    }
}
```

### API response format

Every API endpoint MUST return JSON in this shape:

**Success:**
```json
{
    "ok": true,
    "data": {
        // payload
    }
}
```

**Error:**
```json
{
    "ok": false,
    "error": "human_readable_error_code",
    "message": "Optional human-readable explanation"
}
```

HTTP status codes still matter (200 for success, 400 for validation, 401 for auth, 403 for permission, 500 for server error), but the body always uses this shape.

### Database conventions

- All table names lowercase plural snake_case: `users`, `cities`, `building_levels`
- Primary keys named `id` (BIGINT UNSIGNED AUTO_INCREMENT)
- Foreign keys named `<table_singular>_id`: `user_id`, `city_id`
- Timestamps `created_at` and `updated_at` on every table (DATETIME, default CURRENT_TIMESTAMP)
- All tables use `ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`
- Index everything that gets queried — but don't over-index

### Front-end conventions

- HTML in `*.php` files (templated server-side, no separate templating engine)
- CSS in `assets/css/main.css` (one file, mobile-first responsive)
- JS in `assets/js/main.js` (Alpine.js + vanilla — no jQuery)
- Inline `<script>` and `<style>` only for tiny page-specific tweaks

## Local development

The owner uses XAMPP on Windows at `C:\xampp\htdocs\conquer\`.

- **Apache + MySQL** running locally
- **Site URL:** `http://localhost/conquer/`
- **DB:** `conquer_dev` (root user, no password — XAMPP default)
- **Config files** (gitignored, copied from `*.example.php` templates):
  - `config/database.php` — local credentials
  - `config/app.php` — local settings (env=development, debug=true)

## Production deployment

- **Hostinger Shared Hosting** auto-deploys on `git push origin main`
- **URL:** `https://conquer.svenmanderscheid.lu/`
- **Path:** `/home/u171686647/domains/svenmanderscheid.lu/public_html/conquer/`
- **Database:** `u171686647_conquer` (set via Hostinger panel)
- **PHP:** 8.2+
- **SSL:** Let's Encrypt Lifetime cert active

After every push, verify:
```bash
bash tests/verify_security.sh https://conquer.svenmanderscheid.lu
```

## When unsure — ASK the owner

The owner reads every PR and is happy to clarify. Don't guess on:

- New mechanics that touch multiple systems
- Balance value tuning that might shift F2P pacing target
- Anything affecting the F2P-vs-paid player gap
- Visual / artistic decisions
- Whether something belongs in this Sprint or a later one

## Owner's design pillars (priority order)

1. **No pay-to-win** — paid currency saves time, never unlocks content
2. **Strategic depth over UI polish** — game must reward thinking, not reflexes
3. **Long-term progression is the game** — 12-month F2P journey to L30 is intentional, not a bug
4. **Solo-friendly** — alliance is rewarded but not required for endgame
5. **Code readable** — owner reads every PR, prefers clarity over cleverness

## How to do specific Sprint 1 tasks

### Adding the autoloader (task 1.1)

1. Create `src/Autoloader.php` — handles `Conquer\` namespace
2. Register it in `index.php` BEFORE any other code
3. Test: instantiate a class from `src/` and confirm it loads
4. Don't use Composer's autoloader — custom only

### Adding the database connection (task 1.2)

1. Read `docs/SPEC.md` §22 first to understand the schema you'll connect to
2. Create `src/Db/Connection.php` as a singleton with `getInstance()`
3. Read config from `config/database.php` (require it, don't hardcode)
4. Set PDO error mode to EXCEPTION
5. Use prepared statements, never string concatenation
6. Provide `transaction()` method that wraps callable + commit/rollback

### Adding migrations (tasks 1.3 + 1.4)

1. Naming: `migrations/0001_create_users_table.sql` (zero-padded, sequential)
2. Each file is a single migration. No "down" migrations (intentional — we don't roll back, we add forward-only fixes)
3. The runner (`migrations/run.php`) tracks applied migrations in a `migrations` table
4. Schema MUST match `docs/SPEC.md` §22 exactly

### Adding authentication (task 1.5)

1. Read `docs/SPEC.md` §22 for `users` and `sessions` tables
2. Read `docs/SPEC.md` §23 for API conventions
3. Use PHP's native `password_hash()` + `password_verify()` (Argon2 default)
4. Sessions stored in DB (not PHP file sessions) — for horizontal scalability later
5. Generate session tokens with `random_bytes(32)` + `bin2hex()`
6. CSRF token per session, validated on every state-changing request
7. Rate limit login attempts (5 per minute per IP)

## Active context for THIS Claude Code session

You are in the **Sprint 1** phase. Sprint 0 (foundation) is done. The site deploys but only shows a Coming Soon page. Real work starts now.

When the owner gives you a task, before writing code:

1. Read the relevant section(s) of `docs/SPEC.md`
2. Check if `data/*.json` has values you need
3. Check if a similar pattern exists already in `src/` (Sprint 1 means usually no — but as Sprint 1 progresses this changes)
4. Write the code following the conventions above
5. Tell the owner what you did and what's next

You're allowed to suggest improvements to the spec or to find inconsistencies. Flag them clearly so the owner can decide.

## Quick reference

| Item | Value |
|---|---|
| Owner | Sven Manderscheid (Ekki), Luxembourg |
| Spec version | 1.5 (2026-05-04) |
| Current sprint | Sprint 1 (auth + DB + city view) |
| Current code progress | 0% — pure placeholder |
| World speed factor (default) | 1.0 |
| Castle max level | 30 |
| Map size | 1024 × 1024 tiles |
| Sectors per world | 8 |
| Max players per world | 5,000 |
| F2P endgame target | ~12 months Castle L30 + T5 troops |
| Production URL | https://conquer.svenmanderscheid.lu/ |
| GitHub | https://github.com/svenmanderscheid/conquer |
| Local dev | http://localhost/conquer/ (XAMPP) |
