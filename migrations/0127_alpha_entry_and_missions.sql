CREATE TABLE IF NOT EXISTS world_entry_settings (
    world_id INT UNSIGNED NOT NULL PRIMARY KEY,
    default_slot TINYINT UNSIGNED NULL,
    starting_resources INT UNSIGNED NOT NULL DEFAULT 100000,
    spawn_canton VARCHAR(8) NULL,
    spawn_x SMALLINT UNSIGNED NULL,
    spawn_y SMALLINT UNSIGNED NULL,
    spawn_radius SMALLINT UNSIGNED NOT NULL DEFAULT 64,
    UNIQUE KEY one_default_world (default_slot)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS player_starter_missions (
    player_id INT UNSIGNED NOT NULL,
    world_id INT UNSIGNED NOT NULL,
    quest_code VARCHAR(60) NOT NULL,
    claimed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (player_id,world_id,quest_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
