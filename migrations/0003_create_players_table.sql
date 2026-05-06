-- Player accounts (global, not per-world).
-- Schema: SPEC.md §22.1
CREATE TABLE players (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    username        VARCHAR(30) UNIQUE NOT NULL,
    email           VARCHAR(100) UNIQUE NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login      DATETIME NULL,
    is_banned       TINYINT(1) DEFAULT 0,
    vip_level       SMALLINT DEFAULT 1,
    vip_points      INT DEFAULT 0,
    gems            INT DEFAULT 0,
    INDEX (last_login)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
