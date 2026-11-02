import { adminApi } from '../api/admin-api.js';
import { element, status } from '../core/ui.js';
import { hasPermission, handleAdminError } from '../core/session.js';
import { confirmAction } from '../components/dialog.js';

export async function initializeAdmins(identity) {
  const form = document.querySelector('#search-form');
  const message = document.querySelector('#page-status');
  const tbody = document.querySelector('#admin-rows');
  const pager = document.querySelector('#pagination');
  const roles = new Map([['content-editor', '內容管理員'], ['news-editor', '映演與消息編輯'], ['viewer', '唯讀檢視者']]);
  roles.set('root', '系統管理員');
  document.querySelector('#add-admin').hidden = !hasPermission(identity, 'admins', 'create');
  const query = new URLSearchParams(location.search);
  form.elements.q.value = query.get('q') || '';
  form.elements.status.value = query.get('status') || '';
  let page = Math.max(1, Number(query.get('page')) || 1);
  let loadVersion = 0;
  async function load() {
    const version = ++loadVersion;
    const filter = { q: form.elements.q.value, status: form.elements.status.value, page, pageSize: 20 };
    status(message, '載入中…');
    try {
      const result = await adminApi.list(filter);
      if (version !== loadVersion) return;
      history.replaceState(null, '', location.pathname + '?' + new URLSearchParams({q: filter.q, status: filter.status, page: filter.page}));
      tbody.replaceChildren();
      for (const account of result.items) {
        const row = element('tr');
        for (const value of [account.displayName, account.account, account.email, roles.get(account.roleId) || '自訂權限']) row.append(element('td', {}, value));
        const state = element('td');
        state.append(element('span', { className: 'status-pill' + (account.isRoot ? ' fixed-admin-pill' : '') }, account.isRoot ? '固定保護' : account.status === 'active' ? '啟用中' : '停用'));
        row.append(state);
        const actions = element('td', { className: 'inline-actions' });
        if (account.isRoot) actions.append(element('span', { className: 'fixed-admin-note' }, '不可編輯／刪除'));
        else {
          if (hasPermission(identity, 'admins', 'edit')) actions.append(element('a', { className: 'table-actions', href: '/admin/admin-edit.html?id=' + encodeURIComponent(account.id) }, '編輯'));
          if (hasPermission(identity, 'permissions', 'view')) actions.append(element('a', { className: 'table-actions', href: '/admin/permissions.html?id=' + encodeURIComponent(account.id) }, '權限'));
          if (account.id !== identity.admin.id && hasPermission(identity, 'admins', 'delete')) {
            const button = element('button', { type: 'button', className: 'table-actions' }, '刪除');
            button.disabled = Boolean(identity.preview?.active);
            button.addEventListener('click', async () => {
              if (!await confirmAction('刪除管理員', `確認刪除 ${account.displayName}？此帳號將立即失去登入資格。`, button)) return;
              button.disabled = true;
              // [S G34；E] 被刪除列重建後，聚焦現存結果而非消失的刪除按鈕。
              try { await adminApi.remove(account.id, account.version); await load(); status(message, `已刪除管理員 ${account.displayName}。${message.textContent}`); message.focus(); }
              catch (error) { handleAdminError(error, message); button.disabled = false; }
            });
            actions.append(button);
          }
        }
        row.append(actions); tbody.append(row);
      }
      const totalPages = Math.max(1, Math.ceil(result.total / result.pageSize));
      if (page > totalPages) { page = totalPages; return load(); }
      pager.replaceChildren();
      for (const [label, offset, disabled] of [['上一頁', -1, page === 1], ['下一頁', 1, page >= totalPages]]) {
        const button = element('button', { type: 'button' }, label); button.disabled = disabled;
        button.addEventListener('click', () => { page += offset; load(); }); pager.append(button);
        if (offset === -1) pager.append(element('span', {}, `第 ${page} / ${totalPages} 頁`));
      }
      status(message, result.total ? `共 ${result.total} 筆管理員。` : '沒有符合條件的管理員。');
    } catch (error) { if (version === loadVersion) handleAdminError(error, message); }
  }
  form.addEventListener('submit', event => { event.preventDefault(); page = 1; load(); });
  await load();
}
