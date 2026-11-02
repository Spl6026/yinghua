import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { addModulePreloads, moduleGraph } from '../scripts/module-preload.mjs';

function fixture() {
  const root = mkdtempSync(join(tmpdir(), 'preload-'));
  mkdirSync(join(root, 'js/core'), { recursive: true });
  writeFileSync(
    join(root, 'js/app.js'),
    "import {\n  a,\n  b,\n} from './core/a.js';\nimport './core/side.js';\nconst lazy = () => import('./core/lazy.js');\n"
  );
  writeFileSync(
    join(root, 'js/core/a.js'),
    "export { c } from './c.js';\nimport { routes } from './generated.js';\nexport function a() {}\nexport const b = 1;\n"
  );
  writeFileSync(join(root, 'js/core/c.js'), "import { a } from './a.js';\nexport const c = a;\n");
  writeFileSync(join(root, 'js/core/side.js'), 'console.log(1);\n');
  writeFileSync(join(root, 'js/core/lazy.js'), 'export default 1;\n');
  return root;
}

test('展開多行、re-export 與副作用 import，循環相依只列一次，建置時才產生的模組仍預載，動態 import 不預載', () => {
  const graph = moduleGraph('/assets/js/app.js', fixture());
  assert.deepEqual(graph, [
    '/assets/js/app.js',
    '/assets/js/core/a.js',
    '/assets/js/core/c.js',
    '/assets/js/core/generated.js',
    '/assets/js/core/side.js',
  ]);
});

test('每個模組入口加入 modulepreload，重複執行不重複加入', () => {
  const root = fixture();
  const html =
    '<html><head><title>x</title></head><body><script type="module" src="/assets/js/app.js"></script></body></html>';
  const once = addModulePreloads(html, root);
  assert.equal((once.match(/rel="modulepreload"/g) || []).length, 5);
  assert.ok(once.indexOf('modulepreload') < once.indexOf('</head>'));
  assert.equal(addModulePreloads(once, root), once);
  assert.equal(
    addModulePreloads('<head></head><script src="/x.js"></script>', root),
    '<head></head><script src="/x.js"></script>'
  );
});
