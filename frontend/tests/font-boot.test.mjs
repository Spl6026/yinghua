import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFile } from 'node:fs/promises';
const source = await readFile(new URL('../assets/js/font-boot.js', import.meta.url), 'utf8');
function boot(load) {
  const classes = [];
  let paint;
  vm.runInNewContext(source, {
    document: { documentElement: { classList: { add: value => classes.push(value) } }, fonts: { load } },
    window: {
      requestAnimationFrame: callback => {
        paint = callback;
      },
    },
  });
  return { classes, paint: () => paint() };
}
test('晚到的字型不會在首幀後改變本頁字型', async () => {
  let complete;
  const pending = new Promise(resolve => {
    complete = resolve;
  });
  const page = boot(() => pending);
  page.paint();
  complete([]);
  await pending;
  await Promise.resolve();
  await Promise.resolve();
  assert.deepEqual(page.classes, []);
});
test('首幀前已就緒才使用自訂字型', async () => {
  const page = boot(() => Promise.resolve([]));
  await new Promise(setImmediate);
  page.paint();
  assert.deepEqual(page.classes, ['fonts-ready']);
});
test('字型載入失敗維持可見的系統字型', async () => {
  const page = boot(() => Promise.reject(new Error('unavailable')));
  await new Promise(setImmediate);
  page.paint();
  assert.deepEqual(page.classes, []);
});
