<?php
declare(strict_types=1);
namespace Conquer\Game\World;

use Conquer\Db\Connection;
use Conquer\Game\Map\WorldPlacement;

/** Server-owned first-world routing and compact, collision-safe alpha entry. */
final class WorldEntry
{
    public static function settings(int $worldId): ?array
    {
        try { return Connection::getInstance()->query('SELECT * FROM world_entry_settings WHERE world_id=?',[$worldId])->fetch() ?: null; }
        catch (\PDOException $e) { if ((int)($e->errorInfo[1]??0)!==1146) throw $e; return null; }
    }

    public static function defaultWorld(): int
    {
        $db=Connection::getInstance();
        try {
            $world=$db->query("SELECT w.id FROM worlds w JOIN world_entry_settings e ON e.world_id=w.id WHERE e.default_slot=1 AND w.status IN ('open','running')")->fetchColumn();
            if ($world) return (int)$world;
            // A configured alpha never silently sends registrants into an older world.
            if ($db->query('SELECT 1 FROM world_entry_settings WHERE default_slot=1')->fetchColumn())
                throw new \DomainException(\Conquer\Game\Locale::t('alpha.error.closed'));
        } catch (\PDOException $e) { if ((int)($e->errorInfo[1]??0)!==1146) throw $e; }
        return (int)($db->query("SELECT id FROM worlds WHERE status IN ('open','running') ORDER BY id LIMIT 1")->fetchColumn() ?: 0);
    }

    /** Caller owns the world placement lock; never scatter players when the region is full. */
    public static function position(Connection $db,int $worldId,?int $ignoreCityId=null,?int $now=null): ?array
    {
        $entry=self::settings($worldId);
        if (!$entry || $entry['spawn_x']===null || $entry['spawn_y']===null) return null;
        // Deadlines are exclusive UTC instants; null keeps permanent entry regions.
        $until=$entry['spawn_until']??null;
        if ($until!==null && ($now??time())>=strtotime($until.' UTC')) return null;
        $x=(int)$entry['spawn_x']; $y=(int)$entry['spawn_y']; $radius=(int)$entry['spawn_radius'];
        for ($r=0;$r<=$radius;$r++) for ($dy=-$r;$dy<=$r;$dy++) {
            foreach (abs($dy)===$r?range(-$r,$r):[-$r,$r] as $dx) {
                $tx=$x+$dx; $ty=$y+$dy;
                if ($entry['spawn_canton']!==null && (LuxembourgGeography::at($tx,$ty)['canton_id']??null)!==$entry['spawn_canton']) continue;
                if (WorldPlacement::canPlace($db,$worldId,'city',$tx,$ty,$ignoreCityId)) return ['x'=>$tx,'y'=>$ty];
            }
        }
        throw new \DomainException(\Conquer\Game\Locale::t('alpha.error.full'));
    }
}
