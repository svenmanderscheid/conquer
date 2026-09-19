<?php
declare(strict_types=1);
namespace Conquer\Admin;

final class ItemPresentation
{
    public const CATEGORIES=['resource_pack'=>'Rohstoffe','resource_box'=>'Rohstoffkisten','speedup'=>'Beschleuniger','boost'=>'Boni','chest'=>'Truhen','ap_refill'=>'Aktionspunkte','vip_point'=>'Prestige','fragment_pack'=>'Reliktfragmente','treasure_fragment'=>'Reliktfragmente','teleport'=>'Teleporter','other'=>'Sonstiges'];
    public const GRADES=['normal'=>'Normal','common'=>'Normal','uncommon'=>'Ungewöhnlich','rare'=>'Selten','epic'=>'Episch','legendary'=>'Legendär','mythic'=>'Mythisch'];

    public static function item(array $d): array
    {
        $category=$d['category']??'other';$image='items/shield.svg';
        if(!empty($d['icon']))$image='items/'.$d['icon'];
        elseif($category==='resource_pack')$image=in_array($d['resource'],['food','lumber','stone','gold'],true)?'ui-resources/'.$d['resource'].'.png':'items/gems.svg';
        elseif($category==='speedup')$image='items/speedup.svg';
        elseif($category==='chest')$image='items/chest-'.($d['chest_type']??'silver').'.svg';
        elseif($category==='ap_refill')$image='items/energy.svg';
        elseif($category==='vip_point')$image='items/prestige.svg';
        elseif($category==='fragment_pack')$image='items/fragment.svg';
        elseif($category==='boost')$image='items/'.(['resource_production'=>'production.svg','gathering_speed'=>'gathering.svg','construction_speed'=>'hammer.svg','research_speed'=>'research.svg','training_speed'=>'helmet.svg','anti_spy'=>'anti-spy.svg'][$d['boost_type']??'']??'shield.svg');
        return ['code'=>(string)$d['code'],'name'=>$d['name_de']??$d['name'],'category'=>$category,'category_name'=>self::CATEGORIES[$category]??'Sonstiges','rarity'=>$d['rarity']??'normal','image'=>self::image($image),'description'=>$d['description_de']??$d['description']??''];
    }

    public static function image(string $relative): string
    {
        $root=dirname(__DIR__,2).'/assets/art/';
        if(str_contains($relative,'..')||!is_file($root.$relative))$relative='items/pouch.svg';
        return (defined('APP_BASE')?APP_BASE:'').'/assets/art/'.$relative;
    }

    public static function catalog(bool $fragments=false): array
    {
        $out=array_values(array_map(self::item(...),\Conquer\Game\Inventory\InventoryService::allDefs()));
        if($fragments)foreach(['normal','rare','epic','legendary','mythic'] as $grade)$out[]=['code'=>'fragment:'.$grade,'name'=>self::GRADES[$grade].' · zufällige Reliktfragmente','category'=>'fragments','category_name'=>'Reliktfragmente','rarity'=>$grade,'image'=>self::image('items/fragment.svg'),'description'=>'Fragmente eines zufälligen Relikts dieser Seltenheit.'];
        return $out;
    }
}
