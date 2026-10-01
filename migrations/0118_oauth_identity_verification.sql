-- Historical links have no recorded proof of mailbox ownership. Revalidate each
-- link on its next OAuth login; never infer trust from the mere link's existence.
ALTER TABLE oauth_accounts
    ADD COLUMN IF NOT EXISTS identity_verified_at DATETIME NULL;
