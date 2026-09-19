CREATE TABLE IF NOT EXISTS alpha_access_keys (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(120) NOT NULL,
    key_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    max_uses SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    uses_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATETIME NULL,
    revoked_at DATETIME NULL,
    last_used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_alpha_access_key_hash (key_hash),
    INDEX idx_alpha_access_available (revoked_at, expires_at, uses_count)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE players
    ADD COLUMN IF NOT EXISTS alpha_access_key_id BIGINT UNSIGNED NULL AFTER password_hash;

ALTER TABLE players
    ADD INDEX IF NOT EXISTS idx_players_alpha_access_key (alpha_access_key_id);

