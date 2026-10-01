-- Durable social state. Every relationship and message reference is scoped to a world.
CREATE TABLE IF NOT EXISTS community_friends (
 world_id INT NOT NULL,
 player_low BIGINT UNSIGNED NOT NULL,
 player_high BIGINT UNSIGNED NOT NULL,
 requested_by BIGINT UNSIGNED NOT NULL,
 status ENUM('pending','accepted') NOT NULL DEFAULT 'pending',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 accepted_at DATETIME NULL,
 PRIMARY KEY(world_id,player_low,player_high),
 INDEX recipient_lookup(world_id,player_high,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS community_blocks (
 world_id INT NOT NULL,
 player_id BIGINT UNSIGNED NOT NULL,
 blocked_id BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(world_id,player_id,blocked_id),
 INDEX reverse_block(world_id,blocked_id,player_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS community_preferences (
 world_id INT NOT NULL,
 player_id BIGINT UNSIGNED NOT NULL,
 private_messages ENUM('everyone','friends','alliance','nobody') NOT NULL DEFAULT 'everyone',
 world_notifications ENUM('all','mentions','off') NOT NULL DEFAULT 'all',
 alliance_notifications ENUM('all','mentions','off') NOT NULL DEFAULT 'all',
 private_notifications ENUM('all','mentions','off') NOT NULL DEFAULT 'all',
 PRIMARY KEY(world_id,player_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS community_conversations (
 world_id INT NOT NULL,
 player_id BIGINT UNSIGNED NOT NULL,
 partner_id BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(world_id,player_id,partner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS community_read_cursors (
 world_id INT NOT NULL,
 player_id BIGINT UNSIGNED NOT NULL,
 channel ENUM('world','alliance','private') NOT NULL,
 scope_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
 message_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(world_id,player_id,channel,scope_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS community_message_meta (
 world_id INT NOT NULL,
 channel ENUM('world','alliance','private') NOT NULL,
 message_id BIGINT UNSIGNED NOT NULL,
 reply_to_id BIGINT UNSIGNED NULL,
 mentions_json TEXT NOT NULL,
 PRIMARY KEY(world_id,channel,message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS community_reactions (
 world_id INT NOT NULL,
 channel ENUM('world','alliance','private') NOT NULL,
 message_id BIGINT UNSIGNED NOT NULL,
 player_id BIGINT UNSIGNED NOT NULL,
 reaction ENUM('like','heart','laugh','cheer') NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(world_id,channel,message_id,player_id,reaction)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS community_pins (
 world_id INT NOT NULL,
 alliance_id BIGINT UNSIGNED NOT NULL,
 message_id BIGINT UNSIGNED NOT NULL,
 pinned_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(world_id,alliance_id,message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS community_reports (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 world_id INT NOT NULL,
 reporter_id BIGINT UNSIGNED NOT NULL,
 player_id BIGINT UNSIGNED NOT NULL,
 channel ENUM('world','alliance','private') NULL,
 message_id BIGINT UNSIGNED NULL,
 reason ENUM('harassment','spam','cheating','inappropriate','other') NOT NULL,
 details VARCHAR(2000) NOT NULL DEFAULT '',
 snapshot_json MEDIUMTEXT NOT NULL,
 status ENUM('new','reviewing','resolved','dismissed') NOT NULL DEFAULT 'new',
 admin_note VARCHAR(2000) NOT NULL DEFAULT '',
 handled_by BIGINT UNSIGNED NULL,
 handled_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX moderation_queue(status,created_at),
 INDEX reporter_limit(reporter_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS community_chat_bans (
 world_id INT NOT NULL,
 player_id BIGINT UNSIGNED NOT NULL,
 reason VARCHAR(500) NOT NULL,
 expires_at DATETIME NOT NULL,
 created_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(world_id,player_id),
 INDEX active_bans(expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
