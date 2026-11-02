import { contentAdminApi } from '../api/content-admin-api.js';
import { fromTaipeiInput, toTaipeiInput } from '../core/taipei-time.js';
import { hasPermission, handleAdminError } from '../core/session.js';
import { status, formErrors, submitForm, unsavedChanges, flash } from '../core/ui.js';
import { initializeContentBodyEditor } from '../components/content-body-editor.js';
import { adminFormErrors, confirmDiscard, redirectBackLinks } from '../core/admin-form.js';

const common = [
  'title',
  'slug',
  'status',
  'coverUrl',
  'coverMediaId',
  'coverAlt',
  'coverSource',
  'coverLicense',
  'seoTitle',
  'seoDescription',
];

export async function initializeContentForm(identity, module, page) {
  const creating = page.endsWith('-add'),
    service = contentAdminApi(module),
    form = document.querySelector('#content-form');
  const message = document.querySelector('#content-form-status'),
    mediaMessage = document.querySelector('#content-media-status'),
    preview = document.querySelector('#content-image-preview');
  let id = creating ? null : new URLSearchParams(location.search).get('id'),
    version = 0,
    dirty = false,
    busy = false,
    loaded = creating,
    completed = false,
    generation = 0;
  const fields = [
    ...common,
    ...(module === 'news'
      ? ['type', 'summary', 'body', 'eventLabel', 'eventLocation']
      : ['merchantId', 'discount', 'description', 'terms', 'validFrom', 'validTo']),
  ];
  const dates = module === 'news' ? ['publishAt', 'eventStartAt', 'eventEndAt'] : ['publishAt'];
  const allowed = () => !identity.preview?.active && hasPermission(identity, module, id ? 'edit' : 'create');
  const available = () => allowed() && loaded && !busy && !completed;
  const cancel = document.querySelector('#content-cancel');
  redirectBackLinks(identity, module, cancel, `/admin/${module === 'news' ? 'news' : 'offer'}-add.html`);
  function controls() {
    for (const node of form.querySelectorAll('input,textarea,select,button')) node.disabled = !available();
    document.querySelector('#reload-content-form').disabled = busy || completed;
    form.elements.coverAlt.required = Boolean(
      form.elements.coverUrl.value.trim() ||
      form.elements.coverMediaId.value ||
      form.elements.image.files.length
    );
    merchants?.refresh();
  }
  const setBusy = value => {
    busy = value;
    controls();
  };
  const changed = () => {
    dirty = true;
    controls();
  };
  const editor = initializeContentBodyEditor(form, service, module, available, setBusy, changed);
  const merchants =
    module === 'offers'
      ? (await import('../components/offer-merchants.js')).offerMerchants(
          identity,
          form,
          available,
          setBusy,
          changed
        )
      : null;
  function photo() {
    const url = form.elements.coverUrl.value || preview.dataset.mediaUrl || '';
    preview.hidden = !url;
    preview.src = url || '/assets/image-placeholder.svg';
    preview.alt = form.elements.coverAlt.value || '封面預覽';
  }
  preview.addEventListener('error', () => {
    if (!preview.src.endsWith('/assets/image-placeholder.svg')) {
      preview.src = '/assets/image-placeholder.svg';
      status(mediaMessage, '圖片無法顯示，請確認網址。', true);
    }
  });
  form.addEventListener('input', changed);
  form.addEventListener('change', changed);
  unsavedChanges(() => dirty);
  form.elements.coverUrl.addEventListener('input', () => {
    form.elements.coverMediaId.value = '';
    preview.dataset.mediaUrl = '';
    photo();
  });
  form.elements.coverAlt.addEventListener('input', photo);
  document.querySelector('#reload-content-form').hidden = creating;
  async function load() {
    if (!id) {
      status(message, '找不到要編輯的內容，請返回清單重新開啟。', true);
      loaded = false;
      controls();
      return;
    }
    const request = ++generation;
    setBusy(true);
    loaded = false;
    try {
      const { record } = await service.get(id);
      if (request !== generation) return;
      for (const name of fields) form.elements[name].value = record[name] ?? '';
      for (const name of dates) form.elements[name].value = toTaipeiInput(record[name]);
      form.elements.image.value = '';
      preview.dataset.mediaUrl = record.coverMediaId ? record.image : '';
      if (merchants) await merchants.select(record.merchantId);
      version = record.version;
      loaded = true;
      dirty = false;
      formErrors(form, { fieldErrors: {} });
      photo();
      editor?.render();
      status(message, '資料已載入。');
    } catch (error) {
      if (request === generation) handleAdminError(error, message);
    } finally {
      if (request === generation) setBusy(false);
    }
  }
  document.querySelector('#reload-content-form').addEventListener('click', async event => {
    if (busy || completed) return;
    if (await confirmDiscard(dirty, event.currentTarget, '重新載入內容')) await load();
  });
  document.querySelector('#upload-content-image').addEventListener('click', async () => {
    if (!available()) return;
    const file = form.elements.image.files[0],
      alt = form.elements.coverAlt;
    if (!file || !alt.value.trim()) {
      status(mediaMessage, '請選擇圖片並填寫替代文字。', true);
      alt.focus();
      return;
    }
    const body = new FormData();
    body.set('image', file);
    body.set('module', module);
    body.set('action', id ? 'edit' : 'create');
    for (const [key, name] of Object.entries({
      alt: 'coverAlt',
      source: 'coverSource',
      license: 'coverLicense',
    }))
      body.set(key, form.elements[name].value);
    setBusy(true);
    status(mediaMessage, '上傳與檢查圖片中…');
    try {
      const { media } = await service.upload(body);
      form.elements.coverMediaId.value = media.id;
      form.elements.coverUrl.value = '';
      form.elements.image.value = '';
      preview.dataset.mediaUrl = media.url;
      photo();
      dirty = true;
      status(mediaMessage, `圖片已上傳（${media.width} × ${media.height}），儲存內容後套用。`);
    } catch (error) {
      handleAdminError(error, mediaMessage);
      formErrors(form, error);
    } finally {
      setBusy(false);
    }
  });
  form.addEventListener('submit', event => {
    event.preventDefault();
    if (!available()) return;
    if (form.elements.image.files.length) {
      status(message, '選擇的封面尚未上傳，請先上傳圖片。', true);
      message.focus();
      return;
    }
    const body = Object.fromEntries(fields.map(name => [name, form.elements[name].value]));
    for (const name of dates) body[name] = fromTaipeiInput(form.elements[name].value);
    if (id) body.version = version;
    void submitForm(
      form,
      async () => {
        setBusy(true);
        try {
          const result = await service.save(id, body);
          id = result.record.id;
          version = result.record.version;
          dirty = false;
          completed = true;
          if (result.publication.status === 'succeeded' && hasPermission(identity, module, 'view')) {
            flash((module === 'offers' ? '優惠' : '消息') + '已儲存，公開頁面已同步。');
            location.assign('/admin/' + module + '.html');
          } else {
            status(message, result.publication.message, result.publication.status !== 'succeeded');
            document.querySelector('#content-another').hidden = !hasPermission(identity, module, 'create');
            message.focus();
          }
        } finally {
          setBusy(false);
        }
      },
      { canSubmit: available, statusNode: message, onError: adminFormErrors(message) }
    );
  });
  controls();
  if (merchants) await merchants.load();
  if (!creating) await load();
  else photo();
  controls();
}
