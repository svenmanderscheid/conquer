-- Structured chat references for securely shared battle reports.
CREATE TABLE IF NOT EXISTS battle_report_shares (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 world_id INT NOT NULL,
 battle_report_id INT NOT NULL,
 shared_by INT NOT NULL,
 channel ENUM('world','alliance','private') NOT NULL,
 message_id BIGINT UNSIGNED NOT NULL,
 alliance_id INT NULL,
 recipient_id INT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_report_share_message(channel,message_id),
 INDEX idx_report_share_world(world_id,id),
 INDEX idx_report_share_report(battle_report_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
