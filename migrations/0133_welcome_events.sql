-- A welcome campaign starts on the first authenticated visit in each world.
-- No historical login dates are fabricated when this additive feature is installed.
CREATE TABLE IF NOT EXISTS player_welcome_events (
    player_id INT NOT NULL,
    world_id INT NOT NULL,
    started_at DATETIME NOT NULL,
    last_visit_date DATE NOT NULL,
    visit_days TINYINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (player_id, world_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS player_welcome_event_claims (
    player_id INT NOT NULL,
    world_id INT NOT NULL,
    milestone_code VARCHAR(40) NOT NULL,
    reward_json JSON NOT NULL,
    claimed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (player_id, world_id, milestone_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
