<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require ROOT_DIR.'/tests/Support/FeatureDatabase.php';
use Conquer\Game\Buff\ActiveEffectService;
use Conquer\Db\Connection;
function effectCheck(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); echo "PASS $message\n"; }
$fixture = new \ConquerTests\FeatureDatabase();
try {
    $db = Connection::getInstance();
    foreach ([1,2] as $id) $db->execute("INSERT INTO players(id,username,email,password_hash) VALUES(?,?,?,'unused')", [$id,'EffectTest'.$id,'effects'.$id.'@tests.invalid']);
    foreach ([[1,1,'construction',5,100], [1,2,'research',10,101], [2,1,'march_speed',20,102], [1,2,'resource_production',25,null], [1,1,'troops_atk',-15,103]] as [$pid,$world,$category,$bonus,$source]) {
        $db->execute("INSERT INTO player_charms_active(player_id,world_id,stat_category,grade,charm_code,source_map_charm_id,bonus_pct,activated_at,expires_at) VALUES(?,?,?,'epic',?,?,?,?,?)", [$pid,$world,$category,$source===null?10600001:10700002,$source,$bonus,gmdate('Y-m-d H:i:s',time()-1800),gmdate('Y-m-d H:i:s',time()+1800)]);
    }
    $expiry = $db->query("SELECT expires_at FROM player_charms_active WHERE player_id=1 AND stat_category='resource_production'")->fetchColumn();
    // One mirror and one independent stacked production boost must yield two rows.
    $db->execute("INSERT INTO active_buffs(player_id,buff_type,multiplier,expires_at) VALUES(1,'production_boost',1.25,?),(1,'production_boost',1.25,?),(1,'research_boost',0.8,?),(2,'training_boost',1.5,?)", [$expiry,$expiry,$expiry,$expiry]);
    $db->execute("INSERT INTO active_buffs(player_id,buff_type,multiplier,expires_at) VALUES(1,'training_boost',1.5,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND)),(1,'training_boost',1,?)", [$expiry]);
    $effects = ActiveEffectService::forPlayer(1,1);
    effectCheck(count($effects) === 5, 'Only active effects for this player and world; account-wide boosts included');
    effectCheck(count(array_filter($effects,fn($e)=>$e['stat_category']==='resource_production')) === 2, 'Mirror removed once; independent stacked boost preserved');
    effectCheck(count(array_filter($effects,fn($e)=>$e['kind']==='debuff')) === 2, 'Negative charm and multiplier below one become debuffs');
    effectCheck(!in_array('research',array_column($effects,'stat_category'),true), 'Map charm in another world is hidden');
    effectCheck(in_array('research',array_column(ActiveEffectService::forPlayer(1,2),'stat_category'),true), 'World switch shows the corresponding map charm');
    effectCheck(count(ActiveEffectService::forPlayer(2,1)) === 2, 'Other player receives only their own effects');
    $db->execute("UPDATE player_charms_active SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE player_id=1");
    $db->execute("UPDATE active_buffs SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE player_id=1");
    effectCheck(ActiveEffectService::forPlayer(1,1) === [], 'Expired effects disappear from the snapshot');
} finally { $fixture->close(); }
