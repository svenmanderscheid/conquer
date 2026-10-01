<?php
declare(strict_types=1);
namespace Conquer\Discord;

final class BotConfig
{
    public static function load(): array
    {
        $file = dirname(__DIR__, 2) . '/config/discord.php';
        $config = is_file($file) ? require $file : [];
        return is_array($config) ? $config : [];
    }

    public static function enabled(array $config): bool
    {
        return ($config['enabled'] ?? false) === true
            && preg_match('/^[a-f0-9]{64}$/iD', (string)($config['public_key'] ?? '')) === 1
            && self::snowflake($config['application_id'] ?? null)
            && !empty($config['guild_ids']) && is_array($config['guild_ids'])
            && count(array_filter($config['guild_ids'], [self::class, 'snowflake'])) === count($config['guild_ids'])
            && function_exists('sodium_crypto_sign_verify_detached');
    }

    public static function snowflake(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[1-9][0-9]{16,19}$/D', $value) === 1;
    }
}
