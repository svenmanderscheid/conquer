<?php
declare(strict_types=1);
/** NPC progression outcomes against a disposable database; no real player data is modified. */
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';
date_default_timezone_set('UTC');

use Conquer\Db\Connection;
use Conquer\Game\Research\{BuffEngine,ResearchEffects};
use Conquer\Game\Shrine\{CongressService,ShrineService};
use Conquer\Game\World\WorldContext;

function expectNpc(bool $ok,string $label):void
{
    if(!$ok)throw new RuntimeException($label);
    echo "PASS $label\n";
}

$fixture=new \ConquerTests\FeatureDatabase();
try{
    $db=Connection::getInstance();WorldContext::bind(1);
    \Conquer\Logger::init($fixture->sessionPath().'/http.log');
    // An open elemental event allows the real solo dispatch/arrival path to run.
    $db->execute('SET timestamp='.strtotime('2026-10-10 16:00:00 UTC'));
    for($player=1;$player<=2;$player++){
        $db->execute("INSERT INTO players(id,username,email,password_hash)VALUES(?,?,?,'unused')",[$player,'NpcBalance'.$player,'npc'.$player.'@invalid.test']);
        $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y)VALUES(?,?,1,'NPC test',?,40)",[$player,$player,30+$player]);
        $db->execute("INSERT INTO city_buildings(city_id,building_code,level)VALUES(?,'castle',10)",[$player]);
        $db->execute('INSERT INTO city_troops(city_id,troop_code,count)VALUES(?,50200201,1000000)',[$player]);
    }
    $db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id)VALUES(1,1,'NPC balance alliance','NPC',1)");
    $db->execute("INSERT INTO alliance_members(alliance_id,player_id,role)VALUES(1,1,'leader'),(1,2,'member')");
    $targets=[
        ['id'=>101,'code'=>'SHRINE_FOREST','tier'=>'C','reference'=>1500000,'old'=>[50100101=>50000,50200101=>50000]],
        ['id'=>102,'code'=>'SHRINE_ICE','tier'=>'B','reference'=>1500000,'old'=>[50100301=>100000,50200301=>100000]],
        ['id'=>103,'code'=>'SHRINE_SAND','tier'=>'A','reference'=>1500000,'old'=>[50100401=>200000,50200401=>200000]],
        ['id'=>104,'code'=>'CONGRESS','tier'=>'S','reference'=>2000000,'old'=>[50100401=>500000,50200401=>500000,50300401=>500000]],
    ];
    foreach($targets as $target){
        $id=$target['id'];$tier=$target['tier'];$label=$target['code'];$catalog=ShrineService::NPC_GARRISON[$tier];
        $db->execute('INSERT INTO shrines(id,world_id,shrine_code,tier,coord_x,coord_y)VALUES(?,1,?,?,?,80)',[$id,$label,$tier,80+$id-100]);
        $db->execute('INSERT INTO shrine_captures(shrine_id,garrison_troops_json)VALUES(?,?)',[$id,json_encode($target['old'])]);
        expectNpc(ShrineService::getShrine($id)['garrison_troops']===$catalog,"$label upgrades a full legacy neutral snapshot");
        $db->execute('UPDATE shrine_captures SET garrison_troops_json=? WHERE shrine_id=?',[json_encode(array_map(static fn($n)=>intdiv($n,2),$target['old'])),$id]);
        expectNpc(array_sum(ShrineService::getShrine($id)['garrison_troops'])===intdiv(array_sum($catalog),2),"$label keeps proportional prior NPC attrition");
        $converted=ShrineService::getShrine($id)['garrison_troops'];
        $db->execute('UPDATE shrine_captures SET garrison_troops_json=? WHERE shrine_id=?',[json_encode($converted),$id]);
        expectNpc(ShrineService::getShrine($id)['garrison_troops']===$converted,"$label never rescales an already upgraded snapshot");
        $db->execute('UPDATE shrine_captures SET garrison_troops_json=? WHERE shrine_id=?',[json_encode(array_fill_keys(array_keys($target['old']),1)),$id]);
        expectNpc(array_sum(ShrineService::getShrine($id)['garrison_troops'])>0,"$label does not round a surviving legacy garrison down to zero");
        $db->execute('UPDATE shrine_captures SET alliance_id=1,garrison_troops_json=? WHERE shrine_id=?',[json_encode($target['old']),$id]);
        expectNpc(ShrineService::getShrine($id)['garrison_troops']===$target['old'],"$label preserves occupied defender composition");
        $db->execute("UPDATE shrine_captures SET garrison_troops_json='{}' WHERE shrine_id=?",[$id]);
        expectNpc(ShrineService::getShrine($id)['garrison_troops']===[],"$label does not add NPC guards to an empty owned landmark");
        $db->execute('UPDATE shrine_captures SET alliance_id=NULL WHERE shrine_id=?',[$id]);
        expectNpc(ShrineService::getShrine($id)['garrison_troops']===$catalog,"$label cannot be captured for free through an empty neutral row");

        // T2 archers are the strongest attack per troop available at castle level 10.
        $capacity=ResearchEffects::limits(BuffEngine::getBuffs(1,1))['march_capacity'];
        $march=CongressService::dispatch(1,$id,[50200201=>$capacity]);
        $db->execute('UPDATE marches SET arrival_time=UTC_TIMESTAMP() WHERE id=?',[$march['march_id']]);
        CongressService::resolveMarch($march['march_id']);
        expectNpc(ShrineService::getShrine($id)['alliance_id']===null&&$db->query('SELECT outcome FROM battle_reports WHERE march_id=?',[$march['march_id']])->fetchColumn()==='defender_wins',"$label defeats a full level-10 solo T2 march ($capacity troops)");
        $db->execute('UPDATE marches SET return_time=UTC_TIMESTAMP() WHERE id=?',[$march['march_id']]);CongressService::resolveMarch($march['march_id']);

        // Exercise the real server rally resolver around the intended size, with
        // fresh defenders each time so previous attempts cannot bias the boundary.
        foreach([[.9,'defender_wins'],[1.1,'attacker_wins']] as [$ratio,$expected]){
            $db->execute('DELETE FROM shrine_captures WHERE shrine_id=?',[$id]);
            $perType=(int)floor($target['reference']*$ratio/6);
            $troops=[50100301=>$perType,50200301=>$perType,50300301=>$perType];
            $armies=[['player_id'=>1,'city_id'=>1,'troops'=>$troops],['player_id'=>2,'city_id'=>2,'troops'=>$troops]];
            $result=$db->transaction(fn()=>CongressService::fightRally(['id'=>$id*10+(int)($ratio*10),'world_id'=>1],['id'=>$id],$armies,1));
            expectNpc($result['outcome']===$expected,"$label resolves ".(array_sum($troops)*2)." mixed T3 rally troops as $expected");
        }
        expectNpc((int)$db->query('SELECT COUNT(*) FROM shrine_garrisons WHERE shrine_id=?',[$id])->fetchColumn()===2,"$label keeps each victorious participant as an individual garrison");
    }
    echo "ALL SHRINE NPC BALANCE CHECKS PASSED (isolated database).\n";
}finally{$fixture->close();}
