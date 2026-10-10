<?php
declare(strict_types=1);
namespace Conquer\Discord;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Db\Connection;
use Conquer\Game\Locale;
use Conquer\Game\World\WorldContext;
use Conquer\Security\ApiGuard;

final class DiscordHandler
{
    /** Public provider endpoint: signature verification precedes DB and session work. */
    public static function interactions(?array $configuration = null, ?string $connectionRoot = null): never
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Allow: POST'); self::output(405, ['error' => 'METHOD_NOT_ALLOWED']);
        }
        // Optional server-owned arguments allow isolated HTTP fixtures, never request input.
        $config = $configuration ?? BotConfig::load();
        if (!BotConfig::enabled($config)) self::output(503, ['error' => 'UNAVAILABLE']);
        $body = (string)file_get_contents('php://input', false, null, 0, InteractionProtocol::MAX_BODY + 1);
        if (!InteractionProtocol::verify($body, (string)($_SERVER['HTTP_X_SIGNATURE_ED25519'] ?? ''), (string)($_SERVER['HTTP_X_SIGNATURE_TIMESTAMP'] ?? ''), $config['public_key'])) self::output(401, ['error' => 'INVALID_SIGNATURE']);
        try { $interaction = json_decode($body, true, 32, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { self::output(400, ['error' => 'INVALID_JSON']); }
        if (!is_array($interaction)) self::output(400, ['error' => 'INVALID_JSON']);
        if (($interaction['type'] ?? null) === 1) self::output(200, ['type' => 1]);
        if (!InteractionProtocol::envelope($interaction, $config)) self::output(403, ['error' => 'INVALID_CONTEXT']);
        try {
            // Connection config stays server-side; no player cookies authenticate this route.
            date_default_timezone_set('UTC');
            Connection::init($connectionRoot ?? dirname(__DIR__, 2));
            \Conquer\Logger::init($connectionRoot ? $connectionRoot . '/http.log' : dirname(__DIR__, 2) . '/logs/app.log');
            // Avoid waiting past Discord's 3-second response deadline on a busy game row.
            Connection::getInstance()->execute('SET SESSION innodb_lock_wait_timeout=1');
            self::output(200, MinigameService::handle($interaction, $config));
        } catch (\Throwable $e) {
            // Never log the raw interaction: it can contain a linking code or token.
            \Conquer\Observability\EventLog::exception($e,'discord.minigame');
            self::output(200, InteractionProtocol::message(InteractionProtocol::text('busy')));
        }
    }

    public static function accountAction(array $params = []): never
    {
        $session = Session::current();
        if (!$session) Response::error(401, 'UNAUTHENTICATED', Locale::t('discord.sign_in'));
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($csrf) || $csrf === '' || !hash_equals((string)$session['csrf_token'], $csrf)) Response::error(403, 'CSRF_INVALID', Locale::t('discord.reload'));
        if (!BotConfig::enabled(BotConfig::load())) Response::error(503, 'DISCORD_DISABLED', Locale::t('discord.unavailable'));
        ApiGuard::limit('discord.code', (string)$session['player_id'], 5, 600);
        try {
            $body = json_decode((string)file_get_contents('php://input'), true, 8, JSON_THROW_ON_ERROR);
            if (!is_array($body)) throw new \JsonException();
            WorldContext::current($body['expected_world_id'] ?? null);
            $result = match ($body['action'] ?? '') {
                'code' => MinigameService::issueCode((int)$session['player_id'], WorldContext::id()),
                'unlink' => MinigameService::unlink((int)$session['player_id']),
                default => throw new \DomainException(Locale::t('discord.invalid_action')),
            };
            Response::ok($result + ['discord' => MinigameService::accountState((int)$session['player_id'])]);
        } catch (\JsonException) {
            Response::error(400, 'INVALID_JSON', Locale::t('discord.invalid_action'));
        } catch (\DomainException $e) {
            Response::error(422, 'DISCORD_RULE', $e->getMessage());
        }
    }

    private static function output(int $status, array $data): never
    {
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }
}
