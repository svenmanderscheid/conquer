-- Migration 0041: Alliance reinforcement (support march) tracking
-- reinforcements — links a support march to its sender and target city
-- state transitions: active → recalled | expired

CREATE TABLE IF NOT EXISTS reinforcements (
    id               BIGINT UNSIGNED                         NOT NULL AUTO_INCREMENT,
    march_id         BIGINT UNSIGNED                         NOT NULL COMMENT 'The support march',
    sender_id        BIGINT UNSIGNED                         NOT NULL,
    sender_city_id   BIGINT UNSIGNED                         NOT NULL,
    target_player_id BIGINT UNSIGNED                         NOT NULL,
    target_city_id   BIGINT UNSIGNED                         NOT NULL,
    troops_json      TEXT                                    NOT NULL DEFAULT '{}',
    state            ENUM('active','recalled','expired')     NOT NULL DEFAULT 'active',
    recalled_at      DATETIME                                NULL,
    created_at       DATETIME                                NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_target_city (target_city_id),
    INDEX idx_sender      (sender_id),
    INDEX idx_march       (march_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
