ALTER TABLE sessions ADD COLUMN IF NOT EXISTS active_world_id INT NOT NULL DEFAULT 1;
ALTER TABLE alliance_members ADD COLUMN IF NOT EXISTS world_id INT NOT NULL DEFAULT 1;
UPDATE alliance_members m JOIN alliances a ON a.id=m.alliance_id SET m.world_id=a.world_id;
SET @world_members_ddl=IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='alliance_members' AND index_name='unique_player')>0,'ALTER TABLE alliance_members DROP INDEX unique_player','SELECT 1');
PREPARE world_members_stmt FROM @world_members_ddl;
EXECUTE world_members_stmt;
DEALLOCATE PREPARE world_members_stmt;
SET @world_members_ddl=IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='alliance_members' AND index_name='unique_player_world')=0,'ALTER TABLE alliance_members ADD UNIQUE KEY unique_player_world(player_id,world_id)','SELECT 1');
PREPARE world_members_stmt FROM @world_members_ddl;
EXECUTE world_members_stmt;
DEALLOCATE PREPARE world_members_stmt;
ALTER TABLE market_exchanges ADD COLUMN IF NOT EXISTS world_id INT NOT NULL DEFAULT 1;
ALTER TABLE kingdom_arena_challenges ADD COLUMN IF NOT EXISTS world_id INT NOT NULL DEFAULT 1;
CREATE TABLE IF NOT EXISTS world_operations (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 player_id INT NOT NULL,
 session_id INT NOT NULL,
 request_id VARCHAR(80) NOT NULL,
 payload_hash CHAR(64) NOT NULL,
 action VARCHAR(20) NOT NULL,
 world_id INT NOT NULL,
 result_json JSON NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY world_request(player_id,request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
