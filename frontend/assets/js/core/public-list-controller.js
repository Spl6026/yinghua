import { contentPager, pageHref, fallbackContentImages } from './content-view.js';
import { readPublicListSeo, updatePublicListSeo } from './list-seo.js';

// [E-26] 消息／優惠只共用換頁、URL、競態、錯誤重試與焦點；主題呈現由呼叫端傳入。
export function initializePublicList({
  stateSelector,
  listSelector,
  loadData,
  renderItems,
  titleFor,
  descriptionFor = () => '',
  typeFromUrl,
  renderFeatured = () => {},
  filterSelector = null,
}) {
  const initial = document.querySelector(stateSelector),
    list = document.querySelector(listSelector);
  if (!initial || !list) return;
  const controls = document.querySelector('.public-content-controls');
  const notice = controls.querySelector('.public-content-status');
  const form = filterSelector ? document.querySelector(filterSelector) : null;
  const select = form?.querySelector('select');
  const seoContext = readPublicListSeo();
  let state = JSON.parse(initial.textContent),
    sequence = 0;
  const load = async (target, changeHistory = true) => {
    const request = ++sequence;
    notice.textContent = '正在讀取內容…';
    list.setAttribute('aria-busy', 'true');
    try {
      const data = await loadData(target);
      if (request !== sequence) return;
      const href = pageHref(location.pathname, target.type, data.page);
      // 設定／內容已換版時讀新發布頁，避免用舊 SEO context 搭配新資料。
      if (!seoContext || data.generation !== state.generation) {
        location.assign(href);
        return;
      }
      list.innerHTML = renderItems(data.items);
      renderFeatured(data);
      controls.querySelector('nav')?.remove();
      notice.insertAdjacentHTML(
        'beforebegin',
        contentPager(data.total, data.page, location.pathname, target.type)
      );
      if (select) select.value = target.type;
      state = { ...data, type: target.type };
      if (changeHistory) history.pushState(null, '', href);
      updatePublicListSeo(seoContext, href, titleFor(data, target.type), descriptionFor(data, target.type));
      notice.textContent = `已載入第 ${data.page} 頁，共 ${data.total} 筆內容。`;
      fallbackContentImages();
    } catch (error) {
      if (request !== sequence) return;
      notice.textContent = error.message + ' ';
      const retry = document.createElement('button');
      retry.type = 'button';
      retry.className = 'text-link';
      retry.textContent = '重試';
      retry.addEventListener('click', () => load(target, changeHistory));
      notice.append(retry);
      notice.focus();
    } finally {
      if (request === sequence) list.removeAttribute('aria-busy');
    }
  };
  form?.addEventListener('submit', event => {
    event.preventDefault();
    void load({ type: select.value, page: 1 });
  });
  select?.addEventListener('change', () => void load({ type: select.value, page: 1 }));
  controls.addEventListener('click', event => {
    const link = event.target.closest('a[data-content-page]');
    if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey)
      return;
    event.preventDefault();
    void load({ type: state.type, page: Number(new URL(link.href).searchParams.get('page') || 1) }).then(() =>
      notice.focus()
    );
  });
  addEventListener(
    'popstate',
    () =>
      void load(
        { type: typeFromUrl(), page: Number(new URLSearchParams(location.search).get('page') || 1) },
        false
      )
  );
}
