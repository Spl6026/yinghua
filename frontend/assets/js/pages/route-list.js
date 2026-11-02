import { recommendedRoutesApi } from '../api/recommended-routes-api.js';
import { adminTable, publicationLabel } from '../core/admin-table.js';

export async function initializeRouteList(identity) {
  const query = document.querySelector('#route-query'),
    filter = document.querySelector('#route-status');
  const list = adminTable({
    identity,
    module: 'routes',
    noun: '推薦行程',
    colspan: 6,
    ids: {
      rows: 'route-rows',
      status: 'route-list-status',
      page: 'route-page',
      previous: 'route-previous',
      next: 'route-next',
      add: 'new-route',
    },
    search: query,
    filters: [filter],
    fetchPage: ({ page, pageSize }) =>
      recommendedRoutesApi.list({ q: query.value.trim(), status: filter.value, page, pageSize }),
    cells: route => [
      route.title,
      route.theme || '—',
      route.stops.length,
      route.sortOrder,
      publicationLabel(route),
    ],
    itemName: route => route.title,
    editHref: route => '/admin/route-edit.html?id=' + encodeURIComponent(route.id),
    deleteConfirm: route => ['刪除推薦行程', `確定刪除「${route.title}」？`],
    remove: route => recommendedRoutesApi.delete(route.id, route.version),
  });
  await list.load();
}
