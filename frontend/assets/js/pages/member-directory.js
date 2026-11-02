import { api } from '../core/api-client.js';
import { element, status } from '../core/ui.js';
import { handleAdminError } from '../core/session.js';
import { formatUtcDateTime } from '../core/utc-display.js';

// 唯讀查詢；API 回應已遮罩個資，DOM 也不保存完整的個人資料。
export async function initializeMemberDirectory() {
  const message = document.querySelector('#member-status'),
    rows = document.querySelector('#member-rows');
  const query = document.querySelector('#member-query'),
    previous = document.querySelector('#member-previous'),
    next = document.querySelector('#member-next');
  let page = 1,
    total = 0,
    busy = false,
    generation = 0,
    timer;
  const dialog = document.querySelector('#member-summary');
  let trigger;
  dialog.addEventListener('close', () => trigger?.focus());
  dialog.addEventListener('keydown', event => {
    if (event.key === 'Tab') {
      event.preventDefault();
      dialog.querySelector('button').focus();
    }
  });
  function controls() {
    previous.disabled = busy || page <= 1;
    next.disabled = busy || page * 20 >= total;
  }
  async function load() {
    const request = ++generation;
    busy = true;
    controls();
    status(message, '載入中…');
    rows.setAttribute('aria-busy', 'true');
    try {
      const result = await api(
        '/api/v1/admin/members?' + new URLSearchParams({ q: query.value, page: String(page) }),
        { kind: 'admin' }
      );
      if (request !== generation) return;
      total = result.total;
      rows.replaceChildren();
      for (const member of result.items) {
        const row = element('tr');
        for (const value of [
          member.displayName,
          member.email,
          formatUtcDateTime(member.joinedAt),
          { active: '啟用', disabled: '停用' }[member.status] || member.status,
        ])
          row.append(element('td', {}, value));
        const actions = element('td'),
          view = element(
            'button',
            {
              type: 'button',
              className: 'table-actions',
              'aria-label': '查看 ' + member.displayName + ' 會員資料',
            },
            '查看'
          );
        view.addEventListener('click', () => {
          trigger = view;
          document.querySelector('#member-summary-text').textContent =
            `會員識別：${member.memberId}\n名稱：${member.displayName}\n電子郵件：${member.email}\n加入日期（台北）：${formatUtcDateTime(member.joinedAt)}\n狀態：${member.status === 'active' ? '啟用' : '停用'}`;
          dialog.showModal();
        });
        actions.append(view);
        row.append(actions);
        rows.append(row);
      }
      if (!result.items.length) {
        const row = element('tr');
        row.append(element('td', { colspan: '5' }, '沒有符合條件的會員。'));
        rows.append(row);
      }
      document.querySelector('#member-page').textContent = `第 ${page} 頁，共 ${total} 筆`;
      status(message, `已載入 ${result.items.length} 筆會員。`);
    } catch (error) {
      if (request !== generation) return;
      rows.replaceChildren();
      total = 0;
      document.querySelector('#member-page').textContent = '載入失敗';
      handleAdminError(error, message);
    } finally {
      if (request === generation) {
        busy = false;
        controls();
        rows.removeAttribute('aria-busy');
      }
    }
  }
  query.addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
      page = 1;
      void load();
    }, 250);
  });
  document.querySelector('#member-search').addEventListener('submit', event => {
    event.preventDefault();
    clearTimeout(timer);
    page = 1;
    void load();
  });
  document.querySelector('#member-retry').addEventListener('click', () => void load());
  previous.addEventListener('click', () => {
    if (!busy && page > 1) {
      page--;
      void load();
    }
  });
  next.addEventListener('click', () => {
    if (!busy && page * 20 < total) {
      page++;
      void load();
    }
  });
  query.disabled = false;
  document.querySelector('#member-submit').disabled = false;
  document.querySelector('#member-retry').disabled = false;
  await load();
}
