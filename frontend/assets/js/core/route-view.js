import {escapeHtml as e} from './content-view.js';

// [S E14/G14；U-05] 保留原型 route-card，補上實際有序站點及時間。
export function routeCards(items){
  if(!items.length)return '<p class="empty-results">目前沒有推薦行程。</p>';
  return items.map(route=>`<article class="route-card"><p class="eyebrow">${e(route.theme||'城市散策')}</p><h3>${e(route.title)}</h3>${route.summary?`<p>${e(route.summary).replaceAll('\n','<br>')}</p>`:''}${route.durationMinutes?`<p>預估時間：${e(String(route.durationMinutes))} 分鐘</p>`:''}<ol class="route-stop-links">${route.stops.map(stop=>`<li><a href="${e(stop.url)}">${e(stop.name)}</a>${stop.suggestedTime?` ${e(stop.suggestedTime)}`:''}${stop.note?`<p>${e(stop.note)}</p>`:''}</li>`).join('')}</ol><a class="button button-dark" href="/planner.html?route=${encodeURIComponent(route.id)}">套用這條路線 ↗</a></article>`).join('');
}
