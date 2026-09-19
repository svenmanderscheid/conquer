<?php
declare(strict_types=1);
namespace Conquer\Game\Map;

/** Same world geometry that is sent to the canvas renderer. Banks stay unbuildable. */
final class WorldTerrain
{
    public static function definition(): array
    {
        static $data;
        return $data ??= json_decode(file_get_contents(dirname(__DIR__,3).'/data/world_terrain.json'),true,512,JSON_THROW_ON_ERROR);
    }
    /** Dominant biome; mirrors world-landscape.js including curved borders. */
    public static function biomeAt(float $x,float $y): string
    {
        $b=self::definition()['biomes']??[];$cx=$b['centerX']??128;$cy=$b['centerY']??128;$width=$b['transitionWidth']??52;
        $smooth=static function(float $v):float{$v=max(0,min(1,$v));return $v*$v*(3-2*$v);};
        $east=$smooth(.5+($x-$cx-13*sin(($y-$cy)/34)-5*sin(($y-$cy)/13))/$width);
        $south=$smooth(.5+($y-$cy+15*sin(($x-$cx)/39)+4*sin(($x-$cx)/15))/$width);
        $weights=[(1-$east)*(1-$south),$east*(1-$south),(1-$east)*$south,$east*$south];
        return ['forest','ice','sand','lava'][array_search(max($weights),$weights,true)];
    }
    public static function isWater(float $x,float $y): bool
    {
        $d=self::definition();$r=$d['rivers'];
        foreach($r['sides'] as $side){$rx=$side+sin($y/$r['period']+$side)*$r['amplitude']+sin($y/$r['detailPeriod'])*$r['detailAmplitude'];if(abs($x-$rx)<$r['bankWidth'])return true;}
        foreach($d['streams'] as $s){$sx=max($s['from'],min($s['to'],$x));$sy=$s['base']+sin($sx/$s['period'])*$s['amplitude']+sin($sx/$s['detailPeriod'])*$s['detailAmplitude'];if(hypot($x-$sx,$y-$sy)<$d['streamBankWidth'])return true;}
        foreach($d['lakes'] as $l){$dx=($x-$l['x'])/$l['rx'];$dy=($y-$l['y'])/$l['ry'];$a=atan2($dy,$dx);$edge=(1+sin($a*3+.8)*.065+cos($a*5)*.035)*$d['lakeBankScale'];if(hypot($dx,$dy)<$edge)return true;}
        return false;
    }
    /** Coordinates describe occupied tile centers, including all four corners and shoreline. */
    public static function isDryRectangle(int $left,int $top,int $right,int $bottom): bool
    {
        // Quarter-tile samples plus the reserved bank margin conservatively cover shore edges.
        for($y=$top-.5;$y<=$bottom+.5;$y+=.25)for($x=$left-.5;$x<=$right+.5;$x+=.25)if(self::isWater($x,$y))return false;
        return true;
    }
}
