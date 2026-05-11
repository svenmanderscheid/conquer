-- Migration 0050: add defender columns + scouted outcome to battle_reports
-- Allows the scouted player to see a "you were scouted" report.

ALTER TABLE battle_reports
    ADD COLUMN defender_id   INT NULL AFTER attacker_city_id,
    ADD COLUMN defender_read TINYINT(1) NOT NULL DEFAULT 0 AFTER attacker_read,
    MODIFY COLUMN outcome ENUM('attacker_wins','defender_wins','draw','scouted') NOT NULL,
    ADD INDEX idx_defender (defender_id, created_at);
