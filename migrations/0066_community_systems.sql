-- Private mail, bounded alliance help, mutual treaties and delayed member shipments.
CREATE TABLE IF NOT EXISTS community_operations (
 player_id BIGINT UNSIGNED NOT NULL, request_id VARCHAR(80) NOT NULL,
 payload_hash CHAR(64) NOT NULL, result_json MEDIUMTEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(player_id,request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
CREATE TABLE IF NOT EXISTS community_mail (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, world_id INT NOT NULL,
 sender_id BIGINT UNSIGNED NOT NULL, recipient_id BIGINT UNSIGNED NOT NULL,
 subject VARCHAR(100) NOT NULL, body TEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, read_at DATETIME NULL,
 INDEX inbox(recipient_id,world_id,id), INDEX outbox(sender_id,world_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS community_help_requests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, world_id INT NOT NULL,
 alliance_id INT UNSIGNED NOT NULL, player_id BIGINT UNSIGNED NOT NULL, city_id BIGINT UNSIGNED NOT NULL,
 queue_type ENUM('building','research') NOT NULL, queue_id BIGINT UNSIGNED NOT NULL,
 initial_seconds INT UNSIGNED NOT NULL, reduced_seconds INT UNSIGNED NOT NULL DEFAULT 0,
 help_count INT UNSIGNED NOT NULL DEFAULT 0, max_helps INT UNSIGNED NOT NULL DEFAULT 30,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY job(queue_type,queue_id), INDEX requests(alliance_id,world_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS community_help_log (
 request_id BIGINT UNSIGNED NOT NULL, helper_id BIGINT UNSIGNED NOT NULL,
 seconds_removed INT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(request_id,helper_id), INDEX daily_limit(helper_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS community_treaty_proposals (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, world_id INT NOT NULL,
 alliance_id INT UNSIGNED NOT NULL, target_id INT UNSIGNED NOT NULL,
 relation ENUM('ally','nap') NOT NULL, proposed_by BIGINT UNSIGNED NOT NULL,
 status ENUM('pending','accepted','declined','cancelled','expired') NOT NULL DEFAULT 'pending',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, expires_at DATETIME NOT NULL,
 resolved_at DATETIME NULL, INDEX pending(world_id,alliance_id,target_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS community_shipments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, world_id INT NOT NULL,
 alliance_id INT UNSIGNED NOT NULL, sender_id BIGINT UNSIGNED NOT NULL, recipient_id BIGINT UNSIGNED NOT NULL,
 source_city_id BIGINT UNSIGNED NOT NULL, target_city_id BIGINT UNSIGNED NOT NULL,
 resource ENUM('food','lumber','stone','gold') NOT NULL, amount INT UNSIGNED NOT NULL,
 status ENUM('travelling','delivered','refunded') NOT NULL DEFAULT 'travelling',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, arrives_at DATETIME NOT NULL, settled_at DATETIME NULL,
 INDEX due(status,arrives_at), INDEX sender(sender_id,world_id,id), INDEX recipient(recipient_id,world_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
