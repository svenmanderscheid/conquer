<?php
declare(strict_types=1);
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();

use Conquer\Game\Dungeon\DungeonRules;

$ok=0;$check=static function(bool $condition,string $message)use(&$ok):void{if(!$condition)throw new RuntimeException($message);$ok++;};
$member=static fn(string$role,int$count=1000):array=>['role'=>$role,'specialty_points'=>1,'troops'=>[50100101=>$count],'attack'=>45*$count,'defense'=>35*$count,'hp'=>130*$count,'gather_bonus'=>$role==='gather'?.2:0,'hunter_bonus'=>$role==='hunter'?.25:0];
$party=[$member('attack'),$member('defense'),$member('gather'),$member('hunter')];

$catalog=DungeonRules::catalog();
$check(count($catalog['dungeons'])===6&&count($catalog['difficulties'])===2,'catalog shape');
$check(count(array_filter($catalog['dungeons'],static fn(array$d):bool=>(float)($d['enemy_factor']??0)===3.5))===6,'all new dungeon definitions use the cooperative enemy factor');
$rotation=DungeonRules::weeklyRotation(new DateTimeImmutable('2026-09-12T18:00:00+02:00'));
$check($rotation['week_start']==='2026-09-07T00:00:00+00:00'&&count($rotation['available'])===3,'UTC Monday rotation');
$preview=DungeonRules::preview(new DateTimeImmutable('2026-09-12T18:00:00+02:00'));
$check($preview['week_start']==='2026-09-14T00:00:00+00:00'&&$preview['available']!==$rotation['available'],'next week preview');
$check(DungeonRules::validateParty($party),'valid mixed party');
foreach ([[$member('attack')]] as $bad){try{DungeonRules::validateParty($bad);$check(false,'invalid party accepted');}catch(InvalidArgumentException){$ok++;}}
$check(DungeonRules::validateParty([$member('attack'),$member('attack')]),'duplicate roles are valid');
$utilityParty=[$member('gather'),$member('hunter')];
$check(DungeonRules::validateParty($utilityParty),'gather and hunter party is valid');
$fake=$member('attack');$fake['specialty_points']=0;try{DungeonRules::validateParty([$fake,$member('defense')]);$check(false,'fake role accepted');}catch(InvalidArgumentException){$ok++;}
$badStat=$member('attack');$badStat['attack']=INF;try{DungeonRules::validateParty([$badStat,$member('defense')]);$check(false,'infinite stat accepted');}catch(InvalidArgumentException){$ok++;}
$badTroop=$member('attack');$badTroop['troops']=[99999999=>10];try{DungeonRules::validateParty([$badTroop,$member('defense')]);$check(false,'unknown troop accepted');}catch(InvalidArgumentException){$ok++;}
$duplicate=$member('attack');$duplicate['player_id']=7;$other=$member('defense');$other['player_id']=7;try{DungeonRules::validateParty([$duplicate,$other]);$check(false,'duplicate player accepted');}catch(InvalidArgumentException){$ok++;}

$d=$catalog['dungeons'][0];
$pending=DungeonRules::simulate($d,$party,'normal','balanced',42,null);
$check($pending['decision_required']&&!$pending['success']&&count($pending['rounds'])>0&&$pending['encounters_done']===1&&$pending['encounters_total']===3,'simulation pauses after one encounter');
$a=DungeonRules::simulate($d,$party,'normal','balanced',42,'explore');$b=DungeonRules::simulate($d,$party,'normal','balanced',42,'explore');
$check($a===$b&&$a['success']&&$a['fragments']>3,'seeded successful simulation and gathering loot');
$utilityResult=DungeonRules::simulate($d,$utilityParty,'normal','balanced',42,'explore');
$check($utilityResult['fragments']>3&&$utilityResult['duration_seconds']<(int)ceil($d['base_duration']*1.25),'gather and hunter bonuses apply without combat roles');
$weak=[$member('attack',10),$member('defense',10)];$check(!DungeonRules::simulate($d,$weak,'normal','balanced',42,'skip')['success'],'minimum army loses');
$typical=[$member('attack'),$member('defense')];$check(DungeonRules::simulate($d,$typical,'normal','balanced',42,'skip')['success'],'typical two-player army wins normal');
$doubleKo=$d;$doubleKo['encounters'][0]=['name'=>'Gleichzeitiger Fall','attack'=>100000,'defense'=>0,'hp'=>1];$ko=DungeonRules::simulate($doubleKo,$typical,'normal','balanced',42,null);$check(!$ko['success']&&!$ko['decision_required']&&$ko['hp_remaining']===0,'simultaneous knockout never heals or advances');
$check(DungeonRules::simulate($d,$typical,'hard','balanced',42,'skip')['success'],'typical two-player army can win hard');
$skip=DungeonRules::simulate($d,$party,'normal','balanced',42,'skip');
$check($skip['duration_seconds']===(int)ceil($d['base_duration']*.75),'hunter reduces base travel');
$check($a['duration_seconds']===(int)ceil($d['base_duration']*1.25*.75)&&$a['duration_seconds']>$skip['duration_seconds'],'exploration adds 25 percent travel before hunter reduction');
$check($a['encounters_done']===4&&$a['encounters_total']===4,'exploration progress counts side chamber');
$check(DungeonRules::resolveSideChoice([], 'cautious',100,99)===null,'vote remains open');
$check(DungeonRules::resolveSideChoice([], 'cautious',100,100)==='skip'&&DungeonRules::resolveSideChoice(['explore','skip'],'risky',100,100)==='explore','deadline stance fallback');
$maxRound=max(array_column($a['rounds'],'round'));$check($maxRound<=18,'round cap');

$itemCodes=array_column(json_decode(file_get_contents(ROOT_DIR.'/data/items.json'),true,64,JSON_THROW_ON_ERROR)['items'],'code');
$treasureCodes=array_column(json_decode(file_get_contents(ROOT_DIR.'/data/treasures.json'),true,64,JSON_THROW_ON_ERROR)['treasures'],'code');
foreach($catalog['dungeons']as$def){$check(in_array($def['treasure_code'],$treasureCodes,true),'valid treasure reward');foreach($def['item_codes']as$code)$check(in_array($code,$itemCodes,true),'valid item reward');}
$balance=[];
foreach($catalog['dungeons']as$def)foreach(['normal','hard']as$difficulty){$typicalWins=0;foreach([1,42,777,2147483647]as$seed){$weakRun=DungeonRules::simulate($def,$weak,$difficulty,'balanced',$seed,'skip');$run=DungeonRules::simulate($def,$typical,$difficulty,'balanced',$seed,'skip');$again=DungeonRules::simulate($def,$typical,$difficulty,'balanced',$seed,'skip');$check(!$weakRun['success'],'minimum T1 party stays below dungeon threshold');$check($run===$again,'all catalog simulations remain deterministic');if($run['success'])$typicalWins++;}$balance[$def['dungeon_code'].':'.$difficulty]=$typicalWins;}
$check(count(array_filter($balance,static fn(int$n):bool=>$n===4))>=6,'typical party wins a sensible share across seeds');

$profileParty=static function(array$mix,int$total):array{
    $defs=[];foreach(\Conquer\Game\City\TroopData::t1()as$t)$defs[(int)$t['type']]=$t;$counts=[];$assigned=0;
    foreach($mix as$i=>$row){$count=$i===array_key_last($mix)?$total-$assigned:(int)floor($total*(int)$row['percent']/100);$counts[(int)$row['type']]=$count;$assigned+=$count;}
    $party=[];foreach(['attack','defense']as$i=>$role){$army=[];$types=[];$a=$de=$hp=0.0;foreach($counts as$type=>$count){$share=intdiv($count,2)+($i===0?$count%2:0);if(!$share)continue;$t=$defs[$type];$army[(string)$t['code']]=$share;$ta=$t['attack']*$share*($role==='attack'?1.02:1);$td=$t['defense']*$share*($role==='defense'?1.02:1);$th=$t['hp']*$share*($role==='defense'?1.01:1);$types[(string)$type]=['attack'=>$ta,'defense'=>$td,'hp'=>$th];$a+=$ta;$de+=$td;$hp+=$th;}$party[]=['role'=>$role,'specialty_points'=>1,'troops'=>$army,'attack'=>$a,'defense'=>$de,'hp'=>$hp,'type_stats'=>$types];}return$party;
};
$names=[];$mixes=[];
foreach($catalog['dungeons']as$def){$guidance=DungeonRules::guidance($def);$names[]=$guidance['profile_name'];$mixes[]=json_encode($guidance['mix']);$check(array_sum(array_column($guidance['mix'],'percent'))===100,'profile mix sums to 100 for '.$def['dungeon_code']);
    $check($guidance['requirements']['normal']['skip']>=12000,'normal guidance needs a meaningful cooperative army for '.$def['dungeon_code']);
    $check($guidance['requirements']['hard']['skip']>=20000,'hard guidance needs a meaningful cooperative army for '.$def['dungeon_code']);
    $check($guidance['requirements']['normal']['explore']>=$guidance['requirements']['normal']['skip']&&$guidance['requirements']['hard']['explore']>=$guidance['requirements']['hard']['skip'],'side-room guidance never understates the safer route for '.$def['dungeon_code']);
    foreach(['normal','hard']as$difficulty)foreach(['skip','explore']as$choice){$recommended=$profileParty($guidance['mix'],$guidance['requirements'][$difficulty][$choice]);foreach([1,42,777,20260912,2147483647]as$seed){$run=DungeonRules::simulate($def,$recommended,$difficulty,'balanced',$seed,$choice);$max=DungeonRules::effectivePartyHp($def,$recommended);$check($run['success']&&$run['hp_remaining']>=$max*.35,'computed guidance is safe across reference samples');}}
    $disadvantaged=array_reverse($guidance['mix']);foreach($disadvantaged as$i=>&$row)$row['type']=$guidance['mix'][$i]['type'];unset($row);$count=$guidance['requirements']['hard']['explore'];$good=$profileParty($guidance['mix'],$count);$bad=$profileParty($disadvantaged,$count);$goodRun=DungeonRules::simulate($def,$good,'hard','balanced',42,'explore');$badRun=DungeonRules::simulate($def,$bad,'hard','balanced',42,'explore');$check($goodRun['hp_remaining']>$badRun['hp_remaining'],'recommended formation beats disadvantaged mix for '.$def['dungeon_code']);
}
$check(count(array_unique($names))===6&&count(array_unique($mixes))===6,'all six dungeons have distinct named formations');
$legacy=$catalog['dungeons'][0];unset($legacy['army_profile']);$legacyBefore=$legacy;$legacyGuidance=DungeonRules::guidance($legacy);$check($legacy===$legacyBefore&&!empty($legacyGuidance['requirements']['hard']['explore'])&&array_sum(array_column($legacyGuidance['mix'],'percent'))===100,'legacy definition receives neutral advisory guidance without mutation');$withTypes=$profileParty($catalog['dungeons'][0]['army_profile']['mix'],300);$withoutTypes=array_map(static function(array$m):array{unset($m['type_stats']);return$m;},$withTypes);$check(DungeonRules::simulate($legacy,$withTypes,'normal','balanced',42,'skip')===DungeonRules::simulate($legacy,$withoutTypes,'normal','balanced',42,'skip'),'saved definitions without profiles retain legacy aggregate combat math');
echo "ALL DUNGEON RULE CHECKS PASSED ($ok assertions).\n";
echo 'BALANCE typical wins per 4 seeds: '.json_encode($balance,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
