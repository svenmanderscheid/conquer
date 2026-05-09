CREATE TABLE IF NOT EXISTS map_charms (
    id             BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    world_id       INT              NOT NULL DEFAULT 1,
    coord_x        INT              NOT NULL,
    coord_y        INT              NOT NULL,
    stat_category  VARCHAR(20)      NOT NULL,
    grade          ENUM('normal','epic','legendary') NOT NULL,
    charm_code     INT              NOT NULL,
    spawned_at     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at     DATETIME         NOT NULL,
    collected_by   INT              NULL DEFAULT NULL,
    collected_at   DATETIME         NULL DEFAULT NULL,
    PRIMARY KEY (id),
    INDEX idx_world_viewport (world_id, coord_x, coord_y),
    INDEX idx_expires (expires_at),
    INDEX idx_collector (collected_by)
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
