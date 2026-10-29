import test from 'node:test';
import assert from 'node:assert/strict';
import { annotateEnglishPassages } from '../scripts/language-passages.mjs';

test('已核對的介面英文只新增語言屬性，保留標籤及樣式', () => {
  assert.equal(annotateEnglishPassages('<p class="eyebrow">YOUR TRIP</p>'), '<p class="eyebrow" lang="en">YOUR TRIP</p>');
  assert.equal(annotateEnglishPassages('<p>CONTENT</p>'), '<p lang="en">CONTENT</p>');
  assert.equal(annotateEnglishPassages('<span>SCROLL TO DISCOVER　↓</span>'), '<span lang="en">SCROLL TO DISCOVER　↓</span>');
});
test('不覆蓋明確語言、不猜測使用者內容或混合語句', () => {
  const html='<p lang="fr">YOUR TRIP</p><p>Bonjour le monde</p><p>YOUR TRIP 你的旅程</p>';
  assert.equal(annotateEnglishPassages(html),html);
});
test('JSON、程式、CSS 及輸入內容中的 HTML 字串保持完整', () => {
  for(const tag of ['script','style','textarea','pre','code','title']) {
    const html=`<${tag}>"<p>YOUR TRIP</p>"</${tag}>`;
    assert.equal(annotateEnglishPassages(html),html);
  }
});
test('固定與公開頁重複處理時不重複加入屬性', () => {
  const html=annotateEnglishPassages('<span>WELCOME BACK</span>');
  assert.equal(annotateEnglishPassages(html),html);
});
