-- Migration 0047: Alliance treasury and donation log
-- alliance_treasury  — one row per alliance, holds communal resource reserves
-- alliance_donations — append-only log of every member donation

CREATE TABLE IF NOT EXISTS alliance_treasury (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    alliance_id BIGINT UNSIGNED NOT NULL,
    food        BIGINT UNSIGNED NOT NULL DEFAULT 0,
    lumber      BIGINT UNSIGNED NOT NULL DEFAULT 0,
    stone       BIGINT UNSIGNED NOT NULL DEFAULT 0,
    gold        BIGINT UNSIGNED NOT NULL DEFAULT 0,
    updated_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_alliance (alliance_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alliance_donations (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    alliance_id BIGINT UNSIGNED NOT NULL,
    player_id   BIGINT UNSIGNED NOT NULL,
    food        INT UNSIGNED    NOT NULL DEFAULT 0,
    lumber      INT UNSIGNED    NOT NULL DEFAULT 0,
    stone       INT UNSIGNED    NOT NULL DEFAULT 0,
    gold        INT UNSIGNED    NOT NULL DEFAULT 0,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_alliance_date (alliance_id, created_at),
    INDEX idx_player        (player_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
