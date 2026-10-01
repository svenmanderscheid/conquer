<?php
declare(strict_types=1);
namespace Conquer\Game\World;

/** Same segment/box and polygon rules as assets/world-lux-preview/hydrology.mjs. */
final class LuxembourgHydrology
{
    private static bool $loaded=false;
    private static array $segments=[],$lakes=[],$segmentCells=[],$lakeCells=[];
    private static function load(): void
    {
        if(self::$loaded)return;
        $data=json_decode((string)file_get_contents(dirname(__DIR__,3).'/assets/world-lux-preview/game-hydrology.json'),true,512,JSON_THROW_ON_ERROR);
        foreach($data['rivers']as$r)for($i=1;$i<count($r['points']);$i++){
            $a=$r['points'][$i-1];$b=$r['points'][$i];$radius=$r['width']/2;$id=count(self::$segments);self::$segments[]=['a'=>$a,'b'=>$b,'radius'=>$radius];
            self::index(self::$segmentCells,$id,min($a[0],$b[0])-$radius,min($a[1],$b[1])-$radius,max($a[0],$b[0])+$radius,max($a[1],$b[1])+$radius);
        }
        foreach($data['lakes']as$id=>$l){self::$lakes[$id]=$l;self::index(self::$lakeCells,$id,...array_merge($l['bounds'][0],$l['bounds'][1]));}self::$loaded=true;
    }
    private static function index(array &$cells,int $id,float $l,float $t,float $r,float $b): void
    {
        for($y=(int)floor($t/32);$y<=(int)floor($b/32);$y++)for($x=(int)floor($l/32);$x<=(int)floor($r/32);$x++)$cells[$x.':'.$y][]=$id;
    }
    private static function query(array $cells,float $l,float $t,float $r,float $b): array
    {
        $out=[];for($y=(int)floor($t/32);$y<=(int)floor($b/32);$y++)for($x=(int)floor($l/32);$x<=(int)floor($r/32);$x++)foreach($cells[$x.':'.$y]??[]as$id)$out[$id]=true;return array_keys($out);
    }
    private static function distance2(float $x,float $y,array $a,array $b): float
    {
        $dx=$b[0]-$a[0];$dy=$b[1]-$a[1];$t=$dx||$dy?max(0,min(1,(($x-$a[0])*$dx+($y-$a[1])*$dy)/($dx*$dx+$dy*$dy))):0;return ($x-$a[0]-$t*$dx)**2+($y-$a[1]-$t*$dy)**2;
    }
    private static function crosses(array $a,array $b,float $l,float $t,float $r,float $bottom): bool
    {
        $lo=0;$hi=1;foreach([[0,$l,$r],[1,$t,$bottom]]as[$axis,$min,$max]){$d=$b[$axis]-$a[$axis];if(!$d){if($a[$axis]<$min||$a[$axis]>$max)return false;continue;}$t0=($min-$a[$axis])/$d;$t1=($max-$a[$axis])/$d;$lo=max($lo,min($t0,$t1));$hi=min($hi,max($t0,$t1));if($lo>$hi)return false;}return true;
    }
    private static function lakeContains(array $lake,float $x,float $y): bool
    {
        if($x<$lake['bounds'][0][0]||$x>$lake['bounds'][1][0]||$y<$lake['bounds'][0][1]||$y>$lake['bounds'][1][1])return false;
        $inside=false;foreach($lake['rings']as$ring)for($i=0,$j=count($ring)-1;$i<count($ring);$j=$i++){$a=$ring[$i];$b=$ring[$j];if(($a[1]>$y)!==($b[1]>$y)&&$x<($b[0]-$a[0])*($y-$a[1])/($b[1]-$a[1])+$a[0])$inside=!$inside;}return $inside;
    }
    public static function waterAt(float $x,float $y): bool
    {
        self::load();$key=(int)floor($x/32).':'.(int)floor($y/32);
        foreach(self::$segmentCells[$key]??[]as$id){$s=self::$segments[$id];if(self::distance2($x,$y,$s['a'],$s['b'])<=$s['radius']**2)return true;}
        foreach(self::$lakeCells[$key]??[]as$id)if(self::lakeContains(self::$lakes[$id],$x,$y))return true;return false;
    }
    public static function intersectsRect(float $l,float $t,float $r,float $b): bool
    {
        self::load();$corners=[[$l,$t],[$r,$t],[$l,$b],[$r,$b]];
        foreach(self::query(self::$segmentCells,$l,$t,$r,$b)as$id){$s=self::$segments[$id];if(self::crosses($s['a'],$s['b'],$l,$t,$r,$b))return true;
            $d=INF;foreach([$s['a'],$s['b']]as$p)$d=min($d,max($l-$p[0],0,$p[0]-$r)**2+max($t-$p[1],0,$p[1]-$b)**2);
            foreach($corners as$p)$d=min($d,self::distance2($p[0],$p[1],$s['a'],$s['b']));if($d<=$s['radius']**2)return true;
        }
        foreach(self::query(self::$lakeCells,$l,$t,$r,$b)as$id){$lake=self::$lakes[$id];foreach($corners as$p)if(self::lakeContains($lake,...$p))return true;foreach($lake['rings']as$ring)for($i=0;$i<count($ring);$i++)if(self::crosses($ring[$i],$ring[($i+1)%count($ring)],$l,$t,$r,$b))return true;}return false;
    }
}
