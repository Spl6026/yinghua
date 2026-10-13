-- [U-12／D：資料表建議 C6/D6；S：功能規格 E26/G26/L26]
-- 一般管理員可使用原型允許的文字帳號並改名；固定 admin／root 仍受 CHECK 及 trigger 保護。
-- 不改首登密碼旗標、自我移除或自我降權規則。
-- @statement
ALTER TABLE admin_users
    MODIFY COLUMN account VARCHAR(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;
-- @statement
CREATE OR REPLACE TRIGGER protect_admin_update BEFORE UPDATE ON admin_users FOR EACH ROW
BEGIN
    IF OLD.id <> NEW.id OR OLD.is_root <> NEW.is_root OR (OLD.is_root=1 AND OLD.account <> NEW.account) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Fixed admin identity is immutable';
    END IF;
    IF OLD.is_root=1 AND (NOT (OLD.email <=> NEW.email) OR OLD.display_name <> NEW.display_name OR OLD.role_id <> NEW.role_id OR OLD.status <> NEW.status OR NOT (OLD.deleted_at <=> NEW.deleted_at) OR OLD.version <> NEW.version OR OLD.permissions_version <> NEW.permissions_version OR NEW.must_change_password<>0) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Root admin is protected';
    END IF;
END;
