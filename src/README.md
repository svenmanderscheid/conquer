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
