import { element } from '../core/ui.js';

// 候選卡先顯示資料來源；使用者確認帶入後，由共用匯入流程補齊空白欄位。
export function renderPlaceReference(container, item) {
  container.replaceChildren();
  container.hidden = !item;
  if (!item) return;
  container.append(element('span', { className: 'google-maps-attribution', translate: 'no' }, 'Google Maps'));
  container.append(element('h3', {}, item.name + '｜地點參考'));
  container.append(element('p', {}, item.address || '未提供地址'));
  container.append(
    element(
      'p',
      { className: 'google-editorial-summary' },
      item.editorialSummary || 'Google 未提供此地點的簡介。'
    )
  );
  container.append(
    element(
      'p',
      { className: 'helper-text' },
      '確認帶入後，Google 簡介只填入空白的列表摘要及景點介紹；已有內容保留。Google 未提供簡介則留空，按儲存後才保存。請確認內容使用與保存授權。'
    )
  );
  if (item.mapUrl)
    container.append(
      element(
        'a',
        { href: item.mapUrl, target: '_blank', rel: 'noopener noreferrer' },
        '在 Google Maps 查看 ↗'
      )
    );
  for (const provider of item.attributions || [])
    container.append(
      provider.providerUri
        ? element(
            'a',
            { href: provider.providerUri, target: '_blank', rel: 'noopener noreferrer' },
            provider.provider
          )
        : element('span', {}, provider.provider)
    );
}
