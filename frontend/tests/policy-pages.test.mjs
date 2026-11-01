import test from 'node:test';
import assert from 'node:assert/strict';
import {renderPolicyVersion} from '../scripts/policy-pages.mjs';

test('條款版本只替換指定標記，惡意文字保持文字而非標籤或模板',()=>{
  assert.equal(renderPolicyVersion('<p>{{TERMS_VERSION}}</p>{{SITE_HEADER}}','v"<&'),'<p>v&quot;&lt;&amp;</p>{{SITE_HEADER}}');
  assert.equal(renderPolicyVersion('{{TERMS_VERSION}}/{{TERMS_VERSION}}'), 'local-development-v1/local-development-v1');
});

test('發布政策保留換行並轉義標籤，版本各自顯示，空白保留既有說明',()=>{
  const source='<article data-policy-kind="terms"><span data-policy-version>{{TERMS_VERSION}}</span><!-- POLICY_CONTENT_START --><p>fallback</p><!-- POLICY_CONTENT_END --></article>';
  const html=renderPolicyVersion(source,'consent-hash',{termsVersion:'v1',termsText:'第一行\n<script>bad</script>'});
  assert.ok(html.includes('<span data-policy-version>v1</span>'));
  assert.ok(html.includes('<p>第一行</p><p>&lt;script&gt;bad&lt;/script&gt;</p>'));
  assert.ok(!html.includes('<script>'));
  assert.ok(renderPolicyVersion(source,'fallback',{termsText:''}).includes('<p>fallback</p>'));
});

test('政策中的工程模板文字保留為文字，不展開網站模板',()=>{
  const source='<article data-policy-kind="privacy"><span data-policy-version>{{TERMS_VERSION}}</span><!-- POLICY_CONTENT_START -->fallback<!-- POLICY_CONTENT_END --></article>';
  const html=renderPolicyVersion(source,'hash',{privacyText:'{{SITE_HEADER}} <img src=x>',privacyVersion:'v2'});
  assert.ok(html.includes('<p>{{SITE_HEADER}} &lt;img src=x&gt;</p>'));
});
