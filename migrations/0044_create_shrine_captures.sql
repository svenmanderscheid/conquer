-- Migration 0044: Shrine capture and garrison system
-- shrine_captures   — one row per shrine, tracks ownership and contest timer
-- shrine_garrisons  — troops sent by alliance members to defend a shrine
-- alliance_id NULL = NPC controlled
-- contested_until = 1h hold timer before capture is secured

CREATE TABLE IF NOT EXISTS shrine_captures (
    id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    shrine_id             BIGINT UNSIGNED NOT NULL,
    alliance_id           BIGINT UNSIGNED NULL COMMENT 'NULL = NPC controlled',
    captured_at           DATETIME        NULL,
    contested_until       DATETIME        NULL COMMENT '1h hold timer',
    secured_at            DATETIME        NULL,
    garrison_troops_json  TEXT            NOT NULL DEFAULT '{}' COMMENT 'NPC or defending alliance troops',
    updated_at            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_shrine (shrine_id),
    INDEX idx_alliance (alliance_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shrine_garrisons (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    shrine_id   BIGINT UNSIGNED NOT NULL,
    player_id   BIGINT UNSIGNED NOT NULL,
    city_id     BIGINT UNSIGNED NOT NULL,
    troops_json TEXT            NOT NULL DEFAULT '{}',
    sent_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_shrine_player (shrine_id, player_id),
    INDEX idx_shrine (shrine_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
