// 初次內容由靜態 HTML 提供，篩選與換頁才呼叫公開 API。
import { api } from '../core/api-client.js';
import { element, status } from '../core/ui.js';
import { placeCard, attractionCardData as cardData } from '../components/content-cards.js';
import { mountMemberActions } from '../core/member-actions.js';

export { cardData };
function imageFallbacks() {
  document.querySelectorAll('[data-image-url]').forEach(node => {
    if (node.dataset.imageChecked) return;
    node.dataset.imageChecked = 'true';
    const image = new Image();
    image.addEventListener('error', () => {
      node.style.backgroundImage = 'url("/assets/image-placeholder.svg")';
    });
    image.src = node.dataset.imageUrl;
  });
}
if (typeof document !== 'undefined' && document.body.dataset.managedAttractions) {
  imageFallbacks();
  const grid = document.querySelector('#place-grid');
  if (grid) {
    const query = document.querySelector('#place-search');
    const district = document.querySelector('#area-filter');
    const tabs = [...document.querySelectorAll('.guide-tab')];
    let category = location.pathname === '/food.html' ? '美食' : '',
      page = 1,
      generation = 0,
      timer;
    const message = element('p', { role: 'status', tabindex: '-1', className: 'form-status' });
    grid.before(message);
    const navigation = element('nav', { className: 'list-pagination', 'aria-label': '景點分頁' });
    const previous = element('button', { type: 'button', className: 'button button-outline' }, '上一頁');
    const next = element('button', { type: 'button', className: 'button button-outline' }, '下一頁');
    const label = element('span', { 'aria-live': 'polite' });
    navigation.append(previous, label, next);
    grid.after(navigation);
    // 篩選條件寫進網址：重新整理、分享連結或按上一頁都會回到同一個篩選結果，和一般網站的列表一致。
    // 換分類、區域與換頁會新增一筆瀏覽紀錄；邊打字邊搜尋只更新目前這筆，不讓上一頁塞滿每個字。
    // 美食頁預設顯示「美食」分類；網址只記錄與預設不同的分類，「全部」以 all 表示。
    const defaultCategory = category;
    const categories = tabs.map(button => (button.dataset.category === 'all' ? '' : button.dataset.category));
    function readUrl() {
      const params = new URLSearchParams(location.search);
      if (query) query.value = params.get('q') || '';
      const area = params.get('district');
      if (district)
        district.value = [...district.options].some(option => option.value === area) ? area : 'all';
      const wanted = params.get('category');
      const value = wanted === 'all' ? '' : wanted;
      category = wanted !== null && categories.includes(value) ? value : defaultCategory;
      page = Math.max(1, Number.parseInt(params.get('page') || '1', 10) || 1);
    }
    function writeUrl(push) {
      const params = new URLSearchParams();
      const text = query?.value.trim();
      if (text) params.set('q', text);
      if (category !== defaultCategory) params.set('category', category || 'all');
      if (district?.value && district.value !== 'all') params.set('district', district.value);
      if (page > 1) params.set('page', String(page));
      const search = params.toString();
      const target = location.pathname + (search ? '?' + search : '');
      if (target === location.pathname + location.search) return;
      history[push ? 'pushState' : 'replaceState'](null, '', target);
    }
    readUrl();
    function markTabs(counts) {
      tabs.forEach((button, index) => {
        const value = button.dataset.category === 'all' ? '' : button.dataset.category;
        const active = value === category;
        button.classList.toggle('active', active);
        button.setAttribute('aria-selected', String(active));
        button.tabIndex = active ? 0 : -1;
        button.id ||= `attraction-tab-${index}`;
        button.setAttribute('aria-controls', 'place-grid');
        if (active) {
          grid.setAttribute('role', 'tabpanel');
          grid.setAttribute('aria-labelledby', button.id);
        }
        // 美食原型的分類沒有數字 span，共用載入流程不能因此中斷。
        const count = button.querySelector('span');
        if (counts && count) count.textContent = String(counts[value || 'all'] || 0).padStart(2, '0');
      });
    }
    async function load() {
      const request = ++generation;
      previous.disabled = next.disabled = true;
      grid.setAttribute('aria-busy', 'true');
      status(message, '讀取景點中…');
      markTabs();
      try {
        const data = await api(
          '/api/v1/attractions?' +
            new URLSearchParams({
              q: query?.value.trim() || '',
              category,
              district: district?.value === 'all' ? '' : district?.value || '',
              page,
              pageSize: 12,
            })
        );
        if (request !== generation) return;
        if (page > 1 && (page - 1) * 12 >= data.total) {
          page = Math.max(1, Math.ceil(data.total / 12));
          writeUrl(false);
          await load();
          return;
        }
        grid.replaceChildren(
          ...data.items.map(item => {
            const card = placeCard(cardData(item));
            card.classList.add('in-view');
            const cover = card.querySelector('.place-image');
            cover.dataset.imageUrl = item.image;
            cover.setAttribute('aria-label', item.imageAlt);
            return card;
          })
        );
        if (!data.items.length)
          grid.append(element('p', { className: 'empty-results' }, '找不到符合條件的地點，試試其他關鍵字。'));
        markTabs(data.counts);
        imageFallbacks();
        void mountMemberActions();
        label.textContent = `第 ${page} 頁，共 ${data.total} 筆`;
        previous.disabled = page <= 1;
        next.disabled = page * 12 >= data.total;
        status(message, `找到 ${data.total} 筆景點。`);
      } catch (error) {
        if (request !== generation) return;
        grid.replaceChildren();
        status(message, error.message, true);
        const retry = element('button', { type: 'button', className: 'button button-outline' }, '重新讀取');
        retry.addEventListener('click', load);
        grid.append(retry);
      } finally {
        if (request === generation) grid.removeAttribute('aria-busy');
      }
    }
    tabs.forEach(button =>
      button.addEventListener('click', () => {
        category = button.dataset.category === 'all' ? '' : button.dataset.category;
        page = 1;
        writeUrl(true);
        void load();
      })
    );
    document.querySelector('.guide-tabs')?.addEventListener('keydown', event => {
      const index = tabs.indexOf(document.activeElement);
      if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key) || index < 0) return;
      event.preventDefault();
      const target =
        event.key === 'Home'
          ? 0
          : event.key === 'End'
            ? tabs.length - 1
            : (index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
      tabs[target].focus();
      tabs[target].click();
    });
    query?.addEventListener('input', () => {
      clearTimeout(timer);
      timer = setTimeout(() => {
        page = 1;
        writeUrl(false);
        void load();
      }, 250);
    });
    district?.addEventListener('change', () => {
      page = 1;
      writeUrl(true);
      void load();
    });
    previous.addEventListener('click', () => {
      page--;
      writeUrl(true);
      void load();
    });
    next.addEventListener('click', () => {
      page++;
      writeUrl(true);
      void load();
    });
    window.addEventListener('popstate', () => {
      readUrl();
      void load();
    });
    await load();
  }
}
