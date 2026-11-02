import { resolveSeo } from './seo-resolver.js';
import { structuredDataGraph, jsonLdForHtml } from './structured-data.js';

// 初次載入、切換分類與換頁都套用發布時的 SEO 規則，並使用同一版本的公開設定。
export function readPublicListSeo(documentRoot = document) {
  const node = documentRoot.querySelector('#public-list-seo');
  return node ? JSON.parse(node.textContent) : null;
}

export function updatePublicListSeo(context, path, title, description, documentRoot = document) {
  const meta = resolveSeo({ ...context, path, title, description });
  documentRoot.title = meta.title;
  const attributes = {
    'link[rel=canonical]': ['href', meta.canonical],
    'meta[name=description]': ['content', meta.description],
    'meta[name=keywords]': ['content', meta.keywords],
    'meta[name=robots]': ['content', meta.robots],
    'meta[name=googlebot]': ['content', meta.robots],
    'meta[property="og:title"]': ['content', meta.title],
    'meta[property="og:description"]': ['content', meta.description],
    'meta[property="og:url"]': ['content', meta.canonical],
    'meta[property="og:image"]': ['content', meta.imageUrl],
    'meta[name="twitter:title"]': ['content', meta.title],
    'meta[name="twitter:description"]': ['content', meta.description],
    'meta[name="twitter:image"]': ['content', meta.imageUrl],
  };
  for (const [selector, [attribute, value]] of Object.entries(attributes))
    documentRoot.querySelector(selector)?.setAttribute(attribute, value);
  // 更新伺服器已輸出的 JSON-LD graph，不另外產生一套，也不保留舊分類或舊頁碼。
  const schema = documentRoot.querySelector('script[type="application/ld+json"]');
  if (schema) {
    const graph = structuredDataGraph({ ...context, path, published: true }, meta);
    if (graph) schema.textContent = jsonLdForHtml(graph);
    else schema.remove();
  }
  return meta;
}
