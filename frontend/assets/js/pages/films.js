import { filmRows, screeningPeriodLabel } from '../core/film-view.js';
import { element, status } from '../core/ui.js';
import { fallbackContentImages } from '../core/content-view.js';
import { publicApi } from '../api/public-api.js';
import { createPagedList } from '../core/paged-list.js';

// 初始內容由同一發布版本的 HTML 提供；換頁失敗時不回填原型資料。
const list = document.querySelector('#cinema-list');
const snapshot = document.querySelector('#film-publication');
if (list && snapshot) {
  const notice = element('p', { role: 'status', tabindex: '-1', className: 'form-status' });
  const pager = element('div', { className: 'pagination-controls' });
  const previous = element('button', { type: 'button', className: 'button button-outline' }, '上一頁');
  const next = element('button', { type: 'button', className: 'button button-outline' }, '下一頁');
  let initialTotal = 0;
  try {
    initialTotal = JSON.parse(snapshot.textContent).total || 0;
  } catch {}
  const films = createPagedList({
    total: initialTotal,
    fetchPage: ({ page, pageSize }) => publicApi.films({ week: 'current', page, pageSize }),
    onBusy: controls,
    render(data, state) {
      const period = document.querySelector('#film-period');
      // 和發布頁一樣，英文字樣以 lang="en" 標示，讀屏才會用英文念出。
      if (period)
        period.replaceChildren(
          element('bdi', { lang: 'en' }, 'THIS WEEK'),
          ` · ${screeningPeriodLabel(data.period)}`
        );
      list.innerHTML = filmRows(data.items);
      status(notice, `已載入第 ${state.page} 頁，共 ${state.total} 部電影。`);
      fallbackContentImages();
    },
    // 換頁失敗時保留發布版本的片單，不回填原型資料。
    onError(error) {
      status(notice, error.message, true);
      notice.focus();
    },
  });
  function controls() {
    previous.disabled = films.state.busy || !films.hasPrevious();
    next.disabled = films.state.busy || !films.hasNext();
    pager.hidden = !films.hasPrevious() && !films.hasNext();
  }
  pager.append(previous, next);
  list.after(notice, pager);
  controls();
  const turn = offset => {
    if (!films.state.busy) status(notice, '正在讀取片單…');
    return films.goTo(films.state.page + offset);
  };
  previous.addEventListener('click', () => turn(-1));
  next.addEventListener('click', () => turn(1));
}
fallbackContentImages();
