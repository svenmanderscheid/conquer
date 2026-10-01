-- Recruitment and planning belong to an alliance in one world. Player text stays plain text.
ALTER TABLE alliances ADD COLUMN IF NOT EXISTS recruitment_mode VARCHAR(16) NOT NULL DEFAULT 'open';
ALTER TABLE alliances ADD COLUMN IF NOT EXISTS recruitment_language VARCHAR(12) NOT NULL DEFAULT 'en';
ALTER TABLE alliances ADD COLUMN IF NOT EXISTS play_style VARCHAR(16) NOT NULL DEFAULT 'casual';
ALTER TABLE alliances ADD COLUMN IF NOT EXISTS activity_time VARCHAR(16) NOT NULL DEFAULT 'flexible';
ALTER TABLE alliances ADD COLUMN IF NOT EXISTS activity_timezone VARCHAR(64) NOT NULL DEFAULT 'UTC';
ALTER TABLE alliances ADD COLUMN IF NOT EXISTS minimum_power BIGINT UNSIGNED NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS alliance_applications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, alliance_id INT UNSIGNED NOT NULL,
 world_id INT NOT NULL, player_id INT NOT NULL, message VARCHAR(500) NOT NULL DEFAULT '',
 status ENUM('pending','accepted','declined','withdrawn') NOT NULL DEFAULT 'pending',
 reviewed_by INT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY alliance_applicant(alliance_id,player_id), INDEX(player_id,world_id,status), INDEX(alliance_id,status,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alliance_notices (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, alliance_id INT UNSIGNED NOT NULL, world_id INT NOT NULL,
 creator_id INT NOT NULL, kind ENUM('notice','goal') NOT NULL DEFAULT 'notice',
 title VARCHAR(100) NOT NULL, body VARCHAR(2000) NOT NULL DEFAULT '',
 pinned TINYINT NOT NULL DEFAULT 1, completed TINYINT NOT NULL DEFAULT 0, archived TINYINT NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(alliance_id,archived,pinned,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alliance_calendar_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, alliance_id INT UNSIGNED NOT NULL, world_id INT NOT NULL,
 creator_id INT NOT NULL, title VARCHAR(100) NOT NULL, description VARCHAR(2000) NOT NULL DEFAULT '',
 starts_at DATETIME NOT NULL, duration_minutes INT UNSIGNED NOT NULL DEFAULT 60,
 timezone VARCHAR(64) NOT NULL DEFAULT 'UTC', cancelled_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(alliance_id,starts_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alliance_event_rsvps (
 event_id BIGINT UNSIGNED NOT NULL, player_id INT NOT NULL, response ENUM('yes','maybe','no') NOT NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(event_id,player_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alliance_polls (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, alliance_id INT UNSIGNED NOT NULL, world_id INT NOT NULL,
 creator_id INT NOT NULL, question VARCHAR(200) NOT NULL, kind VARCHAR(12) NOT NULL DEFAULT 'choice', options_json JSON NOT NULL,
 closes_at DATETIME NOT NULL, closed_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX(alliance_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alliance_poll_votes (
 poll_id BIGINT UNSIGNED NOT NULL, player_id INT NOT NULL, choice TINYINT UNSIGNED NOT NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY(poll_id,player_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alliance_community_operations (
 player_id INT NOT NULL, request_id VARCHAR(80) NOT NULL, payload_hash CHAR(64) NOT NULL, result_json JSON NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(player_id,request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
