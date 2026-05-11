<?php
declare(strict_types=1);

namespace Conquer\Game\Alliance;

use Conquer\Db\Connection;
use Conquer\Logger;

/**
 * Manages the alliance treasury (communal resource pool).
 *
 * Every alliance has exactly one treasury row. Members can donate resources
 * from their city into the shared pool. Donations are logged in
 * alliance_donations for history and contribution tracking.
 *
 * DB tables:
 *   alliance_treasury   — one row per alliance, holds food/lumber/stone/gold
 *   alliance_donations  — append-only donation log
 *   cities              — deducted when a player donates
 */
final class TreasuryService
{
    private function __construct() {}

    // ─────────────────────────────────────────────────────────────────────────
    // Balance
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Returns the current treasury balance for the given alliance.
     * Inserts a zero-balance row if none exists yet (upsert on first call).
     *
     * @return array{food: int, lumber: int, stone: int, gold: int}
     */
    public static function getBalance(int $allianceId): array
    {
        $db = Connection::getInstance();

        try {
            $row = $db->query(
                'SELECT food, lumber, stone, gold
                 FROM   alliance_treasury
                 WHERE  alliance_id = ?',
                [$allianceId],
            )->fetch();
        } catch (\PDOException $e) {
            self::log()->error('[TreasuryService] getBalance SELECT failed: ' . $e->getMessage());
            return self::zeroBalance();
        }

        if ($row !== false) {
            return [
                'food'   => (int) $row['food'],
                'lumber' => (int) $row['lumber'],
                'stone'  => (int) $row['stone'],
                'gold'   => (int) $row['gold'],
            ];
        }

        // First access — insert zero row and return zeros
        try {
            $db->execute(
                'INSERT IGNORE INTO alliance_treasury (alliance_id, food, lumber, stone, gold)
                 VALUES (?, 0, 0, 0, 0)',
                [$allianceId],
            );
        } catch (\PDOException $e) {
            self::log()->error('[TreasuryService] getBalance INSERT failed: ' . $e->getMessage());
        }

        return self::zeroBalance();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Donations
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Donates resources from a player's city to the alliance treasury.
     *
     * The donation amounts are clamped to what the city actually has —
     * negative amounts are rejected immediately.
     *
     * All DB mutations run in a single transaction:
     *   1. Deduct from cities (GREATEST guard prevents going below 0).
     *   2. Add to alliance_treasury (INSERT … ON DUPLICATE KEY UPDATE).
     *   3. Append to alliance_donations log.
     *
     * @throws \RuntimeException when amounts are invalid or city has insufficient resources
     */
    public static function donate(
        int $allianceId,
        int $playerId,
        int $cityId,
        int $food,
        int $lumber,
        int $stone,
        int $gold,
    ): bool {
        // ── Basic validation ──────────────────────────────────────────────────
        if ($food < 0 || $lumber < 0 || $stone < 0 || $gold < 0) {
            throw new \RuntimeException('Negativer Spendebetrag ist nicht erlaubt.');
        }

        $total = $food + $lumber + $stone + $gold;
        if ($total === 0) {
            throw new \RuntimeException('Mindestens eine Ressource muss gespendet werden.');
        }

        $db = Connection::getInstance();

        // ── Verify player has enough resources ────────────────────────────────
        try {
            $city = $db->query(
                'SELECT food, wood AS lumber, stone, gold
                 FROM   cities
                 WHERE  id = ? AND player_id = ?',
                [$cityId, $playerId],
            )->fetch();
        } catch (\PDOException $e) {
            self::log()->error('[TreasuryService] donate city load failed: ' . $e->getMessage());
            throw new \RuntimeException('Stadt konnte nicht geladen werden.');
        }

        if ($city === false) {
            throw new \RuntimeException('Stadt nicht gefunden.');
        }

        if ((int) $city['lumber'] < $lumber) {
            throw new \RuntimeException('Nicht genug Holz in der Stadt. Vorhanden: ' . $city['lumber'] . ', benötigt: ' . $lumber . '.');
        }
        if ((int) $city['food'] < $food) {
            throw new \RuntimeException('Nicht genug Nahrung in der Stadt. Vorhanden: ' . $city['food'] . ', benötigt: ' . $food . '.');
        }
        if ((int) $city['stone'] < $stone) {
            throw new \RuntimeException('Nicht genug Stein in der Stadt. Vorhanden: ' . $city['stone'] . ', benötigt: ' . $stone . '.');
        }
        if ((int) $city['gold'] < $gold) {
            throw new \RuntimeException('Nicht genug Gold in der Stadt. Vorhanden: ' . $city['gold'] . ', benötigt: ' . $gold . '.');
        }

        // ── Transaction ───────────────────────────────────────────────────────
        try {
            $db->transaction(function () use (
                $db,
                $allianceId,
                $playerId,
                $cityId,
                $food,
                $lumber,
                $stone,
                $gold,
            ): void {
                // Deduct from city (GREATEST guard — never go negative)
                $db->execute(
                    'UPDATE cities
                     SET food  = GREATEST(0, food  - :f),
                         wood  = GREATEST(0, wood  - :l),
                         stone = GREATEST(0, stone - :s),
                         gold  = GREATEST(0, gold  - :g)
                     WHERE id  = :id',
                    [
                        ':f'  => $food,
                        ':l'  => $lumber,
                        ':s'  => $stone,
                        ':g'  => $gold,
                        ':id' => $cityId,
                    ],
                );

                // Add to treasury
                $db->execute(
                    'INSERT INTO alliance_treasury (alliance_id, food, lumber, stone, gold)
                     VALUES (:aid, :f, :l, :s, :g)
                     ON DUPLICATE KEY UPDATE
                        food   = food   + VALUES(food),
                        lumber = lumber + VALUES(lumber),
                        stone  = stone  + VALUES(stone),
                        gold   = gold   + VALUES(gold)',
                    [
                        ':aid' => $allianceId,
                        ':f'   => $food,
                        ':l'   => $lumber,
                        ':s'   => $stone,
                        ':g'   => $gold,
                    ],
                );

                // Append to donation log
                $db->execute(
                    'INSERT INTO alliance_donations
                        (alliance_id, player_id, food, lumber, stone, gold, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
                    [$allianceId, $playerId, $food, $lumber, $stone, $gold],
                );
            });
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\PDOException $e) {
            self::log()->error('[TreasuryService] donate transaction failed: ' . $e->getMessage());
            throw new \RuntimeException('Spende konnte nicht verarbeitet werden.');
        }

        self::log()->info(sprintf(
            '[TreasuryService] Player %d donated to alliance %d — food:%d lumber:%d stone:%d gold:%d',
            $playerId,
            $allianceId,
            $food,
            $lumber,
            $stone,
            $gold,
        ));

        return true;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Donation history
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Returns the most recent donations to the alliance treasury.
     *
     * @return list<array{player_id: int, player_name: string, food: int, lumber: int, stone: int, gold: int, created_at: string}>
     */
    public static function getDonationHistory(int $allianceId, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $db    = Connection::getInstance();

        try {
            return $db->query(
                'SELECT  d.player_id,
                         p.username AS player_name,
                         d.food,
                         d.lumber,
                         d.stone,
                         d.gold,
                         d.created_at
                 FROM    alliance_donations d
                 JOIN    players            p ON p.id = d.player_id
                 WHERE   d.alliance_id = ?
                 ORDER   BY d.created_at DESC
                 LIMIT   ?',
                [$allianceId, $limit],
            )->fetchAll();
        } catch (\PDOException $e) {
            self::log()->error('[TreasuryService] getDonationHistory failed: ' . $e->getMessage());
            return [];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Spend (for internal Alliance systems)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Deducts resources directly from the treasury (e.g. for alliance research costs).
     * Does NOT log a donation record — this is a spend, not a donation.
     *
     * @throws \RuntimeException when the treasury has insufficient resources.
     */
    public static function spend(
        int $allianceId,
        int $food,
        int $lumber,
        int $stone,
        int $gold,
    ): void {
        if ($food < 0 || $lumber < 0 || $stone < 0 || $gold < 0) {
            throw new \RuntimeException('Negative Ausgabe ist nicht erlaubt.');
        }

        $db      = Connection::getInstance();
        $balance = self::getBalance($allianceId);

        if ($balance['food']   < $food)   throw new \RuntimeException("Zu wenig Nahrung im Schatzamt. Vorhanden: {$balance['food']}, benötigt: {$food}.");
        if ($balance['lumber'] < $lumber) throw new \RuntimeException("Zu wenig Holz im Schatzamt. Vorhanden: {$balance['lumber']}, benötigt: {$lumber}.");
        if ($balance['stone']  < $stone)  throw new \RuntimeException("Zu wenig Stein im Schatzamt. Vorhanden: {$balance['stone']}, benötigt: {$stone}.");
        if ($balance['gold']   < $gold)   throw new \RuntimeException("Zu wenig Gold im Schatzamt. Vorhanden: {$balance['gold']}, benötigt: {$gold}.");

        $db->execute(
            'UPDATE alliance_treasury
             SET food   = GREATEST(0, food   - :f),
                 lumber = GREATEST(0, lumber - :l),
                 stone  = GREATEST(0, stone  - :s),
                 gold   = GREATEST(0, gold   - :g)
             WHERE alliance_id = :aid',
            [':f' => $food, ':l' => $lumber, ':s' => $stone, ':g' => $gold, ':aid' => $allianceId],
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /** @return array{food: int, lumber: int, stone: int, gold: int} */
    private static function zeroBalance(): array
    {
        return ['food' => 0, 'lumber' => 0, 'stone' => 0, 'gold' => 0];
    }

    private static function log(): Logger
    {
        return Logger::getInstance();
    }
}
