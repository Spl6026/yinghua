import test from 'node:test';
import assert from 'node:assert/strict';
import {spawnSync} from 'node:child_process';
import {mkdtemp,readFile,rm} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import {offerCards,offerDetail} from '../assets/js/core/offer-view.js';

const script=new URL('../scripts/publish.mjs',import.meta.url);
const offer={id:'offer-test',slug:'offer-test',title:'優惠 $& <script>',description:'內容 </script>',discount:'九折',terms:'每人一次\n不得轉售',
  merchant:{id:'merchant-test',name:'店家 <b>',district:'北區'},validFrom:null,validTo:null,active:true,validity:'active',image:'',imageAlt:'',
  url:'/offers/offer-test.html',seoTitle:'',seoDescription:''};

test('優惠保留原型詳情殼、純文字條款與沒有日期時的公告提示',()=>{
  const html=offerDetail(offer);
  assert.match(html,/article class="detail-layout" id="offer-detail-content"/);
  assert.match(html,/優惠期間：依店家公告/);
  assert.match(html,/每人一次<br>不得轉售/);
  assert.match(html,/店家 &lt;b&gt;/);
  assert.doesNotMatch(html,/<script>|undefined|null|1970/);
  assert.match(offerCards([offer]),/優惠詳情/);
  assert.match(offerDetail({...offer,validity:'expired'}),/這項優惠已結束/);
  assert.match(offerDetail({...offer,validity:'upcoming'}),/這項優惠尚未開始/);
});

test('優惠深頁／獨立詳情與下架使用同一 generation，不重用原型資料',async()=>{
  const directory=await mkdtemp(join(tmpdir(),'spl-offers-'));
  const rows=Array.from({length:21},(_,n)=>({...offer,id:'offer-'+n,slug:'offer-'+n,url:'/offers/offer-'+n+'.html'}));
  const expired={...offer,id:'old-offer',slug:'old-offer',url:'/offers/old-offer.html',active:false,validity:'expired'};
  const publish=(generation,offers)=>spawnSync(process.execPath,[script.pathname,directory],{encoding:'utf8',input:JSON.stringify({origin:'https://example.test',generation:generation.repeat(64),items:[],aliases:{},offers,offerAliases:{}})});
  try{
    const result=publish('e',[...rows,expired]);assert.equal(result.status,0,result.stderr);
    const html=await readFile(join(directory,'current/_content_pages/offers/active/2.html'),'utf8');
    const state=JSON.parse(html.match(/id="public-offers-state">([\s\S]*?)<\/script>/)[1]);
    assert.equal(state.total,21);assert.equal(state.items.length,1);assert.equal(state.items[0].id,'offer-20');
    assert.equal(state.items[0].description,offer.description);
    assert.match(html,/rel="canonical" href="https:\/\/example.test\/offers.html\?page=2"/);
    assert.doesNotMatch(html,/class="detail-layout"/);
    assert.match(await readFile(join(directory,'current/offers/old-offer.html'),'utf8'),/這項優惠已結束/);
    assert.equal(publish('f',[]).status,0);
    await assert.rejects(readFile(join(directory,'current/offers/offer-0.html'),'utf8'),{code:'ENOENT'});
    assert.match(await readFile(join(directory,'current/_content_pages/offers/active/1.html'),'utf8'),/目前沒有上架中的店家優惠/);
  }finally{await rm(directory,{recursive:true,force:true});}
});
