-- Fix vip_level default: was 1, should be 0.
-- Also reset any players who have vip_level=1 but vip_points < 200 (threshold for VIP 1).
ALTER TABLE players MODIFY COLUMN vip_level SMALLINT DEFAULT 0;

UPDATE players SET vip_level = 0 WHERE vip_level = 1 AND vip_points < 200;
