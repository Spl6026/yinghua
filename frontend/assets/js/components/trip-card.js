import { element } from '../core/ui.js';
import { tripDateLabel, tripDetailUrl, tripEditUrl, tripTitle } from '../core/planner-links.js';

/**
 * 行程總覽的一張卡片：名稱連到行程內容，另有編輯連結。
 * 有待加入的地點或待套用的推薦行程時，主要動作改為「加入／套用到這份行程」。
 */
export function tripCard(trip, intent = {}) {
  const card = element('article', { className: 'trip-card' }),
    heading = element('h3'),
    link = element('a', { href: tripDetailUrl(trip.id) }, tripTitle(trip));
  heading.append(link);
  const meta = element('p', { className: 'trip-card-meta' });
  meta.append(
    element('span', {}, tripDateLabel(trip)),
    element('span', {}, trip.stopCount ? `${trip.stopCount} 個地點` : '尚未加入地點')
  );
  card.append(element('p', { className: 'eyebrow' }, trip.isCurrent ? '目前行程' : 'MY TRIP'), heading, meta);
  // 預覽前幾站，讓使用者不必點進去就認得是哪一份；超過的以「…」表示還有更多。
  const preview = trip.stopPreview || [];
  if (preview.length)
    card.append(
      element(
        'p',
        { className: 'trip-card-stops' },
        preview.join(' → ') + (trip.stopCount > preview.length ? ' → …' : '')
      )
    );
  const actions = element('div', { className: 'trip-card-actions' });
  if (intent.place || intent.route)
    actions.append(
      element(
        'a',
        { className: 'button button-dark', href: tripEditUrl(trip.id, intent) },
        intent.place ? '加入這份行程 ↗' : '套用到這份行程 ↗'
      )
    );
  else
    actions.append(
      element('a', { className: 'button button-dark', href: tripDetailUrl(trip.id) }, '查看行程 ↗'),
      element(
        'a',
        { className: 'text-link', href: tripEditUrl(trip.id), 'aria-label': `編輯「${tripTitle(trip)}」` },
        '編輯'
      )
    );
  card.append(actions);
  return card;
}
