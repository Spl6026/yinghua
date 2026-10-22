-- [D：資料表建議 C16；S：功能規格 G11／G23；E-26] 空日期是開放邊界，條款明文必填。
-- @statement
CREATE TABLE IF NOT EXISTS offers (
    id CHAR(36) CHARACTER SET ascii PRIMARY KEY,
    slug VARCHAR(160) CHARACTER SET ascii NOT NULL UNIQUE,
    legacy_id SMALLINT UNSIGNED NULL UNIQUE,
    merchant_id CHAR(36) CHARACTER SET ascii NOT NULL,
    title VARCHAR(255) NOT NULL,
    discount_text VARCHAR(255) NOT NULL,
    description MEDIUMTEXT NOT NULL DEFAULT '',
    terms MEDIUMTEXT NOT NULL,
    cover_media_id CHAR(36) CHARACTER SET ascii NULL,
    cover_url VARCHAR(2000) NOT NULL DEFAULT '',
    cover_alt VARCHAR(255) NOT NULL DEFAULT '',
    cover_source VARCHAR(255) NOT NULL DEFAULT '',
    cover_license VARCHAR(255) NOT NULL DEFAULT '',
    valid_from DATE NULL,
    valid_to DATE NULL,
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
    CONSTRAINT fk_offer_merchant FOREIGN KEY (merchant_id) REFERENCES merchants(id),
    CONSTRAINT fk_offer_cover FOREIGN KEY (cover_media_id) REFERENCES media_assets(id),
    CONSTRAINT fk_offer_creator FOREIGN KEY (created_by) REFERENCES admin_users(id),
    CONSTRAINT fk_offer_editor FOREIGN KEY (updated_by) REFERENCES admin_users(id),
    CONSTRAINT chk_offer_required CHECK (CHAR_LENGTH(TRIM(title))>0 AND CHAR_LENGTH(TRIM(discount_text))>0 AND CHAR_LENGTH(TRIM(terms))>0),
    CONSTRAINT chk_offer_period CHECK (valid_from IS NULL OR valid_to IS NULL OR valid_to>=valid_from),
    INDEX offer_public (deleted_at,status,publish_at,id)
) ENGINE=InnoDB;
-- @statement
CREATE TABLE IF NOT EXISTS offer_aliases (
    path VARCHAR(255) CHARACTER SET ascii PRIMARY KEY,
    offer_id CHAR(36) CHARACTER SET ascii NOT NULL,
    CONSTRAINT fk_offer_alias FOREIGN KEY (offer_id) REFERENCES offers(id)
) ENGINE=InnoDB;
