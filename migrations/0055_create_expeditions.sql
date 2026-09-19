-- Cooperative encounters keep immutable troop reservations and contribution records.
CREATE TABLE IF NOT EXISTS expeditions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    world_id INT NOT NULL DEFAULT 1,
    name VARCHAR(80) NOT NULL,
    boss_code VARCHAR(32) NOT NULL DEFAULT 'ashen_lord',
    host_alliance_id INT UNSIGNED NOT NULL,
    guest_alliance_id INT UNSIGNED NULL,
    created_by INT NOT NULL,
    invitation_status ENUM('none','pending','accepted','declined') NOT NULL DEFAULT 'none',
    phase ENUM('planning','preparation','boss','victory','expired','cancelled') NOT NULL DEFAULT 'planning',
    supplies INT UNSIGNED NOT NULL DEFAULT 0,
    defenses INT UNSIGNED NOT NULL DEFAULT 0,
    pass_progress INT UNSIGNED NOT NULL DEFAULT 0,
    boss_hp INT UNSIGNED NOT NULL DEFAULT 1800,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    INDEX idx_expedition_host (host_alliance_id,phase),
    INDEX idx_expedition_guest (guest_alliance_id,phase),
    INDEX idx_expedition_expiry (phase,expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS expedition_participants (
    expedition_id BIGINT UNSIGNED NOT NULL,
    player_id INT NOT NULL,
    alliance_id INT UNSIGNED NOT NULL,
    city_id INT NOT NULL,
    contribution INT UNSIGNED NOT NULL DEFAULT 0,
    supplies INT UNSIGNED NOT NULL DEFAULT 0,
    defenses INT UNSIGNED NOT NULL DEFAULT 0,
    pass_progress INT UNSIGNED NOT NULL DEFAULT 0,
    boss_damage INT UNSIGNED NOT NULL DEFAULT 0,
    reward_claimed TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (expedition_id,player_id),
    INDEX idx_expedition_player (player_id,expedition_id),
    FOREIGN KEY (expedition_id) REFERENCES expeditions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS expedition_missions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    expedition_id BIGINT UNSIGNED NOT NULL,
    player_id INT NOT NULL,
    city_id INT NOT NULL,
    alliance_id INT UNSIGNED NOT NULL,
    objective ENUM('defenses','pass','boss') NOT NULL,
    status ENUM('marching','returning','returned') NOT NULL DEFAULT 'marching',
    troops_json JSON NOT NULL,
    potential_damage INT UNSIGNED NOT NULL,
    damage INT UNSIGNED NOT NULL DEFAULT 0,
    arrival_at DATETIME NOT NULL,
    return_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_expedition_mission (expedition_id,status),
    INDEX idx_expedition_due (status,arrival_at,return_at),
    INDEX idx_expedition_mission_player (player_id,status),
    FOREIGN KEY (expedition_id) REFERENCES expeditions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS expedition_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    expedition_id BIGINT UNSIGNED NOT NULL,
    player_id INT NULL,
    type VARCHAR(24) NOT NULL,
    message VARCHAR(300) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_expedition_log (expedition_id,id),
    FOREIGN KEY (expedition_id) REFERENCES expeditions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS expedition_rewards (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    expedition_id BIGINT UNSIGNED NOT NULL,
    player_id INT NOT NULL,
    reward_json JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_expedition_reward_once (expedition_id,player_id),
    INDEX idx_expedition_reward_cycle (player_id,created_at),
    FOREIGN KEY (expedition_id) REFERENCES expeditions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
