-- Independent minigame links never create or change OAuth login identities.
CREATE TABLE IF NOT EXISTS discord_game_links (
    player_id INT NOT NULL PRIMARY KEY,
    discord_id VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    world_id INT NOT NULL,
    linked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_discord_game_user (discord_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS discord_link_codes (
    code_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    player_id INT NOT NULL,
    world_id INT NOT NULL,
    expires_at DATETIME NOT NULL,
    UNIQUE KEY uq_discord_link_player (player_id),
    KEY idx_discord_code_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS discord_expeditions (
    token CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    player_id INT NOT NULL,
    discord_id VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    guild_id VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    world_id INT NOT NULL,
    city_id INT NOT NULL,
    play_day DATE NOT NULL,
    stage TINYINT UNSIGNED NOT NULL DEFAULT 0,
    route VARCHAR(12) NOT NULL DEFAULT '',
    fortune TINYINT UNSIGNED NOT NULL,
    reward_json TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    claimed_at DATETIME NULL,
    UNIQUE KEY uq_discord_expedition_player_day (player_id, play_day),
    UNIQUE KEY uq_discord_expedition_user_day (discord_id, play_day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS discord_interaction_receipts (
    interaction_id VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    payload_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    response_json TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_discord_receipt_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
