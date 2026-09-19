CREATE TABLE IF NOT EXISTS dungeon_runs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 world_id INT NOT NULL,
 dungeon_code VARCHAR(48) NOT NULL,
 week_key VARCHAR(10) NOT NULL,
 difficulty ENUM('normal','hard') NOT NULL DEFAULT 'normal',
 stance ENUM('cautious','balanced','risky') NOT NULL DEFAULT 'balanced',
 status ENUM('recruiting','running','decision','completed','failed','cancelled') NOT NULL DEFAULT 'recruiting',
 leader_player_id BIGINT UNSIGNED NOT NULL,
 seed INT UNSIGNED NOT NULL,
 definition_json JSON NULL,
 choice ENUM('explore','skip') NULL,
 simulation_json JSON NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 started_at DATETIME NULL,
 decision_at DATETIME NULL,
 decision_deadline DATETIME NULL,
 finishes_at DATETIME NULL,
 completed_at DATETIME NULL,
 INDEX dungeon_world_status(world_id,status,created_at),
 INDEX dungeon_due(status,decision_at,decision_deadline,finishes_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE dungeon_runs ADD COLUMN IF NOT EXISTS definition_json JSON NULL AFTER seed;

CREATE TABLE IF NOT EXISTS dungeon_members (
 run_id BIGINT UNSIGNED NOT NULL,
 player_id BIGINT UNSIGNED NOT NULL,
 city_id INT NOT NULL,
 role ENUM('attack','defense','gather','hunter') NOT NULL,
 troops_json JSON NOT NULL,
 stats_json JSON NOT NULL,
 joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 returned_at DATETIME NULL,
 PRIMARY KEY(run_id,player_id),
 INDEX dungeon_member_player(player_id,returned_at),
 CONSTRAINT dungeon_member_run FOREIGN KEY(run_id) REFERENCES dungeon_runs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dungeon_votes (
 run_id BIGINT UNSIGNED NOT NULL,
 player_id BIGINT UNSIGNED NOT NULL,
 choice ENUM('explore','skip') NOT NULL,
 voted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(run_id,player_id),
 CONSTRAINT dungeon_vote_run FOREIGN KEY(run_id) REFERENCES dungeon_runs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dungeon_rewards (
 run_id BIGINT UNSIGNED NOT NULL,
 player_id BIGINT UNSIGNED NOT NULL,
 reward_json JSON NOT NULL,
 claimed_at DATETIME NULL,
 PRIMARY KEY(run_id,player_id),
 CONSTRAINT dungeon_reward_run FOREIGN KEY(run_id) REFERENCES dungeon_runs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
