CREATE TABLE IF NOT EXISTS alliance_structures (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    alliance_id INT UNSIGNED NOT NULL,
    world_id INT NOT NULL,
    structure_type ENUM('center','outpost') NOT NULL,
    coord_x SMALLINT UNSIGNED NOT NULL,
    coord_y SMALLINT UNSIGNED NOT NULL,
    placed_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_alliance_structure_coordinate (world_id,coord_x,coord_y),
    KEY idx_alliance_structures_alliance (alliance_id,structure_type),
    KEY idx_alliance_structures_world (world_id,coord_x,coord_y)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
