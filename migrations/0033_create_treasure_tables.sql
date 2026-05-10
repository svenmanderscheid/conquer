-- Migration 0033: Treasure system
-- player_treasures — collected treasures with fragment progress and equipped slots
-- Fragment logic: 10 fragments = unlock (level 1), every 10 more = +1 level, max level 10

CREATE TABLE IF NOT EXISTS player_treasures (
    id             BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    player_id      BIGINT UNSIGNED  NOT NULL,
    treasure_code  INT UNSIGNED     NOT NULL,
    fragments      INT UNSIGNED     NOT NULL DEFAULT 0,
    equipped_slot  TINYINT UNSIGNED NULL COMMENT '1-6, NULL = not equipped',
    created_at     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_player_treasure (player_id, treasure_code),
    INDEX idx_player   (player_id),
    INDEX idx_equipped (player_id, equipped_slot)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
