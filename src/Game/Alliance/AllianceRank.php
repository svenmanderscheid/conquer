<?php
declare(strict_types=1);
namespace Conquer\Game\Alliance;

/** The existing role enum is authoritative; numeric ranks are derived from it. */
final class AllianceRank
{
    public const LEVELS = ['member'=>1, 'veteran'=>2, 'officer'=>3, 'vice_leader'=>4, 'leader'=>5];

    private function __construct() {}

    public static function level(string $role): int
    {
        return self::LEVELS[$role] ?? 0;
    }

    /** Ordinary rank changes cannot affect oneself, peers or the alliance leader. */
    public static function assignableRoles(string $actor, string $target, bool $self = false): array
    {
        $actorLevel = self::level($actor);
        $targetLevel = self::level($target);
        if ($self || $actorLevel < 4 || $targetLevel < 1 || $targetLevel >= $actorLevel) return [];
        return array_keys(array_filter(self::LEVELS, static fn(int $level): bool => $level < $actorLevel));
    }
}
