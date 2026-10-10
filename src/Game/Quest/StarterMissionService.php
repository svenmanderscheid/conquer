<?php
declare(strict_types=1);
namespace Conquer\Game\Quest;

use Conquer\Db\Connection;
use Conquer\Game\City\TroopData;
use Conquer\Game\Locale;
use Conquer\Game\World\WorldContext;

/** Permanent per-world milestones. Progress comes only from completed server state. */
final class StarterMissionService
{
    private const MISSIONS = [
        'starter_barrack_2'=>['building'=>'barrack','target'=>2,'reward'=>20000],
        'starter_castle_2'=>['building'=>'castle','target'=>2,'reward'=>25000],
        'starter_storage_2'=>['building'=>'storage','target'=>2,'reward'=>15000],
        'starter_train_100'=>['target'=>100,'reward'=>20000],
        'starter_train_500'=>['target'=>500,'reward'=>40000],
        'starter_train_tier2'=>['target'=>20,'tier'=>2,'reward'=>30000],
        'starter_promote_20'=>['target'=>20,'promotion'=>true,'reward'=>30000],
    ];

    public static function quests(int $playerId): array
    {
        $db=Connection::getInstance();
        // Older installations remain readable until the additive migration is applied.
        try { $claimed=$db->query('SELECT quest_code FROM player_starter_missions WHERE player_id=? AND world_id=?',[$playerId,WorldContext::id()])->fetchAll(\PDO::FETCH_COLUMN); }
        catch (\PDOException $e) { if ((int)($e->errorInfo[1]??0)!==1146) throw $e; return []; }
        $city=WorldContext::city($playerId);
        $levels=$db->query('SELECT building_code,level FROM city_buildings WHERE city_id=?',[$city['id']])->fetchAll(\PDO::FETCH_KEY_PAIR);
        $trained=$db->query('SELECT troop_code,SUM(count) AS total FROM troop_queue WHERE city_id=? AND is_processed=1 GROUP BY troop_code',[$city['id']])->fetchAll();
        $promoted=(int)$db->query("SELECT COALESCE(SUM(count),0) FROM defense_promotions WHERE city_id=? AND state='complete'",[$city['id']])->fetchColumn();
        $out=[];
        foreach (self::MISSIONS as $code=>$def) {
            $progress=isset($def['building'])?(int)($levels[$def['building']]??0):0;
            if (!isset($def['building']) && !isset($def['promotion'])) foreach ($trained as $row)
                if (!isset($def['tier']) || (int)(TroopData::get((int)$row['troop_code'])['tier']??0)>=$def['tier']) $progress+=(int)$row['total'];
            if (isset($def['promotion'])) $progress=$promoted;
            $out[]=['quest_code'=>$code,'permanent'=>true,'title'=>Locale::t('alpha.mission.'.$code.'.title'),
                'description'=>Locale::t('alpha.mission.'.$code.'.description'),'progress'=>min($progress,$def['target']),
                'target'=>$def['target'],'completed'=>$progress>=$def['target'],'claimed'=>in_array($code,$claimed,true),
                'rewards'=>[['resources'=>array_fill_keys(['food','lumber','stone','gold'],$def['reward'])]],
                'building'=>$def['building']??null];
        }
        return $out;
    }

    public static function claim(int $playerId,string $code): array
    {
        if (!isset(self::MISSIONS[$code])) throw new \DomainException(Locale::t('alpha.error.unknown_mission'));
        WorldContext::assertActionAvailable();
        return Connection::getInstance()->transaction(static function(Connection $db) use ($playerId,$code): array {
            // Shared city lock serializes both claim routes, including concurrent requests.
            $city=WorldContext::city($playerId,null,true);
            if ($db->query('SELECT 1 FROM player_starter_missions WHERE player_id=? AND world_id=? AND quest_code=? FOR UPDATE',[$playerId,WorldContext::id(),$code])->fetchColumn()) throw new \DomainException(Locale::t('alpha.error.claimed'));
            $quest=null;
            foreach (self::quests($playerId) as $row) if ($row['quest_code']===$code) $quest=$row;
            if (!$quest || !$quest['completed']) throw new \DomainException(Locale::t('alpha.error.incomplete'));
            if ($quest['claimed']) throw new \DomainException(Locale::t('alpha.error.claimed'));
            $db->execute('INSERT INTO player_starter_missions(player_id,world_id,quest_code) VALUES(?,?,?)',[$playerId,WorldContext::id(),$code]);
            $reward=self::MISSIONS[$code]['reward'];
            $db->execute('UPDATE cities SET food=food+?,lumber=lumber+?,stone=stone+?,gold=gold+? WHERE id=? AND player_id=? AND world_id=?',[$reward,$reward,$reward,$reward,$city['id'],$playerId,WorldContext::id()]);
            \Conquer\Admin\RewardLedger::resources($playerId,WorldContext::id(),array_fill_keys(['food','lumber','stone','gold'],$reward),['source_type'=>'daily_quest','source_key'=>$code,'reference'=>'starter:'.$code]);
            return $quest['rewards'];
        });
    }
}
