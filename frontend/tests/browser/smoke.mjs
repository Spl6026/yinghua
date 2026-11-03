// 瀏覽器冒煙測試：以真的瀏覽器開啟公開、會員與後台頁面，確認沒有主控台錯誤、模組沒有重複載入、
// 手機寬度沒有橫向捲動，並實際操作後台編輯表單與會員行程。由 backend/tests/browser_smoke.py 建立
// 隔離帳號後執行；只連 localhost:8081 的測試站，不碰開發站資料。
import { chromium } from 'playwright';
import { mkdir, readdir, writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';

const base = process.env.SMOKE_BASE_URL || 'http://localhost:8081';
const admin = process.env.SMOKE_ADMIN;
const member = process.env.SMOKE_MEMBER;
const password = process.env.SMOKE_PASSWORD;
const artifacts = process.env.SMOKE_ARTIFACTS || 'artifacts/browser-smoke';
if (!admin || !member || !password)
  throw new Error('請由 backend/tests/browser_smoke.py 執行，以取得隔離帳號。');
if (!/^http:\/\/(localhost|127\.0\.0\.1):8081$/.test(base))
  throw new Error('冒煙測試只允許連到隔離測試站 8081。');

const publicPages = [
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
  '/search.html?q=%E6%96%B0%E7%AB%B9',
  '/login.html',
  '/register.html',
];
const memberPages = ['/planner.html', '/favorites.html', '/change-password.html'];
const adminDirectory = fileURLToPath(new URL('../../pages/admin/', import.meta.url));
const adminPages = (await readdir(adminDirectory))
  .filter(
    name =>
      name.endsWith('.html') && !['login.html', 'forgot-password.html', 'reset-password.html'].includes(name)
  )
  .map(name => '/admin/' + name);

const results = [];
const failures = [];
function record(name, ok, detail = '') {
  results.push({ name, ok, ...(detail ? { detail } : {}) });
  if (!ok) failures.push(name + (detail ? '：' + detail : ''));
}

// 收集頁面錯誤與主控台錯誤；權限不足或找不到資源（401／403／404）是頁面預期處理的情況，不算錯誤。
function watch(page) {
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  page.on('console', message => {
    if (message.type() === 'error' && !/status of 40[134]/.test(message.text())) errors.push(message.text());
  });
  const modules = [];
  page.on('request', request => {
    const url = new URL(request.url());
    if (url.pathname.startsWith('/assets/js/')) modules.push(url.pathname + url.search);
  });
  return {
    errors,
    take() {
      const taken = { errors: errors.splice(0), modules: modules.splice(0) };
      return taken;
    },
  };
}
// 同一模組以不同版本參數載入兩次，代表建置與發布的資產版本不一致，會造成重複執行。
function duplicateModules(modules) {
  const versions = new Map();
  for (const entry of modules) {
    const [path, query = ''] = entry.split('?');
    versions.set(path, new Set([...(versions.get(path) || []), query]));
  }
  return [...versions].filter(([, set]) => set.size > 1).map(([path]) => path);
}

async function visit(page, monitor, path, label) {
  await page.goto(base + path, { waitUntil: 'networkidle' });
  await page.waitForTimeout(300);
  const { errors, modules } = monitor.take();
  const duplicates = duplicateModules(modules);
  record(
    `${label} ${path} 無錯誤`,
    errors.length === 0 && duplicates.length === 0,
    [...errors, ...duplicates].join('；')
  );
}
async function noHorizontalScroll(page, path, label) {
  await page.goto(base + path, { waitUntil: 'networkidle' });
  await page.waitForTimeout(300);
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - innerWidth);
  record(`${label} ${path} 手機寬度無橫向捲動`, overflow <= 1, overflow > 1 ? `超出 ${overflow}px` : '');
}
async function login(page, path, account, field) {
  await page.goto(base + path, { waitUntil: 'networkidle' });
  await page.fill(field, account);
  await page.fill('#password', password);
  await page.click('button[type=submit]');
  await page.waitForURL(url => !url.pathname.endsWith('/login.html'), { timeout: 15000 });
}
// 以頁面內的 click() 操作，避免 Playwright 為了點擊而自行捲動，才能量測頁面本身是否捲動。
const clickInPage = (page, selector) => page.evaluate(s => document.querySelector(s).click(), selector);

const browser = await chromium.launch();
try {
  await mkdir(artifacts, { recursive: true });

  // 公開頁：桌面無錯誤，手機無橫向捲動。
  {
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await context.newPage();
    const monitor = watch(page);
    for (const path of publicPages) await visit(page, monitor, path, '公開頁');
    await page.setViewportSize({ width: 390, height: 844 });
    for (const path of publicPages) await noHorizontalScroll(page, path, '公開頁');
    await context.close();
  }

  // 後台：所有頁面無錯誤，四種編輯表單載入後可編輯，有修改時重新載入要先確認。
  {
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await context.newPage();
    const monitor = watch(page);
    await login(page, '/admin/login.html', admin, '#account');
    monitor.take();
    for (const path of adminPages) await visit(page, monitor, path, '後台');
    const forms = [
      ['景點', '/api/v1/admin/attractions', 'place-edit', '#place-form', 'name', '#reload-place'],
      ['電影', '/api/v1/admin/films', 'film-edit', '#film-form', 'title', '#reload-film'],
      ['消息', '/api/v1/admin/news', 'news-edit', '#content-form', 'title', '#reload-content-form'],
    ];
    for (const [label, api, editPage, form, field, reload] of forms) {
      const list = await page.evaluate(async url => (await fetch(url + '?page=1&pageSize=1')).json(), api);
      const item = list.items?.[0];
      if (!item) {
        record(`後台${label}編輯表單`, false, '沒有可編輯的資料');
        continue;
      }
      await page.goto(`${base}/admin/${editPage}.html?id=${item.id}`, { waitUntil: 'networkidle' });
      await page.waitForTimeout(300);
      const editable = await page.evaluate(s => !document.querySelector(s + ' [type=submit]').disabled, form);
      const input = page.locator(`${form} [name="${field}"]`);
      const original = await input.inputValue();
      await input.fill(original + ' ');
      await page.click(reload);
      await page.waitForTimeout(200);
      const asked = await page.evaluate(() => Boolean(document.querySelector('dialog[open]')));
      if (asked) await page.keyboard.press('Escape');
      await input.fill(original);
      record(`後台${label}編輯表單可編輯且重新載入前確認`, editable && asked);
      const { errors } = monitor.take();
      record(`後台${label}編輯表單無錯誤`, errors.length === 0, errors.join('；'));
    }
    await context.close();
  }

  // 會員：行程總覽 → 新增 → 編輯加入地點並儲存（畫面不捲動）→ 行程內容 → 刪除。
  {
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await context.newPage();
    const monitor = watch(page);
    await login(page, '/login.html', member, '#account, #email');
    monitor.take();
    for (const path of memberPages) await visit(page, monitor, path, '會員頁');
    await page.goto(base + '/planner.html', { waitUntil: 'networkidle' });
    await page.click('#overview-new-trip');
    await page.waitForURL(/edit=/);
    await page.waitForTimeout(500);
    const tripId = new URL(page.url()).searchParams.get('edit');
    await page.fill('#trip-title', '冒煙測試行程');
    await page.click('#add-stop');
    await page.waitForTimeout(800);
    for (let i = 0; i < 2; i++) {
      await page.locator('#trip-picker-host button:has-text("加入行程")').first().click();
      await page.waitForTimeout(250);
    }
    // 從頁首儲存：狀態訊息此時在畫面外，若程式把焦點移過去，畫面就會跳動。
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.waitForTimeout(200);
    const offscreen = await page.evaluate(
      () => document.querySelector('#trip-status').getBoundingClientRect().top > innerHeight
    );
    record('儲存前狀態訊息在畫面外（捲動檢查的前提）', offscreen);
    const before = await page.evaluate(() => scrollY);
    await clickInPage(page, '#save-trip');
    await page.waitForTimeout(1500);
    const after = await page.evaluate(() => scrollY);
    record('行程儲存成功', /已儲存/.test(await page.textContent('#trip-status')));
    record('行程儲存後畫面不捲動', before === after, `scrollY ${before} → ${after}`);
    // 無頭瀏覽器對程式聚焦不一定捲動，所以另外直接檢查原因：儲存後焦點不能被移到狀態訊息。
    const focused = await page.evaluate(() => document.activeElement?.id || document.activeElement?.tagName);
    record('行程儲存後焦點不移到狀態訊息', focused !== 'trip-status', `焦點在 ${focused}`);
    await page.goto(`${base}/planner.html?trip=${tripId}`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(400);
    record('行程內容顯示兩個地點', (await page.locator('#trip-detail-stops li').count()) === 2);
    await page.screenshot({ path: `${artifacts}/planner-detail.png`, fullPage: true });
    await page.setViewportSize({ width: 390, height: 844 });
    for (const path of ['/planner.html', `/planner.html?trip=${tripId}`, `/planner.html?edit=${tripId}`])
      await noHorizontalScroll(page, path, '會員頁');
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto(`${base}/planner.html?trip=${tripId}`, { waitUntil: 'networkidle' });
    await page.click('#trip-detail-delete');
    await page
      .locator('dialog[open] button', { hasText: /刪除|確認|確定/ })
      .last()
      .click();
    await page.waitForURL(url => url.search === '', { timeout: 10000 });
    await page.waitForTimeout(400);
    record('行程刪除後回到總覽', (await page.locator('.trip-card').count()) === 0);
    const { errors } = monitor.take();
    record('會員行程操作無錯誤', errors.length === 0, errors.join('；'));
    await context.close();
  }
} finally {
  await browser.close();
}

await writeFile(`${artifacts}/results.json`, JSON.stringify(results, null, 2));
console.log(JSON.stringify({ checks: results.length, failed: failures.length, failures }, null, 2));
if (failures.length) process.exitCode = 1;
