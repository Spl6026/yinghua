-- [D: 資料表建議 12/13; U-16; E-25] 本階段只保存開始時間，不建立結束或場地欄位。
-- @statement
CREATE TABLE IF NOT EXISTS films (
    id CHAR(36) CHARACTER SET ascii PRIMARY KEY,
    legacy_id INT UNSIGNED NULL UNIQUE,
    slug VARCHAR(160) CHARACTER SET ascii NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    genre VARCHAR(255) NOT NULL DEFAULT '',
    synopsis MEDIUMTEXT NOT NULL DEFAULT '',
    poster_media_id CHAR(36) CHARACTER SET ascii NULL,
    poster_url VARCHAR(2000) NOT NULL DEFAULT '',
    poster_alt VARCHAR(255) NULL,
    poster_source VARCHAR(255) NULL,
    poster_license VARCHAR(255) NULL,
    ticket_url VARCHAR(2000) NOT NULL DEFAULT '',
    status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    publish_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    seo_title VARCHAR(160) NOT NULL DEFAULT '',
    seo_description VARCHAR(500) NOT NULL DEFAULT '',
    created_by CHAR(36) CHARACTER SET ascii NULL,
    updated_by CHAR(36) CHARACTER SET ascii NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    CONSTRAINT fk_film_poster FOREIGN KEY (poster_media_id) REFERENCES media_assets(id),
    CONSTRAINT fk_film_creator FOREIGN KEY (created_by) REFERENCES admin_users(id),
    CONSTRAINT fk_film_editor FOREIGN KEY (updated_by) REFERENCES admin_users(id),
    CONSTRAINT film_poster_source CHECK (poster_media_id IS NULL OR poster_url=''),
    INDEX film_public (status,deleted_at,publish_at,id),
    INDEX film_admin (deleted_at,updated_at,id)
) ENGINE=InnoDB;
-- @statement
CREATE TABLE IF NOT EXISTS film_screenings (
    id CHAR(36) CHARACTER SET ascii PRIMARY KEY,
    film_id CHAR(36) CHARACTER SET ascii NOT NULL,
    starts_at DATETIME NOT NULL,
    ticket_url VARCHAR(2000) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_screening_film FOREIGN KEY (film_id) REFERENCES films(id),
    UNIQUE KEY film_start (film_id,starts_at),
    INDEX screening_start (starts_at,film_id,id)
) ENGINE=InnoDB;
