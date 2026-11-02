import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';

const source = (await readFile(new URL('../assets/js/components/content-cards.js', import.meta.url), 'utf8'))
  .replace(/^import\b[\s\S]*?;[ \t]*\r?\n/gm, '')
  .replace(/\bexport /g, '');
test('景點收藏控制不被上層 img 語意隱藏，且保留完整可讀名稱', () => {
  const sandbox = {
    element: (tag, attributes = {}, text) => ({
      tag,
      attrs: { ...attributes },
      text,
      style: {},
      children: [],
      setAttribute(key, value) {
        this.attrs[key] = value;
      },
      append(...nodes) {
        for (const node of nodes) {
          node.parent = this;
          this.children.push(node);
        }
      },
    }),
    detailLinks: {},
  };
  vm.createContext(sandbox);
  vm.runInContext(source, sandbox);
  const card = sandbox.placeCard({
    id: 'a',
    name: '景點甲',
    image: '/example.png',
    url: '/places/a.html',
    hours: '',
    rating: '',
  });
  const descendants = node => [node, ...node.children.flatMap(descendants)];
  const favorite = descendants(card).find(node => node.attrs['data-favorite']);
  assert.equal(favorite.attrs['aria-label'], '收藏景點甲');
  for (let parent = favorite.parent; parent; parent = parent.parent)
    assert.notEqual(parent.attrs.role, 'img');
  assert.ok(
    descendants(card).some(node => node.text === '◷ 依店家公告'),
    '選填營業時間留空時仍提供可讀提示'
  );
});
