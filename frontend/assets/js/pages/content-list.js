import { contentAdminApi } from '../api/content-admin-api.js';
import { hasPermission, handleAdminError } from '../core/session.js';
import { element, status } from '../core/ui.js';
import { confirmAction } from '../components/dialog.js';

export async function initializeContentList(identity, module) {
  const service = contentAdminApi(module),
    singular = module === 'news' ? 'news' : 'offer';
  const message = document.querySelector('#content-list-status'),
    rows = document.querySelector('#content-rows');
  const query = document.querySelector('#content-query'),
    filter = document.querySelector('#content-filter-status');
  const type = document.querySelector('#content-filter-type'),
    active = document.querySelector('#content-filter-active');
  const previous = document.querySelector('#content-previous'),
    next = document.querySelector('#content-next');
  let page = 1,
    total = 0,
    busy = false,
    generation = 0,
    timer;
  const allowed = action => !identity.preview?.active && hasPermission(identity, module, action);
  document.querySelector('#new-content').hidden = !allowed('create');
  function controls() {
    previous.disabled = busy || page <= 1;
    next.disabled = busy || page * 20 >= total;
    document.querySelector('#reload-content').disabled = busy;
  }
  async function load() {
    const request = ++generation;
    busy = true;
    controls();
    status(message, '載入中…');
    try {
      const result = await service.list({
        q: query.value,
        status: filter.value,
        type: type?.value || '',
        active: active?.value || '',
        page: String(page),
      });
      if (request !== generation) return;
      total = result.total;
      rows.replaceChildren();
      for (const item of result.items) {
        const row = element('tr');
        let period =
          item.status === 'draft'
            ? '草稿'
            : item.status === 'archived'
              ? '下架'
              : Date.parse(item.publishAt) > Date.now()
                ? '排程中'
                : '已發布';
        if (module === 'offers') {
          const today = new Intl.DateTimeFormat('sv-SE', { timeZone: 'Asia/Taipei' }).format(new Date());
          if (item.status === 'published' && Date.parse(item.publishAt) <= Date.now())
            period +=
              item.validTo && item.validTo < today
                ? '／已過期'
                : item.validFrom && item.validFrom > today
                  ? '／尚未開始'
                  : item.merchantAvailable === false
                    ? '／店家停用'
                    : '／有效優惠';
          period += '；' + (item.validFrom || '不限開始') + ' ～ ' + (item.validTo || '不限結束');
        }
        for (const text of [
          item.title,
          module === 'news' ? (item.type === 'event' ? '活動' : '城市消息') : item.merchant?.name || '',
          item.publishAt ? new Date(item.publishAt).toLocaleString('zh-TW', { timeZone: 'Asia/Taipei' }) : '',
          period,
        ])
          row.append(element('td', {}, text));
        const actions = element('td');
        if (allowed('edit'))
          actions.append(
            element(
              'a',
              {
                className: 'table-actions',
                href: `/admin/${singular}-edit.html?id=${encodeURIComponent(item.id)}`,
              },
              '編輯'
            )
          );
        if (allowed('delete')) {
          const remove = element('button', { type: 'button', className: 'table-actions' }, '刪除');
          remove.addEventListener('click', async () => {
            if (
              busy ||
              !allowed('delete') ||
              !(await confirmAction(
                '刪除內容',
                `確定刪除「${item.title}」？公開列表與相鄰連結會同步更新。`,
                remove
              ))
            )
              return;
            busy = true;
            controls();
            remove.disabled = true;
            // [S G34；E] 公開同步與列表重載完成後，焦點移到仍存在的結果訊息。
            try {
              const result = await service.delete(item.id, item.version);
              await load();
              status(message, result.publication.message, result.publication.status !== 'succeeded');
              message.focus();
            } catch (error) {
              handleAdminError(error, message);
            } finally {
              busy = false;
              controls();
              remove.disabled = !allowed('delete');
            }
          });
          actions.append(document.createTextNode('　'), remove);
        }
        if (!actions.childNodes.length) actions.textContent = '唯讀';
        row.append(actions);
        rows.append(row);
      }
      if (!result.items.length) {
        const row = element('tr');
        row.append(element('td', { colspan: '5' }, '目前沒有符合條件的內容。'));
        rows.append(row);
      }
      document.querySelector('#content-page').textContent = `第 ${page} 頁，共 ${total} 筆`;
      status(message, `已載入 ${result.items.length} 筆內容。`);
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
  for (const node of [filter, type, active].filter(Boolean))
    node.addEventListener('change', () => {
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
  document.querySelector('#reload-content').addEventListener('click', load);
  await load();
}
