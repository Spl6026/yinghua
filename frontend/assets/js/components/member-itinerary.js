import { element } from '../core/ui.js';

// [D：C20；E-27] 私有快照以文字輸出；下架後不從草稿重建地點資訊。
export function memberItineraryStop(item, index, disabled = false) {
  const node = element('article', {className:'itinerary-stop'});
  const info = element('div', {className:'stop-info'});
  const current = item.attraction;
  const availability = item.availability === 'unknown' ? '資訊更新中' : '目前無法瀏覽';
  const area = current ? [current.district,current.typeLabel || current.category].filter(Boolean).join('・') : availability;
  info.append(element('span', {}, `${item.plannedTime || '待安排'}　・　${area}`), element('strong', {}, current?.name || item.snapshotName));
  if (current?.summary) info.append(element('p', {}, current.summary));
  const actions = element('div', {className:'stop-actions'});
  const remove = element('button', {type:'button','data-remove-stop':item.attractionId,'aria-label':`從行程移除${current?.name || item.snapshotName}`}, '×');
  remove.disabled = disabled; actions.append(remove);
  node.append(element('span', {className:'stop-number'}, String(index + 1).padStart(2,'0')), info, actions);
  return node;
}
