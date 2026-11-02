// 後台新增／編輯表單共用的小規則，各表單頁面不再各寫一份、各自略有差異。
import { adminDestination, handleAdminError, hasPermission } from './session.js';
import { confirmAction } from '../components/dialog.js';
import { formErrors, status } from './ui.js';

/**
 * 沒有列表檢視權限的人（例如只能新增）不能回到列表：「取消」與頁首返回連結改指向他能用的頁面；
 * 若那正是目前這張新增表單，就改回網站首頁，避免連結指回自己。
 */
export function redirectBackLinks(identity, module, cancel, ownAddPage) {
  if (hasPermission(identity, module, 'view')) return;
  const destination = adminDestination(identity);
  const href = destination === ownAddPage ? '/index.html' : destination;
  const text = href === '/index.html' ? '返回網站' : '返回可用頁面';
  for (const link of [cancel, document.querySelector('.admin-preview')]) {
    if (!link) continue;
    link.href = href;
    link.textContent = text;
  }
}

/** 重新載入前若有未儲存的修改先請使用者確認；回傳 true 表示可以繼續。 */
export async function confirmDiscard(isDirty, button, title) {
  return !isDirty || confirmAction(title, '這會放棄尚未儲存的修改。', button);
}

/** submitForm 的共用錯誤處理：登入逾時等情況交給 handleAdminError 統一處理。 */
export const adminFormErrors = message => error => handleAdminError(error, message);

/**
 * 只採用最後一次載入的結果：連續按「重新載入」時，較慢的舊回應不會蓋掉較新的資料。
 * 用法：const request = latest.next(); ... if (!latest.isCurrent(request)) return;
 */
export function latestRequest() {
  let current = 0;
  return { next: () => ++current, isCurrent: request => request === current };
}

/**
 * 表單內「上傳圖片」按鈕的共同流程：先檢查已選檔案與替代文字，上傳後清空檔案欄位，
 * 由 onUploaded 把媒體編號寫回表單。圖片要等表單儲存後才會套用到內容上。
 *
 * fields 為上傳欄位對應的表單欄位名稱，例如 { alt: 'coverAlt', source: 'coverSource', license: 'coverLicense' }。
 */
export async function uploadFormImage({
  form,
  fields,
  module,
  creating,
  message,
  setBusy,
  upload,
  onUploaded,
  label,
  savedAs,
}) {
  const file = form.elements.image.files[0],
    alt = form.elements[fields.alt];
  if (!file || !alt.value.trim()) {
    status(message, '請選擇圖片並填寫替代文字。', true);
    (file ? alt : message).focus();
    return;
  }
  const body = new FormData();
  body.set('image', file);
  body.set('module', module);
  body.set('action', creating ? 'create' : 'edit');
  for (const [key, field] of Object.entries(fields)) body.set(key, form.elements[field].value);
  setBusy(true);
  status(message, `上傳與檢查${label}中…`);
  try {
    const { media } = await upload(body);
    form.elements.image.value = '';
    onUploaded(media);
    status(message, `${label}已上傳（${media.width} × ${media.height}）；儲存${savedAs}後才套用。`);
  } catch (error) {
    handleAdminError(error, message);
    formErrors(form, error);
  } finally {
    setBusy(false);
  }
}
