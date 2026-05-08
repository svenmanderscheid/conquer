-- Migration 0017: March + Battle Report tables (SPEC §22)

CREATE TABLE IF NOT EXISTS marches (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    player_id       INT NOT NULL,
    world_id        INT NOT NULL DEFAULT 1,
    march_type      TINYINT NOT NULL,          -- 5 = monster
    origin_city_id  INT NOT NULL,
    target_x        SMALLINT NOT NULL,
    target_y        SMALLINT NOT NULL,
    target_type     TINYINT NULL,              -- 3 = monster
    target_id       INT NULL,                  -- field_monsters.id
    troops_json     JSON NULL,                 -- {"50100101": 1000, ...}
    departure_time  DATETIME NOT NULL,
    arrival_time    DATETIME NOT NULL,
    return_time     DATETIME NULL,
    state           ENUM('marching','resolving','returning','complete') NOT NULL DEFAULT 'marching',
    haul_json       JSON NULL,
    INDEX idx_arrival  (arrival_time, state),
    INDEX idx_return   (return_time,  state),
    INDEX idx_player   (player_id, state),
    FOREIGN KEY (player_id)       REFERENCES players(id),
    FOREIGN KEY (origin_city_id)  REFERENCES cities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS battle_reports (
    id               INT PRIMARY KEY AUTO_INCREMENT,
    world_id         INT NOT NULL DEFAULT 1,
    march_id         INT NULL,
    attacker_id      INT NOT NULL,
    attacker_city_id INT NOT NULL,
    target_type      TINYINT NOT NULL,         -- 3 = monster
    target_id        INT NULL,
    target_x         SMALLINT NOT NULL,
    target_y         SMALLINT NOT NULL,
    outcome          ENUM('attacker_wins','defender_wins','draw') NOT NULL,
    data_json        JSON NOT NULL,
    attacker_read    TINYINT(1) NOT NULL DEFAULT 0,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_attacker (attacker_id, created_at),
    INDEX idx_march    (march_id),
    FOREIGN KEY (attacker_id) REFERENCES players(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
