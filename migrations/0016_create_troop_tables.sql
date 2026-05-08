-- Migration 0016: Troop tables
-- city_troops  — standing army per city
-- troop_queue  — training queue (per SPEC §22)

CREATE TABLE IF NOT EXISTS city_troops (
    city_id     INT NOT NULL,
    troop_code  INT NOT NULL,
    count       INT NOT NULL DEFAULT 0,
    PRIMARY KEY (city_id, troop_code),
    FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS troop_queue (
    id           INT PRIMARY KEY AUTO_INCREMENT,
    city_id      INT NOT NULL,
    troop_code   INT NOT NULL,
    count        INT NOT NULL,
    barrack_slot TINYINT NOT NULL DEFAULT 1,
    started_at   DATETIME NOT NULL,
    finishes_at  DATETIME NOT NULL,
    is_processed TINYINT(1) NOT NULL DEFAULT 0,
    INDEX idx_finishes (finishes_at, is_processed),
    FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
