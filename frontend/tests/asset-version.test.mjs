import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtemp, mkdir, writeFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { assetVersion, versionHtmlAssets, versionModuleImports } from '../scripts/asset-version.mjs';

test('HTML 引用的 CSS、JS 與圖示加上版本號，字型與外部網址不動', () => {
  const html = [
    '<link rel="preload" href="/assets/fonts/NotoSansTC-common.woff2" as="font">',
    '<link rel="stylesheet" href="/assets/css/style.css">',
    '<link rel="modulepreload" href="/assets/js/core/ui.js">',
    '<script type="module" src="/assets/js/public.js"></script>',
    '<link rel="icon" href="/assets/favicon.svg">',
    '<a href="/explore.html">城市散策</a>',
    '<img src="https://example.test/a.js">',
  ].join('\n');
  const result = versionHtmlAssets(html, 'abc123def456');
  assert.match(result, /href="\/assets\/fonts\/NotoSansTC-common\.woff2"/);
  assert.match(result, /href="\/assets\/css\/style\.css\?v=abc123def456"/);
  assert.match(result, /href="\/assets\/js\/core\/ui\.js\?v=abc123def456"/);
  assert.match(result, /src="\/assets\/js\/public\.js\?v=abc123def456"/);
  assert.match(result, /href="\/assets\/favicon\.svg\?v=abc123def456"/);
  assert.match(result, /href="\/explore\.html"/);
  assert.match(result, /src="https:\/\/example\.test\/a\.js"/);
  // 重複套用不會疊加第二個版本號。
  assert.equal(versionHtmlAssets(result, 'abc123def456'), result);
});

test('模組的靜態、動態與副作用 import 都帶同一個版本號', () => {
  const source = [
    "import { ui } from './core/ui.js';",
    "export { api } from '../core/api-client.js';",
    "import './side-effect.js';",
    "const m = await import('../components/offer-merchants.js');",
    "const text = 'important ./not-an-import.js';",
  ].join('\n');
  const result = versionModuleImports(source, 'v1');
  assert.match(result, /from '\.\/core\/ui\.js\?v=v1'/);
  assert.match(result, /from '\.\.\/core\/api-client\.js\?v=v1'/);
  assert.match(result, /import '\.\/side-effect\.js\?v=v1'/);
  assert.match(result, /import\('\.\.\/components\/offer-merchants\.js\?v=v1'\)/);
  assert.match(result, /important \.\/not-an-import\.js'/);
});

test('版本號由資產內容與頁面清單決定，字型改動不影響', async () => {
  const root = await mkdtemp(join(tmpdir(), 'asset-version-'));
  try {
    await mkdir(join(root, 'assets/js'), { recursive: true });
    await mkdir(join(root, 'assets/fonts'), { recursive: true });
    await mkdir(join(root, 'scripts'));
    await mkdir(join(root, 'pages'));
    await writeFile(join(root, 'assets/js/a.js'), 'export const a = 1;');
    await writeFile(join(root, 'assets/fonts/x.woff2'), 'font');
    await writeFile(join(root, 'pages/index.html'), '<main></main>');
    const first = assetVersion(root);
    assert.match(first, /^[a-f0-9]{12}$/);
    assert.equal(assetVersion(root), first);
    await writeFile(join(root, 'assets/fonts/x.woff2'), 'font2');
    assert.equal(assetVersion(root), first);
    await writeFile(join(root, 'pages/index.html'), '<main>內容改了，頁面清單沒變</main>');
    assert.equal(assetVersion(root), first);
    await writeFile(join(root, 'pages/new.html'), '<main></main>');
    const withPage = assetVersion(root);
    assert.notEqual(withPage, first);
    await writeFile(join(root, 'assets/js/a.js'), 'export const a = 2;');
    assert.notEqual(assetVersion(root), withPage);
  } finally {
    await rm(root, { recursive: true, force: true });
  }
});
