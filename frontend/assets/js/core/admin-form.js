// 後台新增／編輯表單共用的小規則，各表單頁面不再各寫一份、各自略有差異。
import { adminDestination, handleAdminError, hasPermission } from './session.js';
import { confirmAction } from '../components/dialog.js';

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
