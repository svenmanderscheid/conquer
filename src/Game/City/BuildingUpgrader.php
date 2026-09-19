<?php
declare(strict_types=1);

namespace Conquer\Game\City;

use Conquer\Game\World\WorldContext;

use Conquer\Db\Connection;

/**
 * Handles starting a building upgrade.
 *
 * Validation order:
 *   1. Building code must be valid
 *   2. Building must not already be at max level (30)
 *   3. No other upgrade in progress (1 slot; 2 at VIP 4+)
 *   4. Non-castle buildings cannot exceed castle level
 *   5. Resources must be sufficient (after applying production tick)
 *
 * On success: resources are deducted and a building_queue row is inserted.
 */
final class BuildingUpgrader
{
    private const MAX_LEVEL = 30;

    private function __construct() {}

    /**
     * Start a building upgrade for the given city.
     *
     * @param  int                               $cityId
     * @param  string                            $code        Building code to upgrade
     * @param  array<string, mixed>              $city        City row from DB
     * @param  array<string, array{level: int}>  $buildings   Current buildings map
     * @param  int                               $vipLevel    Player VIP level
     * @param  array<string, int>                $vipBonuses  VIP bonus array from VipService::bonuses()
     * @return array<string, mixed>  The new building_queue row
     * @throws \RuntimeException  On any validation failure (message is user-safe)
     */
    public static function start(
        int    $cityId,
        string $code,
        array  $city,
        array  $buildings,
        int    $vipLevel   = 0,
        array  $vipBonuses = [],
    ): array {
        WorldContext::assertActionAvailable();
        $owned=WorldContext::city((int)($city['player_id']??Connection::getInstance()->query('SELECT player_id FROM cities WHERE id=?',[$cityId])->fetchColumn()));
        if((int)$owned['id']!==$cityId)throw new \DomainException('Diese Stadt gehört nicht zur aktiven Welt.',403);
        // 1. Valid building code?
        if (!in_array($code, CityState::BUILDING_CODES, true)) {
            throw new \RuntimeException('Unknown building: ' . $code);
        }

        $currentLevel = (int) ($buildings[$code]['level'] ?? 1);
        $toLevel      = $currentLevel + 1;

        // 2. Already at max?
        if ($currentLevel >= self::MAX_LEVEL) {
            throw new \RuntimeException(
                CityState::BUILDING_NAMES[$code] . ' is already at maximum level.',
            );
        }

        $db = Connection::getInstance();

        // 3. Queue slot available?
        $maxSlots = $vipLevel >= 4 ? 2 : 1;
        $activeCount = (int) $db->query(
            'SELECT COUNT(*) FROM building_queue WHERE city_id = ? AND is_processed = 0',
            [$cityId],
        )->fetchColumn();

        if ($activeCount + BuildingPlotService::busy($cityId) >= $maxSlots) {
            $msg = $maxSlots === 1
                ? 'A building is already being upgraded. Reach VIP 4 to unlock a second slot.'
                : 'Both building queue slots are occupied.';
            throw new \RuntimeException($msg);
        }

        // Also check the specific building isn't already queued.
        $alreadyQueued = $db->query(
            'SELECT 1 FROM building_queue
             WHERE city_id = ? AND building_code = ? AND is_processed = 0',
            [$cityId, $code],
        )->fetch();

        if ($alreadyQueued !== false) {
            throw new \RuntimeException(
                CityState::BUILDING_NAMES[$code] . ' is already in the upgrade queue.',
            );
        }

        // Every prerequisite in the source table applies, including secondary buildings.
        self::assertRequirements($code, $toLevel, $buildings);

        // 5. Resources — persist accumulated production first, then check.
        ResourceTick::persist($city, $buildings);

        // Re-read fresh resource values from DB.
        $fresh = $db->query(
            'SELECT food, lumber, stone, gold FROM cities WHERE id = ?',
            [$cityId],
        )->fetch();

        $cost = BuildingData::getCost($code, $toLevel);

        foreach (['lumber', 'stone', 'gold', 'food'] as $res) {
            if ((int) $fresh[$res] < $cost[$res]) {
                throw new \RuntimeException(
                    'Not enough ' . $res . '. Need '
                        . number_format($cost[$res]) . ', have '
                        . number_format((int) $fresh[$res]) . '.',
                );
            }
        }

        // All checks passed — deduct resources and insert queue entry.
        $buildTime  = BuildingData::getBuildTime($code, $toLevel, $vipBonuses);
        $startedAt  = gmdate('Y-m-d H:i:s');
        $finishesAt = gmdate('Y-m-d H:i:s', time() + $buildTime);

        $db->transaction(static function (Connection $db) use (
            $cityId, $code, $toLevel, $currentLevel, $cost, $startedAt, $finishesAt, $maxSlots,
        ): void {
            $playerId = (int)$db->query('SELECT player_id FROM cities WHERE id=? FOR UPDATE',[$cityId])->fetchColumn();
            $current = $db->query('SELECT building_code,level FROM city_buildings WHERE city_id=? FOR UPDATE',[$cityId])->fetchAll(\PDO::FETCH_KEY_PAIR);
            if ((int)($current[$code] ?? 0) !== $currentLevel) throw new \RuntimeException('Die Gebäudestufe hat sich verändert. Bitte lade den aktuellen Stand.');
            self::assertRequirements($code, $toLevel, array_map(static fn($level)=>['level'=>(int)$level], $current));
            if ($db->query('SELECT 1 FROM building_queue WHERE city_id=? AND building_code=? AND is_processed=0',[$cityId,$code])->fetchColumn()) throw new \RuntimeException('Dieses Gebäude wird bereits ausgebaut.');
            $items = BuildingData::getItemCosts($code, $toLevel);
            foreach ($items as $itemCode=>$quantity) {
                if (!\Conquer\Game\Inventory\InventoryService::removeItems($playerId,(int)$itemCode,$quantity)) {
                    $def = \Conquer\Game\Inventory\InventoryService::getItemDef((int)$itemCode);
                    throw new \RuntimeException('Für diesen Ausbau fehlen '.$quantity.' × '.($def['name_de'] ?? $def['name']).'.');
                }
            }
            $active=(int)$db->query('SELECT COUNT(*) FROM building_queue WHERE city_id=? AND is_processed=0',[$cityId])->fetchColumn();
            if($active+BuildingPlotService::busy($cityId)>=$maxSlots)throw new \RuntimeException('Deine Baumeister sind beschäftigt.');
            // Deduct resources.
            $debited=$db->execute(
                'UPDATE cities
                 SET lumber = lumber - ?,
                     stone  = stone  - ?,
                     gold   = gold   - ?,
                     food   = food   - ?
                 WHERE id = ? AND lumber>=? AND stone>=? AND gold>=? AND food>=?',
                [$cost['lumber'], $cost['stone'], $cost['gold'], $cost['food'], $cityId,$cost['lumber'],$cost['stone'],$cost['gold'],$cost['food']],
            );
            if($debited!==1)throw new \RuntimeException('Deine Ressourcen haben sich verändert. Für diesen Ausbau fehlen jetzt Rohstoffe.');

            // Enqueue upgrade.
            $db->execute(
                'INSERT INTO building_queue
                     (city_id, building_code, level_to, started_at, finishes_at, cost_json)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$cityId, $code, $toLevel, $startedAt, $finishesAt, json_encode(['resources'=>$cost, 'items'=>$items], JSON_THROW_ON_ERROR)],
            );
        });

        return [
            'building_code' => $code,
            'level_to'      => $toLevel,
            'started_at'    => $startedAt,
            'finishes_at'   => $finishesAt,
        ];
    }
    public static function assertRequirements(string $code, int $toLevel, array $buildings): void
    {
        $row = BuildingData::level($code, $toLevel);
        if (!$row || !$row['valid']) throw new \RuntimeException('Diese Ausbaustufe ist nicht verfügbar.');
        foreach (BuildingData::getUpgradeRequirements($code, $toLevel) as $required=>$level) {
            $actual = (int)($buildings[$required]['level'] ?? 0);
            if ($actual < $level) throw new \RuntimeException((CityState::BUILDING_NAMES[$code] ?? $code).' benötigt '.(CityState::BUILDING_NAMES[$required] ?? $required).' Stufe '.$level.' (aktuell '.$actual.').');
        }
    }

    /** Serialize against city settlement and refund the actual paid snapshot exactly once. */
    public static function cancel(int $playerId, int $queueId): array
    {
        WorldContext::assertActionAvailable();
        $db = Connection::getInstance();
        $lock = 'conquer-player-'.$playerId;
        if ((int)$db->query('SELECT GET_LOCK(?,5)',[$lock])->fetchColumn() !== 1) throw new \RuntimeException('Dein Königreich wird gerade aktualisiert.');
        try {
            return $db->transaction(static function(Connection $db) use($playerId,$queueId): array {
                $city = WorldContext::city($playerId, null, true);
                $entry = $db->query('SELECT * FROM building_queue WHERE id=? AND city_id=? AND is_processed=0 AND finishes_at>UTC_TIMESTAMP() FOR UPDATE',[$queueId,$city['id']])->fetch();
                if (!$entry) throw new \RuntimeException('Der Bauauftrag wurde bereits abgeschlossen oder abgebrochen.');
                $paid = $entry['cost_json'] === null
                    ? ['resources'=>BuildingData::legacyCost($entry['building_code'],(int)$entry['level_to']), 'items'=>[]]
                    : json_decode($entry['cost_json'],true,32,JSON_THROW_ON_ERROR);
                $r = $paid['resources'];
                $db->execute('UPDATE cities SET food=food+?,lumber=lumber+?,stone=stone+?,gold=gold+? WHERE id=?',[$r['food'],$r['lumber'],$r['stone'],$r['gold'],$city['id']]);
                foreach ($paid['items'] as $code=>$quantity) \Conquer\Game\Inventory\InventoryService::addItems($playerId,(int)$code,(int)$quantity);
                $db->execute('DELETE FROM building_queue WHERE id=?',[$queueId]);
                return ['cancelled'=>true,'refunded_food'=>$r['food'],'refunded_lumber'=>$r['lumber'],'refunded_stone'=>$r['stone'],'refunded_gold'=>$r['gold'],'refunded_items'=>$paid['items']];
            });
        } finally { $db->query('SELECT RELEASE_LOCK(?)',[$lock]); }
    }

}
