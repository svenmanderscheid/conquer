<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
date_default_timezone_set('UTC');
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR . '/src'))->register();
require __DIR__ . '/Support/FeatureDatabase.php';

use Conquer\Db\Connection;
use Conquer\Game\March\MarchSkinService;
use Conquer\Game\Premium\LocalCosmeticEntitlements;
use Conquer\Game\Premium\NameFrameService;
use Conquer\Game\Premium\ThemeBundleService;

$fixture = new \ConquerTests\FeatureDatabase();
$db = Connection::getInstance();
$checks = 0;
function localSkinCheck(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}

try {
    $db->execute("INSERT INTO players(id,username,email,password_hash) VALUES(1,'ExistingLocal','existing-local@tests.invalid','unused'),(2,'NewLocal','new-local@tests.invalid','unused')");
    foreach ([1,2] as $playerId) {
        $db->execute('INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,castle_level) VALUES(?,?,1,?,?,20,1)', [$playerId,$playerId,'Local city '.$playerId,20+$playerId*10]);
        $db->execute('INSERT INTO kingdom_profiles(player_id,display_name) VALUES(?,?)', [$playerId,'Local '.$playerId]);
    }

    $testDatabase = (string)$db->query('SELECT DATABASE()')->fetchColumn();
    $enabled = ['env'=>'development','local_free_skins'=>true,'local_free_skins_database'=>$testDatabase];
    $disabled = ['env'=>'production','local_free_skins'=>true,'local_free_skins_database'=>$testDatabase];
    localSkinCheck(!LocalCosmeticEntitlements::sync(1,$disabled,['host'=>'127.0.0.1','database'=>$testDatabase]), 'production environment cannot grant local cosmetics');
    localSkinCheck(!LocalCosmeticEntitlements::sync(1,$enabled,['host'=>'db.example.test','database'=>$testDatabase]), 'remote database cannot grant local cosmetics');
    localSkinCheck(!LocalCosmeticEntitlements::sync(1,$enabled,['host'=>'127.0.0.1','database'=>'another_database']), 'another local database cannot receive the development grant');
    localSkinCheck((int)$db->query('SELECT COUNT(*) FROM player_march_skins')->fetchColumn()===0, 'disabled guards leave ownership unchanged');

    foreach ([1,2] as $playerId) {
        localSkinCheck(LocalCosmeticEntitlements::sync($playerId,$enabled,['host'=>'127.0.0.1','database'=>$testDatabase]), 'local grant is enabled');
        localSkinCheck(LocalCosmeticEntitlements::sync($playerId,$enabled,['host'=>'127.0.0.1','database'=>$testDatabase]), 'local grant is idempotent');
        localSkinCheck((int)$db->query('SELECT COUNT(*) FROM player_march_skins WHERE player_id=?',[$playerId])->fetchColumn()===18, 'all march skins are owned');
        localSkinCheck((int)$db->query('SELECT COUNT(*) FROM player_castle_skins WHERE player_id=?',[$playerId])->fetchColumn()===17, 'all premium castle skins are owned');
        localSkinCheck((int)$db->query('SELECT COUNT(*) FROM player_name_frames WHERE player_id=?',[$playerId])->fetchColumn()===17, 'all premium name frames are owned');
    }

    $march = MarchSkinService::equip(1,'eclipse');
    $frame = NameFrameService::equip(1,'eclipse');
    localSkinCheck($march['march_skin']==='eclipse' && $frame['name_frame']==='eclipse', 'granted march skin and name frame can be equipped');
    localSkinCheck(count(array_filter(MarchSkinService::state(1)['entries'],static fn(array $entry):bool=>$entry['owned']))===18, 'march state exposes all granted skins');
    $gemsBefore = (int)$db->query('SELECT gems FROM players WHERE id=1')->fetchColumn();
    $freeRetry = MarchSkinService::buy(1,'dragon');
    localSkinCheck($freeRetry['charged_gems']===0 && (int)$db->query('SELECT gems FROM players WHERE id=1')->fetchColumn()===$gemsBefore, 'local premium march skins never charge gems');
    localSkinCheck(count(array_filter(ThemeBundleService::state(1)['entries'],static fn(array $entry):bool=>$entry['cosmetic_owned']))===51, 'bundle shop recognizes every locally free cosmetic');
    echo "PASS {$checks} local free-skin checks: guards, existing/new accounts, idempotency and equip verified.\n";
} finally {
    $fixture->close();
}
