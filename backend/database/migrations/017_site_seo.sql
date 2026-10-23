-- [D：資料表建議 C24–D25；E-28] SEO 文件樂觀版本與明確目標，不改既有內容 SEO。
-- @statement
CREATE TABLE IF NOT EXISTS site_settings (
    `key` VARCHAR(80) CHARACTER SET ascii PRIMARY KEY,
    value_json JSON NOT NULL,
    published BOOLEAN NOT NULL DEFAULT FALSE,
    version INT UNSIGNED NOT NULL,
    updated_by CHAR(36) CHARACTER SET ascii NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_setting_editor FOREIGN KEY (updated_by) REFERENCES admin_users(id),
    CONSTRAINT setting_version CHECK (version > 0),
    CONSTRAINT setting_published CHECK (published IN (0,1))
) ENGINE=InnoDB;
-- @statement
CREATE TABLE IF NOT EXISTS content_seo (
    id CHAR(36) CHARACTER SET ascii PRIMARY KEY,
    resource_type ENUM('page','attraction','film','news','offer') NOT NULL,
    resource_id CHAR(36) CHARACTER SET ascii NULL,
    path_pattern VARCHAR(180) CHARACTER SET ascii NULL,
    seo_title VARCHAR(160) NULL,
    seo_description VARCHAR(500) NULL,
    seo_keywords VARCHAR(1000) NULL,
    canonical_path VARCHAR(180) CHARACTER SET ascii NULL,
    robots ENUM('index,follow','noindex,follow','noindex,nofollow') NULL,
    og_image_media_id CHAR(36) CHARACTER SET ascii NULL,
    og_image_url VARCHAR(2000) NULL,
    updated_by CHAR(36) CHARACTER SET ascii NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT seo_target CHECK ((resource_type='page' AND resource_id IS NULL AND path_pattern IS NOT NULL) OR (resource_type<>'page' AND resource_id IS NOT NULL AND path_pattern IS NULL)),
    CONSTRAINT fk_seo_image FOREIGN KEY (og_image_media_id) REFERENCES media_assets(id),
    CONSTRAINT fk_seo_editor FOREIGN KEY (updated_by) REFERENCES admin_users(id),
    CONSTRAINT seo_image_source CHECK (og_image_media_id IS NULL OR og_image_url IS NULL),
    UNIQUE KEY seo_resource (resource_type,resource_id),
    UNIQUE KEY seo_path (path_pattern)
) ENGINE=InnoDB;
