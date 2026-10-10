<?php
declare(strict_types=1);
/** Synthetic Luxembourg browser fixture only; never loaded by the game. */
if(PHP_SAPI!=='cli'||!str_starts_with((string)$db->query('SELECT DATABASE()')->fetchColumn(),'conquer_feature_test_'))throw new RuntimeException('Isolated fixture required.');
$rules=\Conquer\Game\Territory\TerritoryRules::defaults();
if(in_array('--territory-report',$argv,true)){
 // Report rendering deliberately uses a small custom garrison; ordinary previews keep real balance.
 $rules['npc_troops']=['commune'=>120,'canton'=>800,'crown'=>1600];
 $rules['npc_balance_revision']=2;
}
$rules['pvp_window_start_hour_utc']=0;$rules['pvp_window_hours']=24;
$rules['crown_anchor']=gmdate('Y-m-d 00:00:00');$rules['crown_duration_hours']=24;
$db->execute('UPDATE territory_profiles SET rules_json=? WHERE world_id=1',[json_encode($rules,JSON_THROW_ON_ERROR)]);
$db->execute("UPDATE worlds SET status='running',started_at=UTC_TIMESTAMP() WHERE id=1");
$target=$db->query("SELECT * FROM territory_targets WHERE world_id=1 AND kind='commune' ORDER BY y DESC LIMIT 1")->fetch();
$db->transaction(static function($db)use($target){
 \Conquer\Game\Map\WorldPlacement::lockWorld($db,1);
 $spot=\Conquer\Game\Map\WorldPlacement::findNear($db,1,'city',(int)$target['x']+10,(int)$target['y'],1,50);
 if(!$spot)throw new RuntimeException('No synthetic Luxembourg town position.');
 $db->execute('UPDATE cities SET coord_x=?,coord_y=? WHERE id=1',$spot);
});
$db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id) VALUES(1,1,'Die Morgenwacht','MW',1)");
$db->execute("INSERT INTO alliance_members(alliance_id,player_id,world_id,role) VALUES(1,1,1,'leader')");
$db->execute("INSERT INTO territory_goals(world_id,alliance_id,target_id,set_by,updated_at) VALUES(1,1,?,1,UTC_TIMESTAMP())",[$target['id']]);
$db->execute('UPDATE city_troops SET count=10000 WHERE city_id=1');
foreach([10208001,10208002,10208003]as$code)\Conquer\Game\Inventory\InventoryService::addItems(1,$code,5);
foreach([2=>'Elara',3=>'Raven']as$pid=>$username){
 $db->transaction(static function($db)use($pid,$username){
  $db->execute('INSERT INTO players(id,username,email,password_hash) VALUES(?,?,?,?)',[$pid,$username,strtolower($username).'@tests.invalid',password_hash('PreviewFixture!2026',PASSWORD_DEFAULT)]);
  \Conquer\Auth\OAuth::createDefaultCity($db,$pid,$username,1);
 });
}
$db->execute("INSERT INTO alliance_members(alliance_id,player_id,world_id,role) VALUES(1,2,1,'member')");
$db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id) VALUES(2,1,'Die Abendwacht','AW',3)");
$db->execute("INSERT INTO alliance_members(alliance_id,player_id,world_id,role) VALUES(2,3,1,'leader')");
// Explicit fixture ownership makes friendly, neutral and rival UI inspectable.
foreach(['food','abbey','rune']as$benefit){
 $id=$db->query("SELECT id FROM territory_targets WHERE world_id=1 AND kind='commune' AND benefit_type=? AND id<>? ORDER BY id LIMIT 1",[$benefit,$target['id']])->fetchColumn();
 if($id)$db->execute('UPDATE territory_targets SET owner_alliance_id=1,owned_since=UTC_TIMESTAMP(),last_income_at=UTC_TIMESTAMP(),ownership_seq=1 WHERE world_id=1 AND id=?',[$id]);
}
$id=$db->query("SELECT id FROM territory_targets WHERE world_id=1 AND kind='commune' AND owner_alliance_id IS NULL AND id<>? ORDER BY id LIMIT 1",[$target['id']])->fetchColumn();
$db->execute('UPDATE territory_targets SET owner_alliance_id=2,owned_since=UTC_TIMESTAMP(),last_income_at=UTC_TIMESTAMP(),ownership_seq=1 WHERE world_id=1 AND id=?',[$id]);
if(in_array('--territory-protection',$argv,true)){
 // A closed custom window verifies that the renderer does not assume 17:00 UTC.
 $rules['pvp_window_start_hour_utc']=((int)gmdate('G')+2)%24;$rules['pvp_window_hours']=1;
 $db->execute('UPDATE territory_profiles SET rules_json=? WHERE world_id=1',[json_encode($rules,JSON_THROW_ON_ERROR)]);
 $shrine=$db->query("SELECT id FROM territory_targets WHERE world_id=1 AND kind='canton' ORDER BY id LIMIT 1")->fetchColumn();
 $db->execute('UPDATE territory_targets SET owner_alliance_id=2,owned_since=UTC_TIMESTAMP(),last_income_at=UTC_TIMESTAMP(),ownership_seq=1 WHERE world_id=1 AND id=?',[$shrine]);
}
