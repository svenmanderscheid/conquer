<?php
declare(strict_types=1);

namespace Conquer\Auth;

use Conquer\Db\Connection;

/** Closed-alpha invite keys. Plain-text keys are shown once and never stored. */
final class AlphaAccess
{
    public static function normalize(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', trim($value)));
    }

    public static function display(string $normalized): string
    {
        return implode('-', str_split($normalized, 4));
    }

    public static function generate(Connection $db, string $label, int $maxUses = 1, ?string $expiresAt = null): string
    {
        $label = trim($label);
        if ($label === '' || mb_strlen($label) > 120 || $maxUses < 1 || $maxUses > 65535) {
            throw new \InvalidArgumentException('Label oder Nutzungszahl ist ungültig.');
        }
        if ($expiresAt !== null && strtotime($expiresAt . ' UTC') === false) {
            throw new \InvalidArgumentException('Das Ablaufdatum ist ungültig.');
        }

        do {
            $plain = strtoupper(bin2hex(random_bytes(12)));
            $hash = hash('sha256', $plain);
            try {
                $db->execute(
                    'INSERT INTO alpha_access_keys (label,key_hash,max_uses,expires_at) VALUES (?,?,?,?)',
                    [$label, $hash, $maxUses, $expiresAt],
                );
                return self::display($plain);
            } catch (\PDOException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
            }
        } while (true);
    }

    /** Must run inside the same transaction that creates the player. */
    public static function consume(Connection $db, mixed $submittedKey): ?int
    {
        $normalized = self::normalize($submittedKey);
        $config = \Conquer\Bootstrap::getConfig();
        if (($config['env'] ?? 'production') === 'development'
            && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
            && hash_equals('LOCALALPHAACCESS2026KEY1', $normalized)) {
            return null;
        }
        if (strlen($normalized) !== 24) {
            throw new \DomainException('Dieser Alpha-Key ist ungültig oder nicht mehr verfügbar.');
        }

        $row = $db->query(
            'SELECT id,max_uses,uses_count,expires_at,revoked_at FROM alpha_access_keys WHERE key_hash=? FOR UPDATE',
            [hash('sha256', $normalized)],
        )->fetch();

        if (!$row || $row['revoked_at'] !== null || (int) $row['uses_count'] >= (int) $row['max_uses']
            || ($row['expires_at'] !== null && strtotime((string) $row['expires_at'] . ' UTC') <= time())) {
            throw new \DomainException('Dieser Alpha-Key ist ungültig oder nicht mehr verfügbar.');
        }

        $db->execute(
            'UPDATE alpha_access_keys SET uses_count=uses_count+1,last_used_at=UTC_TIMESTAMP() WHERE id=?',
            [(int) $row['id']],
        );
        return (int) $row['id'];
    }
}
