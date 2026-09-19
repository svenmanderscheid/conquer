-- Progress per world; legacy XP goes to the player's oldest city. Old columns
-- and masteries remain archived. INSERT IGNORE makes migration replay safe.
CREATE TABLE IF NOT EXISTS player_lord_progress (
 player_id BIGINT UNSIGNED NOT NULL, world_id INT NOT NULL,
 xp BIGINT UNSIGNED NOT NULL DEFAULT 0, revision INT UNSIGNED NOT NULL DEFAULT 0,
 last_respec_at DATETIME NULL, PRIMARY KEY (player_id,world_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS player_lord_talents (
 player_id BIGINT UNSIGNED NOT NULL, world_id INT NOT NULL,
 talent_code VARCHAR(40) NOT NULL, rank TINYINT UNSIGNED NOT NULL,
 PRIMARY KEY (player_id,world_id,talent_code),
 CONSTRAINT lord_talent_rank CHECK (rank BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS lord_xp_receipts (
 player_id BIGINT UNSIGNED NOT NULL, world_id INT NOT NULL,
 source VARCHAR(100) NOT NULL, xp INT UNSIGNED NOT NULL,
 PRIMARY KEY (player_id,world_id,source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO player_lord_progress(player_id,world_id,xp)
SELECT c.player_id,c.world_id,IF(c.id=(SELECT MIN(c2.id) FROM cities c2 WHERE c2.player_id=c.player_id),GREATEST(0,p.lord_xp),0)
FROM cities c JOIN players p ON p.id=c.player_id;
-- AP remains account-wide; remember the rate that applied during elapsed time.
CREATE TABLE IF NOT EXISTS player_ap_regeneration (
 player_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 rate DECIMAL(10,6) NOT NULL DEFAULT 1,
 fraction DECIMAL(12,9) NOT NULL DEFAULT 0
) ENGINE=InnoDB;
