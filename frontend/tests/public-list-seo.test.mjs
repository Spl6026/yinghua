import test from 'node:test';
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import vm from 'node:vm';
import {renderSeoHead} from '../scripts/seo-head.mjs';
import {newsListTitle,newsListDescription} from '../assets/js/core/news-view.js';
import {updatePublicListSeo} from '../assets/js/core/list-seo.js';

const settings={defaults:{titleSuffix:'｜測試品牌',canonicalOrigin:'https://canonical.example.test',description:'全站摘要'},
  overrides:[{target:{type:'page',path:'/events.html'},title:'管理員指定消息標題',description:'管理員指定摘要'},
    {target:{type:'news',id:'unrelated'},title:'不該出現在 context'}]};
const catalog=[{path:'/events.html',title:'新竹現場',description:'消息主題摘要'}];
const context=(seo=settings)=>({origin:'http://localhost:8081',catalog,seo});
function documentFixture(){
  const values={};return {values,title:'',querySelector:selector=>({setAttribute:(name,value)=>{values[selector+':'+name]=value;}})};
}

test('實際發布的 context 只帶目前頁面覆寫，JSON 不可結束 script',()=>{
  const seo={...settings,overrides:[...settings.overrides,{target:{type:'page',path:'/offers.html'},title:'其他主題'},
    {target:{type:'page',path:'/events.html'},description:'</script><script>bad</script>'}]};
  const html=renderSeoHead('<head></head>',{...context(seo),path:'/events.html'});
  const data=JSON.parse(html.match(/id="public-list-seo">([\s\S]*?)<\/script>/)[1]);
  assert.equal(data.seo.overrides.length,2);assert.doesNotMatch(html,/不該出現在 context|其他主題|<script>bad/);
  assert.equal((renderSeoHead(html,{...context(seo),path:'/events.html'}).match(/id="public-list-seo"/g)||[]).length,1);
});

test('分類、第二頁及返回第一頁與初次發布 head 相同，尊重覆寫與正式 canonical',()=>{
  for(const seo of [settings,{defaults:settings.defaults,overrides:[]}])for(const [type,page]of [['event',1],['city',2],['',1]]){
    const path='/events.html'+(type||page>1?'?'+new URLSearchParams({...type?{type}:{},...page>1?{page}: {}}):'');
    const title=newsListTitle('/events.html',type,page),description=newsListDescription('/events.html',type,page);
    const html=renderSeoHead('<head></head>',{...context(seo),path,title,description});
    const published=JSON.parse(html.match(/id="public-list-seo">([\s\S]*?)<\/script>/)[1]);
    const doc=documentFixture(),meta=updatePublicListSeo(published,path,title,description,doc);
    assert.equal(doc.title,html.match(/<title>(.*?)<\/title>/)[1]);
    assert.equal(doc.values['meta[property="og:title"]:content'],meta.title);
    assert.equal(doc.values['meta[name="twitter:description"]:content'],meta.description);
    assert.equal(doc.values['link[rel=canonical]:href'],'https://canonical.example.test'+path);
    assert.match(meta.title,seo.overrides.length?/管理員指定消息標題/:/測試品牌/);
    if(page>1){assert.match(meta.title,/第 2 頁/);assert.match(meta.description,/第 2 頁/);}
  }
});

const controllerSources=await Promise.all(['core/seo-resolver.js','core/list-seo.js','core/public-list-controller.js'].map(path=>readFile(new URL('../assets/js/'+path,import.meta.url),'utf8')));
function controllerFixture(){
  const events={},historyChanges=[],navigations=[],meta=documentFixture(),notice={textContent:'',focus(){this.focused=true;}},select={value:''};
  const form={querySelector:()=>select,addEventListener:(name,fn)=>{events['form:'+name]=fn;}};
  select.addEventListener=(name,fn)=>{events[name]=fn;};
  const list={innerHTML:'原始列表',setAttribute(){},removeAttribute(){}};
  const controls={querySelector:selector=>selector==='.public-content-status'?notice:null,insertAdjacentHTML(){},addEventListener:(name,fn)=>{events[name]=fn;}};
  notice.insertAdjacentHTML=()=>{};
  const nodes={'#state':{textContent:JSON.stringify({generation:'a',type:'',page:1})},'#list':list,'.filter':form,'.public-content-controls':controls,'#public-list-seo':{textContent:JSON.stringify(context())}};
  const doc={...meta,querySelector:selector=>nodes[selector]||meta.querySelector(selector)};
  const state={result:{items:[],total:0,page:1,generation:'a'}};
  const sandbox={document:doc,URL,URLSearchParams,location:{pathname:'/events.html',search:'',assign:href=>navigations.push(href)},history:{pushState:(_a,_b,href)=>historyChanges.push(href)},
    addEventListener:(name,fn)=>{events[name]=fn;},contentPager:()=>'',pageHref:(path,type,page)=>path+'?'+new URLSearchParams({...type?{type}:{},...page>1?{page}:{}}),fallbackContentImages(){}};
  vm.createContext(sandbox);controllerSources.forEach(code=>vm.runInContext(code.replace(/^import .*;\r?\n/gm,'').replace(/\bexport /g,''),sandbox));
  sandbox.initializePublicList({stateSelector:'#state',listSelector:'#list',filterSelector:'.filter',loadData:async()=>state.result,renderItems:()=>'新列表',titleFor:()=> '活動｜映畫竹塹',typeFromUrl:()=> 'city'});
  const settle=async()=>{for(let n=0;n<4;n++)await new Promise(resolve=>setImmediate(resolve));};
  return {events,state,doc,list,select,historyChanges,navigations,settle};
}

test('真實控制器分類／popstate 都保留後台 title，換 generation 改讀新發布頁',async()=>{
  const f=controllerFixture();f.select.value='event';f.events.change();await f.settle();
  assert.equal(f.doc.title,'管理員指定消息標題');assert.equal(f.historyChanges.length,1);assert.equal(f.list.innerHTML,'新列表');
  f.events.popstate();await f.settle();assert.equal(f.doc.title,'管理員指定消息標題');assert.equal(f.historyChanges.length,1);
  f.state.result={...f.state.result,generation:'b'};f.select.value='city';f.events.change();await f.settle();
  assert.deepEqual(f.navigations,['/events.html?type=city']);assert.equal(f.historyChanges.length,1);
});

test('優惠第二頁也保留品牌／覆寫，canonical 不改成 localhost',()=>{
  const doc=documentFixture(),seo={...settings,overrides:[{target:{type:'page',path:'/offers.html'},title:'店家專題',description:'優惠專題摘要'}]};
  updatePublicListSeo({seo,origin:'http://localhost:8081'},'/offers.html?page=2','店家優惠・第 2 頁｜映畫竹塹','優惠摘要',doc);
  assert.equal(doc.title,'店家專題・第 2 頁');assert.equal(doc.values['meta[property="og:description"]:content'],'優惠專題摘要（第 2 頁）');
  assert.equal(doc.values['link[rel=canonical]:href'],'https://canonical.example.test/offers.html?page=2');
});
