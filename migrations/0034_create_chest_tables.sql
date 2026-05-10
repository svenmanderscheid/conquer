-- Migration 0034: Chest inventory and free daily silver tracking
-- player_chests — one row per player, tracks silver/gold/platinum chest counts
-- free_silver_used_today resets daily via last_free_silver_reset date check

CREATE TABLE IF NOT EXISTS player_chests (
    id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    player_id             BIGINT UNSIGNED NOT NULL,
    silver_count          INT UNSIGNED    NOT NULL DEFAULT 0,
    gold_count            INT UNSIGNED    NOT NULL DEFAULT 0,
    platinum_count        INT UNSIGNED    NOT NULL DEFAULT 0,
    free_silver_used_today INT UNSIGNED   NOT NULL DEFAULT 0,
    last_free_silver_reset DATE           NULL,
    created_at            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_player (player_id),
    INDEX idx_player (player_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
