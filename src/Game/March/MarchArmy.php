<?php
declare(strict_types=1);

namespace Conquer\Game\March;

use Conquer\Db\Connection;
use Conquer\Game\City\TroopData;

/** Shared composition rules for the monster and gathering army selectors. */
final class MarchArmy
{
    public const MAX_CAP = 50_000;

    /** @return array<int,int> Troop code => count, preserving the chosen types. */
    public static function clean(mixed $input, int $limit = self::MAX_CAP): array
    {
        if (!is_array($input) || count($input) > count(TroopData::all())) {
            throw new \RuntimeException('Wähle eine gültige Truppenzusammenstellung.');
        }
        $clean = [];
        foreach ($input as $code => $count) {
            if (!preg_match('/^[1-9][0-9]*$/D', (string) $code) || TroopData::get((int) $code) === null
                || !is_int($count) || $count < 0 || $count > $limit) {
                throw new \RuntimeException('Truppentypen und nichtnegative ganze Truppenanzahlen sind erforderlich.');
            }
            if ($count > 0) { $clean[(int) $code] = $count; }
        }
        if (!$clean) { throw new \RuntimeException('Mindestens 1 Truppe muss ausgewählt werden.'); }
        if (array_sum($clean) > $limit) { throw new \RuntimeException('Maximal '.number_format($limit,0,',','.').' Truppen pro Marsch.'); }
        ksort($clean);
        return $clean;
    }

    /** Caller supplies a transaction; partial reservations roll back together. */
    public static function reserve(Connection $db, int $cityId, array $troops): void
    {
        foreach ($troops as $code => $count) {
            if ($db->execute('UPDATE city_troops SET count=count-? WHERE city_id=? AND troop_code=? AND count>=?', [$count, $cityId, $code, $count]) !== 1) {
                throw new \RuntimeException('Nicht genügend verfügbare Truppen dieses Typs. Prüfe die Armeen, die bereits unterwegs sind.');
            }
        }
    }
}
