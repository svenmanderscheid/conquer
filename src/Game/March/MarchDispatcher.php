<?php
declare(strict_types=1);

namespace Conquer\Game\March;

use Conquer\Db\Connection;
use Conquer\Game\City\TroopData;

/**
 * Validates and dispatches a troop march (SPEC §8).
 *
 * March types:
 *   5 = MARCH_MONSTER        — attack a field monster
 *   6 = MARCH_CHARM          — collect a map charm
 *   7 = MARCH_ATTACK_PLAYER  — attack another player's city
 *   8 = MARCH_SCOUT          — scout another player's city (no troops needed)
 *
 * March slots: max 3 (base, no research unlock yet).
 * March cap: 50 000 troops per march.
 */
final class MarchDispatcher
{
    private const MAX_SLOTS           = 3;
    private const MAX_CAP             = 50_000;
    private const MARCH_MONSTER       = 5;
    private const MARCH_CHARM         = 6;
    private const MARCH_ATTACK_PLAYER = 7;
    private const MARCH_SCOUT         = 8;
    public const  MARCH_GATHER        = 9;
    public const  MARCH_SUPPORT       = 10;

    private function __construct() {}

    /**
     * Throws RuntimeException('MARCH_SLOT_FULL') when the player has no free march slots.
     * Called internally and from GatherService which shares the same slot cap.
     *
     * @throws \RuntimeException
     */
    public static function assertSlotAvailable(int $playerId): void
    {
        $db = Connection::getInstance();

        $active = (int) $db->query(
            "SELECT COUNT(*) FROM marches
             WHERE player_id = ? AND state IN ('marching','resolving','returning')",
            [$playerId],
        )->fetchColumn();

        if ($active >= self::MAX_SLOTS) {
            throw new \RuntimeException('MARCH_SLOT_FULL');
        }
    }

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

        self::assertSlotAvailable($playerId);

        $db = Connection::getInstance();

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
     * Dispatch a charm-collection march (march_type = 6).
     * Requires at least 1 troop. Speed = slowest troop.
     *
     * @throws \RuntimeException on validation failure
     */
    public static function dispatchCharm(
        int   $playerId,
        int   $cityId,
        int   $originX,
        int   $originY,
        int   $targetX,
        int   $targetY,
        int   $charmId,
        array $selectedTroops,
    ): int {
        if (empty($selectedTroops)) {
            throw new \RuntimeException('Mindestens 1 Truppe muss zum Einsammeln mitgeschickt werden.');
        }

        self::assertSlotAvailable($playerId);

        $db = Connection::getInstance();

        // Validate troops
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
        if ($total <= 0) throw new \RuntimeException('Mindestens 1 Truppe muss ausgewählt werden.');
        if ($total > self::MAX_CAP) throw new \RuntimeException('Zu viele Truppen.');

        // Verify available
        $availableRows = $db->query(
            'SELECT troop_code, count FROM city_troops WHERE city_id = ?',
            [$cityId],
        )->fetchAll();
        $available = [];
        foreach ($availableRows as $r) $available[(int)$r['troop_code']] = (int)$r['count'];

        foreach ($cleanTroops as $code => $count) {
            if (($available[$code] ?? 0) < $count) {
                $name = TroopData::get($code)['name'] ?? ('Code ' . $code);
                throw new \RuntimeException('Nicht genug ' . $name . '.');
            }
        }

        // Validate charm exists and is collectible
        $charm = $db->query(
            'SELECT id FROM map_charms WHERE id = ? AND world_id = 1 AND collected_by IS NULL AND expires_at > UTC_TIMESTAMP()',
            [$charmId],
        )->fetch();
        if ($charm === false) {
            throw new \RuntimeException('Charm nicht mehr verfügbar (abgelaufen oder bereits eingesammelt).');
        }

        // March speed = slowest troop
        $minSpeed = PHP_INT_MAX;
        foreach ($cleanTroops as $code => $_) {
            $speed = (int) (TroopData::get($code)['speed'] ?? 65);
            if ($speed < $minSpeed) $minSpeed = $speed;
        }
        $distance  = sqrt(($targetX - $originX) ** 2 + ($targetY - $originY) ** 2);
        $marchSecs = max(5, (int) floor($distance * 100 / $minSpeed));

        $marchId    = 0;
        $troopsJson = json_encode($cleanTroops);

        $db->transaction(function () use (
            $db, $cityId, $playerId, $targetX, $targetY,
            $charmId, $troopsJson, $marchSecs, $cleanTroops, &$marchId,
        ): void {
            foreach ($cleanTroops as $code => $count) {
                $db->execute(
                    'UPDATE city_troops SET count = count - ? WHERE city_id = ? AND troop_code = ?',
                    [$count, $cityId, $code],
                );
            }

            $db->execute(
                'INSERT INTO marches
                    (player_id, world_id, march_type, origin_city_id,
                     target_x, target_y, target_type, target_id,
                     troops_json, departure_time, arrival_time, state)
                 VALUES
                    (:pid, 1, :type, :city,
                     :tx, :ty, 4, :cid,
                     :troops,
                     UTC_TIMESTAMP(),
                     DATE_ADD(UTC_TIMESTAMP(), INTERVAL :dur SECOND),
                     "marching")',
                [
                    ':pid'    => $playerId,
                    ':type'   => self::MARCH_CHARM,
                    ':city'   => $cityId,
                    ':tx'     => $targetX,
                    ':ty'     => $targetY,
                    ':cid'    => $charmId,
                    ':troops' => $troopsJson,
                    ':dur'    => $marchSecs,
                ],
            );

            $marchId = (int) $db->lastInsertId();
        });

        return $marchId;
    }

    /**
     * Dispatch a player-vs-player attack march (march_type = 7).
     *
     * @param array<int,int> $selectedTroops  troop_code → count to send
     *
     * @throws \RuntimeException on validation failure
     */
    public static function dispatchPlayerAttack(
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

        self::assertSlotAvailable($playerId);

        $db = Connection::getInstance();

        // ── Validate + count troops ──────────────────────────────────────────
        $total       = 0;
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

        // ── Verify target city exists at coords ──────────────────────────────
        $targetCity = $db->query(
            'SELECT id, player_id FROM cities WHERE world_id = 1 AND coord_x = ? AND coord_y = ? AND is_hidden = 0',
            [$targetX, $targetY],
        )->fetch();

        if ($targetCity === false) {
            throw new \RuntimeException('Keine Stadt auf diesem Tile (' . $targetX . ',' . $targetY . ').');
        }

        if ((int) $targetCity['player_id'] === $playerId) {
            throw new \RuntimeException('Du kannst dich nicht selbst angreifen.');
        }

        $targetCityId = (int) $targetCity['id'];

        // ── Calculate march duration (SPEC §8.3) ─────────────────────────────
        $distance = sqrt(($targetX - $originX) ** 2 + ($targetY - $originY) ** 2);
        $minSpeed = PHP_INT_MAX;

        foreach ($cleanTroops as $code => $_) {
            $def   = TroopData::get($code);
            $speed = (int) ($def['speed'] ?? 65);
            if ($speed < $minSpeed) $minSpeed = $speed;
        }

        $marchSecs  = max(5, (int) floor($distance * 100 / $minSpeed));
        $troopsJson = json_encode($cleanTroops);

        // ── Transaction: deduct troops + insert march ─────────────────────────
        $marchId = 0;
        $db->transaction(function () use (
            $db, $cityId, $playerId, $targetX, $targetY,
            $targetCityId, $troopsJson, $marchSecs, $cleanTroops, &$marchId,
        ): void {
            foreach ($cleanTroops as $code => $count) {
                $db->execute(
                    'UPDATE city_troops SET count = count - ? WHERE city_id = ? AND troop_code = ?',
                    [$count, $cityId, $code],
                );
            }

            $db->execute(
                'INSERT INTO marches
                    (player_id, world_id, march_type, origin_city_id,
                     target_x, target_y, target_type, target_id,
                     troops_json, departure_time, arrival_time, state)
                 VALUES
                    (:pid, 1, :type, :city,
                     :tx, :ty, 2, :tid,
                     :troops,
                     UTC_TIMESTAMP(),
                     DATE_ADD(UTC_TIMESTAMP(), INTERVAL :dur SECOND),
                     "marching")',
                [
                    ':pid'    => $playerId,
                    ':type'   => self::MARCH_ATTACK_PLAYER,
                    ':city'   => $cityId,
                    ':tx'     => $targetX,
                    ':ty'     => $targetY,
                    ':tid'    => $targetCityId,
                    ':troops' => $troopsJson,
                    ':dur'    => $marchSecs,
                ],
            );

            $marchId = (int) $db->lastInsertId();
        });

        return $marchId;
    }

    /**
     * Dispatch a scout march (march_type = 8).
     * No troops required — scouts move at fixed high speed.
     *
     * @throws \RuntimeException on validation failure
     */
    public static function dispatchScout(
        int $playerId,
        int $cityId,
        int $originX,
        int $originY,
        int $targetX,
        int $targetY,
    ): int {
        self::assertSlotAvailable($playerId);

        $db = Connection::getInstance();

        // ── Verify target city exists ─────────────────────────────────────────
        $targetCity = $db->query(
            'SELECT id FROM cities WHERE world_id = 1 AND coord_x = ? AND coord_y = ? AND is_hidden = 0',
            [$targetX, $targetY],
        )->fetch();

        if ($targetCity === false) {
            throw new \RuntimeException('Keine Stadt auf diesem Tile (' . $targetX . ',' . $targetY . ').');
        }

        $targetCityId = (int) $targetCity['id'];

        // ── March duration: Scout-Speed = 200 (very fast), min 2 seconds ─────
        $scoutSpeed = 200;
        $distance   = sqrt(($targetX - $originX) ** 2 + ($targetY - $originY) ** 2);
        $marchSecs  = max(2, (int) floor($distance * 100 / $scoutSpeed));

        // ── Insert march ──────────────────────────────────────────────────────
        $marchId = 0;
        $db->transaction(function () use (
            $db, $cityId, $playerId, $targetX, $targetY,
            $targetCityId, $marchSecs, &$marchId,
        ): void {
            $db->execute(
                'INSERT INTO marches
                    (player_id, world_id, march_type, origin_city_id,
                     target_x, target_y, target_type, target_id,
                     troops_json, departure_time, arrival_time, state)
                 VALUES
                    (:pid, 1, :type, :city,
                     :tx, :ty, 2, :tid,
                     "{}",
                     UTC_TIMESTAMP(),
                     DATE_ADD(UTC_TIMESTAMP(), INTERVAL :dur SECOND),
                     "marching")',
                [
                    ':pid'  => $playerId,
                    ':type' => self::MARCH_SCOUT,
                    ':city' => $cityId,
                    ':tx'   => $targetX,
                    ':ty'   => $targetY,
                    ':tid'  => $targetCityId,
                    ':dur'  => $marchSecs,
                ],
            );

            $marchId = (int) $db->lastInsertId();
        });

        return $marchId;
    }

    /**
     * Dispatch a gather march to a field object (march_type = 9).
     * Delegates to GatherService for full validation and insertion.
     *
     * @throws \RuntimeException on validation failure
     */
    public static function dispatchGather(
        int $playerId,
        int $cityId,
        int $targetX,
        int $targetY,
        int $troopCount,
    ): int {
        // Delegate to GatherService
        return \Conquer\Game\March\GatherService::dispatch($playerId, $cityId, $targetX, $targetY, $troopCount);
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
                "SELECT m.id, m.march_type, m.origin_city_id,
                        c.coord_x AS origin_x, c.coord_y AS origin_y,
                        m.target_x, m.target_y, m.target_type, m.target_id,
                        m.troops_json, m.departure_time, m.arrival_time, m.return_time, m.state
                 FROM   marches m
                 JOIN   cities c ON c.id = m.origin_city_id
                 WHERE  m.player_id = ? AND m.state IN ('marching','resolving','returning')
                 ORDER  BY m.arrival_time ASC",
                [$playerId],
            )->fetchAll();
        } catch (\PDOException) {
            return [];
        }
    }

    /**
     * Returns all active marches from all players (for public map overlay).
     * Only includes PvP attacks (type 7) and monster marches (type 5).
     *
     * @return list<array<string,mixed>>
     */
    public static function listAllActive(): array
    {
        $db = Connection::getInstance();

        try {
            return $db->query(
                "SELECT m.id, m.march_type, m.player_id,
                        c.coord_x AS origin_x, c.coord_y AS origin_y,
                        m.target_x, m.target_y,
                        m.departure_time, m.arrival_time, m.return_time, m.state
                 FROM   marches m
                 JOIN   cities c ON c.id = m.origin_city_id
                 WHERE  m.state IN ('marching','resolving','returning')
                   AND  m.march_type IN (5, 7)
                 ORDER  BY m.arrival_time ASC",
            )->fetchAll();
        } catch (\PDOException) {
            return [];
        }
    }
}
