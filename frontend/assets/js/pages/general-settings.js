import { confirmAction } from '../components/dialog.js';
import { api } from '../core/api-client.js';
import { hasPermission, handleAdminError } from '../core/session.js';
import { element, status, formErrors, unsavedChanges } from '../core/ui.js';
import { seoField, seoPanel } from '../components/seo-fields.js';

// 網站名稱與摘要和 SEO 設定共用，但兩份文件各自有獨立的衝突保護。
export async function initializeGeneralSettings(identity) {
  const form = document.querySelector('#general-settings-form'),
    message = document.querySelector('#general-status');
  const reload = document.querySelector('#reload-general');
  let data,
    busy = false,
    dirty = false;
  const fields = new Map();
  const writable = hasPermission(identity, 'settings', 'edit') && !identity.preview?.active;
  unsavedChanges(() => dirty);
  async function load() {
    if (busy) return;
    busy = true;
    reload.disabled = true;
    status(message, '讀取一般設定中…');
    try {
      data = await api('/api/v1/admin/settings', { kind: 'admin' });
      form.replaceChildren();
      fields.clear();
      const { panel, grid } = seoPanel('基本網站資訊與政策', '共用於全站頁尾、預設 SEO 與會員同意紀錄');
      panel.className = 'admin-panel general-settings-panel';
      const definitions = [
        ['siteName', '網站名稱', 160],
        ['description', '全站預設摘要', 500, 'textarea'],
        ['contactEmail', '聯絡信箱', 254, 'email'],
        ['termsVersion', '會員服務條款版本', 80],
        ['termsText', '會員服務條款', 20000, 'textarea'],
        ['privacyVersion', '隱私權政策版本', 80],
        ['privacyText', '隱私權政策', 20000, 'textarea'],
      ];
      for (const [key, label, max, type] of definitions) {
        const field = seoField(label, 'general.' + key, key in data ? data[key] : data.values[key], {
          max,
          type,
          disabled: !writable,
          hint: key.endsWith('Text') ? '純文字，每行一段；留空時使用網站預設內容。' : '',
        });
        fields.set(key, field.input);
        grid.append(field.wrapper);
      }
      const save = element(
        'button',
        { type: 'submit', className: 'button button-dark' },
        '儲存並發布一般設定 ↗'
      );
      save.disabled = !writable;
      const actions = element('div', { className: 'admin-form-actions' });
      actions.append(save);
      panel.append(actions);
      form.append(panel);
      dirty = false;
      status(message, writable ? '已讀取設定。' : '唯讀檢視設定。');
    } catch (error) {
      handleAdminError(error, message);
    } finally {
      busy = false;
      reload.disabled = false;
    }
  }
  form.addEventListener('input', () => {
    if (writable) dirty = true;
  });
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (!writable || busy || !data) return;
    busy = true;
    const save = form.querySelector('[type="submit"]');
    save.disabled = true;
    reload.disabled = true;
    for (const input of fields.values()) input.disabled = true;
    formErrors(form, { fieldErrors: {} });
    status(message, '儲存中…');
    const values = Object.fromEntries(
      [...fields]
        .filter(([key]) => !['siteName', 'description'].includes(key))
        .map(([key, input]) => [key, input.value])
    );
    try {
      data = await api('/api/v1/admin/settings', {
        kind: 'admin',
        method: 'PUT',
        body: {
          version: data.version,
          seoVersion: data.seoVersion,
          siteName: fields.get('siteName').value,
          description: fields.get('description').value,
          values,
        },
      });
      dirty = false;
      status(
        message,
        data.publicationReady ? '已儲存並發布。' : '已儲存，發布尚未完成，請稍後重新載入確認。',
        !data.publicationReady
      );
      message.focus();
      document.dispatchEvent(new CustomEvent('settings-section-saved', { detail: { section: 'general' } }));
    } catch (error) {
      for (const input of fields.values()) input.disabled = !writable;
      const mapped = {
        ...error,
        fieldErrors: Object.fromEntries(
          Object.entries(error.fieldErrors || {}).map(([key, value]) => [
            'general.' + key.replace(/^values\./, ''),
            value,
          ])
        ),
      };
      handleAdminError(error, message);
      formErrors(form, mapped);
    } finally {
      busy = false;
      save.disabled = false;
      reload.disabled = false;
      for (const input of fields.values()) input.disabled = !writable;
    }
  });
  // 兩區共用網站名稱、摘要與版本號；SEO 區儲存後，若一般區沒有未存的修改就自動重讀，避免下次儲存被誤判為他人更新。
  document.addEventListener('settings-section-saved', event => {
    if (event.detail?.section !== 'general' && !dirty && !busy) void load();
  });
  reload.addEventListener('click', async () => {
    if (!dirty || (await confirmAction('重新讀取一般設定', '放棄尚未儲存的一般設定？', reload))) await load();
  });
  await load();
}
