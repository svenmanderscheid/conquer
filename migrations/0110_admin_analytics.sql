CREATE TABLE IF NOT EXISTS player_activity_minutes (
    player_id INT NOT NULL,
    world_id INT NOT NULL,
    minute_slot DATETIME NOT NULL,
    PRIMARY KEY (player_id, world_id, minute_slot),
    INDEX idx_activity_world_time (world_id, minute_slot),
    CONSTRAINT fk_activity_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    CONSTRAINT fk_activity_world FOREIGN KEY (world_id) REFERENCES worlds(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
