# src/

Serverseitiger PHP-Code. Architektur- und Prüfstand: [Alpha-Prüfung](../docs/ALPHA_ARCHITECTURE_2026-09-26.md).

Zentrale Bereiche:
- `Autoloader.php` — PSR-4-style autoloader
- `Bootstrap.php` — initializes config, DB, session, error handlers
- `Db/Connection.php` — PDO singleton
- `Auth/` — Session, Login, Register
- `Game/` — game logic (Buildings, Resources, Marches, etc.)
- `Api/` — API request handlers
- `Security/` — Ratenbegrenzung, CSRF und Vorgangsbelege
- `Admin/` — Backoffice, Rollen und Inhaltsverwaltung

Conventions:
- All files start with `declare(strict_types=1);`
- PSR-4-style namespaces: `Conquer\Subsystem\Class`
- Class file matches class name exactly (case-sensitive)
- One class per file

Building completion markers reuse the existing `notifications` table; no new migration is required. `GET /api/game/state` includes unread `building_completions` for the current player, city and world. Building, training, research and healing settlement records these notices alongside the automatically credited result. `POST /api/notifications/read` acknowledges captured notification IDs with the existing authentication and CSRF checks. Unread completion notices remain available across reloads and offline periods; already processed historical jobs are not backfilled. Verify the contract with `php tests/building_completions.php` using its disposable database.
