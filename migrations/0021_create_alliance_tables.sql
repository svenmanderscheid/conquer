-- Alliance system core tables

CREATE TABLE IF NOT EXISTS alliances (
    id           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    world_id     INT             NOT NULL DEFAULT 1,
    name         VARCHAR(50)     NOT NULL,
    tag          VARCHAR(6)      NOT NULL,
    description  TEXT,
    leader_id    INT             NOT NULL,
    member_count INT             NOT NULL DEFAULT 1,
    max_members  INT             NOT NULL DEFAULT 30,
    created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_name (world_id, name),
    UNIQUE KEY unique_tag  (world_id, tag)
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alliance_members (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    alliance_id INT UNSIGNED    NOT NULL,
    player_id   INT             NOT NULL,
    role        ENUM('leader','vice_leader','officer','veteran','member') NOT NULL DEFAULT 'member',
    joined_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_player (player_id),
    INDEX idx_alliance (alliance_id)
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alliance_messages (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    alliance_id INT UNSIGNED    NOT NULL,
    player_id   INT             NOT NULL,
    username    VARCHAR(50)     NOT NULL,
    message     VARCHAR(200)    NOT NULL,
    sent_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_alliance_time (alliance_id, sent_at)
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
