import {element} from '../core/ui.js';

// [S E18/L18；E-35] 沿原型search-hit結構的純卡片，不載入示範陣列。
export function searchCard(item,label){
  const card=element('article',{className:'route-card search-hit'});
  card.append(element('p',{className:'eyebrow'},label),element('h3',{},item.title),element('p',{},item.summary));
  const prefix={place:'places',food:'places',film:'films',news:'news',offer:'offers'}[item.type];
  const valid=prefix&&new RegExp('^/'+prefix+'/[a-z0-9-]+\\.html$').test(item.url)||item.type==='place'&&item.url==='/museum.html';
  if(valid){const link=element('a',{className:'text-link',href:item.url,'aria-label':'閱讀詳情：'+item.title},'閱讀詳情 ');link.append(element('span',{'aria-hidden':'true'},'↗'));card.append(link);}
  return card;
}
