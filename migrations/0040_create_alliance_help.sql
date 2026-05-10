-- Migration 0040: Alliance help request system
-- alliance_help_requests — one entry per queue item that needs help
-- alliance_help_log      — one row per individual help action (for per-player dedup)

CREATE TABLE IF NOT EXISTS alliance_help_requests (
    id         BIGINT UNSIGNED                              NOT NULL AUTO_INCREMENT,
    queue_type ENUM('building','research','training','healing') NOT NULL,
    queue_id   BIGINT UNSIGNED                              NOT NULL,
    player_id  BIGINT UNSIGNED                              NOT NULL COMMENT 'Who needs help',
    city_id    BIGINT UNSIGNED                              NOT NULL,
    help_count INT UNSIGNED                                 NOT NULL DEFAULT 0,
    max_helps  INT UNSIGNED                                 NOT NULL DEFAULT 30,
    completed  TINYINT(1)                                   NOT NULL DEFAULT 0,
    created_at DATETIME                                     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_player (player_id),
    INDEX idx_queue  (queue_type, queue_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alliance_help_log (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id BIGINT UNSIGNED NOT NULL,
    helper_id  BIGINT UNSIGNED NOT NULL,
    helped_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_request     (request_id),
    INDEX idx_helper_date (helper_id, helped_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
