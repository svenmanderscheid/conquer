<?php
declare(strict_types=1);
/** Pure arena and expedition rules with actual catalogue keys; does not read or mutate player accounts. */
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
use Conquer\Game\Kingdom\KingdomService;
use Conquer\Game\Research\ResearchData;
use Conquer\Game\Expedition\{ExpeditionRules,ExpeditionException};
use Conquer\Game\City\TroopData;
function combatCheck(bool $ok,string $label): void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
function maxResearchBuff(string $code): array{$n=ResearchData::get($code);if(!$n)throw new RuntimeException($code);return [ResearchData::buffKey($n)=>(float)$n['levels'][count($n['levels'])-1]['ability_value']];}
$inf=50100101;$arch=50200101;$cav=50300101;
$baseline=KingdomService::simulateArena([$inf=>10],[$inf=>10]);
combatCheck($baseline['challenger_score']===82 && $baseline['opponent_score']===82 && $baseline['outcome']==='draw','unbuffed identical fighter armies tie at 82 points with the reference T1 stats');
$oldCounter=KingdomService::simulateArena([$inf=>10],[$cav=>10]);
combatCheck($oldCounter['challenger_score']===98,'the existing unbuffed twenty-percent type advantage is unchanged');
$normal=KingdomService::simulateArena([$inf=>10],[$inf=>10],['troops_atk'=>.2,'infantry_atk'=>.1]);
combatCheck($normal['challenger_score']===85 && $normal['opponent_score']===82,'general and troop-type research both improve actual arena score');
$defender=KingdomService::simulateArena([$inf=>10],[$inf=>10],[],['infantry_hp'=>.5]);
combatCheck($defender['opponent_score']===88 && $defender['outcome']==='opponent_wins','the accepting player receives their own HP research');
$counter=maxResearchBuff('infantry_atk_against_archer');
$full=KingdomService::simulateArena([$inf=>10],[$arch=>10],$counter);
$half=KingdomService::simulateArena([$inf=>10],[$arch=>5,$inf=>5],$counter);
$none=KingdomService::simulateArena([$inf=>10],[$inf=>10],$counter);
combatCheck($full['challenger_score']===86 && $half['challenger_score']===84 && $none['challenger_score']===82,'counter research is weighted by the actual share of its target in the enemy army');
foreach ([[$inf,$arch,'infantry','archer'],[$arch,$cav,'archer','cavalry'],[$cav,$inf,'cavalry','infantry']] as [$own,$enemy,$source,$target]){
    foreach(['hp','def','atk'] as $stat){
        $key=$source.'_'.$stat.'_against_'.$target;
        $without=KingdomService::simulateArena([$own=>10],[$enemy=>10]);
        $with=KingdomService::simulateArena([$own=>10],[$enemy=>10],maxResearchBuff($key));
        combatCheck($with['challenger_score']>$without['challenger_score'] && $with['opponent_score']===$without['opponent_score'],$key.' changes only the researched army against its intended enemy');
    }
}
$composition=maxResearchBuff('infantrys_atk_when_composed_of_infantry_only');
$pure=KingdomService::simulateArena([$inf=>10],[$inf=>10],$composition);
$mixed=KingdomService::simulateArena([$inf=>5,$arch=>5],[$inf=>10],$composition);
$mixedBase=KingdomService::simulateArena([$inf=>5,$arch=>5],[$inf=>10]);
combatCheck($pure['challenger_score']===86 && $mixed===$mixedBase,'single-type composition research activates for a pure army and not a mixed army');
combatCheck($pure['losses']===0 && $pure['resource_cost']===0,'research keeps consensual sparring free of troop and resource losses');
$rally=maxResearchBuff('rally_attack_amount');$cap=ExpeditionRules::missionCapacity($rally);
combatCheck($cap===7000 && ExpeditionRules::publicRules($rally)['max_troops_per_mission']===7000,'maximum rally research exposes the same 7000-troop cap used by expedition validation');
combatCheck(array_sum(ExpeditionRules::troops([$inf=>7000],$cap)['troops'])===7000,'the researched expedition cap accepts 7000 actual troops');
try{ExpeditionRules::troops([$inf=>7001],$cap);throw new RuntimeException('Over-cap army accepted');}catch(ExpeditionException $e){combatCheck($e->errorCode==='INVALID_TROOPS','army over researched expedition limit is rejected');}
try{ExpeditionRules::troops([$inf=>5001]);throw new RuntimeException('Unresearched over-cap army accepted');}catch(ExpeditionException $e){combatCheck(true,'unresearched expedition cap remains 5000');}
combatCheck(ExpeditionRules::missionTiming([])===['march_seconds'=>20,'return_seconds'=>20],'unresearched expedition travel times remain unchanged');
$speed=maxResearchBuff('troop_speed_when_participating_a_rally');
$timing=ExpeditionRules::missionTiming($speed);$public=ExpeditionRules::publicRules($speed);
combatCheck($timing===['march_seconds'=>15,'return_seconds'=>15] && $public['march_seconds']===15 && $public['return_seconds']===15,'rally speed shortens both journeys and agrees with the displayed rule snapshot');
$combined=ExpeditionRules::missionTiming(array_merge($speed,['troops_spd'=>.4,'march_speed'=>.1]));
combatCheck($combined===['march_seconds'=>11,'return_seconds'=>11],'general army and equipped march speed also improve coalition travel');
echo "ALL RESEARCH COMBAT CHECKS PASSED\n";
