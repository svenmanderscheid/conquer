<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!preg_match('/^conquer_feature_test_[a-f0-9]{12}$/D',(string)$db->query('SELECT DATABASE()')->fetchColumn()))throw new RuntimeException('Disposable fixture required.');
$entrance=\Conquer\Game\Dungeon\DungeonEntrance::forWorld(1);
if(!$entrance)throw new RuntimeException('Missing Melusina entrance.');
$db->transaction(static function($db)use($entrance):void{
    \Conquer\Game\Map\WorldPlacement::lockWorld($db,1);
    for($pid=1;$pid<=4;$pid++){
        $spot=\Conquer\Game\Map\WorldPlacement::findNear($db,1,'city',$entrance['x']-12,$entrance['y']+($pid-1)*6,$pid,30);
        if(!$spot)throw new RuntimeException('No quest fixture city position.');
        $db->execute('UPDATE cities SET coord_x=?,coord_y=? WHERE id=?',[...$spot,$pid]);
    }
    $db->execute('UPDATE city_troops SET count=20000 WHERE city_id BETWEEN 1 AND 4');
    $code=20201001;
    foreach(\Conquer\Game\Rewards\RewardCatalog::json('monsters')['monsters'] as $monster){if(($monster['type']??'')==='solo'&&(int)($monster['level']??0)===1){$code=(int)$monster['code'];break;}}
    $definition=\Conquer\Game\Map\MonsterData::get($code);
    $hp=(int)round($definition['stats']['hp']*$definition['amount']);
    for($i=0;$i<6;$i++){
        $spot=\Conquer\Game\Map\WorldPlacement::findNear($db,1,'monster',$entrance['x']-8+$i*3,$entrance['y']-5,null,12);
        if(!$spot)throw new RuntimeException('No quest fixture monster position.');
        $db->execute("INSERT INTO field_monsters(world_id,monster_code,coord_x,coord_y,hp_current,monster_type,expires_at)VALUES(1,?,?,?,?,'solo',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY))",[$code,...$spot,$hp]);
    }
});
// Keep the intro unaccepted. The browser accepts it and uses the actual crafting API.
\Conquer\Game\Inventory\InventoryService::addItems(1,10309001,3,1);
// Expose only this disposable fixture's directory for controlled clock advancement.
echo 'Melusina fixture root: '.$dir."\n";
