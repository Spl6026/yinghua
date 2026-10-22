import {listNews} from '../api/news-api.js';
import {newsCards,newsFeatured,newsLabel} from '../core/news-view.js';
import {contentPager,pageHref,fallbackContentImages} from '../core/content-view.js';

const initial=document.querySelector('#public-news-state');
const list=document.querySelector('#news-list');
if(initial&&list){
  const controls=document.querySelector('.public-content-controls');
  const notice=controls.querySelector('.public-content-status');
  const form=document.querySelector('.public-content-filter');
  const select=form?.querySelector('select');
  const featured=document.querySelector('.news-featured-lead');
  const path=location.pathname;
  let state=JSON.parse(initial.textContent),sequence=0,retryTarget=null;
  const load=async(target,changeHistory=true)=>{
    const request=++sequence;
    notice.textContent='正在讀取消息…';
    list.setAttribute('aria-busy','true');
    try{
      const data=await listNews(target);
      if(request!==sequence)return;
      list.innerHTML=newsCards(data.items);
      if(featured)featured.innerHTML=newsFeatured(data.featured);
      controls.querySelector('nav')?.remove();
      notice.insertAdjacentHTML('beforebegin',contentPager(data.total,data.page,path,target.type));
      if(select)select.value=target.type;
      state={...data,type:target.type};retryTarget=null;
      const href=pageHref(path,target.type,data.page);
      if(changeHistory)history.pushState(null,'',href);
      const title=(path==='/events.html'?(target.type?newsLabel(target.type):'新竹現場'):path==='/activities.html'?'新竹活動':'新竹城市消息')+(data.page>1?'・第 '+data.page+' 頁':'')+'｜映畫竹塹';
      document.title=title;
      const canonical=document.querySelector('link[rel=canonical]');
      if(canonical)canonical.href=location.origin+href;
      for(const selector of ['meta[property="og:title"]','meta[name="twitter:title"]'])document.querySelector(selector)?.setAttribute('content',title);
      document.querySelector('meta[property="og:url"]')?.setAttribute('content',location.origin+href);
      notice.textContent=`已載入第 ${data.page} 頁，共 ${data.total} 則消息。`;
      fallbackContentImages();
    }catch(error){
      if(request!==sequence)return;
      retryTarget=target;
      notice.textContent=error.message+' ';
      const retry=document.createElement('button');retry.type='button';retry.className='text-link';retry.textContent='重試';
      retry.addEventListener('click',()=>load(retryTarget));notice.append(retry);notice.focus();
    }finally{
      if(request===sequence)list.removeAttribute('aria-busy');
    }
  };
  form?.addEventListener('submit',event=>{event.preventDefault();void load({type:select.value,page:1});});
  select?.addEventListener('change',()=>void load({type:select.value,page:1}));
  controls.addEventListener('click',event=>{
    const link=event.target.closest('a[data-content-page]');
    if(!link||event.button!==0||event.metaKey||event.ctrlKey||event.shiftKey||event.altKey)return;
    event.preventDefault();
    const target=new URL(link.href);
    void load({type:state.type,page:Number(target.searchParams.get('page')||1)}).then(()=>notice.focus());
  });
  addEventListener('popstate',()=>{
    const params=new URLSearchParams(location.search);
    void load({type:path==='/activities.html'?'event':path==='/city-news.html'?'city':params.get('type')||'',page:Number(params.get('page')||1)},false);
  });
}
fallbackContentImages();
