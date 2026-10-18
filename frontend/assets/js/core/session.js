import { authApi } from '../api/auth-api.js';
import { routes } from './routes.js';
import { element, initializeMenus, toast } from './ui.js';

// [E-04] 僅允許已建置的站內頁面；不把 query 直接當 redirect URL。
export function returnTo(kind, fallback = kind === 'admin' ? '/admin/index.html' : '/index.html') {
  const value = new URLSearchParams(location.search).get('returnTo');
  if (!value || !value.startsWith('/') || value.startsWith('//') || /[\\\x00-\x1f]/.test(value)) return fallback;
  const url = new URL(value, location.origin);
  const publishedPlacePath = kind === 'member' && /^\/places\/[a-z0-9]+(?:-[a-z0-9]+)*\.html$/.test(url.pathname);
  if (url.origin !== location.origin || (!routes.has(url.pathname) && !publishedPlacePath) || /\/(login|register|forgot-password|reset-password|change-password)\.html$/.test(url.pathname)) return fallback;
  if ((kind === 'admin') !== url.pathname.startsWith('/admin/')) return fallback;
  return url.pathname + url.search;
}

export function loginLocation(kind) {
  const base = kind === 'admin' ? '/admin/login.html' : '/login.html';
  return base + '?returnTo=' + encodeURIComponent(location.pathname + location.search);
}

// [U-13／E-04] 已登入者離開登入／註冊頁，不增加首次登入改密碼門檻。
export async function redirectAuthenticated(kind = 'member') {
  try {
    const identity = await authApi(kind).me();
    location.replace(kind === 'admin' ? adminReturnTo(identity) : returnTo(kind));
    return true;
  } catch (error) {
    if (error.status === 401) return false;
    throw error;
  }
}

export function hasPermission(identity, module, action) {
  return identity.permissions?.some(right => right.module === module && right.action === action && right.allowed) ?? false;
}

// [S E27／E-14] 檢視與新增互相獨立；共用登入、側欄與表單離開位置，不擴大 API 權限。
const adminPages = {
  dashboard: ['index'],
  attractions: ['places', 'place-add', 'place-edit', '⌖　新增景點'],
  films: ['films', 'film-add', 'film-edit', '▣　新增映演場次'],
  news: ['news'],
  offers: ['offers'],
  routes: ['routes'],
  admins: ['admins', 'admin-add', 'admin-edit', '♙　新增管理員'],
  permissions: ['permissions'],
  members: ['members'],
  settings: ['settings'],
};

export function adminNavigation(identity, module) {
  const pages = adminPages[module];
  if (!pages) return null;
  if (hasPermission(identity, module, 'view')) return { href: `/admin/${pages[0]}.html` };
  if (pages[1] && hasPermission(identity, module, 'create')) return { href: `/admin/${pages[1]}.html`, label: pages[3] };
  return null;
}

export function adminDestination(identity) {
  for (const module of Object.keys(adminPages)) {
    const entry = adminNavigation(identity, module);
    if (entry) return entry.href;
  }
  return '/index.html';
}

export function adminReturnTo(identity) {
  const fallback = adminDestination(identity);
  const target = returnTo('admin', fallback);
  const path = new URL(target, location.origin).pathname;
  for (const [module, pages] of Object.entries(adminPages)) {
    if (path === `/admin/${pages[0]}.html` && hasPermission(identity, module, 'view')) return target;
    if (pages[1] && path === `/admin/${pages[1]}.html` && hasPermission(identity, module, 'create')) return target;
    if (pages[2] && path === `/admin/${pages[2]}.html`
      && hasPermission(identity, module, 'view') && hasPermission(identity, module, 'edit')) return target;
  }
  return fallback;
}

export async function requireSession(kind = 'member', allowChange = false) {
  try {
    const identity = await authApi(kind).me();
    return identity;
  } catch (error) {
    if (error.status === 401) { location.replace(loginLocation(kind)); return null; }
    throw error;
  }
}

let memberNavigationVersion = 0;
export async function memberNavigation() {
  const generation = ++memberNavigationVersion;
  initializeMenus();
  const node = document.querySelector('#member-navigation');
  if (!node) return;
  try {
    const { member } = await authApi().me();
    if (generation !== memberNavigationVersion) return;
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
    if (generation !== memberNavigationVersion) return;
    if (error.status === 401) {
      node.replaceChildren(element('a', { className: 'login-button', href: '/login.html' }, '會員登入 ↗'));
      node.className = '';
    } else toast('暫時無法確認登入狀態，請稍後重新整理。');
  }
}

export function handleAdminError(error, message) {
  if (error.status === 401) location.replace(loginLocation('admin'));
  else { message.textContent = error.message; message.dataset.error = 'true'; message.focus(); }
}
