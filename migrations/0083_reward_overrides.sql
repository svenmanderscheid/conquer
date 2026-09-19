-- Global reward definitions; historical runs keep their saved reward snapshots.
CREATE TABLE IF NOT EXISTS reward_overrides (
    source_type VARCHAR(20) NOT NULL,
    source_key VARCHAR(80) NOT NULL,
    config_json JSON NULL,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    updated_by BIGINT UNSIGNED NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (source_type, source_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
