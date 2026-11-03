// 網站導覽與其他固定公開頁；保護頁與以模板產生的頁面不在這裡處理。
import { readFile } from 'node:fs/promises';
import { join } from 'node:path';
import { mustReplace } from './html-edit.mjs';
import { renderPublicContentIndex } from './public-content-index.mjs';

export async function publishStaticPages(source, snapshot, page, seoCatalog) {
  const indexTemplate = await readFile(join(source, 'pages/site-map.html'), 'utf8');
  await page(
    '/site-map.html',
    mustReplace(
      indexTemplate,
      '<div id="public-content-index"></div>',
      () => `<div id="public-content-index">${renderPublicContentIndex(snapshot)}</div>`
    ),
    '網站導覽｜映畫竹塹',
    '依分類瀏覽或按名稱尋找映畫竹塹的景點、電影、活動消息與店家優惠。',
    undefined,
    '/site-map.html',
    false
  );
  // 非內容型的固定公開頁也放進同一個 generation；保護頁使用安全的靜態 shell。
  const managedPaths = new Set([
    '/index.html',
    '/explore.html',
    '/food.html',
    '/museum.html',
    '/cinema.html',
    '/events.html',
    '/activities.html',
    '/city-news.html',
    '/offers.html',
    '/routes.html',
    '/site-map.html',
  ]);
  for (const entry of seoCatalog.filter(
    entry => !entry.protected && !entry.template && !managedPaths.has(entry.path)
  )) {
    let html;
    try {
      html = await readFile(join(source, 'pages', entry.path.slice(1)), 'utf8');
    } catch (error) {
      if (error.code === 'ENOENT') continue;
      throw error;
    }
    await page(entry.path, html, entry.title, entry.description, undefined, entry.path, false);
  }
}
