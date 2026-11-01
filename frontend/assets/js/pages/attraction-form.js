import { attractionSamplePicker } from '../components/attraction-samples.js';
import { attractionsApi } from '../api/attractions-api.js';
import { hasPermission, handleAdminError, adminDestination } from '../core/session.js';
import { element, status, submitForm, unsavedChanges, formErrors } from '../core/ui.js';
import { confirmAction } from '../components/dialog.js';
import { openingHoursEditor } from '../components/opening-hours-editor.js';
import { applyPlaceCandidate } from '../core/place-candidate.js';
import { googlePlaceLink } from '../components/google-place-link.js';
import { renderPlaceReference } from '../components/place-reference.js';
import { attractionContentPreview } from '../components/attraction-content-preview.js';

const fields = ['slug','name','category','district','typeLabel','summary','description','hours','address','phone','transport','websiteUrl','mapUrl','googlePlaceId','mediaId','seoTitle','seoDescription','status','imageAlt','imageSource','imageLicense','recommendationLabel'];

export async function initializeAttractionForm(identity, page) {
  const form = document.querySelector('#place-form');
  const message = document.querySelector('#place-form-status');
  const creating = page === 'place-add';
  if (!hasPermission(identity, 'attractions', 'view')) {
    const cancel = document.querySelector('#place-cancel');
    cancel.href = adminDestination(identity); cancel.textContent = '返回可用功能';
    const back = document.querySelector('.admin-preview');
    back.href = cancel.href; back.textContent = cancel.textContent;
  }
  let id = creating ? null : new URLSearchParams(location.search).get('id');
  let version, loaded = creating, busy = false, dirty = false, generation = 0, completed = false;
  const preview = document.querySelector('#place-photo-preview');
  const mediaMessage = document.querySelector('#media-status');
  const mapMessage = document.querySelector('#map-status');
  const mapField = document.querySelector('#map-url-field');
  // 查詢網址與景點保存的 mapUrl 分開，不能把保存錯誤指向查詢欄。
  const mapErrors = error => formErrors(mapField, { fieldErrors: error.fieldErrors?.mapUrl ? { mapLookupUrl: error.fieldErrors.mapUrl } : {} });
  const openingEditor = openingHoursEditor(document.querySelector('#opening-hours-editor'));
  const reference = document.querySelector('#google-place-reference');
  const linkContainer = document.querySelector('#google-place-link');
  const placeLink = googlePlaceLink(form, linkContainer, () => renderPlaceReference(reference, null));
  const writeAllowed = () => !identity.preview?.active && hasPermission(identity, 'attractions', id ? 'edit' : 'create');
  const available = () => writeAllowed() && loaded && !busy && !completed;
  attractionContentPreview(form,available);
  const samples = attractionSamplePicker(creating ? document.querySelector('#place-samples') : null,
    form, openingEditor, available, () => {
      dirty = true; placeLink.load(); renderPlaceReference(reference, null);
      document.querySelector('#map-candidates').replaceChildren();
    });
  function controls() {
    form.querySelectorAll('input,select,textarea,button[data-write]').forEach(node => { node.disabled = !available(); });
    form.querySelector('[type="submit"]').disabled = !available();
    document.querySelector('#reload-place').disabled = busy;
    document.querySelector('#retry-place-publication').disabled = busy || !writeAllowed() || !hasPermission(identity, 'attractions', 'edit');
    openingEditor.setDisabled(!available());
    samples.setDisabled(!available());
    placeLink.setDisabled(!available());
  }
  function photo(url) { preview.src = url || '/assets/image-placeholder.svg'; preview.alt = form.elements.imageAlt.value || '景點圖片預覽'; }
  preview.addEventListener('error', () => { if (!preview.src.endsWith('/assets/image-placeholder.svg')) preview.src = '/assets/image-placeholder.svg'; });
  form.addEventListener('input', () => { dirty = true; });
  unsavedChanges(() => dirty);
  async function load() {
    if (!id) { loaded = false; controls(); status(message, '網址缺少景點識別，請返回清單重新開啟。', true); return; }
    const request = ++generation;
    busy = true; loaded = false; controls();
    try {
      const { attraction } = await attractionsApi.get(id);
      if (request !== generation) return;
      for (const name of fields) form.elements[name].value = attraction[name] ?? '';
      openingEditor.load(attraction.openingHours, attraction.hours);
      for (const name of ['latitude','longitude']) form.elements[name].value = attraction[name] ?? '';
      placeLink.load();renderPlaceReference(reference, null);
      for (const name of ['featured','isMuseum']) form.elements[name].checked = attraction[name];
      const date = new Intl.DateTimeFormat('sv-SE', { timeZone: 'Asia/Taipei', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit' }).format(new Date(attraction.publishAt));
      form.elements.publishAt.value = date.replace(' ', 'T');
      form.elements.imageAlt.value = attraction.imageAlt;
      form.elements.imageSource.value = attraction.imageSource;
      form.elements.imageLicense.value = attraction.imageLicense;
      // [E-18] 確認放棄修改後，待上傳檔案也必須與伺服器資料一起重置。
      form.elements.image.value = '';
      photo(attraction.image);
      version = attraction.version; loaded = true; dirty = false;
      formErrors(form, { fieldErrors: {} });
      status(message, '資料已載入。');
    } catch (error) { if (request === generation) handleAdminError(error, message); }
    finally { if (request === generation) { busy = false; controls(); } }
  }
  document.querySelector('#reload-place').hidden = creating;
  document.querySelector('#reload-place').addEventListener('click', async event => {
    if (busy || completed) return;
    if (!dirty || await confirmAction('重新載入景點', '這會放棄尚未儲存的修改。', event.currentTarget)) await load();
  });
  document.querySelector('#upload-place-image').addEventListener('click', async () => {
    if (!available()) return;
    const image = form.elements.image.files[0];
    if (!image || !form.elements.imageAlt.value.trim()) {
      status(mediaMessage, '請選擇圖片並填寫替代文字。', true); mediaMessage.focus(); return;
    }
    const body = new FormData();
    body.set('image', image); body.set('action', id ? 'edit' : 'create');
    for (const [key, field] of Object.entries({ alt: 'imageAlt', source: 'imageSource', license: 'imageLicense' })) body.set(key, form.elements[field].value);
    busy = true; controls(); status(mediaMessage, '上傳與驗證圖片中…');
    try {
      const { media } = await attractionsApi.upload(body);
      form.elements.mediaId.value = media.id;
      for (const [key, field] of Object.entries({ alt: 'imageAlt', source: 'imageSource', license: 'imageLicense' })) form.elements[field].value = media[key];
      photo(media.url); dirty = true;
      form.elements.image.value = '';
      status(mediaMessage, `圖片已上傳（${media.width} × ${media.height}）；儲存景點後才套用。`);
    } catch (error) { handleAdminError(error, mediaMessage); formErrors(form, error); }
    finally { busy = false; controls(); }
  });
  document.querySelector('#parse-place-url').addEventListener('click', async () => {
    if (!available()) return;
    formErrors(mapField, { fieldErrors: {} });
    const mapUrl = document.querySelector('#google-short-url').value.trim();
    if (!mapUrl) {
      const hint = '請先輸入 Google Maps 網址。';
      status(mapMessage, hint, true); mapErrors({ fieldErrors: { mapUrl: hint } }); return;
    }
    let failure;
    busy = true; controls(); status(mapMessage, '解析地圖資料中…');
    const candidates = document.querySelector('#map-candidates'); candidates.replaceChildren();
    try {
      const result = await attractionsApi.resolve({ mapUrl, action: id ? 'edit' : 'create' });
      status(mapMessage, `${result.attribution}：${result.notice}`);
      for (const item of result.candidates) {
        const card = element('article', { className: 'map-candidate' });
        renderPlaceReference(card, item);
        const button = element('button', { type: 'button', className: 'button button-outline' }, `${item.name}｜${item.address}　帶入欄位`);
        button.addEventListener('click', async () => {
          if (!available() || !await confirmAction('確認候選地點', `將「${item.name}」的資料帶入表單。請自行核實資料與保存授權；不會自動儲存或帶入 Google 照片。`, button)) return;
          if (!available()) return;
          const notes = applyPlaceCandidate(form, item, openingEditor);
          placeLink.acceptCandidate();renderPlaceReference(reference, item);
          dirty = true; status(mapMessage, ['候選資料已帶入，請核實後儲存。',...notes].join(' ')); candidates.replaceChildren();
          form.elements[form.elements.district.value ? 'name' : 'district'].focus();
        });
        card.append(button);candidates.append(card);
      }
    } catch (error) { failure = error; handleAdminError(error, mapMessage); }
    finally {
      busy = false; controls();
      // [S：E34/G34；E-29] 還原可操作狀態後再聚焦，API 原因以純文字關聯解析欄。
      if (failure) mapErrors(failure);
    }
  });
  document.querySelector('#retry-place-publication').addEventListener('click', async () => {
    if (busy || !writeAllowed()) return;
    busy = true; controls();
    try { status(message, (await attractionsApi.retry()).message); }
    catch (error) { handleAdminError(error, message); }
    finally { busy = false; controls(); }
  });
  form.addEventListener('submit', event => {
    event.preventDefault();
    if (!available()) return;
    if (!placeLink.canSave()) { status(message, '請先重新確認或解除 Google 地點關聯。', true);linkContainer.focus();return; }
    if (form.elements.image.files.length) { status(message, '已選擇的新圖片尚未上傳，請先按「上傳圖片」。', true); return; }
    const body = Object.fromEntries(fields.map(name => [name, form.elements[name].value]));
    body.openingHours = openingEditor.read();
    body.googleLinkConfirmed = placeLink.confirmed();
    for (const name of ['latitude','longitude']) body[name] = form.elements[name].value.trim() === '' ? null : Number(form.elements[name].value);
    for (const name of ['featured','isMuseum']) body[name] = form.elements[name].checked;
    const publishAt = form.elements.publishAt.value;
    body.publishAt = publishAt ? (publishAt.length === 16 ? publishAt + ':00' : publishAt.slice(0, 19)) + '+08:00' : '';
    if (id) body.version = version;
    void submitForm(form, async () => {
      busy = true; controls();
      try {
        const result = await attractionsApi.save(id, body);
        id = result.attraction.id; version = result.attraction.version; dirty = false;
        if (result.publication.status === 'succeeded') {
          completed = true;
          if (hasPermission(identity, 'attractions', 'view')) location.assign('/admin/places.html');
          else {
            status(message, result.publication.message || '景點已儲存。');
            document.querySelector('#place-another').hidden = !hasPermission(identity, 'attractions', 'create');
            message.focus();
          }
        }
        else {
          if (hasPermission(identity, 'attractions', 'edit') && hasPermission(identity, 'attractions', 'view')) history.replaceState(null, '', '/admin/place-edit.html?id=' + encodeURIComponent(id));
          status(message, result.publication.message + (hasPermission(identity, 'attractions', 'edit') ? '' : ' 本帳號沒有重試權限，請聯絡具編輯權限的管理員；資料已保存，請勿重複新增。'), true);
          document.querySelector('#retry-place-publication').hidden = false;
        }
      } finally { busy = false; controls(); }
    }, { canSubmit: available, statusNode: message, onError: error => handleAdminError(error, message) });
  });
  if (!creating) await load(); else photo('');
  controls();
  if (creating && writeAllowed()) await samples.load();
}
