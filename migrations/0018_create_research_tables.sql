-- player_research: stores which research each player has unlocked and at which level.
-- One row per (player, world, research_code) — upserted when a queue entry completes.
CREATE TABLE player_research (
    player_id     INT NOT NULL,
    world_id      INT NOT NULL DEFAULT 1,
    research_code VARCHAR(60) NOT NULL,
    level         TINYINT UNSIGNED NOT NULL DEFAULT 0,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (player_id, world_id, research_code),
    FOREIGN KEY (player_id) REFERENCES players(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- research_queue: at most one active research at a time per (player, world).
-- is_processed = 0 → pending/in progress; 1 → applied to player_research.
CREATE TABLE research_queue (
    id            INT PRIMARY KEY AUTO_INCREMENT,
    player_id     INT NOT NULL,
    world_id      INT NOT NULL DEFAULT 1,
    research_code VARCHAR(60) NOT NULL,
    level_to      TINYINT UNSIGNED NOT NULL,
    started_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finishes_at   DATETIME NOT NULL,
    is_processed  TINYINT(1) NOT NULL DEFAULT 0,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_finish (finishes_at, is_processed),
    FOREIGN KEY (player_id) REFERENCES players(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
