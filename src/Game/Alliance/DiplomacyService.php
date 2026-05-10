<?php
declare(strict_types=1);

namespace Conquer\Game\Alliance;

use Conquer\Db\Connection;

/**
 * Manages alliance diplomatic relations (ally / nap / war).
 *
 * Relations are stored in the `alliance_diplomacy` table.
 * Each row represents a directed relation: alliance_id → target_id.
 * The symmetric direction must be set separately if desired.
 */
final class DiplomacyService
{
    private function __construct() {}

    /**
     * Returns the current relation between two alliances, or null when none is set.
     *
     * @return 'ally'|'nap'|'war'|null
     */
    public static function getRelation(int $allianceId, int $targetId): ?string
    {
        $db = Connection::getInstance();

        $row = $db->query(
            'SELECT relation FROM alliance_diplomacy
             WHERE  alliance_id = ? AND target_id = ?',
            [$allianceId, $targetId],
        )->fetch();

        if ($row === false) {
            return null;
        }

        return (string) $row['relation'];
    }

    /**
     * Sets (inserts or updates) a diplomatic relation.
     *
     * @param 'ally'|'nap'|'war' $relation
     *
     * @throws \InvalidArgumentException when $relation is not a valid value
     * @throws \RuntimeException         when alliances cannot set a relation with themselves
     */
    public static function setRelation(
        int    $allianceId,
        int    $targetId,
        string $relation,
        int    $initiatorId,
    ): void {
        if (!in_array($relation, ['ally', 'nap', 'war'], true)) {
            throw new \InvalidArgumentException('Ungültige Relation: ' . $relation . '. Erlaubt: ally, nap, war.');
        }

        if ($allianceId === $targetId) {
            throw new \RuntimeException('Eine Allianz kann keine Diplomatie mit sich selbst betreiben.');
        }

        $db = Connection::getInstance();

        // Verify target alliance exists
        $target = $db->query(
            'SELECT id FROM alliances WHERE id = ?',
            [$targetId],
        )->fetch();

        if ($target === false) {
            throw new \RuntimeException('Ziel-Allianz nicht gefunden.');
        }

        $db->execute(
            'INSERT INTO alliance_diplomacy (alliance_id, target_id, relation, initiated_by)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE relation = VALUES(relation), initiated_by = VALUES(initiated_by)',
            [$allianceId, $targetId, $relation, $initiatorId],
        );
    }

    /**
     * Removes a diplomatic relation between two alliances.
     * No-op when the relation does not exist.
     */
    public static function removeRelation(int $allianceId, int $targetId): void
    {
        $db = Connection::getInstance();

        $db->execute(
            'DELETE FROM alliance_diplomacy WHERE alliance_id = ? AND target_id = ?',
            [$allianceId, $targetId],
        );
    }

    /**
     * Returns all diplomatic relations for the given alliance,
     * joined with the target alliance name and tag.
     *
     * @return list<array{id: int, target_id: int, target_name: string, target_tag: string, relation: string, initiated_by: int, created_at: string}>
     */
    public static function getDiplomacy(int $allianceId): array
    {
        $db = Connection::getInstance();

        return $db->query(
            'SELECT ad.id, ad.target_id, a.name AS target_name, a.tag AS target_tag,
                    ad.relation, ad.initiated_by, ad.created_at
             FROM   alliance_diplomacy ad
             JOIN   alliances a ON a.id = ad.target_id
             WHERE  ad.alliance_id = ?
             ORDER BY ad.relation ASC, a.name ASC',
            [$allianceId],
        )->fetchAll();
    }
}
