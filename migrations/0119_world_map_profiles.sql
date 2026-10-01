-- Additive only: existing worlds retain their current map and every saved position.
CREATE TABLE IF NOT EXISTS world_map_profiles (
    world_id INT NOT NULL PRIMARY KEY,
    profile_key VARCHAR(32) NOT NULL,
    geometry_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    continent_id VARCHAR(40) NOT NULL,
    map_width SMALLINT UNSIGNED NOT NULL,
    map_height SMALLINT UNSIGNED NOT NULL,
    geometry_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_world_map_profile_world FOREIGN KEY (world_id) REFERENCES worlds(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
