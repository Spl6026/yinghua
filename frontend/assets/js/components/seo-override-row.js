// SEO 設定頁「各頁覆寫」的一列：一個可展開的頁面或單筆內容，含覆寫欄位、清空關鍵字、清除按鈕與預覽區。
// 列表、預覽與儲存流程留在頁面；這裡只負責建立一列的畫面並回傳之後要讀取的欄位。
import { element } from '../core/ui.js';
import { contentKind } from '../core/content-kinds.js';
import { seoField, robotsChoices } from './seo-fields.js';

const overrideFields = [
  ['title', 'SEO 標題（建議約 30 中文字）', 160],
  ['description', '搜尋摘要（建議 70–100 中文字）', 500, 'textarea'],
  ['keywords', '頁面關鍵字', 1000],
  ['ogImageUrl', '分享圖 HTTPS 網址', 2000, 'url'],
  ['robots', '搜尋索引設定', 40],
  ['canonicalPath', '標準網址路徑（Canonical）', 180],
];

/**
 * @param {object} options
 * @param {{type:string,path?:string,id?:string}} options.target 頁面路徑或單筆內容
 * @param {object} options.existing 已儲存的覆寫值
 * @param {string} options.label 展開列的標題
 * @param {object} options.page 頁面目錄項目或內容摘要（含 protected、slug、預設標題等）
 * @param {number} options.index 欄位名稱用的序號，伺服器回傳的欄位錯誤依此對回
 * @param {() => void} options.onClear 按下「清除本頁覆寫」並清空欄位後呼叫
 */
export function seoOverrideRow({ target, existing = {}, label = '', page = {}, index, onClear }) {
  const detail = element('details', { className: 'seo-page-config' });
  const summary = element('summary', {}, label || target.path || target.id);
  detail.append(summary);
  const grid = element('div', { className: 'admin-form-grid seo-page-fields' });
  detail.append(grid);
  const inputs = {};
  for (const [field, title, max, type] of overrideFields) {
    // 保護頁是固定的靜態 shell，發布時不會套用覆寫值；因此鎖定全部欄位，避免預覽顯示實際上不會生效的標題。
    const locked =
      Boolean(page.protected) ||
      (field === 'canonicalPath' && (target.type !== 'page' || target.path.includes('*')));
    const fallback =
      field === 'title'
        ? page.seoTitle || page.title
        : field === 'description'
          ? page.seoDescription || page.description
          : field === 'canonicalPath'
            ? target.path
            : '';
    const value = page.protected ? (field === 'robots' ? 'noindex,follow' : '') : (existing[field] ?? '');
    const control = seoField(title, `overrides.${index}.${field}`, value, {
      type,
      max,
      disabled: locked,
      choices: field === 'robots' ? [['', '繼承設定'], ...robotsChoices] : undefined,
      placeholder: fallback || '留空繼承',
      hint: page.protected
        ? field === 'title'
          ? '登入、會員、搜尋等保護頁使用固定標題與摘要且不索引，不提供頁面覆寫。'
          : ''
        : field === 'title'
          ? '留空時沿用內容或全站預設；建議字數僅供參考。'
          : '',
    });
    inputs[field] = control.input;
    grid.append(control.wrapper);
  }
  const clearKeywords = element('input', {
    type: 'checkbox',
    id: `seo-clear-keywords-${index}`,
    name: `clear-keywords-${index}`,
  });
  clearKeywords.checked = !page.protected && existing.keywords === '';
  clearKeywords.disabled = Boolean(page.protected);
  if (page.protected) clearKeywords.dataset.seoProtected = 'true';
  const clearLabel = element('label', { for: clearKeywords.id }, '清空此頁關鍵字（不繼承）');
  const clearWrap = element('div', { className: 'admin-field full' });
  clearWrap.append(clearKeywords, clearLabel);
  grid.append(clearWrap);
  const note = element(
    'p',
    { className: 'helper-text' },
    target.type === 'page'
      ? '逐欄留空可回到全站或模板預設。'
      : '單筆覆寫優先於內容編輯頁的 SEO；清除後才會沿用內容值。'
  );
  grid.append(note);
  const remove = element('button', { type: 'button', className: 'button button-outline' }, '清除本頁覆寫');
  remove.addEventListener('click', () => {
    for (const input of Object.values(inputs)) if (!input.disabled) input.value = '';
    clearKeywords.checked = false;
    onClear();
  });
  grid.append(remove);
  const preview = element('pre', { className: 'seo-preview-box', 'aria-label': '有效 SEO 預覽' });
  grid.append(preview);
  const previewPath =
    target.type === 'page'
      ? target.path.replace('*', 'example')
      : `/${contentKind(target.type).segment}/${page.slug}.html`;
  return { target, inputs, clearKeywords, detail, summary, preview, previewPath, page };
}
