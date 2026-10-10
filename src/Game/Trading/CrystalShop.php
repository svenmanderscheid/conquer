<?php
declare(strict_types=1);
namespace Conquer\Game\Trading;

use Conquer\Game\Inventory\InventoryService;

/** Independent regular-price catalogue. No VIP locks or shared weekly stock. */
final class CrystalShop
{
    public static function offers(): array
    {
        $catalog=json_decode(file_get_contents(ROOT_DIR.'/data/crystal_shop.json'),true,32,JSON_THROW_ON_ERROR);
        $offers=[];
        foreach($catalog['offers'] as $row){
            $item=InventoryService::getItemDef((int)$row['item_code']);
            // Alliance Badges are consumed by building upgrades, not inventory.use.
            $buildingMaterial=$item && (int)$item['code']===119000002 && $item['category']==='material' && ($item['source_resource']??'')==='alliance_badge';
            if(!$item || (!$buildingMaterial && (($item['is_usable']??true)===false || !in_array($item['category'],['resource_pack','speedup','teleport','boost','vip_point'],true))) || ($item['resource']??'')==='gems' || !is_int($row['price_crystals']) || $row['price_crystals']<=0)throw new \RuntimeException('Invalid Crystal Shop catalogue.');
            $offers[]=['id'=>'crystal-'.$item['code'],'item_code'=>$item['code'],'item'=>$item,'quantity'=>1,'price'=>['resource'=>'gems','amount'=>$row['price_crystals']],'remaining'=>100,'limit'=>100,'locked'=>false];
        }
        return $offers;
    }

    public static function price(int $code): int
    {
        foreach(self::offers() as $offer)if((int)$offer['item_code']===$code)return $offer['price']['amount'];
        throw new \DomainException('Dieser Gegenstand wird im Kristall-Shop nicht angeboten.');
    }
}
