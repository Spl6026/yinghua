import test from 'node:test';
import assert from 'node:assert/strict';
import { loadWithSandbox } from './support/sandbox-module.mjs';

class Node {
  constructor(tag) {
    this.tag = tag;
    this.children = [];
  }
  append(...nodes) {
    this.children.push(...nodes);
  }
  text() {
    return [this.textContent || '', ...this.children.map(child => child.text())].join(' ');
  }
}
const sandbox = {
  element: (tag, attrs = {}, text) => Object.assign(new Node(tag), attrs, { textContent: text || '' }),
};
const { tripCard } = await loadWithSandbox('components/trip-card.js', sandbox);
const trip = { id: 't1', title: '週末散步', tripDate: '2026-11-08', isCurrent: false, stopCount: 5 };

test('總覽卡片依順序預覽前幾站，還有更多時以 … 表示', () => {
  const card = tripCard({ ...trip, stopPreview: ['東門市場', '影像博物館', '護城河'] });
  const stops = card.children.find(node => node.className === 'trip-card-stops');
  assert.equal(stops.textContent, '東門市場 → 影像博物館 → 護城河 → …');
});

test('站數不超過預覽數量時不加 …；沒有站點時不顯示預覽', () => {
  const short = tripCard({ ...trip, stopCount: 2, stopPreview: ['東門市場', '影像博物館'] });
  assert.equal(
    short.children.find(node => node.className === 'trip-card-stops').textContent,
    '東門市場 → 影像博物館'
  );
  const empty = tripCard({ ...trip, stopCount: 0, stopPreview: [] });
  assert.equal(
    empty.children.some(node => node.className === 'trip-card-stops'),
    false
  );
});
