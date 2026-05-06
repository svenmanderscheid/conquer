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
    'env' => 'development',  // 'development' | 'staging' | 'production'
    'debug' => true,
    'log_level' => 'debug',  // 'debug' | 'info' | 'warning' | 'error'
    
    // URLs
    'base_url' => 'http://localhost:8080',
    'asset_url' => '/assets',
    
    // Game
    'world_speed_factor' => 1.0,
    'max_players_per_world' => 5000,
    'beginner_shield_days' => 7,
    'inactive_hide_days' => 30,
    
    // Security
    'session_lifetime_minutes' => 60 * 24 * 7,  // 7 days
    'csrf_token_lifetime_minutes' => 30,
    'rate_limit_per_minute' => 120,
    
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
    
    // Locale
    'default_locale' => 'en',
    'available_locales' => ['en', 'de', 'fr'],
    'timezone' => 'UTC',  // ALWAYS UTC for storage; convert at display layer
];
