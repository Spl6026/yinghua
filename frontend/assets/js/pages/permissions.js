import { adminApi } from '../api/admin-api.js';
import { hasPermission, handleAdminError } from '../core/session.js';
import { element, status, submitForm, unsavedChanges, toast } from '../core/ui.js';
import { confirmAction } from '../components/dialog.js';

const labels = { dashboard: '總覽', attractions: '景點與店家', films: '本週映演', news: '消息與活動', offers: '店家優惠', routes: '推薦行程', admins: '管理員', permissions: '權限', members: '會員', settings: '網站設定' };
const actionLabels = { view: '查看', create: '新增', edit: '編輯', delete: '刪除' };
export async function initializePermissions(identity) {
  const form = document.querySelector('#permissions-form');
  const target = document.querySelector('#target');
  const template = document.querySelector('#template');
  const catalog = await adminApi.catalog();
  const roles = (await adminApi.roles()).roles;
  catalog.targets.forEach(account => target.append(element('option', { value: account.id }, `${account.displayName}（${account.account}）` + (account.isRoot ? '（固定管理員）' : ''))));
  roles.forEach(role => template.append(element('option', { value: role.id }, role.name)));
  const queryId = new URLSearchParams(location.search).get('id');
  if (catalog.targets.some(account => account.id === queryId)) target.value = queryId;
  let matrix;
  let loadVersion = 0;
  let dirty = false;
  let loading = false;
  let saving = false;
  let canEdit = false;
  unsavedChanges(() => dirty);
  const inputs = new Map();
  const tbody = document.querySelector('#permission-rows');
  for (const module of [...new Set(catalog.permissions.map(right => right.module))]) {
    const row = element('tr'); row.append(element('th', { scope: 'row' }, labels[module] || module));
    for (const action of ['view', 'create', 'edit', 'delete']) {
      const cell = element('td');
      const label = element('label', { className: 'permission-check' });
      const input = element('input', { type: 'checkbox', 'aria-label': `${labels[module] || module}：${actionLabels[action]}` });
      inputs.set(module + ':' + action, input); input.addEventListener('change', () => { dirty = true; });
      label.append(input); cell.append(label); row.append(cell);
    }
    tbody.append(row);
  }
  function populate(rights) { rights.forEach(right => { inputs.get(right.module + ':' + right.action).checked = right.allowed; }); }
  function updateControls() {
    const readOnly = loading || saving || !canEdit;
    inputs.forEach(input => { input.disabled = readOnly; });
    form.querySelector('[type="submit"]').disabled = readOnly;
    document.querySelector('#apply-template').disabled = readOnly;
  }
  async function load() {
    const version = ++loadVersion;
    const id = target.value;
    loading = true;
    canEdit = false;
    updateControls();
    try {
      const loaded = await adminApi.matrix(id);
      if (version !== loadVersion) return false;
      matrix = loaded; populate(matrix.permissions); dirty = false;
      history.replaceState(null, '', location.pathname + '?id=' + encodeURIComponent(id));
      canEdit = !matrix.isRoot && hasPermission(identity, 'permissions', 'edit');
      const name = catalog.targets.find(account => account.id === id).displayName;
      document.querySelector('#permission-title').textContent = name + ' 的功能權限';
      document.querySelector('#permission-summary').textContent = matrix.isRoot ? '系統管理員｜完整權限' : '自訂權限';
      document.querySelector('#root-note').textContent = matrix.isRoot ? '固定管理員具有完整權限，無法變更。' : '儲存後於下一個請求生效。';
      status(form.querySelector('.form-status'), '權限已載入。');
      return true;
    } catch (error) { if (version === loadVersion) handleAdminError(error, form.querySelector('.form-status')); return false; }
    finally { if (version === loadVersion) { loading = false; updateControls(); } }
  }
  target.addEventListener('change', async () => {
    if (dirty && !await confirmAction('放棄未儲存的權限', '切換管理員將放棄目前的修改。', target)) { target.value = matrix.adminId; return; }
    await load();
  });
  document.querySelector('#apply-template').addEventListener('click', () => { populate(roles.find(role => role.id === template.value).permissions); dirty = true; });
  document.querySelector('#reload-matrix').addEventListener('click', async () => {
    if (!dirty || await confirmAction('重新載入', '重新載入將放棄未儲存的修改。')) await load();
  });
  form.addEventListener('submit', event => {
    event.preventDefault();
    if (loading || saving || !canEdit) return;
    submitForm(form, async () => {
      // [E-14] 保存使用提交時的對象與版本；切換後不覆蓋另一人的矩陣。
      const destination = matrix.adminId;
      const version = matrix.version;
      const generation = loadVersion;
      const name = target.selectedOptions[0].textContent;
      const permissions = catalog.permissions.map(right => ({ ...right, allowed: inputs.get(right.module + ':' + right.action).checked }));
      saving = true;
      updateControls();
      try {
        await adminApi.saveMatrix(destination, { version, permissions });
        if (generation !== loadVersion) { toast(name + ' 的權限已儲存。'); return; }
        if (await load()) status(form.querySelector('.form-status'), '權限已儲存。');
        else status(form.querySelector('.form-status'), '權限已儲存，但無法重新載入最新資料；請按重新載入確認。', true);
      } finally { saving = false; updateControls(); }
    }, { canSubmit: () => !loading && !saving && canEdit, onError: error => handleAdminError(error, form.querySelector('.form-status')) });
  });
  await load();
}
