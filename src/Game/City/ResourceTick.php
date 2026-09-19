<?php
declare(strict_types=1);

namespace Conquer\Game\City;

use Conquer\Game\World\WorldContext;

use Conquer\Db\Connection;

/**
 * Lazy resource production calculator (SPEC §3.4).
 *
 * Resources are NOT continuously updated in the DB.
 * They are stored as a snapshot + last_resource_update timestamp.
 * This class computes what the current values would be.
 *
 * On read:  call apply() to get computed values — no DB write.
 * On write: call persist() first to save the tick, then deduct resources.
 */
final class ResourceTick
{
    private function __construct() {}

    /**
     * Compute current resource values by applying production since last update.
     *
     * @param  array<string, mixed>              $city        Row from cities table
     * @param  array<string, array{level: int}>  $buildings   Current buildings
     * @param  float                             $speedFactor World speed multiplier
     * @param  array<string, int>                $vipBonuses  Optional VIP bonuses from VipService::bonuses()
     * @return array<string, mixed>              City array with updated resource values
     */
    public static function apply(
        array $city,
        array $buildings,
        float $speedFactor = 1.0,
        array $vipBonuses  = [],
    ): array {
        $elapsed = time() - strtotime($city['last_resource_update']);
        if ($elapsed <= 0) {
            return $city;
        }

        $caps = self::storageCaps($buildings,$vipBonuses);
        $plotGains = BuildingPlotService::production((int)$city['id'], time()-$elapsed, time(), $vipBonuses, $speedFactor);

        foreach (CityState::BUILDING_CODES as $code) {
            $resource = BuildingData::getProducedResource($code);
            if ($resource === null) {
                continue;
            }

            $level      = (int) ($buildings[$code]['level'] ?? 1);
            $hourlyRate = BuildingData::getHourlyRate($code, $level, $vipBonuses) * $speedFactor;
            $gained     = ($elapsed / 3600.0) * $hourlyRate + ($plotGains[$resource] ?? 0.0);

            $city[$resource] = (int) max((int) $city[$resource], min(
                (int) $city[$resource] + $gained,
                $caps[$resource],
            ));
        }

        // A computed snapshot must not accrue the same interval again when
        // passed through apply()/persist() by an action handler.
        $city['last_resource_update'] = gmdate('Y-m-d H:i:s');
        return $city;
    }

    /** Storage research changes the production ceiling; earned loot remains available above it. */
    public static function storageCaps(array $buildings, array $bonuses = []): array
    {
        $caps=BuildingData::getStorageCaps($buildings);
        foreach ($caps as $resource=>&$capacity) { $capacity=(int)floor($capacity*(1+max(0.0,(float)($bonuses[$resource.'_capacity_pct'] ?? 0)))+1.0e-8); }
        unset($capacity);
        return $caps;
    }

    /**
     * Persist the current resource values to the DB.
     *
     * Call this before any action that consumes resources (upgrades, training, etc.)
     * to ensure the accumulated production is not lost.
     *
     * @param array<string, mixed>             $city        City row (after apply())
     * @param array<string, array{level: int}> $buildings   Current buildings
     * @param float                            $speedFactor World speed multiplier
     * @param array<string, int>               $vipBonuses  Optional VIP bonuses from VipService::bonuses()
     */
    public static function persist(
        array $city,
        array $buildings,
        ?float $speedFactor = null,
        array $vipBonuses  = [],
    ): void {
        $db=Connection::getInstance();
        $persist=static function(Connection $db)use($city,$buildings,$speedFactor,$vipBonuses):void{
        // A battle can change this city after its owner loaded a screen. Never
        // overwrite those changes with the old, client-facing resource snapshot.
        $fresh=$db->query('SELECT * FROM cities WHERE id=? FOR UPDATE',[(int)$city['id']])->fetch();
        if(!$fresh)throw new \RuntimeException('Stadt nicht gefunden.');
        $owner=(int)$fresh['player_id'];
        $speedFactor??=max(.01,(float)$db->query('SELECT speed_factor FROM worlds WHERE id=?',[(int)$fresh['world_id']])->fetchColumn());
        if(!$vipBonuses){
            $vipBonuses=\Conquer\Game\Vip\VipService::status($owner)['bonuses'];
            $buffs=\Conquer\Game\Research\BuffEngine::getBuffs($owner,(int)$fresh['world_id']);
            $alliance=$db->query('SELECT alliance_id FROM alliance_members WHERE player_id=? AND world_id=?',[$owner,(int)$fresh['world_id']])->fetchColumn();
            $shared=$alliance===false?[]:\Conquer\Game\Alliance\AllianceResearchService::getProductionBonuses((int)$alliance);
            foreach(['food','lumber','stone','gold']as$resource){$vipBonuses[$resource.'_prod_pct']=(float)($buffs[$resource.'_production']??0)+(float)($shared[$resource.'_pct']??0);$vipBonuses[$resource.'_capacity_pct']=(float)($buffs[$resource.'_capacity']??0)+(float)($buffs['resource_capacity']??0);}
        }
        $speedFactor*=\Conquer\Game\Buff\ActiveBuffService::getMultiplier($owner,'production_boost');
        $updated=self::apply($fresh,$buildings,$speedFactor,$vipBonuses);
        $db->execute(
            'UPDATE cities
             SET    food = ?, lumber = ?, stone = ?, gold = ?,
                    last_resource_update = UTC_TIMESTAMP()
             WHERE  id = ?',
            [
                $updated['food'],
                $updated['lumber'],
                $updated['stone'],
                $updated['gold'],
                (int) $city['id'],
            ],
        );
        };
        if($db->getPdo()->inTransaction())$persist($db);else $db->transaction($persist);
    }
}
