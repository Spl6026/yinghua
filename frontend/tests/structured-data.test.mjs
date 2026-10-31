import test from 'node:test';
import assert from 'node:assert/strict';
import {resolveSeo} from '../assets/js/core/seo-resolver.js';
import {structuredDataGraph,jsonLdForHtml,validTimestamp} from '../scripts/structured-data.mjs';
import {renderSeoHead} from '../scripts/seo-head.mjs';

const base={origin:'https://example.test',published:true,path:'/places/walk.html',publicImages:['/media/visible.png'],item:{name:'真實景點',category:'景點',summary:'可見摘要',description:'可見介紹',address:'新竹市',phone:'03-1234567',image:'/media/visible.png'}};
const graph=context=>structuredDataGraph(context,resolveSeo(context));
const main=context=>graph(context)['@graph'].find(node=>node['@id'].endsWith('#content'));

test('真實公開graph包含網站、組織、頁面及景點，略過SEO正文／隱藏欄位',()=>{
  const context={...base,item:{...base.item,seoDescription:'搜尋摘要',secret:'不可外流'},seo:{defaults:{canonicalOrigin:'https://official.test'},overrides:[]}};
  const result=graph(context),entity=main(context);
  assert.deepEqual(result['@graph'].map(n=>n['@type']),['WebSite','Organization','WebPage','TouristAttraction']);
  assert.equal(entity.description,'可見介紹');assert.equal(entity.url,'https://official.test/places/walk.html');
  assert.equal(entity.image,'https://official.test/media/visible.png');assert.equal(entity.address,'新竹市');
  assert.ok(!JSON.stringify(result).includes('不可外流'));assert.ok(!JSON.stringify(entity).includes('搜尋摘要'));
});

test('館舍與美食依真實型別，不臆造價格／評分／Google介紹',()=>{
  assert.equal(main({...base,path:'/museum.html'})['@type'],'Museum');
  assert.equal(main({...base,item:{...base.item,category:'在地店家'}})['@type'],'LocalBusiness');
  const entity=main({...base,item:{...base.item,category:'美食',price:'unknown',rating:5,googleSummary:'參考資料'}});
  assert.equal(entity['@type'],'LocalBusiness');assert.ok(!('price' in entity));assert.ok(!('aggregateRating' in entity));assert.ok(!JSON.stringify(entity).includes('參考資料'));
});

test('電影不把場次誤當電影上映日或虛構導演片長',()=>{
  const entity=main({...base,path:'/films/a.html',item:{title:'電影',synopsis:'電影介紹',genre:'紀錄片',screenings:[{startsAt:'2026-11-01T10:00:00Z'}]}});
  assert.equal(entity['@type'],'Movie');assert.equal(entity.description,'電影介紹');assert.ok(!('datePublished' in entity));assert.ok(!('director' in entity));assert.ok(!('duration' in entity));
});

test('活動有日期及場地才用Event，其餘Article保留真發布日期',()=>{
  const item={title:'活動',summary:'可見活動摘要',type:'event',eventStartAt:'2026-11-02T02:00:00Z',eventEndAt:'2026-11-02T05:00:00Z',eventLocation:'市民廣場',publishAt:'2026-10-31T01:00:00Z'};
  const entity=main({...base,path:'/news/event.html',item});
  assert.equal(entity['@type'],'Event');assert.deepEqual(entity.location,{'@type':'Place',name:'市民廣場'});assert.ok(!('address' in entity.location));
  const article=main({...base,path:'/news/event.html',item:{...item,eventStartAt:null}});
  assert.equal(article['@type'],'Article');assert.equal(article.datePublished,'2026-10-31T01:00:00.000Z');assert.ok(!('author' in article));
});

test('優惠使用真店家和有效期間，不捏造Product價格或在庫狀態',()=>{
  const entity=main({...base,path:'/offers/autumn.html',item:{title:'散步優惠',discount:'第二杯半價',validFrom:'2026-11-01',validTo:'2026-11-30',merchant:{name:'店家',address:'未在詳情顯示的地址'}}});
  assert.equal(entity['@type'],'Offer');assert.equal(entity.seller.name,'店家');assert.equal(entity.validThrough,'2026-11-30T23:59:59+08:00');
  assert.ok(!('address' in entity.seller));assert.ok(!('price' in entity));assert.ok(!('availability' in entity));
});

test('私人、管理、搜尋、未發布及noindex都沒有graph',()=>{
  for(const path of ['/planner.html','/favorites.html','/login.html','/register.html','/search.html','/admin/settings.html','/api/v1/me/itineraries','/_content_pages/events/all/1.html'])assert.equal(graph({...base,path}),null,path);
  assert.equal(graph({...base,published:false}),null);assert.equal(graph({...base,unavailable:true}),null);
  assert.equal(graph({...base,seo:{defaults:{robots:'noindex,follow'}}}),null);
});

test('安全序列化與單一擁有者，不留下原型或惡意script',()=>{
  const context={...base,item:{...base.item,name:'</script><img src=x onerror=alert(1)>\u2028&'}};
  const html=renderSeoHead('<head><script type="application/ld+json">{"demo":true}</script></head>',context);
  const blocks=[...html.matchAll(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/g)];
  assert.equal(blocks.length,1);assert.ok(!html.includes('<img src=x'));assert.ok(!html.includes('"demo"'));
  assert.equal(JSON.parse(blocks[0][1])['@graph'].at(-1).name,context.item.name);
  assert.equal(jsonLdForHtml({x:'<>&\u2028\u2029'}),'{"x":"\\u003c\\u003e\\u0026\\u2028\\u2029"}');
  const privateHtml=renderSeoHead(html,{...context,path:'/planner.html'});assert.ok(!privateHtml.includes('application/ld+json'));
});

test('圖片與日期不從私有媒體或錯誤日曆值補造',()=>{
  assert.ok(!main({...base,item:{...base.item,image:'/media/private.png'}}).image);
  assert.ok(!main({...base,item:{...base.item,image:'javascript:alert(1)'}}).image);
  assert.equal(main({...base,publicImages:['https://example.test/media/visible.png'],item:{...base.item,image:'https://example.test/media/visible.png'}}).image,'https://example.test/media/visible.png');
  assert.equal(validTimestamp('2026-02-30T12:00:00Z'),null);assert.equal(validTimestamp('2026-11-01T24:00:00Z'),null);
  assert.equal(validTimestamp('2026-11-01 12:00:00'),'2026-11-01T12:00:00.000Z');
});
