import {escapeHtml as e,contentDate,contentImage} from './content-view.js';
export const newsLabel = type => type === 'event' ? '活動' : '城市消息';
export function newsCards(items) {
  return items.map(item=>{
    // [U-05] 保留原型卡片 YYYY / MM.DD 的日期排版，日期仍依台北時區。
    const parts=Object.fromEntries(new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Taipei',year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(new Date(item.publishAt)).map(part=>[part.type,part.value]));
    const date=`${parts.year} / ${parts.month}.${parts.day}`;
    const image=contentImage(item,'news-card-image','linear-gradient(0deg,rgba(22,28,22,.18),rgba(22,28,22,.02)),').replace('</div>',()=>`<span class="news-card-tag">${e(newsLabel(item.type))}</span></div>`);
    return `<a class="news-item news-card reveal in-view" href="${e(item.url)}" target="_blank" rel="noopener noreferrer" aria-label="閱讀文章：${e(item.title)}（另開新分頁）">${image}<div class="news-card-body"><div class="news-item-top"><span>新竹現場</span><time datetime="${e(item.publishAt)}">${e(date)}</time></div><h3>${e(item.title)}</h3><p>${e(item.summary)}</p><div class="news-card-footer"><span>發布日期　${e(date)}</span><strong>閱讀文章 <span aria-hidden="true">↗</span></strong></div></div></a>`;
  }).join('') || '<p class="empty-results">目前沒有符合分類的消息。</p>';
}
export function newsFeatured(item) {
  if (!item) return '<p class="empty-results">目前沒有已發布的推薦消息。</p>';
  return `<div class="news-feature">${contentImage(item,'news-feature-photo')}<div><span class="news-tag">推薦・${e(newsLabel(item.type))}</span><h3>${e(item.title)}</h3><p>${e(item.summary)}</p><a class="text-link" href="${e(item.url)}" target="_blank" rel="noopener noreferrer" aria-label="閱讀${e(item.title)}（另開新分頁）">閱讀${item.type==='event'?'活動':'消息'}資訊 <span>→</span></a></div></div>`;
}
export function newsDetail(item) {
  const dates=[item.eventStartAt&&`開始：${contentDate(item.eventStartAt,{hour:'2-digit',minute:'2-digit',hourCycle:'h23'})}`,item.eventEndAt&&`結束：${contentDate(item.eventEndAt,{hour:'2-digit',minute:'2-digit',hourCycle:'h23'})}`].filter(Boolean);
  // body 只接受後端 Service 淨化後的 HTML，其餘欄位一律當作文字編碼。
  return `<section class="section-pad detail-page">${contentImage(item,'detail-cover')}<div class="detail-body"><p class="eyebrow">CITY STORY / DETAIL</p><h1>${e(item.title)}</h1><p class="detail-lead">${e(item.summary)}</p><div class="public-article-body">${item.body || ''}</div>${dates.map(d=>`<p>${e(d)}</p>`).join('')}${item.eventLocation?`<p>活動地點：${e(item.eventLocation)}</p>`:''}<div class="detail-meta">${e(newsLabel(item.type))}・發布日期 <time datetime="${e(item.publishAt)}">${e(contentDate(item.publishAt))}</time></div><div class="detail-actions"><a class="button button-dark" href="/events.html">回到新竹現場 ↗</a><a class="text-link" href="/planner.html">安排進旅行 <span>→</span></a></div></div></section>`;
}
// [S：E9/E30；E-29] 發布 HTML 與瀏覽器分類／分頁共用主題文字。
export function newsListTitle(path,type,page=1){
  return (path==='/events.html'?(type?newsLabel(type):'新竹現場'):path==='/activities.html'?'新竹活動':'新竹消息')+(page>1?'・第 '+page+' 頁':'')+'｜映畫竹塹';
}
export function newsListDescription(path,type,page=1){
  return `${newsListTitle(path,type,page)}，瀏覽已發布的活動與城市消息。`;
}
