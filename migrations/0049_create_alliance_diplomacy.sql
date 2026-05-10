CREATE TABLE IF NOT EXISTS alliance_diplomacy (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    alliance_id   BIGINT UNSIGNED NOT NULL,
    target_id     BIGINT UNSIGNED NOT NULL,
    relation      ENUM('ally','nap','war') NOT NULL,
    initiated_by  BIGINT UNSIGNED NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_relation (alliance_id, target_id),
    INDEX idx_target (target_id)
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
