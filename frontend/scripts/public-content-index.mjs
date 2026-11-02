// [S G34／B15；E-29] 只使用已通過公開白名單的同一發布快照；不建立另一份內容資料。
const escape = value =>
  String(value ?? '').replace(
    /[&<>"']/g,
    char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]
  );
function contentUrl(kind, item) {
  if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(item.slug)) throw new Error('Invalid public index slug');
  return kind === 'places' && item.isMuseum ? '/museum.html' : `/${kind}/${item.slug}.html`;
}
export function renderPublicContentIndex(snapshot) {
  const groups = [
    ['places', '景點、美食與店家', snapshot.items || []],
    ['films', '電影介紹與歷史場次', snapshot.films || []],
    ['news', '消息與活動', snapshot.news || []],
    ['offers', '店家優惠與歷史資訊', snapshot.offers || []],
  ];
  return groups
    .map(
      ([kind, label, items]) =>
        `<section class="public-index-group" id="index-${kind}" aria-labelledby="index-${kind}-title"><h2 id="index-${kind}-title">${label}</h2><ul>${items.map(item => `<li data-index-item><a href="${escape(contentUrl(kind, item))}">${escape(item.name || item.title)}${kind === 'offers' && !item.active ? '（已結束或尚未開始）' : ''}</a></li>`).join('')}</ul>${!items.length ? '<p>目前還沒有內容。</p>' : ''}</section>`
    )
    .join('');
}
