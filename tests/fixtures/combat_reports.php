<?php
declare(strict_types=1);

// Called only by the disposable preview/test database after PreviewPlayer exists.
return static function (\Conquer\Db\Connection $db): void {
    if (!str_starts_with((string)$db->query('SELECT DATABASE()')->fetchColumn(),'conquer_feature_test_')) throw new RuntimeException('Disposable database required.');
    \Conquer\Game\World\LandProgressService::ensureWorld(1,true);
    foreach ([2=>'Elara',3=>'Nordwacht'] as $pid=>$name) {
        $db->execute('INSERT INTO players(id,username,email,password_hash) VALUES(?,?,?,?)',[$pid,$name,$name.'@tests.invalid',password_hash('PreviewFixture!2026',PASSWORD_DEFAULT)]);
        $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,castle_level,food,lumber,stone,gold) VALUES(?,?,1,?,?,65,12,100000,100000,100000,100000)",[$pid,$pid,$name,70+$pid*5]);
        foreach (\Conquer\Game\City\CityState::BUILDING_CODES as $code) $db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(?,?,7)',[$pid,$code]);
        $db->execute('INSERT INTO kingdom_profiles(player_id,display_name,avatar) VALUES(?,?,?)',[$pid,$name,$pid===2?'archer':'rider']);
    }
    foreach ([1=>'attack_0',2=>'defense_0',3=>'defense_0'] as $pid=>$talent) {
        $db->execute('INSERT INTO player_lord_progress(player_id,world_id,xp) VALUES(?,1,?) ON DUPLICATE KEY UPDATE xp=VALUES(xp)',[$pid,\Conquer\Game\Player\LordLevel::totalForLevel(30)]);
        $db->execute('INSERT INTO player_lord_talents(player_id,world_id,talent_code,rank) VALUES(?,1,?,5)',[$pid,$talent]);
        $slot=1;
        foreach (\Conquer\Game\Treasure\TreasureData::all() as $code=>$def) {
            if (!array_intersect(array_column($def['stats'],'type'),['all_attack','all_defense','all_hp','infantry_defense','cavalry_attack','ranged_attack'])) continue;
            $db->execute('INSERT INTO player_treasures(player_id,treasure_code,fragments) VALUES(?,?,?)',[$pid,$code,100*$pid]);
            $db->execute('INSERT INTO player_treasure_loadouts(player_id,world_id,slot,treasure_code) VALUES(?,1,?,?)',[$pid,$slot++,$code]);
            if($slot>6)break;
        }
    }
    foreach ([50100301=>4200,50200301=>3500,50300301=>2800] as $code=>$count) $db->execute('INSERT INTO city_troops(city_id,troop_code,count) VALUES(2,?,?)',[$code,$count]);
    $db->execute("INSERT INTO reinforcements(march_id,sender_id,sender_city_id,target_player_id,target_city_id,troops_json,state) VALUES(999,3,3,2,2,?, 'active')",[json_encode([50100201=>1000,50200201=>500,50300201=>500])]);
    \Conquer\Game\WorldRules::combatLock(static fn()=> $db->transaction(static fn()=> \Conquer\Game\March\CityCombat::resolve([
        ['player_id'=>1,'city_id'=>1,'troops'=>[50100301=>10000,50200301=>7000,50300301=>4000]],
    ],2,80,65)));
    // Older report exercises the honest fallback without fabricated opponent stats.
    $row=$db->query('SELECT * FROM battle_reports WHERE attacker_id=1 ORDER BY id LIMIT 1')->fetch();
    $legacy=json_decode($row['data_json'],true);unset($legacy['combat']);$legacy['target_name']='Archivbericht';
    $db->execute('INSERT INTO battle_reports(world_id,attacker_id,attacker_city_id,defender_id,target_type,target_id,target_x,target_y,outcome,data_json,created_at) VALUES(1,1,1,2,2,2,80,65,?,?,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY))',[$row['outcome'],json_encode($legacy)]);
};
