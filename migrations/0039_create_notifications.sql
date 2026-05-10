-- Migration 0039: In-game notification inbox per player
-- notifications — build_complete, research_complete, train_complete,
--                 march_returned, battle_incoming, etc.
-- read_at NULL = unread; set to NOW() when player opens/dismisses

CREATE TABLE IF NOT EXISTS notifications (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    player_id  BIGINT UNSIGNED NOT NULL,
    type       VARCHAR(64)     NOT NULL COMMENT 'build_complete|research_complete|train_complete|march_returned|battle_incoming|...',
    data_json  TEXT            NULL,
    read_at    DATETIME        NULL,
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_player_unread (player_id, read_at),
    INDEX idx_created       (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
