// 推薦行程列表頁：只在快照帶有推薦行程時產生。
import { readFile } from 'node:fs/promises';
import { join } from 'node:path';
import { routeCards } from '../assets/js/core/route-view.js';
import { mustReplace } from './html-edit.mjs';

export async function publishRoutes(source, snapshot, page) {
  if (Array.isArray(snapshot.recommendedRoutes)) {
    let html = await readFile(join(source, 'pages/routes.html'), 'utf8');
    html = mustReplace(
      html,
      /<div class="route-cards" id="route-cards">\s*<\/div>/,
      () => '<div class="route-cards" id="route-cards">' + routeCards(snapshot.recommendedRoutes) + '</div>'
    );
    await page(
      '/routes.html',
      html,
      '推薦行程｜映畫竹塹',
      '參考新竹主題散步推薦行程，查看有序景點與預估時間。'
    );
  }
}
