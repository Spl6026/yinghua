import { adminApi } from '../api/admin-api.js';
import { element, formErrors, submitForm, status, unsavedChanges } from '../core/ui.js';
import { handleAdminError, hasPermission, adminDestination } from '../core/session.js';
import { confirmAction } from '../components/dialog.js';

export async function initializeAdminForm(identity, page) {
  const form = document.querySelector('#admin-form');
  const creating = page === 'admin-add';
  if (!hasPermission(identity, 'admins', 'view')) {
    const cancel = document.querySelector('#admin-cancel');
    cancel.href = adminDestination(identity); cancel.textContent = '返回可用功能';
  }
  const id = new URLSearchParams(location.search).get('id');
  const roles = (await adminApi.roles()).roles;
  roles.forEach(role => form.elements.roleId.append(element('option', { value: role.id }, role.name)));
  let version;
  let dirty = false;
  let loadVersion = 0;
  let loading = false;
  let saving = false;
  let loaded = creating;
  const save = form.querySelector('[type="submit"]');
  // [E-14] 固定管理員會以保護說明取代表單；先保留控制項參照，避免讀取已移除節點。
  const reload = document.querySelector('#reload-admin');
  const message = form.querySelector('.form-status');
  // [E-14] 讀取失敗、重試與保存共用狀態；不能靠 finally 無條件開啟保存。
  function updateControls() {
    save.disabled = loading || saving || !loaded;
    form.querySelectorAll('input,select').forEach(input => { input.disabled = loading || saving || !loaded; });
    reload.disabled = saving;
  }
  unsavedChanges(() => dirty);
  form.addEventListener('input', () => { dirty = true; });
  async function load() {
    if (creating) return;
    const generation = ++loadVersion;
    // [E-14] 只採用最後一次讀取，避免較慢的舊回應覆蓋新版本。
    loading = true;
    loaded = false;
    updateControls();
    try {
      const { admin } = await adminApi.get(id);
      if (generation !== loadVersion) return;
      if (admin.isRoot) {
        form.replaceChildren(element('p', {}, '固定管理員的個人資料與系統身分受保護；密碼請使用更改密碼功能。'));
        return;
      }
      for (const name of ['account', 'displayName', 'email', 'roleId', 'status']) form.elements[name].value = admin[name];
      version = admin.version;
      loaded = true;
      dirty = false;
      // [E-14] 新資料已取代草稿，舊欄位錯誤也須清除。
      formErrors(form, { fieldErrors: {} });
      status(message, '資料已載入。');
    } catch (error) { if (generation === loadVersion) handleAdminError(error, message); }
    finally { if (generation === loadVersion) { loading = false; updateControls(); } }
  }
  reload.hidden = creating;
  reload.addEventListener('click', async event => {
    if (!dirty || await confirmAction('重新載入管理員資料', '重新載入將放棄尚未儲存的修改。', event.currentTarget)) await load();
  });
  form.addEventListener('submit', event => {
    event.preventDefault();
    if (saving) return;
    if (loading || !loaded) { status(message, '請先重新載入有效資料，再進行儲存。', true); return; }
    submitForm(form, async () => {
      const body = Object.fromEntries(new FormData(form));
      body.status = form.elements.status.value;
      saving = true;
      updateControls();
      let completed = false;
      try {
        if (creating) await adminApi.create(body);
        else { body.version = version; await adminApi.update(id, body); }
        dirty = false;
        completed = true;
        if (hasPermission(identity, 'admins', 'view')) location.assign('/admin/admins.html');
        else {
          status(message, '管理員已儲存。');
          document.querySelector('#admin-another').hidden = !hasPermission(identity, 'admins', 'create');
          message.focus();
        }
      } finally { saving = completed; updateControls(); }
    }, { canSubmit: () => !loading && !saving && loaded, onError: error => handleAdminError(error, message) });
  });
  await load();
  updateControls();
}
