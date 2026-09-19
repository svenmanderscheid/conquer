<?php
declare(strict_types=1);
namespace Conquer\Game\World;

use Conquer\Db\Connection;

/** Validated, revisioned per-world balancing rules for E16. */
final class LandRules
{
    private static array $cache=[];

    public static function defaults(): array
    {
        return ['parcel_size'=>8,'geometry_version'=>1,
            'thresholds'=>[1=>1000,2=>2000,3=>4000,4=>8000,5=>16000,6=>32000,7=>64000,8=>128000],
            'resource_values'=>['food'=>1,'lumber'=>1,'stone'=>2,'gold'=>4],
            'resource_units_per_point'=>100,'monster_points_per_level'=>100,
            'gather_full_daily_ratio'=>.10,'gather_reduced_factor'=>.25,'donation_daily_ratio'=>.10,
            'gates'=>[
                'middle'=>['source_zone'=>'outer','target_level'=>3,'ratio'=>.10,'minimum_count'=>32,'not_before_days'=>14,'fallback_after_days'=>null],
                'center'=>['source_zone'=>'middle','target_level'=>7,'ratio'=>.15,'minimum_count'=>24,'not_before_days'=>14,'fallback_after_days'=>null],
            ]];
    }

    /** Returns validated settings plus the database `revision`. */
    public static function get(int $worldId): array
    {
        $db=Connection::getInstance();$key=spl_object_id($db->getPdo()).':'.$worldId;
        if(isset(self::$cache[$key]))return self::$cache[$key];
        try{$row=$db->query('SELECT settings_json,revision FROM world_land_rules WHERE world_id=?',[$worldId])->fetch();}
        catch(\PDOException $e){if((int)($e->errorInfo[1]??0)!==1146)throw $e;return self::defaults()+['revision'=>0];}
        if(!$row)return self::defaults()+['revision'=>0];
        $decoded=json_decode((string)$row['settings_json'],true,32,JSON_THROW_ON_ERROR);
        return self::$cache[$key]=self::validate(is_array($decoded)?$decoded:[])+['revision'=>(int)$row['revision']];
    }

    public static function validate(array $input): array
    {
        foreach(['thresholds','resource_values','gates'] as $key)if(isset($input[$key])&&!is_array($input[$key]))throw new \DomainException("$key muss eine strukturierte Regel sein.");
        if(isset($input['gates'])&&is_array($input['gates']))foreach(['middle','center'] as $zone)if(isset($input['gates'][$zone])&&!is_array($input['gates'][$zone]))throw new \DomainException("Ungültige Zonenregel für $zone.");
        $base=self::defaults();$merged=array_replace_recursive($base,$input);
        if(self::integer($merged['parcel_size'],1,64)!==8)throw new \DomainException('Landteile müssen 8 × 8 Felder groß sein.');
        if(self::integer($merged['geometry_version'],1,65535)!==1)throw new \DomainException('Unbekannte Landgeometrie-Version.');
        $out=$base;
        foreach(range(1,8) as $level)$out['thresholds'][$level]=self::integer($merged['thresholds'][$level]??$merged['thresholds'][(string)$level]??null,1,1000000000);
        foreach(array_keys($base['resource_values']) as $resource)$out['resource_values'][$resource]=self::integer($merged['resource_values'][$resource]??null,0,1000000);
        $out['resource_units_per_point']=self::integer($merged['resource_units_per_point'],1,1000000000);
        $out['monster_points_per_level']=self::integer($merged['monster_points_per_level'],1,100000000);
        $out['gather_full_daily_ratio']=self::decimal($merged['gather_full_daily_ratio'],0,1);
        $out['gather_reduced_factor']=self::decimal($merged['gather_reduced_factor'],0,1);
        $out['donation_daily_ratio']=self::decimal($merged['donation_daily_ratio'],.001,1);
        foreach(['middle','center'] as $zone){
            $gate=$merged['gates'][$zone]??null;if(!is_array($gate))throw new \DomainException('Ungültige Zonenregel.');
            $expected=$zone==='middle'?'outer':'middle';
            if(($gate['source_zone']??null)!==$expected)throw new \DomainException('Das Zonenziel muss die unmittelbar vorherige Zone verwenden.');
            $out['gates'][$zone]=['source_zone'=>$expected,
                'target_level'=>self::integer($gate['target_level']??null,$zone==='center'?7:2,9),
                'ratio'=>self::decimal($gate['ratio']??null,.001,1),
                'minimum_count'=>self::integer($gate['minimum_count']??null,1,1000000),
                'not_before_days'=>self::integer($gate['not_before_days']??null,0,3650),
                'fallback_after_days'=>self::nullableInteger($gate['fallback_after_days']??null,1,3650)];
        }
        return $out;
    }

    public static function save(int $worldId,array $input,int $updatedBy,?int $expectedRevision=null): array
    {
        $settings=self::validate($input);$db=Connection::getInstance();
        $work=static function()use($db,$worldId,$settings,$updatedBy,$expectedRevision):void{
            $world=$db->query('SELECT id FROM worlds WHERE id=? FOR UPDATE',[$worldId])->fetchColumn();
            if($world===false)throw new \DomainException('Welt nicht gefunden.',404);
            $row=$db->query('SELECT revision FROM world_land_rules WHERE world_id=? FOR UPDATE',[$worldId])->fetch();
            $revision=$row?(int)$row['revision']:0;
            if($expectedRevision!==null&&$expectedRevision!==$revision)throw new \DomainException('Die Landregeln wurden zwischenzeitlich geändert.',409);
            $json=json_encode($settings,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
            if($row)$db->execute('UPDATE world_land_rules SET settings_json=?,revision=revision+1,updated_by=? WHERE world_id=?',[$json,$updatedBy,$worldId]);
            else $db->execute('INSERT INTO world_land_rules(world_id,settings_json,revision,updated_by)VALUES(?,?,1,?)',[$worldId,$json,$updatedBy]);
            $db->execute('UPDATE world_land_zones SET rule_revision=rule_revision+1 WHERE world_id=?',[$worldId]);
        };
        $db->getPdo()->inTransaction()?$work():$db->transaction($work);self::invalidate($worldId);
        if(class_exists(LandProgressService::class))LandProgressService::invalidate($worldId);
        if(class_exists(LandUnlockService::class))LandUnlockService::invalidate($worldId);
        return self::get($worldId);
    }

    public static function threshold(array $rules,int $level): ?int
    {
        return $level>=9?null:(int)($rules['thresholds'][$level]??$rules['thresholds'][(string)$level]??0);
    }

    public static function invalidate(int $worldId): void
    {
        $suffix=':'.$worldId;foreach(array_keys(self::$cache) as $key)if(str_ends_with($key,$suffix))unset(self::$cache[$key]);
    }

    private static function integer(mixed $value,int $min,int $max): int
    {
        if((!is_int($value)&&!is_string($value))||filter_var($value,FILTER_VALIDATE_INT)===false||(int)$value<$min||(int)$value>$max)throw new \DomainException("Erwartet wird eine ganze Zahl von $min bis $max.");
        return (int)$value;
    }
    private static function nullableInteger(mixed $value,int $min,int $max): ?int{return $value===null||$value===''?null:self::integer($value,$min,$max);}
    private static function decimal(mixed $value,float $min,float $max): float
    {
        if((!is_int($value)&&!is_float($value)&&!is_string($value))||!is_numeric($value)||!is_finite((float)$value)||(float)$value<$min||(float)$value>$max)throw new \DomainException("Erwartet wird ein Wert von $min bis $max.");
        return round((float)$value,6);
    }
}
