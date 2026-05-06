-- Buildings per city. One row per building type, level tracks upgrades.
-- building_code: 'castle', 'wall', 'farm', 'sawmill', etc.
-- Schema: SPEC.md §22.1
CREATE TABLE city_buildings (
    city_id       INT NOT NULL,
    building_code VARCHAR(30) NOT NULL,
    level         TINYINT DEFAULT 1,
    PRIMARY KEY (city_id, building_code),
    FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
