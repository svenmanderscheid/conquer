-- Add deterministic terrain seed to worlds table.
-- Client-side JS uses this seed to generate terrain (no tile-by-tile DB storage).
ALTER TABLE worlds ADD COLUMN map_seed INT UNSIGNED NOT NULL DEFAULT 42 AFTER map_size;
UPDATE worlds SET map_seed = 123456789 WHERE id = 1;

-- Field objects (resource nodes spawned on the map).
-- Schema: SPEC.md §22
CREATE TABLE field_objects (
    id            INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    world_id      INT NOT NULL,
    object_code   INT NOT NULL,
    coord_x       SMALLINT NOT NULL,
    coord_y       SMALLINT NOT NULL,
    remaining     INT NOT NULL,
    spawned_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_gathered DATETIME NULL,
    INDEX (world_id, coord_x, coord_y),
    INDEX (world_id, object_code),
    FOREIGN KEY (world_id) REFERENCES worlds(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Field monsters (monsters spawned on the map).
-- Schema: SPEC.md §22
CREATE TABLE field_monsters (
    id           INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    world_id     INT NOT NULL,
    monster_code INT NOT NULL,
    coord_x      SMALLINT NOT NULL,
    coord_y      SMALLINT NOT NULL,
    hp_current   INT NOT NULL,
    spawned_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (world_id, coord_x, coord_y),
    INDEX (world_id, monster_code),
    FOREIGN KEY (world_id) REFERENCES worlds(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Shrines (conquest control points).
-- Schema: SPEC.md §22
CREATE TABLE shrines (
    id                     INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    world_id               INT NOT NULL,
    shrine_code            VARCHAR(20) NOT NULL,
    tier                   ENUM('C','B','A','S') NOT NULL,
    coord_x                SMALLINT NOT NULL,
    coord_y                SMALLINT NOT NULL,
    owner_alliance_id      INT NULL,
    captured_at            DATETIME NULL,
    secured_at             DATETIME NULL,
    contesting_alliance_id INT NULL,
    contest_started_at     DATETIME NULL,
    UNIQUE KEY one_per_code (world_id, shrine_code),
    INDEX (world_id, owner_alliance_id),
    INDEX (world_id, tier),
    FOREIGN KEY (world_id) REFERENCES worlds(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
