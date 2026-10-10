-- Explicit per-device opt-in; session revocation removes the device and queued work.
CREATE TABLE IF NOT EXISTS push_subscriptions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    player_id INT NOT NULL,
    session_id BIGINT UNSIGNED NOT NULL,
    platform VARCHAR(12) NOT NULL DEFAULT 'web',
    endpoint_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    endpoint VARCHAR(2048) NOT NULL,
    public_key VARCHAR(100) NOT NULL,
    auth_token VARCHAR(32) NOT NULL,
    locale VARCHAR(8) NOT NULL DEFAULT 'en',
    completions TINYINT(1) NOT NULL DEFAULT 1,
    security TINYINT(1) NOT NULL DEFAULT 1,
    -- Immutable opt-in baseline; scans overlap because transaction IDs may commit out of order.
    last_notification_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    scanned_at DATETIME NULL,
    last_test_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY push_endpoint (endpoint_hash),
    INDEX push_player (player_id),
    INDEX push_scan (scanned_at),
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS push_deliveries (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    subscription_id BIGINT UNSIGNED NOT NULL,
    notification_id BIGINT UNSIGNED NULL,
    world_id INT NOT NULL DEFAULT 1,
    category VARCHAR(16) NOT NULL,
    delivery_tag CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    outcome VARCHAR(16) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY push_notification (subscription_id, notification_id),
    INDEX push_pending (completed_at, next_attempt_at),
    FOREIGN KEY (subscription_id) REFERENCES push_subscriptions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
