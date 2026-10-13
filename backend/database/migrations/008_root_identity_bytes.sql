-- [S：功能規格 G26/L26；E-23] 固定身分採逐位元組不可變比對，避免 CI collation 忽略大小寫／尾端空白。
-- 一般帳號仍可改名；密碼與正常登入時間仍可更新。001–007 已套用內容均不變。
-- @statement
CREATE OR REPLACE TRIGGER protect_admin_update BEFORE UPDATE ON admin_users FOR EACH ROW
BEGIN
    IF BINARY OLD.id <> BINARY NEW.id OR OLD.is_root <> NEW.is_root OR (OLD.is_root=1 AND BINARY OLD.account <> BINARY NEW.account) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Fixed admin identity is immutable';
    END IF;
    IF OLD.is_root=1 AND (NOT (BINARY OLD.email <=> BINARY NEW.email) OR BINARY OLD.display_name <> BINARY NEW.display_name OR BINARY OLD.role_id <> BINARY NEW.role_id OR BINARY OLD.status <> BINARY NEW.status OR NOT (OLD.deleted_at <=> NEW.deleted_at) OR OLD.version <> NEW.version OR OLD.permissions_version <> NEW.permissions_version OR NEW.must_change_password<>0) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Root admin is protected';
    END IF;
END;
