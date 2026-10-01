-- Additive, opt-in Luxembourg territory system. Existing worlds and armies are preserved.
ALTER TABLE rallies ADD COLUMN IF NOT EXISTS target_territory_id VARCHAR(100) NULL;
ALTER TABLE rallies ADD COLUMN IF NOT EXISTS territory_campaign_id BIGINT NULL;
ALTER TABLE rallies ADD COLUMN IF NOT EXISTS territory_army_snapshot LONGTEXT NULL;
ALTER TABLE rally_participants ADD COLUMN IF NOT EXISTS territory_army_snapshot LONGTEXT NULL;

CREATE TABLE IF NOT EXISTS territory_profiles (
 world_id INT NOT NULL PRIMARY KEY, continent_id VARCHAR(80) NOT NULL,
 version INT NOT NULL, rules_json LONGTEXT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS territory_targets (
 world_id INT NOT NULL, id VARCHAR(100) NOT NULL, continent_id VARCHAR(80) NOT NULL,
 geometry_version VARCHAR(80) NOT NULL, kind VARCHAR(12) NOT NULL, commune_id VARCHAR(80) NULL,
 canton_id VARCHAR(80) NULL, name VARCHAR(150) NOT NULL, x SMALLINT NOT NULL, y SMALLINT NOT NULL,
 footprint TINYINT NOT NULL, benefit_type VARCHAR(20) NOT NULL, owner_alliance_id INT NULL,
 owned_since DATETIME NULL, last_income_at DATETIME NULL, income_remainder INT NOT NULL DEFAULT 0,
 ownership_seq INT NOT NULL DEFAULT 0, fortification INT NOT NULL DEFAULT 0,
 PRIMARY KEY(world_id,id), KEY owner(world_id,owner_alliance_id,kind), KEY canton(world_id,canton_id,kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS territory_operations (
 world_id INT NOT NULL, player_id INT NOT NULL, request_id VARCHAR(80) NOT NULL,
 payload_hash CHAR(64) NOT NULL, result_json LONGTEXT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(world_id,player_id,request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS territory_campaigns (
 id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY, world_id INT NOT NULL, continent_id VARCHAR(80) NOT NULL,
 target_id VARCHAR(100) NOT NULL, alliance_id INT NOT NULL, rally_id BIGINT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'gathering', rules_json LONGTEXT NOT NULL,
 eligibility_json TEXT NOT NULL, slot_reserved TINYINT NOT NULL DEFAULT 0,
 objective VARCHAR(20) NULL, crown_cycle_id BIGINT NULL, result_json LONGTEXT NULL,
 created_at DATETIME NOT NULL, resolved_at DATETIME NULL,
 UNIQUE KEY rally(rally_id), KEY due(world_id,status), KEY capacity(world_id,alliance_id,slot_reserved)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS territory_garrisons (
 id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY, world_id INT NOT NULL, continent_id VARCHAR(80) NOT NULL,
 target_id VARCHAR(100) NOT NULL, alliance_id INT NOT NULL, player_id INT NOT NULL, city_id BIGINT NOT NULL,
 troops_json TEXT NOT NULL, army_snapshot LONGTEXT NULL, status VARCHAR(20) NOT NULL, departure_at DATETIME NOT NULL,
 arrival_at DATETIME NOT NULL, return_at DATETIME NULL, travel_seconds INT NOT NULL,
 KEY due(world_id,status,arrival_at,return_at), KEY owner(world_id,player_id,status), KEY target(world_id,target_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS territory_history (
 id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY, world_id INT NOT NULL, continent_id VARCHAR(80) NOT NULL,
 target_id VARCHAR(100) NOT NULL, event_key VARCHAR(150) NOT NULL, old_alliance_id INT NULL,
 new_alliance_id INT NULL, campaign_id BIGINT NULL, occurred_at DATETIME NOT NULL, detail_json TEXT NOT NULL,
 UNIQUE KEY event(world_id,event_key), KEY target(world_id,target_id,occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS territory_income_ledger (
 id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY, world_id INT NOT NULL, continent_id VARCHAR(80) NOT NULL,
 target_id VARCHAR(100) NOT NULL, ownership_seq INT NOT NULL, alliance_id INT NOT NULL,
 period_start DATETIME NOT NULL, period_end DATETIME NOT NULL, resource VARCHAR(12) NOT NULL, amount BIGINT NOT NULL,
 UNIQUE KEY interval_receipt(world_id,target_id,ownership_seq,period_start,period_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS territory_rewards (
 id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY, world_id INT NOT NULL, continent_id VARCHAR(80) NOT NULL,
 player_id INT NOT NULL, alliance_id INT NOT NULL, target_id VARCHAR(100) NOT NULL,
 event_key VARCHAR(150) NOT NULL, reward_json TEXT NOT NULL, created_at DATETIME NOT NULL,
 claimed_at DATETIME NULL, UNIQUE KEY earned(world_id,player_id,event_key), KEY claims(world_id,player_id,claimed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS territory_goals (
 world_id INT NOT NULL, alliance_id INT NOT NULL, target_id VARCHAR(100) NOT NULL,
 set_by INT NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(world_id,alliance_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS territory_support (
 id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY, world_id INT NOT NULL, alliance_id INT NOT NULL,
 player_id INT NOT NULL, target_id VARCHAR(100) NOT NULL, kind VARCHAR(20) NOT NULL,
 day_key DATE NOT NULL, created_at DATETIME NOT NULL,
 UNIQUE KEY daily_player_type(world_id,player_id,kind,day_key), KEY collective(world_id,alliance_id,kind,day_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS territory_benefit_usage (
 world_id INT NOT NULL, alliance_id INT NOT NULL, benefit VARCHAR(30) NOT NULL,
 day_key DATE NOT NULL, used INT NOT NULL DEFAULT 0, PRIMARY KEY(world_id,alliance_id,benefit,day_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS territory_crown_cycles (
 id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY, world_id INT NOT NULL, continent_id VARCHAR(80) NOT NULL,
 starts_at DATETIME NOT NULL, ends_at DATETIME NOT NULL, status VARCHAR(12) NOT NULL DEFAULT 'open',
 rules_json LONGTEXT NOT NULL, winner_alliance_id INT NULL, result_json LONGTEXT NULL,
 UNIQUE KEY cycle(world_id,starts_at), KEY due(status,ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS territory_crown_control (
 cycle_id BIGINT NOT NULL, objective VARCHAR(20) NOT NULL, alliance_id INT NULL,
 controlled_since DATETIME NOT NULL, PRIMARY KEY(cycle_id,objective)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS territory_crown_scores (
 cycle_id BIGINT NOT NULL, alliance_id INT NOT NULL, control_seconds BIGINT NOT NULL DEFAULT 0,
 objectives_json TEXT NOT NULL, first_control_at DATETIME NOT NULL,
 PRIMARY KEY(cycle_id,alliance_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS territory_crown_offices (
 world_id INT NOT NULL, office VARCHAR(20) NOT NULL, cycle_id BIGINT NOT NULL,
 alliance_id INT NOT NULL, player_id INT NOT NULL, title VARCHAR(30) NOT NULL,
 appointed_at DATETIME NOT NULL, PRIMARY KEY(world_id,office)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS territory_pve_ledger (
 world_id INT NOT NULL, event_key VARCHAR(150) NOT NULL, continent_id VARCHAR(80) NOT NULL,
 canton_id VARCHAR(80) NOT NULL, alliance_id INT NOT NULL, player_id INT NOT NULL,
 reward_json TEXT NOT NULL, occurred_at DATETIME NOT NULL,
 PRIMARY KEY(world_id,event_key), KEY alliance(world_id,alliance_id,occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS territory_army_returns (
 id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY, world_id INT NOT NULL, continent_id VARCHAR(80) NOT NULL,
 rally_id BIGINT NOT NULL, participant_id BIGINT NOT NULL, player_id INT NOT NULL, city_id BIGINT NOT NULL,
 troops_json TEXT NOT NULL, turn_x SMALLINT NOT NULL, turn_y SMALLINT NOT NULL,
 turns_at DATETIME NOT NULL, returns_at DATETIME NOT NULL, status VARCHAR(12) NOT NULL DEFAULT 'returning',
 UNIQUE KEY participant_return(rally_id,participant_id), KEY due(world_id,status,returns_at), KEY player(world_id,player_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
