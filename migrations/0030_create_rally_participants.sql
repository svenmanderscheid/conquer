CREATE TABLE rally_participants (
    id         BIGINT NOT NULL AUTO_INCREMENT,
    rally_id   BIGINT NOT NULL,
    player_id  INT NOT NULL,
    city_id    BIGINT NOT NULL,
    troops_json TEXT           NOT NULL DEFAULT '{}',
    joined_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status     ENUM('pending','marching','returned','cancelled')
                               NOT NULL DEFAULT 'pending',
    PRIMARY KEY (id),
    UNIQUE KEY  uq_rally_player (rally_id, player_id),
    INDEX       idx_rp_rally    (rally_id),
    CONSTRAINT fk_rp_rally  FOREIGN KEY (rally_id)  REFERENCES rallies  (id) ON DELETE CASCADE,
    CONSTRAINT fk_rp_player FOREIGN KEY (player_id) REFERENCES players  (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
