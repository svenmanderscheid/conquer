-- Migration 0050: Add monster_type column to field_monsters for rally/solo distinction
ALTER TABLE field_monsters
    ADD COLUMN monster_type ENUM('solo','rally') NOT NULL DEFAULT 'solo'
    AFTER hp_current;
