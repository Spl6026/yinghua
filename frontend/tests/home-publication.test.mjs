import test from 'node:test';
import assert from 'node:assert/strict';
import {spawnSync} from 'node:child_process';
import {mkdtemp,readFile,rm} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import {homeNewsRows} from '../assets/js/core/home-view.js';

const article={id:'newest',slug:'newest',type:'city',title:'消息 $& <script>',url:'/news/newest.html',summary:'摘要',body:'',
  eventStartAt:null,eventLabel:'',eventLocation:'',image:'',publishAt:'2026-12-31T16:00:00+00:00'};
test('首頁沿用消息三列樣式，正確區分台北跨年發布／活動日期',()=>{
  const html=homeNewsRows([article]);
  assert.match(html,/class="event-row reveal in-view"/);
  assert.match(html,/發布日期 2027\/01\/01/);
  assert.match(html,/<b>01<\/b><small>JAN \/ FRI/);
  assert.match(html,/城市消息/);assert.doesNotMatch(html,/<script>/);
  assert.match(homeNewsRows([{...article,eventStartAt:'2027-02-01T09:00:00+08:00'}]),/活動日期 2027\/02\/01/);
  assert.match(homeNewsRows([]),/目前沒有已發布的活動與消息/);
});
test('首頁 HTML 僅採用同一首頁投影且保留原型區塊',async()=>{
  const directory=await mkdtemp(join(tmpdir(),'spl-home-'));
  const home={museum:null,featured:null,films:[],filmTotal:0,period:{from:'2026-10-18T16:00:00+00:00',to:'2026-10-25T16:00:00+00:00',timeZone:'Asia/Taipei'},news:[article]};
  try{
    const result=spawnSync(process.execPath,[new URL('../scripts/publish.mjs',import.meta.url).pathname,directory],{encoding:'utf8',input:JSON.stringify({origin:'https://example.test',generation:'1'.repeat(64),items:[],aliases:{},films:[],filmPeriod:home.period,news:[],newsAliases:{},offers:[],offerAliases:{},home})});
    assert.equal(result.status,0,result.stderr);
    const html=await readFile(join(directory,'current/index.html'),'utf8');
    const data=JSON.parse(html.match(/id="home-publication">([\s\S]*?)<\/script>/)[1]);
    assert.equal(data.generation,'1'.repeat(64));assert.deepEqual(data.news,[article]);
    assert.match(html,/href="\/news\/newest.html"/);
    assert.match(html,/class="event-list"/);assert.match(html,/screening-grid/);assert.match(html,/planner-banner/);
    assert.doesNotMatch(html,/風城慢慢市集｜老城散步日|class="offer-grid"/);
    assert.match(html,/目前沒有本週映演場次/);
  }finally{await rm(directory,{recursive:true,force:true});}
});
