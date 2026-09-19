-- Free claims belong to the account, independently of owned chest inventory.
ALTER TABLE player_chests ADD COLUMN IF NOT EXISTS last_free_silver_at DATETIME NULL DEFAULT NULL;
ALTER TABLE player_chests ADD COLUMN IF NOT EXISTS last_free_gold_at DATETIME NULL DEFAULT NULL;
