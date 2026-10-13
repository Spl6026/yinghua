import { authApi } from '../api/auth-api.js';
import { routes } from './routes.js';
import { element, initializeMenus, toast } from './ui.js';

// [E-04] 僅允許已建置的站內頁面；不把 query 直接當 redirect URL。
export function returnTo(kind, fallback = kind === 'admin' ? '/admin/index.html' : '/index.html') {
  const value = new URLSearchParams(location.search).get('returnTo');
  if (!value || !value.startsWith('/') || value.startsWith('//') || /[\\\x00-\x1f]/.test(value)) return fallback;
  const url = new URL(value, location.origin);
  if (url.origin !== location.origin || !routes.has(url.pathname) || /\/(login|register|forgot-password|reset-password|change-password)\.html$/.test(url.pathname)) return fallback;
  if ((kind === 'admin') !== url.pathname.startsWith('/admin/')) return fallback;
  return url.pathname + url.search;
}

export function loginLocation(kind) {
  const base = kind === 'admin' ? '/admin/login.html' : '/login.html';
  return base + '?returnTo=' + encodeURIComponent(location.pathname + location.search);
}

export function hasPermission(identity, module, action) {
  return identity.permissions?.some(right => right.module === module && right.action === action && right.allowed) ?? false;
}

export async function requireSession(kind = 'member', allowChange = false) {
  try {
    const identity = await authApi(kind).me();
    if (kind === 'admin' && identity.admin.mustChangePassword && !allowChange) {
      location.replace('/admin/change-password.html');
      return null;
    }
    return identity;
  } catch (error) {
    if (error.status === 401) { location.replace(loginLocation(kind)); return null; }
    throw error;
  }
}

export async function memberNavigation() {
  initializeMenus();
  const node = document.querySelector('#member-navigation');
  if (!node) return;
  try {
    const { member } = await authApi().me();
    node.replaceChildren(element('span', {}, member.displayName), element('a', { href: '/change-password.html' }, '更改密碼'));
    node.className = 'member-menu';
    const button = element('button', { type: 'button' }, '登出');
    button.addEventListener('click', async () => {
      button.disabled = true;
      try { await authApi().logout(); location.assign('/index.html'); }
      catch (error) { toast(error.message); button.disabled = false; }
    });
    node.append(button);
  } catch (error) {
    if (error.status !== 401) toast('暫時無法確認登入狀態，請稍後重新整理。');
  }
}

export function handleAdminError(error, message) {
  if (error.status === 401) location.replace(loginLocation('admin'));
  else if (error.code === 'PASSWORD_CHANGE_REQUIRED') location.replace('/admin/change-password.html');
  else { message.textContent = error.message; message.dataset.error = 'true'; message.focus(); }
}
