import test from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { mkdtemp, readFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { weeklyFilms, screeningDate, filmRows, filmCards, filmDetail } from '../assets/js/core/film-view.js';

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
    assert.equal((cinema.match(/class="cinema-row/g)||[]).length,2);
    assert.match(cinema,/data-managed-films="true"/);
    assert.equal(publish('b',[]).status,0);
    await assert.rejects(readFile(join(directory,'current/films/film-test.html'),'utf8'),{code:'ENOENT'});
    assert.match(await readFile(join(directory,'current/cinema.html'),'utf8'),/目前沒有/);
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
