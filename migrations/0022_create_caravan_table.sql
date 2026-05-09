CREATE TABLE IF NOT EXISTS caravan_state (
    player_id       INT NOT NULL,
    world_id        INT NOT NULL DEFAULT 1,
    refreshed_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    next_refresh_at DATETIME NOT NULL,
    slots_json      JSON NOT NULL,
    PRIMARY KEY (player_id, world_id)
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
