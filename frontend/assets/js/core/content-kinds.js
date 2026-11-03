// 四種公開內容（景點、電影、消息、優惠）共同的對照表：發布快照的鍵、網址目錄與 SEO 覆寫用的類型。
// 對應後端的 Models\PublicContentType；判斷網址屬於哪一類、組出詳情頁網址都從這裡取，不再各處各寫一份。
export const MUSEUM_PATH = '/museum.html';

export const contentKinds = [
  { type: 'attraction', segment: 'places', snapshotKey: 'items' },
  { type: 'film', segment: 'films', snapshotKey: 'films' },
  { type: 'news', segment: 'news', snapshotKey: 'news' },
  { type: 'offer', segment: 'offers', snapshotKey: 'offers' },
];

const byType = Object.fromEntries(contentKinds.map(kind => [kind.type, kind]));

export const contentKind = type => byType[type] || null;

/** 詳情頁網址屬於哪一類內容；博物館是景點的固定頁面。不是詳情頁時回傳 null。 */
export function contentKindOfPath(pathname) {
  if (pathname === MUSEUM_PATH) return byType.attraction;
  return contentKinds.find(kind => pathname.startsWith(`/${kind.segment}/`)) || null;
}

/** 是否為單一內容的詳情頁（含博物館）。 */
export function isContentDetailPath(pathname) {
  return (
    pathname === MUSEUM_PATH ||
    contentKinds.some(kind => new RegExp(`^/${kind.segment}/[^/]+\\.html$`).test(pathname))
  );
}

/** 依代稱組出詳情頁網址；標示為博物館的景點固定在博物館頁。 */
export function contentDetailUrl(kind, item) {
  return kind.type === 'attraction' && item.isMuseum ? MUSEUM_PATH : `/${kind.segment}/${item.slug}.html`;
}

/** 發布快照中所有公開內容（依景點、電影、消息、優惠的順序）。 */
export const snapshotContent = snapshot => contentKinds.flatMap(kind => snapshot[kind.snapshotKey] || []);

/** 搜尋結果的分類對應到的內容類型；美食是景點中依分類另外分出的一組。 */
export const searchResultKind = searchType =>
  ({
    place: byType.attraction,
    food: byType.attraction,
    film: byType.film,
    news: byType.news,
    offer: byType.offer,
  })[searchType] || null;
