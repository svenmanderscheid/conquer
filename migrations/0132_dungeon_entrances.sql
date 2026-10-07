-- A registered entrance reserves land without relocating existing world objects.
CREATE TABLE IF NOT EXISTS world_dungeon_entrances (
    world_id INT UNSIGNED NOT NULL,
    dungeon_code VARCHAR(64) NOT NULL,
    coord_x INT NOT NULL,
    coord_y INT NOT NULL,
    footprint TINYINT UNSIGNED NOT NULL DEFAULT 3,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (world_id,dungeon_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
