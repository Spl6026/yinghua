import { element, status } from '../core/ui.js';
import { publicApi } from '../api/public-api.js';
import { createPagedList } from '../core/paged-list.js';

// 在原頁面選擇地點，不導航離開，也不丟棄尚未儲存的行程編排。
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
  let trigger;
  const places = createPagedList({
    pageSize: 10,
    fetchPage: ({ page, pageSize }) => publicApi.attractions({ q: query.value, page, pageSize }),
    onBusy(state) {
      previous.disabled = state.busy || !places.hasPrevious();
      next.disabled = state.busy || !places.hasNext();
      search.disabled = state.busy;
      if (state.busy) {
        status(message, '載入可加入的地點中…');
        list.setAttribute('aria-busy', 'true');
      } else list.removeAttribute('aria-busy');
    },
    render(data, state) {
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
      pageText.textContent = `第 ${state.page} 頁，共 ${state.total} 個地點`;
    },
    onError(error, state) {
      list.replaceChildren();
      state.total = 0;
      status(message, error.message + ' 可按搜尋地點重試。', true);
    },
  });
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
  search.addEventListener('click', () => void places.reset());
  query.addEventListener('keydown', event => {
    if (event.key === 'Enter') {
      event.preventDefault();
      void places.reset();
    }
  });
  previous.addEventListener('click', () => void places.goTo(places.state.page - 1));
  next.addEventListener('click', () => void places.goTo(places.state.page + 1));
  return {
    open: async button => {
      trigger = button;
      panel.hidden = false;
      button.setAttribute('aria-expanded', 'true');
      query.focus();
      await places.load();
    },
  };
}
