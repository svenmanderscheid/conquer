-- Migration 0036: Beginner shield and activity tracking on players table
-- beginner_shield_until — 7-day protection window from account creation
-- is_hidden             — player hidden from map (inside shield or manual)
-- last_active_at        — last interaction timestamp for idle detection

ALTER TABLE players
    ADD COLUMN IF NOT EXISTS beginner_shield_until DATETIME NULL         AFTER last_vip_login,
    ADD COLUMN IF NOT EXISTS is_hidden             TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER beginner_shield_until,
    ADD COLUMN IF NOT EXISTS last_active_at        DATETIME NULL         AFTER is_hidden;

-- Backfill: grant existing players a 7-day shield from their account creation date
UPDATE players
SET beginner_shield_until = DATE_ADD(created_at, INTERVAL 7 DAY)
WHERE beginner_shield_until IS NULL;

-- Backfill: treat creation date as last activity for existing accounts
UPDATE players
SET last_active_at = created_at
WHERE last_active_at IS NULL;
