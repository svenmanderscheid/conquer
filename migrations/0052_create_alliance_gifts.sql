-- Migration 0052: Alliance Gifts system
CREATE TABLE IF NOT EXISTS alliance_gifts (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    alliance_id INT UNSIGNED NOT NULL,
    trigger_type VARCHAR(30) NOT NULL,
    gift_json   JSON NOT NULL,
    expires_at  DATETIME NOT NULL,
    created_by  INT NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_alliance_exp (alliance_id, expires_at),
    FOREIGN KEY (alliance_id) REFERENCES alliances(id),
    FOREIGN KEY (created_by) REFERENCES players(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alliance_gift_claims (
    gift_id    INT NOT NULL,
    player_id  INT NOT NULL,
    claimed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (gift_id, player_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
