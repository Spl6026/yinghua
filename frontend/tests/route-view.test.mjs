import test from 'node:test';
import assert from 'node:assert/strict';
import { routeCards } from '../assets/js/core/route-view.js';

test('路線卡片空狀態與原型語意相同', () => {
  assert.match(routeCards([]), /目前沒有推薦行程/);
});
test('路線順序與站點順序保留且安全編碼', () => {
  const html = routeCards([
    {
      id: 'a',
      title: '<img src=x>',
      theme: '散步',
      summary: '<script>\n介紹',
      durationMinutes: 120,
      stops: [
        { name: '第一站', url: '/museum.html', suggestedTime: '10:30', note: '<b>' },
        { name: '第二站', url: '/places/test.html' },
      ],
    },
    { id: 'b', title: '另一條', stops: [] },
  ]);
  assert.ok(html.indexOf('&lt;img') < html.indexOf('另一條'));
  assert.ok(html.indexOf('第一站') < html.indexOf('第二站'));
  assert.ok(!html.includes('<script>'));
  assert.match(html, /&lt;b&gt;/);
  assert.match(html, /120 分鐘/);
  assert.match(html, /href="\/planner.html\?route=a"/);
  assert.match(html, /套用這條路線/);
});
