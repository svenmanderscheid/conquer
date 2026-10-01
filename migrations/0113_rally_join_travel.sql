-- Joining armies must physically reach the rally leader before departure.
ALTER TABLE rally_participants
    MODIFY COLUMN status ENUM('joining','pending','marching','returned','cancelled') NOT NULL DEFAULT 'joining',
    ADD COLUMN IF NOT EXISTS arrival_time DATETIME NULL AFTER joined_at;
