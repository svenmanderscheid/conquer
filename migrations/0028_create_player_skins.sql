CREATE TABLE player_skins (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    player_id   INT NOT NULL,
    skin_code   VARCHAR(64)     NOT NULL,
    acquired_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_equipped TINYINT(1)      NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY  uq_player_skin (player_id, skin_code),
    CONSTRAINT fk_ps_player FOREIGN KEY (player_id)
        REFERENCES players (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
