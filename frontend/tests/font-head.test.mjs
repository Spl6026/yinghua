import test from 'node:test';
import assert from 'node:assert/strict';
import {renderFontHead} from '../scripts/font-head.mjs';

test('內文與標題字型及font-face樣式都在主樣式前出現，charset仍居前',()=>{
  const html=renderFontHead('<html><head><meta charset="utf-8"><link rel="stylesheet" href="/assets/css/style.css"></head><body>內容</body></html>');
  assert.ok(html.indexOf('charset=')<html.indexOf('rel="preload"'));
  for(const path of ['NotoSansTC-common.woff2','NotoSerifTC-common.woff2','/assets/css/fonts.css'])assert.ok(html.indexOf(path)<html.indexOf('/assets/css/style.css'));
  assert.ok(html.includes('<body>內容</body>'));
});
test('重複產頁不重複插入字型連結，舊單一預載會被更新',()=>{
  const source='<head><meta charset="utf-8"><link rel="stylesheet" href="/assets/css/style.css"><link rel="preload" href="/assets/fonts/NotoSansTC-common.woff2" as="font"></head>';
  const once=renderFontHead(source),twice=renderFontHead(once);
  assert.equal(twice,once);assert.equal((twice.match(/rel="preload"/g)||[]).length,2);assert.equal((twice.match(/href="\/assets\/css\/fonts.css"/g)||[]).length,1);
});
