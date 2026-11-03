import test from 'node:test';
import assert from 'node:assert/strict';
import { mustReplace } from '../scripts/html-edit.mjs';

test('找到標記時與一般 replace 結果相同', () => {
  assert.equal(mustReplace('<main></main>', '</main>', '<p>x</p></main>'), '<main><p>x</p></main>');
  assert.equal(
    mustReplace('<b>1</b><b>2</b>', /<b>(\d)<\/b>/g, (_, n) => `<i>${n}</i>`),
    '<i>1</i><i>2</i>'
  );
});

test('原型標記改掉時中止發布，不靜靜地產生缺內容的頁面', () => {
  assert.throws(
    () => mustReplace('<div class="route-list"></div>', /<div class="route-cards" id="route-cards">/, ''),
    /missing the expected markup/
  );
  assert.throws(() => mustReplace('<body>', '</main>', ''), /<\/main>/);
});
