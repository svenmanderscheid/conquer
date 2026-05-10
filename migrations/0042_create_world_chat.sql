-- Migration 0042: World chat message log
-- world_chat — rolling chat per world; old messages can be purged by cron
-- alliance_tag stored denormalized for fast display without JOIN

CREATE TABLE IF NOT EXISTS world_chat (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    world_id     BIGINT UNSIGNED NOT NULL DEFAULT 1,
    player_id    BIGINT UNSIGNED NOT NULL,
    username     VARCHAR(64)     NOT NULL,
    alliance_tag VARCHAR(8)      NULL,
    message      VARCHAR(200)    NOT NULL,
    created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_world_recent (world_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
