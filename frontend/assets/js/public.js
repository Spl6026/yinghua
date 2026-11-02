// [E-12／E-27] 共用導覽與未交付頁的展示；受管內容與會員資料交由各模組的真實 API。
import { memberNavigation } from './core/session.js';
import { element, toast } from './core/ui.js';
import { places, films, news, offers, routes } from './data/prototype.js';
import { placeCard, cinemaRow, filmCard, newsCard, offerCard, routeCard } from './components/content-cards.js';
import { initializeDetails } from './pages/public-details.js';
import { mountMemberActions } from './core/member-actions.js';

void memberNavigation();
// [S：G29；U-24] 上一篇／下一篇縮圖為靜態發布 HTML，圖片失效時與主圖同樣改用替代圖，不留破圖。
const placeholder = '/assets/image-placeholder.svg';
function useImageFallback(image) {
  if (!image.closest?.('.article-neighbor') || image.dataset.fallback) return;
  image.dataset.fallback = 'true';
  image.src = placeholder;
}
document.addEventListener('error', event => { if (event.target instanceof HTMLImageElement) useImageFallback(event.target); }, true);
document.querySelectorAll('.article-neighbor img').forEach(image => { if (image.complete && !image.naturalWidth) useImageFallback(image); });
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
let category = path === '/food.html' ? '美食' : 'all';
const placeSearch = document.querySelector('#place-search');
const areaFilter = document.querySelector('#area-filter');
const newsFilter = document.querySelector('#news-filter');
if (newsFilter && path === '/activities.html') newsFilter.value = '活動';
if (newsFilter && path === '/city-news.html') newsFilter.value = '城市消息';
const initialQuery = new URLSearchParams(location.search).get('q') || '';
if (placeSearch) placeSearch.value = initialQuery;

function renderPlaces() {
  if (document.body.dataset.managedAttractions) return;
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
  if(document.body.dataset.managedNews)return;
  const type = newsFilter?.value || (path === '/activities.html' ? '活動' : path === '/city-news.html' ? '城市消息' : 'all');
  render('#news-list', news.filter(item => type === 'all' || item.type === type), item => newsCard(item, news.indexOf(item)), '目前沒有符合分類的消息。');
  reveal();
}
newsFilter?.addEventListener('change', renderNews);


renderPlaces();
if (!document.body.dataset.managedFilms) render('#cinema-list', films, cinemaRow, '目前沒有本週映演場次。');
if (!document.body.dataset.managedFilms) document.querySelector('#cinema-list')?.append(element('p', { className: 'demo-note' }, '以上場次為展示用示意資料，請以新竹市影像博物館公告為準。'));
if (!document.body.dataset.managedFilms) render('.screening-grid', films.slice(0, 3), filmCard, '目前沒有本週映演場次。');
renderNews();
if(!document.body.dataset.managedOffers)render('#offers-list', offers, offerCard, '目前沒有上架中的店家優惠。');
if(!document.body.dataset.managedRoutes)render('#route-cards', routes, routeCard, '目前沒有推薦行程。');
initializeDetails();
reveal();

// 保留原有按鈕位置；尚未實作的業務不以瀏覽器假資料保存。
document.addEventListener('click', event => {
  if (!document.body.dataset.managedItineraries && event.target.closest('[data-move],#use-route')) {
    event.preventDefault(); toast('完整行程編排將於後續開放。');
  }
  const navigation = event.target.closest('button[data-page]');
  if (navigation) {
    const href = { home: '/index.html', explore: '/explore.html', cinema: '/cinema.html', events: '/events.html', planner: '/planner.html' }[navigation.dataset.page];
    if (href) { event.preventDefault(); location.assign(href); }
  }
});
void mountMemberActions();
const backdrop = document.querySelector('.hero-backdrop');
if (backdrop) window.addEventListener('scroll', () => {
  if (innerWidth >= 761 && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
    backdrop.style.transform = `scale(1.03) translateY(${Math.min(scrollY * .11, 75)}px)`;
  }
}, { passive: true });
if(!document.body.dataset.managedItineraries)document.querySelector('#add-stop')?.addEventListener('click', () => location.assign('/explore.html'));
