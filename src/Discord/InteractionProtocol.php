<?php
declare(strict_types=1);
namespace Conquer\Discord;

use Conquer\Game\Locale;

final class InteractionProtocol
{
    public const MAX_BODY = 65536;

    public static function verify(string $body, string $signature, string $timestamp, string $publicKey, ?int $now = null): bool
    {
        if (strlen($body) > self::MAX_BODY || !function_exists('sodium_crypto_sign_verify_detached')
            || preg_match('/^[a-f0-9]{128}$/iD', $signature) !== 1
            || preg_match('/^[a-f0-9]{64}$/iD', $publicKey) !== 1
            || preg_match('/^[0-9]{10}$/D', $timestamp) !== 1
            || abs(($now ?? time()) - (int)$timestamp) > 300) return false;
        return sodium_crypto_sign_verify_detached(hex2bin($signature), $timestamp . $body, hex2bin($publicKey));
    }

    public static function envelope(array $interaction, array $config): bool
    {
        return ($interaction['application_id'] ?? '') === ($config['application_id'] ?? null)
            && BotConfig::snowflake($interaction['id'] ?? null)
            && in_array($interaction['guild_id'] ?? null, $config['guild_ids'] ?? [], true)
            && BotConfig::snowflake($interaction['member']['user']['id'] ?? null)
            && empty($interaction['member']['user']['bot'])
            && in_array($interaction['type'] ?? null, [2, 3], true);
    }

    public static function text(string $key, array $params = []): string
    {
        // Discord's client language is not an explicit game-language preference.
        return Locale::t('discord.' . $key, $params, 'en');
    }

    public static function message(string $content, array $components = [], bool $update = false): array
    {
        $data = ['content' => $content, 'allowed_mentions' => ['parse' => []], 'components' => $components];
        if (!$update) $data['flags'] = 64; // Ephemeral: codes and rewards are private.
        return ['type' => $update ? 7 : 4, 'data' => $data];
    }

    public static function commands(): array
    {
        return [
            ['name' => 'link', 'description' => self::text('command.link'), 'type' => 1,
                'options' => [['type' => 3, 'name' => 'code', 'description' => self::text('command.code'), 'required' => true, 'min_length' => 32, 'max_length' => 32]]],
            ['name' => 'expedition', 'description' => self::text('command.expedition'), 'type' => 1],
        ];
    }
}
