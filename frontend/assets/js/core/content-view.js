// [E-26] Node 發布與瀏覽器共用字元編碼、日期及圖片外觀。
export const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
export const jsonForHtml = value => JSON.stringify(value).replaceAll('<','\\u003c').replaceAll('>','\\u003e').replaceAll('&','\\u0026');
export function contentDate(stamp, options = {}) {
  if (!stamp || !Number.isFinite(Date.parse(stamp))) return '日期依公告';
  return new Intl.DateTimeFormat('zh-TW', {timeZone:'Asia/Taipei',year:'numeric',month:'2-digit',day:'2-digit',...options}).format(new Date(stamp));
}
export function contentImage(item, className, tint = '') {
  const image = item.image || '/assets/image-placeholder.svg';
  return `<div class="${className}" role="img" aria-label="${escapeHtml(item.imageAlt || '圖片尚未提供')}" data-image-url="${escapeHtml(image)}" style="background-image:${tint}url(${escapeHtml(JSON.stringify(image))})"></div>`;
}
export function pageHref(path, type, page) {
  const params = new URLSearchParams();
  if (path === '/events.html' && type) params.set('type',type);
  if (page > 1) params.set('page',String(page));
  return path + (params.size ? '?' + params.toString() : '');
}
export function contentPager(total, page, path, type = '', pageSize = 20) {
  const pages = Math.max(1,Math.ceil(total/pageSize));
  if (pages <= 1) return '';
  return `<nav class="pagination-controls public-content-pager" aria-label="文章分頁">${page>1?`<a class="button button-outline" data-content-page href="${escapeHtml(pageHref(path,type,page-1))}">上一頁</a>`:''}<span aria-live="polite">第 ${page}／${pages} 頁，共 ${total} 筆</span>${page<pages?`<a class="button button-outline" data-content-page href="${escapeHtml(pageHref(path,type,page+1))}">下一頁</a>`:''}</nav>`;
}
export function fallbackContentImages(root = document) {
  root.querySelectorAll('[data-image-url]').forEach(node=>{
    const probe = new Image();
    probe.onerror = ()=>{node.style.backgroundImage='url("/assets/image-placeholder.svg")';};
    probe.src=node.dataset.imageUrl;
  });
}
