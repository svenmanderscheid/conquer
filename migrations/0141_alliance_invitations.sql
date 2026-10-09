-- Invitations belong to one alliance and world; only the recipient may accept.
CREATE TABLE IF NOT EXISTS alliance_invitations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 alliance_id INT UNSIGNED NOT NULL, world_id INT NOT NULL,
 player_id INT NOT NULL, invited_by INT NOT NULL,
 status ENUM('pending','accepted','declined','revoked') NOT NULL DEFAULT 'pending',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 expires_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY alliance_invitee(alliance_id,player_id),
 INDEX invitation_inbox(player_id,world_id,status,expires_at),
 INDEX invitation_outbox(alliance_id,world_id,status,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
