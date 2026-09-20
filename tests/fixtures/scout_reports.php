<?php
// Synthetic scout snapshots for the isolated preview only.
if(!str_starts_with((string)$db->query('SELECT DATABASE()')->fetchColumn(),'conquer_feature_test_'))throw new RuntimeException('Disposable database required.');
$db->execute("INSERT IGNORE INTO players(id,username,email,password_hash)VALUES(2,'Elara','elara@tests.invalid','unused')");
$mastery=\Conquer\Game\Player\MasteryService::snapshot(1,1);
foreach($mastery['nodes'] as &$node)$node['level']=in_array($node['code'],['attack_0','defense_0','gather_0','hunter_0'],true)?5:0;
unset($node);
$treasures=[];
foreach(array_slice(\Conquer\Game\Treasure\TreasureData::all(),0,6,true) as $code=>$t)$treasures[]=['treasure_code'=>(int)$code,'name'=>$t['name'],'level'=>7,'equipped_slot'=>count($treasures)+1];
$full=['type'=>'scout','observed_at'=>'2026-09-20 12:15:00','target_name'=>'Elaras Königreich','target_player_id'=>2,'target_power'=>275842667,'castle_level'=>30,'lord_level'=>49,
 'wall'=>['level'=>21,'durability'=>27000,'durability_max'=>30000,'attack_buff'=>0,'defense_buff'=>37.8],
 'resources'=>['food'=>677085717,'lumber'=>718209265,'stone'=>686321632,'gold'=>663295768],
 'protected_resources'=>['food'=>10000,'lumber'=>10000,'stone'=>10000,'gold'=>10000],
 'troops'=>[50100101=>135251,50300101=>11395,50100501=>539675,50300501=>82351,50200501=>50220,50100601=>2884000,50300601=>9638,50200601=>429756],
 'reinforcements'=>[50100301=>14000,50200401=>5200],'mastery'=>$mastery,'treasures'=>$treasures];
$empty=$full;$empty['target_name']='Leere Garnison';$empty['troops']=[];$empty['reinforcements']=[];$empty['treasures']=[];
foreach($empty['mastery']['nodes'] as &$node)$node['level']=0;
unset($node);
$empty['resources']['food']=100;$empty['protected_resources']['food']=10000;
$old=['type'=>'scout','target_name'=>'Alter Bericht <img src=x onerror=alert(1)>','resources'=>['food'=>900],'troops'=>[]];
$blocked=['type'=>'scout','blocked'=>true,'target_name'=>'Geschützte Stadt','reason'=>'Die Stadt ist vor Spähberichten geschützt.'];
foreach([$full,$empty,$old,$blocked] as $index=>$details)$db->execute("INSERT INTO battle_reports(world_id,attacker_id,attacker_city_id,defender_id,target_type,target_x,target_y,outcome,data_json,created_at)VALUES(1,1,1,2,2,64,67,'scouted',?,DATE_SUB(UTC_TIMESTAMP(),INTERVAL ? MINUTE))",[json_encode($details,JSON_THROW_ON_ERROR),$index]);
$db->execute("INSERT INTO battle_reports(world_id,attacker_id,attacker_city_id,defender_id,target_type,target_x,target_y,outcome,data_json,created_at)VALUES(1,2,1,1,2,64,67,'scouted',?,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 5 MINUTE))",[json_encode($full,JSON_THROW_ON_ERROR)]);
