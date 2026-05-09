CREATE TABLE IF NOT EXISTS player_charms_active (
    id             INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    player_id      INT              NOT NULL,
    stat_category  VARCHAR(20)      NOT NULL,
    grade          ENUM('normal','epic','legendary') NOT NULL,
    charm_code     INT              NOT NULL,
    bonus_pct      FLOAT            NOT NULL,
    activated_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at     DATETIME         NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY one_per_category (player_id, stat_category),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
