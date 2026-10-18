// [E-12] 公開內容維持原始交付的展示資料與版型，正式內容 API 留待後續階段。
import { memberNavigation, requireSession } from './core/session.js';
import { element, toast } from './core/ui.js';
import { places, films, news, offers, routes, itineraryPreview } from './data/prototype.js';
import { placeCard, cinemaRow, filmCard, newsCard, offerCard, routeCard, searchHit, itineraryStop } from './components/content-cards.js';
import { initializeDetails } from './pages/public-details.js';

void memberNavigation();
let observer;
function reveal() {
  observer?.disconnect();
  const nodes = document.querySelectorAll('.page.active .reveal,.page.active .image-reveal');
  if (!('IntersectionObserver' in window) || matchMedia('(prefers-reduced-motion: reduce)').matches) {
    nodes.forEach(node => node.classList.add('in-view'));
    return;
  }
  observer = new IntersectionObserver(entries => entries.forEach(entry => {
    if (entry.isIntersecting) { entry.target.classList.add('in-view'); observer.unobserve(entry.target); }
  }), { threshold: .12 });
  nodes.forEach(node => observer.observe(node));
}
function render(selector, items, factory, empty) {
  const node = document.querySelector(selector);
  if (!node) return;
  node.replaceChildren(...items.map(factory));
  if (!items.length) node.append(element('p', { className: 'empty-results' }, empty));
}
const path = location.pathname;
const isSearch = path === '/search.html';
let category = path === '/food.html' ? '美食' : 'all';
const placeSearch = document.querySelector('#place-search');
const areaFilter = document.querySelector('#area-filter');
const queryInput = document.querySelector('#query-input');
const newsFilter = document.querySelector('#news-filter');
if (newsFilter && path === '/activities.html') newsFilter.value = '活動';
if (newsFilter && path === '/city-news.html') newsFilter.value = '城市消息';
const initialQuery = new URLSearchParams(location.search).get('q') || '';
if (placeSearch) placeSearch.value = initialQuery;
if (queryInput) queryInput.value = initialQuery;

function renderPlaces() {
  if (document.body.dataset.managedAttractions) return;
  if (isSearch) return;
  const query = (placeSearch?.value ?? initialQuery).trim().toLowerCase();
  const area = areaFilter?.value || 'all';
  const visible = places.filter(item => (category === 'all' || item.category === category) && (area === 'all' || item.area === area) && `${item.name} ${item.desc} ${item.type}`.toLowerCase().includes(query));
  render('#place-grid', visible, placeCard, '找不到符合條件的地點，試試其他關鍵字。');
  document.querySelectorAll('.guide-tab').forEach(button => {
    const active = button.dataset.category === category;
    button.classList.toggle('active', active);
    button.setAttribute('aria-selected', String(active));
    const count = button.querySelector('span');
    if (count) count.textContent = String(places.filter(item => button.dataset.category === 'all' || item.category === button.dataset.category).length).padStart(2, '0');
  });
  reveal();
}
document.querySelectorAll('.guide-tab').forEach(button => button.addEventListener('click', () => { category = button.dataset.category; renderPlaces(); }));
placeSearch?.addEventListener('input', renderPlaces);
areaFilter?.addEventListener('change', renderPlaces);

function renderNews() {
  const type = newsFilter?.value || (path === '/activities.html' ? '活動' : path === '/city-news.html' ? '城市消息' : 'all');
  render('#news-list', news.filter(item => type === 'all' || item.type === type), item => newsCard(item, news.indexOf(item)), '目前沒有符合分類的消息。');
  reveal();
}
newsFilter?.addEventListener('change', renderNews);

function renderSearch() {
  if (!isSearch) return;
  const query = queryInput.value.trim().toLowerCase();
  const grid = document.querySelector('#place-grid');
  grid.replaceChildren();
  if (!query) { grid.append(element('p', { className: 'empty-results' }, '輸入關鍵字，即可搜尋景點、美食、片單與城市消息。')); return; }
  for (const [items, fields, kind, text, fallback] of [
    [places, ['name', 'desc', 'type', 'area'], item => item.category, item => item.desc, item => '/place-detail.html?id=' + item.id],
    [films, ['name', 'genre', 'desc'], () => '本週映演', item => item.desc, item => '/film-detail.html?id=' + films.indexOf(item)],
    [offers, ['title', 'merchant', 'discount', 'desc'], () => '店家優惠', item => item.discount, item => '/offer-detail.html?id=' + item.id],
    [news, ['title', 'type', 'desc'], item => item.type, item => item.desc, item => '/news-detail.html?id=' + news.indexOf(item)],
  ]) {
    items.filter(item => fields.map(field => item[field] || '').join(' ').toLowerCase().includes(query))
      .forEach(item => grid.append(searchHit(item, kind(item), text(item), fallback(item))));
  }
  if (!grid.children.length) grid.append(element('p', { className: 'empty-results' }, '沒有找到符合條件的資料，請試試景點名稱、電影片名或活動關鍵字。'));
}
document.querySelector('#standalone-search')?.addEventListener('submit', event => {
  event.preventDefault();
  history.replaceState(null, '', path + '?q=' + encodeURIComponent(queryInput.value));
  renderSearch();
});

renderPlaces();
render('#cinema-list', films, cinemaRow, '目前沒有本週映演場次。');
document.querySelector('#cinema-list')?.append(element('p', { className: 'demo-note' }, '以上場次為展示用示意資料，請以新竹市影像博物館公告為準。'));
render('.screening-grid', films.slice(0, 3), filmCard, '目前沒有本週映演場次。');
renderNews();
render('#offers-list', offers, offerCard, '目前沒有上架中的店家優惠。');
render('#route-cards', routes, routeCard, '目前沒有推薦行程。');
renderSearch();
initializeDetails();
reveal();

// 保留原有按鈕位置；尚未實作的業務不以瀏覽器假資料保存。
document.addEventListener('click', event => {
  if (event.target.closest('[data-favorite],[data-add-stop],[data-move],[data-remove],#save-trip,#use-route')) {
    event.preventDefault(); toast('收藏與行程功能將於後續階段開放。');
  }
  const navigation = event.target.closest('button[data-page]');
  if (navigation) {
    const href = { home: '/index.html', explore: '/explore.html', cinema: '/cinema.html', events: '/events.html', planner: '/planner.html' }[navigation.dataset.page];
    if (href) { event.preventDefault(); location.assign(href); }
  }
});
const backdrop = document.querySelector('.hero-backdrop');
if (backdrop) window.addEventListener('scroll', () => {
  if (innerWidth >= 761 && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
    backdrop.style.transform = `scale(1.03) translateY(${Math.min(scrollY * .11, 75)}px)`;
  }
}, { passive: true });
document.querySelector('#add-stop')?.addEventListener('click', () => location.assign('/explore.html'));
if (path === '/planner.html') {
  try {
    const identity = await requireSession();
    if (identity) {
      document.querySelector('#planner-user-name').textContent = identity.member.displayName;
      const accountLink = document.querySelector('.planner-user .text-link');
      accountLink.href = '/change-password.html';
      accountLink.replaceChildren(document.createTextNode('更改密碼 '), element('span', {}, '↗'));
      render('#itinerary-list', itineraryPreview, itineraryStop, '目前沒有示意行程。');
    }
  } catch (error) { toast(error.message); }
}
