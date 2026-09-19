CREATE TABLE IF NOT EXISTS api_operation_receipts (
 player_id INT NOT NULL,
 operation_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 world_id INT NOT NULL,
 payload_hash CHAR(64) NOT NULL,
 response_json MEDIUMTEXT NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(player_id,operation_key)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS security_activity (
 player_id INT NOT NULL, world_id INT NOT NULL, slot_start BIGINT NOT NULL,
 action_count INT NOT NULL DEFAULT 0,
 PRIMARY KEY(player_id,world_id,slot_start), INDEX activity_expiry(slot_start)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS security_activity_flags (
 player_id INT NOT NULL, world_id INT NOT NULL, flag_day DATE NOT NULL,
 reason VARCHAR(40) NOT NULL, evidence_json TEXT NOT NULL,
 first_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 last_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(player_id,world_id,flag_day,reason)
) ENGINE=InnoDB;
