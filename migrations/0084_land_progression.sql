-- E16: persistent 8x8 land progression. Existing worlds are grandfathered open;
-- worlds created after this migration are initialized outer-only by the service.

CREATE TABLE IF NOT EXISTS world_land_rules (
    world_id       INT NOT NULL PRIMARY KEY,
    settings_json  JSON NOT NULL,
    revision       INT UNSIGNED NOT NULL DEFAULT 1,
    updated_by     BIGINT UNSIGNED NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT land_rules_world FOREIGN KEY (world_id) REFERENCES worlds(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS world_land_zones (
    world_id       INT NOT NULL,
    zone_key       VARCHAR(12) NOT NULL,
    status         VARCHAR(12) NOT NULL DEFAULT 'locked',
    opened_at      DATETIME NULL,
    opened_reason  VARCHAR(16) NULL,
    rule_revision  INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (world_id, zone_key),
    CONSTRAINT land_zone_world FOREIGN KEY (world_id) REFERENCES worlds(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS world_land_parts (
    id                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    world_id               INT NOT NULL,
    geometry_version       SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    parcel_x               SMALLINT UNSIGNED NOT NULL,
    parcel_y               SMALLINT UNSIGNED NOT NULL,
    zone_key               VARCHAR(12) NOT NULL,
    initial_level          TINYINT UNSIGNED NOT NULL,
    current_level          TINYINT UNSIGNED NOT NULL,
    progress_points        BIGINT UNSIGNED NOT NULL DEFAULT 0,
    developable_tile_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    has_spawn_anchor       TINYINT(1) NOT NULL DEFAULT 0,
    revision               INT UNSIGNED NOT NULL DEFAULT 1,
    created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY land_part_grid (world_id, geometry_version, parcel_x, parcel_y),
    INDEX land_part_zone_level (world_id, zone_key, has_spawn_anchor, current_level),
    CONSTRAINT land_part_world FOREIGN KEY (world_id) REFERENCES worlds(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS land_progress_events (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    world_id        INT NOT NULL,
    land_part_id    BIGINT UNSIGNED NULL,
    event_key       VARCHAR(100) NOT NULL,
    source_type     VARCHAR(24) NOT NULL,
    source_id       VARCHAR(80) NOT NULL,
    actor_player_id INT NULL,
    payload_hash    CHAR(64) NOT NULL,
    resource_code   VARCHAR(16) NULL,
    raw_amount      BIGINT UNSIGNED NOT NULL DEFAULT 0,
    raw_points      BIGINT UNSIGNED NOT NULL DEFAULT 0,
    credited_points BIGINT UNSIGNED NOT NULL DEFAULT 0,
    level_before    TINYINT UNSIGNED NULL,
    level_after     TINYINT UNSIGNED NULL,
    result_json     JSON NOT NULL,
    metadata_json   JSON NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY land_event_once (world_id, event_key),
    INDEX land_event_part (land_part_id, created_at),
    INDEX land_event_player (world_id, actor_player_id, created_at),
    CONSTRAINT land_event_world FOREIGN KEY (world_id) REFERENCES worlds(id) ON DELETE CASCADE,
    CONSTRAINT land_event_part_fk FOREIGN KEY (land_part_id) REFERENCES world_land_parts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS land_daily_contributions (
    world_id        INT NOT NULL,
    land_part_id    BIGINT UNSIGNED NOT NULL,
    player_id       INT NOT NULL,
    contribution_on DATE NOT NULL,
    source_type     VARCHAR(24) NOT NULL,
    raw_points      BIGINT UNSIGNED NOT NULL DEFAULT 0,
    credited_points BIGINT UNSIGNED NOT NULL DEFAULT 0,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (world_id, land_part_id, player_id, contribution_on, source_type),
    INDEX land_daily_player (player_id, contribution_on),
    CONSTRAINT land_daily_world FOREIGN KEY (world_id) REFERENCES worlds(id) ON DELETE CASCADE,
    CONSTRAINT land_daily_part FOREIGN KEY (land_part_id) REFERENCES world_land_parts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE field_monsters ADD COLUMN IF NOT EXISTS land_part_id BIGINT UNSIGNED NULL;
ALTER TABLE field_monsters ADD COLUMN IF NOT EXISTS regional_level_at_spawn TINYINT UNSIGNED NULL;
ALTER TABLE field_monsters ADD COLUMN IF NOT EXISTS effective_monster_level SMALLINT UNSIGNED NULL;
ALTER TABLE field_monsters ADD COLUMN IF NOT EXISTS spawn_rule_revision INT UNSIGNED NULL;
ALTER TABLE field_monsters ADD COLUMN IF NOT EXISTS regional_point_value INT UNSIGNED NULL;
ALTER TABLE field_monsters ADD INDEX IF NOT EXISTS idx_monster_land_part (world_id, land_part_id);

ALTER TABLE field_objects ADD COLUMN IF NOT EXISTS land_part_id BIGINT UNSIGNED NULL;
ALTER TABLE field_objects ADD COLUMN IF NOT EXISTS regional_level_at_spawn TINYINT UNSIGNED NULL;
ALTER TABLE field_objects ADD COLUMN IF NOT EXISTS spawn_rule_revision INT UNSIGNED NULL;
ALTER TABLE field_objects ADD INDEX IF NOT EXISTS idx_field_land_part (world_id, land_part_id);

INSERT IGNORE INTO world_land_rules (world_id, settings_json, revision)
SELECT id, '{"parcel_size":8,"geometry_version":1,"thresholds":{"1":1000,"2":2000,"3":4000,"4":8000,"5":16000,"6":32000,"7":64000,"8":128000},"resource_values":{"food":1,"lumber":1,"stone":2,"gold":4},"resource_units_per_point":100,"monster_points_per_level":100,"gather_full_daily_ratio":0.1,"gather_reduced_factor":0.25,"donation_daily_ratio":0.1,"gates":{"middle":{"source_zone":"outer","target_level":3,"ratio":0.1,"minimum_count":32,"not_before_days":14,"fallback_after_days":null},"center":{"source_zone":"middle","target_level":7,"ratio":0.15,"minimum_count":24,"not_before_days":14,"fallback_after_days":null}}}', 1
FROM worlds;

INSERT IGNORE INTO world_land_zones (world_id, zone_key, status, opened_at, opened_reason, rule_revision)
SELECT id, 'outer', 'open', COALESCE(started_at, created_at, UTC_TIMESTAMP()), 'legacy', 1 FROM worlds;
INSERT IGNORE INTO world_land_zones (world_id, zone_key, status, opened_at, opened_reason, rule_revision)
SELECT id, 'middle', 'open', COALESCE(started_at, created_at, UTC_TIMESTAMP()), 'legacy', 1 FROM worlds;
INSERT IGNORE INTO world_land_zones (world_id, zone_key, status, opened_at, opened_reason, rule_revision)
SELECT id, 'center', 'open', COALESCE(started_at, created_at, UTC_TIMESTAMP()), 'legacy', 1 FROM worlds;
