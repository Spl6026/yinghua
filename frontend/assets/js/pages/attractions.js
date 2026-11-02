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
    if (query) query.value = new URLSearchParams(location.search).get('q') || '';
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
        void load();
      }, 250);
    });
    district?.addEventListener('change', () => {
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
    await load();
  }
}
