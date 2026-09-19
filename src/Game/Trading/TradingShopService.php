<?php
declare(strict_types=1);
namespace Conquer\Game\Trading;

use Conquer\Db\Connection;
use Conquer\Game\City\ResourceTick;
use Conquer\Game\Inventory\InventoryService;
use Conquer\Game\Treasure\{TreasureData,TreasureService};
use Conquer\Game\Vip\VipService;
use Conquer\Game\World\WorldContext;

/** Server-owned offers, account-wide VIP stock, and atomic currency/item exchanges. */
final class TradingShopService
{
    public static function catalog(): array
    {
        return json_decode(file_get_contents(ROOT_DIR.'/data/trading_shop.json'),true,32,JSON_THROW_ON_ERROR);
    }

    /** UTC boundaries are stable across logins, city upgrades and process restarts. */
    public static function period(string $mode, ?int $now=null): array
    {
        $now??=time();
        if($mode==='caravan'){$start=intdiv($now,28800)*28800;$end=$start+28800;}
        elseif($mode==='vip'){$day=intdiv($now,86400)*86400;$start=$day-(((int)gmdate('N',$now)-1)*86400);$end=$start+604800;}
        else throw new \DomainException('Unbekannter Handelsbereich.');
        return ['rotation'=>$mode.'-'.$start,'start'=>$start,'end'=>$end];
    }

    public static function offerCount(int $level): int
    {
        return $level<=0?0:min(25,4+(int)floor((min(30,$level)-1)*21/29));
    }

    public static function state(int $playerId): array
    {
        $db=Connection::getInstance();$city=WorldContext::city($playerId);$now=time();
        $level=(int)$db->query("SELECT level FROM city_buildings WHERE city_id=? AND building_code='trading_post'",[$city['id']])->fetchColumn();
        $vip=VipService::status($playerId)['level'];$marketPeriod=self::period('caravan',$now);$vipPeriod=self::period('vip',$now);
        return ['market_level'=>$level,'server_time'=>gmdate('Y-m-d\TH:i:s\Z',$now),'world_id'=>WorldContext::id(),
            'refresh_at'=>gmdate('Y-m-d\TH:i:s\Z',$marketPeriod['end']),'rotation'=>$marketPeriod['rotation'],
            'offers'=>self::offers($playerId,'caravan',$marketPeriod,$level,$vip),
            'vip'=>['level'=>$vip,'reset_at'=>gmdate('Y-m-d\TH:i:s\Z',$vipPeriod['end']),'rotation'=>$vipPeriod['rotation'],
                'offers'=>self::offers($playerId,'vip',$vipPeriod,$level,$vip)]];
    }

    private static function offers(int $playerId,string $mode,array $period,int $marketLevel,int $vipLevel): array
    {
        $catalog=self::catalog();$offers=array_values(array_filter($catalog[$mode==='vip'?'vip':'market'],static fn(array $offer):bool=>
            $mode==='vip'||$offer['price']['resource']!=='gems'||\Conquer\Game\CrystalEconomy::allowsItem(isset($offer['item_code'])?InventoryService::getItemDef((int)$offer['item_code']):null)));
        $world=WorldContext::id();
        if($mode==='caravan'){
            // Fixed full ordering per rotation. Upgrading reveals further slots without rerolling purchases.
            $seed=$playerId.':'.$world.':'.$period['rotation'].':';
            usort($offers,static fn($a,$b)=>strcmp(hash('sha256',$seed.$a['id']),hash('sha256',$seed.$b['id'])));
            $offers=array_slice($offers,0,self::offerCount($marketLevel));
        }
        $rows=Connection::getInstance()->query('SELECT offer_id,quantity FROM trading_shop_purchases WHERE player_id=? AND scope_world_id=? AND shop_mode=? AND rotation=?',[$playerId,$mode==='vip'?0:$world,$mode,$period['rotation']])->fetchAll();
        $purchased=array_column($rows,'quantity','offer_id');
        foreach($offers as &$offer){
            $offer['remaining']=max(0,(int)$offer['limit']-(int)($purchased[$offer['id']]??0));
            $offer['locked']=$marketLevel<1||($mode==='vip'&&$vipLevel<(int)$offer['vip_level']);
            if(isset($offer['item_code']))$offer['item']=InventoryService::getItemDef((int)$offer['item_code']);
            else{$treasure=TreasureData::get((int)$offer['treasure_code']);$offer['item']=$treasure+['rarity'=>$treasure['grade'],'category'=>'fragment_pack','fragment_amount'=>(int)$offer['quantity'],'name_de'=>($treasure['name_de']??$treasure['name']).' · Fragment','description_de'=>'Gewährt ein Fragment dieses Relikts. Fragmente erhöhen automatisch die Reliktstufe.'];
                $offer['item']['name_de']=($treasure['name_de']??$treasure['name']).' · Fragment';}
        }
        return $offers;
    }

    public static function buy(int $playerId,string $mode,string $offerId,int $quantity,string $rotation): array
    {
        if(!in_array($mode,['caravan','vip'],true)||$quantity<1||$quantity>100||strlen($offerId)>40||strlen($rotation)>40)throw new \DomainException('Ungültiger Kauf.');
        WorldContext::assertActionAvailable();$db=Connection::getInstance();
        $purchase=static function()use($db,$playerId,$mode,$offerId,$quantity,$rotation):array{
            // This row serializes all purchases, including different-world VIP requests.
            if(!$db->query('SELECT id FROM players WHERE id=? FOR UPDATE',[$playerId])->fetchColumn())throw new \DomainException('Dein Konto wurde nicht gefunden.');
            $city=WorldContext::city($playerId,null,true);$state=self::state($playerId);$shop=$mode==='vip'?$state['vip']:$state;
            if(!hash_equals($shop['rotation'],$rotation))throw new \DomainException('Das Angebot wurde erneuert. Bitte lade den Handel neu.',409);
            $offer=null;foreach($shop['offers']as$candidate)if($candidate['id']===$offerId){$offer=$candidate;break;}
            if(!$offer)throw new \DomainException('Dieses Angebot ist nicht mehr verfügbar.',409);
            if($offer['locked'])throw new \DomainException($state['market_level']<1?'Baue zuerst deinen Handelsmarkt.':'Deine VIP-Stufe ist für dieses Angebot zu niedrig.');
            if($quantity>$offer['remaining'])throw new \DomainException('Für diesen Zeitraum sind nicht mehr genügend Waren verfügbar.');
            $resource=$offer['price']['resource'];$cost=(int)$offer['price']['amount']*$quantity;
            if($cost<=0||!in_array($resource,['food','lumber','stone','gold','gems'],true))throw new \DomainException('Ungültiger Handelspreis.');
            if($resource==='gems'){
                if($mode==='vip')\Conquer\Game\CrystalEconomy::requireVipShopItem($offer['item']);
                else \Conquer\Game\CrystalEconomy::requireItem($offer['item']);
                $changed=$db->execute('UPDATE players SET gems=gems-? WHERE id=? AND gems>=?',[$cost,$playerId,$cost]);
            }
            else{
                $buildings=[];foreach($db->query('SELECT building_code,level FROM city_buildings WHERE city_id=?',[$city['id']])->fetchAll()as$b)$buildings[$b['building_code']]=['level'=>(int)$b['level']];
                ResourceTick::persist($city,$buildings);
                $changed=$db->execute("UPDATE cities SET $resource=$resource-? WHERE id=? AND player_id=? AND world_id=? AND $resource>=?",[$cost,$city['id'],$playerId,WorldContext::id(),$cost]);
            }
            if($changed!==1)throw new \DomainException('Du hast nicht genügend Rohstoffe oder Edelsteine.');
            $grant=$quantity*(int)$offer['quantity'];
            if(isset($offer['item_code']))InventoryService::addItems($playerId,(int)$offer['item_code'],$grant);
            else TreasureService::addFragments($playerId,(int)$offer['treasure_code'],$grant);
            $db->execute('INSERT INTO trading_shop_purchases(player_id,scope_world_id,shop_mode,rotation,offer_id,quantity)VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE quantity=quantity+VALUES(quantity)',[$playerId,$mode==='vip'?0:WorldContext::id(),$mode,$rotation,$offerId,$quantity]);
            return ['message'=>$grant.' × '.($offer['item']['name_de']??$offer['item']['name']).' erhalten.','item_code'=>$offer['item_code']??null,'treasure_code'=>$offer['treasure_code']??null,'quantity'=>$grant,'cost'=>['resource'=>$resource,'amount'=>$cost],'remaining'=>$offer['remaining']-$quantity];
        };
        // KingdomService already holds its player lock and a transaction. Standalone callers use the same lock.
        if($db->getPdo()->inTransaction())return $purchase();
        $lock='conquer-player-'.$playerId;
        if((int)$db->query('SELECT GET_LOCK(?,5)',[$lock])->fetchColumn()!==1)throw new \DomainException('Dein Königreich wird gerade aktualisiert. Bitte versuche es erneut.');
        try{return $db->transaction($purchase);}finally{$db->query('SELECT RELEASE_LOCK(?)',[$lock]);}
    }
}
