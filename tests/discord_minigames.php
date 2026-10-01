<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR . '/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR . '/src'))->register();
require ROOT_DIR . '/tests/Support/FeatureDatabase.php';
date_default_timezone_set('UTC');

use Conquer\Db\Connection;
use Conquer\Discord\{BotConfig, InteractionProtocol as Protocol, MinigameService as Game};

$checks = 0;
function check(bool $ok, string $name): void { global $checks; if (!$ok) throw new RuntimeException($name); ++$checks; echo "PASS $name\n"; }
function denied(callable $fn): bool { try { $fn(); return false; } catch (DomainException) { return true; } }
function interaction(string $name = 'expedition', string $user = '111111111111111111', ?string $code = null): array {
    static $id = 100000000000000000;
    $data = ['name' => $name];
    if ($code !== null) $data['options'] = [['type' => 3, 'name' => 'code', 'value' => $code]];
    return ['id' => (string)++$id, 'application_id' => '999999999999999999', 'guild_id' => '1554839128896573480', 'type' => 2, 'member' => ['user' => ['id' => $user]], 'data' => $data];
}
function click(array $response, int $index = 0, string $user = '111111111111111111'): array {
    $i = interaction('expedition', $user); $i['type'] = 3;
    $i['data'] = ['component_type' => 2, 'custom_id' => $response['data']['components'][0]['components'][$index]['custom_id']];
    return $i;
}
function says(array $response, string $key): bool { return ($response['data']['content'] ?? '') === Protocol::text($key); }

$keys = sodium_crypto_sign_keypair();
$public = bin2hex(sodium_crypto_sign_publickey($keys));
$private = sodium_crypto_sign_secretkey($keys);
$config = ['enabled' => true, 'application_id' => '999999999999999999', 'public_key' => $public, 'guild_ids' => ['1554839128896573480']];
$raw = '{"type":1}'; $ts = (string)time();
$signature = bin2hex(sodium_crypto_sign_detached($ts . $raw, $private));
check(Protocol::verify($raw, $signature, $ts, $public), 'Valid Ed25519 signature over exact raw timestamp/body');
check(!Protocol::verify($raw . ' ', $signature, $ts, $public), 'Body tampering rejected');
check(!Protocol::verify($raw, $signature, $ts, $public, (int)$ts + 301), 'Stale signed request rejected');
check(!Protocol::verify($raw, $signature, $ts, $public, (int)$ts - 301), 'Future signed request rejected');
check(!Protocol::verify($raw, 'invalid', $ts, $public), 'Malformed signature rejected safely');
check(!Protocol::verify(str_repeat('x', 65537), $signature, $ts, $public), 'Oversized signed body rejected');
check(!BotConfig::enabled([]) && BotConfig::enabled($config + ['bot_token' => '']), 'Disabled default; interaction service needs no bot token');
$envelope = interaction();
check(Protocol::envelope($envelope, $config), 'Expected application, guild and member accepted');
foreach (['guild_id', 'application_id'] as $field) { $bad = $envelope; $bad[$field] = '888888888888888888'; check(!Protocol::envelope($bad, $config), 'Foreign ' . $field . ' rejected'); }
$bad = $envelope; unset($bad['guild_id']); check(!Protocol::envelope($bad, $config), 'DM context rejected');
$bad = $envelope; $bad['member']['user']['bot'] = true; check(!Protocol::envelope($bad, $config), 'Bot actor rejected');
check(Protocol::message('x')['data']['flags'] === 64 && Protocol::message('x')['data']['allowed_mentions']['parse'] === [], 'Private replies with mentions disabled');
foreach (Protocol::commands() as $command) check(strlen($command['description']) <= 100, 'Discord command description length /' . $command['name']);

$fixture = new \ConquerTests\FeatureDatabase();
$log = tempnam(sys_get_temp_dir(), 'discord-test-');
try {
    \Conquer\Logger::init($log);
    $db = Connection::getInstance();
    \Conquer\Db\MigrationSql::apply($db->getPdo(), (string)file_get_contents(ROOT_DIR . '/migrations/0125_discord_minigames.sql'));
    $db->execute("UPDATE worlds SET status='running' WHERE id=1");
    $db->execute("INSERT INTO worlds(id,name,slug,status) VALUES(2,'Fixture second world','discord-second','running')");
    foreach ([991, 992, 993, 994, 995] as $pid) {
        $db->execute('INSERT INTO players(id,username,email,password_hash) VALUES(?,?,?,?)', [$pid, 'DiscordFixture' . $pid, $pid . '@example.invalid', 'unused']);
        foreach ([1, 2] as $world) {
            $db->execute("INSERT INTO cities(player_id,world_id,name,coord_x,coord_y,food,lumber,stone,gold,last_resource_update) VALUES(?,?,'Fixture',?,?,0,0,0,0,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))", [$pid, $world, $pid - 900, $pid - 900]);
            $city = $db->lastInsertId();
            foreach (\Conquer\Game\City\CityState::BUILDING_CODES as $code) $db->execute('INSERT INTO city_buildings(city_id,building_code,level) VALUES(?,?,1)', [$city, $code]);
        }
    }
    check(Game::accountState(991) === ['enabled' => false], 'Absent deployment config hides account UI without touching feature tables');
    $first = Game::issueCode(991, 2); $replacement = Game::issueCode(991, 2);
    check(says(Game::handle(interaction('link', '111111111111111111', $first['code']), $config), 'invalid_code'), 'Replacement invalidates the old linking code');
    $hash = $db->query('SELECT code_hash FROM discord_link_codes WHERE player_id=991')->fetchColumn();
    check($hash === hash('sha256', $replacement['code']) && $hash !== $replacement['code'], 'Only code hash is stored');
    $link = interaction('link', '111111111111111111', $replacement['code']);
    $linked = Game::handle($link, $config);
    check(str_contains($linked['data']['content'], 'Fixture second world'), 'Link pins the selected world');
    check(Game::handle($link, $config) === $linked, 'Exact link interaction retry returns its original receipt');
    check(says(Game::handle(interaction('link', '222222222222222222', $replacement['code']), $config), 'invalid_code'), 'Code cannot be redeemed twice by another Discord account');
    check((int)$db->query('SELECT COUNT(*) FROM oauth_accounts')->fetchColumn() === 0, 'Minigame linking never grants OAuth login access');
    check(denied(fn() => Game::issueCode(991, 1)), 'Linked player cannot silently replace destination or identity');
    $exp = Game::handle(interaction(), $config);
    check(count($exp['data']['components'][0]['components']) === 3, 'Expedition offers three resource paths');
    $db->execute("INSERT INTO discord_game_links(player_id,discord_id,world_id) VALUES(992,'222222222222222222',1)");
    check(says(Game::handle(click($exp, 0, '222222222222222222'), $config), 'invalid_action'), 'Another linked user cannot click the owner expedition');
    $choose = click($exp, 1);
    $decision = Game::handle($choose, $config);
    check($decision['type'] === 7 && count($decision['data']['components'][0]['components']) === 2, 'Route advances to two private message choices');
    $finish = click($decision);
    $reward = Game::handle($finish, $config);
    check(str_contains($reward['data']['content'], '500 wood') && $reward['data']['components'] === [], 'Careful decision grants the declared reward and removes buttons');
    check((int)$db->query('SELECT lumber FROM cities WHERE player_id=991 AND world_id=2')->fetchColumn() === 500, 'Reward reaches pinned world');
    check((int)$db->query('SELECT lumber FROM cities WHERE player_id=991 AND world_id=1')->fetchColumn() === 0, 'Other world receives no resource');
    check(Game::handle($finish, $config) === $reward, 'Duplicate interaction returns identical receipt');
    Game::handle(click($decision, 1), $config);
    Game::handle(click($exp, 2), $config);
    check((int)$db->query('SELECT lumber FROM cities WHERE player_id=991 AND world_id=2')->fetchColumn() === 500, 'New interaction IDs and old buttons cannot pay again or change route');
    $collision = $finish; $collision['data']['custom_id'] .= 'x';
    check(says(Game::handle($collision, $config), 'invalid_action'), 'Interaction ID cannot be reused with changed payload');
    Game::unlink(991);
    check(says(Game::handle(interaction(), $config), 'link_first'), 'Unlink immediately revokes new access');
    $newCode = Game::issueCode(991, 1);
    Game::handle(interaction('link', '333333333333333333', $newCode['code']), $config);
    check(says(Game::handle(interaction('expedition', '333333333333333333'), $config), 'daily_limit'), 'Changing Discord ID and world cannot bypass game-account daily limit');
    $otherCode = Game::issueCode(993, 1);
    Game::handle(interaction('link', '111111111111111111', $otherCode['code']), $config);
    check(says(Game::handle(interaction(), $config), 'daily_limit'), 'Changing game account cannot bypass Discord daily limit');
    $expired = Game::issueCode(994, 1);
    $db->execute('UPDATE discord_link_codes SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE player_id=994');
    check(says(Game::handle(interaction('link', '444444444444444444', $expired['code']), $config), 'invalid_code'), 'Expired link rejected');
    $db->execute('UPDATE players SET is_banned=1 WHERE id=992');
    check(says(Game::handle(interaction('expedition', '222222222222222222'), $config), 'unavailable'), 'Banned player cannot start expedition');
    $db->execute('UPDATE players SET is_banned=0 WHERE id=992');
    $db->execute("UPDATE worlds SET status='paused' WHERE id=1");
    check(says(Game::handle(interaction('expedition', '222222222222222222'), $config), 'world_unavailable'), 'Paused world refuses rewards');
    $db->execute("UPDATE worlds SET status='running' WHERE id=1");
    $second = Game::handle(interaction('expedition', '222222222222222222'), $config);
    $secondDecision = Game::handle(click($second, 0, '222222222222222222'), $config);
    $db->execute('UPDATE discord_expeditions SET play_day=DATE_SUB(UTC_DATE(),INTERVAL 1 DAY) WHERE player_id=992');
    check(says(Game::handle(click($secondDecision, 0, '222222222222222222'), $config), 'expired'), 'Old day buttons expire at UTC rollover');
    $fresh = Game::handle(interaction('expedition', '222222222222222222'), $config);
    check($fresh['data']['components'][0]['components'][0]['custom_id'] !== $second['data']['components'][0]['components'][0]['custom_id'], 'Next day creates a new server-side run');
    $freshDecision = Game::handle(click($fresh, 0, '222222222222222222'), $config);
    $finish2 = click($freshDecision, 1, '222222222222222222');
    $db->execute('UPDATE discord_expeditions SET fortune=1 WHERE player_id=992 AND play_day=UTC_DATE()');
    // The receipt failure must roll the resource tick and credit back as well.
    $db->execute("CREATE TRIGGER discord_receipt_failure BEFORE INSERT ON discord_interaction_receipts FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Fixture receipt failure'");
    try { Game::handle($finish2, $config); throw new LogicException('Expected forced rollback'); } catch (PDOException) { }
    $db->execute('DROP TRIGGER discord_receipt_failure');
    check((int)$db->query('SELECT food FROM cities WHERE player_id=992 AND world_id=1')->fetchColumn() === 0
        && (int)$db->query('SELECT stage FROM discord_expeditions WHERE player_id=992 AND play_day=UTC_DATE()')->fetchColumn() === 1, 'Receipt failure rolls back reward and run transition');
    // Full production storage must not swallow the promised expedition loot.
    $db->execute('UPDATE cities SET food=0,last_resource_update=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 365 DAY) WHERE player_id=992 AND world_id=1');
    $pre = \Conquer\Game\World\WorldContext::run(1, fn() => \Conquer\Game\City\CityState::loadForPlayer(992, 1));
    $capFood = (int)$pre['city']['food'];
    $paid = Game::handle($finish2, $config);
    check(str_contains($paid['data']['content'], '1000 food') && (int)$db->query('SELECT food FROM cities WHERE player_id=992 AND world_id=1')->fetchColumn() === $capFood + 1000, 'Production is settled before reward; full store preserves all 1000 earned food');
    // Independent workers race the same final button. Both eventual receipts must be safe.
    $db->execute("INSERT INTO discord_game_links(player_id,discord_id,world_id) VALUES(995,'555555555555555555',2)");
    $start = Game::handle(interaction('expedition', '555555555555555555'), $config);
    $step = Game::handle(click($start, 2, '555555555555555555'), $config);
    $requests = [click($step, 0, '555555555555555555'), click($step, 1, '555555555555555555')];
    $directory = (new ReflectionProperty($fixture, 'directory'))->getValue($fixture);
    $processes = [];
    foreach ($requests as $request) {
        $process = proc_open([PHP_BINARY, ROOT_DIR . '/tests/Support/DiscordWorker.php', $directory], [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes, ROOT_DIR, null, ['bypass_shell'=>true]);
        fwrite($pipes[0], json_encode(['interaction'=>$request,'config'=>$config], JSON_THROW_ON_ERROR)); fclose($pipes[0]);
        $processes[] = [$process, $pipes];
    }
    foreach ($processes as [$process,$pipes]) {
        $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        check(proc_close($process) === 0 && is_array(json_decode($output,true)), 'Concurrent worker completes safely ' . $error);
    }
    foreach ($requests as $request) Game::handle($request, $config);
    $run = $db->query('SELECT reward_json FROM discord_expeditions WHERE player_id=995')->fetchColumn();
    $amount = json_decode($run, true)['amount'];
    check((int)$db->query('SELECT stone FROM cities WHERE player_id=995 AND world_id=2')->fetchColumn() === $amount && in_array($amount,[250,500,1000],true), 'Concurrent conflicting finish clicks and retries pay exactly one bounded reward');
    check((int)$db->query('SELECT COUNT(*) FROM discord_expeditions WHERE player_id=995')->fetchColumn() === 1, 'Concurrent workers preserve unique daily run');
    // Exercise the actual HTTP handler with a fixture-only configuration/database.
    $origin = $fixture->serve('\\Conquer\\Discord\\DiscordHandler::interactions(' . var_export($config, true) . ',__DIR__);');
    $http = static function (array $payload, bool $valid = true, int $age = 0) use ($origin, $private): array {
        $body = json_encode($payload, JSON_THROW_ON_ERROR); $stamp = (string)(time() - $age);
        $signature = bin2hex(sodium_crypto_sign_detached($stamp . $body, $private));
        if (!$valid) $signature = str_repeat('0',128);
        $options = ['http' => ['method' => 'POST', 'ignore_errors' => true, 'timeout' => 3,
            'header' => "Content-Type: application/json\r\nX-Signature-Ed25519: $signature\r\nX-Signature-Timestamp: $stamp\r\n", 'content' => $body]];
        $start = microtime(true);
        $reply = file_get_contents($origin . '/api/discord/interactions', false, stream_context_create($options));
        return [(int)explode(' ', $http_response_header[0])[1], json_decode((string)$reply,true), microtime(true)-$start];
    };
    [$status,$ping,$latency] = $http(['type'=>1]);
    check($status === 200 && $ping === ['type'=>1] && $latency < 2.5, 'Real HTTP signed PING returns Discord ACK within deadline');
    check($http(['type'=>1],false)[0] === 401, 'Real HTTP invalid signature returns 401 even for PING');
    check($http(['type'=>1],true,301)[0] === 401, 'Real HTTP stale signature returns 401');
    $foreign = interaction(); $foreign['guild_id'] = '888888888888888888';
    check($http($foreign)[0] === 403, 'Real HTTP foreign guild rejected before game mutation');
    [$status,$reply,$latency] = $http(interaction('expedition','555555555555555555'));
    check($status === 200 && ($reply['data']['flags']??0) === 64 && $latency < 2.5, 'Real HTTP command returns private receipt within deadline');
    // Hold the app's player lock in this connection while HTTP tries to play.
    $db->query("SELECT GET_LOCK('conquer-player-995',0)");
    [$status,$busy,$latency] = $http(interaction('expedition','555555555555555555'));
    $db->query("SELECT RELEASE_LOCK('conquer-player-995')");
    check($status === 200 && says($busy,'busy') && $latency < 1, 'Contended player lock returns promptly without a duplicate action');
    // Signed requests still cannot override application identity or server-owned rewards.
    $wrongApp = interaction(); $wrongApp['application_id'] = '888888888888888888';
    check($http($wrongApp)[0] === 403, 'Real HTTP foreign app rejected');
    $elapsedStart = microtime(true);
    for ($n=0;$n<3;$n++) Game::handle(interaction('expedition','555555555555555555'),$config);
    check(microtime(true)-$elapsedStart < 2, 'Three normal database-backed replies finish within two seconds');
    echo "ALL $checks DISCORD MINIGAME CHECKS PASSED\n";
} finally { $fixture->close(); if (is_file($log)) unlink($log); }
