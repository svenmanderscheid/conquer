-- Five equipment snapshots per player/world. No rows means never saved;
-- a saved array containing six nulls deliberately unequips every slot.
CREATE TABLE IF NOT EXISTS player_treasure_presets (
    player_id BIGINT UNSIGNED NOT NULL,
    world_id INT NOT NULL,
    preset TINYINT UNSIGNED NOT NULL,
    items_json JSON NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (player_id,world_id,preset),
    CONSTRAINT treasure_preset_range CHECK (preset BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
