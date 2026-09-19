<?php
declare(strict_types=1);

/** Isolated, read-only preview checks. Never changes live rewards or gameplay state. */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

define('ROOT_DIR', dirname(__DIR__));
define('APP_BASE', '/conquer');
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';

use Conquer\Db\Connection;
use Conquer\Game\Rewards\RewardCatalog;
use Conquer\Game\Rewards\RewardPreview;

$checks = 0;
$fixture = null;
$exit = 0;

function previewCheck(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $checks++;
    echo "PASS $message\n";
}

try {
    $fixture = new \ConquerTests\FeatureDatabase();
    $db = Connection::getInstance();
    $monsterKey = '20209901';

    foreach (['monster', 'dungeon', 'chest', 'expedition'] as $type) {
        $validKey = (string)array_key_first(RewardCatalog::sources($type));
        previewCheck(RewardPreview::history($type, $validKey, 0) === [], 'Valid '.$type.' source supports an empty history');
    }
    try {
        RewardPreview::monster('missing-source', 0);
        throw new RuntimeException('Invalid source was accepted');
    } catch (InvalidArgumentException) {
        previewCheck(true, 'Unknown preview source is rejected');
    }

    $global = RewardCatalog::defaults('monster', $monsterKey);
    $global['drops'] = [
        ['item_code' => 10103001, 'count' => 7, 'probability' => 0.0],
        ['item_code' => 10103002, 'count' => 3, 'probability' => 1.0],
    ];
    $global['gems_drop'] = ['chance' => .25, 'amount' => 40];
    $globalJson = json_encode($global, JSON_THROW_ON_ERROR);
    $db->execute('INSERT INTO reward_overrides(source_type,source_key,config_json,revision,updated_by) VALUES(?,?,?,?,?)', ['monster', $monsterKey, $globalJson, 1, 1]);
    $db->execute('INSERT INTO reward_rule_revisions(scope_world_id,source_type,source_key,revision,config_json,updated_by,created_at) VALUES(?,?,?,?,?,?,?)', [0, 'monster', $monsterKey, 1, $globalJson, 1, '2026-09-12 10:00:00']);
    RewardCatalog::resetCache();

    $globalPreview = RewardPreview::monster($monsterKey, 0);
    previewCheck($globalPreview['items'][0]['expected_per_100'] === 0.0, 'Zero-percent item has zero expected quantity');
    previewCheck($globalPreview['items'][1]['expected_per_100'] === 300.0, 'Guaranteed item expectation is quantity times 100 victories');
    previewCheck($globalPreview['gems']['expected_per_100'] === 1000.0, 'Gem expectation multiplies amount, chance and 100 victories');
    previewCheck($globalPreview['charm']['guaranteed_per_victory'] === 1 && $globalPreview['charm']['expected_per_100'] === 100, 'Every monster victory guarantees exactly one charm');

    $world = $global;
    $world['drops'][1] = ['item_code' => 10103002, 'count' => 5, 'probability' => 1.0];
    $world['gems_drop'] = ['chance' => 1.0, 'amount' => 2];
    $worldJson = json_encode($world, JSON_THROW_ON_ERROR);
    $db->execute('INSERT INTO reward_world_overrides(world_id,source_type,source_key,config_json,revision,updated_by) VALUES(?,?,?,?,?,?)', [1, 'monster', $monsterKey, $worldJson, 1, 1]);
    $db->execute('INSERT INTO reward_rule_revisions(scope_world_id,source_type,source_key,revision,config_json,updated_by,created_at) VALUES(?,?,?,?,?,?,?)', [1, 'monster', $monsterKey, 1, $worldJson, 1, '2026-09-12 11:00:00']);
    RewardCatalog::resetCache();
    $worldPreview = RewardPreview::monster($monsterKey, 1);
    previewCheck($worldPreview['items'][1]['expected_per_100'] === 500.0 && $worldPreview['gems']['expected_per_100'] === 200.0, 'World preview uses the effective world override');
    previewCheck($globalPreview['items'][1]['expected_per_100'] === 300.0, 'World scope does not change the global preview');

    $db->execute('UPDATE reward_world_overrides SET config_json=NULL,revision=2 WHERE world_id=1 AND source_type=? AND source_key=?', ['monster', $monsterKey]);
    $db->execute('INSERT INTO reward_rule_revisions(scope_world_id,source_type,source_key,revision,config_json,updated_by,created_at) VALUES(?,?,?,?,?,?,?)', [1, 'monster', $monsterKey, 2, null, 1, '2026-09-12 12:00:00']);
    for ($revision = 3; $revision <= 20; $revision++) {
        $db->execute('INSERT INTO reward_rule_revisions(scope_world_id,source_type,source_key,revision,config_json,updated_by,created_at) VALUES(?,?,?,?,?,?,DATE_ADD(?,INTERVAL ? MINUTE))', [1, 'monster', $monsterKey, $revision, '{"private_marker":"HISTORY_SECRET"}', 1, '2026-09-12 12:00:00', $revision]);
    }
    RewardCatalog::resetCache();
    $resetPreview = RewardPreview::monster($monsterKey, 1);
    previewCheck($resetPreview['items'][1]['expected_per_100'] === 300.0, 'World reset preview falls back to the global effective rule');

    $worldHistory = RewardPreview::history('monster', $monsterKey, 1);
    $globalHistory = RewardPreview::history('monster', $monsterKey, 0);
    previewCheck(count($worldHistory) === 20 && $worldHistory[0]['revision'] === 20 && $worldHistory[19]['revision'] === 1, 'History returns the latest 20 revisions in descending order');
    previewCheck($worldHistory[18]['revision'] === 2 && $worldHistory[18]['kind'] === 'reset' && $worldHistory[19]['kind'] === 'adjustment', 'History distinguishes reset from adjustment');
    previewCheck(count($globalHistory) === 1 && $globalHistory[0]['revision'] === 1, 'History is isolated to the selected scope');
    previewCheck(!array_key_exists('config_json', $worldHistory[0]) && !str_contains(json_encode($worldHistory, JSON_THROW_ON_ERROR), 'HISTORY_SECRET'), 'History projection never exposes rule JSON');

    $_SESSION = ['admin' => ['id' => 1, 'username' => 'PreviewAdmin', 'role' => 'superadmin'], 'admin_csrf' => str_repeat('a', 64)];
    $_GET = ['world_id' => 1, 'scope' => 'world', 'type' => 'monster', 'source' => $monsterKey];
    ob_start();
    \Conquer\Admin\AdminController::rewards();
    $html = ob_get_clean();
    previewCheck(str_contains($html, 'Erwartete Beute') && str_contains($html, 'Regelverlauf') && str_contains($html, 'langfristige Durchschnittswerte'), 'Admin page renders the expected-value preview and history explanation');
    previewCheck(str_contains($html, 'Zurückgesetzt') && str_contains($html, 'Anpassung') && !str_contains($html, 'HISTORY_SECRET'), 'Rendered history is concise and omits stored JSON');
    previewCheck(!str_contains($html, '<form method="post" action="/conquer/admin/action/reward-preview"'), 'Preview does not add a simulation POST action');

    echo "ALL $checks REWARD PREVIEW CHECKS PASSED\n";
} catch (Throwable $e) {
    $exit = 1;
    fwrite(STDERR, 'FAIL '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine()."\n");
} finally {
    if ($fixture !== null) {
        $fixture->close();
    }
}

exit($exit);
