CREATE TABLE IF NOT EXISTS player_universal_treasure_fragments (
    player_id BIGINT UNSIGNED NOT NULL,
    grade ENUM('normal','rare','epic','legendary','mythic') NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (player_id,grade),
    CONSTRAINT universal_treasure_fragments_quantity CHECK (quantity >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
