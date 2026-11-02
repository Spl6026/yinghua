import { escapeHtml as e } from './content-view.js';

// 只呈現後端在同一發布版本算出的上一篇／下一篇，不自行重排，也不混入草稿。
export function contentNeighbors(neighbors) {
  if (!neighbors) return '';
  const cards = [
    ['previous', '上一篇'],
    ['next', '下一篇'],
  ].flatMap(([key, label]) => {
    const item = neighbors[key];
    if (!item || !/^\/(?:museum\.html|(?:places|films|news|offers)\/[a-z0-9-]+\.html)$/.test(item.url || ''))
      return [];
    const image = /^(?:https?:\/\/|\/media\/)/.test(item.image || '')
      ? item.image
      : '/assets/image-placeholder.svg';
    return [
      `<a class="article-neighbor article-neighbor-${key}" href="${e(item.url)}" aria-label="${label}：${e(item.title)}"><img src="${e(image)}" alt="" width="240" height="160" loading="lazy"><span class="article-neighbor-copy"><span class="article-neighbor-label">${label}</span><strong>${e(item.title)}</strong></span></a>`,
    ];
  });
  return cards.length
    ? `<nav class="article-neighbors" aria-label="同主題文章導覽"><h2>繼續閱讀</h2><div class="article-neighbor-grid">${cards.join('')}</div></nav>`
    : '';
}
