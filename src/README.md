# src/

PHP source code lives here. Currently empty (Sprint 0).

Sprint 1 will create:
- `Autoloader.php` — PSR-4-style autoloader
- `Bootstrap.php` — initializes config, DB, session, error handlers
- `Db/Connection.php` — PDO singleton
- `Auth/` — Session, Login, Register
- `Game/` — game logic (Buildings, Resources, Marches, etc.)
- `Api/` — API request handlers

Conventions:
- All files start with `declare(strict_types=1);`
- PSR-4-style namespaces: `Conquer\Subsystem\Class`
- Class file matches class name exactly (case-sensitive)
- One class per file
