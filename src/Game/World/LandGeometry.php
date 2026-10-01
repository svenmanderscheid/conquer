<?php
declare(strict_types=1);
namespace Conquer\Game\World;

/** Pure, deterministic 8x8 world-parcel geometry. */
final class LandGeometry
{
    public const PARCEL_SIZE = 8;
    public const VERSION = 1;

    public static function dimensions(int $mapSize,?int $mapHeight=null): array
    {
        if ($mapSize < 1 || $mapSize > 65535) throw new \DomainException('Ungültige Kartengröße.');
        $mapHeight??=$mapSize;if($mapHeight<1||$mapHeight>65535)throw new \DomainException('Ungültige Kartenhöhe.');
        $count=(int)ceil($mapSize/self::PARCEL_SIZE);
        return ['parcel_size'=>self::PARCEL_SIZE,'map_size'=>$mapSize,'map_width'=>$mapSize,'map_height'=>$mapHeight,'columns'=>$count,'rows'=>(int)ceil($mapHeight/self::PARCEL_SIZE),'geometry_version'=>self::VERSION];
    }

    public static function at(int $mapSize,int $x,int $y,?int $mapHeight=null): array
    {
        if($x<0||$y<0||$x>=$mapSize||$y>=($mapHeight??$mapSize))throw new \DomainException('Diese Koordinate liegt außerhalb der Welt.',409);
        return self::parcel($mapSize,intdiv($x,self::PARCEL_SIZE),intdiv($y,self::PARCEL_SIZE),$mapHeight);
    }

    public static function parcel(int $mapSize,int $parcelX,int $parcelY,?int $mapHeight=null): array
    {
        $d=self::dimensions($mapSize,$mapHeight);$n=$d['columns'];
        if($parcelX<0||$parcelY<0||$parcelX>=$n||$parcelY>=$d['rows'])throw new \DomainException('Ungültiger Landteil.',404);
        $center=($n-1)/2;$centerY=($d['rows']-1)/2;
        $r=max(abs($parcelX-$center)/max($center,.5),abs($parcelY-$centerY)/max($centerY,.5));
        if($r<8/31){
            $zone='center';
            $initial=$r<=3/31?9:($r<=6/31?8:7);
        }elseif($r<20/31){
            $zone='middle';$inward=max(0.0,min(1.0,(20/31-$r)/(12/31)));
            $initial=2+min(4,(int)floor($inward*5));
        }else{$zone='outer';$initial=1;}
        $xMin=$parcelX*self::PARCEL_SIZE;$yMin=$parcelY*self::PARCEL_SIZE;
        $xMax=min($mapSize-1,$xMin+self::PARCEL_SIZE-1);$yMax=min(($mapHeight??$mapSize)-1,$yMin+self::PARCEL_SIZE-1);
        return ['parcel_x'=>$parcelX,'parcel_y'=>$parcelY,'zone'=>$zone,'initial_level'=>$initial,
            'bounds'=>['x_min'=>$xMin,'y_min'=>$yMin,'x_max'=>$xMax,'y_max'=>$yMax],
            'center'=>['x'=>($xMin+$xMax)/2,'y'=>($yMin+$yMax)/2]];
    }

    public static function all(int $mapSize,?int $mapHeight=null): \Generator
    {
        $d=self::dimensions($mapSize,$mapHeight);
        for($py=0;$py<$d['rows'];$py++)for($px=0;$px<$d['columns'];$px++)yield self::parcel($mapSize,$px,$py,$mapHeight);
    }

    /** Outer rectangles of the nested zones, derived from the same parcel geometry. */
    public static function zoneBounds(int $mapSize,?int $mapHeight=null): array
    {
        static $cache=[];$key=$mapSize.':'.($mapHeight??$mapSize);if(isset($cache[$key]))return $cache[$key];$bounds=[];
        foreach(self::all($mapSize,$mapHeight) as $parcel){
            $key=$parcel['zone'];$b=$parcel['bounds'];
            if(!isset($bounds[$key])){$bounds[$key]=$b;continue;}
            foreach(['x_min','y_min'] as $field)$bounds[$key][$field]=min($bounds[$key][$field],$b[$field]);
            foreach(['x_max','y_max'] as $field)$bounds[$key][$field]=max($bounds[$key][$field],$b[$field]);
        }
        return $cache[$mapSize.':'.($mapHeight??$mapSize)]=$bounds;
    }
}
