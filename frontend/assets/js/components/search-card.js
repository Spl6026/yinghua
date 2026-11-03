import { element } from '../core/ui.js';
import { MUSEUM_PATH, searchResultKind } from '../core/content-kinds.js';

// 沿用原型 search-hit 結構的純呈現卡片，不載入示範資料陣列。
export function searchCard(item, label) {
  const card = element('article', { className: 'route-card search-hit' });
  card.append(
    element('p', { className: 'eyebrow' }, label),
    element('h3', {}, item.title),
    element('p', {}, item.summary)
  );
  const kind = searchResultKind(item.type);
  const valid =
    (kind && new RegExp(`^/${kind.segment}/[a-z0-9-]+\\.html$`).test(item.url)) ||
    (item.type === 'place' && item.url === MUSEUM_PATH);
  if (valid) {
    const link = element(
      'a',
      { className: 'text-link', href: item.url, 'aria-label': '閱讀詳情：' + item.title },
      '閱讀詳情 '
    );
    link.append(element('span', { 'aria-hidden': 'true' }, '↗'));
    card.append(link);
  }
  return card;
}
