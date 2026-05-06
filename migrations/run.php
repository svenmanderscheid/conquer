<?php
declare(strict_types=1);

/**
 * Migration runner — Sprint 1 placeholder
 *
 * Sprint 1 deliverable: implement actual migration logic.
 *
 * Design intent:
 * - Migrations live in /migrations/ as YYYYMMDDHHMMSS_description.sql files
 * - A `migrations` table tracks which have been applied
 * - Running this script applies all pending migrations in order
 * - No rollback support in v1 (use git-driven schema diffs)
 */

echo "Migration runner — placeholder.\n";
echo "Sprint 1 will implement actual migration logic.\n";
echo "\n";
echo "Planned design:\n";
echo "  1. Connect to DB using config/database.php\n";
echo "  2. Ensure 'migrations' table exists\n";
echo "  3. Scan /migrations/ for .sql files\n";
echo "  4. Apply each that isn't in the migrations table\n";
echo "  5. Record applied migrations\n";
echo "\n";
echo "For now, this script does nothing.\n";

exit(0);
