CREATE TABLE IF NOT EXISTS alpha_waitlist (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(80) NOT NULL,
    last_name VARCHAR(80) NOT NULL,
    email VARCHAR(254) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    locale VARCHAR(2) NOT NULL DEFAULT 'de',
    consent_version VARCHAR(40) NOT NULL,
    consent_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    invited_at DATETIME NULL,
    UNIQUE KEY uq_alpha_waitlist_email (email),
    KEY idx_alpha_waitlist_created (created_at, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
