-- Building upgrade queue. One active entry per building slot (1 by default, 2 at VIP 4+).
-- Schema: SPEC.md §22.1
CREATE TABLE building_queue (
    id            INT PRIMARY KEY AUTO_INCREMENT,
    city_id       INT NOT NULL,
    building_code VARCHAR(30) NOT NULL,
    level_to      TINYINT NOT NULL,
    started_at    DATETIME NOT NULL,
    finishes_at   DATETIME NOT NULL,
    is_processed  TINYINT(1) DEFAULT 0,
    INDEX (finishes_at, is_processed),
    FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
