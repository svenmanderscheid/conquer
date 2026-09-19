<?php
declare(strict_types=1);
namespace Conquer\Game\Premium;

use Conquer\Bootstrap;
use Conquer\Db\Connection;
use Conquer\Game\March\MarchSkinService;

/** Development-only cosmetic entitlements for an explicitly configured local server. */
final class LocalCosmeticEntitlements
{
    /** @var array<string,true> */
    private static array $synced = [];

    public static function sync(int $playerId, ?array $appConfig = null, ?array $databaseConfig = null): bool
    {
        $appConfig ??= Bootstrap::getConfig();
        if (!self::isEnabled($appConfig, $databaseConfig)) return false;

        $db = Connection::getInstance();
        $activeDatabase = (string)$db->query('SELECT DATABASE()')->fetchColumn();
        if (!hash_equals((string)$appConfig['local_free_skins_database'], $activeDatabase)) return false;
        $syncKey = spl_object_id($db->getPdo()) . ':' . $playerId;
        if (isset(self::$synced[$syncKey])) return true;

        $marchSkins = array_keys(MarchSkinService::catalog());
        $premiumSkins = array_values(array_filter($marchSkins, static fn(string $id): bool => $id !== 'default'));
        self::insertAll($db, 'player_march_skins', 'skin_code', $playerId, $marchSkins);
        self::insertAll($db, 'player_castle_skins', 'skin_code', $playerId, $premiumSkins);
        self::insertAll($db, 'player_name_frames', 'frame_code', $playerId, $premiumSkins);
        self::$synced[$syncKey] = true;
        return true;
    }

    public static function isEnabled(array $appConfig, ?array $databaseConfig = null): bool
    {
        if (($appConfig['env'] ?? null) !== 'development' || ($appConfig['local_free_skins'] ?? false) !== true) {
            return false;
        }
        $allowedDatabase = $appConfig['local_free_skins_database'] ?? null;
        if (!is_string($allowedDatabase) || $allowedDatabase === '') return false;
        if ($databaseConfig === null) {
            $file = ROOT_DIR . '/config/database.php';
            $databaseConfig = is_file($file) ? require $file : [];
        }
        return is_array($databaseConfig)
            && in_array(strtolower((string) ($databaseConfig['host'] ?? '')), ['localhost','127.0.0.1','::1'], true)
            && hash_equals($allowedDatabase, (string)($databaseConfig['database'] ?? ''));
    }

    /** @param list<string> $ids */
    private static function insertAll(Connection $db, string $table, string $column, int $playerId, array $ids): void
    {
        $values = implode(',', array_fill(0, count($ids), '(?,?)'));
        $params = [];
        foreach ($ids as $id) array_push($params, $playerId, $id);
        $db->execute("INSERT IGNORE INTO {$table}(player_id,{$column}) VALUES {$values}", $params);
    }
}
