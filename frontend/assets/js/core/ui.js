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

export function toast(message) {
  const node = document.querySelector('#toast');
  if (!node) return;
  node.textContent = message;
  node.classList.add('show');
  window.setTimeout(() => node.classList.remove('show'), 3000);
}

export function formErrors(form, error) {
  form.querySelectorAll('.field-error').forEach(node => node.remove());
  form.querySelectorAll('[aria-invalid]').forEach(node => { node.removeAttribute('aria-invalid'); node.removeAttribute('aria-describedby'); });
  let first;
  for (const [name, message] of Object.entries(error.fieldErrors || {})) {
    // [U-09／E-20] 複合表單（例如整週營業時間）也須顯示具體錯誤並可取得焦點。
    const input = [...form.querySelectorAll('[data-field-name]')].find(node => node.dataset.fieldName === name) || form.elements.namedItem(name);
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
    const confirmationError = password && confirmation ? passwordConfirmationError(password.value, confirmation.value) : '';
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
    if (retryAfter > 0) window.setTimeout(() => { button.disabled = !canSubmit(); }, Math.min(retryAfter, 86400) * 1000);
    form.removeAttribute('aria-busy');
  }
}

export function unsavedChanges(isDirty) {
  window.addEventListener('beforeunload', event => {
    if (isDirty()) { event.preventDefault(); event.returnValue = ''; }
  });
}

const initializedNavigation = new WeakSet();
export function initializeMenus() {
  for (const [selector, target, openClass] of [['.menu-toggle', '.primary-nav', 'open'], ['.admin-mobile-menu', '.admin-sidebar', 'open']]) {
    const button = document.querySelector(selector);
    const menu = document.querySelector(target);
    if (!button || !menu || initializedNavigation.has(button)) continue;
    // [E-14] 認證狀態刷新可重複呼叫，但相同導覽只綁定一次。
    initializedNavigation.add(button);
    const closedLabel = button.getAttribute('aria-label');
    // [S 功能規格 G34／E-29] 被裁切／移出畫面的手機選單不可留下隱形 Tab 停駐點。
    const mobile = matchMedia('(max-width: 760px)');
    const synchronizeFocusability = () => { menu.inert = mobile.matches && button.getAttribute('aria-expanded') !== 'true'; };
    mobile.addEventListener('change', synchronizeFocusability);
    synchronizeFocusability();
    const close = (restoreFocus = false) => {
      const returnFocus = restoreFocus && button.getAttribute('aria-expanded') === 'true' && menu.contains(document.activeElement);
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
    document.addEventListener('keydown', event => { if (event.key === 'Escape') close(true); });
    menu.querySelectorAll('a').forEach(link => link.addEventListener('click', () => close()));
  }
  const backTop = document.querySelector('.back-top');
  if (!backTop || initializedNavigation.has(backTop)) return;
  initializedNavigation.add(backTop);
  backTop.addEventListener('click', event => {
    event.preventDefault();
    window.scrollTo({ top: 0, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
  });
}
