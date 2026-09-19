<?php
declare(strict_types=1);
namespace Conquer\Game\Map;

/** The source identifies a family and level separately; local object_type stays stable. */
final class FieldObjectData
{
    public static function source(int $code, int $level): ?array
    {
        static $rows = null;
        if ($rows === null) {
            $rows = [];
            $data = json_decode((string)file_get_contents(ROOT_DIR.'/data/field_objects.json'), true, 512, JSON_THROW_ON_ERROR);
            foreach ($data['objects'] as $row) $rows[$row['code']][$row['level']] = $row;
        }
        return $rows[$code][$level] ?? null;
    }

    public static function get(int $type, int $level): array
    {
        if ($type < 1 || $type > 5) throw new \InvalidArgumentException('Unbekannter Ressourcenfeldtyp.');
        return self::source(20100100 + $type, $level) ?? throw new \InvalidArgumentException('Unbekannte Ressourcenfeldstufe.');
    }

    public static function capacity(int $type, int $level): int
    {
        return (int)self::get($type, $level)['production'];
    }

    public static function hourlyRate(string $resource, int $level): float
    {
        $type = array_search($resource, FieldObjectService::RESOURCE_BY_TYPE, true);
        if ($type === false) throw new \InvalidArgumentException('Unbekannter Rohstoff.');
        return (float)self::get($type, $level)['gathering'];
    }
}
