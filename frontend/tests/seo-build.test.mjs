import test from 'node:test';
import assert from 'node:assert/strict';
import {cp,mkdtemp,readFile,rm} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import {spawnSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';

test('實際固定頁建置使用運行 origin，保護頁不依賴發布且不允許索引',async()=>{
  const directory=await mkdtemp(join(tmpdir(),'spl-seo-build-'));const source=fileURLToPath(new URL('..',import.meta.url));
  try{
    for(const name of ['pages','assets','scripts','templates','resources'])await cp(join(source,name),join(directory,name),{recursive:true});
    const result=spawnSync(process.execPath,[join(directory,'scripts/build.mjs')],{encoding:'utf8',env:{...process.env,APP_URL:'https://deployment.example.test',TERMS_VERSION:'test-build-version'}});
    assert.equal(result.status,0,result.stderr);
    for(const page of ['admin/login.html','admin/settings.html','login.html','planner.html','favorites.html','search.html','reset-password.html']){
      const html=await readFile(join(directory,'dist',page),'utf8');
      assert.match(html,/<meta name="robots" content="noindex,(?:follow|nofollow)">/);
      assert.match(html,new RegExp('rel="canonical" href="https://deployment\\.example\\.test/'+page.replaceAll('.','\\.')+'"'));
      assert.doesNotMatch(html,/href="http:\/\/localhost/);
    }
    for(const page of ['terms.html','privacy.html','register.html']){
      const html=await readFile(join(directory,'dist',page),'utf8');
      assert.match(html,/test-build-version/);
      assert.doesNotMatch(html,/\{\{TERMS_VERSION\}\}/);
    }
    const register=await readFile(join(directory,'dist/register.html'),'utf8');
    assert.match(register,/href="\/terms.html" target="_blank" rel="noopener"/);
    assert.match(register,/href="\/privacy.html" target="_blank" rel="noopener"/);
  }finally{await rm(directory,{recursive:true,force:true});}
});
