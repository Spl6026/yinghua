import { taipeiFormatter } from '../core/taipei-time.js';
import { filmsApi } from '../api/films-api.js';
import { element } from '../core/ui.js';
import { adminTable, publicationLabel } from '../core/admin-table.js';

const placeholder = '/assets/image-placeholder.svg';

function poster(film) {
  const image = element('img', {
    className: 'admin-film-thumb',
    src: film.image || placeholder,
    alt: film.imageAlt || `${film.title}電影海報`,
    width: '92',
    height: '62',
    loading: 'lazy',
  });
  image.addEventListener('error', () => {
    if (!image.src.endsWith(placeholder)) image.src = placeholder;
  });
  return image;
}

export async function initializeFilmList(identity) {
  const query = document.querySelector('#admin-film-search'),
    filter = document.querySelector('#film-status-filter');
  const date = taipeiFormatter('zh-TW', { dateStyle: 'short' }),
    time = taipeiFormatter('zh-TW', { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' });
  const list = adminTable({
    identity,
    module: 'films',
    noun: '電影',
    colspan: 8,
    ids: {
      rows: 'film-rows',
      status: 'film-list-status',
      page: 'film-page',
      previous: 'film-previous',
      next: 'film-next',
      add: 'add-film',
      reload: 'reload-films',
      retry: 'retry-publication',
    },
    search: query,
    filters: [filter],
    fetchPage: ({ page, pageSize }) =>
      filmsApi.list({ q: query.value.trim(), status: filter.value, page, pageSize }),
    cells: film => {
      const first = film.screenings[0];
      return [
        poster(film),
        first ? date.format(new Date(first.startsAt)) : '尚未安排',
        film.title,
        film.genre,
        first ? time.format(new Date(first.startsAt)) : '—',
        film.screenings.length,
        publicationLabel(film),
      ];
    },
    itemName: film => film.title,
    editHref: film => '/admin/film-edit.html?id=' + encodeURIComponent(film.id),
    deleteConfirm: film => ['刪除電影', `確定刪除「${film.title}」？刪除後場次與前台詳情也會下架。`],
    remove: film => filmsApi.delete(film.id, film.version),
    retry: () => filmsApi.retry(),
    onRendered: (_result, state) => {
      document.querySelector('#film-total').textContent = `${state.total} 筆電影資料`;
    },
  });
  await list.load();
}
