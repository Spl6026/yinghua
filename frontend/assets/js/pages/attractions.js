// 初次內容由靜態 HTML 提供，篩選與換頁才呼叫公開 API。
import { element, status } from '../core/ui.js';
import { placeCard, attractionCardData as cardData } from '../components/content-cards.js';
import { mountMemberActions } from '../core/member-actions.js';
import { fallbackContentImages } from '../core/content-view.js';
import { publicApi } from '../api/public-api.js';
import { createPagedList } from '../core/paged-list.js';

if (typeof document !== 'undefined' && document.body.dataset.managedAttractions) {
  fallbackContentImages();
  const grid = document.querySelector('#place-grid');
  if (grid) {
    const query = document.querySelector('#place-search');
    const district = document.querySelector('#area-filter');
    const tabs = [...document.querySelectorAll('.guide-tab')];
    let category = location.pathname === '/food.html' ? '美食' : '',
      requestedPage = 1,
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
      requestedPage = Math.max(1, Number.parseInt(params.get('page') || '1', 10) || 1);
    }
    function writeUrl(push, page) {
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
    const places = createPagedList({
      pageSize: 12,
      fetchPage: ({ page, pageSize }) =>
        publicApi.attractions({
          q: query?.value.trim() || '',
          category,
          district: district?.value === 'all' ? '' : district?.value || '',
          page,
          pageSize,
        }),
      onBusy(state) {
        previous.disabled = state.busy || !places.hasPrevious();
        next.disabled = state.busy || !places.hasNext();
        if (state.busy) {
          grid.setAttribute('aria-busy', 'true');
          status(message, '讀取景點中…');
          markTabs();
        } else grid.removeAttribute('aria-busy');
      },
      render(data, state) {
        // 網址上的頁碼超出範圍時會改讀最後一頁，網址也跟著更正。
        if (state.page !== requestedPage) {
          requestedPage = state.page;
          writeUrl(false, state.page);
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
        fallbackContentImages();
        void mountMemberActions();
        label.textContent = `第 ${state.page} 頁，共 ${state.total} 筆`;
        status(message, `找到 ${state.total} 筆景點。`);
      },
      onError(error) {
        grid.replaceChildren();
        status(message, error.message, true);
        const retry = element('button', { type: 'button', className: 'button button-outline' }, '重新讀取');
        retry.addEventListener('click', () => places.load());
        grid.append(retry);
      },
    });
    // 篩選條件改變：回到第一頁並記錄網址；打字搜尋只更新目前這筆瀏覽紀錄。
    const refilter = push => {
      requestedPage = 1;
      writeUrl(push, 1);
      return places.reset();
    };
    const turn = offset => {
      if (places.state.busy || !(offset < 0 ? places.hasPrevious() : places.hasNext())) return;
      requestedPage = places.state.page + offset;
      writeUrl(true, requestedPage);
      return places.goTo(requestedPage);
    };
    tabs.forEach(button =>
      button.addEventListener('click', () => {
        category = button.dataset.category === 'all' ? '' : button.dataset.category;
        void refilter(true);
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
        void refilter(false);
      }, 250);
    });
    district?.addEventListener('change', () => {
      void refilter(true);
    });
    previous.addEventListener('click', () => void turn(-1));
    next.addEventListener('click', () => void turn(1));
    window.addEventListener('popstate', () => {
      readUrl();
      void places.load(requestedPage);
    });
    await places.load(requestedPage);
  }
}
