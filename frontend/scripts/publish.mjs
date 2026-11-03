// 由 PHP CLI／API 提供已驗證的資料，HTML 只在前端產生。
// 每個 generation 都包含完整的公開資料與頁面，全部寫完後才以原子操作切換 current。
// 這裡只負責流程：讀入快照、依序呼叫各類內容的發布器，最後寫出索引並啟用版本。
// 發布順序決定 sitemap 的項目順序，調整時要一併確認索引輸出。
import { readFile, writeFile, mkdir, rename, symlink, rm, realpath } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { weeklyFilms, screeningPeriodLabel } from '../assets/js/core/film-view.js';
import { snapshotContent } from '../assets/js/core/content-kinds.js';
import { loadTemplates } from './page-pipeline.mjs';
import { buildSitemaps, robotsText } from './crawler-index.mjs';
import { assetVersion } from './asset-version.mjs';
import { createPagePublisher } from './publish-page.mjs';
import { publishAttractions } from './public-attractions.mjs';
import { publishFilms } from './public-films.mjs';
import { publishNews } from './public-news.mjs';
import { publishOffers } from './public-offers.mjs';
import { publishRoutes } from './public-routes.mjs';
import { publishStaticPages } from './public-static.mjs';
import { buildHomePage } from './home-page.mjs';

const source = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const output = resolve(process.argv[2] || '/var/www/publication');
let input = '';
for await (const chunk of process.stdin) {
  input += chunk;
  if (input.length > 32 * 1024 * 1024) throw new Error('Publication too large');
}
const snapshot = JSON.parse(input);
const seoCatalog = JSON.parse(await readFile(join(source, 'resources/seo-pages.json'), 'utf8'));
const publicImages = snapshotContent(snapshot)
  .flatMap(item => [item.image, ...(item.bodyImages || [])])
  .filter(Boolean);
if (
  !/^[a-f0-9]{64}$/.test(snapshot.generation) ||
  !Array.isArray(snapshot.items) ||
  !/^https?:\/\//.test(snapshot.origin)
)
  throw new Error('Invalid publication');
const filmItems = weeklyFilms(snapshot.films || [], snapshot.filmPeriod);
const weekLabel = snapshot.filmPeriod ? screeningPeriodLabel(snapshot.filmPeriod) : '';
const root = join(output, 'generations', snapshot.generation);
await mkdir(root, { recursive: true });
// 已完成的 generation 不覆寫，避免正在讀取的版本遭原地修改。
let existing = false;
try {
  existing =
    JSON.parse(await readFile(join(root, '.snapshot.json'), 'utf8')).generation === snapshot.generation;
} catch {}

if (!existing) {
  const { page, indexEntries } = createPagePublisher({
    source,
    snapshot,
    root,
    templates: await loadTemplates(source),
    seoCatalog,
    publicImages,
    version: assetVersion(source),
    filmItems,
  });
  const museum = snapshot.home ? snapshot.home.museum : snapshot.items.find(item => item.isMuseum);
  const featured = snapshot.home
    ? snapshot.home.featured
    : snapshot.items.find(item => item.featured) || museum;

  await publishAttractions(source, snapshot, page);
  if (Array.isArray(snapshot.films))
    await publishFilms(source, snapshot, page, { filmItems, weekLabel, museum });
  if (Array.isArray(snapshot.news)) await publishNews(source, snapshot, page);
  if (Array.isArray(snapshot.offers)) await publishOffers(source, snapshot, page);
  await publishRoutes(source, snapshot, page);
  // 與原型及後台 SEO 目錄使用同一個標題；舊字串以品牌開頭又被加上後綴，會讓「映畫竹塹」重複出現。
  await page(
    '/index.html',
    await buildHomePage(source, snapshot, { museum, featured, filmItems, weekLabel }),
    '在城市裡，遇見一場好電影｜映畫竹塹',
    '從新竹市影像博物館出發，探索本週電影、城市散策、在地美食、店家優惠、活動消息與新竹旅行提案。',
    featured
  );
  await publishStaticPages(source, snapshot, page, seoCatalog);

  // 索引與 HTML 全部成功才啟用此版本；不掃描 dist 或舊的 generation。
  const canonicalOrigin = snapshot.seo?.defaults?.canonicalOrigin || snapshot.origin;
  for (const [name, xml] of buildSitemaps(indexEntries, canonicalOrigin))
    await writeFile(join(root, name), xml);
  await writeFile(join(root, 'robots.txt'), robotsText(canonicalOrigin));
  // 寫完所有頁面最後才放快照，避免半成品被當成可重用版本。
  await writeFile(join(root, '.snapshot.json'), JSON.stringify(snapshot));
}
// 同一 volume 的 symlink rename 是啟用點；PHP 與 Nginx 都讀 current。
await realpath(root);
const temporary = join(output, `.current-${process.pid}`);
await rm(temporary, { force: true });
await symlink(root, temporary);
await rename(temporary, join(output, 'current'));
console.log('Publication activated.');
