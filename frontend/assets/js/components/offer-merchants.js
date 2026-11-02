import { offerMerchantsApi as api } from '../api/content-admin-api.js';
import { hasPermission, handleAdminError } from '../core/session.js';
import { element, status } from '../core/ui.js';
import { confirmAction } from './dialog.js';

export function offerMerchants(identity, form, available, setBusy, changed) {
  const select = form.elements.merchantId,
    query = document.querySelector('#merchant-query'),
    message = document.querySelector('#merchant-status');
  const previous = document.querySelector('#merchant-previous'),
    next = document.querySelector('#merchant-next');
  const records = new Map();
  let page = 1,
    total = 0,
    generation = 0,
    loading = false;
  const allowed = action => !identity.preview?.active && hasPermission(identity, 'offers', action);
  document.querySelector('#create-merchant').hidden = !allowed('create');
  document.querySelector('#update-merchant').hidden = !allowed('edit');
  const names = ['name', 'district', 'address', 'phone', 'website'];
  function fill(record) {
    for (const name of names) document.querySelector('#merchant-' + name).value = record?.[name] || '';
    document.querySelector('#merchant-profile-status').value = record?.status || 'active';
  }
  function controls() {
    previous.disabled = !available() || loading || page <= 1;
    next.disabled = !available() || loading || page * 20 >= total;
    document.querySelector('#search-merchants').disabled = !available() || loading;
  }
  function options(items) {
    const value = select.value,
      current = records.get(value);
    for (const item of items) records.set(item.id, item);
    select.replaceChildren(element('option', { value: '' }, '請選擇合作店家'));
    for (const item of current && !items.some(x => x.id === current.id) ? [current, ...items] : items)
      select.append(
        element('option', { value: item.id }, item.name + (item.status === 'inactive' ? '（已停用）' : ''))
      );
    select.value = value;
  }
  async function load() {
    const request = ++generation;
    loading = true;
    controls();
    try {
      const result = await api.list({ q: query.value, page: String(page), pageSize: '20' });
      if (request !== generation) return;
      total = result.total;
      options(result.items);
      status(message, `第 ${page} 頁，共 ${total} 個合作店家。`);
    } catch (error) {
      if (request === generation) handleAdminError(error, message);
    } finally {
      if (request === generation) {
        loading = false;
        controls();
      }
    }
  }
  select.addEventListener('change', () => {
    fill(records.get(select.value));
    changed();
  });
  document.querySelector('#search-merchants').addEventListener('click', () => {
    page = 1;
    void load();
  });
  query.addEventListener('keydown', event => {
    if (event.key === 'Enter') {
      event.preventDefault();
      page = 1;
      void load();
    }
  });
  previous.addEventListener('click', () => {
    if (!available() || loading || page <= 1) return;
    page--;
    void load();
  });
  next.addEventListener('click', () => {
    if (!available() || loading || page * 20 >= total) return;
    page++;
    void load();
  });
  // [U-24] 店家面板不是獨立 form；伺服器欄位錯誤逐欄顯示於對應輸入下方，不只顯示「請檢查輸入內容」。
  function fieldErrors(errors = {}) {
    for (const node of document.querySelectorAll('[id^="merchant-"].merchant-invalid')) {
      node.classList.remove('merchant-invalid');
      node.removeAttribute('aria-invalid');
      node.removeAttribute('aria-describedby');
      document.querySelector('#' + node.id + '-error')?.remove();
    }
    let first;
    for (const [name, text] of Object.entries(errors)) {
      const input = document.querySelector('#merchant-' + (name === 'status' ? 'profile-status' : name));
      if (!input) continue;
      const hint = element('span', { className: 'field-error', id: input.id + '-error' }, text);
      input.classList.add('merchant-invalid');
      input.setAttribute('aria-invalid', 'true');
      input.setAttribute('aria-describedby', hint.id);
      (input.closest('.admin-field') || input.parentElement).append(hint);
      first ??= input;
    }
    first?.focus();
  }
  async function save(update) {
    const action = update ? 'edit' : 'create';
    if (!available() || !allowed(action)) return;
    const current = update ? records.get(select.value) : null;
    if (update && !current) {
      status(message, '請先選擇要修正的店家。', true);
      return;
    }
    const body = Object.fromEntries(
      names.map(name => [name, document.querySelector('#merchant-' + name).value])
    );
    body.status = document.querySelector('#merchant-profile-status').value;
    if (!body.name.trim()) {
      status(message, '請填寫店家名稱。', true);
      document.querySelector('#merchant-name').focus();
      return;
    }
    if (update) {
      if (
        !(await confirmAction(
          '修正共享店家',
          `修正「${current.name}」將同步影響引用此店家的所有優惠。`,
          document.querySelector('#update-merchant')
        ))
      )
        return;
      body.version = current.version;
      body.attractionId = current.attractionId || null;
    }
    setBusy(true);
    fieldErrors();
    let failed;
    try {
      const result = await api.save(current?.id || null, body);
      records.set(result.record.id, result.record);
      options([result.record]);
      select.value = result.record.id;
      fill(result.record);
      changed();
      status(message, result.publication.message, result.publication.status !== 'succeeded');
    } catch (error) {
      handleAdminError(error, message);
      failed = error.fieldErrors;
    } finally {
      setBusy(false);
      if (failed) fieldErrors(failed);
    }
  }
  document.querySelector('#create-merchant').addEventListener('click', () => save(false));
  document.querySelector('#update-merchant').addEventListener('click', () => save(true));
  return {
    load,
    refresh: controls,
    async select(id) {
      if (!id) return;
      const result = await api.list({ merchantId: id, pageSize: '1' });
      for (const record of result.items) records.set(record.id, record);
      if (!records.has(id)) {
        status(message, '目前關聯店家不存在或已刪除，請重新選擇。', true);
        return;
      }
      select.value = id;
      if (select.value !== id) {
        select.append(element('option', { value: id }, records.get(id).name));
        select.value = id;
      }
      fill(records.get(id));
    },
  };
}
