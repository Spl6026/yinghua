import { memberResourcesApi } from '../api/member-resources-api.js';
import { requireSession, handleSessionExpiry } from '../core/session.js';
import { element, status } from '../core/ui.js';
import { mountMemberActions } from '../core/member-actions.js';
import { placeCard, attractionCardData } from '../components/content-cards.js';

// 私人收藏與公開卡片分開處理；收藏項目不可用時，不從草稿補出名稱或圖片。
const list = document.querySelector('#favorites-list');
const message = document.querySelector('#favorites-status');
const previous = document.querySelector('#favorites-previous');
const next = document.querySelector('#favorites-next');
const retry = document.querySelector('#favorites-retry');
let cursor = '',
  nextCursor = null,
  history = [],
  generation = 0;

function card(record) {
  if (record.attraction) {
    const node = placeCard(attractionCardData(record.attraction));
    node.classList.add('in-view');
    return node;
  }
  const node = element('article', { className: 'place-card member-unavailable' });
  const body = element('div', { className: 'place-body' });
  body.append(
    element('h3', {}, record.availability === 'unknown' ? '地點資訊更新中' : '此地點目前無法瀏覽'),
    element('p', {}, '你仍可取消這筆收藏。'),
    element(
      'button',
      {
        type: 'button',
        className: 'button button-outline',
        'data-favorite': record.attractionId,
        'aria-label': '取消收藏不可用地點',
        'aria-pressed': 'true',
      },
      '已收藏 ♥'
    )
  );
  node.append(
    element('div', { className: 'place-image', role: 'img', 'aria-label': '地點圖片尚未提供' }),
    body
  );
  return node;
}

async function load(focus = false) {
  const request = ++generation;
  list.replaceChildren();
  list.setAttribute('aria-busy', 'true');
  previous.disabled = next.disabled = true;
  retry.hidden = true;
  status(message, '讀取本人收藏中…');
  try {
    if (!(await requireSession())) return;
    const data = await memberResourcesApi.favorites(cursor);
    if (request !== generation) return;
    list.replaceChildren(...data.items.map(card));
    if (!data.items.length)
      list.append(
        element('p', { className: 'empty-results' }, '目前沒有收藏，去城市散策找找喜歡的地方。'),
        element('a', { className: 'text-link', href: '/explore.html' }, '探索城市散策 →')
      );
    nextCursor = data.nextCursor;
    previous.disabled = !history.length;
    next.disabled = !nextCursor;
    status(message, `本頁已載入 ${data.items.length} 筆收藏。`);
    await mountMemberActions();
    if (focus && request === generation) message.focus();
  } catch (error) {
    if (request !== generation) return;
    if (error.status === 401) handleSessionExpiry('member');
    else {
      status(message, error.message, true);
      retry.hidden = false;
    }
  } finally {
    if (request === generation) list.removeAttribute('aria-busy');
  }
}
next.addEventListener('click', () => {
  if (nextCursor) {
    history.push(cursor);
    cursor = nextCursor;
    void load(true);
  }
});
previous.addEventListener('click', () => {
  cursor = history.pop() || '';
  void load(true);
});
retry.addEventListener('click', () => {
  void load(true);
});
document.addEventListener('member-favorite-change', () => {
  cursor = '';
  history = [];
  void load(true);
});
window.addEventListener('pageshow', event => {
  if (event.persisted) void load();
});
window.addEventListener('focus', () => {
  void load();
});
document.addEventListener('visibilitychange', () => {
  if (!document.hidden) void load();
});
void load();
