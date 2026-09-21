<?php
declare(strict_types=1);

/**
 * Application configuration template.
 *
 * Copy this file to config/app.php for environment-specific overrides.
 * config/app.php is NOT gitignored — non-secret settings only.
 */

return [
    // App identity
    'name' => 'Conquer',
    'codename' => true,  // remove this when final name is chosen
    'version' => '0.1.0-dev',
    
    // Environment
    'env' => getenv('CONQUER_ENV') ?: 'production',  // explicitly opt into local development
    'debug' => false,
    'log_level' => 'info',
    
    // URLs
    'base_url' => 'http://localhost:8080',
    'asset_url' => '/assets',
    
    // Game
    'world_speed_factor' => 1.0,
    'max_players_per_world' => 5000,
    'beginner_shield_days' => 7,
    'inactive_hide_days' => 30,

    // Local-only convenience. Keep false outside a private development database.
    'local_free_skins' => false,
    'local_free_skins_database' => null,
    
    // Security
    'session_lifetime_minutes' => 60 * 24 * 7,  // 7 days
    'csrf_token_lifetime_minutes' => 30,
    'rate_limit_per_minute' => 120,

    // Transactional account email. The server's PHP mail transport must be configured.
    'mail' => [
        'from_address' => 'noreply@unionofkingdoms.com',
        'from_name' => 'Union of Kingdoms',
    ],

    // Player images stay private until this server-side check succeeds.
    'profile_images' => [
        'openai_api_key' => getenv('OPENAI_API_KEY') ?: '',
        'model' => 'omni-moderation-latest',
    ],

    // Premium checkout stays unavailable until a real provider is configured.
    // The preview adapter is development-only and also requires a 32+ character secret.
    'premium_payments' => [
        'provider' => null,
        'preview' => ['enabled' => false, 'secret' => ''],
    ],
    
    // Polling
    'poll_interval_active_ms' => 15000,
    'poll_interval_idle_ms' => 60000,
    
    // Paths
    'paths' => [
        'data' => __DIR__ . '/../data',
        'logs' => __DIR__ . '/../logs',
        'cache' => __DIR__ . '/../cache',
        'sessions' => __DIR__ . '/../sessions',
    ],
    
    // OAuth — register apps at:
    //   Google:  https://console.cloud.google.com/apis/credentials
    //   Discord: https://discord.com/developers/applications
    'oauth' => [
        'google' => [
            'client_id'     => '',   // CHANGE ME
            'client_secret' => getenv('CONQUER_GOOGLE_CLIENT_SECRET') ?: '',
            'redirect_uri'  => 'http://conquer.local/auth/google/callback',
        ],
        'discord' => [
            'client_id'     => '',   // CHANGE ME
            'client_secret' => getenv('CONQUER_DISCORD_CLIENT_SECRET') ?: '',
            'redirect_uri'  => 'http://conquer.local/auth/discord/callback',
        ],
    ],

    // Locale
    'default_locale' => 'en',
    'available_locales' => ['en', 'de', 'fr'],
    'timezone' => 'UTC',  // ALWAYS UTC for storage; convert at display layer
];
