-- Worlds (game instances). Required before cities (FK).
-- Schema: SPEC.md §22.1
CREATE TABLE worlds (
    id                  INT PRIMARY KEY AUTO_INCREMENT,
    name                VARCHAR(50) NOT NULL,
    slug                VARCHAR(20) UNIQUE NOT NULL,
    status              ENUM('open','running','paused','closed') DEFAULT 'open',
    speed_factor        FLOAT DEFAULT 1.0,
    gather_factor       FLOAT DEFAULT 1.0,
    haul_factor         FLOAT DEFAULT 1.0,
    base_mortality_rate FLOAT DEFAULT 0.0,
    map_size            SMALLINT DEFAULT 1024,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    started_at          DATETIME NULL,
    INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
