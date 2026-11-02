// Node 發布流程與瀏覽器共用字元編碼、日期格式及圖片呈現。
export const escapeHtml = value =>
  String(value ?? '').replace(
    /[&<>"']/g,
    c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]
  );
// 嵌入 <script> 的 JSON：跳脫 < > & 以免提早結束標籤，並跳脫 U+2028／U+2029，讓舊瀏覽器解析時不會斷行出錯。
export const jsonForHtml = value =>
  JSON.stringify(value)
    .replaceAll('<', '\\u003c')
    .replaceAll('>', '\\u003e')
    .replaceAll('&', '\\u0026')
    .replaceAll('\u2028', '\\u2028')
    .replaceAll('\u2029', '\\u2029');
export function contentDate(stamp, options = {}) {
  if (!stamp || !Number.isFinite(Date.parse(stamp))) return '日期依公告';
  return new Intl.DateTimeFormat('zh-TW', {
    timeZone: 'Asia/Taipei',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    ...options,
  }).format(new Date(stamp));
}
export function contentImage(item, className, tint = '') {
  const image = item.image || '/assets/image-placeholder.svg';
  return `<div class="${className}" role="img" aria-label="${escapeHtml(item.imageAlt || '圖片尚未提供')}" data-image-url="${escapeHtml(image)}" style="background-image:${tint}url(${escapeHtml(JSON.stringify(image))})"></div>`;
}
export function pageHref(path, type, page) {
  const params = new URLSearchParams();
  if (path === '/events.html' && type) params.set('type', type);
  if (page > 1) params.set('page', String(page));
  return path + (params.size ? '?' + params.toString() : '');
}
export function contentPager(total, page, path, type = '', pageSize = 20) {
  const pages = Math.max(1, Math.ceil(total / pageSize));
  if (pages <= 1) return '';
  return `<nav class="pagination-controls public-content-pager" aria-label="內容分頁">${page > 1 ? `<a class="button button-outline" data-content-page href="${escapeHtml(pageHref(path, type, page - 1))}">上一頁</a>` : ''}<span aria-live="polite">第 ${page}／${pages} 頁，共 ${total} 筆</span>${page < pages ? `<a class="button button-outline" data-content-page href="${escapeHtml(pageHref(path, type, page + 1))}">下一頁</a>` : ''}</nav>`;
}
// 背景圖載入失敗時換成替代圖，不留下空白卡片。已檢查過的節點會標記，換頁後重複呼叫不會再下載一次。
export function fallbackContentImages(root = document) {
  root.querySelectorAll('[data-image-url]').forEach(node => {
    if (node.dataset.imageChecked) return;
    node.dataset.imageChecked = 'true';
    const probe = new Image();
    probe.onerror = () => {
      node.style.backgroundImage = 'url("/assets/image-placeholder.svg")';
    };
    probe.src = node.dataset.imageUrl;
  });
}
