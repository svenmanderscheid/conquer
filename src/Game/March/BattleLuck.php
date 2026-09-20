<?php
declare(strict_types=1);
namespace Conquer\Game\March;

/** Server-side luck roll shared by monster and player combat. */
final class BattleLuck
{
    public const MIN_TENTHS = -100;
    public const MAX_TENTHS = 100;

    private function __construct() {}

    /** Luck in percent, in 0.1 percentage-point steps. */
    public static function roll(): float
    {
        return random_int(self::MIN_TENTHS, self::MAX_TENTHS) / 10;
    }

    public static function factor(float $percent): float
    {
        return 1 + max(-10.0, min(10.0, $percent)) / 100;
    }
}
