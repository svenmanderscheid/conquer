<?php
declare(strict_types=1);
namespace Conquer\Admin;

use Conquer\Auth\AlphaAccess;
use Conquer\Db\Connection;
use Conquer\Game\World\WorldSettings;

/** Called only inside AdminService's authenticated, audited operation transaction. */
final class AlphaKeyAdmin
{
    public static function create(Connection $db, array $input): array
    {
        $label = $input['label'] ?? null;
        if (!is_string($label) || trim($label) === '' || mb_strlen(trim($label)) > 120) {
            throw new \InvalidArgumentException('Bitte gib eine Bezeichnung mit höchstens 120 Zeichen ein.');
        }
        $label = trim($label);
        $quantity = WorldSettings::integer($input['quantity'] ?? 1, 1, 50, 'Anzahl Keys');
        $maxUses = WorldSettings::integer($input['max_uses'] ?? 1, 1, 65535, 'Registrierungen je Key');
        $rawExpiry = $input['expires_at'] ?? '';
        if (!is_string($rawExpiry)) throw new \InvalidArgumentException('Das Ablaufdatum ist ungültig.');
        $expiresAt = null;
        if ($rawExpiry !== '') {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $rawExpiry, new \DateTimeZone('UTC'));
            if (!$date || $date->format('Y-m-d\TH:i') !== $rawExpiry || $date->getTimestamp() <= time()) {
                throw new \InvalidArgumentException('Bitte wähle ein gültiges Ablaufdatum in der Zukunft (UTC).');
            }
            $expiresAt = $date->format('Y-m-d H:i:s');
        }
        $issued = [];
        foreach (range(1, $quantity) as $_) {
            $key = AlphaAccess::generate($db, $label, $maxUses, $expiresAt);
            $issued[] = ['id' => $db->lastInsertId(), 'key' => $key];
        }
        return [
            'target_type' => 'alpha_key', 'target_id' => $issued[0]['id'],
            'before' => null,
            'after' => ['ids' => array_column($issued, 'id'), 'label' => $label, 'max_uses' => $maxUses, 'expires_at' => $expiresAt],
            'issued_keys' => $issued,
            'message' => $quantity === 1 ? 'Alpha-Key wurde erstellt.' : $quantity . ' Alpha-Keys wurden erstellt.',
        ];
    }

    public static function revoke(Connection $db, array $input): array
    {
        $id = WorldSettings::integer($input['key_id'] ?? 0, 1, PHP_INT_MAX, 'Key-ID');
        // Registration takes the same row lock, so a revocation and consumption are serialized.
        $before = $db->query('SELECT id,label,max_uses,uses_count,expires_at,revoked_at FROM alpha_access_keys WHERE id=? FOR UPDATE', [$id])->fetch();
        if (!$before) throw new \InvalidArgumentException('Dieser Alpha-Key wurde nicht gefunden.');
        $after = $before;
        if ($before['revoked_at'] === null) {
            $after['revoked_at'] = gmdate('Y-m-d H:i:s');
            $db->execute('UPDATE alpha_access_keys SET revoked_at=? WHERE id=?', [$after['revoked_at'], $id]);
        }
        return [
            'target_type' => 'alpha_key', 'target_id' => $id, 'before' => $before, 'after' => $after,
            'message' => 'Alpha-Key ist gesperrt. Bereits registrierte Konten bleiben bestehen.',
        ];
    }

    public static function listing(Connection $db, array $query): array
    {
        $search = is_string($query['q'] ?? null) ? mb_substr(trim($query['q']), 0, 120) : '';
        $status = is_string($query['status'] ?? null) ? $query['status'] : '';
        if (!in_array($status, ['active', 'used', 'expired', 'revoked'], true)) $status = '';
        $state = "CASE WHEN revoked_at IS NOT NULL THEN 'revoked' WHEN expires_at IS NOT NULL AND expires_at<=UTC_TIMESTAMP() THEN 'expired' WHEN uses_count>=max_uses THEN 'used' ELSE 'active' END";
        $where = []; $params = [];
        if ($search !== '') {
            $where[] = '(LOCATE(?,label)>0 OR CAST(id AS CHAR)=?)';
            $params[] = $search; $params[] = $search;
        }
        if ($status !== '') { $where[] = "($state)=?"; $params[] = $status; }
        $filter = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $total = (int) $db->query('SELECT COUNT(*) FROM alpha_access_keys' . $filter, $params)->fetchColumn();
        $pages = max(1, (int) ceil($total / 20));
        $page = min($pages, max(1, (int) (is_scalar($query['page'] ?? null) ? $query['page'] : 1)));
        $rows = $db->query("SELECT id,label,max_uses,uses_count,expires_at,revoked_at,last_used_at,created_at,$state AS status FROM alpha_access_keys" . $filter . ' ORDER BY id DESC LIMIT 20 OFFSET ' . (($page - 1) * 20), $params)->fetchAll();
        return compact('rows', 'search', 'status', 'total', 'page', 'pages');
    }
}
