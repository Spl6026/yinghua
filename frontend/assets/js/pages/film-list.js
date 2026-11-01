import { filmsApi } from '../api/films-api.js';
import { hasPermission, handleAdminError } from '../core/session.js';
import { element, status } from '../core/ui.js';
import { confirmAction } from '../components/dialog.js';

export async function initializeFilmList(identity) {
  const rows = document.querySelector('#film-rows');
  const message = document.querySelector('#film-list-status');
  const query = document.querySelector('#admin-film-search');
  const filter = document.querySelector('#film-status-filter');
  const previous = document.querySelector('#film-previous');
  const next = document.querySelector('#film-next');
  const retry = document.querySelector('#retry-publication');
  const writable = action => !identity.preview?.active && hasPermission(identity, 'films', action);
  document.querySelector('#add-film').hidden = !hasPermission(identity, 'films', 'create');
  const editable = hasPermission(identity, 'films', 'edit');
  retry.hidden = !editable;
  let page = 1, generation = 0, total = 0, busy = false, timer;
  function controls() {
    previous.disabled = busy || page <= 1;
    next.disabled = busy || page * 20 >= total;
    retry.disabled = busy || !writable('edit');
  }
  async function load() {
    const request = ++generation;
    busy = true; controls();
    rows.replaceChildren();
    status(message, '讀取中…');
    try {
      const result = await filmsApi.list({ q: query.value.trim(), status: filter.value, page, pageSize: 20 });
      if (request !== generation) return;
      total = result.total;
      document.querySelector('#film-total').textContent = `${total} 筆電影資料`;
      if (page > 1 && (page - 1) * 20 >= total) { page = Math.max(1, Math.ceil(total / 20)); await load(); return; }
      for (const item of result.items) {
        const row = element('tr');
        const state = { draft: '草稿', published: new Date(item.publishAt) > new Date() ? '排程發布' : '已發布', archived: '已下架' }[item.status];
        const first=item.screenings[0];
        const poster=element('img',{className:'admin-film-thumb',src:item.image||'/assets/image-placeholder.svg',alt:item.imageAlt||`${item.title}電影海報`,width:'92',height:'62',loading:'lazy'});
        poster.addEventListener('error',()=>{if(!poster.src.endsWith('/assets/image-placeholder.svg'))poster.src='/assets/image-placeholder.svg';});
        const imageCell=element('td');imageCell.append(poster);
        const date=first?new Intl.DateTimeFormat('zh-TW',{timeZone:'Asia/Taipei',dateStyle:'short'}).format(new Date(first.startsAt)):'尚未安排';
        const time=first?new Intl.DateTimeFormat('zh-TW',{timeZone:'Asia/Taipei',hour:'2-digit',minute:'2-digit',hourCycle:'h23'}).format(new Date(first.startsAt)):'—';
        row.append(imageCell,element('td', {}, date),element('td', {}, item.title),element('td', {}, item.genre),element('td', {}, time),element('td', {}, String(item.screenings.length)),element('td', {}, state));
        const actions = element('td');
        if (editable) actions.append(element('a', { className: 'table-actions', href: '/admin/film-edit.html?id=' + encodeURIComponent(item.id), 'aria-label': '編輯' + item.title }, '編輯'));
        if (hasPermission(identity, 'films', 'delete')) {
          const remove = element('button', { type: 'button', className: 'table-actions', 'aria-label': '刪除' + item.title }, '刪除');
          remove.disabled = !writable('delete');
          remove.addEventListener('click', async () => {
            if (busy || !await confirmAction('刪除電影', `確定刪除「${item.title}」？刪除後場次與前台詳情也會下架。`, remove)) return;
            busy = true; controls(); remove.disabled = true;
            try {
              const result = await filmsApi.delete(item.id, item.version);
              await load();
              status(message, result.publication.message, result.publication.status !== 'succeeded');
              // [S G34；E] 刪除後原按鈕不再存在，焦點返回列表結果。
              message.focus();
            } catch (error) { handleAdminError(error, message); }
            finally { busy = false; controls(); remove.disabled = !writable('delete'); }
          });
          actions.append(document.createTextNode('　'), remove);
        }
        row.append(actions); rows.append(row);
      }
      if (!result.items.length) {
        const cell = element('td', { colspan: '8' }, '目前沒有符合條件的電影。');
        const row = element('tr'); row.append(cell); rows.append(row);
      }
      document.querySelector('#film-page').textContent = `第 ${page} 頁，共 ${total} 筆`;
      status(message, `已載入 ${result.items.length} 筆電影。`);
    } catch (error) { if (request === generation) handleAdminError(error, message); }
    finally { if (request === generation) { busy = false; controls(); } }
  }
  query.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => { page = 1; void load(); }, 250); });
  filter.addEventListener('change', () => { page = 1; void load(); });
  previous.addEventListener('click', () => { page--; void load(); });
  next.addEventListener('click', () => { page++; void load(); });
  document.querySelector('#reload-films').addEventListener('click', load);
  retry.addEventListener('click', async () => {
    if (busy || !writable('edit')) return;
    busy = true; controls(); status(message, '正在更新公開頁面…');
    try { const result = await filmsApi.retry(); status(message, result.message); }
    catch (error) { handleAdminError(error, message); }
    finally { busy = false; controls(); }
  });
  await load();
}
