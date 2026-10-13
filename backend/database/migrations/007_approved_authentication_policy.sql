-- [U-13] 使用者明確同意取消首次登入強制改密碼。
-- 舊欄位暫留給既有工具相容，應用程式不再讀取；固定 root 及最後管理權保護不變。
-- @statement
ALTER TABLE admin_users ALTER COLUMN must_change_password SET DEFAULT 0;
-- @statement
UPDATE admin_users SET must_change_password=0 WHERE must_change_password<>0;
