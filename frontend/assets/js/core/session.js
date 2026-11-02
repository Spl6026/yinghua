import { authApi } from '../api/auth-api.js';
import { routes } from './routes.js';
import { element, initializeMenus, toast } from './ui.js';
import { recoverSession } from './session-expiry.js';

// 只允許導向已建置的站內頁面，不把 query 直接當成 redirect URL，避免開放式重導向。
export function returnTo(kind, fallback = kind === 'admin' ? '/admin/index.html' : '/index.html') {
  const value = new URLSearchParams(location.search).get('returnTo');
  if (!value || !value.startsWith('/') || value.startsWith('//') || /[\\\x00-\x1f]/.test(value))
    return fallback;
  const url = new URL(value, location.origin);
  const publishedContentPath =
    kind === 'member' &&
    /^\/(?:places|films|news|offers)\/[a-z0-9]+(?:-[a-z0-9]+)*\.html$/.test(url.pathname);
  if (
    url.origin !== location.origin ||
    (!routes.has(url.pathname) && !publishedContentPath) ||
    /\/(login|register|forgot-password|reset-password|change-password)\.html$/.test(url.pathname)
  )
    return fallback;
  if ((kind === 'admin') !== url.pathname.startsWith('/admin/')) return fallback;
  return url.pathname + url.search;
}

export function loginLocation(kind) {
  const base = kind === 'admin' ? '/admin/login.html' : '/login.html';
  return base + '?returnTo=' + encodeURIComponent(location.pathname + location.search);
}

// 已登入者離開登入／註冊頁時，不額外加上首次登入必須改密碼的門檻。
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
  return (
    identity.permissions?.some(
      right => right.module === module && right.action === action && right.allowed
    ) ?? false
  );
}

// 檢視與新增權限各自獨立；共用登入、側欄與表單離開位置，但不擴大 API 權限。
const adminPages = {
  dashboard: ['index'],
  attractions: ['places', 'place-add', 'place-edit', '⌖　新增景點'],
  films: ['films', 'film-add', 'film-edit', '▣　新增映演場次'],
  news: ['news', 'news-add', 'news-edit', '▤　新增消息與活動'],
  offers: ['offers', 'offer-add', 'offer-edit', '♧　新增店家優惠'],
  routes: ['routes', 'route-add', 'route-edit', '⌁　建立推薦行程'],
  admins: ['admins', 'admin-add', 'admin-edit', '♙　新增管理員'],
  permissions: ['permissions'],
  members: ['members'],
  settings: ['settings'],
};

export function adminNavigation(identity, module) {
  const pages = adminPages[module];
  if (!pages) return null;
  if (hasPermission(identity, module, 'view')) return { href: `/admin/${pages[0]}.html` };
  if (pages[1] && hasPermission(identity, module, 'create'))
    return { href: `/admin/${pages[1]}.html`, label: pages[3] };
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
    if (pages[1] && path === `/admin/${pages[1]}.html` && hasPermission(identity, module, 'create'))
      return target;
    if (
      pages[2] &&
      path === `/admin/${pages[2]}.html` &&
      (hasPermission(identity, module, 'view') || ['news', 'offers'].includes(module)) &&
      hasPermission(identity, module, 'edit')
    )
      return target;
  }
  return fallback;
}

export async function requireSession(kind = 'member', allowChange = false) {
  try {
    const identity = await authApi(kind).me();
    return identity;
  } catch (error) {
    if (error.status === 401) {
      handleSessionExpiry(kind);
      return null;
    }
    throw error;
  }
}

let memberNavigationVersion = 0;

// 會員連結沿用原本的頁首文字樣式：名稱、我的收藏、更改密碼與登出排成一列。
// 頁首右側用看不見的「搜尋＋會員登入」撐住固定寬度，實際內容疊在上面靠右對齊，
// 因此確認中、未登入、已登入三種狀態寬度不同，主選單也不會左右移動。
function memberLinks(member) {
  const signOut = element('button', { type: 'button' }, '登出');
  signOut.addEventListener('click', async () => {
    signOut.disabled = true;
    try {
      await authApi().logout();
      location.assign('/index.html');
    } catch (error) {
      toast(error.message);
      signOut.disabled = false;
    }
  });
  return {
    signOut,
    children: [
      element('span', { className: 'member-name' }, member.displayName),
      element('a', { href: '/favorites.html' }, '我的收藏'),
      element('a', { href: '/change-password.html' }, '更改密碼'),
      signOut,
    ],
  };
}

// 手機寬度時頁首只留會員名稱，其餘連結放進漢堡選單，避免在 65px 高的頁首和品牌標誌擠在一起而折行。
function mobileAccountLinks(signOut) {
  const navigation = document.querySelector('#primary-nav');
  if (!navigation) return;
  navigation.querySelector('.nav-account')?.remove();
  const account = element('div', { className: 'nav-account' });
  const menuSignOut = element('button', { type: 'button' }, '登出');
  menuSignOut.addEventListener('click', () => signOut.click());
  account.append(
    element('a', { href: '/favorites.html' }, '我的收藏'),
    element('a', { href: '/change-password.html' }, '更改密碼'),
    menuSignOut
  );
  navigation.append(account);
}

// 公開頁允許瀏覽器的「上一頁快取」，還原時頁面不會重新執行；這時重新確認一次帳號，
// 才不會在別處登出後，按上一頁仍看到舊的會員名稱。
let restoreListener = false;
function refreshOnRestore() {
  if (restoreListener || typeof window === 'undefined' || !window.addEventListener) return;
  restoreListener = true;
  window.addEventListener('pageshow', event => {
    if (event.persisted) void memberNavigation();
  });
}

export async function memberNavigation() {
  const generation = ++memberNavigationVersion;
  refreshOnRestore();
  initializeMenus();
  const node = document.querySelector('#member-navigation');
  if (!node) return;
  node.setAttribute('aria-busy', 'true');
  node.className = '';
  node.replaceChildren(element('span', { className: 'sr-only', role: 'status' }, '確認帳號中…'));
  try {
    const { member } = await authApi().me();
    if (generation !== memberNavigationVersion) return;
    const { signOut, children } = memberLinks(member);
    node.replaceChildren(...children);
    node.className = 'member-menu';
    mobileAccountLinks(signOut);
  } catch (error) {
    if (generation !== memberNavigationVersion) return;
    document.querySelector('#primary-nav .nav-account')?.remove();
    if (error.status === 401) {
      node.replaceChildren(element('a', { className: 'login-button', href: '/login.html' }, '會員登入 ↗'));
    } else {
      const retry = element('button', { type: 'button', className: 'account-retry' }, '重新確認帳號');
      retry.addEventListener('click', () => void memberNavigation());
      node.replaceChildren(retry);
    }
  } finally {
    if (generation === memberNavigationVersion) node.removeAttribute('aria-busy');
  }
}

export function handleAdminError(error, message) {
  if (error.status === 401) {
    handleSessionExpiry('admin');
    message.textContent = '登入已到期，重新登入後請再儲存；未儲存內容仍保留。';
  } else {
    message.textContent = error.message;
    message.dataset.error = 'true';
    message.focus();
  }
}

export function handleSessionExpiry(kind) {
  if (!recoverSession(kind)) location.replace(loginLocation(kind));
}
