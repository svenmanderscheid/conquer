<?php
declare(strict_types=1);
namespace Conquer\Admin;

use Conquer\Db\Connection;
use Conquer\Game\World\WorldSettings;

final class AlphaWaitlistAdmin
{
    /** Called inside AdminService's authenticated, audited transaction. */
    public static function update(Connection $db, array $input): array
    {
        $id = WorldSettings::integer($input['entry_id'] ?? null, 1, PHP_INT_MAX, 'Eintrags-ID');
        $status = $input['status'] ?? null;
        if (!is_string($status) || !in_array($status, ['waiting', 'invited', 'delete'], true)) {
            throw new \InvalidArgumentException('Ungültiger Wartelistenstatus.');
        }
        $before = $db->query('SELECT id,invited_at FROM alpha_waitlist WHERE id=? FOR UPDATE', [$id])->fetch();
        if (!$before) throw new \InvalidArgumentException('Wartelisteneintrag nicht gefunden.');
        if ($status === 'delete') {
            $db->execute('DELETE FROM alpha_waitlist WHERE id=?', [$id]);
        } else {
            $db->execute('UPDATE alpha_waitlist SET invited_at=' . ($status === 'invited' ? 'COALESCE(invited_at,UTC_TIMESTAMP())' : 'NULL') . ' WHERE id=?', [$id]);
        }
        // Audit and receipts retain the ID/status only, never names or addresses.
        return ['target_type' => 'alpha_waitlist', 'target_id' => $id, 'before' => $before,
            'after' => ['status' => $status], 'message' => $status === 'delete' ? 'Wartelisteneintrag wurde gelöscht.' : 'Wartelistenstatus wurde gespeichert.'];
    }

    public static function export(Connection $db): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="conquer-alpha-warteliste.csv"');
        header('X-Content-Type-Options: nosniff');
        $stream = fopen('php://output', 'wb');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, ['Vorname', 'Nachname', 'E-Mail', 'Sprache', 'Eingetragen (UTC)', 'Einwilligung (UTC)', 'Einwilligungsversion', 'Eingeladen (UTC)'], ';', '"', '');
        $after = 0;
        // Small batches keep exports within the same memory budget as the list.
        do {
            $rows = $db->query('SELECT * FROM alpha_waitlist WHERE id>? ORDER BY id LIMIT 500', [$after])->fetchAll();
            foreach ($rows as $row) {
                $values = [$row['first_name'], $row['last_name'], $row['email'], $row['locale'], $row['created_at'], $row['consent_at'], $row['consent_version'], $row['invited_at'] ?? ''];
                fputcsv($stream, array_map(self::csvCell(...), $values), ';', '"', '');
                $after = (int)$row['id'];
            }
        } while (count($rows) === 500);
        fclose($stream);
    }

    public static function csvCell(string $value): string
    {
        // Spreadsheet formula injection, including a formula after whitespace.
        return preg_match('/^[\s\x{FEFF}]*[=+@\-]/u', $value) ? "'" . $value : $value;
    }
}
