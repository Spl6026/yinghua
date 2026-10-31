// [E-12] 重現原始 website/js/app.js 的唯讀卡片結構；資料以 textContent 安全輸出。
import { element } from '../core/ui.js';
import { places, films, detailLinks } from '../data/prototype.js';

export function detailHref(item, fallback) {
  return item.url || detailLinks[item.name || item.title] || fallback;
}

export function imageBlock(className, image, label, gradient) {
  const node = element('div', { className, role: 'img', 'aria-label': label });
  node.style.backgroundImage = (gradient ? gradient + ',' : '') + `url(${JSON.stringify(image)})`;
  return node;
}

export function attractionCardData(item) {
  return { ...item, area: item.district, type: item.typeLabel || item.category, desc: item.summary, rating: item.recommendationLabel || '在地推薦' };
}

export function placeCard(item) {
  const card = element('article', { className: 'place-card reveal' });
  const image = imageBlock('place-image', item.image, item.name);
  // [S：G34／E-27] 圖片區含收藏按鈕，使用 group 避免 img 語意隱藏互動子元素。
  image.setAttribute('role', 'group');
  image.append(element('span', { className: 'place-type' }, item.type || item.category),
    element('button', { type: 'button', className: 'favorite-button', 'aria-label': '收藏' + item.name, 'aria-pressed': 'false', 'data-favorite': item.id }, '♡'));
  const body = element('div', { className: 'place-body' });
  const details = element('div', { className: 'place-details' });
  const actions = element('span', { className: 'place-actions' });
  actions.append(element('a', { href: detailHref(item, '/place-detail.html?id=' + item.id) }, '看介紹 ↗'),
    element('button', { type: 'button', 'data-add-stop': item.id }, '＋ 行程'));
  details.append(element('span', {}, '◷ ' + (item.hours || '依店家公告')), actions);
  // [U-11／E-18] 選填內容留空時省略空段落及分隔符號，不自動補入介紹。
  body.append(element('span', { className: 'place-area' }, [item.area, item.rating].filter(Boolean).join('　/　')), element('h3', {}, item.name));
  if (item.desc) body.append(element('p', {}, item.desc));
  body.append(details);
  card.append(image, body);
  return card;
}

export function offerCard(item) {
  const card = element('article', { className: 'place-card reveal' });
  const image = imageBlock('place-image', item.image, item.merchant + '優惠圖片');
  image.append(element('span', { className: 'place-type' }, item.area + '・期間限定'));
  const body = element('div', { className: 'place-body' });
  const details = element('div', { className: 'place-details' });
  const actions = element('span', { className: 'place-actions' });
  actions.append(element('a', { href: detailHref(item, '/offer-detail.html?id=' + item.id) }, '優惠詳情 ↗'));
  details.append(element('strong', {}, item.discount), actions);
  body.append(element('span', { className: 'place-area' }, item.merchant + '　/　優惠至 ' + item.endDate), element('h3', {}, item.title), element('p', {}, item.desc), details);
  card.append(image, body);
  return card;
}

export function cinemaRow(item, index) {
  const row = element('article', { className: 'cinema-row' });
  const date = element('div', { className: 'cinema-date' }, item.date);
  date.append(element('small', {}, item.day + ' / OCT'));
  const info = element('div', { className: 'cinema-info' });
  info.append(element('span', {}, item.genre), element('h3', {}, item.name), element('p', {}, item.desc));
  row.append(imageBlock('cinema-poster', item.image, item.name + '電影海報', 'linear-gradient(0deg,rgba(18,24,20,.08),rgba(18,24,20,.08))'), date, info,
    element('div', { className: 'cinema-time' }, '◷ ' + item.time), element('a', { className: 'button button-outline', href: detailHref(item, '/film-detail.html?id=' + index) }, '場次介紹 ↗'));
  return row;
}

export function filmCard(item, index) {
  const card = element('article', { className: 'film-card' + (index === 0 ? ' film-featured' : '') + ' reveal' });
  const meta = element('div', { className: 'film-meta' });
  meta.append(element('span', {}, (index === 0 ? '本週主打　・　' : '') + item.genre), element('span', {}, String(index + 1).padStart(2, '0') + ' / ' + String(films.length).padStart(2, '0')));
  const footer = element('div', { className: 'film-footer' });
  footer.append(element('span', {}, item.date + '　' + item.time), element('a', { className: 'round-arrow small', 'aria-label': '開啟' + item.name + '詳情頁', href: detailHref(item, '/film-detail.html?id=' + index) }, '↗'));
  card.append(imageBlock('film-art', item.image, item.name + '電影海報', 'linear-gradient(0deg,rgba(16,24,19,.28),rgba(16,24,19,.04))'), meta, element('h3', {}, item.name), element('p', {}, item.desc), footer);
  return card;
}

export function newsImage(item) {
  const images = {
    '風城慢慢市集｜老城散步日': places[1].image,
    '在城市裡收集光｜街拍小展': 'https://images.unsplash.com/photo-1513364776144-60967b0f800f?auto=format&fit=crop&w=1100&q=85',
    '秋日咖啡散步地圖上線': places[4].image,
    '風的聲音｜午後草地音樂會': 'https://images.unsplash.com/photo-1501386761578-eac5c94b800a?auto=format&fit=crop&w=1100&q=85',
    '舊城街區步行環境改善公告': 'https://images.unsplash.com/photo-1519608487953-e999c86e7455?auto=format&fit=crop&w=1100&q=85',
  };
  return item.image || images[item.title] || places[0].image;
}

export function newsCard(item, index) {
  const card = element('a', { className: 'news-item news-card reveal', href: detailHref(item, '/news-detail.html?id=' + index), 'aria-label': '閱讀文章：' + item.title });
  const image = imageBlock('news-card-image', newsImage(item), item.title + '相關照片', 'linear-gradient(0deg,rgba(22,28,22,.18),rgba(22,28,22,.02))');
  image.append(element('span', { className: 'news-card-tag' }, item.type));
  const date = '2026 / ' + item.date;
  const top = element('div', { className: 'news-item-top' });
  top.append(element('span', {}, '新竹現場'), element('time', {}, date));
  const footer = element('div', { className: 'news-card-footer' });
  const action = element('strong', {}, '閱讀文章 ');
  action.append(element('span', { 'aria-hidden': 'true' }, '↗'));
  footer.append(element('span', {}, '發布日期　' + date), action);
  const body = element('div', { className: 'news-card-body' });
  body.append(top, element('h3', {}, item.title), element('p', {}, item.desc), footer);
  card.append(image, body);
  return card;
}

export function routeCard(item) {
  const card = element('article', { className: 'route-card' });
  card.append(element('p', { className: 'eyebrow' }, item.theme), element('h3', {}, item.title), element('p', {}, item.desc),
    element('a', { className: 'button button-dark', href: '/planner.html?route=' + encodeURIComponent(item.id) }, '套用這條路線 ↗'));
  return card;
}

export function itineraryStop(item, index) {
  const stop = element('article', { className: 'itinerary-stop' });
  const info = element('div', { className: 'stop-info' });
  info.append(element('span', {}, item.time + '　・　' + item.area), element('strong', {}, item.name), element('p', {}, item.desc));
  const actions = element('div', { className: 'stop-actions' });
  actions.append(element('button', { type: 'button', 'aria-label': '將' + item.name + '上移', 'data-move': index, 'data-dir': '-1' }, '↑'),
    element('button', { type: 'button', 'aria-label': '將' + item.name + '下移', 'data-move': index, 'data-dir': '1' }, '↓'),
    element('button', { type: 'button', 'aria-label': '從行程移除' + item.name, 'data-remove': item.id }, '×'));
  stop.append(element('span', { className: 'stop-number' }, String(index + 1).padStart(2, '0')), info, actions);
  return stop;
}
