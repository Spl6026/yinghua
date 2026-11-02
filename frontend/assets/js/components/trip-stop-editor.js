import { element } from '../core/ui.js';

// 站點卡片的值只透過 textContent／input.value 寫入，不插入使用者提供的 HTML。
export function tripStopEditor(item, index, total, { disabled, onMove, onRemove, onEdit }) {
  const name = item.attraction?.name || item.snapshotName,
    node = element('article', { className: 'itinerary-stop' }),
    info = element('div', { className: 'stop-info' });
  info.append(element('strong', {}, name));
  if (item.attraction) {
    info.append(
      element(
        'span',
        { className: 'trip-stop-meta' },
        [item.attraction.district, item.attraction.typeLabel || item.attraction.category]
          .filter(Boolean)
          .join('・')
      )
    );
    if (item.attraction.summary) info.append(element('p', {}, item.attraction.summary));
    info.append(
      element('a', { className: 'text-link trip-stop-detail', href: item.attraction.url }, '看介紹 ↗')
    );
  } else
    info.append(
      element(
        'p',
        {},
        item.availability === 'unknown'
          ? '公開資訊更新中；保留原站點。'
          : '地點目前不可用；保留加入時名稱，可移除或調整原站備註。'
      )
    );
  for (const [field, label, type] of [
    ['startTime', '安排時間（選填）', 'time'],
    ['note', '站點備註（選填）', 'textarea'],
  ]) {
    const id = `trip-${field}-${item.attractionId}`,
      group = element('div', { className: 'trip-stop-field' }),
      input = element(type === 'textarea' ? 'textarea' : 'input', {
        id,
        name: id,
        ...(type === 'textarea' ? { rows: '2', maxlength: '2000' } : { type: 'time' }),
        autocomplete: 'off',
      });
    input.value = item[field] || '';
    input.disabled = disabled;
    input.addEventListener('input', () => onEdit(field, input.value));
    group.append(element('label', { for: id }, label), input);
    info.append(group);
  }
  const actions = element('div', { className: 'stop-actions' });
  for (const [label, text, delta] of [
    ['上移', '↑ 上移', -1],
    ['下移', '↓ 下移', 1],
    ['移除', '移除', 0],
  ]) {
    const button = element('button', { type: 'button', 'aria-label': `將${name}${label}` }, text);
    button.disabled = disabled || (delta === -1 && index === 0) || (delta === 1 && index === total - 1);
    button.addEventListener('click', () => (delta ? onMove(index, delta) : onRemove(index)));
    actions.append(button);
  }
  node.append(
    element('span', { className: 'stop-number' }, String(index + 1).padStart(2, '0')),
    info,
    actions
  );
  return node;
}
