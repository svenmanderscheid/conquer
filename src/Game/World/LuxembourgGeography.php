<?php
declare(strict_types=1);
namespace Conquer\Game\World;

/** The approved field-grid is authoritative; polygons are its exact visual outlines. */
final class LuxembourgGeography
{
    public const WIDTH=768, HEIGHT=1100, VERSION=1;
    private static ?array $data=null;
    private static array $rows=[],$cantons=[];

    public static function data(): array
    {
        if(self::$data===null){
            self::$data=json_decode((string)file_get_contents(dirname(__DIR__,3).'/assets/world-lux-preview/game-geography.json'),true,512,JSON_THROW_ON_ERROR);
            foreach(self::$data['cantons']as$c)self::$cantons[$c['id']]=$c;
            foreach(self::$data['grid']['rows']as$y=>$runs){$row='';for($i=0;$i<count($runs);$i+=2)$row.=str_repeat(chr($runs[$i]),$runs[$i+1]);self::$rows[$y]=$row;}
            if(count(self::$data['communes'])!==100||count(self::$cantons)!==12)throw new \RuntimeException('Invalid Luxembourg geography catalog.');
        }
        return self::$data;
    }
    public static function hash(): string
    {
        static $hash;return $hash??=hash('sha256',file_get_contents(dirname(__DIR__,3).'/assets/world-lux-preview/game-geography.json').file_get_contents(dirname(__DIR__,3).'/assets/world-lux-preview/game-hydrology.json'));
    }
    public static function communes(): array {return self::data()['communes'];}
    public static function cantons(): array {return self::data()['cantons'];}
    public static function at(float $x,float $y): ?array
    {
        self::data();if(!is_finite($x)||!is_finite($y)||$x<0||$y<0||$x>=self::WIDTH||$y>=self::HEIGHT)return null;
        $n=ord(self::$rows[(int)floor($y/4)][(int)floor($x/4)]);if(!$n)return null;$c=self::$data['communes'][$n-1];
        return ['commune_id'=>$c['id'],'canton_id'=>$c['canton'],'commune_name'=>$c['name'],'canton_name'=>self::$cantons[$c['canton']]['name']];
    }
    /** Physical rectangle edges, not tile indices. Checks every intersected grid cell. */
    public static function isDryRectangle(float $left,float $top,float $right,float $bottom,?string $cantonId=null): bool
    {
        if($left<0||$top<0||$right>self::WIDTH||$bottom>self::HEIGHT||$left>$right||$top>$bottom)return false;
        for($gy=(int)floor($top/4);$gy<=(int)floor(($bottom-0.000001)/4);$gy++)for($gx=(int)floor($left/4);$gx<=(int)floor(($right-0.000001)/4);$gx++){
            $place=self::at($gx*4+2,$gy*4+2);if(!$place||($cantonId!==null&&$place['canton_id']!==$cantonId))return false;
        }
        return !LuxembourgHydrology::intersectsRect($left,$top,$right,$bottom);
    }
    /** Stable, generated and reviewable catalog, no synthetic preview cities imported. */
    public static function landmarks(): array
    {
        static $landmarks;if($landmarks===null){$catalog=json_decode((string)file_get_contents(dirname(__DIR__,3).'/data/luxembourg_landmarks.json'),true,512,JSON_THROW_ON_ERROR);
            if(!hash_equals(self::hash(),$catalog['geometry_hash']))throw new \RuntimeException('Landmark catalog requires a reviewed geometry rebuild.');$landmarks=$catalog['landmarks'];}return $landmarks;
    }
    public static function boundsForSize(int $x,int $y,int $size): array
    {
        $before=intdiv($size-1,2);$after=intdiv($size,2);return [$x-$before,$y-$before,$x+$after,$y+$after];
    }
}
