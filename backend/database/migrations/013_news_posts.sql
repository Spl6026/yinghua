-- [D：資料表建議 C14；S：功能規格 G9／G22；E-26] 欄位清單不是必填清單。
-- @statement
CREATE TABLE IF NOT EXISTS news_posts (
    id CHAR(36) CHARACTER SET ascii PRIMARY KEY,
    slug VARCHAR(160) CHARACTER SET ascii NOT NULL UNIQUE,
    legacy_id SMALLINT UNSIGNED NULL UNIQUE,
    type ENUM('event','city') NOT NULL,
    title VARCHAR(255) NOT NULL,
    summary VARCHAR(500) NOT NULL DEFAULT '',
    body MEDIUMTEXT NOT NULL DEFAULT '',
    cover_media_id CHAR(36) CHARACTER SET ascii NULL,
    cover_url VARCHAR(2000) NOT NULL DEFAULT '',
    cover_alt VARCHAR(255) NOT NULL DEFAULT '',
    cover_source VARCHAR(255) NOT NULL DEFAULT '',
    cover_license VARCHAR(255) NOT NULL DEFAULT '',
    event_starts_at DATETIME NULL,
    event_ends_at DATETIME NULL,
    event_label VARCHAR(255) NOT NULL DEFAULT '',
    event_location VARCHAR(255) NOT NULL DEFAULT '',
    status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    publish_at DATETIME NOT NULL,
    seo_title VARCHAR(160) NOT NULL DEFAULT '',
    seo_description VARCHAR(500) NOT NULL DEFAULT '',
    created_by CHAR(36) CHARACTER SET ascii NOT NULL,
    updated_by CHAR(36) CHARACTER SET ascii NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    CONSTRAINT fk_news_cover FOREIGN KEY (cover_media_id) REFERENCES media_assets(id),
    CONSTRAINT fk_news_creator FOREIGN KEY (created_by) REFERENCES admin_users(id),
    CONSTRAINT fk_news_editor FOREIGN KEY (updated_by) REFERENCES admin_users(id),
    CONSTRAINT chk_news_title CHECK (CHAR_LENGTH(TRIM(title))>0),
    CONSTRAINT chk_news_period CHECK (event_starts_at IS NULL OR event_ends_at IS NULL OR event_ends_at>=event_starts_at),
    INDEX news_public (deleted_at,status,publish_at,id)
) ENGINE=InnoDB;
-- @statement
CREATE TABLE IF NOT EXISTS news_aliases (
    path VARCHAR(255) CHARACTER SET ascii PRIMARY KEY,
    news_post_id CHAR(36) CHARACTER SET ascii NOT NULL,
    CONSTRAINT fk_news_alias FOREIGN KEY (news_post_id) REFERENCES news_posts(id)
) ENGINE=InnoDB;
