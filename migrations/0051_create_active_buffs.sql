-- Migration 0051: Active Buffs system (production_boost, research_boost, training_boost)
CREATE TABLE IF NOT EXISTS active_buffs (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    player_id   INT NOT NULL,
    buff_type   VARCHAR(30) NOT NULL,
    multiplier  DECIMAL(4,2) NOT NULL DEFAULT 1.00,
    expires_at  DATETIME NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_player_exp (player_id, expires_at),
    FOREIGN KEY (player_id) REFERENCES players(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
