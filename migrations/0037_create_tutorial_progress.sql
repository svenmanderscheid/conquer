-- Migration 0037: Tutorial progress per player
-- tutorial_progress — tracks which step the player is on (1-12)
-- completed_at / skipped_at are mutually exclusive; NULL = tutorial still active

CREATE TABLE IF NOT EXISTS tutorial_progress (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    player_id    BIGINT UNSIGNED NOT NULL,
    current_step TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1-12',
    completed_at DATETIME        NULL,
    skipped_at   DATETIME        NULL,
    created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_player (player_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
