CREATE TABLE IF NOT EXISTS player_march_skins (
    player_id   INT         NOT NULL,
    skin_code   VARCHAR(20) NOT NULL,
    acquired_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (player_id, skin_code),
    CONSTRAINT fk_player_march_skins_player
        FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE kingdom_profiles
    ADD COLUMN IF NOT EXISTS march_skin VARCHAR(20) NULL AFTER city_skin;

-- These values are dispatch snapshots. NULL/0 is the safe legacy fallback.
ALTER TABLE marches
    ADD COLUMN IF NOT EXISTS march_skin VARCHAR(20) NULL AFTER march_type;
ALTER TABLE marches
    ADD COLUMN IF NOT EXISTS march_speed_bonus_pct TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER march_skin;

-- A rally is drawn with its captain's dispatch skin. Every participating army
-- keeps its own bonus snapshot for the slowest-army travel calculation.
ALTER TABLE rallies
    ADD COLUMN IF NOT EXISTS march_skin VARCHAR(20) NULL AFTER leader_city_id;
ALTER TABLE rallies
    ADD COLUMN IF NOT EXISTS march_speed_bonus_pct TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER march_skin;
ALTER TABLE rally_participants
    ADD COLUMN IF NOT EXISTS march_skin VARCHAR(20) NULL AFTER city_id;
ALTER TABLE rally_participants
    ADD COLUMN IF NOT EXISTS march_speed_bonus_pct TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER march_skin;

ALTER TABLE shrine_garrisons
    ADD COLUMN IF NOT EXISTS march_skin VARCHAR(20) NULL AFTER city_id;
ALTER TABLE shrine_garrisons
    ADD COLUMN IF NOT EXISTS march_speed_bonus_pct TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER march_skin;

ALTER TABLE expedition_missions
    ADD COLUMN IF NOT EXISTS march_skin VARCHAR(20) NULL AFTER objective;
ALTER TABLE expedition_missions
    ADD COLUMN IF NOT EXISTS march_speed_bonus_pct TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER march_skin;
