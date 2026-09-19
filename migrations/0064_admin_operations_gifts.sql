CREATE TABLE admin_operations (
 operation_id CHAR(32) NOT NULL PRIMARY KEY,
 admin_id BIGINT UNSIGNED NOT NULL,
 action VARCHAR(64) NOT NULL,
 payload_hash CHAR(64) NOT NULL,
 result_json JSON NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX(admin_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE admin_gifts (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 operation_id CHAR(32) NOT NULL,
 player_id INT NOT NULL,
 world_id INT NOT NULL,
 title VARCHAR(100) NOT NULL,
 message VARCHAR(1000) NOT NULL DEFAULT '',
 rewards_json JSON NOT NULL,
 before_json JSON NOT NULL,
 after_json JSON NOT NULL,
 delivered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY gift_recipient(operation_id,player_id,world_id),
 INDEX(player_id,delivered_at),
 FOREIGN KEY (operation_id) REFERENCES admin_operations(operation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
