-- Neutral villages: hidden resource producers with PvP-like combat losses.
CREATE TABLE IF NOT EXISTS neutral_villages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    world_id BIGINT UNSIGNED NOT NULL,
    coord_x SMALLINT UNSIGNED NOT NULL,
    coord_y SMALLINT UNSIGNED NOT NULL,
    level TINYINT UNSIGNED NOT NULL DEFAULT 1,
    name VARCHAR(80) NOT NULL DEFAULT 'Freies Dorf',
    garrison_json JSON NOT NULL,
    food BIGINT UNSIGNED NOT NULL DEFAULT 0,
    lumber BIGINT UNSIGNED NOT NULL DEFAULT 0,
    stone BIGINT UNSIGNED NOT NULL DEFAULT 0,
    gold BIGINT UNSIGNED NOT NULL DEFAULT 0,
    last_production_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_attacked_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_neutral_village_coords (world_id, coord_x, coord_y),
    KEY idx_neutral_village_world_level (world_id, level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
