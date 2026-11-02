import { api } from '../core/api-client.js';
import { element, status } from '../core/ui.js';

// [U：行程操作改善；E-37] 原頁選地點，不導航離開或丟棄尚未儲存的編排。
export function attractionPicker(host, { onChoose, isSelected, isDisabled }) {
  const panel = element('section', {
    className: 'trip-place-picker',
    'aria-labelledby': 'trip-picker-title',
    id: 'trip-place-picker',
  });
  panel.hidden = true;
  const heading = element('div', { className: 'trip-picker-heading' }),
    close = element('button', { type: 'button', className: 'button button-outline' }, '收起地點選擇');
  heading.append(element('h3', { id: 'trip-picker-title' }, '新增地點'), close);
  const field = element('div', { className: 'trip-picker-search' }),
    query = element('input', {
      id: 'trip-picker-query',
      type: 'search',
      maxlength: '80',
      autocomplete: 'off',
    }),
    search = element('button', { type: 'button', className: 'button button-dark' }, '搜尋地點');
  field.append(element('label', { for: 'trip-picker-query' }, '搜尋景點、美食或店家'), query, search);
  const message = element('p', { role: 'status' }),
    list = element('div', { className: 'trip-picker-results' }),
    pager = element('div', { className: 'trip-picker-pager' }),
    previous = element('button', { type: 'button', className: 'button button-outline' }, '上一頁地點'),
    next = element('button', { type: 'button', className: 'button button-outline' }, '下一頁地點'),
    pageText = element('span');
  pager.append(previous, pageText, next);
  panel.append(heading, field, message, list, pager);
  host.append(panel);
  let page = 1,
    total = 0,
    generation = 0,
    busy = false,
    trigger;
  function controls() {
    previous.disabled = busy || page <= 1;
    next.disabled = busy || page * 10 >= total;
    search.disabled = busy;
  }
  async function load() {
    const request = ++generation;
    busy = true;
    controls();
    status(message, '載入可加入的地點中…');
    list.setAttribute('aria-busy', 'true');
    try {
      const data = await api(
        '/api/v1/attractions?' + new URLSearchParams({ q: query.value, page: String(page), pageSize: '10' })
      );
      if (request !== generation) return;
      total = data.total;
      list.replaceChildren();
      for (const place of data.items) {
        const row = element('article', { className: 'trip-picker-place' }),
          description = element('div'),
          button = element(
            'button',
            { type: 'button', className: 'button button-outline', 'aria-label': '加入 ' + place.name },
            isSelected(place.id) ? '已在行程中' : '加入行程'
          );
        description.append(
          element('h4', {}, place.name),
          element('p', {}, [place.district, place.category].filter(Boolean).join('・'))
        );
        button.disabled = isSelected(place.id) || isDisabled();
        button.addEventListener('click', async () => {
          if (isDisabled() || isSelected(place.id)) return;
          button.disabled = true;
          try {
            await onChoose(place);
            button.textContent = isSelected(place.id) ? '已在行程中' : '加入行程';
          } finally {
            button.disabled = isSelected(place.id) || isDisabled();
          }
        });
        row.append(description, button);
        list.append(row);
      }
      status(
        message,
        data.items.length
          ? '選取地點會加入草稿；編排完成後按「儲存行程」。'
          : '找不到符合條件的地點，請更換關鍵字。'
      );
      pageText.textContent = `第 ${page} 頁，共 ${total} 個地點`;
    } catch (error) {
      if (request === generation) {
        list.replaceChildren();
        total = 0;
        status(message, error.message + ' 可按搜尋地點重試。', true);
      }
    } finally {
      if (request === generation) {
        busy = false;
        controls();
        list.removeAttribute('aria-busy');
      }
    }
  }
  function hide() {
    panel.hidden = true;
    trigger?.setAttribute('aria-expanded', 'false');
    trigger?.focus();
  }
  close.addEventListener('click', hide);
  panel.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      event.preventDefault();
      hide();
    }
  });
  search.addEventListener('click', () => {
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
    if (!busy && page > 1) {
      page--;
      void load();
    }
  });
  next.addEventListener('click', () => {
    if (!busy && page * 10 < total) {
      page++;
      void load();
    }
  });
  return {
    open: async button => {
      trigger = button;
      panel.hidden = false;
      button.setAttribute('aria-expanded', 'true');
      query.focus();
      await load();
    },
  };
}
