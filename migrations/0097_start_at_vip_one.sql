-- Every account starts at VIP 1. Keep the persisted level and point total aligned
-- because gameplay sessions read vip_level while VipService derives it from points.
ALTER TABLE players
    MODIFY COLUMN vip_level SMALLINT DEFAULT 1,
    MODIFY COLUMN vip_points INT DEFAULT 200;

UPDATE players
SET vip_points = 200,
    vip_level = 1
WHERE vip_points < 200;

UPDATE players
SET vip_level = 1
WHERE vip_points >= 200
  AND vip_points < 500
  AND vip_level < 1;
