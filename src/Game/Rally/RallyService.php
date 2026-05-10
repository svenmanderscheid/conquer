<?php
declare(strict_types=1);

namespace Conquer\Game\Rally;

use Conquer\Db\Connection;
use Conquer\Game\City\TroopData;

/**
 * Alliance rally system.
 *
 * A rally is a coordinated multi-player attack against an enemy city.
 * The leader starts the rally and sets a gather window (rally_minutes).
 * Alliance members join before launch_at fires.
 * MarchTick resolves the combined attack when arrival_time is reached.
 *
 * Constraints enforced here:
 *   - Leader and all participants must belong to the same alliance.
 *   - A player cannot rally their own city.
 *   - Sufficient troops must be available in the given city.
 *   - Participants can only join while status = 'gathering'.
 *   - Each player can join a given rally only once (DB UNIQUE KEY enforces this too).
 *
 * March speed: determined by the slowest troop type among all participants.
 * This is only computable at launch time (tryLaunch) since participants
 * are still joining during the gather window.
 */
final class RallyService
{
    /** Minimum rally gather window in minutes. */
    private const MIN_RALLY_MINUTES = 1;

    /** Maximum rally gather window in minutes. */
    private const MAX_RALLY_MINUTES = 60;

    /** Maximum troops the leader can send in one rally. */
    private const MAX_TROOP_CAP = 50_000;

    private function __construct() {}

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Creates a new rally and deducts the leader's troops from their city.
     *
     * @param int                 $leaderId      Player ID of the rally leader
     * @param int                 $leaderCityId  City the leader's troops march from
     * @param int                 $targetPlayerId Player being attacked
     * @param int                 $targetX       Map X coordinate of the target city
     * @param int                 $targetY       Map Y coordinate of the target city
     * @param array<int|string,int> $troops      troop_code → count
     * @param int                 $rallyMinutes  Gather window before launch
     * @param string              $message       Optional message shown to alliance
     *
     * @return int  The new rally ID
     * @throws \RuntimeException on any validation failure
     */
    public static function start(
        int    $leaderId,
        int    $leaderCityId,
        int    $targetPlayerId,
        int    $targetX,
        int    $targetY,
        array  $troops,
        int    $rallyMinutes,
        string $message,
    ): int {
        // ── Basic argument validation ─────────────────────────────────────────
        if ($leaderId === $targetPlayerId) {
            throw new \RuntimeException('Du kannst keine Rally gegen dich selbst starten.');
        }

        if ($rallyMinutes < self::MIN_RALLY_MINUTES || $rallyMinutes > self::MAX_RALLY_MINUTES) {
            throw new \RuntimeException(
                'Rally-Dauer muss zwischen ' . self::MIN_RALLY_MINUTES .
                ' und ' . self::MAX_RALLY_MINUTES . ' Minuten liegen.'
            );
        }

        $cleanTroops = self::validateAndCleanTroops($troops, self::MAX_TROOP_CAP);

        $db = Connection::getInstance();

        // ── Alliance membership check ─────────────────────────────────────────
        $leaderAllianceId = self::getAllianceId($db, $leaderId);
        if ($leaderAllianceId === null) {
            throw new \RuntimeException('Du musst Mitglied einer Alliance sein, um eine Rally zu starten.');
        }

        $targetAllianceId = self::getAllianceId($db, $targetPlayerId);
        if ($targetAllianceId === $leaderAllianceId) {
            throw new \RuntimeException('Du kannst keine Rally gegen ein Mitglied deiner eigenen Alliance starten.');
        }

        // ── Verify target city exists at given coordinates ────────────────────
        $targetCity = $db->query(
            'SELECT id FROM cities WHERE player_id = ? AND coord_x = ? AND coord_y = ?',
            [$targetPlayerId, $targetX, $targetY],
        )->fetch();

        if ($targetCity === false) {
            throw new \RuntimeException(
                'Kein Ziel-Stadtfeld von Spieler ' . $targetPlayerId .
                ' auf Koordinaten (' . $targetX . ',' . $targetY . ').'
            );
        }

        $targetCityId = (int) $targetCity['id'];

        // ── Verify troop availability ─────────────────────────────────────────
        self::assertTroopsAvailable($db, $leaderCityId, $cleanTroops);

        // ── Persist inside a transaction ──────────────────────────────────────
        $rallyId = 0;

        $db->transaction(function (Connection $db) use (
            $leaderId, $leaderCityId,
            $targetPlayerId, $targetCityId,
            $targetX, $targetY,
            $cleanTroops, $rallyMinutes, $message,
            &$rallyId,
        ): void {
            // Deduct leader's troops from city
            self::deductTroops($db, $leaderCityId, $cleanTroops);

            // Insert rally record
            $db->execute(
                'INSERT INTO rallies
                     (leader_player_id, leader_city_id,
                      target_player_id, target_city_id,
                      target_x, target_y,
                      rally_minutes, troops_json, message,
                      status, launch_at, created_at)
                 VALUES
                     (:lid, :lcid,
                      :tpid, :tcid,
                      :tx, :ty,
                      :rm, :tj, :msg,
                      "gathering",
                      DATE_ADD(UTC_TIMESTAMP(), INTERVAL :rm2 MINUTE),
                      UTC_TIMESTAMP())',
                [
                    ':lid'  => $leaderId,
                    ':lcid' => $leaderCityId,
                    ':tpid' => $targetPlayerId,
                    ':tcid' => $targetCityId,
                    ':tx'   => $targetX,
                    ':ty'   => $targetY,
                    ':rm'   => $rallyMinutes,
                    ':tj'   => json_encode($cleanTroops),
                    ':msg'  => mb_substr($message, 0, 512),
                    ':rm2'  => $rallyMinutes,
                ],
            );

            $rallyId = $db->lastInsertId();
        });

        return $rallyId;
    }

    /**
     * Adds a participant to an existing rally.
     *
     * @param int                 $playerId  Joining player's ID
     * @param int                 $cityId    City the troops march from
     * @param int                 $rallyId   Target rally
     * @param array<int|string,int> $troops  troop_code → count
     *
     * @throws \RuntimeException on validation failure
     */
    public static function join(
        int   $playerId,
        int   $cityId,
        int   $rallyId,
        array $troops,
    ): void {
        $cleanTroops = self::validateAndCleanTroops($troops, self::MAX_TROOP_CAP);

        $db = Connection::getInstance();

        // ── Load rally ────────────────────────────────────────────────────────
        $rally = $db->query(
            'SELECT id, leader_player_id, status FROM rallies WHERE id = ?',
            [$rallyId],
        )->fetch();

        if ($rally === false) {
            throw new \RuntimeException('Rally #' . $rallyId . ' nicht gefunden.');
        }

        if ($rally['status'] !== 'gathering') {
            throw new \RuntimeException('Diese Rally akzeptiert keine neuen Teilnehmer mehr (Status: ' . $rally['status'] . ').');
        }

        $leaderId = (int) $rally['leader_player_id'];

        if ($playerId === $leaderId) {
            throw new \RuntimeException('Der Leader ist bereits Teil der Rally.');
        }

        // ── Alliance check: same alliance as leader ───────────────────────────
        $leaderAllianceId = self::getAllianceId($db, $leaderId);
        $playerAllianceId = self::getAllianceId($db, $playerId);

        if ($leaderAllianceId === null || $playerAllianceId !== $leaderAllianceId) {
            throw new \RuntimeException('Du musst in derselben Alliance wie der Rally-Leader sein.');
        }

        // ── Check player hasn't already joined ────────────────────────────────
        $alreadyJoined = $db->query(
            'SELECT id FROM rally_participants WHERE rally_id = ? AND player_id = ?',
            [$rallyId, $playerId],
        )->fetch();

        if ($alreadyJoined !== false) {
            throw new \RuntimeException('Du bist dieser Rally bereits beigetreten.');
        }

        // ── Troop availability ────────────────────────────────────────────────
        self::assertTroopsAvailable($db, $cityId, $cleanTroops);

        // ── Persist ───────────────────────────────────────────────────────────
        $db->transaction(function (Connection $db) use ($rallyId, $playerId, $cityId, $cleanTroops): void {
            self::deductTroops($db, $cityId, $cleanTroops);

            $db->execute(
                'INSERT INTO rally_participants
                     (rally_id, player_id, city_id, troops_json, joined_at, status)
                 VALUES
                     (?, ?, ?, ?, UTC_TIMESTAMP(), "pending")',
                [$rallyId, $playerId, $cityId, json_encode($cleanTroops)],
            );
        });
    }

    /**
     * Returns all active (status = 'gathering') rallies started by members
     * of the given alliance. Includes participant count for each rally.
     *
     * @return list<array<string,mixed>>
     */
    public static function listForAlliance(int $allianceId): array
    {
        $db = Connection::getInstance();

        return $db->query(
            "SELECT
                 r.id,
                 r.leader_player_id,
                 r.leader_city_id,
                 r.target_player_id,
                 r.target_city_id,
                 r.target_x,
                 r.target_y,
                 r.rally_minutes,
                 r.message,
                 r.status,
                 r.launch_at,
                 r.created_at,
                 p.username         AS leader_username,
                 tp.username        AS target_username,
                 COUNT(rp.id)       AS participant_count
             FROM   rallies          r
             JOIN   players          p  ON p.id  = r.leader_player_id
             JOIN   players          tp ON tp.id = r.target_player_id
             JOIN   alliance_members am ON am.player_id = r.leader_player_id
             LEFT JOIN rally_participants rp ON rp.rally_id = r.id
             WHERE  am.alliance_id = ?
               AND  r.status = 'gathering'
             GROUP BY r.id
             ORDER BY r.launch_at ASC",
            [$allianceId],
        )->fetchAll();
    }

    /**
     * Attempts to launch a rally that has reached its launch_at time.
     *
     * Conditions for launch:
     *   - status = 'gathering'
     *   - launch_at <= UTC_NOW
     *
     * On launch:
     *   - Determines march speed from the slowest troop across all participants
     *     (leader + all joined participants).
     *   - Calculates arrival_time and return_time.
     *   - Sets status = 'marching' and participant status = 'marching'.
     *
     * If the rally has no valid troops at all, it is cancelled.
     *
     * @throws \RuntimeException if the rally is not found or not ready
     */
    public static function tryLaunch(int $rallyId): void
    {
        $db = Connection::getInstance();

        $rally = $db->query(
            "SELECT id, leader_city_id, target_x, target_y,
                    troops_json, status, launch_at
             FROM   rallies
             WHERE  id = ?",
            [$rallyId],
        )->fetch();

        if ($rally === false) {
            throw new \RuntimeException('Rally #' . $rallyId . ' nicht gefunden.');
        }

        if ($rally['status'] !== 'gathering') {
            throw new \RuntimeException('Rally #' . $rallyId . ' ist nicht im Status "gathering".');
        }

        $launchAt = new \DateTimeImmutable($rally['launch_at'], new \DateTimeZone('UTC'));
        $now      = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        if ($now < $launchAt) {
            throw new \RuntimeException(
                'Rally #' . $rallyId . ' ist noch nicht bereit zum Starten ' .
                '(launch_at: ' . $rally['launch_at'] . ').'
            );
        }

        // ── Collect all troop sets to determine slowest speed ─────────────────
        $allTroopCodes = self::collectAllTroopCodes($rally, $db, $rallyId);

        if (empty($allTroopCodes)) {
            // No troops — cancel
            $db->execute(
                "UPDATE rallies SET status = 'cancelled' WHERE id = ?",
                [$rallyId],
            );
            return;
        }

        // ── March speed = slowest troop ───────────────────────────────────────
        $minSpeed = PHP_INT_MAX;

        foreach ($allTroopCodes as $code) {
            $def   = TroopData::get((int) $code);
            $speed = (int) ($def['speed'] ?? 65);
            if ($speed < $minSpeed) {
                $minSpeed = $speed;
            }
        }

        // ── Leader city coordinates as march origin ───────────────────────────
        $leaderCity = $db->query(
            'SELECT coord_x, coord_y FROM cities WHERE id = ?',
            [(int) $rally['leader_city_id']],
        )->fetch();

        if ($leaderCity === false) {
            throw new \RuntimeException('Leader-Stadt nicht gefunden.');
        }

        $distance  = sqrt(
            ((int) $rally['target_x'] - (int) $leaderCity['coord_x']) ** 2 +
            ((int) $rally['target_y'] - (int) $leaderCity['coord_y']) ** 2
        );

        $marchSecs   = max(5, (int) floor($distance * 100 / $minSpeed));
        $returnSecs  = $marchSecs * 2;

        $db->transaction(function (Connection $db) use ($rallyId, $marchSecs, $returnSecs): void {
            $db->execute(
                "UPDATE rallies
                 SET status       = 'marching',
                     arrival_time = DATE_ADD(UTC_TIMESTAMP(), INTERVAL :arr SECOND),
                     return_time  = DATE_ADD(UTC_TIMESTAMP(), INTERVAL :ret SECOND)
                 WHERE id = :id",
                [':arr' => $marchSecs, ':ret' => $returnSecs, ':id' => $rallyId],
            );

            $db->execute(
                "UPDATE rally_participants
                 SET status = 'marching'
                 WHERE rally_id = ? AND status = 'pending'",
                [$rallyId],
            );
        });
    }

    /**
     * Returns all open (status = 'gathering') rallies for an alliance with
     * participant counts. Alias kept for API handler compatibility — this
     * wraps listForAlliance which already filters by 'gathering'.
     *
     * @return list<array<string,mixed>>
     */
    public static function getOpenRallies(int $allianceId): array
    {
        return self::listForAlliance($allianceId);
    }

    /**
     * Manually launches a rally before its auto-launch timer fires.
     *
     * Delegates to tryLaunch after verifying the caller is the captain (leader).
     * Only the leader may force-launch early.
     *
     * @return array{launched: bool, rally_id: int}
     * @throws \RuntimeException if the caller is not the captain or the rally
     *         is not in 'gathering' state
     */
    public static function launch(int $rallyId, int $captainId): array
    {
        $db = Connection::getInstance();

        $rally = $db->query(
            'SELECT id, leader_player_id, status, launch_at FROM rallies WHERE id = ?',
            [$rallyId],
        )->fetch();

        if ($rally === false) {
            throw new \RuntimeException('Rally #' . $rallyId . ' nicht gefunden.');
        }

        if ((int) $rally['leader_player_id'] !== $captainId) {
            throw new \RuntimeException('Nur der Rally-Leader kann die Rally starten.');
        }

        if ($rally['status'] !== 'gathering') {
            throw new \RuntimeException(
                'Rally #' . $rallyId . ' kann nicht gestartet werden (Status: ' . $rally['status'] . ').'
            );
        }

        // Allow early launch — tryLaunch enforces launch_at, so we temporarily
        // set it to UTC_TIMESTAMP() if needed.
        $launchAt = new \DateTimeImmutable($rally['launch_at'], new \DateTimeZone('UTC'));
        $now      = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        if ($now < $launchAt) {
            // Force launch_at to now so tryLaunch accepts it
            $db->execute(
                'UPDATE rallies SET launch_at = UTC_TIMESTAMP() WHERE id = ?',
                [$rallyId],
            );
        }

        self::tryLaunch($rallyId);

        return ['launched' => true, 'rally_id' => $rallyId];
    }

    /**
     * Cancels a rally and returns all troops to their respective cities.
     *
     * Only the captain (leader) may cancel. Participants' troops are also
     * returned. The rally status is set to 'cancelled'.
     *
     * @throws \RuntimeException if the caller is not the captain or the rally
     *         is already past 'gathering' state
     */
    public static function cancel(int $rallyId, int $captainId): void
    {
        $db = Connection::getInstance();

        $rally = $db->query(
            'SELECT id, leader_player_id, leader_city_id, troops_json, status
             FROM   rallies
             WHERE  id = ?',
            [$rallyId],
        )->fetch();

        if ($rally === false) {
            throw new \RuntimeException('Rally #' . $rallyId . ' nicht gefunden.');
        }

        if ((int) $rally['leader_player_id'] !== $captainId) {
            throw new \RuntimeException('Nur der Rally-Leader kann die Rally abbrechen.');
        }

        if ($rally['status'] !== 'gathering') {
            throw new \RuntimeException(
                'Rally #' . $rallyId . ' kann nicht mehr abgebrochen werden (Status: ' . $rally['status'] . ').'
            );
        }

        $db->transaction(function (Connection $db) use ($rallyId, $rally): void {
            // Return leader troops
            $leaderTroops = json_decode($rally['troops_json'] ?? '{}', true) ?: [];
            $leaderCityId = (int) $rally['leader_city_id'];

            foreach ($leaderTroops as $code => $count) {
                if ((int) $count <= 0) {
                    continue;
                }

                $db->execute(
                    "INSERT INTO city_troops (city_id, troop_code, count)
                     VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE count = count + VALUES(count)",
                    [$leaderCityId, (int) $code, (int) $count],
                );
            }

            // Return participant troops
            $participants = $db->query(
                'SELECT player_id, city_id, troops_json FROM rally_participants WHERE rally_id = ?',
                [$rallyId],
            )->fetchAll();

            foreach ($participants as $p) {
                $pTroops = json_decode($p['troops_json'] ?? '{}', true) ?: [];
                $pCityId = (int) $p['city_id'];

                foreach ($pTroops as $code => $count) {
                    if ((int) $count <= 0) {
                        continue;
                    }

                    $db->execute(
                        "INSERT INTO city_troops (city_id, troop_code, count)
                         VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE count = count + VALUES(count)",
                        [$pCityId, (int) $code, (int) $count],
                    );
                }
            }

            // Update rally status
            $db->execute(
                "UPDATE rallies SET status = 'cancelled' WHERE id = ?",
                [$rallyId],
            );

            // Mark participants cancelled
            $db->execute(
                "UPDATE rally_participants SET status = 'cancelled' WHERE rally_id = ?",
                [$rallyId],
            );
        });
    }

    /**
     * Returns all participants for a rally with their troop compositions.
     *
     * @return list<array<string,mixed>>
     */
    public static function getParticipants(int $rallyId): array
    {
        $db = Connection::getInstance();

        $rows = $db->query(
            "SELECT
                 rp.id,
                 rp.player_id,
                 rp.city_id,
                 rp.troops_json,
                 rp.joined_at,
                 rp.status,
                 p.username
             FROM   rally_participants rp
             JOIN   players             p ON p.id = rp.player_id
             WHERE  rp.rally_id = ?
             ORDER  BY rp.joined_at ASC",
            [$rallyId],
        )->fetchAll();

        foreach ($rows as &$row) {
            $row['troops'] = json_decode((string) ($row['troops_json'] ?? '{}'), true) ?: [];
            unset($row['troops_json']);
        }
        unset($row);

        return $rows;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Validates and normalises a troop array.
     * Removes zero entries, checks codes exist in TroopData, enforces cap.
     *
     * @param array<int|string,int> $troops
     * @return array<int,int>  clean map: troop_code (int) → count (int)
     * @throws \RuntimeException
     */
    private static function validateAndCleanTroops(array $troops, int $maxCap): array
    {
        if (empty($troops)) {
            throw new \RuntimeException('Mindestens eine Truppeneinheit muss ausgewählt werden.');
        }

        $clean = [];
        $total = 0;

        foreach ($troops as $code => $count) {
            $count = (int) $count;
            if ($count <= 0) {
                continue;
            }

            $intCode = (int) $code;

            if (TroopData::get($intCode) === null) {
                throw new \RuntimeException('Unbekannter Truppen-Code: ' . $code);
            }

            $clean[$intCode] = $count;
            $total          += $count;
        }

        if ($total <= 0) {
            throw new \RuntimeException('Mindestens eine Truppeneinheit muss ausgewählt werden.');
        }

        if ($total > $maxCap) {
            throw new \RuntimeException('Maximal ' . number_format($maxCap) . ' Truppen pro Rally.');
        }

        return $clean;
    }

    /**
     * Verifies that a city has enough troops for the requested amounts.
     *
     * @param array<int,int> $troops  troop_code → count
     * @throws \RuntimeException if any troop type is insufficient
     */
    private static function assertTroopsAvailable(Connection $db, int $cityId, array $troops): void
    {
        $rows = $db->query(
            'SELECT troop_code, count FROM city_troops WHERE city_id = ?',
            [$cityId],
        )->fetchAll();

        $available = [];
        foreach ($rows as $r) {
            $available[(int) $r['troop_code']] = (int) $r['count'];
        }

        foreach ($troops as $code => $count) {
            if (($available[$code] ?? 0) < $count) {
                $def  = TroopData::get($code);
                $name = $def['name'] ?? ('Code ' . $code);
                throw new \RuntimeException(
                    'Nicht genug ' . $name . '. Vorhanden: ' .
                    ($available[$code] ?? 0) . ', benötigt: ' . $count . '.'
                );
            }
        }
    }

    /**
     * Deducts troops from city_troops within an already-open transaction.
     *
     * @param array<int,int> $troops  troop_code → count
     */
    private static function deductTroops(Connection $db, int $cityId, array $troops): void
    {
        foreach ($troops as $code => $count) {
            $db->execute(
                'UPDATE city_troops SET count = count - ? WHERE city_id = ? AND troop_code = ?',
                [$count, $cityId, $code],
            );
        }
    }

    /**
     * Returns the alliance_id for a player, or null if not in an alliance.
     */
    private static function getAllianceId(Connection $db, int $playerId): ?int
    {
        $row = $db->query(
            'SELECT alliance_id FROM alliance_members WHERE player_id = ?',
            [$playerId],
        )->fetch();

        return ($row !== false) ? (int) $row['alliance_id'] : null;
    }

    /**
     * Collects all unique troop codes used by the leader and all participants.
     *
     * @return list<int>  distinct troop codes
     */
    private static function collectAllTroopCodes(array $rally, Connection $db, int $rallyId): array
    {
        $codes = [];

        // Leader troops
        $leaderTroops = json_decode($rally['troops_json'] ?? '{}', true);
        if (is_array($leaderTroops)) {
            foreach (array_keys($leaderTroops) as $code) {
                $codes[(int) $code] = true;
            }
        }

        // Participant troops
        $participants = $db->query(
            'SELECT troops_json FROM rally_participants WHERE rally_id = ? AND status = "pending"',
            [$rallyId],
        )->fetchAll();

        foreach ($participants as $p) {
            $pTroops = json_decode($p['troops_json'] ?? '{}', true);
            if (is_array($pTroops)) {
                foreach (array_keys($pTroops) as $code) {
                    $codes[(int) $code] = true;
                }
            }
        }

        return array_keys($codes);
    }
}
