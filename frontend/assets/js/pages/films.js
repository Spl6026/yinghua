import { api } from '../core/api-client.js';
import { filmRows, screeningPeriodLabel } from '../core/film-view.js';
import { element, status } from '../core/ui.js';
import { fallbackContentImages } from '../core/content-view.js';

// 初始內容由同一發布版本的 HTML 提供；換頁失敗時不回填原型資料。
const list = document.querySelector('#cinema-list');
const snapshot = document.querySelector('#film-publication');
if (list && snapshot) {
  const notice = element('p', { role: 'status', tabindex: '-1', className: 'form-status' });
  const pager = element('div', { className: 'pagination-controls' });
  const previous = element('button', { type: 'button', className: 'button button-outline' }, '上一頁');
  const next = element('button', { type: 'button', className: 'button button-outline' }, '下一頁');
  let page = 1,
    busy = false,
    total = 0;
  try {
    total = JSON.parse(snapshot.textContent).total || 0;
  } catch {}
  const controls = () => {
    previous.disabled = busy || page === 1;
    next.disabled = busy || page * 20 >= total;
    pager.hidden = page === 1 && total <= 20;
  };
  pager.append(previous, next);
  list.after(notice, pager);
  controls();
  const load = async target => {
    if (busy) return;
    busy = true;
    controls();
    status(notice, '正在讀取片單…');
    try {
      const data = await api(`/api/v1/films?week=current&page=${target}&pageSize=20`);
      const period = document.querySelector('#film-period');
      // 和發布頁一樣，英文字樣以 lang="en" 標示，讀屏才會用英文念出。
      if (period)
        period.replaceChildren(
          element('bdi', { lang: 'en' }, 'THIS WEEK'),
          ` · ${screeningPeriodLabel(data.period)}`
        );
      list.innerHTML = filmRows(data.items);
      page = target;
      total = data.total;
      status(notice, `已載入第 ${page} 頁，共 ${total} 部電影。`);
      fallbackContentImages();
    } catch (error) {
      status(notice, error.message, true);
      notice.focus();
    } finally {
      busy = false;
      controls();
    }
  };
  previous.addEventListener('click', () => load(page - 1));
  next.addEventListener('click', () => load(page + 1));
}
fallbackContentImages();
