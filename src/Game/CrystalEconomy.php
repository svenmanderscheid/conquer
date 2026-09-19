<?php
declare(strict_types=1);
namespace Conquer\Game;

/** Central crystal-spending rules. VIP catalogue offers are explicitly allowed. */
final class CrystalEconomy
{
    public const RESTRICTION = 'Dieser Gegenstand kann außerhalb des VIP-Shops nicht mit Kristallen gekauft werden.';

    public static function allowsItem(?array $item): bool
    {
        return ($item['category'] ?? '') === 'vip_point';
    }

    public static function requireItem(?array $item): void
    {
        if (!self::allowsItem($item)) self::reject();
    }

    /** VIP offers are server-owned catalogue entries and may sell any defined item. */
    public static function requireVipShopItem(?array $item): void
    {
        if ($item === null || !isset($item['code'])) self::reject();
    }

    public static function reject(): never
    {
        throw new \DomainException(self::RESTRICTION);
    }
}
