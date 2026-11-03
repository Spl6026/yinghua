import { attractionsApi } from '../api/attractions-api.js';
import { adminTable, publicationLabel } from '../core/admin-table.js';

export async function initializeAttractionList(identity) {
  const query = document.querySelector('#admin-place-search'),
    filter = document.querySelector('#place-status-filter');
  const list = adminTable({
    identity,
    module: 'attractions',
    noun: '景點',
    colspan: 6,
    ids: {
      rows: 'place-rows',
      status: 'place-list-status',
      page: 'place-page',
      previous: 'place-previous',
      next: 'place-next',
      add: 'add-place',
      reload: 'reload-places',
      retry: 'retry-publication',
    },
    search: query,
    filters: [filter],
    fetchPage: ({ page, pageSize }) =>
      attractionsApi.list({ q: query.value.trim(), status: filter.value, page, pageSize }),
    cells: item => [
      item.name,
      item.category,
      item.district,
      item.hours || '依店家公告',
      publicationLabel(item),
    ],
    itemName: item => item.name,
    editHref: item => '/admin/place-edit.html?id=' + encodeURIComponent(item.id),
    deleteConfirm: item => ['刪除景點', `確定刪除「${item.name}」？被路線引用的景點需先調整路線。`],
    remove: item => attractionsApi.delete(item.id, item.version),
    retry: () => attractionsApi.retry(),
  });
  await list.load();
}
