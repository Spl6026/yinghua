// 景點卡片：沿用原始網站的卡片結構，供城市散策列表與我的收藏共用；資料一律以 textContent 輸出，避免插入 HTML。
import { element } from '../core/ui.js';

function imageBlock(className, image, label, gradient) {
  const node = element('div', { className, role: 'img', 'aria-label': label });
  node.style.backgroundImage = (gradient ? gradient + ',' : '') + `url(${JSON.stringify(image)})`;
  return node;
}

export function attractionCardData(item) {
  return {
    ...item,
    area: item.district,
    type: item.typeLabel || item.category,
    desc: item.summary,
    rating: item.recommendationLabel || '在地推薦',
  };
}

export function placeCard(item) {
  const card = element('article', { className: 'place-card reveal' });
  const image = imageBlock('place-image', item.image, item.name);
  // 圖片區內含收藏按鈕，所以用 role="group"；若用 img 語意會把互動子元素從無障礙樹隱藏。
  image.setAttribute('role', 'group');
  image.append(
    element('span', { className: 'place-type' }, item.type || item.category),
    element(
      'button',
      {
        type: 'button',
        className: 'favorite-button',
        'aria-label': '收藏' + item.name,
        'aria-pressed': 'false',
        'data-favorite': item.id,
      },
      '♡'
    )
  );
  const body = element('div', { className: 'place-body' });
  const details = element('div', { className: 'place-details' });
  const actions = element('span', { className: 'place-actions' });
  actions.append(
    element('a', { href: item.url }, '看介紹 ↗'),
    element('button', { type: 'button', 'data-add-stop': item.id }, '＋ 行程')
  );
  details.append(element('span', {}, '◷ ' + (item.hours || '依店家公告')), actions);
  // 選填內容留空時省略空段落與分隔符號，不自動補上介紹文字。
  body.append(
    element('span', { className: 'place-area' }, [item.area, item.rating].filter(Boolean).join('　/　')),
    element('h3', {}, item.name)
  );
  if (item.desc) body.append(element('p', {}, item.desc));
  body.append(details);
  card.append(image, body);
  return card;
}
