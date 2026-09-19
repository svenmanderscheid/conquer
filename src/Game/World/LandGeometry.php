<?php
declare(strict_types=1);
namespace Conquer\Game\World;

/** Pure, deterministic 8x8 world-parcel geometry. */
final class LandGeometry
{
    public const PARCEL_SIZE = 8;
    public const VERSION = 1;

    public static function dimensions(int $mapSize): array
    {
        if ($mapSize < 1 || $mapSize > 65535) throw new \DomainException('Ungültige Kartengröße.');
        $count=(int)ceil($mapSize/self::PARCEL_SIZE);
        return ['parcel_size'=>self::PARCEL_SIZE,'map_size'=>$mapSize,'columns'=>$count,'rows'=>$count,'geometry_version'=>self::VERSION];
    }

    public static function at(int $mapSize,int $x,int $y): array
    {
        if($x<0||$y<0||$x>=$mapSize||$y>=$mapSize)throw new \DomainException('Diese Koordinate liegt außerhalb der Welt.',409);
        return self::parcel($mapSize,intdiv($x,self::PARCEL_SIZE),intdiv($y,self::PARCEL_SIZE));
    }

    public static function parcel(int $mapSize,int $parcelX,int $parcelY): array
    {
        $d=self::dimensions($mapSize);$n=$d['columns'];
        if($parcelX<0||$parcelY<0||$parcelX>=$n||$parcelY>=$n)throw new \DomainException('Ungültiger Landteil.',404);
        $center=($n-1)/2;$radius=max($center,.5);
        $q=max(abs($parcelX-$center),abs($parcelY-$center));$r=$q/$radius;
        if($r<8/31){
            $zone='center';
            $initial=$r<=3/31?9:($r<=6/31?8:7);
        }elseif($r<20/31){
            $zone='middle';$inward=max(0.0,min(1.0,(20/31-$r)/(12/31)));
            $initial=2+min(4,(int)floor($inward*5));
        }else{$zone='outer';$initial=1;}
        $xMin=$parcelX*self::PARCEL_SIZE;$yMin=$parcelY*self::PARCEL_SIZE;
        $xMax=min($mapSize-1,$xMin+self::PARCEL_SIZE-1);$yMax=min($mapSize-1,$yMin+self::PARCEL_SIZE-1);
        return ['parcel_x'=>$parcelX,'parcel_y'=>$parcelY,'zone'=>$zone,'initial_level'=>$initial,
            'bounds'=>['x_min'=>$xMin,'y_min'=>$yMin,'x_max'=>$xMax,'y_max'=>$yMax],
            'center'=>['x'=>($xMin+$xMax)/2,'y'=>($yMin+$yMax)/2]];
    }

    public static function all(int $mapSize): \Generator
    {
        $n=self::dimensions($mapSize)['columns'];
        for($py=0;$py<$n;$py++)for($px=0;$px<$n;$px++)yield self::parcel($mapSize,$px,$py);
    }

    /** Outer rectangles of the nested zones, derived from the same parcel geometry. */
    public static function zoneBounds(int $mapSize): array
    {
        static $cache=[];if(isset($cache[$mapSize]))return $cache[$mapSize];$bounds=[];
        foreach(self::all($mapSize) as $parcel){
            $key=$parcel['zone'];$b=$parcel['bounds'];
            if(!isset($bounds[$key])){$bounds[$key]=$b;continue;}
            foreach(['x_min','y_min'] as $field)$bounds[$key][$field]=min($bounds[$key][$field],$b[$field]);
            foreach(['x_max','y_max'] as $field)$bounds[$key][$field]=max($bounds[$key][$field],$b[$field]);
        }
        return $cache[$mapSize]=$bounds;
    }
}
