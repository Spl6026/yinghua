// [S：功能規格 9；E-17／26] 各分類所有頁均由成功快照產生，query 重整不退回第一頁。
import {readFile} from 'node:fs/promises';
import {join} from 'node:path';
import {newsCards,newsFeatured,newsDetail,newsLabel,newsListTitle,newsListDescription} from '../assets/js/core/news-view.js';
import {contentPager,pageHref,jsonForHtml} from '../assets/js/core/content-view.js';

export async function publishNews(source,snapshot,page) {
  for (const kind of ['events','activities','city-news']) {
    const template=await readFile(join(source,'pages',kind+'.html'),'utf8');
    for (const type of kind==='events'?['','event','city']:[kind==='activities'?'event':'city']) {
      const items=snapshot.news.filter(item=>!type||item.type===type);
      const total=items.length;
      for(let current=1;current<=Math.max(1,Math.ceil(total/20));current++) {
        const data={items:items.slice((current-1)*20,current*20),total,page:current,pageSize:20,type,generation:snapshot.generation};
        const path='/'+kind+'.html';
        const href=pageHref(path,type,current);
        let html=template.replace(/(<div class="news-list[^\"]*" id="news-list"[^>]*>)[\s\S]*?<\/div>/,(_,open)=>open+newsCards(data.items)+'</div>');
        html=html.replace('</main>',()=>`<div class="section-pad public-content-controls">${contentPager(total,current,path,type)}<p class="public-content-status" role="status" tabindex="-1"></p></div></main>`);
        if(kind==='events') {
          html=html.replace(/<div class="news-featured-lead">[\s\S]*?(?=<div class="news-list-heading">)/,()=>`<div class="news-featured-lead">${newsFeatured(items[0])}</div>\n`);
          html=html.replace(/<select id="news-filter">[\s\S]*?<\/select>/,()=>`<form class="public-content-filter" action="/events.html" method="get"><select id="news-filter" name="type"><option value=""${!type?' selected':''}>全部消息</option><option value="event"${type==='event'?' selected':''}>活動</option><option value="city"${type==='city'?' selected':''}>城市消息</option></select><noscript><button class="button button-outline" type="submit">篩選</button></noscript></form>`);
        }
        html=html.replace('</body>',()=>`<script type="application/json" id="public-news-state">${jsonForHtml(data)}</script><script type="module" src="/assets/js/pages/news.js"></script></body>`);
        const title=newsListTitle(path,type,current);
        const destination=`/_content_pages/${kind}/${type||'all'}/${current}.html`;
        await page(destination,html,title,newsListDescription(path,type,current),undefined,href);
      }
    }
  }
  const detailTemplate=await readFile(join(source,'pages/news/slow-market.html'),'utf8');
  for(const item of snapshot.news) {
    if(!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(item.slug))throw new Error('Invalid news slug');
    const html=detailTemplate.replace(/<section class="section-pad detail-page">[\s\S]*?<\/section>/,()=>newsDetail(item));
    await page(item.url,html,item.seoTitle||`${item.title}｜映畫竹塹`,item.seoDescription||item.summary||`${item.title}的活動與城市消息資訊。`,{...item,category:newsLabel(item.type)});
  }
}
