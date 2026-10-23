import { resolveSeo } from '../assets/js/core/seo-resolver.js';

const escape=value=>String(value??'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
// [S：E30／SEO E17；E-28] HTML 只有一個標籤擁有者；所有動態值均編碼。
export function renderSeoHead(html,context){
  const meta=resolveSeo(context);
  html=html.replace(/<title\b[^>]*>[\s\S]*?<\/title>/gi,'').replace(/<meta\b[^>]*>/gi,tag=>
    /(?:name|property)\s*=\s*["'](?:description|keywords|robots|googlebot|twitter:[^"']*|og:[^"']*|google-site-verification|msvalidate\.01)["']/i.test(tag)?'':tag)
    .replace(/<link\b[^>]*>/gi,tag=>/rel\s*=\s*["']canonical["']/i.test(tag)?'':tag);
  const name=(key,value)=>`<meta name="${key}" content="${escape(value)}">`;
  const property=(key,value)=>`<meta property="${key}" content="${escape(value)}">`;
  let head=`<title>${escape(meta.title)}</title>`+name('description',meta.description)+name('keywords',meta.keywords)+name('robots',meta.robots)+name('googlebot',meta.robots)+`<link rel="canonical" href="${escape(meta.canonical)}">`;
  for(const [key,value]of Object.entries({title:meta.title,description:meta.description,type:meta.type,url:meta.canonical,image:meta.imageUrl,site_name:meta.siteName,locale:meta.locale}))head+=property('og:'+key,value);
  for(const [key,value]of Object.entries({card:meta.twitterCard,title:meta.title,description:meta.description,image:meta.imageUrl}))head+=name('twitter:'+key,value);
  if(meta.twitterSite)head+=name('twitter:site',meta.twitterSite);
  if(meta.googleVerification)head+=name('google-site-verification',meta.googleVerification);
  if(meta.bingVerification)head+=name('msvalidate.01',meta.bingVerification);
  if(!/<link\b[^>]*rel=["'](?:shortcut )?icon["']/i.test(html))head+='<link rel="icon" href="/assets/favicon.svg">';
  return html.replace('</head>',()=>head+'</head>');
}
