<?php
// Synthetic targets with catalogue-correct HP, only in the isolated preview database.
$db->transaction(static function($db){
    \Conquer\Game\Map\WorldPlacement::lockWorld($db,1);
    foreach([[20209901,90,65,1.0],[20209901,85,70,.5],[20209901,81,61,0.0],[20202401,90,75,.25]] as [$code,$x,$y,$fraction]){
        $definition=\Conquer\Game\Map\MonsterData::get($code);
        $spot=\Conquer\Game\Map\WorldPlacement::findNear($db,1,\Conquer\Game\Map\WorldPlacement::monsterKind($code),$x,$y,null,16,'forest');
        if(!$spot)throw new RuntimeException('No health preview position.');
        $maximum=(int)round($definition['stats']['hp']*$definition['amount']);
        $db->execute("INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current,monster_type,expires_at) VALUES(1,?,?,?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))",[$code,$spot[0],$spot[1],max(1,(int)floor($maximum*$fraction)),$definition['type']]);
    }
});
