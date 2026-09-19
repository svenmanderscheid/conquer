<?php
declare(strict_types=1);
namespace Conquer\Security;

use Conquer\Db\Connection;

/** Atomic token buckets shared by all PHP workers; never call inside a game transaction. */
final class RateLimit
{
    /** Returns seconds until the next request is allowed, or zero. */
    public static function consume(string $scope, string $identity, int $capacity, int $period): int
    {
        if ($capacity < 1 || $period < 1) throw new \InvalidArgumentException('Invalid rate limit.');
        $db = Connection::getInstance();
        $key = hash('sha256', $scope . ':' . $identity);
        $result = $db->transaction(static function () use ($db, $key, $capacity, $period): array {
            // An upsert takes an exclusive row lock immediately. INSERT IGNORE would
            // take shared duplicate-key locks that can deadlock when upgraded below.
            $db->execute('INSERT INTO security_rate_limits(bucket_key,tokens,updated_at,expires_at) VALUES(?,?,0,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY)) ON DUPLICATE KEY UPDATE bucket_key=VALUES(bucket_key)', [$key, $capacity]);
            $row = $db->query('SELECT * FROM security_rate_limits WHERE bucket_key=? FOR UPDATE', [$key])->fetch();
            // Read the database clock after acquiring the lock, not the PHP worker clock.
            $now = (float) $db->query('SELECT UNIX_TIMESTAMP(UTC_TIMESTAMP(6))')->fetchColumn();
            $tokens = min((float)$capacity, (float)$row['tokens'] + max(0, $now - (float)$row['updated_at']) * $capacity / $period);
            $retry = $tokens >= 1 ? 0 : max(1, (int)ceil((1 - $tokens) * $period / $capacity));
            $report = $retry > 0 && $now - (float)$row['reported_at'] >= 300;
            $db->execute('UPDATE security_rate_limits SET tokens=?,updated_at=?,reported_at=?,denied_count=denied_count+?,expires_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY) WHERE bucket_key=?',
                [$retry ? $tokens : $tokens - 1, $now, $report ? $now : $row['reported_at'], $retry ? 1 : 0, $key]);
            return [$retry, $report];
        });
        if ($result[1]) {
            \Conquer\Logger::getInstance()->warn('SECURITY rate_limit scope=' . $scope . ' subject=' . $key);
        }
        // Bounded maintenance, with indexed expiry; no unbounded per-request scan.
        if (random_int(1, 100) === 1) $db->execute('DELETE FROM security_rate_limits WHERE expires_at<UTC_TIMESTAMP() LIMIT 100');
        return $result[0];
    }

    public static function ip(): string
    {
        // Forwarded headers are client-controlled unless a trusted proxy normalizes REMOTE_ADDR.
        return (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }
}
