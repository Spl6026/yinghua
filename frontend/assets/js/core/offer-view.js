import {escapeHtml as e,contentImage} from './content-view.js';
export function offerCards(items) {
  return items.map(item=>{
    const merchant=item.merchant;
    const image=contentImage(item,'place-image').replace('</div>',()=>`<span class="place-type">${e(merchant.district)}・期間限定</span></div>`);
    return `<article class="place-card reveal in-view">${image}<div class="place-body"><span class="place-area">${e(merchant.name)}　/　優惠至 ${e(item.validTo||'依店家公告')}</span><h3>${e(item.title)}</h3><p>${e(item.description)}</p><div class="place-details"><strong>${e(item.discount)}</strong><span class="place-actions"><a href="${e(item.url)}">優惠詳情 ↗</a></span></div></div></article>`;
  }).join('') || '<p class="empty-results">目前沒有上架中的店家優惠。</p>';
}
export function offerDetail(item) {
  const merchant=item.merchant;
  const period=item.validFrom&&item.validTo?`${item.validFrom} 至 ${item.validTo}`:item.validFrom?`${item.validFrom} 起，截止依店家公告`:item.validTo?`截止 ${item.validTo}`:'依店家公告';
  const state=item.validity==='expired'?'這項優惠已結束，內容僅供參考。':item.validity==='upcoming'?'這項優惠尚未開始，請留意開始日期。':'';
  return `<article class="detail-layout" id="offer-detail-content">${contentImage(item,'detail-cover')}<div class="detail-body"><p class="eyebrow">LOCAL OFFER / ${e(merchant.district)}</p><h1>${e(item.title)}</h1><p class="detail-lead">${e(item.discount)}</p>${state?`<p class="offer-validity-note" role="status">${e(state)}</p>`:''}<p>${e(item.description).replaceAll('\n','<br>')}</p><p>合作店家：${e(merchant.name)}<br>優惠期間：${e(period)}</p><div class="detail-meta">優惠使用方式與注意事項：${e(item.terms).replaceAll('\n','<br>')}</div><div class="detail-actions"><a class="button button-dark" href="/offers.html">回到店家優惠 ↗</a><a class="text-link" href="/explore.html">探索更多新竹店家 →</a></div></div></article>`;
}
