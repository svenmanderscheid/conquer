-- Durable, per-recipient copies: stars and deletion never affect other players.
CREATE TABLE IF NOT EXISTS mailbox_entries (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 player_id BIGINT UNSIGNED NOT NULL,
 world_id INT NOT NULL,
 source VARCHAR(24) NOT NULL,
 source_id BIGINT UNSIGNED NOT NULL,
 category VARCHAR(16) NOT NULL,
 subject VARCHAR(180) NOT NULL,
 body MEDIUMTEXT NOT NULL,
 metadata_json MEDIUMTEXT NOT NULL,
 reward_status VARCHAR(16) NOT NULL DEFAULT 'none',
 expires_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 read_at DATETIME NULL,
 starred TINYINT(1) NOT NULL DEFAULT 0,
 deleted_at DATETIME NULL,
 UNIQUE KEY recipient_source(player_id,world_id,source,source_id),
 INDEX inbox(player_id,world_id,deleted_at,category,created_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
