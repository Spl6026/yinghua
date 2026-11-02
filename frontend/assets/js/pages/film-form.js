import { filmsApi } from '../api/films-api.js';
import { hasPermission, handleAdminError } from '../core/session.js';
import { status, submitForm, unsavedChanges, formErrors, flash } from '../core/ui.js';
import { confirmAction } from '../components/dialog.js';
import { screeningEditor } from '../components/screening-editor.js';

const fields = [
  'title',
  'genre',
  'synopsis',
  'posterUrl',
  'posterMediaId',
  'posterAlt',
  'posterSource',
  'posterLicense',
  'ticketUrl',
  'slug',
  'status',
  'seoTitle',
  'seoDescription',
];

export async function initializeFilmForm(identity, page) {
  const form = document.querySelector('#film-form'),
    message = document.querySelector('#film-form-status');
  const creating = page === 'film-add';
  if (!hasPermission(identity, 'films', 'view')) {
    const cancel = document.querySelector('#film-cancel');
    cancel.href = '/index.html';
    cancel.textContent = '返回網站';
    const back = document.querySelector('.admin-preview');
    back.href = cancel.href;
    back.textContent = cancel.textContent;
  }
  let id = creating ? null : new URLSearchParams(location.search).get('id');
  let version,
    loaded = creating,
    busy = false,
    dirty = false,
    completed = false,
    generation = 0;
  const preview = document.querySelector('#film-image-preview'),
    mediaMessage = document.querySelector('#film-media-status');
  const screenings = screeningEditor(document.querySelector('#film-screenings'), () => {
    dirty = true;
  });
  const writeAllowed = () =>
    !identity.preview?.active && hasPermission(identity, 'films', id ? 'edit' : 'create');
  const available = () => writeAllowed() && loaded && !busy && !completed;
  const uploadMode = () => form.elements.posterMode.value === 'upload';
  function controls() {
    form.querySelectorAll('input,select,textarea,button[data-write]').forEach(node => {
      node.disabled = !available();
    });
    form.querySelector('[type="submit"]').disabled = !available();
    form.elements.posterUrl.disabled = !available() || uploadMode();
    form.elements.image.disabled = !available() || !uploadMode();
    document.querySelector('#film-url-fields').hidden = uploadMode();
    document.querySelector('#film-upload-fields').hidden = !uploadMode();
    document.querySelector('#reload-film').disabled = busy || completed;
    document.querySelector('#retry-film-publication').disabled =
      busy || !writeAllowed() || !hasPermission(identity, 'films', 'edit');
    form.elements.posterAlt.required = Boolean(
      form.elements.posterUrl.value.trim() ||
      form.elements.posterMediaId.value ||
      form.elements.image.files.length
    );
    screenings.setDisabled(!available());
  }
  function photo(url) {
    preview.hidden = !url;
    preview.src = url || '/assets/image-placeholder.svg';
    preview.alt = form.elements.posterAlt.value || '電影海報預覽';
    status(
      mediaMessage,
      location.protocol === 'https:' && /^http:/.test(url)
        ? 'HTTP 圖片可能被瀏覽器封鎖，建議改用 HTTPS 網址。'
        : ''
    );
  }
  preview.addEventListener('error', () => {
    if (!preview.src.endsWith('/assets/image-placeholder.svg')) {
      preview.src = '/assets/image-placeholder.svg';
      status(mediaMessage, '無法載入海報，請檢查網址或圖片。', true);
    }
  });
  form.addEventListener('input', () => {
    dirty = true;
    controls();
  });
  form.elements.posterUrl.addEventListener('input', () => {
    form.elements.posterMediaId.value = '';
    photo(form.elements.posterUrl.value);
  });
  form.elements.posterAlt.addEventListener('input', () => {
    preview.alt = form.elements.posterAlt.value || '電影海報預覽';
  });
  form.elements.posterMode.addEventListener('change', () => {
    if (uploadMode()) {
      form.elements.posterUrl.value = '';
    } else {
      form.elements.posterMediaId.value = '';
      form.elements.image.value = '';
    }
    dirty = true;
    photo('');
    controls();
  });
  unsavedChanges(() => dirty);
  async function load() {
    if (!id) {
      loaded = false;
      controls();
      status(message, '找不到要編輯的電影，請返回清單重新開啟。', true);
      return;
    }
    const request = ++generation;
    busy = true;
    loaded = false;
    controls();
    try {
      const { film } = await filmsApi.get(id);
      if (request !== generation) return;
      for (const name of fields) form.elements[name].value = film[name] ?? '';
      form.elements.posterMode.value = film.posterMediaId ? 'upload' : 'url';
      form.elements.image.value = '';
      const date = new Intl.DateTimeFormat('sv-SE', {
        timeZone: 'Asia/Taipei',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
      }).format(new Date(film.publishAt));
      form.elements.publishAt.value = date.replace(' ', 'T');
      screenings.load(film.screenings);
      version = film.version;
      loaded = true;
      dirty = false;
      photo(film.image);
      formErrors(form, { fieldErrors: {} });
      status(message, '資料已載入。');
    } catch (error) {
      if (request === generation) handleAdminError(error, message);
    } finally {
      if (request === generation) {
        busy = false;
        controls();
      }
    }
  }
  document.querySelector('#reload-film').hidden = creating;
  document.querySelector('#reload-film').addEventListener('click', async event => {
    if (busy || completed) return;
    if (!dirty || (await confirmAction('重新載入電影', '這會放棄尚未儲存的修改。', event.currentTarget)))
      await load();
  });
  document.querySelector('#upload-film-image').addEventListener('click', async () => {
    if (!available() || !uploadMode()) return;
    const image = form.elements.image.files[0];
    if (!image || !form.elements.posterAlt.value.trim()) {
      status(mediaMessage, '請選擇圖片並填寫替代文字。', true);
      mediaMessage.focus();
      return;
    }
    const body = new FormData();
    body.set('image', image);
    body.set('module', 'films');
    body.set('action', id ? 'edit' : 'create');
    for (const [key, field] of Object.entries({
      alt: 'posterAlt',
      source: 'posterSource',
      license: 'posterLicense',
    }))
      body.set(key, form.elements[field].value);
    busy = true;
    controls();
    status(mediaMessage, '上傳與驗證海報中…');
    try {
      const { media } = await filmsApi.upload(body);
      form.elements.posterMediaId.value = media.id;
      form.elements.posterUrl.value = '';
      form.elements.image.value = '';
      photo(media.url);
      dirty = true;
      status(mediaMessage, `海報已上傳（${media.width} × ${media.height}）；儲存電影後才套用。`);
    } catch (error) {
      handleAdminError(error, mediaMessage);
      formErrors(form, error);
    } finally {
      busy = false;
      controls();
    }
  });
  document.querySelector('#retry-film-publication').addEventListener('click', async () => {
    if (busy || !writeAllowed() || !hasPermission(identity, 'films', 'edit')) return;
    busy = true;
    controls();
    try {
      status(message, (await filmsApi.retry()).message);
    } catch (error) {
      handleAdminError(error, message);
    } finally {
      busy = false;
      controls();
    }
  });
  form.addEventListener('submit', event => {
    event.preventDefault();
    if (!available()) return;
    if (form.elements.image.files.length) {
      status(message, '選擇的海報尚未上傳，請先按「上傳海報」。', true);
      message.focus();
      return;
    }
    const body = Object.fromEntries(fields.map(name => [name, form.elements[name].value]));
    const publishAt = form.elements.publishAt.value;
    body.publishAt = publishAt
      ? (publishAt.length === 16 ? publishAt + ':00' : publishAt.slice(0, 19)) + '+08:00'
      : '';
    if (id) body.version = version;
    void submitForm(
      form,
      async () => {
        busy = true;
        controls();
        try {
          body.screenings = screenings.read();
          const result = await filmsApi.save(id, body);
          id = result.film.id;
          version = result.film.version;
          dirty = false;
          if (result.publication.status === 'succeeded') {
            completed = true;
            if (hasPermission(identity, 'films', 'view')) {
              flash('電影已儲存，公開頁面已同步。');
              location.assign('/admin/films.html');
            } else {
              status(message, result.publication.message || '電影已儲存。');
              document.querySelector('#film-another').hidden = !hasPermission(identity, 'films', 'create');
              message.focus();
            }
          } else {
            if (hasPermission(identity, 'films', 'edit') && hasPermission(identity, 'films', 'view'))
              history.replaceState(null, '', '/admin/film-edit.html?id=' + encodeURIComponent(id));
            status(
              message,
              result.publication.message +
                (hasPermission(identity, 'films', 'edit')
                  ? ''
                  : ' 資料已保存，請勿重複新增；請聯絡具編輯權限的管理員重試同步。'),
              true
            );
            document.querySelector('#retry-film-publication').hidden = false;
          }
        } finally {
          busy = false;
          controls();
        }
      },
      { canSubmit: available, statusNode: message, onError: error => handleAdminError(error, message) }
    );
  });
  if (!creating) await load();
  else photo('');
  controls();
}
