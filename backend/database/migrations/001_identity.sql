-- [D／E-02..E-10] 每個 @statement 是獨立 DDL；不清空已有資料。
-- @statement
CREATE TABLE IF NOT EXISTS members (
    id CHAR(36) CHARACTER SET ascii PRIMARY KEY,
    email VARCHAR(254) NOT NULL UNIQUE,
    display_name VARCHAR(80) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('active','disabled') NOT NULL DEFAULT 'active',
    email_verified_at DATETIME NULL,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL
) ENGINE=InnoDB;
-- @statement
CREATE TABLE IF NOT EXISTS roles (
    id VARCHAR(40) CHARACTER SET ascii PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    description VARCHAR(255) NOT NULL,
    is_system BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
-- @statement
CREATE TABLE IF NOT EXISTS permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_key VARCHAR(40) CHARACTER SET ascii NOT NULL,
    action_key ENUM('view','create','edit','delete') NOT NULL,
    UNIQUE KEY module_action (module_key,action_key)
) ENGINE=InnoDB;
-- @statement
CREATE TABLE IF NOT EXISTS admin_users (
    id CHAR(36) CHARACTER SET ascii PRIMARY KEY,
    account VARCHAR(60) CHARACTER SET ascii NOT NULL UNIQUE,
    email VARCHAR(254) NOT NULL UNIQUE,
    display_name VARCHAR(80) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role_id VARCHAR(40) CHARACTER SET ascii NOT NULL,
    status ENUM('active','disabled') NOT NULL DEFAULT 'active',
    is_root BOOLEAN NOT NULL DEFAULT FALSE,
    must_change_password BOOLEAN NOT NULL DEFAULT TRUE,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    permissions_version INT UNSIGNED NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    FOREIGN KEY (role_id) REFERENCES roles(id),
    CONSTRAINT root_identity CHECK ((account='admin' AND is_root=1 AND role_id='root' AND status='active' AND deleted_at IS NULL AND must_change_password=0) OR (account<>'admin' AND is_root=0 AND role_id<>'root')),
    INDEX active_listing (deleted_at,status,created_at,id)
) ENGINE=InnoDB;
-- @statement
CREATE TABLE IF NOT EXISTS role_permissions (
    role_id VARCHAR(40) CHARACTER SET ascii NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    allowed BOOLEAN NOT NULL DEFAULT FALSE,
    PRIMARY KEY (role_id,permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id),
    FOREIGN KEY (permission_id) REFERENCES permissions(id)
) ENGINE=InnoDB;
-- @statement
CREATE TABLE IF NOT EXISTS admin_role_permissions (
    admin_id CHAR(36) CHARACTER SET ascii NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    allowed BOOLEAN NOT NULL DEFAULT FALSE,
    updated_by CHAR(36) CHARACTER SET ascii NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (admin_id,permission_id),
    FOREIGN KEY (admin_id) REFERENCES admin_users(id),
    FOREIGN KEY (permission_id) REFERENCES permissions(id),
    FOREIGN KEY (updated_by) REFERENCES admin_users(id)
) ENGINE=InnoDB;
-- @statement
CREATE TABLE IF NOT EXISTS sessions (
    id CHAR(36) CHARACTER SET ascii PRIMARY KEY,
    member_id CHAR(36) CHARACTER SET ascii NULL,
    admin_id CHAR(36) CHARACTER SET ascii NULL,
    token_hash CHAR(64) CHARACTER SET ascii NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    idle_expires_at DATETIME NOT NULL,
    last_seen_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    ip_hash CHAR(64) CHARACTER SET ascii NOT NULL,
    user_agent_hash CHAR(64) CHARACTER SET ascii NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT session_owner CHECK ((member_id IS NULL) <> (admin_id IS NULL)),
    FOREIGN KEY (member_id) REFERENCES members(id),
    FOREIGN KEY (admin_id) REFERENCES admin_users(id),
    INDEX member_revocation (member_id,revoked_at),
    INDEX admin_revocation (admin_id,revoked_at),
    INDEX expiry_cleanup (expires_at)
) ENGINE=InnoDB;
-- @statement
CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_admin_id CHAR(36) CHARACTER SET ascii NOT NULL,
    action VARCHAR(40) CHARACTER SET ascii NOT NULL,
    resource_type VARCHAR(40) CHARACTER SET ascii NOT NULL,
    resource_id CHAR(36) CHARACTER SET ascii NOT NULL,
    before_json JSON NULL,
    after_json JSON NULL,
    request_id CHAR(32) CHARACTER SET ascii NOT NULL,
    ip_hash CHAR(64) CHARACTER SET ascii NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (actor_admin_id) REFERENCES admin_users(id),
    INDEX resource_history (resource_type,resource_id,created_at,id)
) ENGINE=InnoDB;
-- @statement
CREATE TABLE IF NOT EXISTS captcha_challenges (
    id CHAR(36) CHARACTER SET ascii PRIMARY KEY,
    token_hash CHAR(64) CHARACTER SET ascii NOT NULL UNIQUE,
    purpose ENUM('register') NOT NULL,
    mode ENUM('image','email') NOT NULL,
    answer_hmac CHAR(64) CHARACTER SET ascii NOT NULL,
    session_binding_hash CHAR(64) CHARACTER SET ascii NOT NULL,
    recipient_hash CHAR(64) CHARACTER SET ascii NULL,
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts INT UNSIGNED NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    revoked_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX binding_challenge (session_binding_hash,purpose,revoked_at),
    INDEX expiry_cleanup (expires_at)
) ENGINE=InnoDB;
-- @statement
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id CHAR(36) CHARACTER SET ascii PRIMARY KEY,
    member_id CHAR(36) CHARACTER SET ascii NULL,
    admin_id CHAR(36) CHARACTER SET ascii NULL,
    token_hash CHAR(64) CHARACTER SET ascii NOT NULL UNIQUE,
    purpose ENUM('member-reset','admin-reset') NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    revoked_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT reset_owner CHECK ((member_id IS NULL) <> (admin_id IS NULL)),
    FOREIGN KEY (member_id) REFERENCES members(id),
    FOREIGN KEY (admin_id) REFERENCES admin_users(id),
    INDEX member_reset (member_id,revoked_at),
    INDEX admin_reset (admin_id,revoked_at),
    INDEX expiry_cleanup (expires_at)
) ENGINE=InnoDB;
-- @statement
CREATE TABLE IF NOT EXISTS auth_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id CHAR(36) CHARACTER SET ascii NULL,
    admin_id CHAR(36) CHARACTER SET ascii NULL,
    event_type VARCHAR(40) CHARACTER SET ascii NOT NULL,
    outcome ENUM('success','failure') NOT NULL,
    reason_code VARCHAR(60) CHARACTER SET ascii NOT NULL,
    account_hash CHAR(64) CHARACTER SET ascii NOT NULL,
    ip_hash CHAR(64) CHARACTER SET ascii NOT NULL,
    user_agent_hash CHAR(64) CHARACTER SET ascii NOT NULL,
    request_id CHAR(32) CHARACTER SET ascii NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id),
    FOREIGN KEY (admin_id) REFERENCES admin_users(id),
    INDEX account_events (account_hash,created_at,id),
    INDEX time_cleanup (created_at)
) ENGINE=InnoDB;
-- @statement
CREATE TABLE IF NOT EXISTS auth_rate_limits (
    scope VARCHAR(60) CHARACTER SET ascii NOT NULL,
    bucket_key CHAR(64) CHARACTER SET ascii NOT NULL,
    window_start BIGINT UNSIGNED NOT NULL,
    attempt_count INT UNSIGNED NOT NULL DEFAULT 1,
    locked_until DATETIME NULL,
    expires_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (scope,bucket_key,window_start),
    INDEX expiry_cleanup (expires_at)
) ENGINE=InnoDB;
-- @statement
CREATE TABLE IF NOT EXISTS member_consents (
    id CHAR(36) CHARACTER SET ascii PRIMARY KEY,
    member_id CHAR(36) CHARACTER SET ascii NOT NULL,
    document_key VARCHAR(40) CHARACTER SET ascii NOT NULL,
    document_version VARCHAR(80) NOT NULL,
    accepted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    source VARCHAR(40) CHARACTER SET ascii NOT NULL,
    ip_hash CHAR(64) CHARACTER SET ascii NOT NULL,
    FOREIGN KEY (member_id) REFERENCES members(id),
    UNIQUE KEY consent_version (member_id,document_key,document_version)
) ENGINE=InnoDB;
-- @statement
CREATE TRIGGER IF NOT EXISTS protect_admin_update BEFORE UPDATE ON admin_users FOR EACH ROW
BEGIN
    IF OLD.account <> NEW.account OR OLD.id <> NEW.id OR OLD.is_root <> NEW.is_root THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Admin identity is immutable';
    END IF;
    IF OLD.is_root=1 AND (NOT (OLD.email <=> NEW.email) OR OLD.display_name <> NEW.display_name OR OLD.role_id <> NEW.role_id OR OLD.status <> NEW.status OR NOT (OLD.deleted_at <=> NEW.deleted_at) OR OLD.version <> NEW.version OR OLD.permissions_version <> NEW.permissions_version OR NEW.must_change_password<>0) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Root admin is protected';
    END IF;
END;
-- @statement
CREATE TRIGGER IF NOT EXISTS protect_admin_delete BEFORE DELETE ON admin_users FOR EACH ROW
BEGIN
    IF OLD.is_root=1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Root admin cannot be deleted';
    END IF;
END;
-- @statement
CREATE TRIGGER IF NOT EXISTS protect_system_role BEFORE DELETE ON roles FOR EACH ROW
BEGIN
    IF OLD.is_system=1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='System roles cannot be deleted';
    END IF;
END;
-- @statement
CREATE TRIGGER IF NOT EXISTS protect_root_role_update BEFORE UPDATE ON roles FOR EACH ROW
BEGIN
    IF OLD.id='root' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Root role cannot be changed';
    END IF;
END;
-- @statement
CREATE TRIGGER IF NOT EXISTS protect_root_matrix_insert BEFORE INSERT ON admin_role_permissions FOR EACH ROW
BEGIN
    IF EXISTS (SELECT 1 FROM admin_users WHERE id=NEW.admin_id AND is_root=1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Root permissions are fixed';
    END IF;
END;
-- @statement
CREATE TRIGGER IF NOT EXISTS protect_root_matrix_update BEFORE UPDATE ON admin_role_permissions FOR EACH ROW
BEGIN
    IF EXISTS (SELECT 1 FROM admin_users WHERE id=NEW.admin_id AND is_root=1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Root permissions are fixed';
    END IF;
END;
-- @statement
CREATE TRIGGER IF NOT EXISTS audit_append_only_update BEFORE UPDATE ON audit_logs FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Audit records are append-only';
-- @statement
CREATE TRIGGER IF NOT EXISTS audit_append_only_delete BEFORE DELETE ON audit_logs FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Audit records are append-only';
