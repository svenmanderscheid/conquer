-- Migration 0031: Hospital wounded tracking
-- hospital_wounded — tracks injured troops awaiting healing per city

CREATE TABLE IF NOT EXISTS hospital_wounded (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    city_id          BIGINT UNSIGNED NOT NULL,
    troop_code       INT UNSIGNED    NOT NULL,
    count            INT UNSIGNED    NOT NULL DEFAULT 0,
    healing_ends_at  DATETIME        NOT NULL,
    created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_city_troop (city_id, troop_code),
    INDEX idx_city    (city_id),
    INDEX idx_healing (healing_ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
