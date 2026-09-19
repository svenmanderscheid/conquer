-- Fragment ownership remains account-wide. Equipment belongs to one existing world.
-- Empty slot rows are deliberate: repeating this migration cannot resurrect an
-- unequipped legacy treasure or overwrite a player's newer selection.
CREATE TABLE IF NOT EXISTS player_treasure_loadouts (
    player_id BIGINT UNSIGNED NOT NULL,
    world_id INT NOT NULL,
    slot TINYINT UNSIGNED NOT NULL,
    treasure_code INT UNSIGNED NULL,
    PRIMARY KEY (player_id, world_id, slot),
    UNIQUE KEY unique_world_treasure (player_id, world_id, treasure_code),
    CONSTRAINT treasure_slot_range CHECK (slot BETWEEN 1 AND 6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS treasure_loadout_backfill (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY
) ENGINE=InnoDB;

INSERT IGNORE INTO player_treasure_loadouts (player_id,world_id,slot,treasure_code)
SELECT c.player_id,c.world_id,s.slot,
       (SELECT MIN(t.treasure_code) FROM player_treasures t
        WHERE t.player_id=c.player_id AND t.equipped_slot=s.slot)
FROM cities c CROSS JOIN
     (SELECT 1 AS slot UNION ALL SELECT 2 UNION ALL SELECT 3
      UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6) s
WHERE NOT EXISTS (SELECT 1 FROM treasure_loadout_backfill WHERE id=1);
INSERT IGNORE INTO treasure_loadout_backfill (id) VALUES (1);
