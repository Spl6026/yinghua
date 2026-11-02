// [S G18；E-35] URL與非同步結果只有一份狀態；過時成功／錯誤皆不能覆寫新搜尋。
export const searchTypes = {
  place: '景點',
  food: '美食',
  film: '電影',
  news: '消息與活動',
  offer: '店家優惠',
};
export function searchState(search) {
  const p = new URLSearchParams(search);
  return { q: (p.get('q') || '').trim(), type: p.get('type') || '', page: p.get('page') || '1' };
}
export function searchUrl({ q, type = '', page = '1' }) {
  const p = new URLSearchParams();
  if (q) p.set('q', q);
  if (type) p.set('type', type);
  if (String(page) !== '1') p.set('page', String(page));
  return '/search.html' + (p.size ? '?' + p : '');
}
export function latestSearch(load) {
  let sequence = 0;
  return async (state, success, failure) => {
    const version = ++sequence;
    try {
      const result = await load(state);
      if (version === sequence) success(result);
    } catch (error) {
      if (version === sequence) failure(error);
    }
  };
}
