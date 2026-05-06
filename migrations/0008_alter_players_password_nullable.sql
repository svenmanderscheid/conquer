-- OAuth-only auth: password_hash is no longer required.
-- Existing rows with NOT NULL constraint are altered to allow NULL.
ALTER TABLE players
    MODIFY COLUMN password_hash VARCHAR(255) NULL DEFAULT NULL;
