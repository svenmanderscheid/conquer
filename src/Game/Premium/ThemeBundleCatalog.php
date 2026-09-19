<?php
declare(strict_types=1);
namespace Conquer\Game\Premium;

use Conquer\Game\March\MarchSkinService;

/** Validated, server-owned prices and rewards. The client submits only a bundle id. */
final class ThemeBundleCatalog
{
    private static ?array $data=null;

    /** @return array{currency:string,entries:array<string,array<string,mixed>>} */
    public static function all(): array
    {
        if(self::$data!==null)return self::$data;
        try{$raw=json_decode((string)file_get_contents(ROOT_DIR.'/data/theme_bundles.json'),true,32,JSON_THROW_ON_ERROR);}
        catch(\Throwable $e){throw new \RuntimeException('Paketkatalog kann nicht geladen werden.',0,$e);}
        $currency=strtoupper((string)($raw['currency']??''));
        if(!preg_match('/^[A-Z]{3}$/D',$currency))throw new \RuntimeException('Paketkatalog enthält eine ungültige Währung.');
        $steps=[];
        foreach(($raw['steps']??[])as$step){
            $number=(int)($step['step']??0);$resources=$step['resources']??[];
            if($number<1||$number>3||isset($steps[$number])||(int)($step['price_cents']??0)<1
                ||!in_array($step['cosmetic_type']??null,['name_frame','march_skin','castle_skin'],true))throw new \RuntimeException('Paketkatalog enthält eine ungültige Stufe.');
            foreach(['food','lumber','stone','gold']as$r)if(!is_int($resources[$r]??null)||$resources[$r]<0)throw new \RuntimeException('Paketkatalog enthält ungültige Rohstoffe.');
            if(!is_int($step['gems']??null)||$step['gems']<0)throw new \RuntimeException('Paketkatalog enthält ungültige Edelsteine.');
            $steps[$number]=$step;
        }
        if(array_keys($steps)!==[1,2,3]){ksort($steps);if(array_keys($steps)!==[1,2,3])throw new \RuntimeException('Paketkatalog braucht genau drei Stufen.');}
        $entries=[];$themes=[];
        foreach(($raw['themes']??[])as$theme){
            $id=(string)($theme['id']??'');
            if($id==='default'||!preg_match('/^[a-z0-9_]{1,20}$/D',$id)||isset($themes[$id]))throw new \RuntimeException('Paketkatalog enthält eine ungültige Skin-ID.');
            foreach(['title','frame_name','march_name','castle_name']as$key)if(!is_string($theme[$key]??null)||trim($theme[$key])==='')throw new \RuntimeException('Paketkatalog enthält einen ungültigen Namen.');
            $themes[$id]=true;
            foreach($steps as$number=>$step){
                $type=$step['cosmetic_type'];$cosmeticId=$id;
                $cosmeticName=$theme[$type==='name_frame'?'frame_name':($type==='march_skin'?'march_name':'castle_name')];
                $bundleId=$id.'_'.$number;
                $entries[$bundleId]=[
                    'id'=>$bundleId,'theme_id'=>$id,'theme_title'=>$theme['title'],'step'=>$number,
                    'title'=>$step['title'],'price_cents'=>$step['price_cents'],'currency'=>$currency,
                    'contents'=>['resources'=>$step['resources'],'gems'=>$step['gems'],'cosmetic'=>['type'=>$type,'id'=>$cosmeticId,'name'=>$cosmeticName]],
                ];
            }
        }
        $expected=array_values(array_filter(array_keys(MarchSkinService::catalog()),static fn(string$id):bool=>$id!=='default'));
        if(array_keys($themes)!==$expected)throw new \RuntimeException('Paketkatalog muss dieselben Premium-Skin-IDs wie der Marschkatalog enthalten.');
        return self::$data=['currency'=>$currency,'entries'=>$entries];
    }

    public static function get(mixed $bundleId): array
    {
        if(!is_string($bundleId)||!isset(self::all()['entries'][$bundleId]))throw new \DomainException('Wähle ein verfügbares Themenpaket.');
        return self::all()['entries'][$bundleId];
    }
}
