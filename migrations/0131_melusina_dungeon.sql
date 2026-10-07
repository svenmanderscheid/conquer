-- World-bound Melusina quest, event receipts and refundable party keys.
CREATE TABLE IF NOT EXISTS melusina_progress (
 player_id BIGINT UNSIGNED NOT NULL,
 world_id INT NOT NULL,
 accepted_at DATETIME NULL,
 pity_count INT UNSIGNED NOT NULL DEFAULT 0,
 first_clear_at DATETIME NULL,
 completed_runs INT UNSIGNED NOT NULL DEFAULT 0,
 PRIMARY KEY(player_id,world_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS melusina_drop_events (
 world_id INT NOT NULL,
 event_key VARCHAR(80) NOT NULL,
 player_id BIGINT UNSIGNED NOT NULL,
 source_type ENUM('monster','gather') NOT NULL,
 fragment_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
 occurred_at DATETIME NOT NULL,
 PRIMARY KEY(world_id,event_key),
 INDEX melusina_drop_player(player_id,world_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS melusina_operations (
 player_id BIGINT UNSIGNED NOT NULL,
 world_id INT NOT NULL,
 request_id VARCHAR(80) NOT NULL,
 action ENUM('accept','craft') NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(player_id,world_id,request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS melusina_key_reservations (
 run_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 world_id INT NOT NULL,
 player_id BIGINT UNSIGNED NOT NULL,
 status ENUM('reserved','consumed','returned') NOT NULL DEFAULT 'reserved',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 settled_at DATETIME NULL,
 INDEX melusina_key_owner(player_id,world_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
