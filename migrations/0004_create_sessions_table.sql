-- DB-backed sessions (not PHP file sessions — for horizontal scalability).
-- token + csrf_token: bin2hex(random_bytes(32)) — 64 hex chars each.
CREATE TABLE sessions (
    id           BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    player_id    INT NOT NULL,
    token        CHAR(64) NOT NULL,
    csrf_token   CHAR(64) NOT NULL,
    ip_address   VARCHAR(45) NOT NULL,
    user_agent   VARCHAR(255) NOT NULL DEFAULT '',
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_active  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at   DATETIME NOT NULL,
    UNIQUE KEY (token),
    INDEX (player_id),
    INDEX (expires_at),
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
