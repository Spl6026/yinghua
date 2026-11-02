import { attractionsApi } from '../api/attractions-api.js';
import { hasPermission, handleAdminError } from '../core/session.js';
import { element, status } from '../core/ui.js';
import { confirmAction } from '../components/dialog.js';

export async function initializeAttractionList(identity) {
  const rows = document.querySelector('#place-rows');
  const message = document.querySelector('#place-list-status');
  const query = document.querySelector('#admin-place-search');
  const filter = document.querySelector('#place-status-filter');
  const previous = document.querySelector('#place-previous');
  const next = document.querySelector('#place-next');
  const retry = document.querySelector('#retry-publication');
  const writable = action => !identity.preview?.active && hasPermission(identity, 'attractions', action);
  document.querySelector('#add-place').hidden = !hasPermission(identity, 'attractions', 'create');
  const editable = hasPermission(identity, 'attractions', 'edit');
  retry.hidden = !editable;
  let page = 1,
    generation = 0,
    total = 0,
    busy = false,
    timer;
  function controls() {
    previous.disabled = busy || page <= 1;
    next.disabled = busy || page * 20 >= total;
    retry.disabled = busy || !writable('edit');
  }
  async function load() {
    const request = ++generation;
    busy = true;
    controls();
    rows.replaceChildren();
    status(message, '讀取中…');
    try {
      const result = await attractionsApi.list({
        q: query.value.trim(),
        status: filter.value,
        page,
        pageSize: 20,
      });
      if (request !== generation) return;
      total = result.total;
      if (page > 1 && (page - 1) * 20 >= total) {
        page = Math.max(1, Math.ceil(total / 20));
        await load();
        return;
      }
      for (const item of result.items) {
        const row = element('tr');
        const state = {
          draft: '草稿',
          published: new Date(item.publishAt) > new Date() ? '排程發布' : '已發布',
          archived: '已下架',
        }[item.status];
        row.append(
          element('td', {}, item.name),
          element('td', {}, item.category),
          element('td', {}, item.district),
          element('td', {}, item.hours || '依店家公告'),
          element('td', {}, state)
        );
        const actions = element('td');
        if (editable)
          actions.append(
            element(
              'a',
              {
                className: 'table-actions',
                href: '/admin/place-edit.html?id=' + encodeURIComponent(item.id),
                'aria-label': '編輯' + item.name,
              },
              '編輯'
            )
          );
        if (hasPermission(identity, 'attractions', 'delete')) {
          const remove = element(
            'button',
            { type: 'button', className: 'table-actions', 'aria-label': '刪除' + item.name },
            '刪除'
          );
          remove.disabled = !writable('delete');
          remove.addEventListener('click', async () => {
            if (
              busy ||
              !(await confirmAction(
                '刪除景點',
                `確定刪除「${item.name}」？被路線引用的景點需先調整路線。`,
                remove
              ))
            )
              return;
            busy = true;
            controls();
            remove.disabled = true;
            try {
              const result = await attractionsApi.delete(item.id, item.version);
              await load();
              status(message, result.publication.message, result.publication.status !== 'succeeded');
              // [S G34；E] 被刪除的按鈕已離開 DOM，將焦點交給現有結果訊息。
              message.focus();
            } catch (error) {
              handleAdminError(error, message);
            } finally {
              busy = false;
              controls();
              remove.disabled = !writable('delete');
            }
          });
          actions.append(document.createTextNode('　'), remove);
        }
        row.append(actions);
        rows.append(row);
      }
      if (!result.items.length) {
        const cell = element('td', { colspan: '6' }, '目前沒有符合條件的景點。');
        const row = element('tr');
        row.append(cell);
        rows.append(row);
      }
      document.querySelector('#place-page').textContent = `第 ${page} 頁，共 ${total} 筆`;
      status(message, `已載入 ${result.items.length} 筆景點。`);
    } catch (error) {
      if (request === generation) handleAdminError(error, message);
    } finally {
      if (request === generation) {
        busy = false;
        controls();
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
  filter.addEventListener('change', () => {
    page = 1;
    void load();
  });
  previous.addEventListener('click', () => {
    page--;
    void load();
  });
  next.addEventListener('click', () => {
    page++;
    void load();
  });
  document.querySelector('#reload-places').addEventListener('click', load);
  retry.addEventListener('click', async () => {
    if (busy || !writable('edit')) return;
    busy = true;
    controls();
    status(message, '正在更新公開頁面…');
    try {
      const result = await attractionsApi.retry();
      status(message, result.message);
    } catch (error) {
      handleAdminError(error, message);
    } finally {
      busy = false;
      controls();
    }
  });
  await load();
}
