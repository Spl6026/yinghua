-- [U-12／D：資料表建議 C10；原型 place-add/edit]
-- 未填的文字使用空字串；未知行政區不猜北區。歷史建立／修改者無可證明來源者保持 NULL。
-- @statement
ALTER TABLE attractions
    MODIFY COLUMN district VARCHAR(40) NOT NULL DEFAULT '',
    MODIFY COLUMN address VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS created_by CHAR(36) CHARACTER SET ascii NULL,
    ADD COLUMN IF NOT EXISTS updated_by CHAR(36) CHARACTER SET ascii NULL;
-- @statement
ALTER TABLE attractions
    ADD CONSTRAINT IF NOT EXISTS chk_attraction_district CHECK (district IN ('','北區','東區','香山區')),
    ADD CONSTRAINT fk_attraction_created_by FOREIGN KEY IF NOT EXISTS (created_by) REFERENCES admin_users(id),
    ADD CONSTRAINT fk_attraction_updated_by FOREIGN KEY IF NOT EXISTS (updated_by) REFERENCES admin_users(id);
