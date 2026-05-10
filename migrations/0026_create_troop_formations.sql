CREATE TABLE troop_formations (
    id         BIGINT NOT NULL AUTO_INCREMENT,
    player_id  INT NOT NULL,
    slot       TINYINT NOT NULL COMMENT '1 to 4',
    troops_json TEXT            NOT NULL DEFAULT '{}',
    updated_at DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                         ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY  uq_player_slot (player_id, slot),
    CONSTRAINT fk_tf_player FOREIGN KEY (player_id)
        REFERENCES players (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
