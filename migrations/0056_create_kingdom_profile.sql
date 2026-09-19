-- Supporting PvE client data. Existing player names and alliance membership remain intact.
CREATE TABLE IF NOT EXISTS kingdom_profiles (
    player_id INT NOT NULL PRIMARY KEY,
    display_name VARCHAR(30) NOT NULL,
    avatar VARCHAR(20) NOT NULL DEFAULT 'knight',
    bio VARCHAR(300) NOT NULL DEFAULT '',
    reduced_motion TINYINT(1) NOT NULL DEFAULT 0,
    compact_numbers TINYINT(1) NOT NULL DEFAULT 1,
    confirm_actions TINYINT(1) NOT NULL DEFAULT 1,
    welcome_claimed TINYINT(1) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kingdom_arena_challenges (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    challenger_id INT NOT NULL,
    opponent_id INT NOT NULL,
    status ENUM('pending','completed','declined','cancelled','expired') NOT NULL DEFAULT 'pending',
    challenger_army_json MEDIUMTEXT NOT NULL,
    winner_id INT NULL,
    result_json MEDIUMTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    resolved_at DATETIME NULL,
    INDEX idx_challenger (challenger_id,status,created_at),
    INDEX idx_opponent (opponent_id,status,created_at),
    FOREIGN KEY (challenger_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (opponent_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
