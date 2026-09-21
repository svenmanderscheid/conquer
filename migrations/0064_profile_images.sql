ALTER TABLE kingdom_profiles
    ADD COLUMN IF NOT EXISTS profile_image VARCHAR(80) NULL AFTER avatar;

ALTER TABLE kingdom_profiles
    ADD COLUMN IF NOT EXISTS profile_image_updated_at DATETIME NULL AFTER profile_image;
