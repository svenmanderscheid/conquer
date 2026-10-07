<?php
declare(strict_types=1);
namespace Conquer\Game\Dungeon;

use Conquer\Db\Connection;
use Conquer\Game\Inventory\InventoryService;
use Conquer\Game\Locale;
use Conquer\Game\Map\{FieldObjectService,MonsterData};
use Conquer\Game\World\{LuxembourgGeography,WorldContext,WorldMapProfile};

/** One world-bound quest; callers settle drop events in the same transaction as their march. */
final class MelusinaProgress
{
    public static function rules(): array
    {
        static $rules;
        return $rules??=json_decode((string)file_get_contents(dirname(__DIR__,3).'/data/melusina.json'),true,512,JSON_THROW_ON_ERROR);
    }

    public static function available(int $worldId): bool
    {
        if(!WorldMapProfile::isLuxembourg($worldId))return false;
        try { Connection::getInstance()->query('SELECT 1 FROM melusina_progress LIMIT 1'); return true; }
        catch(\PDOException $e){if((int)($e->errorInfo[1]??0)===1146)return false;throw $e;}
    }

    /** Map marker is geometry-only; it does not create player progress. */
    public static function entrance(int $worldId): ?array
    {
        return DungeonEntrance::forWorld($worldId);
    }

    public static function keyCount(int $playerId,int $worldId): int
    {
        return InventoryService::quantity($playerId,(int)self::rules()['key_item_code'],$worldId);
    }

    public static function reservation(int $runId): ?array
    {
        return Connection::getInstance()->query('SELECT * FROM melusina_key_reservations WHERE run_id=?',[$runId])->fetch()?:null;
    }

    public static function status(int $playerId,int $worldId): array
    {
        $r=self::rules();$available=self::available($worldId);$db=Connection::getInstance();
        $progress=$available?($db->query('SELECT * FROM melusina_progress WHERE player_id=? AND world_id=?',[$playerId,$worldId])->fetch()?:[]):[];
        $accepted=!empty($progress['accepted_at']);
        $fragments=$available?InventoryService::quantity($playerId,(int)$r['fragment_item_code'],$worldId):0;
        $keys=$available?self::keyCount($playerId,$worldId):0;
        $pity=(int)($progress['pity_count']??0);$transit=0;
        if($available)foreach($db->query("SELECT haul_json FROM marches WHERE player_id=? AND world_id=? AND state='returning'",[$playerId,$worldId])->fetchAll() as $march){
            $haul=json_decode($march['haul_json']??'{}',true)?:[];$transit+=max(0,(int)($haul['items'][$r['fragment_item_code']]??0));
        }
        return [
            'available'=>$available,'accepted'=>$accepted,'fragments'=>$fragments,'fragments_required'=>(int)$r['fragments_required'],
            'keys'=>$keys,'can_craft'=>$accepted&&$fragments>=(int)$r['fragments_required'],
            'pity_count'=>$pity,'pity_limit'=>(int)$r['pity_limit'],'actions_until_guaranteed'=>max(1,(int)$r['pity_limit']-$pity),
            'drop_chance'=>(float)$r['drop_chance'],'first_clear_at'=>empty($progress['first_clear_at'])?null:str_replace(' ','T',$progress['first_clear_at']).'Z',
            'completed_runs'=>(int)($progress['completed_runs']??0),
            'reserved_keys'=>$available?(int)$db->query("SELECT COUNT(*) FROM melusina_key_reservations WHERE player_id=? AND world_id=? AND status='reserved'",[$playerId,$worldId])->fetchColumn():0,
            'fragment_item_code'=>(int)$r['fragment_item_code'],'key_item_code'=>(int)$r['key_item_code'],
            'entrance'=>self::entrance($worldId),'sources'=>$available&&$accepted?self::sources($playerId,$worldId):[],
            'in_transit_fragments'=>$transit,
        ];
    }

    public static function accept(int $playerId,int $worldId,string $requestId): array
    {
        return self::operation($playerId,$worldId,$requestId,'accept',static function(Connection $db)use($playerId,$worldId):void{
            $db->execute('UPDATE melusina_progress SET accepted_at=COALESCE(accepted_at,UTC_TIMESTAMP()) WHERE player_id=? AND world_id=?',[$playerId,$worldId]);
        });
    }

    public static function craft(int $playerId,int $worldId,string $requestId): array
    {
        return self::operation($playerId,$worldId,$requestId,'craft',static function(Connection $db,array $progress)use($playerId,$worldId):void{
            self::require(!empty($progress['accepted_at']),'melusina_accept_first');$r=self::rules();
            self::require(InventoryService::removeItems($playerId,(int)$r['fragment_item_code'],(int)$r['fragments_required'],$worldId),'melusina_fragments');
            InventoryService::addItems($playerId,(int)$r['key_item_code'],1,$worldId);
        });
    }

    private static function operation(int $playerId,int $worldId,string $requestId,string $action,callable $work): array
    {
        self::require(self::available($worldId),'melusina_unavailable');
        self::require((bool)preg_match('/^[A-Za-z0-9_-]{8,80}$/D',$requestId),'melusina_request_id');
        WorldContext::city($playerId,$worldId);
        Connection::getInstance()->transaction(static function(Connection $db)use($playerId,$worldId,$requestId,$action,$work):void{
            $progress=self::lockProgress($playerId,$worldId);
            $previous=$db->query('SELECT action FROM melusina_operations WHERE player_id=? AND world_id=? AND request_id=?',[$playerId,$worldId,$requestId])->fetchColumn();
            if($previous!==false){self::require($previous===$action,'melusina_request_id');return;}
            $work($db,$progress);
            $db->execute('INSERT INTO melusina_operations(player_id,world_id,request_id,action)VALUES(?,?,?,?)',[$playerId,$worldId,$requestId,$action]);
        });
        return self::status($playerId,$worldId);
    }

    /**
     * Internal event hook: only call after an authoritative victory or normal full depletion.
     * Returns items for the existing return-haul pipeline, never directly pays inventory.
     * Event identity uses the defeated monster/node ID, not a client request ID.
     */
    public static function drop(int $playerId,int $worldId,string $source,int $sourceId,int $x,int $y,int $occurredAt): array
    {
        if(!in_array($source,['monster','gather'],true)||$sourceId<1||!self::available($worldId)||!self::inCanton($x,$y))return [];
        $db=Connection::getInstance();
        if(!$db->getPdo()->inTransaction())throw new \LogicException('Melusina drops must share the march transaction.');
        $progress=self::lockProgress($playerId,$worldId);
        if(empty($progress['accepted_at'])||$occurredAt<strtotime($progress['accepted_at'].' UTC'))return [];
        $event=$source.':'.$sourceId;
        if($db->execute('INSERT IGNORE INTO melusina_drop_events(world_id,event_key,player_id,source_type,occurred_at)VALUES(?,?,?,?,?)',[$worldId,$event,$playerId,$source,gmdate('Y-m-d H:i:s',$occurredAt)])!==1)return [];
        $r=self::rules();$pity=(int)$progress['pity_count']+1;
        $won=$pity>=(int)$r['pity_limit']||random_int(1,1000000)<=(int)round((float)$r['drop_chance']*1000000);
        $db->execute('UPDATE melusina_progress SET pity_count=? WHERE player_id=? AND world_id=?',[$won?0:$pity,$playerId,$worldId]);
        if(!$won)return [];
        $db->execute('UPDATE melusina_drop_events SET fragment_count=1 WHERE world_id=? AND event_key=?',[$worldId,$event]);
        return [(int)$r['fragment_item_code']=>1];
    }

    public static function inCanton(int $x,int $y): bool
    {
        return (LuxembourgGeography::at($x,$y)['canton_id']??null)===self::rules()['canton_id'];
    }

    /** Start holds one owner key. Leader/party eligibility is checked by DungeonService. */
    public static function reserve(int $runId,int $ownerId,int $worldId): void
    {
        self::require(self::available($worldId),'melusina_unavailable');
        Connection::getInstance()->transaction(static function(Connection $db)use($runId,$ownerId,$worldId):void{
            $run=$db->query('SELECT id,world_id,dungeon_code,leader_player_id FROM dungeon_runs WHERE id=? FOR UPDATE',[$runId])->fetch();
            self::require($run&&(int)$run['world_id']===$worldId&&(int)$run['leader_player_id']===$ownerId&&$run['dungeon_code']===self::rules()['dungeon_code'],'melusina_unavailable');
            $existing=$db->query('SELECT * FROM melusina_key_reservations WHERE run_id=? FOR UPDATE',[$runId])->fetch();
            if($existing){self::require((int)$existing['player_id']===$ownerId&&(int)$existing['world_id']===$worldId&&$existing['status']==='reserved','melusina_key');return;}
            $progress=self::lockProgress($ownerId,$worldId);self::require(!empty($progress['accepted_at']),'melusina_accept_first');
            self::require(InventoryService::removeItems($ownerId,(int)self::rules()['key_item_code'],1,$worldId),'melusina_key');
            $db->execute('INSERT INTO melusina_key_reservations(run_id,world_id,player_id)VALUES(?,?,?)',[$runId,$worldId,$ownerId]);
        });
    }

    /** Persisted reservation owns its world; a tick's bound world cannot redirect refunds. */
    public static function settle(int $runId,bool $success,array $partyIds): void
    {
        Connection::getInstance()->transaction(static function(Connection $db)use($runId,$success,$partyIds):void{
            $reservation=$db->query('SELECT * FROM melusina_key_reservations WHERE run_id=? FOR UPDATE',[$runId])->fetch();
            if(!$reservation||$reservation['status']!=='reserved')return;
            $worldId=(int)$reservation['world_id'];
            if($success){
                $ids=array_values(array_unique(array_map('intval',$partyIds)));sort($ids);
                foreach($ids as $playerId){
                    self::lockProgress($playerId,$worldId);
                    $db->execute('UPDATE melusina_progress SET first_clear_at=COALESCE(first_clear_at,UTC_TIMESTAMP()),completed_runs=completed_runs+1 WHERE player_id=? AND world_id=?',[$playerId,$worldId]);
                }
            }else InventoryService::addItems((int)$reservation['player_id'],(int)self::rules()['key_item_code'],1,$worldId);
            $db->execute('UPDATE melusina_key_reservations SET status=?,settled_at=UTC_TIMESTAMP() WHERE run_id=?',[$success?'consumed':'returned',$runId]);
        });
    }

    private static function lockProgress(int $playerId,int $worldId): array
    {
        // Duplicate-key UPDATE takes the exclusive row lock directly. INSERT IGNORE
        // would take shared locks which simultaneous crafting requests then upgrade.
        $db=Connection::getInstance();$db->execute('INSERT INTO melusina_progress(player_id,world_id)VALUES(?,?) ON DUPLICATE KEY UPDATE player_id=VALUES(player_id)',[$playerId,$worldId]);
        return $db->query('SELECT * FROM melusina_progress WHERE player_id=? AND world_id=? FOR UPDATE',[$playerId,$worldId])->fetch();
    }

    private static function sources(int $playerId,int $worldId): array
    {
        $db=Connection::getInstance();$city=WorldContext::city($playerId,$worldId);$result=[];$r=self::rules();
        $canton=array_values(array_filter(LuxembourgGeography::cantons(),static fn($c)=>$c['id']===$r['canton_id']))[0];
        [$minimum,$maximum]=$canton['bounds'];
        foreach(['monster','gather'] as $type){
            $sql=$type==='monster'?"SELECT id,coord_x,coord_y,monster_code FROM field_monsters WHERE world_id=? AND hp_current>0 AND monster_type='solo' AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP())":"SELECT id,coord_x,coord_y,object_type,level FROM field_objects WHERE world_id=? AND resource_amount>0 AND expires_at>UTC_TIMESTAMP() AND gatherer_march_id IS NULL";
            $rows=$db->query($sql.' AND coord_x BETWEEN ? AND ? AND coord_y BETWEEN ? AND ? ORDER BY POW(coord_x-?,2)+POW(coord_y-?,2),id',[$worldId,$minimum[0],$maximum[0],$minimum[1],$maximum[1],$city['coord_x'],$city['coord_y']])->fetchAll();
            $count=0;
            foreach($rows as $row){
                $x=(int)$row['coord_x'];$y=(int)$row['coord_y'];if(!self::inCanton($x,$y))continue;
                if($type==='monster'){
                    if(!MonsterData::isActive((int)$row['monster_code']))continue;
                    $def=MonsterData::definition((int)$row['monster_code']);$name=Locale::text($def['name']);$level=(int)$def['level'];
                }else{$name=Locale::t('resource.'.FieldObjectService::RESOURCE_BY_TYPE[(int)$row['object_type']]);$level=(int)$row['level'];}
                $result[]=['type'=>$type,'id'=>(int)$row['id'],'x'=>$x,'y'=>$y,'level'=>$level,'name'=>$name,'distance'=>round(hypot($x-(int)$city['coord_x'],$y-(int)$city['coord_y']),1)];
                if(++$count>=(int)$r['source_limit'])break;
            }
        }
        return $result;
    }

    private static function require(bool $condition,string $code): void
    {
        if(!$condition)throw new DungeonException($code,Locale::t('dungeons.error.'.$code),409);
    }
}
