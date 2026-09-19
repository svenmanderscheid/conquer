<?php
// Loaded only by the disposable preview; never a public endpoint.
use Conquer\Game\March\{BattleEngine,MonsterReport};
use Conquer\Game\Player\LordLevel;
use Conquer\Game\Treasure\{TreasureData,TreasureService};
LordLevel::addXp(1,LordLevel::totalForLevel(20),1,'monster-report-preview');
$db->execute("INSERT INTO player_lord_talents(player_id,world_id,talent_code,rank) VALUES(1,1,'attack_0',5),(1,1,'hunter_0',3)");
$slot=1;
foreach(array_slice(array_keys(TreasureData::all()),0,3) as $code){TreasureService::addFragments(1,(int)$code,100);TreasureService::equipTreasure(1,(int)$code,$slot++,7,1);}
$source=MonsterReport::capture(1,1,1);
$buffs=\Conquer\Game\Research\BuffEngine::getBuffs(1,1);
$definition=\Conquer\Game\Map\MonsterData::get(20209901);
$troops=[50100101=>500,50200101=>500,50300101=>500];
$baseReport=BattleEngine::resolveMonster($troops,['hp_current'=>1000,'monster_code'=>20209901],$definition,$buffs)['report'];
$baseReport['source_snapshot']=$source;
$baseReport['loot']=['food'=>106000,'lumber'=>106000,'stone'=>21200,'gold'=>5300,'gems'=>30];
$baseReport['lord_xp']=450;
$baseReport['item_rewards']=[['code'=>10103001,'name'=>'5 Minuten Beschleunigung','count'=>3]];
$baseReport['charm']=['x'=>75,'y'=>65];
$legacy=$baseReport;unset($legacy['source_snapshot'],$legacy['combat_snapshot'],$legacy['monster_snapshot'],$legacy['report_version']);
// Stay below the current training monster's required power (40); ten per type
// now total 122 power and correctly win under the imported balance catalog.
$lost=BattleEngine::resolveMonster([50100101=>1,50200101=>1,50300101=>1],['hp_current'=>1000000,'monster_code'=>20209901],$definition,$buffs)['report'];
if($lost['outcome']==='attacker_wins')throw new RuntimeException('Monster-report defeat fixture must remain below the victory threshold.');
$lost['source_snapshot']=$source;$lost['loot']=[];
$rally=$baseReport;$rally['type']='monster_rally';$rally['rally_combat_snapshot']=$baseReport['combat_snapshot'];
foreach(['attack','defense','hp','count'] as $key)$rally['rally_combat_snapshot'][$key]*=3;
foreach([$legacy,$lost,$rally,$baseReport] as $i=>$detail){
    $db->execute("INSERT INTO marches(player_id,world_id,march_type,origin_city_id,target_x,target_y,target_type,target_id,troops_json,haul_json,departure_time,arrival_time,return_time,state) VALUES(1,1,5,1,75,65,3,999,'{}','{}',DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 MINUTE),UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 10 MINUTE),?)",[$i===0?'complete':'returning']);
    $march=$db->lastInsertId();
    $db->execute('INSERT INTO battle_reports(world_id,march_id,attacker_id,attacker_city_id,target_type,target_id,target_x,target_y,outcome,data_json) VALUES(1,?,1,1,3,999,75,65,?,?)',[$march,$detail['outcome'],json_encode($detail)]);
}
