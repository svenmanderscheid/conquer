-- Login rate-limiting: max 5 attempts per IP per minute.
-- Old rows are cleaned up by the auth layer on each login attempt.
CREATE TABLE login_attempts (
    id           BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    ip_address   VARCHAR(45) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
