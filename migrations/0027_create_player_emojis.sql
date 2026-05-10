-- One active emoji per player. expires_at = UTC_TIMESTAMP + 5 seconds.
CREATE TABLE player_emojis (
    player_id  INT NOT NULL,
    emoji_code VARCHAR(32)     NOT NULL,
    expires_at DATETIME        NOT NULL,
    PRIMARY KEY (player_id),
    CONSTRAINT fk_pe_player FOREIGN KEY (player_id)
        REFERENCES players (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
