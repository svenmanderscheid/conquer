<?php
declare(strict_types=1);
/** Calendar protection requires no database and never depends on viewer membership. */
require dirname(__DIR__).'/src/Game/Territory/TerritoryRules.php';
use Conquer\Game\Territory\TerritoryRules as Rules;
function checkProtection(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
$rules=Rules::defaults();$at=strtotime('2026-10-09 16:59:59 UTC');
foreach(['commune','canton'] as $kind){
    $target=['kind'=>$kind,'active'=>true,'owner_alliance_id'=>null];
    $window=Rules::window($rules,$at);
    $p=Rules::protection($target,$window,$rules);
    checkProtection($p['state']==='neutral'&&$p['until']===null,"Neutral $kind has no protection countdown");
    $target['owner_alliance_id']=7;
    $p=Rules::protection($target,$window,$rules);
    checkProtection($p['state']==='protected'&&$p['until']==='2026-10-09T17:00:00Z',"Occupied $kind is protected until opening");
    foreach(['17:00:00'=>'open','20:59:59'=>'open','21:00:00'=>'protected'] as $hour=>$state){
        $p=Rules::protection($target,Rules::window($rules,strtotime("2026-10-09 $hour UTC")),$rules);
        checkProtection($p['state']===$state,"$kind exact boundary $hour");
        if($state==='protected')checkProtection($p['until']==='2026-10-10T17:00:00Z','Next day comes from server calendar');
    }
    $target['active']=false;
    $p=Rules::protection($target,$window,$rules);
    checkProtection($p['state']==='inactive'&&$p['until']===null,"Inactive $kind does not invent an unlock time");
}
$overnight=array_replace($rules,['pvp_window_start_hour_utc'=>23,'pvp_window_hours'=>4]);
$target=['kind'=>'commune','active'=>true,'owner_alliance_id'=>7];
checkProtection(Rules::protection($target,Rules::window($overnight,strtotime('2026-10-10 02:59:59 UTC')),$overnight)['state']==='open','Overnight window remains open');
checkProtection(Rules::protection($target,Rules::window($overnight,strtotime('2026-10-10 03:00:00 UTC')),$overnight)['until']==='2026-10-10T23:00:00Z','Overnight end shows next opening');
$crown=['kind'=>'crown','active'=>true,'owner_alliance_id'=>null];
$p=Rules::protection($crown,Rules::window($rules,$at,true),$rules);
checkProtection($p['state']==='protected'&&$p['period_seconds']===14*86400&&$p['until']==='2026-10-12T17:00:00Z','Neutral crown follows its own fortnightly calendar');
$custom=array_replace($rules,['pvp_window_start_hour_utc'=>5,'pvp_window_hours'=>2]);
checkProtection(Rules::protection($target,Rules::window($custom,strtotime('2026-10-09 04:00:00 UTC')),$custom)['until']==='2026-10-09T05:00:00Z','World settings override default protection times');
