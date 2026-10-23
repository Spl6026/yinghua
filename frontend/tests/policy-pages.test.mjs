import test from 'node:test';
import assert from 'node:assert/strict';
import {renderPolicyVersion} from '../scripts/policy-pages.mjs';

test('條款版本只替換指定標記，惡意文字保持文字而非標籤或模板',()=>{
  assert.equal(renderPolicyVersion('<p>{{TERMS_VERSION}}</p>{{SITE_HEADER}}','v"<&'),'<p>v&quot;&lt;&amp;</p>{{SITE_HEADER}}');
  assert.equal(renderPolicyVersion('{{TERMS_VERSION}}/{{TERMS_VERSION}}'), 'local-development-v1/local-development-v1');
});
