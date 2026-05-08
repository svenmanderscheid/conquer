<?php
declare(strict_types=1);

namespace Conquer\Game\March;

use Conquer\Db\Connection;
use Conquer\Game\City\TroopData;

/**
 * Validates and dispatches a troop march (SPEC §8).
 *
 * MVP scope: monster attacks only (march_type = 5).
 * March slots: max 2 (base, no research unlock yet).
 * March cap: 50 000 troops per march.
 */
final class MarchDispatcher
{
    private const MAX_SLOTS     = 2;
    private const MAX_CAP       = 50_000;
    private const MARCH_MONSTER = 5;

    private function __construct() {}

    /**
     * Dispatch a monster attack march.
     *
     * @param array<int,int> $selectedTroops  troop_code → count to send
     *
     * @throws \RuntimeException on validation failure
     */
    public static function dispatchMonster(
        int   $playerId,
        int   $cityId,
        int   $originX,
        int   $originY,
        int   $targetX,
        int   $targetY,
        array $selectedTroops,
    ): int {
        if (empty($selectedTroops)) {
            throw new \RuntimeException('Keine Truppen ausgewählt.');
        }

        $db = Connection::getInstance();

        // ── Check march slots ────────────────────────────────────────────────
        $active = (int) $db->query(
            "SELECT COUNT(*) FROM marches
             WHERE player_id = ? AND state IN ('marching','resolving','returning')",
            [$playerId],
        )->fetchColumn();

        if ($active >= self::MAX_SLOTS) {
            throw new \RuntimeException(
                'Alle ' . self::MAX_SLOTS . ' Marsch-Slots belegt. Warte bis ein Marsch zurückkehrt.'
            );
        }

        // ── Validate + count troops ──────────────────────────────────────────
        $total = 0;
        $cleanTroops = [];

        foreach ($selectedTroops as $code => $count) {
            $count = (int) $count;
            if ($count <= 0) continue;

            if (TroopData::get((int) $code) === null) {
                throw new \RuntimeException('Unbekannter Truppen-Code: ' . $code);
            }
            $cleanTroops[(int) $code] = $count;
            $total += $count;
        }

        if ($total <= 0) {
            throw new \RuntimeException('Mindestens 1 Truppe muss ausgewählt werden.');
        }
        if ($total > self::MAX_CAP) {
            throw new \RuntimeException('Maximal ' . number_format(self::MAX_CAP) . ' Truppen pro Marsch.');
        }

        // ── Verify troops available in city ──────────────────────────────────
        $availableRows = $db->query(
            'SELECT troop_code, count FROM city_troops WHERE city_id = ?',
            [$cityId],
        )->fetchAll();

        $available = [];
        foreach ($availableRows as $r) {
            $available[(int) $r['troop_code']] = (int) $r['count'];
        }

        foreach ($cleanTroops as $code => $count) {
            if (($available[$code] ?? 0) < $count) {
                $def  = TroopData::get($code);
                $name = $def['name'] ?? ('Code ' . $code);
                throw new \RuntimeException(
                    'Nicht genug ' . $name . '. Vorhanden: ' . ($available[$code] ?? 0) . ', benötigt: ' . $count . '.'
                );
            }
        }

        // ── Validate target monster ──────────────────────────────────────────
        $monster = $db->query(
            'SELECT id, hp_current FROM field_monsters WHERE world_id = 1 AND coord_x = ? AND coord_y = ?',
            [$targetX, $targetY],
        )->fetch();

        if ($monster === false) {
            throw new \RuntimeException('Kein Monster auf diesem Tile (' . $targetX . ',' . $targetY . ').');
        }

        if ((int) $monster['hp_current'] <= 0) {
            throw new \RuntimeException('Das Monster ist bereits besiegt.');
        }

        // ── Calculate march duration (SPEC §8.3) ─────────────────────────────
        $distance  = sqrt(($targetX - $originX) ** 2 + ($targetY - $originY) ** 2);
        $minSpeed  = PHP_INT_MAX;

        foreach ($cleanTroops as $code => $_) {
            $def   = TroopData::get($code);
            $speed = (int) ($def['speed'] ?? 65);
            if ($speed < $minSpeed) $minSpeed = $speed;
        }

        $marchSecs   = max(5, (int) floor($distance * 100 / $minSpeed));
        $monsterId   = (int) $monster['id'];
        $troopsJson  = json_encode($cleanTroops);

        // ── Transaction: deduct troops + insert march ─────────────────────────
        $marchId = 0;
        $db->transaction(function () use (
            $db, $cityId, $playerId, $targetX, $targetY,
            $monsterId, $troopsJson, $marchSecs, $cleanTroops, &$marchId,
        ): void {
            // Deduct from city_troops.
            foreach ($cleanTroops as $code => $count) {
                $db->execute(
                    'UPDATE city_troops SET count = count - ? WHERE city_id = ? AND troop_code = ?',
                    [$count, $cityId, $code],
                );
            }

            // Insert march.
            $db->execute(
                'INSERT INTO marches
                    (player_id, world_id, march_type, origin_city_id,
                     target_x, target_y, target_type, target_id,
                     troops_json, departure_time, arrival_time, state)
                 VALUES
                    (:pid, 1, :type, :city,
                     :tx, :ty, 3, :mid,
                     :troops,
                     UTC_TIMESTAMP(),
                     DATE_ADD(UTC_TIMESTAMP(), INTERVAL :dur SECOND),
                     "marching")',
                [
                    ':pid'    => $playerId,
                    ':type'   => self::MARCH_MONSTER,
                    ':city'   => $cityId,
                    ':tx'     => $targetX,
                    ':ty'     => $targetY,
                    ':mid'    => $monsterId,
                    ':troops' => $troopsJson,
                    ':dur'    => $marchSecs,
                ],
            );

            $marchId = (int) $db->lastInsertId();
        });

        return $marchId;
    }

    /**
     * Returns all active marches for a player (for the map overlay + city UI).
     *
     * @return list<array<string,mixed>>
     */
    public static function listActive(int $playerId): array
    {
        $db = Connection::getInstance();

        try {
            return $db->query(
                "SELECT id, march_type, origin_city_id,
                        target_x, target_y, target_type, target_id,
                        troops_json, departure_time, arrival_time, return_time, state
                 FROM   marches
                 WHERE  player_id = ? AND state IN ('marching','resolving','returning')
                 ORDER  BY arrival_time ASC",
                [$playerId],
            )->fetchAll();
        } catch (\PDOException) {
            return [];
        }
    }
}
