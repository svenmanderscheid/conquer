<?php
declare(strict_types=1);

/** Complete research catalogue and actual game snapshot, using a disposable database. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR . '/src'))->register();
require __DIR__ . '/Support/FeatureDatabase.php';

use Conquer\Db\Connection;
use Conquer\Game\City\CityState;
use Conquer\Game\Research\ResearchData;

function snapshotCheck(bool $ok, string $message): void
{
    if (!$ok) { throw new RuntimeException($message); }
    echo 'PASS ' . $message . "\n";
}

$fixture = null;
$exit = 0;
try {
    $definitions = ResearchData::allNodes();
    $expected = ['production' => [34, 245], 'battle' => [43, 306], 'advanced' => [40, 400]];
    foreach ($expected as $tree => [$technologies, $levels]) {
        $nodes = ResearchData::tree($tree);
        snapshotCheck(count($nodes) === $technologies && array_sum(array_map(static fn($node) => count($node['levels']), $nodes)) === $levels,
            $tree . ' retains ' . $technologies . ' technologies and ' . $levels . ' levels');
    }
    // A full level graph catches unreachable later upgrades as well as missing opening nodes.
    $visited = [];
    $visiting = [];
    $visit = function (string $code, int $level) use (&$visit, &$visited, &$visiting, $definitions): void {
        $key = $code . ':' . $level;
        if (isset($visited[$key])) { return; }
        if (isset($visiting[$key])) { throw new RuntimeException('Cyclic research upgrade: ' . $key); }
        $node = $definitions[$code] ?? null;
        $entry = $node['levels'][$level - 1] ?? null;
        if (!$entry || $entry['level'] !== $level || $level > $node['max_level']) {
            throw new RuntimeException('Missing research upgrade: ' . $key);
        }
        $visiting[$key] = true;
        if ($level > 1) { $visit($code, $level - 1); }
        foreach ($entry['requirements'] as $requirement) {
            if ($requirement['type'] === 'research') { $visit($requirement['code'], $requirement['level']); }
        }
        unset($visiting[$key]);
        $visited[$key] = true;
    };
    foreach ($definitions as $node) { $visit($node['code'], $node['max_level']); }
    snapshotCheck(count($visited) === 951, 'all 951 sequential upgrades and every prerequisite are reachable');

    $fixture = new \ConquerTests\FeatureDatabase();
    $db = Connection::getInstance();
    $db->execute("UPDATE worlds SET status='running' WHERE id=1");
    $db->execute("INSERT INTO worlds(id,name,slug,status,map_size) VALUES(2,'Research world','research-world','running',256)");
    $db->execute("INSERT INTO players(id,username,email,password_hash) VALUES(1,'ResearchSnapshot','snapshot@invalid.test','unused')");
    $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y) VALUES(1,1,1,'First research city',20,20),(2,1,2,'Second research city',20,20)");
    foreach ([1, 2] as $city) {
        foreach (CityState::BUILDING_CODES as $code) {
            $db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(?,?,1)', [$city, $code]);
        }
    }
    $tokens = [];
    foreach ([1, 2] as $world) {
        $tokens[$world] = bin2hex(random_bytes(32));
        $db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,user_agent,expires_at,active_world_id) VALUES(1,?,?,'127.0.0.1','research snapshot fixture',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR),?)",
            [$tokens[$world], bin2hex(random_bytes(32)), $world]);
    }
    $url = $fixture->serve('\\Conquer\\Api\\Handlers\\GameHandler::state([]);');
    $snapshot = static function (int $world) use ($url, $tokens): array {
        $curl = curl_init($url . '/api/game/state');
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_COOKIE => 'conquer_session=' . $tokens[$world]]);
        $raw = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if ($raw === false) { throw new RuntimeException($error); }
        $body = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if ($status !== 200 || !($body['ok'] ?? false)) { throw new RuntimeException('Snapshot request failed: ' . substr($raw, 0, 600)); }
        return $body['data'];
    };
    $complete = static function (array $state) use ($definitions): void {
        $actual = array_column($state['research_defs'], null, 'code');
        snapshotCheck(count($state['research_defs']) === 117 && count($actual) === 117, 'game API exposes all 117 technologies without duplicate IDs');
        // JSON preserves all semantic values; numeric 0.0 may become integer 0.
        snapshotCheck($actual == $definitions, 'game API retains every tree, level, cost, bonus and prerequisite from the source catalogue');
    };
    $first = $snapshot(1);
    snapshotCheck((int) $first['buildings']['academy']['level'] === 1 && !$first['research'] && !$first['research_queue'], 'new city starts at Academy 1 with no research');
    $complete($first);
    snapshotCheck(isset(array_column($first['research_defs'], null, 'code')['advanced_research_speed']), 'locked advanced technologies remain discoverable before prerequisites are met');

    $db->execute("INSERT INTO player_research(player_id,world_id,research_code,level) VALUES(1,1,'infantry_hp',2),(1,2,'resource_protect',1)");
    $db->execute("INSERT INTO research_queue(player_id,world_id,research_code,level_to,finishes_at) VALUES(1,1,'infantry_def',1,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR)),(1,2,'resource_protect',2,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))");
    foreach ([1 => ['infantry_hp', 2, 'infantry_def'], 2 => ['resource_protect', 1, 'resource_protect']] as $world => [$learned, $level, $running]) {
        $state = $snapshot($world);
        $complete($state);
        snapshotCheck((int) $state['city']['world_id'] === $world && array_map('intval', $state['research']) === [$learned => $level], 'world ' . $world . ' keeps its own learned research levels');
        snapshotCheck(count($state['research_queue']) === 1 && $state['research_queue'][0]['research_code'] === $running, 'world ' . $world . ' exposes only its own running research');
    }
    echo "ALL RESEARCH SNAPSHOT CHECKS PASSED\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL ' . $error->getMessage() . "\n");
    $exit = 1;
} finally {
    $fixture?->close();
}
exit($exit);
