-- Migration runner tracking table.
-- Must be the first migration — the runner creates this before applying anything else.
CREATE TABLE IF NOT EXISTS migrations (
    id           BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    filename     VARCHAR(255) NOT NULL,
    applied_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (filename)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
