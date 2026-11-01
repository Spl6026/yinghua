import test from 'node:test';
import assert from 'node:assert/strict';
import {homeErrorView} from '../scripts/home-error-view.mjs';

test('首頁發布失敗逐區塊呈現錯誤／重試，不殘留舊內容或可索引狀態',()=>{
  const shell='<html><head><title>原標題</title><meta name="robots" content="index,follow"><script type="application/ld+json">{"name":"舊資料"}</script></head><body><header>原導覽</header><main id="main-content"><article>原型示範資料</article></main><footer>原頁尾</footer></body></html>';
  const result=homeErrorView(shell);
  for(const name of ['館舍介紹','精選景點','本週映演','最新活動與消息'])assert.ok(result.includes(`${name}暫時無法載入`));
  for(const path of ['/index.html','/museum.html','/explore.html','/cinema.html','/events.html'])assert.ok(result.includes(`href="${path}"`));
  assert.match(result,/role="alert"/);assert.match(result,/noindex,nofollow/);assert.match(result,/原導覽/);assert.match(result,/原頁尾/);
  assert.doesNotMatch(result,/原型示範資料|舊資料|index,follow|application\/ld\+json/);
});
