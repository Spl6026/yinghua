import { readFile } from 'node:fs/promises';
import { join } from 'node:path';
import { offerCards, offerDetail } from '../assets/js/core/offer-view.js';
import { contentPager, pageHref, jsonForHtml } from '../assets/js/core/content-view.js';

// 預設的有效優惠列表與仍在發布中的歷史詳情頁分開處理。
export async function publishOffers(source, snapshot, page) {
  const template = await readFile(join(source, 'pages/offers.html'), 'utf8');
  const items = snapshot.offers.filter(item => item.active);
  for (let current = 1; current <= Math.max(1, Math.ceil(items.length / 20)); current++) {
    const data = {
      items: items.slice((current - 1) * 20, current * 20),
      total: items.length,
      page: current,
      pageSize: 20,
      type: '',
      generation: snapshot.generation,
    };
    let html = template.replace(
      /(<div class="place-grid" id="offers-list"[^>]*>)[\s\S]*?<\/div>/,
      (_, open) => open + offerCards(data.items) + '</div>'
    );
    html = html.replace(
      '</main>',
      () =>
        `<div class="section-pad public-content-controls">${contentPager(data.total, current, '/offers.html')}<p class="public-content-status" role="status" tabindex="-1"></p></div></main>`
    );
    html = html.replace(
      '</body>',
      () =>
        `<script type="application/json" id="public-offers-state">${jsonForHtml(data)}</script><script type="module" src="/assets/js/pages/offers.js"></script></body>`
    );
    await page(
      `/_content_pages/offers/active/${current}.html`,
      html,
      `店家優惠${current > 1 ? '・第 ' + current + ' 頁' : ''}｜映畫竹塹`,
      '查看新竹合作店家的有效優惠、使用期間與注意事項。',
      undefined,
      pageHref('/offers.html', '', current)
    );
  }
  const detailTemplate = await readFile(join(source, 'templates/offer-detail.html'), 'utf8');
  for (const item of snapshot.offers) {
    if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(item.slug)) throw new Error('Invalid offer slug');
    const html = detailTemplate.replace(
      /<article class="detail-layout" id="offer-detail-content">[\s\S]*?<\/article>/,
      () => offerDetail(item)
    );
    await page(
      item.url,
      html,
      item.seoTitle || `${item.title}｜店家優惠｜映畫竹塹`,
      item.seoDescription || item.description || item.discount,
      { ...item, category: '店家優惠' }
    );
  }
}
