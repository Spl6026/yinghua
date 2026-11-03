import { fromTaipeiInput, toTaipeiInput } from '../core/taipei-time.js';
import { filmsApi } from '../api/films-api.js';
import { hasPermission, handleAdminError } from '../core/session.js';
import { status, submitForm, formErrors, flash } from '../core/ui.js';
import { confirmAction } from '../components/dialog.js';
import { screeningEditor } from '../components/screening-editor.js';
import {
  adminFormErrors,
  confirmDiscard,
  createFormState,
  redirectBackLinks,
  uploadFormImage,
} from '../core/admin-form.js';

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
  redirectBackLinks(identity, 'films', document.querySelector('#film-cancel'), '/admin/film-add.html');
  let id = creating ? null : new URLSearchParams(location.search).get('id');
  let version;
  const state = createFormState({
    writable: () => !identity.preview?.active && hasPermission(identity, 'films', id ? 'edit' : 'create'),
    loaded: creating,
    render: () => controls(),
  });
  const { available, writable: writeAllowed } = state;
  const preview = document.querySelector('#film-image-preview'),
    mediaMessage = document.querySelector('#film-media-status');
  const screenings = screeningEditor(document.querySelector('#film-screenings'), () =>
    state.markDirty({ refresh: false })
  );
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
    document.querySelector('#reload-film').disabled = state.busy || state.completed;
    document.querySelector('#retry-film-publication').disabled =
      state.busy || !writeAllowed() || !hasPermission(identity, 'films', 'edit');
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
  form.addEventListener('input', () => state.markDirty());
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
    photo('');
    state.markDirty();
  });
  async function load() {
    if (!id) {
      state.unavailable();
      status(message, '找不到要編輯的電影，請返回清單重新開啟。', true);
      return;
    }
    await state.load(() => filmsApi.get(id), {
      apply: ({ film }) => {
        for (const name of fields) form.elements[name].value = film[name] ?? '';
        form.elements.posterMode.value = film.posterMediaId ? 'upload' : 'url';
        form.elements.image.value = '';
        form.elements.publishAt.value = toTaipeiInput(film.publishAt);
        screenings.load(film.screenings);
        version = film.version;
        photo(film.image);
        formErrors(form, { fieldErrors: {} });
        status(message, '資料已載入。');
      },
      onError: error => handleAdminError(error, message),
    });
  }
  document.querySelector('#reload-film').hidden = creating;
  document.querySelector('#reload-film').addEventListener('click', async event => {
    if (state.busy || state.completed) return;
    if (await confirmDiscard(state.dirty, event.currentTarget, '重新載入電影')) await load();
  });
  document.querySelector('#upload-film-image').addEventListener('click', async () => {
    if (!available() || !uploadMode()) return;
    await uploadFormImage({
      form,
      fields: { alt: 'posterAlt', source: 'posterSource', license: 'posterLicense' },
      module: 'films',
      creating: !id,
      message: mediaMessage,
      setBusy: state.setBusy,
      upload: filmsApi.upload,
      label: '海報',
      savedAs: '電影',
      onUploaded: media => {
        form.elements.posterMediaId.value = media.id;
        form.elements.posterUrl.value = '';
        photo(media.url);
        state.markDirty({ refresh: false });
      },
    });
  });
  document.querySelector('#retry-film-publication').addEventListener('click', async () => {
    if (state.busy || !writeAllowed() || !hasPermission(identity, 'films', 'edit')) return;
    await state.run(async () => {
      try {
        status(message, (await filmsApi.retry()).message);
      } catch (error) {
        handleAdminError(error, message);
      }
    });
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
    body.publishAt = fromTaipeiInput(publishAt);
    if (id) body.version = version;
    void submitForm(
      form,
      async () => {
        await state.run(async () => {
          body.screenings = screenings.read();
          const result = await filmsApi.save(id, body);
          id = result.film.id;
          version = result.film.version;
          state.markSaved();
          if (result.publication.status === 'succeeded') {
            state.complete();
            if (hasPermission(identity, 'films', 'view')) {
              flash('電影已儲存，公開頁面已同步。');
              location.assign('/admin/films.html');
            } else {
              status(message, result.publication.message || '電影已儲存。');
              document.querySelector('#film-another').hidden = !hasPermission(identity, 'films', 'create');
              message.focus();
            }
          } else {
            // 已儲存但公開頁未同步：有編輯權限就能開啟編輯頁，網址換成編輯頁，重新整理才不會回到新增表單。
            if (hasPermission(identity, 'films', 'edit'))
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
        });
      },
      { canSubmit: available, statusNode: message, onError: adminFormErrors(message) }
    );
  });
  if (!creating) await load();
  else photo('');
  controls();
}
