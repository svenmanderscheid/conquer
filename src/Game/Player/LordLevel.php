<?php
declare(strict_types=1);
namespace Conquer\Game\Player;

use Conquer\Db\Connection;
use Conquer\Game\World\WorldContext;

/** Lord and hunting progression are the same, scoped to the active world. */
final class LordLevel
{
    public const MAX_LEVEL = 60;

    public static function xpForLevel(int $level): int
    {
        if ($level <= 1 || $level > self::MAX_LEVEL) return 0;
        // Preserve cumulative legacy thresholds 2–50. Level 1 now starts at zero.
        if ($level === 2) return 250;
        if ($level > 50) return (int) ceil(self::legacyStep(50) * pow(1.08, $level - 50));
        return self::legacyStep($level);
    }

    private static function legacyStep(int $level): int
    {
        if ($level === 50) return array_sum(array_map(self::legacyStep(...), range(41,49)));
        $position = ($level - 1) % 9 + 1;
        return $position === 9 ? 10200 : 50 * $position * $position;
    }

    public static function totalForLevel(int $level): int
    {
        $total=0;
        for ($i=2;$i<=min(self::MAX_LEVEL,$level);$i++) $total+=self::xpForLevel($i);
        return $total;
    }

    public static function levelFromTotalXp(int $totalXp): int
    {
        $level=1; $threshold=0;
        for ($i=2;$i<=self::MAX_LEVEL;$i++) {
            $threshold+=self::xpForLevel($i);
            if ($totalXp<$threshold) break;
            $level=$i;
        }
        return $level;
    }

    public static function xpIntoCurrentLevel(int $xp): int
    {
        return self::levelFromTotalXp($xp)===self::MAX_LEVEL ? 0 : max(0,$xp-self::totalForLevel(self::levelFromTotalXp($xp)));
    }

    public static function xpForNextLevel(int $level): int { return self::xpForLevel($level+1); }

    public static function ensure(int $playerId,int $worldId): void
    {
        Connection::getInstance()->execute('INSERT IGNORE INTO player_lord_progress(player_id,world_id) SELECT player_id,world_id FROM cities WHERE player_id=? AND world_id=?',[$playerId,$worldId]);
    }

    public static function snapshot(int $playerId,?int $worldId=null): array
    {
        $worldId??=WorldContext::id();
        $xp=(int)Connection::getInstance()->query('SELECT xp FROM player_lord_progress WHERE player_id=? AND world_id=?',[$playerId,$worldId])->fetchColumn();
        $level=self::levelFromTotalXp($xp);
        return ['level'=>$level,'max_level'=>self::MAX_LEVEL,'xp'=>$xp,'xp_into_level'=>self::xpIntoCurrentLevel($xp),'xp_next'=>self::xpForNextLevel($level),'world_id'=>$worldId];
    }

    /** A settlement source is unique per world; nested transactions are supported. */
    public static function addXp(int $playerId,int $xp,?int $worldId=null,?string $source=null): int
    {
        if ($xp<=0) return 0;
        $worldId??=WorldContext::id();
        return TalentEffects::atomic(static function(Connection $db) use($playerId,$xp,$worldId,$source): int {
            WorldContext::city($playerId,$worldId,true);
            self::ensure($playerId,$worldId);
            if ($source!==null) {
                $exists=$db->query('SELECT xp FROM lord_xp_receipts WHERE player_id=? AND world_id=? AND source=?',[$playerId,$worldId,$source])->fetch();
                if ($exists) return 0;
            }
            // Imported XP above cap stays intact; no further XP is awarded at cap.
            $before=(int)$db->query('SELECT xp FROM player_lord_progress WHERE player_id=? AND world_id=? FOR UPDATE',[$playerId,$worldId])->fetchColumn();
            $credited=max(0,min($xp,self::totalForLevel(60)-$before));
            $db->execute('UPDATE player_lord_progress SET xp=xp+? WHERE player_id=? AND world_id=?',[$credited,$playerId,$worldId]);
            if($source!==null)$db->execute('INSERT INTO lord_xp_receipts(player_id,world_id,source,xp) VALUES(?,?,?,?)',[$playerId,$worldId,$source,$credited]);
            return $credited;
        });
    }
}
