-- 這個編號是客戶原型裡詳情頁 ?id= 用的項目編號，網站從未上線，沒有「舊」資料可言，因此改用貼近用途的名稱。
-- 欄位與自動命名的唯一索引一起改名；都加 IF EXISTS，中斷後重跑或已改名的資料庫都能安全執行。
-- @statement
ALTER TABLE attractions RENAME COLUMN IF EXISTS legacy_id TO prototype_id;
-- @statement
ALTER TABLE attractions RENAME INDEX IF EXISTS legacy_id TO prototype_id;
-- @statement
ALTER TABLE films RENAME COLUMN IF EXISTS legacy_id TO prototype_id;
-- @statement
ALTER TABLE films RENAME INDEX IF EXISTS legacy_id TO prototype_id;
-- @statement
ALTER TABLE news_posts RENAME COLUMN IF EXISTS legacy_id TO prototype_id;
-- @statement
ALTER TABLE news_posts RENAME INDEX IF EXISTS legacy_id TO prototype_id;
-- @statement
ALTER TABLE offers RENAME COLUMN IF EXISTS legacy_id TO prototype_id;
-- @statement
ALTER TABLE offers RENAME INDEX IF EXISTS legacy_id TO prototype_id;
