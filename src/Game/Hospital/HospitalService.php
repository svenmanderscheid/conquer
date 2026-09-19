<?php
declare(strict_types=1);
namespace Conquer\Game\Hospital;

use Conquer\Db\Connection;
use Conquer\Game\City\{CityState,ResourceTick,TroopData};
use Conquer\Game\Inventory\InventoryService;
use Conquer\Game\Operation;
use Conquer\Game\Research\BuffEngine;
use Conquer\Game\World\WorldContext;

/** Wounded wait for resource-paid treatment; only started batches have a timer. */
final class HospitalService
{
    private const BASE_CAPACITY=1000;

    public static function addWounded(int $cityId,array $troops): void
    {
        $db=Connection::getInstance();
        foreach($troops as $code=>$count){
            if((int)$count<=0)continue;
            $db->execute('INSERT INTO hospital_wounded(city_id,troop_code,count) VALUES(?,?,?) ON DUPLICATE KEY UPDATE count=count+VALUES(count)',[$cityId,(int)$code,(int)$count]);
        }
    }

    public static function processHealed(int $cityId): void
    {
        $db=Connection::getInstance();
        $settle=static function()use($db,$cityId):void{
            $rows=$db->query('SELECT id,troop_code,count,healing_count FROM hospital_wounded WHERE city_id=? AND healing_count>0 AND healing_ends_at<=UTC_TIMESTAMP() FOR UPDATE',[$cityId])->fetchAll();
            foreach($rows as $row){
                $count=min((int)$row['count'],(int)$row['healing_count']);
                if($count>0)$db->execute('INSERT INTO city_troops(city_id,troop_code,count) VALUES(?,?,?) ON DUPLICATE KEY UPDATE count=count+VALUES(count)',[$cityId,$row['troop_code'],$count]);
                if($count===(int)$row['count'])$db->execute('DELETE FROM hospital_wounded WHERE id=?',[$row['id']]);
                else $db->execute('UPDATE hospital_wounded SET count=count-?,healing_count=0,healing_ends_at=NULL,healing_started_at=NULL,healing_batch=NULL WHERE id=?',[$count,$row['id']]);
            }
        };
        if($db->getPdo()->inTransaction())$settle();else $db->transaction($settle);
    }

    /** Early tiers are deliberately cheap to recover; the rate rises gradually to 45%. */
    public static function healingResources(int $code,int $count=1): array
    {
        $troop=TroopData::get($code);
        if(!$troop)throw new \DomainException('Unbekannter Truppentyp.');
        $rates=[1=>.10,2=>.12,3=>.15,4=>.18,5=>.22,6=>.27,7=>.32,8=>.37,9=>.42,10=>.45];
        $rate=$rates[max(1,min(10,(int)$troop['tier']))];
        $cost=[];foreach(['food','lumber','stone','gold']as$key)$cost[$key]=(int)ceil((int)$troop['need_'.$key]*$rate)*$count;
        return $cost;
    }

    private static function factor(int $cityId): array
    {
        $owner=Connection::getInstance()->query('SELECT player_id,world_id FROM cities WHERE id=?',[$cityId])->fetch();
        $buffs=BuffEngine::getBuffs((int)$owner['player_id'],(int)$owner['world_id']);
        return [$buffs,max(.05,1-(float)($buffs['healing_time_reduced']??0))/max(1,1+(float)($buffs['healing_speed']??0))];
    }

    public static function getStatus(int $cityId): array
    {
        $rows=Connection::getInstance()->query('SELECT troop_code,count,healing_count,healing_ends_at,healing_started_at,healing_batch FROM hospital_wounded WHERE city_id=? ORDER BY troop_code',[$cityId])->fetchAll();
        [$buffs,$factor]=self::factor($cityId);$wounded=[];$used=0;$healing=0;$ends=null;$starts=null;$batch=null;
        foreach($rows as$row){
            $code=(int)$row['troop_code'];$count=(int)$row['count'];$active=(int)$row['healing_count'];$unit=TroopData::get($code);
            $used+=$count;$healing+=$active;
            if($active>0){$ends=max($ends??'',$row['healing_ends_at']);$starts=min($starts??$row['healing_started_at'],$row['healing_started_at']);$batch=$row['healing_batch'];}
            $wounded[]=['troop_code'=>$code,'name'=>$unit['name_de']??$unit['name']??'Truppen','count'=>$count,'waiting_count'=>$count-$active,'healing_count'=>$active,
                'healing_ends_at'=>$active?$row['healing_ends_at']:null,'resources'=>self::healingResources($code),'seconds_per_troop'=>max(1,(int)($unit['heal_time']??1))*$factor];
        }
        return ['wounded'=>$wounded,'used'=>$used,'waiting'=>$used-$healing,'healing'=>$healing,
            'capacity'=>(int)floor(self::BASE_CAPACITY*(1+max(0.0,(float)($buffs['hospital_capacity']??0))))+max(0,(int)($buffs['hospital_capacity_flat']??0)),
            'active'=>$healing?['batch_id'=>$batch,'count'=>$healing,'started_at'=>$starts,'ends_at'=>$ends]:null];
    }

    /** Dedicated commands share receipts, world ownership and resource validation. */
    public static function execute(int $playerId,array $body): array
    {
        if(in_array($body['action']??'',['hospital.instant','hospital.finish'],true))\Conquer\Game\CrystalEconomy::reject();
        WorldContext::current($body['expected_world_id']??null);WorldContext::assertActionAvailable();
        $state=CityState::loadForPlayer($playerId);
        if(!$state)throw new \DomainException('Deine Stadt wurde nicht gefunden.');
        return Operation::run($playerId,$body,static function()use($playerId,$body,$state):array{
            $city=WorldContext::city($playerId,null,true);
            self::processHealed((int)$city['id']);
            if($body['action']==='hospital.heal')ResourceTick::persist($city,$state['buildings']);
            return self::perform($playerId,(int)$city['id'],$body);
        });
    }

    /** Caller holds the city/player lock and an atomic transaction. */
    public static function perform(int $playerId,int $cityId,array $body): array
    {
        return match($body['action']??''){
            'hospital.heal'=>self::start($cityId,$body),
            'hospital.instant','hospital.finish'=>\Conquer\Game\CrystalEconomy::reject(),
            'hospital.speedup'=>self::useSpeedup($cityId,$playerId,$body),
            default=>throw new \DomainException('Unbekannte Hospitalaktion.'),
        };
    }

    private static function start(int $cityId,array $body): array
    {
        $db=Connection::getInstance();$h=self::getStatus($cityId);
        if($h['active'])throw new \DomainException('Es läuft bereits eine Heilung.');
        $troops=$body['troops']??null;
        if(!is_array($troops)||!$troops||count($troops)>100)throw new \DomainException('Wähle verwundete Truppen aus.');
        $available=array_column($h['wounded'],null,'troop_code');$cost=array_fill_keys(['food','lumber','stone','gold'],0);$seconds=0;$count=0;
        foreach($troops as$code=>$n){
            if(!ctype_digit((string)$code)||!is_int($n)||$n<=0||!isset($available[$code])||$n>$available[$code]['waiting_count'])throw new \DomainException('Die Verwundeten haben sich geändert. Bitte wähle erneut.');
            foreach($cost as$key=>$_)$cost[$key]+=$available[$code]['resources'][$key]*$n;
            $count+=$n;$seconds+=$available[$code]['seconds_per_troop']*$n;
        }
        $seconds=max(1,(int)ceil($seconds));
        if(isset($body['expected_resources'])&&$body['expected_resources']!=$cost)throw new \DomainException('Die Ressourcenkosten haben sich geändert. Bitte bestätige erneut.');
        if(isset($body['expected_seconds'])&&$body['expected_seconds']!==$seconds)throw new \DomainException('Die Heilzeit hat sich geändert. Bitte bestätige erneut.');
        if($db->execute('UPDATE cities SET food=food-?,lumber=lumber-?,stone=stone-?,gold=gold-? WHERE id=? AND food>=? AND lumber>=? AND stone>=? AND gold>=?',array_merge(array_values($cost),[$cityId],array_values($cost)))!==1)throw new \DomainException('Nicht genügend Ressourcen für die Heilung.');
        $batch=(string)$body['operation_key'];
        foreach($troops as$code=>$n){
            $db->execute('UPDATE hospital_wounded SET healing_count=?,healing_started_at=UTC_TIMESTAMP(),healing_ends_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND),healing_batch=? WHERE city_id=? AND troop_code=?',[$n,$seconds,$batch,$cityId,$code]);
        }
        return ['message'=>"Die Heilung von $count Truppen wurde gestartet.",'resources_spent'=>$cost,'gems_spent'=>0,'troops_healed'=>0,'troops_started'=>$count,'duration_seconds'=>$seconds,'batch_id'=>$batch];
    }

    private static function active(int $cityId,?string $expected=null): array
    {
        self::processHealed($cityId);$active=self::getStatus($cityId)['active'];
        if(!$active)throw new \DomainException('Es gibt keine laufende Heilung.');
        if($expected!==null&&$active['batch_id']!==$expected)throw new \DomainException('Dieser Heilungsauftrag ist bereits beendet.');
        return $active;
    }

    /** Inventory and hospital controls accelerate the same paid batch. */
    public static function reduceTime(int $cityId,int $seconds,?string $batch=null): void
    {
        self::active($cityId,$batch);
        if($seconds<=0)throw new \DomainException('Ungültige Beschleunigungsdauer.');
        Connection::getInstance()->execute('UPDATE hospital_wounded SET healing_ends_at=GREATEST(UTC_TIMESTAMP(),DATE_SUB(healing_ends_at,INTERVAL ? SECOND)) WHERE city_id=? AND healing_count>0 AND healing_ends_at>UTC_TIMESTAMP()',[$seconds,$cityId]);
        self::processHealed($cityId);
    }

    private static function useSpeedup(int $cityId,int $playerId,array $body): array
    {
        $code=$body['item_code']??null;$quantity=$body['quantity']??1;$batch=$body['batch_id']??null;
        if(!is_int($code)||!is_int($quantity)||$quantity<1||$quantity>10000||!is_string($batch)||$batch==='')throw new \DomainException('Ungültige Beschleunigerauswahl.');
        $def=InventoryService::getItemDef($code);
        if(!$def||$def['category']!=='speedup'||!in_array($def['subcategory']??'',['healing','generic'],true))throw new \DomainException('Hier sind nur Heilungs- und allgemeine Beschleuniger möglich.');
        $seconds=(int)$def['duration_seconds']*$quantity;
        self::reduceTime($cityId,$seconds,$batch);
        if(!InventoryService::removeItems($playerId,$code,$quantity))throw new \DomainException('Nicht genügend Beschleuniger im Inventar.');
        return ['message'=>'Die Heilzeit wurde verkürzt.','item_code'=>$code,'quantity'=>$quantity,'seconds'=>$seconds];
    }
}
