-- [D：資料表建議 C15／D15；E-26] 既有店家標記升級，保留 attraction 關聯及建立時間。
-- @statement
ALTER TABLE merchants
    ADD COLUMN IF NOT EXISTS id CHAR(36) CHARACTER SET ascii NULL,
    ADD COLUMN IF NOT EXISTS name VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS district VARCHAR(20) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS address VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS phone VARCHAR(80) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS website VARCHAR(2000) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS status ENUM('active','inactive') NOT NULL DEFAULT 'inactive',
    ADD COLUMN IF NOT EXISTS version INT UNSIGNED NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL;
-- @statement
UPDATE merchants m JOIN attractions a ON a.id=m.attraction_id
SET m.id=a.id,m.name=a.name,m.district=COALESCE(a.district,''),m.address=a.address,
    m.phone=a.phone,m.website=a.website_url,
    m.status=IF(a.deleted_at IS NULL AND a.status='published' AND a.publish_at<=UTC_TIMESTAMP(),'active','inactive')
WHERE m.id IS NULL;
-- @statement
ALTER TABLE merchants ADD UNIQUE INDEX IF NOT EXISTS merchant_attraction (attraction_id);
-- @statement
ALTER TABLE merchants DROP PRIMARY KEY, MODIFY id CHAR(36) CHARACTER SET ascii NOT NULL,
    MODIFY attraction_id CHAR(36) CHARACTER SET ascii NULL, ADD PRIMARY KEY (id);
