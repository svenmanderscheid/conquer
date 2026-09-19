<?php
declare(strict_types=1);
namespace Conquer\Game\March;

use Conquer\Db\Connection;
use Conquer\Game\World\WorldContext;
use Conquer\Game\Rewards\RewardPresentation;

final class BattleReportService
{
    public const VISIBLE = "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(b.data_json, '$.hidden_by_attacker')), 'false') <> 'true'";
    private const SELECT = "SELECT b.*, m.state AS march_state, r.status AS rally_state
        FROM battle_reports b
        LEFT JOIN marches m ON m.id=b.march_id AND m.player_id=b.attacker_id AND m.world_id=b.world_id
        LEFT JOIN rallies r ON r.id=JSON_UNQUOTE(JSON_EXTRACT(b.data_json,'$.rally_id')) AND r.world_id=b.world_id";

    public static function list(int $playerId, int $page = 1, int $perPage = 20): array
    {
        $rows = Connection::getInstance()->query(self::SELECT.' WHERE b.attacker_id=? AND b.world_id=? AND '.self::VISIBLE.' ORDER BY b.created_at DESC,b.id DESC LIMIT ? OFFSET ?',
            [$playerId,WorldContext::id(),$perPage,(max(1,$page)-1)*$perPage])->fetchAll();
        return array_map(self::present(...),$rows);
    }

    public static function get(int $playerId, int $id): ?array
    {
        $row = Connection::getInstance()->query(self::SELECT.' WHERE b.id=? AND b.attacker_id=? AND b.world_id=? AND '.self::VISIBLE,
            [$id,$playerId,WorldContext::id()])->fetch();
        if (!$row) return null;
        Connection::getInstance()->execute('UPDATE battle_reports SET attacker_read=1 WHERE id=? AND attacker_id=? AND world_id=?',[$id,$playerId,WorldContext::id()]);
        $row['attacker_read'] = 1;
        return self::present($row);
    }

    /** Read-only presentation for a chat share after CommunityService authorized its audience. */
    public static function shared(int $id, int $worldId): ?array
    {
        $row = Connection::getInstance()->query(self::SELECT.' WHERE b.id=? AND b.world_id=?',[$id,$worldId])->fetch();
        if (!$row) return null;
        $report=self::present($row);
        $report['can_delete']=false;
        $report['can_share']=false;
        return $report;
    }

    /** Hide only this player's monster mail. Haul and battle records stay intact. */
    public static function delete(int $playerId, int $id): bool
    {
        $db = Connection::getInstance();
        if (!$db->query('SELECT id FROM battle_reports WHERE id=? AND attacker_id=? AND world_id=? AND target_type=3',[$id,$playerId,WorldContext::id()])->fetchColumn()) return false;
        $db->execute("UPDATE battle_reports SET data_json=JSON_SET(data_json,'$.hidden_by_attacker',JSON_EXTRACT('true','$')) WHERE id=? AND attacker_id=? AND world_id=? AND target_type=3",[$id,$playerId,WorldContext::id()]);
        return true;
    }

    private static function present(array $row): array
    {
        $row['details'] = json_decode($row['data_json'],true) ?: [];
        $state = ($row['details']['type'] ?? '') === 'monster_rally' ? $row['rally_state'] : $row['march_state'];
        $row['reward_delivery'] = in_array($state,['complete','completed'],true) ? 'delivered' : ($state === 'returning' ? 'returning' : 'unknown');
        $row['can_delete'] = (int)$row['target_type'] === 3;
        $row['details']=RewardPresentation::report($row['details']);
        unset($row['data_json'],$row['march_state'],$row['rally_state']);
        return $row;
    }
}
