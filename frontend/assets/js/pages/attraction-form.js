import { fromTaipeiInput, toTaipeiInput } from '../core/taipei-time.js';
import { attractionSamplePicker } from '../components/attraction-samples.js';
import { attractionsApi } from '../api/attractions-api.js';
import { hasPermission, handleAdminError } from '../core/session.js';
import { element, status, submitForm, formErrors, flash } from '../core/ui.js';
import { confirmAction } from '../components/dialog.js';
import { openingHoursEditor } from '../components/opening-hours-editor.js';
import { applyPlaceCandidate } from '../core/place-candidate.js';
import { googlePlaceLink } from '../components/google-place-link.js';
import { renderPlaceReference } from '../components/place-reference.js';
import {
  adminFormErrors,
  confirmDiscard,
  createFormState,
  redirectBackLinks,
  uploadFormImage,
} from '../core/admin-form.js';

const fields = [
  'slug',
  'name',
  'category',
  'district',
  'typeLabel',
  'summary',
  'description',
  'hours',
  'address',
  'phone',
  'transport',
  'websiteUrl',
  'mapUrl',
  'googlePlaceId',
  'mediaId',
  'seoTitle',
  'seoDescription',
  'status',
  'imageAlt',
  'imageSource',
  'imageLicense',
  'recommendationLabel',
];

export async function initializeAttractionForm(identity, page) {
  const form = document.querySelector('#place-form');
  const message = document.querySelector('#place-form-status');
  const creating = page === 'place-add';
  redirectBackLinks(
    identity,
    'attractions',
    document.querySelector('#place-cancel'),
    '/admin/place-add.html'
  );
  let id = creating ? null : new URLSearchParams(location.search).get('id');
  let version;
  const state = createFormState({
    writable: () =>
      !identity.preview?.active && hasPermission(identity, 'attractions', id ? 'edit' : 'create'),
    loaded: creating,
    render: () => controls(),
  });
  const { available, writable: writeAllowed } = state;
  const preview = document.querySelector('#place-photo-preview');
  const mediaMessage = document.querySelector('#media-status');
  const mapMessage = document.querySelector('#map-status');
  const mapField = document.querySelector('#map-url-field');
  // 查詢網址與景點保存的 mapUrl 分開，不能把保存錯誤指向查詢欄。
  const mapErrors = error =>
    formErrors(mapField, {
      fieldErrors: error.fieldErrors?.mapUrl ? { mapLookupUrl: error.fieldErrors.mapUrl } : {},
    });
  const openingEditor = openingHoursEditor(document.querySelector('#opening-hours-editor'));
  const reference = document.querySelector('#google-place-reference');
  const linkContainer = document.querySelector('#google-place-link');
  const placeLink = googlePlaceLink(form, linkContainer, () => renderPlaceReference(reference, null));
  const samples = attractionSamplePicker(
    creating ? document.querySelector('#place-samples') : null,
    form,
    openingEditor,
    available,
    () => {
      state.markDirty({ refresh: false });
      placeLink.load();
      renderPlaceReference(reference, null);
      document.querySelector('#map-candidates').replaceChildren();
    }
  );
  function controls() {
    form.querySelectorAll('input,select,textarea,button[data-write]').forEach(node => {
      node.disabled = !available();
    });
    form.querySelector('[type="submit"]').disabled = !available();
    document.querySelector('#reload-place').disabled = state.busy;
    document.querySelector('#retry-place-publication').disabled =
      state.busy || !writeAllowed() || !hasPermission(identity, 'attractions', 'edit');
    openingEditor.setDisabled(!available());
    samples.setDisabled(!available());
    placeLink.setDisabled(!available());
  }
  function photo(url) {
    preview.src = url || '/assets/image-placeholder.svg';
    preview.alt = form.elements.imageAlt.value || '景點圖片預覽';
  }
  preview.addEventListener('error', () => {
    if (!preview.src.endsWith('/assets/image-placeholder.svg')) preview.src = '/assets/image-placeholder.svg';
  });
  form.addEventListener('input', () => state.markDirty({ refresh: false }));
  async function load() {
    if (!id) {
      state.unavailable();
      status(message, '找不到要編輯的景點，請返回清單重新開啟。', true);
      return;
    }
    await state.load(() => attractionsApi.get(id), {
      apply: ({ attraction }) => {
        for (const name of fields) form.elements[name].value = attraction[name] ?? '';
        openingEditor.load(attraction.openingHours, attraction.hours);
        for (const name of ['latitude', 'longitude']) form.elements[name].value = attraction[name] ?? '';
        placeLink.load();
        renderPlaceReference(reference, null);
        for (const name of ['featured', 'isMuseum']) form.elements[name].checked = attraction[name];
        form.elements.publishAt.value = toTaipeiInput(attraction.publishAt);
        form.elements.imageAlt.value = attraction.imageAlt;
        form.elements.imageSource.value = attraction.imageSource;
        form.elements.imageLicense.value = attraction.imageLicense;
        // 確認放棄修改後，待上傳的檔案也要和伺服器資料一起重置。
        form.elements.image.value = '';
        photo(attraction.image);
        version = attraction.version;
        formErrors(form, { fieldErrors: {} });
        status(message, '資料已載入。');
      },
      onError: error => handleAdminError(error, message),
    });
  }
  document.querySelector('#reload-place').hidden = creating;
  document.querySelector('#reload-place').addEventListener('click', async event => {
    if (state.busy || state.completed) return;
    if (await confirmDiscard(state.dirty, event.currentTarget, '重新載入景點')) await load();
  });
  document.querySelector('#upload-place-image').addEventListener('click', async () => {
    if (!available()) return;
    await uploadFormImage({
      form,
      fields: { alt: 'imageAlt', source: 'imageSource', license: 'imageLicense' },
      module: 'attractions',
      creating: !id,
      message: mediaMessage,
      setBusy: state.setBusy,
      upload: attractionsApi.upload,
      label: '圖片',
      savedAs: '景點',
      onUploaded: media => {
        form.elements.mediaId.value = media.id;
        // 伺服器會整理說明文字，以回傳值為準。
        form.elements.imageAlt.value = media.alt;
        form.elements.imageSource.value = media.source;
        form.elements.imageLicense.value = media.license;
        photo(media.url);
        state.markDirty({ refresh: false });
      },
    });
  });
  document.querySelector('#parse-place-url').addEventListener('click', async () => {
    if (!available()) return;
    formErrors(mapField, { fieldErrors: {} });
    const mapUrl = document.querySelector('#google-short-url').value.trim();
    if (!mapUrl) {
      const hint = '請先輸入 Google Maps 網址。';
      status(mapMessage, hint, true);
      mapErrors({ fieldErrors: { mapUrl: hint } });
      return;
    }
    let failure;
    state.setBusy(true);
    status(mapMessage, '解析地圖資料中…');
    const candidates = document.querySelector('#map-candidates');
    candidates.replaceChildren();
    try {
      const result = await attractionsApi.resolve({ mapUrl, action: id ? 'edit' : 'create' });
      status(mapMessage, `${result.attribution}：${result.notice}`);
      for (const item of result.candidates) {
        const card = element('article', { className: 'map-candidate' });
        renderPlaceReference(card, item);
        const button = element(
          'button',
          { type: 'button', className: 'button button-outline' },
          `${item.name}｜${item.address}　帶入欄位`
        );
        button.addEventListener('click', async () => {
          if (
            !available() ||
            !(await confirmAction(
              '確認候選地點',
              `將「${item.name}」的資料帶入表單。請自行核實資料與保存授權；不會自動儲存或帶入 Google 照片。`,
              button
            ))
          )
            return;
          if (!available()) return;
          const notes = applyPlaceCandidate(form, item, openingEditor);
          placeLink.acceptCandidate();
          renderPlaceReference(reference, null);
          state.markDirty({ refresh: false });
          status(mapMessage, ['候選資料已帶入，請核實後儲存。', ...notes].join(' '));
          candidates.replaceChildren();
          form.elements[form.elements.district.value ? 'name' : 'district'].focus();
        });
        card.append(button);
        candidates.append(card);
      }
    } catch (error) {
      failure = error;
      handleAdminError(error, mapMessage);
    } finally {
      state.setBusy(false);
      // 先把控制項恢復為可操作再聚焦；API 回傳的原因以純文字關聯到解析欄位。
      if (failure) mapErrors(failure);
    }
  });
  document.querySelector('#retry-place-publication').addEventListener('click', async () => {
    if (state.busy || !writeAllowed()) return;
    await state.run(async () => {
      try {
        status(message, (await attractionsApi.retry()).message);
      } catch (error) {
        handleAdminError(error, message);
      }
    });
  });
  form.addEventListener('submit', event => {
    event.preventDefault();
    if (!available()) return;
    if (!placeLink.canSave()) {
      status(message, '請先重新確認或解除 Google 地點關聯。', true);
      linkContainer.focus();
      return;
    }
    if (form.elements.image.files.length) {
      status(message, '已選擇的新圖片尚未上傳，請先按「上傳圖片」。', true);
      return;
    }
    const body = Object.fromEntries(fields.map(name => [name, form.elements[name].value]));
    body.openingHours = openingEditor.read();
    body.googleLinkConfirmed = placeLink.confirmed();
    for (const name of ['latitude', 'longitude'])
      body[name] = form.elements[name].value.trim() === '' ? null : Number(form.elements[name].value);
    for (const name of ['featured', 'isMuseum']) body[name] = form.elements[name].checked;
    const publishAt = form.elements.publishAt.value;
    body.publishAt = fromTaipeiInput(publishAt);
    if (id) body.version = version;
    void submitForm(
      form,
      async () => {
        await state.run(async () => {
          const result = await attractionsApi.save(id, body);
          id = result.attraction.id;
          version = result.attraction.version;
          state.markSaved();
          if (result.publication.status === 'succeeded') {
            state.complete();
            if (hasPermission(identity, 'attractions', 'view')) {
              flash('景點已儲存，公開頁面已同步。');
              location.assign('/admin/places.html');
            } else {
              status(message, result.publication.message || '景點已儲存。');
              document.querySelector('#place-another').hidden = !hasPermission(
                identity,
                'attractions',
                'create'
              );
              message.focus();
            }
          } else {
            // 已儲存但公開頁未同步：有編輯權限就能開啟編輯頁，網址換成編輯頁，重新整理才不會回到新增表單。
            if (hasPermission(identity, 'attractions', 'edit'))
              history.replaceState(null, '', '/admin/place-edit.html?id=' + encodeURIComponent(id));
            status(
              message,
              result.publication.message +
                (hasPermission(identity, 'attractions', 'edit')
                  ? ''
                  : ' 本帳號沒有重試權限，請聯絡具編輯權限的管理員；資料已保存，請勿重複新增。'),
              true
            );
            document.querySelector('#retry-place-publication').hidden = false;
          }
        });
      },
      { canSubmit: available, statusNode: message, onError: adminFormErrors(message) }
    );
  });
  if (!creating) await load();
  else photo('');
  controls();
  if (creating && writeAllowed()) await samples.load();
}
