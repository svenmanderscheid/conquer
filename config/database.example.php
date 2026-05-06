<?php
declare(strict_types=1);

/**
 * Database configuration template.
 *
 * Copy this file to config/database.php and edit with your local credentials.
 * config/database.php is gitignored — never commit real credentials.
 */

return [
    'driver'   => 'mysql',
    'host'     => '127.0.0.1',
    'port'     => 3306,
    'database' => 'conquer_dev',     // CHANGE ME
    'username' => 'conquer_user',    // CHANGE ME
    'password' => 'changeme_local',  // CHANGE ME
    'charset'  => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    
    // PDO options
    'options' => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
    ],
    
    // App-level settings
    'debug' => true,  // false in production
];
