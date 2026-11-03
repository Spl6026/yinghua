import { element, status } from '../core/ui.js';
import { handleAdminError } from '../core/session.js';
import { authApi } from '../api/auth-api.js';

// 沿用原版左下角的身分預覽選單；資料與切換都由 API 提供，前端不保存假身分。
export async function initializeAdminPreview(identity) {
  const preview = identity.preview;
  if (!identity.admin.isRoot && !preview?.active) return;
  const control = document.querySelector('#admin-preview-control');
  const select = document.querySelector('#admin-preview-target');
  const message = document.querySelector('#admin-preview-status');
  const operator = preview?.operator || identity.admin;
  const current = preview?.active ? preview.targetId : '';
  select.append(element('option', { value: '' }, `${operator.displayName}（本人）`));
  control.hidden = false;
  select.disabled = true;
  const hint = document.querySelector('#admin-preview-hint');
  hint.textContent = preview?.active
    ? `真正登入：${operator.account}。選「本人」可退出模擬。`
    : '僅預覽各帳號的介面與權限。';
  let exit;
  if (preview?.active) {
    const banner = element('div', { className: 'admin-preview-banner', role: 'status' });
    banner.append(
      element(
        'span',
        {},
        preview.targetAvailable
          ? `唯讀模擬中：${identity.admin.displayName}（${identity.admin.account}）`
          : '模擬對象已停用或刪除，請退出模擬。'
      )
    );
    exit = element('button', { type: 'button', className: 'btn ghost' }, '退出模擬');
    banner.append(exit);
    document.querySelector('.admin-content').before(banner);
    document.querySelector('.admin-top-actions a[href="/admin/change-password.html"]').hidden = true;
  }
  async function switchTo(id) {
    select.disabled = true;
    if (exit) exit.disabled = true;
    status(message, '切換中…');
    try {
      await authApi('admin').preview(id);
      // 保留目前頁面以便比較權限；即使被拒絕也保留退出入口。
      location.reload();
    } catch (error) {
      select.value = current;
      handleAdminError(error, message);
      select.disabled = false;
      if (exit) exit.disabled = false;
    }
  }
  exit?.addEventListener('click', () => switchTo(''));
  select.addEventListener('change', () => switchTo(select.value));
  try {
    const { targets } = await authApi('admin').previewTargets();
    targets.forEach(account =>
      select.append(element('option', { value: account.id }, `${account.displayName}（${account.account}）`))
    );
    if (current && !targets.some(account => account.id === current))
      select.append(element('option', { value: current }, '原模擬帳號（已停用或刪除）'));
    select.value = current;
    if (!targets.length && !current) hint.textContent = '建立一般管理員後，即可選擇模擬身分。';
  } catch (error) {
    handleAdminError(error, message);
  } finally {
    select.disabled = false;
  }
}
