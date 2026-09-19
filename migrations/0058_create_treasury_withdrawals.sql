-- Durable receipts make retrying a treasury withdrawal safe after a lost HTTP response.
CREATE TABLE IF NOT EXISTS kingdom_treasury_withdrawals (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    alliance_id INT UNSIGNED NOT NULL,
    player_id INT NOT NULL,
    request_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    resource ENUM('food','lumber','stone','gold') NOT NULL,
    amount INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_player_request (player_id,request_id),
    INDEX idx_alliance_created (alliance_id,created_at),
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
