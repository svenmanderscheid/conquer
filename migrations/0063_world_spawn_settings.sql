CREATE TABLE world_spawn_settings (
 world_id INT NOT NULL PRIMARY KEY,
 settings_json JSON NOT NULL,
 next_run_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 last_run_at DATETIME NULL,
 updated_by BIGINT UNSIGNED NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY (world_id) REFERENCES worlds(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE world_spawn_runs (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 world_id INT NOT NULL,
 trigger_source VARCHAR(20) NOT NULL,
 status VARCHAR(20) NOT NULL,
 resources_spawned INT NOT NULL DEFAULT 0,
 monsters_spawned INT NOT NULL DEFAULT 0,
 expired_removed INT NOT NULL DEFAULT 0,
 details_json JSON NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX(world_id,created_at),
 FOREIGN KEY (world_id) REFERENCES worlds(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE field_monsters ADD COLUMN expires_at DATETIME NULL;
ALTER TABLE field_monsters ADD INDEX idx_monster_expiry(world_id,expires_at);
