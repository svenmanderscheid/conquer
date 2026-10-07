<?php
declare(strict_types=1);

namespace Conquer\Game\Player;

use Conquer\Db\Connection;

/**
 * Action Point system.
 *
 * - Regeneration cap: 200 (plus talents); refills may exceed it
 * - Regen rate:      1 AP every 5 minutes (12 AP/hour)
 * - Monster cost:    10 AP  (normal monsters)
 * - Dämmerhorn cost: 25 AP
 * - Dragon cost:     30 AP  (Dragon / Wyrm / Wyvern)
 *
 * AP regeneration is computed lazily when get() is called.
 * If the recalculated value differs from the stored value, the DB is updated.
 */
final class ActionPoints
{
    public const MAX_AP           = 200;
    public const REGEN_PER_HOUR   = 12;
    private const REGEN_INTERVAL_MINUTES = 5; // 1 AP per interval

    private function __construct() {}

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Returns current AP for a player, applying any pending regeneration first.
     * Persists updated values to DB if AP changed.
     *
     * @return array{current: int, max: int, regen_per_hour: float}
     * @throws \RuntimeException if the player is not found
     */
    public static function get(int $playerId): array
    {
        $db=Connection::getInstance();
        $regenerate=static function(Connection $db)use($playerId):array {
            $row=$db->query('SELECT action_points,last_ap_regen FROM players WHERE id=? FOR UPDATE',[$playerId])->fetch();
            if(!$row)throw new \RuntimeException('Player not found.');
            $saved=$db->query('SELECT rate,fraction FROM player_ap_regeneration WHERE player_id=?',[$playerId])->fetch()?:['rate'=>1,'fraction'=>0];
            $bonuses=MasteryService::bonuses($playerId);
            $vip=\Conquer\Game\Vip\VipService::status($playerId)['bonuses'];
            $rate=1+max(0,(float)($bonuses['talent_ap_regen']??0))+$vip['action_point_regeneration']/100;
            $maximum=self::MAX_AP+max(0,(int)($bonuses['talent_max_ap']??0))+$vip['action_points'];
            $elapsed=max(0,time()-strtotime($row['last_ap_regen'].' UTC'));
            $stored=max(0,(int)$row['action_points']);
            // Full or overfilled balances keep their value without banking regeneration.
            $credit=$stored<$maximum?$elapsed/300*(float)$saved['rate']+(float)$saved['fraction']:0;
            $current=$stored<$maximum?min($maximum,$stored+(int)floor($credit+1e-9)):$stored;
            $fraction=$current>=$maximum?0:max(0,$credit-floor($credit+1e-9));
            $db->execute('UPDATE players SET action_points=?,last_ap_regen=UTC_TIMESTAMP() WHERE id=?',[$current,$playerId]);
            $db->execute('INSERT INTO player_ap_regeneration(player_id,rate,fraction) VALUES(?,?,?) ON DUPLICATE KEY UPDATE rate=VALUES(rate),fraction=VALUES(fraction)',[$playerId,$rate,$fraction]);
            return ['current'=>$current,'max'=>$maximum,'regen_per_hour'=>self::REGEN_PER_HOUR*$rate];
        };
        return $db->getPdo()->inTransaction()?$regenerate($db):$db->transaction($regenerate);
    }

    /**
     * Deducts AP from a player after validating sufficient balance.
     * Calls get() first to apply pending regeneration.
     *
     * @throws \RuntimeException if the player has insufficient AP
     */
    public static function deduct(int $playerId, int $cost): void
    {
        if ($cost <= 0) {
            return;
        }

        $db = Connection::getInstance();

        // Apply regen first so we operate on the freshest value
        $state   = self::get($playerId);
        $current = $state['current'];

        if ($current < $cost) {
            throw new \RuntimeException(
                'Nicht genug Aktionspunkte. Vorhanden: ' . $current . ', benötigt: ' . $cost . '.'
            );
        }

        $db->execute(
            'UPDATE players SET action_points = action_points - ? WHERE id = ? AND action_points >= ?',
            [$cost, $playerId, $cost],
        );
    }

    /**
     * Returns the AP cost for attacking a named monster.
     *
     * Cost table:
     *   'Dämmerhorn' / 'Deathkar'     → 25 AP
     *   'Dragon' / 'Wyrm' / 'Wyvern' → 30 AP
     *   everything else               → 10 AP
     */
    public static function costForMonster(string $monsterName): int
    {
        $name = strtolower(trim($monsterName));

        if (in_array($name, ['dämmerhorn', 'deathkar'], true)) {
            return 25;
        }

        if (
            str_contains($name, 'dragon') ||
            str_contains($name, 'wyrm')   ||
            str_contains($name, 'wyvern')
        ) {
            return 30;
        }

        return 10;
    }

    public static function monsterCost(int $baseCost,array $bonuses): int
    {
        return $baseCost<=0?0:max(1,(int)ceil($baseCost*max(.05,1+(float)($bonuses['talent_monster_ap_cost']??0))-1e-8));
    }

    /**
     * Credit the full refill/refund under the same lock as pending regeneration.
     * @return array{current: int, max: int, regen_per_hour: float}
     */
    public static function credit(int $playerId,int $amount): array
    {
        if($amount<=0)throw new \InvalidArgumentException('AP credit must be positive.');
        $db=Connection::getInstance();
        $credit=static function(Connection $db)use($playerId,$amount):array {
            $state=self::get($playerId);
            $state['current']+=$amount;
            $db->execute('UPDATE players SET action_points=action_points+? WHERE id=?',[$amount,$playerId]);
            if($state['current']>=$state['max'])$db->execute('UPDATE player_ap_regeneration SET fraction=0 WHERE player_id=?',[$playerId]);
            return $state;
        };
        return $db->getPdo()->inTransaction()?$credit($db):$db->transaction($credit);
    }

    public static function refund(int $playerId,int $paid): void
    {
        if($paid<=0)return;
        self::credit($playerId,$paid);
    }
}
