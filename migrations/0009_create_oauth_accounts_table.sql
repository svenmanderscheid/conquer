-- Links players to OAuth provider accounts.
-- One player can have one account per provider (same email on both = same player row).
CREATE TABLE oauth_accounts (
    id               BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    player_id        INT NOT NULL,
    provider         ENUM('google','discord') NOT NULL,
    provider_user_id VARCHAR(100) NOT NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (provider, provider_user_id),
    INDEX (player_id),
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
