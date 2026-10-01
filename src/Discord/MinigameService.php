<?php
declare(strict_types=1);
namespace Conquer\Discord;

use Conquer\Db\Connection;
use Conquer\Game\World\WorldContext;
use Conquer\Game\City\CityState;
use Conquer\Game\City\ResourceTick;
use Conquer\Security\RateLimit;

/** All mutations and their receipts commit together. No Discord-supplied reward values. */
final class MinigameService
{
    private static function fail(string $key): never
    {
        throw new \DomainException(InteractionProtocol::text($key));
    }

    public static function accountState(int $playerId): array
    {
        if (!BotConfig::enabled(BotConfig::load())) return ['enabled' => false];
        $row = Connection::getInstance()->query('SELECT l.discord_id,l.world_id,w.name AS world_name FROM discord_game_links l JOIN worlds w ON w.id=l.world_id WHERE l.player_id=?', [$playerId])->fetch();
        return ['enabled' => true, 'linked' => (bool)$row, 'link' => $row ?: null];
    }

    public static function issueCode(int $playerId, int $worldId): array
    {
        $db = Connection::getInstance();
        return self::playerLock($playerId, static fn() => $db->transaction(static function () use ($db, $playerId, $worldId): array {
            self::activePlayer($playerId);
            self::city($playerId, $worldId);
            if ($db->query('SELECT player_id FROM discord_game_links WHERE player_id=?', [$playerId])->fetchColumn()) self::fail('already_linked');
            $code = bin2hex(random_bytes(16));
            $db->execute('DELETE FROM discord_link_codes WHERE player_id=?', [$playerId]);
            $db->execute('INSERT INTO discord_link_codes(code_hash,player_id,world_id,expires_at) VALUES(?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 10 MINUTE))', [hash('sha256', $code), $playerId, $worldId]);
            return ['code' => $code, 'expires_in' => 600, 'world_id' => $worldId];
        }));
    }

    public static function unlink(int $playerId): array
    {
        $db = Connection::getInstance();
        return self::playerLock($playerId, static fn() => $db->transaction(static function () use ($db, $playerId): array {
            self::activePlayer($playerId);
            $db->execute('DELETE FROM discord_link_codes WHERE player_id=?', [$playerId]);
            $db->execute('DELETE FROM discord_game_links WHERE player_id=?', [$playerId]);
            // Daily rows deliberately survive unlinking, preventing reward farming.
            return ['message' => InteractionProtocol::text('unlinked')];
        }));
    }

    /** Called only after signature, app and guild checks. It repeats envelope checks for callers. */
    public static function handle(array $interaction, array $config): array
    {
        if (!BotConfig::enabled($config) || !InteractionProtocol::envelope($interaction, $config)) {
            return InteractionProtocol::message(InteractionProtocol::text('unavailable'));
        }
        $discord = $interaction['member']['user']['id'];
        $command = $interaction['type'] === 2 ? ($interaction['data']['name'] ?? '') : '';
        $db = Connection::getInstance();
        $lock = 'uok-discord-' . $discord;
        if ((int)$db->query('SELECT GET_LOCK(?,0)', [$lock])->fetchColumn() !== 1) {
            return InteractionProtocol::message(InteractionProtocol::text('busy'));
        }
        try {
            $retry = RateLimit::consume('discord.interactions', $discord, 30, 60);
            if (!$retry && $command === 'link') $retry = RateLimit::consume('discord.link', $discord, 8, 600);
            if ($retry) return InteractionProtocol::message(InteractionProtocol::text('slow_down'));
            $code = self::code($interaction);
            $owner = $command === 'link' && $code !== ''
                ? $db->query('SELECT player_id FROM discord_link_codes WHERE code_hash=? AND expires_at>UTC_TIMESTAMP()', [hash('sha256', $code)])->fetchColumn()
                : $db->query('SELECT player_id FROM discord_game_links WHERE discord_id=?', [$discord])->fetchColumn();
            $work = static fn() => $db->transaction(static function () use ($db, $interaction, $discord): array {
                $hash = hash('sha256', json_encode($interaction, JSON_THROW_ON_ERROR));
                $receipt = $db->query('SELECT payload_hash,response_json FROM discord_interaction_receipts WHERE interaction_id=? FOR UPDATE', [$interaction['id']])->fetch();
                if ($receipt) {
                    if (!hash_equals($receipt['payload_hash'], $hash)) self::fail('invalid_action');
                    return json_decode($receipt['response_json'], true, 32, JSON_THROW_ON_ERROR);
                }
                $response = self::dispatch($interaction, $discord);
                $db->execute('INSERT INTO discord_interaction_receipts(interaction_id,payload_hash,response_json) VALUES(?,?,?)', [$interaction['id'], $hash, json_encode($response, JSON_THROW_ON_ERROR)]);
                return $response;
            });
            return $owner ? self::playerLock((int)$owner, $work) : $work();
        } catch (\DomainException $e) {
            return InteractionProtocol::message($e->getMessage());
        } finally {
            $db->query('SELECT RELEASE_LOCK(?)', [$lock]);
        }
    }

    private static function dispatch(array $interaction, string $discord): array
    {
        $data = $interaction['data'] ?? [];
        if ($interaction['type'] === 2 && ($data['name'] ?? '') === 'link') return self::redeemCode($interaction, $discord);
        if ($interaction['type'] === 2 && ($data['name'] ?? '') !== 'expedition') self::fail('invalid_action');
        $db = Connection::getInstance();
        $link = $db->query('SELECT * FROM discord_game_links WHERE discord_id=? FOR UPDATE', [$discord])->fetch();
        if (!$link) self::fail('link_first');
        $player = (int)$link['player_id'];
        self::activePlayer($player);
        $city = self::city($player, (int)$link['world_id']);
        $day = (string)$db->query('SELECT UTC_DATE()')->fetchColumn();
        if ($interaction['type'] === 2) {
            $run = $db->query('SELECT * FROM discord_expeditions WHERE play_day=? AND (player_id=? OR discord_id=?) FOR UPDATE', [$day, $player, $discord])->fetch();
            if ($run && ($run['discord_id'] !== $discord || (int)$run['player_id'] !== $player || $run['guild_id'] !== $interaction['guild_id'])) self::fail('daily_limit');
            if (!$run) {
                $token = bin2hex(random_bytes(16));
                $db->execute('INSERT INTO discord_expeditions(token,player_id,discord_id,guild_id,world_id,city_id,play_day,fortune) VALUES(?,?,?,?,?,?,?,?)', [$token, $player, $discord, $interaction['guild_id'], $link['world_id'], $city['id'], $day, random_int(0, 1)]);
                $run = $db->query('SELECT * FROM discord_expeditions WHERE token=?', [$token])->fetch();
            }
            if ((int)$run['world_id'] !== (int)$link['world_id']) self::fail('daily_limit');
            return self::render($run);
        }
        $custom = $data['custom_id'] ?? '';
        if (($data['component_type'] ?? null) !== 2 || !is_string($custom)
            || preg_match('/^uokexp:([a-f0-9]{32}):([01]):(food|lumber|stone|safe|bold)$/D', $custom, $m) !== 1) self::fail('invalid_action');
        $run = $db->query('SELECT * FROM discord_expeditions WHERE token=? FOR UPDATE', [$m[1]])->fetch();
        if (!$run || (int)$run['player_id'] !== $player || $run['discord_id'] !== $discord
            || $run['guild_id'] !== $interaction['guild_id'] || (int)$run['world_id'] !== (int)$link['world_id']
            || (int)$run['city_id'] !== (int)$city['id']) self::fail('invalid_action');
        if ($run['play_day'] !== $day) self::fail('expired');
        if ((int)$run['stage'] !== (int)$m[2]) return self::render($run, true);
        if ($m[2] === '0') {
            if (!in_array($m[3], ['food', 'lumber', 'stone'], true)) self::fail('invalid_action');
            $db->execute('UPDATE discord_expeditions SET stage=1,route=? WHERE token=?', [$m[3], $run['token']]);
            $run['stage'] = 1;
            $run['route'] = $m[3];
            return self::render($run, true);
        }
        if (!in_array($m[3], ['safe', 'bold'], true)) self::fail('invalid_action');
        $resource = $run['route'];
        if (!in_array($resource, ['food', 'lumber', 'stone'], true)) throw new \RuntimeException('Invalid expedition reward resource.');
        $amount = $m[3] === 'safe' ? 500 : ((int)$run['fortune'] === 1 ? 1000 : 250);
        // Settle production first, or a nearly full store could absorb the reward into
        // its pending production. Earned loot may exceed storage like battle rewards.
        WorldContext::run((int)$run['world_id'], static function () use ($player, $run): void {
            $state = CityState::loadForPlayer($player, (int)$run['world_id']);
            if (!$state) self::fail('world_unavailable');
            ResourceTick::persist($state['city'], $state['buildings']);
        });
        if ($db->execute('UPDATE cities SET ' . $resource . '=' . $resource . '+? WHERE id=? AND player_id=? AND world_id=?', [$amount, $run['city_id'], $player, $run['world_id']]) !== 1) self::fail('unavailable');
        $run['stage'] = 2;
        $run['reward_json'] = json_encode(['resource' => $resource, 'amount' => $amount], JSON_THROW_ON_ERROR);
        $db->execute('UPDATE discord_expeditions SET stage=2,reward_json=?,claimed_at=UTC_TIMESTAMP() WHERE token=?', [$run['reward_json'], $run['token']]);
        return self::render($run, true);
    }

    private static function code(array $interaction): string
    {
        $options = $interaction['data']['options'] ?? [];
        if (!is_array($options) || count($options) !== 1) return '';
        $option = $options[0] ?? [];
        $code = $option['value'] ?? '';
        return ($option['name'] ?? '') === 'code' && ($option['type'] ?? null) === 3 && is_string($code)
            && preg_match('/^[a-f0-9]{32}$/D', $code) === 1 ? $code : '';
    }

    private static function redeemCode(array $interaction, string $discord): array
    {
        $db = Connection::getInstance();
        $code = self::code($interaction);
        if ($code === '') self::fail('invalid_code');
        $row = $db->query('SELECT * FROM discord_link_codes WHERE code_hash=? AND expires_at>UTC_TIMESTAMP() FOR UPDATE', [hash('sha256', $code)])->fetch();
        if (!$row) self::fail('invalid_code');
        self::activePlayer((int)$row['player_id']);
        $city = self::city((int)$row['player_id'], (int)$row['world_id']);
        if ($db->query('SELECT player_id FROM discord_game_links WHERE player_id=? OR discord_id=? FOR UPDATE', [$row['player_id'], $discord])->fetchColumn()) self::fail('already_linked');
        $db->execute('INSERT INTO discord_game_links(player_id,discord_id,world_id) VALUES(?,?,?)', [$row['player_id'], $discord, $row['world_id']]);
        $db->execute('DELETE FROM discord_link_codes WHERE code_hash=?', [$row['code_hash']]);
        return InteractionProtocol::message(InteractionProtocol::text('linked', ['world' => self::plain($city['world_name'])]));
    }

    private static function activePlayer(int $playerId): void
    {
        $player = Connection::getInstance()->query('SELECT id,is_banned FROM players WHERE id=? FOR UPDATE', [$playerId])->fetch();
        if (!$player || $player['is_banned']) self::fail('unavailable');
    }

    private static function city(int $playerId, int $worldId): array
    {
        $db = Connection::getInstance();
        $world = $db->query('SELECT name,status FROM worlds WHERE id=?', [$worldId])->fetch();
        if (!$world || !in_array($world['status'], ['open', 'running'], true)) self::fail('world_unavailable');
        $city = $db->query('SELECT id,world_id FROM cities WHERE player_id=? AND world_id=? FOR UPDATE', [$playerId, $worldId])->fetch();
        if (!$city) self::fail('world_unavailable');
        return $city + ['world_name' => $world['name']];
    }

    private static function playerLock(int $playerId, callable $fn): mixed
    {
        $db = Connection::getInstance();
        $lock = 'conquer-player-' . $playerId;
        if ((int)$db->query('SELECT GET_LOCK(?,0)', [$lock])->fetchColumn() !== 1) self::fail('busy');
        try { return $fn(); }
        finally { $db->query('SELECT RELEASE_LOCK(?)', [$lock]); }
    }

    private static function plain(string $value): string
    {
        return preg_replace('/([\\\\`*_{}\[\]()<>#+.!|~>])/', '\\\\$1', $value) ?? '';
    }

    private static function render(array $run, bool $update = false): array
    {
        $world = Connection::getInstance()->query('SELECT name FROM worlds WHERE id=?', [$run['world_id']])->fetchColumn();
        $params = ['world' => self::plain((string)$world)];
        if ((int)$run['stage'] === 2) {
            $reward = json_decode($run['reward_json'], true, 8, JSON_THROW_ON_ERROR);
            return InteractionProtocol::message(InteractionProtocol::text('reward', $params + ['amount' => $reward['amount'], 'resource' => InteractionProtocol::text('resource.' . $reward['resource'])]), [], $update);
        }
        $choices = (int)$run['stage'] === 0 ? ['food', 'lumber', 'stone'] : ['safe', 'bold'];
        $components = [['type' => 1, 'components' => array_map(static fn($choice) => ['type' => 2, 'style' => 1,
            'label' => InteractionProtocol::text('choice.' . $choice), 'custom_id' => 'uokexp:' . $run['token'] . ':' . $run['stage'] . ':' . $choice], $choices)]];
        return InteractionProtocol::message(InteractionProtocol::text((int)$run['stage'] === 0 ? 'intro' : 'decision', $params + ['resource' => InteractionProtocol::text('resource.' . $run['route'])]), $components, $update);
    }
}
