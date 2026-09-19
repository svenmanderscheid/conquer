CREATE TABLE IF NOT EXISTS player_name_frames (
    player_id INT NOT NULL,
    frame_code VARCHAR(32) NOT NULL,
    acquired_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (player_id, frame_code),
    CONSTRAINT fk_player_name_frames_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS player_castle_skins (
    player_id INT NOT NULL,
    skin_code VARCHAR(20) NOT NULL,
    acquired_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (player_id, skin_code),
    CONSTRAINT fk_player_castle_skins_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE kingdom_profiles
    ADD COLUMN IF NOT EXISTS name_frame VARCHAR(32) NULL AFTER avatar;

CREATE TABLE IF NOT EXISTS theme_bundle_orders (
    id CHAR(35) NOT NULL,
    player_id INT NOT NULL,
    world_id INT NOT NULL,
    city_id INT NOT NULL,
    skin_code VARCHAR(20) NOT NULL,
    step TINYINT UNSIGNED NOT NULL,
    price_cents INT UNSIGNED NOT NULL,
    currency CHAR(3) NOT NULL,
    provider VARCHAR(24) NOT NULL,
    provider_reference VARCHAR(100) NOT NULL,
    client_operation_key VARCHAR(64) NOT NULL,
    payload_hash CHAR(64) NOT NULL,
    checkout_json JSON NULL,
    status ENUM('pending','paid','fulfilled','failed','refunded') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_at DATETIME NULL,
    fulfilled_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_theme_bundle_client_operation (player_id, client_operation_key),
    UNIQUE KEY uq_theme_bundle_provider_reference (provider, provider_reference),
    INDEX idx_theme_bundle_player (player_id, created_at),
    CONSTRAINT fk_theme_bundle_order_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    CONSTRAINT fk_theme_bundle_order_world FOREIGN KEY (world_id) REFERENCES worlds(id),
    CONSTRAINT fk_theme_bundle_order_city FOREIGN KEY (city_id) REFERENCES cities(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS player_theme_bundle_purchases (
    player_id INT NOT NULL,
    skin_code VARCHAR(20) NOT NULL,
    step TINYINT UNSIGNED NOT NULL,
    order_id CHAR(35) NOT NULL,
    purchased_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (player_id, skin_code, step),
    UNIQUE KEY uq_theme_bundle_purchase_order (order_id),
    CONSTRAINT fk_theme_bundle_purchase_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    CONSTRAINT fk_theme_bundle_purchase_order FOREIGN KEY (order_id) REFERENCES theme_bundle_orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS theme_bundle_provider_events (
    provider VARCHAR(24) NOT NULL,
    event_id VARCHAR(100) NOT NULL,
    order_id CHAR(35) NOT NULL,
    payload_hash CHAR(64) NOT NULL,
    processed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (provider, event_id),
    INDEX idx_theme_bundle_event_order (order_id),
    CONSTRAINT fk_theme_bundle_event_order FOREIGN KEY (order_id) REFERENCES theme_bundle_orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Before packages existed every current account could select every castle skin.
-- Preserve that effective entitlement; accounts created after this migration start with default only.
INSERT IGNORE INTO player_castle_skins (player_id, skin_code)
SELECT p.id, skins.skin_code
FROM players p
CROSS JOIN (
    SELECT 'ironkeep' skin_code UNION ALL SELECT 'rosehall' UNION ALL SELECT 'sandspire'
    UNION ALL SELECT 'tidewatch' UNION ALL SELECT 'winterhold' UNION ALL SELECT 'jadecourt'
    UNION ALL SELECT 'emberforge' UNION ALL SELECT 'ravenloft' UNION ALL SELECT 'clockwork'
    UNION ALL SELECT 'sapphire' UNION ALL SELECT 'phoenix' UNION ALL SELECT 'dragon'
    UNION ALL SELECT 'astral' UNION ALL SELECT 'leviathan' UNION ALL SELECT 'yggdrasil'
    UNION ALL SELECT 'tempest' UNION ALL SELECT 'eclipse'
) skins;
