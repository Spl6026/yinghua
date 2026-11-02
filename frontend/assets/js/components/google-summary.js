import { element } from '../core/ui.js';
import { api } from '../core/api-client.js';

function safeLink(value) {
  try {
    const url = new URL(value);
    return ['https:', 'http:'].includes(url.protocol) && !url.username && !url.password ? url.href : '';
  } catch {
    return '';
  }
}

// Google 原文只顯示在目前的 DOM，不覆寫網站介紹、SEO 或任何儲存欄位。
export async function loadGoogleSummary(host, fetcher = api) {
  host.setAttribute('aria-live', 'polite');
  host.setAttribute('aria-busy', 'true');
  try {
    const value = await fetcher(
      '/api/v1/attractions/' + encodeURIComponent(host.dataset.googleSummary) + '/google-summary'
    );
    if (value.status !== 'available' || !value.text) return;
    const box = element('section', {
      className: 'google-summary-reference',
      'aria-label': 'Google 地點簡介',
      translate: 'no',
    });
    box.append(element('span', { className: 'google-maps-attribution', translate: 'no' }, 'Google Maps'));
    box.append(element('p', { className: 'google-editorial-summary' }, value.text));
    if (safeLink(value.mapUrl))
      box.append(
        element(
          'a',
          { href: safeLink(value.mapUrl), target: '_blank', rel: 'noopener noreferrer' },
          '在 Google Maps 查看 ↗'
        )
      );
    for (const provider of value.attributions || [])
      box.append(
        safeLink(provider.providerUri)
          ? element(
              'a',
              { href: safeLink(provider.providerUri), target: '_blank', rel: 'noopener noreferrer' },
              provider.provider
            )
          : element('span', {}, provider.provider)
      );
    host.replaceChildren(box);
  } catch {
    // 無簡介、配額、逾時或下架均保持網站原有空狀態，不以來源錯誤覆蓋全頁。
  } finally {
    host.removeAttribute('aria-busy');
  }
}
if (typeof document !== 'undefined')
  for (const host of document.querySelectorAll('[data-google-summary]')) void loadGoogleSummary(host);
