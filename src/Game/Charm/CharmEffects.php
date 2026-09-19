<?php
declare(strict_types=1);
namespace Conquer\Game\Charm;

/** Keep saved map-charm categories compatible with the current bonus consumers. */
final class CharmEffects
{
    public const KEYS = [
        'construction'=>'construction_speed', 'research'=>'research_speed',
        'troops_hp'=>'troops_hp', 'troops_attack'=>'troops_atk',
        'troops_defense'=>'troops_def', 'carry'=>'troops_storage',
        'march_speed'=>'march_speed', 'gathering'=>'gathering_speed',
    ];

    public static function key(string $category): string
    {
        return self::KEYS[$category] ?? $category;
    }
}
