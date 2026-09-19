-- Separate promotion queue preserves source troops and paid costs on cancellation.
CREATE TABLE IF NOT EXISTS defense_promotions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    city_id INT NOT NULL,
    source_code INT NOT NULL,
    target_code INT NOT NULL,
    count INT NOT NULL,
    cost_json TEXT NOT NULL,
    started_at DATETIME NOT NULL,
    finishes_at DATETIME NOT NULL,
    state ENUM('training','complete','cancelled') NOT NULL DEFAULT 'training',
    INDEX idx_promotion_city (city_id,state,finishes_at),
    CONSTRAINT fk_promotion_city FOREIGN KEY (city_id) REFERENCES cities(id) ON DELETE CASCADE,
    CONSTRAINT fk_promotion_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE troop_formations ADD COLUMN IF NOT EXISTS name VARCHAR(48) NOT NULL DEFAULT '';
ALTER TABLE marches MODIFY COLUMN state ENUM('marching','resolving','returning','complete','arrived') NOT NULL DEFAULT 'marching';
