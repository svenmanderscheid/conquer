-- Migration 0038: Daily quest tracking per player
-- player_daily_quests — one row per player+quest+date combination
-- claimed flag is set after the player collects the reward (separate from completed)

CREATE TABLE IF NOT EXISTS player_daily_quests (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    player_id  BIGINT UNSIGNED NOT NULL,
    quest_code VARCHAR(64)     NOT NULL,
    quest_date DATE            NOT NULL,
    progress   INT UNSIGNED    NOT NULL DEFAULT 0,
    target     INT UNSIGNED    NOT NULL DEFAULT 1,
    completed  TINYINT(1)      NOT NULL DEFAULT 0,
    claimed    TINYINT(1)      NOT NULL DEFAULT 0,
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_player_quest_date (player_id, quest_code, quest_date),
    INDEX idx_player_date (player_id, quest_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
