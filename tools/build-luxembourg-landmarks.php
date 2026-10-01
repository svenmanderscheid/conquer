<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
use Conquer\Game\World\LuxembourgGeography as G;

$landmarks=[];
$place=static function(string $id,string $type,string $name,array $point,int $size,?string $commune,?string $canton)use(&$landmarks):void{
    for($radius=0;$radius<150;$radius++)for($dy=-$radius;$dy<=$radius;$dy++){
        $columns=abs($dy)===$radius?range(-$radius,$radius):[-$radius,$radius];
        foreach($columns as$dx){$x=(int)round($point[0])+$dx;$y=(int)round($point[1])+$dy;$at=G::at($x,$y);
            if(!$at||($commune!==null&&$at['commune_id']!==$commune)||($canton!==null&&$at['canton_id']!==$canton))continue;
            [$l,$t,$r,$b]=G::boundsForSize($x,$y,$size);if(!G::isDryRectangle($l-.5,$t-.5,$r+.5,$b+.5,$at['canton_id']))continue;
            foreach($landmarks as$other){[$ol,$ot,$or,$ob]=G::boundsForSize($other['x'],$other['y'],$other['footprint']);if(!($r+5<$ol||$l-5>$or||$b+5<$ot||$t-5>$ob))continue 2;}
            $landmarks[]=['id'=>$id,'type'=>$type,'name'=>$name,'commune_id'=>$commune,'canton_id'=>$canton,'x'=>$x,'y'=>$y,'footprint'=>$size];return;
        }
    }
    throw new RuntimeException('No landmark position: '.$id);
};
$capital=array_values(array_filter(G::communes(),static fn($c)=>$c['name']==='Luxembourg'))[0];
$place('crown:krounbuerg','crown','Royal Castle',$capital['point'],7,null,null);
foreach(G::cantons()as$c)$place('canton:'.$c['id'],'canton','Shrine of '.$c['name'],$c['point'],6,null,$c['id']);
foreach(G::communes()as$c)$place('commune:'.$c['id'],'commune','Commune '.$c['name'],$c['point'],4,$c['id'],$c['canton']);
$output=['version'=>G::VERSION,'geometry_hash'=>G::hash(),'minimum_gap'=>5,'landmarks'=>$landmarks];
file_put_contents(ROOT_DIR.'/data/luxembourg_landmarks.json',json_encode($output,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");
echo count($landmarks)." dry, separated landmark sites exported.\n";
