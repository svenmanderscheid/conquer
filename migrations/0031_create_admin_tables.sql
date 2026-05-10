-- Migration 0031: Admin Panel tables
-- admin_users  — separate from game player accounts
-- admin_audit_log — immutable action log

CREATE TABLE IF NOT EXISTS admin_users (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username      VARCHAR(64)     NOT NULL,
    password_hash VARCHAR(255)    NOT NULL,
    role          ENUM('superadmin','moderator') NOT NULL DEFAULT 'moderator',
    last_login_at DATETIME        NULL DEFAULT NULL,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_users_username (username)
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_audit_log (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_id    BIGINT UNSIGNED NOT NULL,
    action      VARCHAR(128)    NOT NULL,
    target_type VARCHAR(64)     NULL DEFAULT NULL,
    target_id   BIGINT UNSIGNED NULL DEFAULT NULL,
    details     JSON            NULL DEFAULT NULL,
    ip          VARCHAR(45)     NULL DEFAULT NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_admin_id   (admin_id),
    KEY idx_audit_created_at (created_at),
    KEY idx_audit_target     (target_type, target_id)
) ENGINE=InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
