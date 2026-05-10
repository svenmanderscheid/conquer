-- Migration 0035: World field objects (resource nodes on the map)
-- field_objects — farm/lumber/quarry/gold_mine/gem_node tiles that spawn on the world map
-- object_type: 1=farm, 2=lumber, 3=quarry, 4=gold_mine, 5=gem_node
-- gatherer_march_id locks the node to a single gathering march while occupied

CREATE TABLE IF NOT EXISTS field_objects (
    id                BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    world_id          BIGINT UNSIGNED  NOT NULL DEFAULT 1,
    coord_x           SMALLINT UNSIGNED NOT NULL,
    coord_y           SMALLINT UNSIGNED NOT NULL,
    object_type       TINYINT UNSIGNED NOT NULL COMMENT '1=farm,2=lumber,3=quarry,4=gold_mine,5=gem_node',
    level             TINYINT UNSIGNED NOT NULL DEFAULT 1,
    resource_amount   INT UNSIGNED     NOT NULL DEFAULT 0 COMMENT 'Current remaining resources',
    resource_max      INT UNSIGNED     NOT NULL DEFAULT 0,
    spawned_at        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at        DATETIME         NOT NULL,
    gatherer_march_id BIGINT UNSIGNED  NULL COMMENT 'Locked to this march while gathering',
    PRIMARY KEY (id),
    INDEX idx_world_coords (world_id, coord_x, coord_y),
    INDEX idx_expires      (expires_at),
    INDEX idx_type_level   (object_type, level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
