-- Shared across PHP workers and sessions. No raw IPs, passwords or tokens.
CREATE TABLE IF NOT EXISTS security_rate_limits (
 bucket_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 tokens DOUBLE NOT NULL,
 updated_at DOUBLE NOT NULL,
 reported_at DOUBLE NOT NULL DEFAULT 0,
 denied_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
 expires_at DATETIME NOT NULL,
 INDEX security_rate_expiry (expires_at)
) ENGINE=InnoDB;
