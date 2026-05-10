-- Add Lord Level, Action Points, Kill Count to players table
ALTER TABLE players
    ADD COLUMN lord_xp       BIGINT UNSIGNED  NOT NULL DEFAULT 0          AFTER vip_level,
    ADD COLUMN lord_level    TINYINT UNSIGNED NOT NULL DEFAULT 0          AFTER lord_xp,
    ADD COLUMN action_points SMALLINT UNSIGNED NOT NULL DEFAULT 200       AFTER lord_level,
    ADD COLUMN last_ap_regen DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER action_points,
    ADD COLUMN kill_count    INT UNSIGNED     NOT NULL DEFAULT 0          AFTER last_ap_regen;
