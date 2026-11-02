import { passwordError, passwordConfirmationError } from './password-policy.js';

export function element(tag, attributes = {}, text) {
  const node = document.createElement(tag);
  for (const [key, value] of Object.entries(attributes)) {
    if (key === 'className') node.className = value;
    else node.setAttribute(key, String(value));
  }
  if (text !== undefined) node.textContent = String(text);
  return node;
}

export function status(container, message = '', error = false) {
  container.textContent = message;
  container.dataset.error = String(error);
}

// 連續出現的提示要各自顯示滿 3 秒；沿用前一則的計時器會讓新提示提早消失。
let toastTimer = 0;
export function toast(message) {
  const node = document.querySelector('#toast');
  if (!node) return;
  node.textContent = message;
  node.classList.add('show');
  window.clearTimeout(toastTimer);
  toastTimer = window.setTimeout(() => node.classList.remove('show'), 3000);
}

// 使用者回到分頁時（切回分頁、從上一頁快取還原、視窗重新取得焦點）重新確認資料。
// 這幾個事件常常同時發生，一秒內只執行一次，避免重複送出相同的請求。
export function onPageReturn(callback) {
  let last = 0;
  const run = () => {
    const now = Date.now();
    if (now - last < 1000) return;
    last = now;
    callback();
  };
  window.addEventListener('pageshow', event => {
    if (event.persisted) run();
  });
  window.addEventListener('focus', run);
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) run();
  });
}

export function formErrors(form, error) {
  form.querySelectorAll('.field-error').forEach(node => node.remove());
  form.querySelectorAll('[aria-invalid]').forEach(node => {
    node.removeAttribute('aria-invalid');
    node.removeAttribute('aria-describedby');
  });
  let first;
  for (const [name, message] of Object.entries(error.fieldErrors || {})) {
    // 複合表單（例如整週營業時間）也要顯示具體錯誤並能取得焦點；
    // 地圖解析等獨立欄位群組也共用這段邏輯，且不會清除其他表單區塊的錯誤。
    const input =
      [...form.querySelectorAll('[data-field-name]')].find(node => node.dataset.fieldName === name) ||
      form.elements?.namedItem(name);
    if (!(input instanceof HTMLElement)) continue;
    input.setAttribute('aria-invalid', 'true');
    const hint = element('span', { className: 'field-error', id: `${input.id}-error` }, message);
    input.setAttribute('aria-describedby', hint.id);
    input.closest('.form-field,.admin-field,.terms-consent')?.append(hint);
    first ??= input;
  }
  first?.focus();
  return Boolean(first);
}

export async function submitForm(form, action, { canSubmit = () => true, onError, statusNode } = {}) {
  const message = statusNode || form.querySelector('.form-status');
  const button = form.querySelector('[type="submit"]');
  let retryAfter = 0;
  button.disabled = true;
  form.setAttribute('aria-busy', 'true');
  formErrors(form, { fieldErrors: {} });
  status(message, '處理中…');
  try {
    const password = form.querySelector('input[name="password"][autocomplete="new-password"]');
    const policyError = password ? passwordError(password.value) : '';
    if (policyError) {
      const error = new Error(policyError);
      error.fieldErrors = { password: policyError };
      throw error;
    }
    const confirmation = form.querySelector('input[name="passwordConfirmation"]');
    const confirmationError =
      password && confirmation ? passwordConfirmationError(password.value, confirmation.value) : '';
    if (confirmationError) {
      const error = new Error(confirmationError);
      error.fieldErrors = { passwordConfirmation: confirmationError };
      throw error;
    }
    await action();
  } catch (error) {
    onError?.(error);
    retryAfter = error.retryAfter || 0;
    const focusedField = formErrors(form, error);
    status(message, error.message + (error.retryAfter ? ` 請於 ${error.retryAfter} 秒後再試。` : ''), true);
    if (!focusedField) message.focus();
  } finally {
    button.disabled = retryAfter > 0 || !canSubmit();
    if (retryAfter > 0)
      window.setTimeout(
        () => {
          button.disabled = !canSubmit();
        },
        Math.min(retryAfter, 86400) * 1000
      );
    form.removeAttribute('aria-busy');
  }
}

export function unsavedChanges(isDirty) {
  window.addEventListener('beforeunload', event => {
    if (isDirty()) {
      event.preventDefault();
      event.returnValue = '';
    }
  });
}

const initializedNavigation = new WeakSet();
export function initializeMenus() {
  for (const [selector, target, openClass] of [
    ['.menu-toggle', '.primary-nav', 'open'],
    ['.admin-mobile-menu', '.admin-sidebar', 'open'],
  ]) {
    const button = document.querySelector(selector);
    const menu = document.querySelector(target);
    if (!button || !menu || initializedNavigation.has(button)) continue;
    // 認證狀態刷新可能重複呼叫，但同一個導覽只綁定一次事件。
    initializedNavigation.add(button);
    const closedLabel = button.getAttribute('aria-label');
    // 手機選單被裁切或移出畫面時，不能留下看不見的 Tab 停駐點。
    const mobile = matchMedia('(max-width: 760px)');
    const synchronizeFocusability = () => {
      menu.inert = mobile.matches && button.getAttribute('aria-expanded') !== 'true';
    };
    mobile.addEventListener('change', synchronizeFocusability);
    synchronizeFocusability();
    const close = (restoreFocus = false) => {
      const returnFocus =
        restoreFocus &&
        button.getAttribute('aria-expanded') === 'true' &&
        menu.contains(document.activeElement);
      menu.classList.remove(openClass);
      button.setAttribute('aria-expanded', 'false');
      button.setAttribute('aria-label', closedLabel);
      synchronizeFocusability();
      if (returnFocus) button.focus();
    };
    button.addEventListener('click', () => {
      const open = button.getAttribute('aria-expanded') !== 'true';
      button.setAttribute('aria-expanded', String(open));
      button.setAttribute('aria-label', open ? closedLabel.replace(/^開啟/, '關閉') : closedLabel);
      menu.classList.toggle(openClass, open);
      synchronizeFocusability();
    });
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape') close(true);
    });
    menu.querySelectorAll('a').forEach(link => link.addEventListener('click', () => close()));
  }
  const backTop = document.querySelector('.back-top');
  if (!backTop || initializedNavigation.has(backTop)) return;
  initializedNavigation.add(backTop);
  backTop.addEventListener('click', event => {
    event.preventDefault();
    window.scrollTo({
      top: 0,
      behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
    });
  });
}

// 新增／編輯成功後會導回列表，所以把一次性的成功訊息暫存在本分頁的 sessionStorage，列表載入後再顯示。
const flashKey = 'yinghua-admin-flash';
export function flash(message) {
  try {
    sessionStorage.setItem(flashKey, message);
  } catch {
    /* 儲存不可用時僅略過提示，不影響儲存結果。 */
  }
}
export function consumeFlash() {
  try {
    const message = sessionStorage.getItem(flashKey);
    sessionStorage.removeItem(flashKey);
    return message || '';
  } catch {
    return '';
  }
}
