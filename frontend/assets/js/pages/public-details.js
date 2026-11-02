// 原始詳情頁與上一篇／下一篇版型，使用原始交付的唯讀內容。
import { element } from '../core/ui.js';
import { places, films, news, offers } from '../data/prototype.js';
import { detailHref, imageBlock } from '../components/content-cards.js';

function bodyContent(item, eyebrow, lead) {
  const body = element('div', { className: 'detail-body' });
  body.append(
    element('p', { className: 'eyebrow' }, eyebrow),
    element('h1', {}, item.name || item.title),
    element('p', { className: 'detail-lead' }, lead)
  );
  document.title = (item.name || item.title) + '｜映畫竹塹';
  return body;
}
function actions(back, label, other, otherLabel, arrowSpan = false) {
  const node = element('div', { className: 'detail-actions' });
  const link = element(
    'a',
    { className: 'text-link', href: other },
    arrowSpan ? otherLabel.replace(/ →$/, ' ') : otherLabel
  );
  if (arrowSpan) link.append(element('span', {}, '→'));
  node.append(element('a', { className: 'button button-dark', href: back }, label), link);
  return node;
}
function updateDynamic(selector, item, title, meta) {
  const container = document.querySelector(selector);
  if (!container) return;
  container.querySelector('[data-detail-title]').textContent = item ? title : '找不到這筆內容';
  if (!item) return;
  container.querySelector('[data-detail-lead]').textContent = item.desc;
  container.querySelector('[data-detail-meta]').textContent = meta;
  document.title = title + '｜映畫竹塹';
  if (item.image) {
    const cover = container.querySelector('.detail-cover');
    cover.style.backgroundImage = `url(${JSON.stringify(item.image)})`;
    cover.setAttribute('aria-label', title + '電影海報');
  }
}

export function initializeDetails() {
  if (document.body.dataset.managedAttractions) return;
  const query = new URLSearchParams(location.search);
  const id = Number(query.get('id'));
  const place = document.querySelector('#place-detail-content');
  if (place) {
    const item = places.find(item => item.id === id);
    if (item) {
      const body = bodyContent(item, 'HSINCHU CITY GUIDE / DETAIL', item.desc);
      const location = element('p', {}, item.address);
      location.append(element('br'), document.createTextNode('營業時間：' + item.hours));
      body.append(
        location,
        element(
          'div',
          { className: 'detail-meta' },
          item.area + '・' + item.type + (item.discount ? '・' + item.discount : '')
        ),
        actions('/explore.html', '回到城市散策 ↗', '/planner.html?place=' + item.id, '加入旅行計畫 →', true)
      );
      place.replaceChildren(imageBlock('detail-cover', item.image, item.name), body);
    } else
      place.replaceChildren(
        element('h1', {}, '找不到這個景點'),
        element('p', {}, '這筆示範景點可能已刪除。'),
        element('a', { className: 'button button-dark', href: '/explore.html' }, '回到城市散策 ↗')
      );
  }
  const film = films[id];
  updateDynamic(
    '#dynamic-film-detail',
    film,
    film?.name,
    film ? `${film.date}　${film.day}　${film.time}｜${film.genre}` : ''
  );
  const article = news[id];
  updateDynamic(
    '#dynamic-news-detail',
    article,
    article?.title,
    article ? `${article.type}｜${article.date}` : ''
  );
  if (location.pathname.startsWith('/films/')) {
    const detail = document.querySelector('.detail-page');
    const item = films.find(item => item.name === detail?.querySelector('h1')?.textContent.trim());
    if (item) {
      detail.querySelector('.detail-cover').style.backgroundImage = `url(${JSON.stringify(item.image)})`;
      detail.querySelector('.detail-lead').textContent = item.desc;
      detail.querySelector('.detail-meta').textContent = `${item.genre}・${item.date} ${item.time}`;
    }
  }
  const offer = document.querySelector('#offer-detail-content');
  if (offer) {
    const item =
      offers.find(item => item.id === id) ||
      offers.find(item => detailHref(item, '').split('?')[0] === location.pathname) ||
      offers[id];
    if (item) {
      const body = bodyContent(item, 'LOCAL OFFER / ' + item.area, item.discount);
      document.title = item.title + '｜店家優惠｜映畫竹塹';
      const information = element('p', {}, '合作店家：' + item.merchant);
      information.append(
        element('br'),
        document.createTextNode(`優惠期間：${item.startDate} 至 ${item.endDate}`)
      );
      body.append(
        element('p', {}, item.desc),
        information,
        element('div', { className: 'detail-meta' }, '優惠使用方式與注意事項：' + item.terms),
        actions('/offers.html', '回到店家優惠 ↗', '/explore.html', '探索更多新竹店家 →')
      );
      offer.replaceChildren(imageBlock('detail-cover', item.image, item.merchant + '優惠圖片'), body);
    } else
      offer.replaceChildren(
        element('h1', {}, '找不到這項優惠'),
        element('p', {}, '優惠可能已下架。'),
        element('a', { className: 'button', href: '/offers.html' }, '回到店家優惠 ↗')
      );
  }
  initializeNeighbors();
}

function initializeNeighbors() {
  const path = location.pathname;
  const id = Number(new URLSearchParams(location.search).get('id'));
  const container = document.querySelector('.detail-page,.detail-layout');
  if (!container) return;
  let items, description, fallback;
  if (path.startsWith('/films/') || path === '/film-detail.html') {
    items = films;
    description = item => item.genre;
    fallback = item => '/film-detail.html?id=' + films.indexOf(item);
  } else if (
    path.startsWith('/offers/') ||
    path === '/offer-detail.html' ||
    path === '/news/coffee-walk.html'
  ) {
    items = offers;
    description = item => item.merchant;
    fallback = item => '/offer-detail.html?id=' + item.id;
  } else if (path.startsWith('/news/') || path === '/news-detail.html') {
    items = news;
    description = item => item.type;
    fallback = item => '/news-detail.html?id=' + news.indexOf(item);
  } else if (path.startsWith('/places/') || path === '/museum.html' || path === '/place-detail.html') {
    items = places;
    description = item => item.area + '・' + item.type;
    fallback = item => '/place-detail.html?id=' + item.id;
  } else return;
  const current = items.findIndex(
    item =>
      detailHref(item, fallback(item)).split('?')[0] === path ||
      (path.endsWith('-detail.html') && (item.id ? item.id === id : items.indexOf(item) === id)) ||
      (path === '/news/coffee-walk.html' && item.id === 103)
  );
  if (current < 0) return;
  const grid = element('div', { className: 'article-neighbor-grid' });
  for (const [index, label, direction] of [
    [current - 1, '上一篇', 'previous'],
    [current + 1, '下一篇', 'next'],
  ]) {
    const item = items[index];
    if (!item) continue;
    const title = item.name || item.title;
    const image =
      item.image ||
      (item.type === '活動'
        ? 'https://images.unsplash.com/photo-1517457373958-b7de0e?auto=format&fit=crop&w=800&q=75'
        : 'https://images.unsplash.com/photo-1470252649378-9c29740c9fa8?auto=format&fit=crop&w=800&q=75');
    const link = element('a', {
      className: 'article-neighbor article-neighbor-' + direction,
      href: detailHref(item, fallback(item)),
      'aria-label': label + '：' + title,
    });
    const copy = element('span', { className: 'article-neighbor-copy' });
    copy.append(
      element('span', { className: 'article-neighbor-label' }, label),
      element('strong', {}, title),
      element('small', {}, description(item))
    );
    link.append(element('img', { src: image, alt: '', loading: 'lazy' }), copy);
    grid.append(link);
  }
  if (!grid.children.length) return;
  const navigation = element('nav', { className: 'article-neighbors', 'aria-label': '同主題文章導覽' });
  navigation.append(element('h2', {}, '繼續閱讀'), grid);
  container.after(navigation);
}
