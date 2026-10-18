import { element } from '../core/ui.js';

// [U-10／E-21] Google 原文只在本頁參考，不進 form 欄位、保存請求或公開內容。
export function renderPlaceReference(container, item) {
  container.replaceChildren();container.hidden = !item;
  if (!item) return;
  container.append(element('span',{className:'google-maps-attribution',translate:'no'},'Google Maps'));
  container.append(element('h3',{},item.name+'｜地點參考'));
  container.append(element('p',{},item.address || '未提供地址'));
  container.append(element('p',{className:'google-editorial-summary'},item.editorialSummary || 'Google 未提供此地點的簡介。'));
  container.append(element('p',{className:'helper-text'},'簡介原文僅供參考，網站摘要與介紹請另行撰寫；此區內容不會隨儲存送出。'));
  if (item.mapUrl) container.append(element('a',{href:item.mapUrl,target:'_blank',rel:'noopener noreferrer'},'在 Google Maps 查看 ↗'));
  for (const provider of item.attributions || []) container.append(provider.providerUri
    ? element('a',{href:provider.providerUri,target:'_blank',rel:'noopener noreferrer'},provider.provider)
    : element('span',{},provider.provider));
}
