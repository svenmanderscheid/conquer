<?php
declare(strict_types=1);
namespace Conquer\Game\Premium;

use Conquer\Db\Connection;
use Conquer\Game\World\WorldContext;

final class ThemeBundleService
{
    public static function state(int $playerId): array
    {
        LocalCosmeticEntitlements::sync($playerId);
        $db=Connection::getInstance();$catalog=ThemeBundleCatalog::all();
        $purchaseRows=$db->query('SELECT skin_code,step FROM player_theme_bundle_purchases WHERE player_id=?',[$playerId])->fetchAll();
        $purchased=[];foreach($purchaseRows as$row)$purchased[$row['skin_code']][(int)$row['step']]=true;
        $pendingRows=$db->query("SELECT skin_code,step FROM theme_bundle_orders WHERE player_id=? AND status IN ('pending','paid')",[$playerId])->fetchAll();
        $pending=[];foreach($pendingRows as$row)$pending[$row['skin_code']][(int)$row['step']]=true;
        $frames=array_fill_keys($db->query('SELECT frame_code FROM player_name_frames WHERE player_id=?',[$playerId])->fetchAll(\PDO::FETCH_COLUMN),true);
        $marches=array_fill_keys($db->query('SELECT skin_code FROM player_march_skins WHERE player_id=?',[$playerId])->fetchAll(\PDO::FETCH_COLUMN),true);
        $castles=array_fill_keys($db->query('SELECT skin_code FROM player_castle_skins WHERE player_id=?',[$playerId])->fetchAll(\PDO::FETCH_COLUMN),true);
        $firstOpen=[];
        foreach($catalog['entries']as$entry)if(!isset($firstOpen[$entry['theme_id']])&&!isset($purchased[$entry['theme_id']][$entry['step']]))$firstOpen[$entry['theme_id']]=$entry['step'];
        $entries=[];
        foreach($catalog['entries']as$entry){
            $theme=$entry['theme_id'];$step=$entry['step'];$owned=isset($purchased[$theme][$step]);
            $unlocked=$step===1||isset($purchased[$theme][$step-1]);$isPending=isset($pending[$theme][$step]);
            $type=$entry['contents']['cosmetic']['type'];$id=$entry['contents']['cosmetic']['id'];
            $ownedCosmetics=$type==='name_frame'?$frames:($type==='march_skin'?$marches:$castles);
            $cosmeticOwned=isset($ownedCosmetics[$id]);
            $entry['status']=$owned?'owned':($isPending?'pending':($unlocked?'available':'locked'));
            $entry['owned']=$owned;$entry['cosmetic_owned']=$cosmeticOwned;$entry['unlocked']=$unlocked;
            $entry['lock_reason']=$unlocked?null:'Kaufe zuerst Paket '.($step-1).' dieses Skins.';
            $entry['next']=($firstOpen[$theme]??null)===$step;$entries[]=$entry;
        }
        $gateway=PaymentGatewayFactory::configured();
        $nextId=null;foreach($entries as$entry)if($entry['next']&&$entry['unlocked']&&!$entry['owned']){$nextId=$entry['id'];break;}
        return [
            'payment_configured'=>$gateway->isAvailable(),
            'provider_message'=>$gateway->isAvailable()?null:'Echtgeldkäufe sind noch nicht eingerichtet.',
            'currency'=>$catalog['currency'],'next_bundle_id'=>$nextId,'entries'=>$entries,
        ];
    }

    public static function checkout(int $playerId,array $body,?PaymentGateway $gateway=null):array
    {
        $bundle=ThemeBundleCatalog::get($body['bundle_id']??null);$key=$body['operation_key']??'';
        if(!is_string($key)||!preg_match('/^[A-Za-z0-9_-]{16,64}$/D',$key))throw new \DomainException('Eine gültige Vorgangskennung ist erforderlich.');
        $gateway??=PaymentGatewayFactory::configured();
        if(!$gateway->isAvailable())throw new \DomainException('Echtgeldkäufe sind noch nicht eingerichtet.',503);
        $db=Connection::getInstance();$hash=hash('sha256',json_encode(['bundle_id'=>$bundle['id']],JSON_THROW_ON_ERROR));
        $old=$db->query('SELECT * FROM theme_bundle_orders WHERE player_id=? AND client_operation_key=? FOR UPDATE',[$playerId,$key])->fetch();
        if($old){
            if(!hash_equals($old['payload_hash'],$hash))throw new \DomainException('Diese Vorgangskennung gehört zu einem anderen Kauf.');
            return self::checkoutResult($old,true);
        }
        if($db->query('SELECT 1 FROM player_theme_bundle_purchases WHERE player_id=? AND skin_code=? AND step=?',[$playerId,$bundle['theme_id'],$bundle['step']])->fetchColumn()!==false)throw new \DomainException('Dieses Paket gehört dir bereits.');
        $sameBundle=$db->query('SELECT * FROM theme_bundle_orders WHERE player_id=? AND skin_code=? AND step=? FOR UPDATE',[$playerId,$bundle['theme_id'],$bundle['step']])->fetch();
        if($sameBundle)return self::checkoutResult($sameBundle,true);
        if($bundle['step']>1&&$db->query('SELECT 1 FROM player_theme_bundle_purchases WHERE player_id=? AND skin_code=? AND step=?',[$playerId,$bundle['theme_id'],$bundle['step']-1])->fetchColumn()===false)throw new \DomainException('Kaufe zuerst das vorherige Paket dieses Skins.');
        $city=$db->query('SELECT id,world_id FROM cities WHERE player_id=? AND world_id=? FOR UPDATE',[$playerId,WorldContext::id()])->fetch();
        if(!$city)throw new \DomainException('Deine Stadt wurde nicht gefunden.');
        $id='pb_'.bin2hex(random_bytes(16));
        $order=['id'=>$id,'player_id'=>$playerId,'world_id'=>(int)$city['world_id'],'city_id'=>(int)$city['id'],'bundle_id'=>$bundle['id'],'theme_id'=>$bundle['theme_id'],'step'=>$bundle['step'],'amount_cents'=>$bundle['price_cents'],'currency'=>$bundle['currency']];
        $checkout=$gateway->createCheckout($order);$reference=$checkout['provider_reference']??null;
        if(!is_string($reference)||$reference===''||strlen($reference)>100)throw new \RuntimeException('Zahlungsanbieter hat keine gültige Referenz geliefert.');
        $db->execute('INSERT INTO theme_bundle_orders(id,player_id,world_id,city_id,skin_code,step,price_cents,currency,provider,provider_reference,client_operation_key,payload_hash,checkout_json) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)',[
            $id,$playerId,$city['world_id'],$city['id'],$bundle['theme_id'],$bundle['step'],$bundle['price_cents'],$bundle['currency'],$gateway->name(),$reference,$key,$hash,json_encode($checkout,JSON_THROW_ON_ERROR)
        ]);
        return ['message'=>'Der sichere Bezahlvorgang wurde vorbereitet.','order_id'=>$id,'bundle_id'=>$bundle['id'],'status'=>'pending','price_cents'=>$bundle['price_cents'],'currency'=>$bundle['currency'],'checkout'=>$checkout,'duplicate'=>false];
    }

    /** Provider notifications are verified before this method sees their fields. */
    public static function handleNotification(PaymentGateway $gateway,string $rawBody,array $headers):array
    {
        $verified=$gateway->verifyNotification($rawBody,$headers);$db=Connection::getInstance();
        return $db->transaction(function()use($db,$gateway,$verified):array{
            $eventId=self::providerText($verified,'event_id',100);$reference=self::providerText($verified,'provider_reference',100);$payloadHash=self::providerText($verified,'payload_hash',64);
            $event=$db->query('SELECT order_id,payload_hash FROM theme_bundle_provider_events WHERE provider=? AND event_id=? FOR UPDATE',[$gateway->name(),$eventId])->fetch();
            if($event){
                if(!hash_equals($event['payload_hash'],$payloadHash))throw new \DomainException('Diese Provider-Ereigniskennung wurde mit anderen Zahlungsdaten wiederholt.');
                $order=$db->query('SELECT * FROM theme_bundle_orders WHERE id=?',[$event['order_id']])->fetch();return ['order_id'=>$event['order_id'],'status'=>$order['status']??'fulfilled','duplicate'=>true];
            }
            $order=$db->query('SELECT * FROM theme_bundle_orders WHERE provider=? AND provider_reference=? FOR UPDATE',[$gateway->name(),$reference])->fetch();
            if(!$order)throw new \DomainException('Zahlungsauftrag wurde nicht gefunden.');
            if(($verified['status']??null)!=='paid')throw new \DomainException('Die Zahlung ist noch nicht bestätigt.');
            if(!is_int($verified['amount_cents']??null)||(int)$verified['amount_cents']!==(int)$order['price_cents']
                ||strtoupper((string)($verified['currency']??''))!==$order['currency'])throw new \DomainException('Zahlungsbetrag oder Währung stimmen nicht mit dem Auftrag überein.');
            $db->query('SELECT id FROM players WHERE id=? FOR UPDATE',[$order['player_id']])->fetchColumn();
            $bundle=ThemeBundleCatalog::get($order['skin_code'].'_'.$order['step']);
            if((int)$order['step']>1&&$db->query('SELECT 1 FROM player_theme_bundle_purchases WHERE player_id=? AND skin_code=? AND step=?',[$order['player_id'],$order['skin_code'],(int)$order['step']-1])->fetchColumn()===false)throw new \DomainException('Das vorherige Paket wurde noch nicht erfüllt.');
            $already=$db->query('SELECT order_id FROM player_theme_bundle_purchases WHERE player_id=? AND skin_code=? AND step=? FOR UPDATE',[$order['player_id'],$order['skin_code'],$order['step']])->fetchColumn();
            if($already===false){
                $rewards=$bundle['contents'];$resources=$rewards['resources'];
                if($db->execute('UPDATE cities SET food=food+?,lumber=lumber+?,stone=stone+?,gold=gold+? WHERE id=? AND player_id=? AND world_id=?',[$resources['food'],$resources['lumber'],$resources['stone'],$resources['gold'],$order['city_id'],$order['player_id'],$order['world_id']])!==1)throw new \DomainException('Die Zielstadt dieses Kaufs wurde nicht gefunden.');
                $db->execute('UPDATE players SET gems=gems+? WHERE id=?',[$rewards['gems'],$order['player_id']]);
                self::grantCosmetic($db,(int)$order['player_id'],$rewards['cosmetic']);
                $db->execute('INSERT INTO player_theme_bundle_purchases(player_id,skin_code,step,order_id) VALUES(?,?,?,?)',[$order['player_id'],$order['skin_code'],$order['step'],$order['id']]);
            }
            $db->execute("UPDATE theme_bundle_orders SET status='fulfilled',paid_at=COALESCE(paid_at,UTC_TIMESTAMP()),fulfilled_at=COALESCE(fulfilled_at,UTC_TIMESTAMP()) WHERE id=?",[$order['id']]);
            $db->execute('INSERT INTO theme_bundle_provider_events(provider,event_id,order_id,payload_hash) VALUES(?,?,?,?)',[$gateway->name(),$eventId,$order['id'],$payloadHash]);
            return ['order_id'=>$order['id'],'bundle_id'=>$bundle['id'],'status'=>'fulfilled','duplicate'=>$already!==false];
        });
    }

    private static function grantCosmetic(Connection $db,int $playerId,array $cosmetic):void
    {
        match($cosmetic['type']){
            'name_frame'=>$db->execute('INSERT IGNORE INTO player_name_frames(player_id,frame_code) VALUES(?,?)',[$playerId,$cosmetic['id']]),
            'march_skin'=>$db->execute('INSERT IGNORE INTO player_march_skins(player_id,skin_code) VALUES(?,?)',[$playerId,$cosmetic['id']]),
            'castle_skin'=>$db->execute('INSERT IGNORE INTO player_castle_skins(player_id,skin_code) VALUES(?,?)',[$playerId,$cosmetic['id']]),
            default=>throw new \RuntimeException('Unbekannte kosmetische Belohnung.'),
        };
    }

    private static function checkoutResult(array $row,bool $duplicate):array
    {
        return ['message'=>'Dieser Bezahlvorgang wurde bereits vorbereitet.','order_id'=>$row['id'],'bundle_id'=>$row['skin_code'].'_'.$row['step'],'status'=>$row['status'],'price_cents'=>(int)$row['price_cents'],'currency'=>$row['currency'],'checkout'=>json_decode((string)$row['checkout_json'],true)?:[],'duplicate'=>$duplicate];
    }
    private static function providerText(array $data,string $key,int $max):string
    {
        $value=$data[$key]??null;if(!is_string($value)||$value===''||strlen($value)>$max)throw new \DomainException('Ungültige Zahlungsnachricht.');return $value;
    }
}
