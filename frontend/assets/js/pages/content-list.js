import { formatTaipeiDateTime, taipeiToday } from '../core/taipei-time.js';
import { contentAdminApi } from '../api/content-admin-api.js';
import { adminTable, publicationLabel } from '../core/admin-table.js';

// 優惠在已發布時另外標示有效期間狀態，方便判斷前台是否真的看得到。
function offerPeriod(offer, today) {
  if (offer.status !== 'published' || Date.parse(offer.publishAt) > Date.now()) return '';
  const validity =
    offer.validTo && offer.validTo < today
      ? '／已過期'
      : offer.validFrom && offer.validFrom > today
        ? '／尚未開始'
        : offer.merchantAvailable === false
          ? '／店家停用'
          : '／有效優惠';
  return `${validity}；${offer.validFrom || '不限開始'} ～ ${offer.validTo || '不限結束'}`;
}

export async function initializeContentList(identity, module) {
  const service = contentAdminApi(module),
    singular = module === 'news' ? 'news' : 'offer';
  const query = document.querySelector('#content-query'),
    filter = document.querySelector('#content-filter-status'),
    type = document.querySelector('#content-filter-type'),
    active = document.querySelector('#content-filter-active');
  let today = taipeiToday();
  const list = adminTable({
    identity,
    module,
    noun: '內容',
    colspan: 5,
    ids: {
      rows: 'content-rows',
      status: 'content-list-status',
      page: 'content-page',
      previous: 'content-previous',
      next: 'content-next',
      add: 'new-content',
      reload: 'reload-content',
    },
    search: query,
    filters: [filter, type, active],
    fetchPage: ({ page, pageSize }) => {
      today = taipeiToday();
      return service.list({
        q: query.value.trim(),
        status: filter.value,
        type: type?.value || '',
        active: active?.value || '',
        page,
        pageSize,
      });
    },
    cells: item => [
      item.title,
      module === 'news' ? (item.type === 'event' ? '活動' : '城市消息') : item.merchant?.name || '',
      item.publishAt ? formatTaipeiDateTime(item.publishAt) : '',
      publicationLabel(item) + (module === 'offers' ? offerPeriod(item, today) : ''),
    ],
    itemName: item => item.title,
    editHref: item => `/admin/${singular}-edit.html?id=${encodeURIComponent(item.id)}`,
    deleteConfirm: item => ['刪除內容', `確定刪除「${item.title}」？公開列表與相鄰連結會同步更新。`],
    remove: item => service.delete(item.id, item.version),
  });
  await list.load();
}
