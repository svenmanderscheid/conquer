<?php
declare(strict_types=1);

namespace Conquer\Game\City;

use Conquer\Game\World\WorldContext;

use Conquer\Db\Connection;

/**
 * Handles troop training queue insertion and lazy queue processing.
 *
 * train()       — validate + enqueue a training batch
 * processQueue() — credit finished training batches (called on city load)
 */
final class TroopTrainer
{
    private function __construct() {}

    /**
     * Start training $count troops of $troopCode for the given city.
     *
     * Validates:
     *  - troop code is valid
     *  - troop is unlocked (own school + town center levels)
     *  - count >= 1
     *  - resources are sufficient
     *  - barrack slot is not already busy
     *
     * On success: deducts resources, inserts troop_queue row.
     * On failure: throws \RuntimeException with a user-visible message.
     *
     * @param array<string, mixed>              $city      City row from DB
     * @param array<string, array{level: int}>  $buildings Current building levels
     */
    public static function train(
        array  $city,
        array  $buildings,
        int    $troopCode,
        int    $count,
        ?int   $barrackSlot = null,
    ): void {
        WorldContext::assertActionAvailable();
        $owned=WorldContext::city((int)($city['player_id']??Connection::getInstance()->query('SELECT player_id FROM cities WHERE id=?',[$city['id']])->fetchColumn()));
        if((int)$owned['id']!==(int)$city['id'])throw new \DomainException('Diese Stadt gehört nicht zur aktiven Welt.',403);
        if ($count < 1 || $count > 50000) {
            throw new \RuntimeException('Count must be at least 1.');
        }

        $troop = TroopData::get($troopCode);
        if ($troop === null) {
            throw new \RuntimeException('Unknown troop code.');
        }

        // Derive the queue from the troop; a client cannot borrow another building.
        $slot=TroopData::slotFor($troopCode);
        if($barrackSlot!==null && $barrackSlot!==$slot)throw new \DomainException('Der Ausbildungsplatz passt nicht zur Truppenart.');
        $barrackSlot=$slot;
        $building=TroopData::buildingFor($troopCode);

        $barrackLevel = (int) ($buildings[$building]['level'] ?? 0);
        $castleLevel = (int) ($buildings['castle']['level'] ?? 0);

        if (!TroopData::isUnlocked($troopCode, $barrackLevel, $castleLevel)) {
            throw new \DomainException('Benötigt '.CityState::BUILDING_NAMES[$building].' Stufe '.$troop['unlock_building'].' und Stadtzentrum Stufe '.$troop['unlock_castle'].'.');
        }

        $db = Connection::getInstance();
        $ownerId = (int)$db->query('SELECT player_id FROM cities WHERE id=?',[(int)$city['id']])->fetchColumn();
        $trainingBuffs=\Conquer\Game\Research\BuffEngine::getBuffs($ownerId);
        $boost=\Conquer\Game\Buff\ActiveBuffService::getMultiplier($ownerId,'training_boost');
        $training=\Conquer\Game\Research\ResearchEffects::training($troopCode,$trainingBuffs,$boost);
        if ($count > $training['max_count']) { throw new \RuntimeException('TRAINING_LIMIT: Maximal '.$training['max_count'].' Truppen je Ausbildungsauftrag.'); }
        $cost = array_map(static fn($amount)=>(int)ceil($amount*$count),$training['cost']);

        if ($city['food']   < $cost['food'] ||
            $city['lumber'] < $cost['lumber'] ||
            $city['stone']  < $cost['stone'] ||
            $city['gold']   < $cost['gold']) {
            throw new \RuntimeException('Not enough resources to train ' . $count . ' ' . $troop['name'] . '.');
        }

        $db     = Connection::getInstance();
        $cityId = (int) $city['id'];
        ResourceTick::persist($city, $buildings);

        // Check that the requested barrack slot is free.
        $busy = $db->query(
            'SELECT id FROM troop_queue
             WHERE city_id = ? AND barrack_slot = ? AND is_processed = 0
             LIMIT 1',
            [$cityId, $barrackSlot],
        )->fetch();

        if ($busy !== false || \Conquer\Game\Defense\DefenseService::hasPromotion($cityId,$barrackSlot)) {
            throw new \RuntimeException('Barrack slot ' . $barrackSlot . ' is already training troops.');
        }

        $durationSec = TroopData::trainingSeconds($troopCode, $count);
        $durationSec=max(1,(int)ceil($durationSec / ($training['speed_multiplier']*(1+BuildingPlotService::trainingBonus($cityId)))));

        $enqueue=function () use ($db, $cityId, $troopCode, $count, $barrackSlot, $durationSec, $cost): void {
            $db->query('SELECT id FROM cities WHERE id=? FOR UPDATE',[$cityId])->fetchColumn();
            if($db->query('SELECT id FROM troop_queue WHERE city_id=? AND barrack_slot=? AND is_processed=0 LIMIT 1 FOR UPDATE',[$cityId,$barrackSlot])->fetchColumn()!==false || \Conquer\Game\Defense\DefenseService::hasPromotion($cityId,$barrackSlot))throw new \DomainException('Dieses Ausbildungsgebäude ist bereits beschäftigt.');
            // Deduct resources.
            $debited=$db->execute(
                'UPDATE cities SET
                    food   = food   - :food,
                    lumber = lumber - :lumber,
                    stone  = stone  - :stone,
                    gold   = gold   - :gold
                 WHERE id = :id AND food>=:min_food AND lumber>=:min_lumber AND stone>=:min_stone AND gold>=:min_gold',
                [
                    ':food'   => $cost['food'],
                    ':lumber' => $cost['lumber'],
                    ':stone'  => $cost['stone'],
                    ':gold'   => $cost['gold'],
                    ':id'     => $cityId,
                    ':min_food'=>$cost['food'], ':min_lumber'=>$cost['lumber'], ':min_stone'=>$cost['stone'], ':min_gold'=>$cost['gold'],
                ],
            );
            if($debited!==1)throw new \RuntimeException('Deine Ressourcen haben sich verändert. Für die Ausbildung fehlen jetzt Rohstoffe.');

            // Enqueue training batch.
            $db->execute(
                'INSERT INTO troop_queue
                    (city_id, troop_code, count, barrack_slot, started_at, finishes_at, cost_json)
                 VALUES
                    (:city_id, :code, :count, :slot,
                     UTC_TIMESTAMP(),
                     DATE_ADD(UTC_TIMESTAMP(), INTERVAL :dur SECOND), :cost)',
                [
                    ':city_id' => $cityId,
                    ':code'    => $troopCode,
                    ':count'   => $count,
                    ':slot'    => $barrackSlot,
                    ':dur'     => $durationSec,
                    ':cost'    => json_encode($cost,JSON_THROW_ON_ERROR),
                ],
            );
        };
        if($db->getPdo()->inTransaction())$enqueue();else $db->transaction($enqueue);
    }

    /** Cancel a running batch under the same city lock used to enqueue it. */
    public static function cancel(int $playerId,int $queueId): array
    {
        $db=Connection::getInstance();$city=WorldContext::city($playerId);$cityId=(int)$city['id'];
        WorldContext::assertActionAvailable();
        return $db->transaction(function()use($db,$cityId,$queueId):array{
            $db->query('SELECT id FROM cities WHERE id=? FOR UPDATE',[$cityId])->fetchColumn();
            $entry=$db->query('SELECT *,TIMESTAMPDIFF(SECOND,UTC_TIMESTAMP(),finishes_at) AS remaining_seconds,TIMESTAMPDIFF(SECOND,started_at,finishes_at) AS total_seconds FROM troop_queue WHERE id=? AND city_id=? AND is_processed=0 AND finishes_at>UTC_TIMESTAMP() FOR UPDATE',[$queueId,$cityId])->fetch();
            if(!$entry)throw new \DomainException('Dieser Ausbildungsauftrag ist beendet oder gehört nicht zu deiner Stadt.',404);
            $count=(int)$entry['count'];$remaining=min($count,(int)ceil($count*(int)$entry['remaining_seconds']/max(1,(int)$entry['total_seconds'])));
            $paid=json_decode($entry['cost_json']??'null',true);
            if(!is_array($paid))$paid=TroopData::trainingCost((int)$entry['troop_code'],$count);
            $refund=[];foreach(['food','lumber','stone','gold']as$r)$refund[$r]=min((int)$paid[$r],(int)round((int)$paid[$r]*$remaining/$count));
            $db->execute('DELETE FROM troop_queue WHERE id=?',[$queueId]);
            $db->execute('UPDATE cities SET food=food+?,lumber=lumber+?,stone=stone+?,gold=gold+? WHERE id=?',[$refund['food'],$refund['lumber'],$refund['stone'],$refund['gold'],$cityId]);
            return ['cancelled'=>true,'remaining_count'=>$remaining,'refunded_food'=>$refund['food'],'refunded_lumber'=>$refund['lumber'],'refunded_stone'=>$refund['stone'],'refunded_gold'=>$refund['gold']];
        });
    }

    /**
     * Credits completed training batches to city_troops.
     * Called lazily on every city load (same pattern as building upgrades).
     *
     * @param array<string, mixed> $city  passed by reference — troop counts updated in memory
     */
    public static function processQueue(Connection $db, int $cityId): void
    {
        try {
            $finished = $db->query(
                'SELECT id, troop_code, count
                 FROM   troop_queue
                 WHERE  city_id = ? AND is_processed = 0 AND finishes_at <= UTC_TIMESTAMP()',
                [$cityId],
            )->fetchAll();
        } catch (\PDOException) {
            return; // table may not exist yet
        }

        foreach ($finished as $entry) {
            $db->transaction(static function (Connection $db) use ($entry, $cityId): void {
            if ($db->execute('UPDATE troop_queue SET is_processed = 1 WHERE id = ? AND is_processed = 0', [(int) $entry['id']]) !== 1) { return; }
            $code  = (int) $entry['troop_code'];
            $count = (int) $entry['count'];

            $db->execute(
                'INSERT INTO city_troops (city_id, troop_code, count)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE count = count + ?',
                [$cityId, $code, $count, $count],
            );

            $db->execute(
                'UPDATE troop_queue SET is_processed = 1 WHERE id = ?',
                [(int) $entry['id']],
            );
            });
        }

        // Update city power to include new troops.
        if (!empty($finished)) {
            self::updateTroopPower($db, $cityId);
        }
    }

    /**
     * Adds troop power contribution to the city's power column.
     * Called after training completes.
     */
    private static function updateTroopPower(Connection $db, int $cityId): void
    {
        $rows = $db->query(
            'SELECT troop_code, count FROM city_troops WHERE city_id = ?',
            [$cityId],
        )->fetchAll();

        $troopPower = 0;
        foreach ($rows as $row) {
            $troop = TroopData::get((int) $row['troop_code']);
            if ($troop !== null) {
                $troopPower += (int) $troop['power'] * (int) $row['count'];
            }
        }

        // city power = building power + troop power
        // We don't recalculate building power here to avoid loading all buildings;
        // instead we add the delta. A full recalc happens on the next city load.
        // For now: just persist total troop power separately via a no-op update
        // (building power is recalculated each load anyway — this is fine).
        // Nothing to do here — CityState::loadForPlayer recalculates power on every load.
    }
}
