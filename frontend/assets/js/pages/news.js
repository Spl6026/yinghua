import {listNews} from '../api/news-api.js';
import {newsCards,newsFeatured,newsLabel} from '../core/news-view.js';
import {initializePublicList} from '../core/public-list-controller.js';
import {fallbackContentImages} from '../core/content-view.js';

initializePublicList({stateSelector:'#public-news-state',listSelector:'#news-list',loadData:listNews,renderItems:newsCards,
  filterSelector:'.public-content-filter',
  renderFeatured:data=>{const node=document.querySelector('.news-featured-lead');if(node)node.innerHTML=newsFeatured(data.featured);},
  titleFor:(data,type)=>(location.pathname==='/events.html'?(type?newsLabel(type):'新竹現場'):location.pathname==='/activities.html'?'新竹活動':'新竹城市消息')+(data.page>1?'・第 '+data.page+' 頁':'')+'｜映畫竹塹',
  typeFromUrl:()=>location.pathname==='/activities.html'?'event':location.pathname==='/city-news.html'?'city':new URLSearchParams(location.search).get('type')||''});
fallbackContentImages();
