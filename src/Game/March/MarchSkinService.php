<?php
declare(strict_types=1);
namespace Conquer\Game\March;

use Conquer\Db\Connection;
use Conquer\Game\Premium\LocalCosmeticEntitlements;

/** Server-owned march cosmetics, ownership and immutable dispatch bonuses. */
final class MarchSkinService
{
    private static ?array $catalog = null;

    /** @return array<string,array<string,mixed>> */
    public static function catalog(): array
    {
        if (self::$catalog !== null) return self::$catalog;
        $path = ROOT_DIR . '/data/march_skins.json';
        try { $data = json_decode((string) file_get_contents($path), true, 32, JSON_THROW_ON_ERROR); }
        catch (\Throwable $e) { throw new \RuntimeException('Marsch-Skin-Katalog kann nicht geladen werden.', 0, $e); }
        if (!is_array($data['entries'] ?? null) || $data['entries'] === []) {
            throw new \RuntimeException('Marsch-Skin-Katalog ist unvollständig.');
        }
        $catalog = [];
        foreach ($data['entries'] as $entry) {
            if (!is_array($entry) || !preg_match('/^[a-z0-9_]{1,20}$/D', (string) ($entry['id'] ?? ''))) {
                throw new \RuntimeException('Marsch-Skin-Katalog enthält eine ungültige ID.');
            }
            if (isset($catalog[$entry['id']])) throw new \RuntimeException('Marsch-Skin-Katalog enthält doppelte IDs.');
            $entry['price_gems'] = max(0, (int) ($entry['price_gems'] ?? 0));
            $entry['bonus_pct'] = max(0, min(100, (int) ($entry['bonus_pct'] ?? 0)));
            $entry['unlock_castle_level'] = isset($entry['unlock_castle_level']) ? (int) $entry['unlock_castle_level'] : null;
            $catalog[$entry['id']] = $entry;
        }
        return self::$catalog = $catalog;
    }

    public static function state(int $playerId): array
    {
        LocalCosmeticEntitlements::sync($playerId);
        $db = Connection::getInstance();
        $profile = $db->query('SELECT march_skin FROM kingdom_profiles WHERE player_id=?', [$playerId])->fetch();
        $equipped = is_string($profile['march_skin'] ?? null) ? $profile['march_skin'] : null;
        $owned = array_fill_keys($db->query('SELECT skin_code FROM player_march_skins WHERE player_id=?', [$playerId])->fetchAll(\PDO::FETCH_COLUMN), true);
        if ($equipped !== null && (!isset($owned[$equipped]) || !isset(self::catalog()[$equipped]))) $equipped = null;
        $castleLevel = (int) $db->query('SELECT COALESCE(MAX(castle_level),0) FROM cities WHERE player_id=?', [$playerId])->fetchColumn();
        $entries = [];
        foreach (self::catalog() as $id => $entry) {
            $entry['owned'] = isset($owned[$id]);
            $entry['equipped'] = $equipped === $id;
            $entry['can_claim'] = !$entry['owned'] && $entry['price_gems'] === 0
                && $entry['unlock_castle_level'] !== null && $castleLevel >= $entry['unlock_castle_level'];
            $entries[] = $entry;
        }
        return ['entries'=>$entries, 'equipped'=>$equipped, 'bonus_pct'=>$equipped === null ? 0 : (int) self::catalog()[$equipped]['bonus_pct']];
    }

    public static function claim(int $playerId, mixed $skinId): array
    {
        LocalCosmeticEntitlements::sync($playerId);
        $skin = self::definition($skinId);
        if ((int) $skin['price_gems'] !== 0 || $skin['unlock_castle_level'] === null) {
            throw new \DomainException('Dieser Marsch-Skin kann nicht kostenlos freigeschaltet werden.');
        }
        $db = Connection::getInstance();
        $level = (int) ($db->query('SELECT castle_level FROM cities WHERE player_id=? ORDER BY castle_level DESC LIMIT 1 FOR UPDATE', [$playerId])->fetchColumn() ?: 0);
        if ($level < (int) $skin['unlock_castle_level']) {
            throw new \DomainException('Erreiche zuerst Burgstufe ' . $skin['unlock_castle_level'] . '.');
        }
        $created = $db->execute('INSERT IGNORE INTO player_march_skins(player_id,skin_code) VALUES(?,?)', [$playerId,$skin['id']]) === 1;
        return ['message'=>$created ? $skin['name'].' wurde freigeschaltet.' : $skin['name'].' ist bereits freigeschaltet.', 'march_skin'=>$skin['id'], 'charged_gems'=>0];
    }

    public static function buy(int $playerId, mixed $skinId): array
    {
        LocalCosmeticEntitlements::sync($playerId);
        $skin = self::definition($skinId);
        $price = (int) $skin['price_gems'];
        if ($price < 1) throw new \DomainException('Dieser Marsch-Skin wird durch Spielfortschritt freigeschaltet.');
        $db = Connection::getInstance();
        $player = $db->query('SELECT gems FROM players WHERE id=? FOR UPDATE', [$playerId])->fetch();
        if (!$player) throw new \DomainException('Spieler nicht gefunden.');
        if ($db->query('SELECT 1 FROM player_march_skins WHERE player_id=? AND skin_code=? FOR UPDATE', [$playerId,$skin['id']])->fetchColumn() !== false) {
            return ['message'=>$skin['name'].' gehört dir bereits.', 'march_skin'=>$skin['id'], 'charged_gems'=>0];
        }
        if ((int) $player['gems'] < $price) throw new \DomainException('Nicht genug Juwelen für diesen Marsch-Skin.');
        if ($db->execute('UPDATE players SET gems=gems-? WHERE id=? AND gems>=?', [$price,$playerId,$price]) !== 1) {
            throw new \DomainException('Dein Juwelenstand hat sich geändert. Bitte versuche es erneut.');
        }
        $db->execute('INSERT INTO player_march_skins(player_id,skin_code) VALUES(?,?)', [$playerId,$skin['id']]);
        return ['message'=>$skin['name'].' wurde gekauft.', 'march_skin'=>$skin['id'], 'charged_gems'=>$price];
    }

    public static function equip(int $playerId, mixed $skinId): array
    {
        LocalCosmeticEntitlements::sync($playerId);
        $skin = self::definition($skinId);
        $db = Connection::getInstance();
        if ($db->query('SELECT 1 FROM player_march_skins WHERE player_id=? AND skin_code=?', [$playerId,$skin['id']])->fetchColumn() === false) {
            throw new \DomainException('Dieser Marsch-Skin gehört dir noch nicht.');
        }
        $db->execute('UPDATE kingdom_profiles SET march_skin=? WHERE player_id=?', [$skin['id'],$playerId]);
        return ['message'=>$skin['name'].' wurde für neue Märsche angelegt.', 'march_skin'=>$skin['id'], 'bonus_pct'=>(int)$skin['bonus_pct']];
    }

    /** Locks the profile row when called in a dispatch transaction. */
    public static function dispatchSnapshot(int $playerId): array
    {
        LocalCosmeticEntitlements::sync($playerId);
        $db = Connection::getInstance();
        $db->execute('INSERT IGNORE INTO kingdom_profiles(player_id,display_name) SELECT id,username FROM players WHERE id=?', [$playerId]);
        $profile = $db->query('SELECT march_skin FROM kingdom_profiles WHERE player_id=? FOR UPDATE', [$playerId])->fetch();
        $id = is_string($profile['march_skin'] ?? null) ? $profile['march_skin'] : null;
        if ($id === null || !isset(self::catalog()[$id]) || $db->query('SELECT 1 FROM player_march_skins WHERE player_id=? AND skin_code=?', [$playerId,$id])->fetchColumn() === false) {
            return ['march_skin'=>null, 'bonus_pct'=>0];
        }
        return ['march_skin'=>$id, 'bonus_pct'=>(int)self::catalog()[$id]['bonus_pct']];
    }

    /** Read-only counterpart used by ETA previews. */
    public static function currentSnapshot(int $playerId): array
    {
        LocalCosmeticEntitlements::sync($playerId);
        $db = Connection::getInstance();
        $id = $db->query('SELECT march_skin FROM kingdom_profiles WHERE player_id=?', [$playerId])->fetchColumn();
        if (!is_string($id) || !isset(self::catalog()[$id]) || $db->query('SELECT 1 FROM player_march_skins WHERE player_id=? AND skin_code=?', [$playerId,$id])->fetchColumn() === false) {
            return ['march_skin'=>null, 'bonus_pct'=>0];
        }
        return ['march_skin'=>$id, 'bonus_pct'=>(int)self::catalog()[$id]['bonus_pct']];
    }

    public static function speedMultiplier(array $snapshot): float
    {
        return 1.0 + max(0, min(100, (int) ($snapshot['bonus_pct'] ?? 0))) / 100;
    }

    private static function definition(mixed $skinId): array
    {
        if (!is_string($skinId) || !preg_match('/^[a-z0-9_]{1,20}$/D', $skinId) || !isset(self::catalog()[$skinId])) {
            throw new \DomainException('Wähle einen verfügbaren Marsch-Skin.');
        }
        return self::catalog()[$skinId];
    }
}
