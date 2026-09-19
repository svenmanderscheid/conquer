CREATE TABLE IF NOT EXISTS monster_kill_receipts (
    id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    world_id             INT NOT NULL,
    field_monster_id     BIGINT NOT NULL,
    source_kind          VARCHAR(16) NOT NULL,
    source_id            BIGINT NOT NULL,
    monster_code         INT NOT NULL,
    monster_level        INT NOT NULL,
    coord_x              SMALLINT NOT NULL,
    coord_y              SMALLINT NOT NULL,
    winner_player_id     INT NOT NULL,
    winner_alliance_id   BIGINT NULL,
    reward_snapshot_json JSON NOT NULL,
    charm_id             BIGINT UNSIGNED NULL,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_monster_kill_once (world_id, field_monster_id),
    UNIQUE KEY uq_monster_source_once (world_id, source_kind, source_id),
    INDEX idx_monster_kill_winner (world_id, winner_player_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE map_charms ADD COLUMN IF NOT EXISTS source_receipt_id BIGINT UNSIGNED NULL AFTER id;
ALTER TABLE map_charms ADD COLUMN IF NOT EXISTS bonus_pct DECIMAL(6,2) NOT NULL DEFAULT 3.00 AFTER charm_code;
ALTER TABLE map_charms ADD COLUMN IF NOT EXISTS effect_duration_seconds INT UNSIGNED NOT NULL DEFAULT 1800 AFTER bonus_pct;
ALTER TABLE map_charms ADD UNIQUE KEY IF NOT EXISTS uq_map_charm_receipt (source_receipt_id);
UPDATE map_charms SET
    bonus_pct=CASE grade WHEN 'legendary' THEN 10.00 WHEN 'epic' THEN 6.00 ELSE 3.00 END,
    effect_duration_seconds=CASE grade WHEN 'legendary' THEN 14400 WHEN 'epic' THEN 7200 ELSE 1800 END
WHERE source_receipt_id IS NULL;

ALTER TABLE player_charms_active ADD COLUMN IF NOT EXISTS world_id INT NOT NULL DEFAULT 1 AFTER player_id;
ALTER TABLE player_charms_active ADD COLUMN IF NOT EXISTS source_map_charm_id BIGINT UNSIGNED NULL AFTER charm_code;
ALTER TABLE player_charms_active ADD COLUMN IF NOT EXISTS source_march_id BIGINT NULL AFTER source_map_charm_id;
ALTER TABLE player_charms_active DROP INDEX IF EXISTS one_per_category;
ALTER TABLE player_charms_active ADD UNIQUE KEY IF NOT EXISTS one_per_category_world (player_id, world_id, stat_category);
ALTER TABLE player_charms_active ADD INDEX IF NOT EXISTS idx_player_charm_world_expiry (player_id, world_id, expires_at);

ALTER TABLE marches ADD COLUMN IF NOT EXISTS encounter_snapshot_json JSON NULL AFTER target_id;
ALTER TABLE marches ADD COLUMN IF NOT EXISTS request_id VARCHAR(80) NULL AFTER encounter_snapshot_json;
ALTER TABLE marches ADD COLUMN IF NOT EXISTS request_payload_hash CHAR(64) NULL AFTER request_id;
ALTER TABLE marches ADD UNIQUE KEY IF NOT EXISTS uq_march_request (player_id, world_id, request_id);
ALTER TABLE marches ADD INDEX IF NOT EXISTS idx_charm_collect_race (world_id, march_type, target_id, state, arrival_time, id);
