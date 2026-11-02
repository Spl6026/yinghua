import { attractionsApi } from '../api/attractions-api.js';
import { element, status } from '../core/ui.js';
import { handleAdminError } from '../core/session.js';
import { confirmAction } from './dialog.js';
import { applyAttractionSample } from '../core/attraction-sample.js';

// 讀取資料庫中的範例景點，使用者確認後才試填表單；任何情況下都不會自動儲存。
export function attractionSamplePicker(container, form, openingEditor, canEdit, onApplied) {
  if (!container) return { setDisabled() {}, async load() {} };
  const select = container.querySelector('select');
  const apply = container.querySelector('#apply-place-sample');
  const reload = container.querySelector('#load-place-samples');
  const message = container.querySelector('[role="status"]');
  let items = [],
    loading = false,
    disabled = true,
    confirming = false;
  function controls() {
    select.disabled = disabled || loading || confirming || items.length === 0;
    apply.disabled = select.disabled || !select.value;
    reload.disabled = disabled || loading || confirming;
  }
  select.addEventListener('change', controls);
  apply.addEventListener('click', async () => {
    const selectedId = select.value;
    const item = items.find(value => value.id === selectedId);
    if (!canEdit() || disabled || loading || confirming || !item) return;
    confirming = true;
    controls();
    try {
      const confirmed = await confirmAction(
        '帶入現有景點資料',
        `以「${item.name}」的基本資料填入表單，取代目前文字與營業時間，並解除目前 Google 地點關聯。圖片、網址識別及發布設定保留，仍須自行儲存。`,
        apply
      );
      if (!confirmed || !canEdit() || disabled || selectedId !== select.value) return;
      applyAttractionSample(form, item, openingEditor);
      onApplied();
      status(message, '已帶入資料，尚未儲存；請核對內容後再儲存。');
      form.elements.name.focus();
    } finally {
      confirming = false;
      controls();
    }
  });
  async function load() {
    if (!canEdit() || disabled || loading || confirming) return;
    loading = true;
    items = [];
    controls();
    status(message, '載入景點清單中…');
    select.replaceChildren(element('option', { value: '' }, '請選擇景點'));
    try {
      const result = await attractionsApi.samples();
      items = result.items;
      for (const item of items) select.append(element('option', { value: item.id }, item.name));
      status(
        message,
        items.length ? '可從現有景點帶入資料，或直接填寫。' : '目前沒有可帶入的景點，請直接填寫。'
      );
    } catch (error) {
      handleAdminError(error, message);
    } finally {
      loading = false;
      controls();
    }
  }
  reload.addEventListener('click', load);
  return {
    setDisabled(value) {
      disabled = value;
      controls();
    },
    load,
  };
}
