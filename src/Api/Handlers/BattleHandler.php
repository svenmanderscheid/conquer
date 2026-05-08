<?php
declare(strict_types=1);

namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Db\Connection;

/**
 * Handles /api/battle/* endpoints.
 *
 * GET /api/battle/reports     — paginated list of battle reports
 * GET /api/battle/report/:id  — full detail of one report
 */
final class BattleHandler
{
    private function __construct() {}

    /**
     * GET /api/battle/reports?page=1
     */
    public static function reports(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $perPage  = 20;
        $offset   = ($page - 1) * $perPage;
        $playerId = (int) $session['player_id'];

        $db = Connection::getInstance();

        try {
            $rows = $db->query(
                'SELECT id, target_type, target_x, target_y, outcome,
                        attacker_read, created_at,
                        JSON_UNQUOTE(JSON_EXTRACT(data_json, "$.monster_name")) AS monster_name
                 FROM   battle_reports
                 WHERE  attacker_id = ?
                 ORDER  BY created_at DESC
                 LIMIT  ? OFFSET ?',
                [$playerId, $perPage, $offset],
            )->fetchAll();
        } catch (\PDOException) {
            $rows = [];
        }

        Response::ok(['reports' => $rows, 'page' => $page]);
    }

    /**
     * GET /api/battle/report/:id
     */
    public static function report(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $reportId = (int) ($params['id'] ?? 0);
        $playerId = (int) $session['player_id'];

        $db = Connection::getInstance();

        $row = $db->query(
            'SELECT * FROM battle_reports WHERE id = ? AND attacker_id = ?',
            [$reportId, $playerId],
        )->fetch();

        if ($row === false) {
            Response::error(404, 'NOT_FOUND', 'Kampfbericht nicht gefunden.');
        }

        // Mark as read.
        if (!(int) $row['attacker_read']) {
            $db->execute(
                'UPDATE battle_reports SET attacker_read = 1 WHERE id = ?',
                [$reportId],
            );
        }

        $row['data'] = json_decode($row['data_json'], true);
        unset($row['data_json']);

        Response::ok(['report' => $row]);
    }
}
