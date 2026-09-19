<?php
declare(strict_types=1);

namespace Conquer\Game\Charm;

use Conquer\Db\Connection;

/** Creates the durable, one-per-field-monster kill receipt and its map charm. */
final class MonsterCharmLifecycle
{
    private function __construct() {}

    /**
     * Must run in the same transaction that removes the field monster and grants
     * its direct rewards. A false `created` result means another settlement owns
     * this monster kill and callers must not grant the direct rewards again.
     *
     * @return array{created:bool,receipt_id:int,charm_id:?int}
     */
    public static function settle(
        int $worldId,
        array $monster,
        array $definition,
        string $sourceKind,
        int $sourceId,
        int $winnerPlayerId,
        ?int $winnerAllianceId,
        array $rewardSnapshot,
    ): array {
        $db = Connection::getInstance();
        if (!$db->getPdo()->inTransaction()) {
            throw new \LogicException('Monster charm settlement requires an active transaction.');
        }
        if (!in_array($sourceKind, ['solo', 'rally'], true)) {
            throw new \InvalidArgumentException('Invalid monster settlement source.');
        }

        $monsterId = (int)$monster['id'];
        $monsterCode = (int)$monster['monster_code'];
        if(!\Conquer\Game\Map\MonsterData::isActive($monsterCode)) {
            throw new \DomainException('Dieses Monster ist derzeit nicht aktiv.',409);
        }
        $x = (int)$monster['coord_x'];
        $y = (int)$monster['coord_y'];
        $monsterLevel = max(1, (int)($monster['effective_monster_level'] ?? $definition['level'] ?? 1));
        $snapshot = $rewardSnapshot + [
            'monster_code' => $monsterCode,
            'spawn_code' => (int)($definition['spawn_code'] ?? $monsterCode),
            'monster_level' => $monsterLevel,
            'definition_code' => (int)($definition['code'] ?? $monsterCode),
        ];

        $changed = $db->execute(
            'INSERT INTO monster_kill_receipts
                (world_id,field_monster_id,source_kind,source_id,monster_code,monster_level,
                 coord_x,coord_y,winner_player_id,winner_alliance_id,reward_snapshot_json)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)',
            [$worldId,$monsterId,$sourceKind,$sourceId,$monsterCode,$monsterLevel,$x,$y,
             $winnerPlayerId,$winnerAllianceId,json_encode($snapshot, JSON_THROW_ON_ERROR)],
        );
        $receiptId = $db->lastInsertId();
        $created = $changed === 1;

        $receipt = $db->query(
            'SELECT id,field_monster_id,source_kind,source_id,charm_id
             FROM monster_kill_receipts WHERE world_id=? AND field_monster_id=? FOR UPDATE',
            [$worldId,$monsterId],
        )->fetch();
        if (!$receipt) {
            throw new \RuntimeException('Monster kill receipt could not be loaded.');
        }
        if ((string)$receipt['source_kind'] !== $sourceKind || (int)$receipt['source_id'] !== $sourceId) {
            return ['created'=>false,'receipt_id'=>(int)$receipt['id'],'charm_id'=>$receipt['charm_id'] === null ? null : (int)$receipt['charm_id']];
        }

        $charmId = $receipt['charm_id'] === null ? null : (int)$receipt['charm_id'];
        if ($created) {
            \Conquer\Game\Alliance\AllianceGiftService::createSourceGift($winnerPlayerId,$worldId,$definition);
            $charmId = CharmSpawner::spawn($worldId,$x,$y,$monsterCode,(int)$receipt['id'],$monsterLevel,$definition);
            if ($charmId !== null) {
                $db->execute('UPDATE monster_kill_receipts SET charm_id=? WHERE id=?',[$charmId,$receipt['id']]);
            }
            if (class_exists(\Conquer\Game\World\LandProgressService::class)) {
                \Conquer\Game\World\LandProgressService::recordMonsterKill(
                    $worldId,$monsterId,$x,$y,$monsterLevel,$winnerPlayerId,
                );
            }
        }

        return ['created'=>$created,'receipt_id'=>(int)$receipt['id'],'charm_id'=>$charmId];
    }
}
