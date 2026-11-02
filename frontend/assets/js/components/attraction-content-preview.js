import { api } from '../core/api-client.js';
import { element } from '../core/ui.js';

// [U-23／E-40] 明確按預覽才請求後端共用政策，不隨每次打字送出Google請求。
export function attractionContentPreview(form, canPreview) {
  const host = element('details', {
    className: 'attraction-content-preview',
    'aria-label': '目前內容顯示預覽',
  });
  const button = element(
    'button',
    { type: 'button', className: 'button button-outline', 'data-write': '' },
    '預覽空白欄位補位'
  );
  const message = element('div', { role: 'status', 'aria-live': 'polite' });
  let pending = false,
    revision = 0;
  host.append(element('summary', {}, '內容補位預覽'), button, message);
  form.elements.description.closest('.form-field,.admin-field').after(host);
  const hint = element(
    'p',
    { className: 'helper-text' },
    '摘要留空時摘錄網站介紹；介紹留空且關聯 Google 時，公開詳情嘗試即時顯示 Google 原文。未提供或失敗時保留空狀態。'
  );
  button.before(hint);
  button.addEventListener('click', async () => {
    if (pending || !canPreview()) return;
    pending = true;
    button.setAttribute('aria-disabled', 'true');
    button.setAttribute('aria-busy', 'true');
    const requestedRevision = revision;
    try {
      const result = await api('/api/v1/admin/attractions/content-preview', {
        kind: 'admin',
        method: 'POST',
        body: {
          summary: form.elements.summary.value,
          description: form.elements.description.value,
          linked: !!form.elements.googlePlaceId.value,
        },
      });
      if (requestedRevision !== revision) return;
      const labels = {
        authored: '自填',
        'description-excerpt': '網站介紹摘錄',
        empty: '尚未提供',
        'google-pending': '公開頁即時Google原文（可能未提供）',
      };
      message.replaceChildren(
        element(
          'p',
          {},
          '列表摘要｜' + labels[result.contentSource.summary] + '：' + (result.summary || '顯示看介紹連結')
        ),
        element(
          'p',
          {},
          '景點介紹｜' +
            labels[result.contentSource.description] +
            (result.description ? '：' + result.description : '')
        )
      );
    } catch {
      message.textContent = '無法取得預覽，請稍後重試；尚未修改或儲存景點。';
    } finally {
      pending = false;
      button.removeAttribute('aria-disabled');
      button.removeAttribute('aria-busy');
      button.disabled = !canPreview();
    }
  });
  form.addEventListener('input', () => {
    revision++;
    message.textContent = '內容已變更，可重新預覽。';
  });
  return host;
}
