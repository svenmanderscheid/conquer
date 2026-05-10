-- Migration 0043: Admin backend users and audit trail
-- admin_users      — separate credential store from player accounts
-- admin_audit_log  — every admin action logged with target and IP

CREATE TABLE IF NOT EXISTS admin_users (
    id             BIGINT UNSIGNED                   NOT NULL AUTO_INCREMENT,
    username       VARCHAR(64)                       NOT NULL,
    password_hash  VARCHAR(255)                      NOT NULL,
    role           ENUM('superadmin','moderator')    NOT NULL DEFAULT 'moderator',
    last_login_at  DATETIME                          NULL,
    created_at     DATETIME                          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME                          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_audit_log (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_id     BIGINT UNSIGNED NOT NULL,
    action       VARCHAR(128)    NOT NULL,
    target_type  VARCHAR(64)     NULL COMMENT 'player|alliance|world|...',
    target_id    BIGINT UNSIGNED NULL,
    details_json TEXT            NULL,
    ip_address   VARCHAR(45)     NULL,
    created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_admin   (admin_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
