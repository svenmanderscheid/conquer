CREATE TABLE IF NOT EXISTS alliance_structure_garrisons (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    structure_id BIGINT UNSIGNED NOT NULL,
    alliance_id BIGINT UNSIGNED NOT NULL,
    player_id BIGINT UNSIGNED NOT NULL,
    city_id BIGINT UNSIGNED NOT NULL,
    troops_json TEXT NOT NULL DEFAULT '{}',
    sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_alliance_structure_player (structure_id,player_id),
    KEY idx_alliance_structure_garrison (structure_id,alliance_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
