import test from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { mkdtemp, readFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { weeklyFilms, screeningDate, filmRows, filmCards, filmDetail } from '../assets/js/core/film-view.js';
import { emptyOpeningHours } from '../assets/js/core/opening-hours.js';

const script = new URL('../scripts/publish.mjs', import.meta.url);
const period = {from:'2026-12-27T16:00:00+00:00',to:'2027-01-03T16:00:00+00:00',timeZone:'Asia/Taipei'};
const film = {id:'film-id',slug:'film-test',title:'電影 $& <script>1</script>',genre:'紀錄片',synopsis:'故事 $1\n<script>2</script>',
  image:'/media/test.webp',imageAlt:'海報',posterAlt:'海報',posterUrl:'',ticketUrl:'',seoTitle:'',seoDescription:'',
  url:'/films/film-test.html',updatedAt:'2026-12-28T01:00:00+00:00',screenings:[
    {id:'first',startsAt:'2026-12-28T06:00:00+00:00',effectiveTicketUrl:'https://tickets.example.test/a'},
    {id:'second',startsAt:'2026-12-28T06:30:00+00:00',effectiveTicketUrl:''},
    {id:'outside',startsAt:period.to,effectiveTicketUrl:''}]};

test('台北週期跨年、半開區間與同電影多場排序',()=>{
  const items=weeklyFilms([film],period);
  assert.equal(items.length,1);assert.equal(items[0].screenings.length,2);
  assert.equal(screeningDate(items[0].screenings[0].startsAt).time,'14:00');
  assert.equal(screeningDate('2026-12-31T16:30:00+00:00').full,'2027-01-01');
  assert.equal(weeklyFilms([{...film,screenings:[]}],period).length,0);
});

test('真正產生首頁、片單及詳情：無 JS 可讀、安全編碼、下架不沿用舊頁',async()=>{
  const directory=await mkdtemp(join(tmpdir(),'spl-film-'));
  const publish=(generation,films)=>spawnSync(process.execPath,[script.pathname,directory],{encoding:'utf8',input:JSON.stringify({
    origin:'https://example.test',generation:generation.repeat(64),items:[],aliases:{},films,filmAliases:{},filmPeriod:period})});
  try {
    const result=publish('a',[film]);assert.equal(result.status,0,result.stderr);
    const detail=await readFile(join(directory,'current/films/film-test.html'),'utf8');
    assert.match(detail,/電影 \$&amp; &lt;script&gt;1&lt;\/script&gt;/);
    assert.match(detail,/故事 \$1/);assert.doesNotMatch(detail,/<script>[12]<\/script>/);
    assert.match(detail,/<title>電影 \$&amp; &lt;script&gt;1&lt;\/script&gt;｜映畫竹塹<\/title>/);
    assert.match(detail,/rel="canonical" href="https:\/\/example.test\/films\/film-test.html"/);
    assert.match(detail,/14:00/);assert.match(detail,/14:30/);assert.doesNotMatch(detail,/附近推薦/);
    const home=await readFile(join(directory,'current/index.html'),'utf8');
    assert.match(home,/電影 \$&amp;/);assert.match(home,/12.28 — 01.03/);
    const cinema=await readFile(join(directory,'current/cinema.html'),'utf8');
    assert.match(cinema, /id="film-period"><bdi lang="en">THIS WEEK<\/bdi> · 12\.28 — 01\.03<\/p>/);
    assert.doesNotMatch(cinema, /10\.10—10\.16/);
    assert.equal((cinema.match(/class="cinema-row/g)||[]).length,2);
    assert.match(cinema,/data-managed-films="true"/);
    assert.equal(publish('b',[]).status,0);
    await assert.rejects(readFile(join(directory,'current/films/film-test.html'),'utf8'),{code:'ENOENT'});
    assert.match(await readFile(join(directory,'current/cinema.html'),'utf8'),/目前沒有/);
    assert.match(await readFile(join(directory,'current/cinema.html'),'utf8'), /id="film-period"><bdi lang="en">THIS WEEK<\/bdi> · 12\.28 — 01\.03<\/p>/);
  } finally {await rm(directory,{recursive:true,force:true});}
});

test('電影頁沿用原型海報遮色、預設類型及詳情場次段落',()=>{
  const item={...film,genre:''};
  assert.match(filmRows([item]),/linear-gradient\(0deg,rgba\(18,24,20,.08\)/);
  assert.match(filmCards([item]),/linear-gradient\(0deg,rgba\(16,24,19,.28\)/);
  assert.match(filmRows([item]),/城市放映/);
  const html=filmDetail(item);assert.match(html,/IMAGE MUSEUM \/ SCREENING/);
  assert.match(html,/href="\/cinema\.html">回到本週映演/);
  assert.doesNotMatch(html,/CITY STORY|回到城市散策/);
  assert.match(html,/aria-label="放映場次"/);assert.match(html,/<time datetime=/);
  assert.doesNotMatch(html,/<h2>放映場次|<ul class="film-screenings"/);
  assert.match(html,/rel="noopener noreferrer"/);
});

test('片單館舍資訊與詳情同版更新，缺館舍不顯示舊地址或時間',async()=>{
  const directory=await mkdtemp(join(tmpdir(),'spl-cinema-museum-'));
  const openingHours=emptyOpeningHours();openingHours.days[1]={day:1,status:'periods',periods:[{opens:'10:15',closes:'21:30',nextDay:false}]};
  const museum={id:'museum',slug:'museum',isMuseum:true,name:'測試館舍 <script>x</script>',summary:'',description:'',category:'景點',address:'新地址 $&',hours:'不要顯示舊摘要',openingHours,updatedAt:'2026-12-28T00:00:00+00:00'};
  const publish=(generation,items)=>spawnSync(process.execPath,[script.pathname,directory],{encoding:'utf8',input:JSON.stringify({origin:'https://example.test',generation:generation.repeat(64),items,aliases:{},films:[],filmAliases:{},filmPeriod:period})});
  try{
    let result=publish('a',[museum]);assert.equal(result.status,0,result.stderr);
    const cinema=await readFile(join(directory,'current/cinema.html'),'utf8');
    const detail=await readFile(join(directory,'current/museum.html'),'utf8');
    for(const html of [cinema,detail]){assert.match(html,/新地址 \$&amp;/);assert.match(html,/週一：10:15–21:30/);assert.doesNotMatch(html,/<script>x<\/script>/);}
    assert.doesNotMatch(cinema.match(/<main[\s\S]*?<\/main>/)[0],/09:30|不要顯示舊摘要/);
    result=publish('b',[{...museum,address:'第二版地址',openingHours:null,hours:'預約參觀'}]);assert.equal(result.status,0,result.stderr);
    const changed=await readFile(join(directory,'current/cinema.html'),'utf8');
    assert.match(changed,/第二版地址/);assert.match(changed,/預約參觀/);assert.doesNotMatch(changed,/新地址|10:15/);
    result=publish('c',[]);assert.equal(result.status,0,result.stderr);
    const empty=await readFile(join(directory,'current/cinema.html'),'utf8');
    assert.match(empty,/博物館介紹即將更新/);assert.doesNotMatch(empty,/第二版地址|09:30|預約參觀/);
  }finally{await rm(directory,{recursive:true,force:true});}
});
