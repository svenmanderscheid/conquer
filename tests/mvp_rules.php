<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR . '/src'))->register();
require __DIR__ . '/Support/FeatureDatabase.php';
// ResourceTick also reads retired production plots; keep these rule checks
// isolated instead of binding them to the developer's saved cities.
$fixture = new \ConquerTests\FeatureDatabase();
register_shutdown_function(static fn() => $fixture->close());
date_default_timezone_set('UTC');
function verify(bool $ok,string $label): void { if (!$ok) { throw new RuntimeException($label); } echo "PASS $label\n"; }
use Conquer\Game\City\{ResourceTick,CityState,BuildingData};
use Conquer\Game\Map\MonsterData;
use Conquer\Game\March\BattleEngine;
$buildings = array_fill_keys(CityState::BUILDING_CODES,['level'=>1]);
$city = ['id'=>0,'food'=>0,'lumber'=>0,'stone'=>0,'gold'=>0,'last_resource_update'=>gmdate('Y-m-d H:i:s',time()-3600)];
$once=ResourceTick::apply($city,$buildings);
$twice=ResourceTick::apply($once,$buildings);
verify($once['food']===300 && $once['gold']===150,'one hour of offline production');
verify($twice['food']===$once['food'],'computed resource snapshot is not double-counted');
$boosted=ResourceTick::apply($city,$buildings,1,['food_prod_pct'=>0.02]);
verify($boosted['food']===306,'research boosts production by the displayed two percent');
$full=$city;$full['food']=BuildingData::getStorageCaps($buildings)['food'] - 10;
$capped=ResourceTick::apply($full,$buildings);
verify($capped['food']===BuildingData::getStorageCaps($buildings)['food'],'production respects storage capacity');
$researched=ResourceTick::storageCaps($buildings,['food_capacity_pct'=>.15]);
$overflowing=$city;$overflowing['food']=$researched['food']-10;
verify($researched['food']===(int)round(BuildingData::getStorageCaps($buildings)['food']*1.15) && ResourceTick::apply($overflowing,$buildings,1,['food_capacity_pct'=>.15])['food']===$researched['food'],'storage research raises both the displayed ceiling and actual production limit');
$reward=$city;$reward['food']=BuildingData::getStorageCaps($buildings)['food']+2000;
verify(ResourceTick::apply($reward,$buildings)['food']===$reward['food'],'earned rewards above capacity remain available without further production');
$monster=MonsterData::get(20209901);
verify($monster['name']==='Ork-Späher' && $monster['resource_reward']['gold']===75,'starter monster definition and reward resolve canonically');
$battle=BattleEngine::resolveMonster([50100101=>10],['hp_current'=>10],$monster);
verify($battle['monster_killed'] && $battle['attacker_survivors'][50100101]===10,'ten starter soldiers can defeat a scout without losses');
$strong=['stats'=>['hp'=>30,'attack'=>200,'defense'=>5],'level'=>5,'amount'=>100,'required_power'=>1000];
$loss=BattleEngine::resolveMonster([50100101=>10],['hp_current'=>3000],$strong);
verify(!$loss['monster_killed'] && $loss['attacker_losses'][50100101]>0,'stronger enemy causes losses');
$buffed=BattleEngine::resolveMonster([50100101=>10],['hp_current'=>3000],$strong,['infantry_atk'=>0.5]);
verify($buffed['report']['attacker_damage']===$loss['report']['attacker_damage']*1.5,'combat research affects actual damage');
verify($buffed['report']['army_power']>$loss['report']['army_power'],'combat research also increases effective army power');
verify($battle===BattleEngine::resolveMonster([50100101=>10],['hp_current'=>10],$monster),'battle resolution is deterministic');
$data=json_decode(file_get_contents(ROOT_DIR.'/data/monsters.json'),true);
$codes=array_column($data['monsters'],'code');verify(count($codes)===count(array_unique($codes)),'monster codes are unique');
echo "ALL RULE CHECKS PASSED\n";
