CREATE TABLE IF NOT EXISTS player_treasure_effects (
    player_id BIGINT UNSIGNED NOT NULL,
    treasure_code INT UNSIGNED NOT NULL,
    effect_index TINYINT UNSIGNED NOT NULL,
    parts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (player_id, treasure_code, effect_index),
    CONSTRAINT treasure_effect_parts CHECK (parts BETWEEN 0 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vorhandene, nach dem alten Stufensystem freigeschaltete Relikte behalten
-- ihren Besitz. Die ersten zehn Fragmente werden dabei wie im neuen Ablauf
-- automatisch für den ersten Teilstern ausgegeben.
-- Capture only missing first stars. Existing stars have already paid their cost.
-- Keep debit and unlock atomic so retrying this file cannot charge twice.
DROP TEMPORARY TABLE IF EXISTS conquer_legacy_relic_unlocks;
CREATE TEMPORARY TABLE conquer_legacy_relic_unlocks (
    player_id BIGINT UNSIGNED NOT NULL,
    treasure_code INT UNSIGNED NOT NULL,
    PRIMARY KEY (player_id, treasure_code)
) ENGINE=InnoDB;
START TRANSACTION;
INSERT INTO conquer_legacy_relic_unlocks (player_id,treasure_code)
SELECT t.player_id,t.treasure_code
FROM player_treasures t
LEFT JOIN player_treasure_effects e
  ON e.player_id=t.player_id AND e.treasure_code=t.treasure_code AND e.effect_index=0
WHERE t.fragments>=10 AND e.player_id IS NULL
FOR UPDATE;
UPDATE player_treasures t
JOIN conquer_legacy_relic_unlocks u ON u.player_id=t.player_id AND u.treasure_code=t.treasure_code
SET t.fragments=t.fragments-10;
INSERT INTO player_treasure_effects (player_id,treasure_code,effect_index,parts)
SELECT player_id,treasure_code,0,1 FROM conquer_legacy_relic_unlocks;
COMMIT;
DROP TEMPORARY TABLE conquer_legacy_relic_unlocks;
