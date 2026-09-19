<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';
date_default_timezone_set('UTC');
\Conquer\Logger::init(sys_get_temp_dir().'/conquer-march-skins-test.log', 'ERROR');

use Conquer\Db\Connection;
use Conquer\Game\March\{MarchDispatcher,MarchSkinService,MarchSpeed};
use Conquer\Game\Rally\RallyService;
use Conquer\Game\World\WorldContext;
use Conquer\Game\Expedition\ExpeditionRules;

$checks=0;$fixture=null;$exit=0;
function checkSkin(bool $condition,string $message):void { global $checks;if(!$condition)throw new RuntimeException($message);$checks++;echo "PASS $message\n"; }
function rejectSkin(callable $fn,string $message):void { try{$fn();}catch(DomainException|RuntimeException $e){checkSkin(true,$message);return;}throw new RuntimeException('Allowed: '.$message); }

try {
    $fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();WorldContext::bind(1);
    $db->execute("UPDATE worlds SET status='running',map_size=256 WHERE id=1");
    foreach([1,2,3] as $id){
        $db->execute('INSERT INTO players(id,username,email,password_hash,gems) VALUES(?,?,?,?,?)',[$id,'SkinFixture'.$id,'skin'.$id.'@invalid.test','unused',$id===1?5000:0]);
        $x=match($id){1=>10,2=>110,default=>11};
        $db->execute('INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,castle_level) VALUES(?,?,1,?,?,10,1)',[$id,$id,'Skin city '.$id,$x]);
        $db->execute('INSERT INTO kingdom_profiles(player_id,display_name) VALUES(?,?)',[$id,'SkinFixture'.$id]);
    }

    $catalog=MarchSkinService::catalog();
    checkSkin(count($catalog)===18&&array_keys($catalog)===['default','ironkeep','rosehall','sandspire','tidewatch','winterhold','jadecourt','emberforge','ravenloft','clockwork','sapphire','phoenix','dragon','astral','leviathan','yggdrasil','tempest','eclipse'],'catalog mirrors every stable castle skin id');
    checkSkin($catalog['dragon']['name']==='Drachenmarsch'&&$catalog['dragon']['rarity']==='mythic'&&$catalog['dragon']['price_gems']===2400&&$catalog['dragon']['bonus_pct']===5,'dragon march has the intended mythic price and five percent bonus');
    checkSkin(count(array_unique(array_column($catalog,'bonus_pct')))===1&&$catalog['default']['bonus_pct']===5,'every themed skin has the same five percent bonus');
    $buffs=['march_speed'=>.20,'talent_hunt_march'=>.30,'talent_pvp_march'=>.10,'troop_speed_when_participating_a_rally'=>.40];
    $speeds=MarchSpeed::readModel(50100101,$buffs,1.05);$base=65.0;
    checkSkin(abs($speeds['march_speed']-$base*1.20*1.05)<1e-8
        &&abs($speeds['monster_march_speed']-$base*1.50*1.05)<1e-8
        &&abs($speeds['charm_march_speed']-$base*1.05)<1e-8
        &&abs($speeds['pvp_march_speed']-$base*1.30*1.05)<1e-8,'normal, monster, charm and PvP ETA speeds keep their different server talents');
    checkSkin(abs($speeds['monster_rally_speed']-$base*1.90*1.05)<1e-8
        &&abs($speeds['pvp_rally_speed']-$base*1.70*1.05)<1e-8
        &&$speeds['shrine_neutral_speed']===$speeds['reinforce_march_speed']
        &&$speeds['shrine_occupied_speed']===$speeds['pvp_march_speed'],'rally and shrine ETA fields match their mission-specific dispatch rules');
    $timing=ExpeditionRules::missionTiming(['march_speed'=>.06],1.05);
    checkSkin($timing===['march_seconds'=>18,'return_seconds'=>18]
        &&ExpeditionRules::publicRules(['march_speed'=>.06],1.05)['march_seconds']===18,'expedition preview and dispatch apply skin as a final multiplier instead of the additive bonus pool');
    checkSkin(MarchSkinService::currentSnapshot(1)===['march_skin'=>null,'bonus_pct'=>0],'legacy profile without ownership has the neutral zero-bonus fallback');
    rejectSkin(fn()=>$db->transaction(fn()=>MarchSkinService::claim(1,'default')),'free skin is gated at castle level two');
    $db->execute('UPDATE cities SET castle_level=2 WHERE id=1');
    $claim=$db->transaction(fn()=>MarchSkinService::claim(1,'default'));
    $repeatClaim=$db->transaction(fn()=>MarchSkinService::claim(1,'default'));
    checkSkin($claim['charged_gems']===0&&$repeatClaim['charged_gems']===0&&(int)$db->query('SELECT COUNT(*) FROM player_march_skins WHERE player_id=1')->fetchColumn()===1,'free claim is idempotent and never spends gems');

    $buy=$db->transaction(fn()=>MarchSkinService::buy(1,'ironkeep'));
    $repeatBuy=$db->transaction(fn()=>MarchSkinService::buy(1,'ironkeep'));
    checkSkin($buy['charged_gems']===1200&&$repeatBuy['charged_gems']===0&&(int)$db->query('SELECT gems FROM players WHERE id=1')->fetchColumn()===3800,'repeat purchase cannot charge the same skin twice');
    $dragonBuy=$db->transaction(fn()=>MarchSkinService::buy(1,'dragon'));
    checkSkin($dragonBuy['charged_gems']===2400&&(int)$db->query('SELECT gems FROM players WHERE id=1')->fetchColumn()===1400&&(int)$db->query("SELECT COUNT(*) FROM player_march_skins WHERE player_id=1 AND skin_code='dragon'")->fetchColumn()===1,'dragon purchase charges its server-owned mythic price exactly once');
    rejectSkin(fn()=>$db->transaction(fn()=>MarchSkinService::equip(1,'phoenix')),'unowned skin cannot be equipped');
    $db->execute('UPDATE players SET gems=100 WHERE id=1');
    rejectSkin(fn()=>$db->transaction(fn()=>MarchSkinService::buy(1,'phoenix')),'insufficient gems cannot create ownership or a negative balance');
    checkSkin((int)$db->query('SELECT gems FROM players WHERE id=1')->fetchColumn()===100&&(int)$db->query("SELECT COUNT(*) FROM player_march_skins WHERE player_id=1 AND skin_code='phoenix'")->fetchColumn()===0,'failed purchase leaves balance and ownership unchanged');

    $db->transaction(fn()=>MarchSkinService::equip(1,'ironkeep'));
    $state=MarchSkinService::state(1);$iron=$state['entries'][array_search('ironkeep',array_column($state['entries'],'id'),true)];
    checkSkin($state['equipped']==='ironkeep'&&$state['bonus_pct']===5&&$iron['owned']&&$iron['equipped'],'state exposes ownership, equipped skin and effective non-stacking bonus');

    $first=MarchDispatcher::dispatchScout(1,1,10,10,110,10);
    $row=$db->query('SELECT march_skin,march_speed_bonus_pct,TIMESTAMPDIFF(SECOND,departure_time,arrival_time) AS seconds FROM marches WHERE id=?',[$first])->fetch();
    checkSkin($row['march_skin']==='ironkeep'&&(int)$row['march_speed_bonus_pct']===5&&(int)$row['seconds']===47,'scout timing and appearance use the equipped five percent dispatch snapshot');
    $db->execute("UPDATE kingdom_profiles SET march_skin='default' WHERE player_id=1");
    $unchanged=$db->query('SELECT march_skin,march_speed_bonus_pct,TIMESTAMPDIFF(SECOND,departure_time,arrival_time) AS seconds FROM marches WHERE id=?',[$first])->fetch();
    checkSkin($unchanged===$row,'equipping another owned skin does not retime or repaint an in-flight march');
    $own=array_values(array_filter(MarchDispatcher::listActive(1),static fn($m)=>(int)$m['id']===$first))[0]??null;
    checkSkin(($own['march_skin']??null)==='ironkeep','active march contract returns the stored skin id');
    $db->execute("UPDATE marches SET state='returning',return_time=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR) WHERE id=?",[$first]);
    checkSkin($db->query('SELECT march_skin FROM marches WHERE id=?',[$first])->fetchColumn()==='ironkeep','ordinary return keeps its original appearance snapshot');

    $db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id) VALUES(1,1,'Skin alliance','SKN',1)");
    $db->execute("INSERT INTO alliance_members(alliance_id,player_id,world_id,role) VALUES(1,1,1,'leader'),(1,3,1,'member')");
    $db->execute("INSERT INTO city_troops(city_id,troop_code,count) VALUES(1,50100101,100),(3,50100101,100)");
    $db->execute("INSERT INTO player_march_skins(player_id,skin_code) VALUES(3,'default')");
    $db->execute("UPDATE kingdom_profiles SET march_skin='ironkeep' WHERE player_id=1");
    $db->execute("UPDATE kingdom_profiles SET march_skin='default' WHERE player_id=3");
    $rally=RallyService::start(1,1,2,110,10,[50100101=>10],1,'Skin snapshot');
    RallyService::join(3,3,$rally,[50100101=>10]);
    $db->execute('UPDATE kingdom_profiles SET march_skin=NULL WHERE player_id IN (1,3)');
    RallyService::launch($rally,1);
    $rallyRow=$db->query('SELECT march_skin,march_speed_bonus_pct,TIMESTAMPDIFF(SECOND,launch_at,arrival_time) AS seconds FROM rallies WHERE id=?',[$rally])->fetch();
    $participant=$db->query('SELECT march_skin,march_speed_bonus_pct FROM rally_participants WHERE rally_id=? AND player_id=3',[$rally])->fetch();
    checkSkin($rallyRow['march_skin']==='ironkeep'&&(int)$rallyRow['march_speed_bonus_pct']===5&&$participant['march_skin']==='default'&&(int)$participant['march_speed_bonus_pct']===5,'rally captain and participant keep independent dispatch snapshots');
    checkSkin((int)$rallyRow['seconds']===146,'rally launch timing uses the stored five percent bonus after later profile changes');
    $db->execute("UPDATE rallies SET status='returning',return_time=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR) WHERE id=?",[$rally]);
    $rallyMarch=array_values(array_filter(RallyService::activeMarchesForPlayer(1),static fn($m)=>(int)$m['rally_id']===$rally))[0]??null;
    checkSkin(($rallyMarch['march_skin']??null)==='ironkeep','returning rally remains painted with the captain dispatch snapshot');

    echo "ALL $checks MARCH SKIN CHECKS PASSED (disposable database).\n";
} catch(Throwable $e) {$exit=1;fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString()."\n");}
finally {if($fixture)$fixture->close();}
exit($exit);
