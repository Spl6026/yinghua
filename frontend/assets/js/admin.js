import { authApi } from './api/auth-api.js';
import {
  requireSession,
  hasPermission,
  handleAdminError,
  adminNavigation,
  adminDestination,
} from './core/session.js';
import { initializeMenus, status, element, toast, consumeFlash } from './core/ui.js';
import { initializeAdminPreview } from './components/admin-preview.js';

// 每個後台頁只載入自己的模組；原本在入口一次 import 全部頁面，任何一頁都要下載十幾個用不到的檔案。
// load 與確認登入同時開始下載，run 在確認權限後才執行頁面初始化。
const forms = name => [`${name}-add`, `${name}-edit`];
const formPages = (names, load, run) => Object.fromEntries(names.map(page => [page, { load, run }]));
const pageModules = {
  index: {
    load: () => import('./pages/operations-overview.js'),
    run: (m, identity) => m.initializeOperations(identity),
  },
  members: { load: () => import('./pages/member-directory.js'), run: m => m.initializeMemberDirectory() },
  admins: { load: () => import('./pages/admin-list.js'), run: (m, identity) => m.initializeAdmins(identity) },
  ...formPages(
    forms('admin'),
    () => import('./pages/admin-form.js'),
    (m, identity, _module, page) => m.initializeAdminForm(identity, page)
  ),
  permissions: {
    load: () => import('./pages/permissions.js'),
    run: (m, identity) => m.initializePermissions(identity),
  },
  films: { load: () => import('./pages/film-list.js'), run: (m, identity) => m.initializeFilmList(identity) },
  ...formPages(
    forms('film'),
    () => import('./pages/film-form.js'),
    (m, identity, _module, page) => m.initializeFilmForm(identity, page)
  ),
  places: {
    load: () => import('./pages/attraction-list.js'),
    run: (m, identity) => m.initializeAttractionList(identity),
  },
  ...formPages(
    forms('place'),
    () => import('./pages/attraction-form.js'),
    (m, identity, _module, page) => m.initializeAttractionForm(identity, page)
  ),
  settings: {
    load: () => Promise.all([import('./pages/general-settings.js'), import('./pages/seo-settings.js')]),
    run: async ([general, seo], identity) => {
      await general.initializeGeneralSettings(identity);
      await seo.initializeSeoSettings(identity);
    },
  },
  ...formPages(
    ['news', 'offers'],
    () => import('./pages/content-list.js'),
    (m, identity, module) => m.initializeContentList(identity, module)
  ),
  ...formPages(
    [...forms('news'), ...forms('offer')],
    () => import('./pages/content-form.js'),
    (m, identity, module, page) => m.initializeContentForm(identity, module, page)
  ),
  routes: {
    load: () => import('./pages/route-list.js'),
    run: (m, identity) => m.initializeRouteList(identity),
  },
  ...formPages(
    forms('route'),
    () => import('./pages/route-form.js'),
    (m, identity, _module, page) => m.initializeRouteForm(identity, page)
  ),
};
const currentPage = pageModules[document.body.dataset.page];
// 先開始下載；失敗留到真正執行時再丟出，交給下方統一的錯誤處理。
const pageModule = currentPage?.load();
pageModule?.catch(() => {});

initializeMenus();
// 瀏覽器原生驗證攔下下一次送出時，先清掉上一輪的伺服器錯誤，避免畫面同時出現過時的錯誤。
document.addEventListener(
  'invalid',
  event => {
    const form = event.target?.form;
    if (!form || form.dataset.clearingErrors) return;
    form.dataset.clearingErrors = 'true';
    queueMicrotask(() => delete form.dataset.clearingErrors);
    form.querySelectorAll('.field-error').forEach(node => node.remove());
    form.querySelectorAll('[aria-invalid="true"]').forEach(node => {
      node.removeAttribute('aria-invalid');
      node.removeAttribute('aria-describedby');
    });
    form.querySelectorAll('.form-status[data-error="true"]').forEach(node => {
      node.textContent = '';
      node.dataset.error = 'false';
    });
  },
  true
);
const main = document.querySelector('.admin-content');
const message = element('p', { role: 'status', tabindex: '-1', className: 'form-status' });
main.prepend(message);
main.setAttribute('aria-busy', 'true');
try {
  const identity = await requireSession('admin');
  if (identity) {
    document.querySelector('#admin-display-name').textContent = identity.admin.displayName;
    document.querySelector('#admin-account').textContent = identity.admin.account;
    document.querySelector('.admin-brand').href = adminDestination(identity);
    document.querySelectorAll('.admin-nav').forEach(link => {
      const entry = adminNavigation(identity, link.dataset.module);
      link.hidden = !entry;
      if (entry) {
        link.href = entry.href;
        if (entry.label) link.textContent = entry.label;
      }
      if (link.dataset.module === document.body.dataset.module) {
        link.classList.add('active');
        link.setAttribute('aria-current', 'page');
      }
    });
    document.querySelector('#admin-logout').addEventListener('click', async event => {
      event.currentTarget.disabled = true;
      try {
        await authApi('admin').logout();
        location.assign('/admin/login.html');
      } catch (error) {
        handleAdminError(error, message);
        document.querySelector('#admin-logout').disabled = false;
      }
    });
    await initializeAdminPreview(identity);
    const { module, action, page } = document.body.dataset;
    if (!hasPermission(identity, module, action)) {
      main.replaceChildren(
        element('h1', {}, '無法存取'),
        element('p', {}, '你尚未取得此功能的權限，請聯絡管理員。')
      );
    } else if (currentPage) await currentPage.run(await pageModule, identity, module, page);
    const saved = consumeFlash();
    if (saved) {
      status(message, saved);
      toast(saved);
    }
  }
} catch (error) {
  status(message, error.message, true);
  message.focus();
} finally {
  main.removeAttribute('aria-busy');
}
