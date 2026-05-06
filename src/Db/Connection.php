<?php
declare(strict_types=1);

namespace Conquer\Db;

/**
 * PDO database connection — singleton.
 *
 * Usage:
 *   // Once during Bootstrap:
 *   Connection::init(ROOT_DIR);
 *
 *   // Everywhere else:
 *   $db = Connection::getInstance();
 *   $rows = $db->query('SELECT * FROM players WHERE id = ?', [$id])->fetchAll();
 */
final class Connection
{
    private static ?self $instance = null;

    private readonly \PDO $pdo;

    private function __construct(string $rootDir)
    {
        $configFile = $rootDir . '/config/database.php';

        if (!is_file($configFile)) {
            throw new \RuntimeException(
                'Database config not found. Copy config/database.example.php to config/database.php.'
            );
        }

        /** @var array<string, mixed> $cfg */
        $cfg = require $configFile;

        $dsn = sprintf(
            '%s:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['driver']   ?? 'mysql',
            $cfg['host']     ?? '127.0.0.1',
            $cfg['port']     ?? 3306,
            $cfg['database'] ?? '',
            $cfg['charset']  ?? 'utf8mb4',
        );

        $options = array_replace([
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
            \PDO::ATTR_STRINGIFY_FETCHES  => false,
        ], $cfg['options'] ?? []);

        $this->pdo = new \PDO($dsn, $cfg['username'] ?? '', $cfg['password'] ?? '', $options);
    }

    // -------------------------------------------------------------------------
    // Lifecycle
    // -------------------------------------------------------------------------

    public static function init(string $rootDir): self
    {
        self::$instance = new self($rootDir);
        return self::$instance;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException(
                'DB Connection not initialized — call Connection::init() first.'
            );
        }
        return self::$instance;
    }

    // -------------------------------------------------------------------------
    // Query helpers
    // -------------------------------------------------------------------------

    /**
     * Run a SELECT (or any statement that returns rows).
     *
     * @param list<mixed>|array<string, mixed> $params
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Run an INSERT / UPDATE / DELETE.
     * Returns the number of affected rows.
     *
     * @param list<mixed>|array<string, mixed> $params
     */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Last auto-increment ID after an INSERT.
     */
    public function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    // -------------------------------------------------------------------------
    // Transactions
    // -------------------------------------------------------------------------

    /**
     * Wrap a callable in a transaction. Commits on success, rolls back on any
     * Throwable and re-throws.
     *
     * Example:
     *   $db->transaction(function (Connection $db) {
     *       $db->execute('INSERT INTO ...', [...]);
     *       $db->execute('UPDATE ...', [...]);
     *   });
     *
     * @template T
     * @param callable(self): T $fn
     * @return T
     */
    public function transaction(callable $fn): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $fn($this);
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
