CREATE TABLE rallies (
    id               BIGINT NOT NULL AUTO_INCREMENT,
    world_id         TINYINT NOT NULL DEFAULT 1,
    leader_player_id INT NOT NULL,
    leader_city_id   BIGINT NOT NULL,
    target_player_id INT NOT NULL,
    target_city_id   BIGINT NOT NULL,
    target_x         SMALLINT NOT NULL,
    target_y         SMALLINT NOT NULL,
    rally_minutes    TINYINT NOT NULL DEFAULT 5 COMMENT '5, 10, 15, 30 or 60',
    troops_json      TEXT            NOT NULL DEFAULT '{}' COMMENT 'Leader troops sent',
    message          VARCHAR(512)    NOT NULL DEFAULT '',
    status           ENUM('gathering','marching','returning','complete','cancelled')
                                     NOT NULL DEFAULT 'gathering',
    launch_at        DATETIME        NOT NULL COMMENT 'When gathering ends and march begins',
    arrival_time     DATETIME        NULL,
    return_time      DATETIME        NULL,
    created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_rallies_leader (leader_player_id),
    INDEX idx_rallies_target (target_player_id),
    INDEX idx_rallies_status (status),
    CONSTRAINT fk_rally_leader FOREIGN KEY (leader_player_id)
        REFERENCES players (id),
    CONSTRAINT fk_rally_target FOREIGN KEY (target_player_id)
        REFERENCES players (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
