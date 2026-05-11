-- Alliance Research tables
-- Sprint 4d: Alliance Research system with 8 nodes, 10 levels each

CREATE TABLE IF NOT EXISTS alliance_research (
    id            INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    alliance_id   INT UNSIGNED NOT NULL,
    research_code VARCHAR(60) NOT NULL,
    level         TINYINT UNSIGNED NOT NULL DEFAULT 0,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_research (alliance_id, research_code),
    FOREIGN KEY (alliance_id) REFERENCES alliances(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alliance_research_queue (
    id            INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    alliance_id   INT UNSIGNED NOT NULL,
    research_code VARCHAR(60) NOT NULL,
    level_to      TINYINT UNSIGNED NOT NULL,
    started_by    INT NOT NULL,
    started_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finishes_at   DATETIME NOT NULL,
    is_processed  TINYINT(1) NOT NULL DEFAULT 0,
    INDEX idx_finish (finishes_at, is_processed),
    FOREIGN KEY (alliance_id) REFERENCES alliances(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
