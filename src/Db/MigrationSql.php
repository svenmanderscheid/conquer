<?php
declare(strict_types=1);
namespace Conquer\Db;

/** Ordered SQL migrations with resumable ADD/DROP portability for MySQL and MariaDB. */
final class MigrationSql
{
    public static function apply(\PDO $pdo, string $sql): void
    {
        // A file may CREATE a table before reconciling its older versions.
        foreach (self::split($sql, ';') as $statement) {
            if (!preg_match('/^ALTER\s+TABLE\s+`?([a-zA-Z0-9_]+)`?\s+(.+)$/is', $statement, $alter)
                || !preg_match('/\bIF\s+(?:NOT\s+)?EXISTS\b/i', $alter[2])) {
                // PREPARE/EXECUTE branches may produce a SELECT result set.
                $result = $pdo->query($statement);
                $result->closeCursor();
                continue;
            }
            $table = '`'.$alter[1].'`';
            // Ignore commas in ENUMs, decimals, index expressions and strings.
            foreach (self::split($alter[2], ',') as $clause) {
                if (preg_match('/^ADD\s+COLUMN\s+IF\s+NOT\s+EXISTS\s+`?([a-zA-Z0-9_]+)`?\s+(.+)$/is', $clause, $m)) {
                    $columns = $pdo->query('SHOW COLUMNS FROM '.$table)->fetchAll(\PDO::FETCH_COLUMN);
                    if (in_array($m[1], $columns, true)) continue;
                    $clause = 'ADD COLUMN `'.$m[1].'` '.$m[2];
                } elseif (preg_match('/^ADD\s+((?:UNIQUE\s+)?(?:KEY|INDEX))\s+IF\s+NOT\s+EXISTS\s+`?([a-zA-Z0-9_]+)`?\s+(.+)$/is', $clause, $m)) {
                    $indexes = $pdo->query('SHOW INDEX FROM '.$table)->fetchAll(\PDO::FETCH_ASSOC);
                    if (in_array($m[2], array_column($indexes, 'Key_name'), true)) continue;
                    $clause = 'ADD '.$m[1].' `'.$m[2].'` '.$m[3];
                } elseif (preg_match('/^DROP\s+INDEX\s+IF\s+EXISTS\s+`?([a-zA-Z0-9_]+)`?$/i', $clause, $m)) {
                    $indexes = $pdo->query('SHOW INDEX FROM '.$table)->fetchAll(\PDO::FETCH_ASSOC);
                    if (!in_array($m[1], array_column($indexes, 'Key_name'), true)) continue;
                    $clause = 'DROP INDEX `'.$m[1].'`';
                }
                $pdo->exec('ALTER TABLE '.$table.' '.$clause);
            }
        }
    }

    /** Repository SQL has no stored routines/DELIMITER. Respect strings and comments. */
    private static function split(string $sql, string $delimiter): array
    {
        $parts = []; $part = ''; $quote = null; $depth = 0; $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $c = $sql[$i]; $next = $sql[$i + 1] ?? '';
            if ($quote !== null) {
                $part .= $c;
                if ($c === '\\' && $next !== '') { $part .= $next; $i++; }
                elseif ($c === $quote) {
                    if ($next === $quote) { $part .= $next; $i++; }
                    else $quote = null;
                }
                continue;
            }
            if ($c === "'" || $c === '"' || $c === '`') { $quote = $c; $part .= $c; continue; }
            if ($c === '#' || ($c === '-' && $next === '-' && ctype_space($sql[$i + 2] ?? ' '))) {
                while ($i < $length && $sql[$i] !== "\n") $i++;
                $part .= "\n"; continue;
            }
            if ($c === '/' && $next === '*') {
                if (($sql[$i + 2] ?? '') === '!') throw new \RuntimeException('Executable SQL comments are not supported by the migration runner.');
                $end = strpos($sql, '*/', $i + 2);
                if ($end === false) throw new \RuntimeException('Unclosed SQL comment.');
                $i = $end + 1; $part .= ' '; continue;
            }
            if ($c === '(') $depth++;
            if ($c === ')') $depth--;
            if ($c === $delimiter && ($delimiter === ';' || $depth === 0)) {
                if (trim($part) !== '') $parts[] = trim($part);
                $part = ''; continue;
            }
            $part .= $c;
        }
        if ($quote !== null || $depth !== 0) throw new \RuntimeException('Unclosed SQL string or expression.');
        if (trim($part) !== '') $parts[] = trim($part);
        return $parts;
    }
}
