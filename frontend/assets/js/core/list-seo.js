import {resolveSeo} from './seo-resolver.js';

// [S：E30/E32/L32；E-29] 初載、分類、分頁共用發布時的 SEO 規則及同版公開設定。
export function readPublicListSeo(documentRoot=document){
  const node=documentRoot.querySelector('#public-list-seo');
  return node?JSON.parse(node.textContent):null;
}

export function updatePublicListSeo(context,path,title,description,documentRoot=document){
  const meta=resolveSeo({...context,path,title,description});
  documentRoot.title=meta.title;
  const attributes={
    'link[rel=canonical]':['href',meta.canonical],
    'meta[name=description]':['content',meta.description],
    'meta[name=keywords]':['content',meta.keywords],
    'meta[name=robots]':['content',meta.robots],
    'meta[name=googlebot]':['content',meta.robots],
    'meta[property="og:title"]':['content',meta.title],
    'meta[property="og:description"]':['content',meta.description],
    'meta[property="og:url"]':['content',meta.canonical],
    'meta[property="og:image"]':['content',meta.imageUrl],
    'meta[name="twitter:title"]':['content',meta.title],
    'meta[name="twitter:description"]':['content',meta.description],
    'meta[name="twitter:image"]':['content',meta.imageUrl],
  };
  for(const [selector,[attribute,value]]of Object.entries(attributes))documentRoot.querySelector(selector)?.setAttribute(attribute,value);
  return meta;
}
