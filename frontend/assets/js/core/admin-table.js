// 後台列表共用的分頁與表格行為。各列表只描述「怎麼取資料、每列顯示什麼、有哪些操作」，
// 讀取順序、換頁、刪除後的焦點、權限與唯讀模擬的規則都在這裡統一處理。
import { element, status } from './ui.js';
import { hasPermission, handleAdminError } from './session.js';
import { confirmAction } from '../components/dialog.js';
import { createPagedList } from './paged-list.js';

/** 發布狀態的顯示文字，所有內容列表用同一套用語。 */
export function publicationLabel(item, now = Date.now()) {
  if (item.status === 'draft') return '草稿';
  if (item.status === 'archived') return '已下架';
  return Date.parse(item.publishAt) > now ? '排程發布' : '已發布';
}

/**
 * 內容列表（景點、電影、消息、優惠、推薦行程）。
 *
 * 權限規則：沒有該權限的操作不顯示；唯讀模擬時仍顯示「編輯」連結與新增按鈕（表單會以唯讀開啟），
 * 「刪除」與「重新發布」按鈕停用，與表單頁的唯讀規則一致。
 */
export function adminTable({
  identity,
  module,
  ids,
  fetchPage,
  cells,
  colspan,
  noun,
  editHref,
  itemName,
  deleteConfirm,
  remove,
  retry,
  filters = [],
  search,
  onRendered = () => {},
}) {
  const $ = id => document.querySelector('#' + id);
  const rows = $(ids.rows),
    message = $(ids.status),
    pageLabel = $(ids.page),
    previous = $(ids.previous),
    next = $(ids.next);
  const can = action => hasPermission(identity, module, action);
  const writable = action => can(action) && !identity.preview?.active;
  const addButton = ids.add && $(ids.add);
  if (addButton) addButton.hidden = !can('create');
  const reload = ids.reload && $(ids.reload);
  const retryButton = retry && $(ids.retry);
  if (retryButton) retryButton.hidden = !can('edit');

  const list = createPagedList({
    fetchPage,
    onError: error => handleAdminError(error, message),
    onBusy: controls,
    render(result, state) {
      rows.replaceChildren(...result.items.map(row));
      if (!result.items.length) {
        const empty = element('tr');
        empty.append(element('td', { colspan: String(colspan) }, `目前沒有符合條件的${noun}。`));
        rows.append(empty);
      }
      pageLabel.textContent = `第 ${state.page} 頁，共 ${state.total} 筆`;
      status(message, `已載入 ${result.items.length} 筆${noun}。`);
      onRendered(result, state);
    },
  });

  function controls() {
    const { busy } = list.state;
    previous.disabled = busy || !list.hasPrevious();
    next.disabled = busy || !list.hasNext();
    if (reload) reload.disabled = busy;
    if (retryButton) retryButton.disabled = busy || !writable('edit');
  }

  function row(item) {
    const tr = element('tr');
    for (const value of cells(item)) {
      const cell = element('td');
      cell.append(value instanceof Object ? value : String(value ?? ''));
      tr.append(cell);
    }
    const actions = element('td');
    const name = itemName(item);
    if (can('edit'))
      actions.append(
        element(
          'a',
          { className: 'table-actions', href: editHref(item), 'aria-label': '編輯' + name },
          '編輯'
        )
      );
    if (can('delete')) {
      const button = element(
        'button',
        { type: 'button', className: 'table-actions', 'aria-label': '刪除' + name },
        '刪除'
      );
      button.disabled = !writable('delete');
      button.addEventListener('click', () => void removeItem(item, button));
      if (actions.childNodes.length) actions.append(document.createTextNode('　'));
      actions.append(button);
    }
    if (!actions.childNodes.length) actions.textContent = '唯讀';
    tr.append(actions);
    return tr;
  }

  async function removeItem(item, button) {
    const [title, text] = deleteConfirm(item);
    if (list.state.busy || !writable('delete') || !(await confirmAction(title, text, button))) return;
    list.state.busy = true;
    controls();
    button.disabled = true;
    try {
      const result = await remove(item);
      await list.load();
      status(message, result.publication.message, result.publication.status !== 'succeeded');
      // 被刪除的那一列已不在畫面上，把焦點交給仍存在的結果訊息。
      message.focus();
    } catch (error) {
      handleAdminError(error, message);
      button.disabled = !writable('delete');
    } finally {
      list.state.busy = false;
      controls();
    }
  }

  let timer;
  search?.addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(() => void list.reset(), 250);
  });
  for (const node of filters.filter(Boolean)) node.addEventListener('change', () => void list.reset());
  previous.addEventListener('click', () => void list.goTo(list.state.page - 1));
  next.addEventListener('click', () => void list.goTo(list.state.page + 1));
  reload?.addEventListener('click', () => void list.load());
  retryButton?.addEventListener('click', async () => {
    if (list.state.busy || !writable('edit')) return;
    list.state.busy = true;
    controls();
    status(message, '正在更新公開頁面…');
    try {
      status(message, (await retry()).message);
    } catch (error) {
      handleAdminError(error, message);
    } finally {
      list.state.busy = false;
      controls();
    }
  });
  status(message, '載入中…');
  return list;
}
